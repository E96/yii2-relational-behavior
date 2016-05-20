<?php

namespace e96\behavior;


use RuntimeException;
use yii\base\Behavior;
use yii\base\ErrorException;
use yii\base\Object;
use yii\db\ActiveQuery;
use yii\db\ActiveRecord;
use yii\db\Query;
use yii\helpers\ArrayHelper;

/**
 * Saves Many-to-many relations
 */
class RelationalBehavior extends Behavior
{
    const EVENT_AFTER_RELATION_UPDATE = 'manyToManyRelationUpdate';

    /**
     * @var ActiveRecord
     */
    public $owner;

    /**
     * @var array
     */
    protected $oldRelations = [];

    /**
     * @var string
     */
    public $sortSettings = null;

    /**
     * @var array
     */
    protected $relationalRawData = null;

    public function attach($owner)
    {
        if (!($owner instanceof ActiveRecord)) {
            throw new RuntimeException('Owner must be instance of yii\db\ActiveRecord');
        }
        if (count($owner->getTableSchema()->primaryKey) > 1) {
            throw new RuntimeException('RelationalBehavior doesn\'t support composite primary keys');
        }

        parent::attach($owner);
    }

    public function events()
    {
        return [
            ActiveRecord::EVENT_AFTER_INSERT => 'saveRelations',
            ActiveRecord::EVENT_AFTER_UPDATE => 'saveRelations',
        ];
    }

    public function canSetProperty($name, $checkVars = true)
    {
        $getter = 'get' . $name;
        if (method_exists($this->owner, $getter) && $this->owner->$getter() instanceof ActiveQuery) {
            return true;
        }

        return parent::canSetProperty($name, $checkVars);
    }

    /**
     * @param ActiveQuery $activeQuery
     * @return array
     * @throws ErrorException
     */
    protected function parseQuery($activeQuery)
    {
        /* @var $viaQuery ActiveQuery */
        if ($activeQuery->via instanceof ActiveQuery) {
            $viaQuery = $activeQuery->via;
        } elseif (is_array($activeQuery->via)) {
            $viaQuery = $activeQuery->via[1];
        } else {
            throw new RuntimeException('Unknown via type');
        }

        $junctionTable = reset($viaQuery->from);
        $primaryModelColumn = array_keys($viaQuery->link)[0];
        $relatedModelColumn = reset($activeQuery->link);

        return [$junctionTable, $primaryModelColumn, $relatedModelColumn];
    }

    public function __set($name, $value)
    {

        $this->relationalRawData[$name] = $value;

        if (is_array($value) && count($value) > 0 && !($value[0] instanceof Object) ||
            !is_array($value) && !($value instanceof Object)
        ) {
            $getter = 'get' . $name;
            /** @var ActiveQuery $activeQuery */
            $activeQuery = $this->owner->$getter();

            /* @var $modelClass ActiveRecord */
            $modelClass = $activeQuery->modelClass;
            $value = $modelClass::findAll($value);

            if (!empty($activeQuery->via)) {
                list($junctionTable, $primaryModelColumn, $relatedModelColumn) = $this->parseQuery($activeQuery);
                $query = new Query([
                    'select' => [$relatedModelColumn],
                    'from' => [$junctionTable],
                    'where' => [$primaryModelColumn => $this->owner->primaryKey]
                ]);
                $this->oldRelations[$name] = $query->createCommand()->queryColumn();
            }
        }

        $this->owner->populateRelation($name, $value);
    }

    public function saveRelations($event)
    {
        /** @var ActiveRecord $model */
        $model = $event->sender;
        $position = 0;

        $relatedRecords = $model->getRelatedRecords();
        foreach ($relatedRecords as $relationName => $relationRecords) {
            $activeQuery = $model->getRelation($relationName);
            if (!empty($activeQuery->via)) { // works only for many-to-many relation
                list($junctionTable, $primaryModelColumn, $relatedModelColumn) = $this->parseQuery($activeQuery);
                $junctionRows = [];
                $relationPks = ArrayHelper::getColumn($relationRecords, array_keys($activeQuery->link)[0], false);
                $passedRecords = count($relationPks);

                if ($this->sortSettings[$relationName] &&
                    isset($this->sortSettings[$relationName]['sortColumn']) &&
                    isset($this->relationalRawData[$relationName])
                ) {
                    $relationPks = $this->relationalRawData[$relationName];
                } else {
                    $relationPks = array_filter($relationPks);
                }

                $savedRecords = count($relationPks);
                if ($passedRecords != $savedRecords) {
                    throw new RuntimeException('All relation records must be saved. There are incorrect PKs.');
                }
                foreach ($relationPks as $relationPk) {

                    $position++;

                    if (isset($this->sortSettings[$relationName]['sortColumn'])) {
                        $junctionRows[] = [$model->primaryKey, $relationPk, $position];
                    } else {
                        $junctionRows[] = [$model->primaryKey, $relationPk];
                    }
                }

                $model->getDb()->transaction(function () use (
                    $junctionTable,
                    $primaryModelColumn,
                    $relatedModelColumn,
                    $junctionRows,
                    $model,
                    $relationName
                ) {
                    $db = $model->getDb();
                    $db->createCommand()->delete($junctionTable,
                        [$primaryModelColumn => $model->primaryKey])->execute();
                    if (!empty($junctionRows)) {

                        if (isset($this->sortSettings[$relationName]['sortColumn'])) {
                            $db->createCommand()->batchInsert($junctionTable,
                                [$primaryModelColumn, $relatedModelColumn, $this->sortSettings[$relationName]['sortColumn']],
                                $junctionRows)->execute();
                        } else {
                            $db->createCommand()->batchInsert($junctionTable,
                                [$primaryModelColumn, $relatedModelColumn],
                                $junctionRows)->execute();
                        }

                    }
                });
            }
        }

        $model->trigger(self::EVENT_AFTER_RELATION_UPDATE, new AfterRelationUpdateEvent([
            'oldRelations' => $this->oldRelations
        ]));
    }
}
