<?php

namespace App\Services\Routing;

use Illuminate\Support\Facades\Http;
use RuntimeException;

/** Self-hosted vroom-express. VROOM reads travel times from its own OSRM per profile. */
class VroomSolver implements Solver
{
    public function __construct(private readonly string $url, private readonly int $timeout = 60) {}

    public function solve(array $problem): array
    {
        $response = Http::timeout($this->timeout)->asJson()->post(rtrim($this->url, '/'), $problem);
        $json = $response->json();

        if (! $response->successful() || ($json['code'] ?? 1) !== 0) {
            throw new RuntimeException('VROOM failed: '.($json['error'] ?? $response->status()));
        }

        return $json;
    }

    public function name(): string
    {
        return 'vroom';
    }
}
