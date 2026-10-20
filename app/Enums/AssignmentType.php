<?php

namespace App\Enums;

enum AssignmentType: string
{
    case Pool = 'pool';
    case CapacityAware = 'capacity_aware';
    case CapacityUnaware = 'capacity_unaware';
    case Slot = 'slot';
    case SelectedCourier = 'selected_courier';

    public function label(): string
    {
        return __('tasks.assignment.'.$this->value);
    }
}
