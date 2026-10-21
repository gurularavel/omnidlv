<?php

namespace App\Services\Routing;

/**
 * Vehicle routing in VROOM's input and output format
 * (https://github.com/VROOM-Project/vroom/blob/master/docs/API.md), which is also what
 * QDelivery's /routing/solve accepts. Coordinates are [lng, lat].
 */
interface Solver
{
    public function solve(array $problem): array;

    public function name(): string;
}
