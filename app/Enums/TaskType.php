<?php

namespace App\Enums;

enum TaskType: string
{
    case DropOff = 'drop_off';
    case Pickup = 'pickup';

    public function label(): string
    {
        return __('tasks.types.'.$this->value);
    }
}
