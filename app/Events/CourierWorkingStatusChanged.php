<?php

namespace App\Events;

use Illuminate\Foundation\Events\Dispatchable;

/** A courier started or ended a shift. */
class CourierWorkingStatusChanged
{
    use Dispatchable;

    public function __construct(
        public int $companyId,
        public int $courierId,
        public bool $working,
    ) {}
}
