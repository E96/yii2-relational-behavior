<?php

namespace e96\behavior;


use yii\base\Event;

class AfterRelationUpdateEvent extends Event
{
    public $oldRelations;
}