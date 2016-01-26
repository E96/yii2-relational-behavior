<?php

namespace e96\behavior;


use yii\base\Event;

class AfterRelationUpdateEvent extends Event
{
    /**
     * @var mixed Key is relation name. Value is array of primary keys
     */
    public $oldRelations;
}