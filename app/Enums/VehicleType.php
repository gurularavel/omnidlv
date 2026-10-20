<?php

namespace App\Enums;

enum VehicleType: string
{
    case Foot = 'foot';
    case Bicycle = 'bicycle';
    case Motorcycle = 'motorcycle';
    case Car = 'car';
    case Minivan = 'minivan';
    case Truck = 'truck';

    public function label(): string
    {
        return __('vehicles.'.$this->value);
    }

    public function icon(): string
    {
        return asset('images/vehicle-icons/'.$this->value.'.svg');
    }

    /** OSRM/VROOM profile used for this vehicle. */
    public function routingProfile(): string
    {
        return match ($this) {
            self::Foot, self::Bicycle => 'car',
            self::Truck => 'truck',
            default => 'car',
        };
    }
}
