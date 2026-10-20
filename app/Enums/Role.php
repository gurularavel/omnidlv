<?php

namespace App\Enums;

enum Role: string
{
    case Owner = 'owner';
    case Admin = 'admin';
    case Dispatcher = 'dispatcher';
    case Courier = 'courier';

    public function label(): string
    {
        return __('roles.'.$this->value);
    }

    public function canManage(): bool
    {
        return in_array($this, [self::Owner, self::Admin], true);
    }

    public function canDispatch(): bool
    {
        return $this !== self::Courier;
    }
}
