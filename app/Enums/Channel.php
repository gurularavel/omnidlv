<?php

namespace App\Enums;

enum Channel: string
{
    case Panel = 'panel';
    case Api = 'api';
    case Import = 'import';
    case Courier = 'courier';
    case System = 'system';
    case Tracking = 'tracking';
}
