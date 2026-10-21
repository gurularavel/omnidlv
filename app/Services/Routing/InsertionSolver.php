<?php

namespace App\Services\Routing;

use App\Support\GeoPoint;

/**
 * Built-in solver for when VROOM is not running: cheapest insertion with capacity, max_tasks
 * and time windows. Jobs are placed one by one (highest priority, then tightest deadline
 * first) where they add the least travel time without breaking any constraint. It gives
 * sensible routes for a few hundred stops; VROOM finds shorter ones.
 *
 * Reads and writes the VROOM format, so callers do not care which solver ran.
 */
class InsertionSolver implements Solver
{
    /** @var list<GeoPoint> */
    private array $points = [];

    private array $index = [];

    private array $durations = [];

    private array $distances = [];

    public function __construct(private readonly Router $router) {}

    public function name(): string
    {
        return 'insertion';
    }

    public function solve(array $problem): array
    {
        $vehicles = array_values($problem['vehicles'] ?? []);
        $jobs = [];
        foreach ($problem['jobs'] ?? [] as $job) {
            $jobs[$job['id']] = $job;
        }

        $this->buildMatrix($vehicles, $jobs, $vehicles[0]['profile'] ?? 'car');

        $routes = array_fill(0, count($vehicles), []);
        $unassigned = [];

        foreach ($this->order($jobs) as $jobId) {
            $best = null;

            foreach ($vehicles as $v => $vehicle) {
                $current = $this->evaluate($vehicle, $routes[$v], $jobs);

                for ($pos = 0, $n = count($routes[$v]); $pos <= $n; $pos++) {
                    $candidate = $routes[$v];
                    array_splice($candidate, $pos, 0, [$jobId]);
                    $result = $this->evaluate($vehicle, $candidate, $jobs);

                    if ($result === null) {
                        continue;
                    }

                    $delta = $result['cost'] - $current['cost'];
                    if ($best === null || $delta < $best['delta']) {
                        $best = ['delta' => $delta, 'vehicle' => $v, 'route' => $candidate];
                    }
                }
            }

            if ($best === null) {
                $unassigned[] = ['id' => $jobId, 'type' => 'job', 'location' => $jobs[$jobId]['location']];
            } else {
                $routes[$best['vehicle']] = $best['route'];
            }
        }

        return $this->output($vehicles, $routes, $jobs, $unassigned);
    }

    /** Priority first, then the earliest deadline. */
    private function order(array $jobs): array
    {
        $ids = array_keys($jobs);
        usort($ids, function ($a, $b) use ($jobs) {
            $pa = $jobs[$a]['priority'] ?? 0;
            $pb = $jobs[$b]['priority'] ?? 0;

            return $pb <=> $pa ?: $this->deadline($jobs[$a]) <=> $this->deadline($jobs[$b]);
        });

        return $ids;
    }

    private function deadline(array $job): int
    {
        return isset($job['time_windows'][0][1]) ? max(array_column($job['time_windows'], 1)) : PHP_INT_MAX;
    }

    private function buildMatrix(array $vehicles, array $jobs, string $profile): void
    {
        $add = function (array $lngLat) {
            $key = $lngLat[0].','.$lngLat[1];
            if (! isset($this->index[$key])) {
                $this->index[$key] = count($this->points);
                $this->points[] = new GeoPoint((float) $lngLat[1], (float) $lngLat[0]);
            }
        };

        foreach ($vehicles as $vehicle) {
            isset($vehicle['start']) && $add($vehicle['start']);
            isset($vehicle['end']) && $add($vehicle['end']);
        }
        foreach ($jobs as $job) {
            $add($job['location']);
        }

        if ($this->points === []) {
            return;
        }

        $table = $this->router->table($this->points, $profile);
        $this->durations = $table['durations'];
        $this->distances = $table['distances'];
    }

    private function at(array $lngLat): int
    {
        return $this->index[$lngLat[0].','.$lngLat[1]];
    }

    /**
     * Walks a route and returns its cost and steps, or null when a constraint breaks.
     *
     * @return array{cost: int, steps: list<array>, duration: int, distance: int, service: int, setup: int, waiting: int}|null
     */
    private function evaluate(array $vehicle, array $route, array $jobs): ?array
    {
        if (isset($vehicle['max_tasks']) && count($route) > $vehicle['max_tasks']) {
            return null;
        }

        if (isset($vehicle['capacity']) && ! $this->fits($vehicle['capacity'], $route, $jobs)) {
            return null;
        }

        $speed = (float) ($vehicle['speed_factor'] ?? 1) ?: 1.0;
        [$open, $close] = $vehicle['time_window'] ?? [0, PHP_INT_MAX];
        $time = $open;
        $here = isset($vehicle['start']) ? $this->at($vehicle['start']) : null;
        $travel = $distance = $service = $setup = $waiting = 0;
        $steps = [];

        if ($here !== null) {
            $steps[] = ['type' => 'start', 'location' => $vehicle['start'], 'arrival' => $time, 'duration' => 0, 'distance' => 0];
        }

        foreach ($route as $jobId) {
            $job = $jobs[$jobId];
            $to = $this->at($job['location']);
            $leg = $here === null ? 0 : (int) round($this->durations[$here][$to] / $speed);
            $legDistance = $here === null ? 0 : $this->distances[$here][$to];

            if ($here === null) {
                // No start point: the route begins at its first job, as in VROOM.
                $time = max($time, $this->windowStart($job, $time) ?? $time);
                $steps[] = ['type' => 'start', 'location' => $job['location'], 'arrival' => $time, 'duration' => 0, 'distance' => 0];
            }

            $arrival = $time + $leg;
            $start = $this->windowStart($job, $arrival);

            if ($start === null) {
                return null;
            }

            $wait = $start - $arrival;
            $jobSetup = ($here === $to) ? 0 : (int) ($job['setup'] ?? 0);
            $jobService = (int) ($job['service'] ?? 0);

            $travel += $leg;
            $distance += $legDistance;
            $waiting += $wait;
            $setup += $jobSetup;
            $service += $jobService;

            $steps[] = [
                'type' => 'job', 'id' => $jobId, 'job' => $jobId, 'location' => $job['location'],
                'arrival' => $arrival, 'waiting_time' => $wait, 'setup' => $jobSetup, 'service' => $jobService,
                'duration' => $travel, 'distance' => $distance,
            ];

            $time = $start + $jobSetup + $jobService;
            $here = $to;
        }

        if (isset($vehicle['end']) && $here !== null) {
            $to = $this->at($vehicle['end']);
            $leg = (int) round($this->durations[$here][$to] / $speed);
            $travel += $leg;
            $distance += $this->distances[$here][$to];
            $time += $leg;
            $steps[] = ['type' => 'end', 'location' => $vehicle['end'], 'arrival' => $time, 'duration' => $travel, 'distance' => $distance];
        } elseif ($route !== []) {
            $steps[] = ['type' => 'end', 'location' => end($steps)['location'], 'arrival' => $time, 'duration' => $travel, 'distance' => $distance];
        }

        if ($time > $close) {
            return null;
        }

        return ['cost' => $travel, 'steps' => $route === [] ? [] : $steps, 'duration' => $travel, 'distance' => $distance,
            'service' => $service, 'setup' => $setup, 'waiting' => $waiting];
    }

    /** Earliest moment the job can start at or after $arrival, or null if every window closed. */
    private function windowStart(array $job, int $arrival): ?int
    {
        if (empty($job['time_windows'])) {
            return $arrival;
        }

        foreach ($job['time_windows'] as [$from, $to]) {
            if ($arrival <= $to) {
                return max($arrival, $from);
            }
        }

        return null;
    }

    private function fits(array $capacity, array $route, array $jobs): bool
    {
        foreach ($capacity as $dim => $limit) {
            $delivery = $pickup = 0;
            foreach ($route as $jobId) {
                $delivery += $jobs[$jobId]['delivery'][$dim] ?? 0;
                $pickup += $jobs[$jobId]['pickup'][$dim] ?? 0;
            }

            if ($delivery > $limit || $pickup > $limit) {
                return false;
            }
        }

        return true;
    }

    private function output(array $vehicles, array $routes, array $jobs, array $unassigned): array
    {
        $out = [];
        $summary = ['cost' => 0, 'routes' => 0, 'unassigned' => count($unassigned), 'setup' => 0, 'service' => 0,
            'duration' => 0, 'waiting_time' => 0, 'priority' => 0, 'distance' => 0, 'computing_times' => ['loading' => 0, 'solving' => 0]];

        foreach ($routes as $v => $route) {
            if ($route === []) {
                continue;
            }

            $r = $this->evaluate($vehicles[$v], $route, $jobs);
            $out[] = [
                'vehicle' => $vehicles[$v]['id'],
                'cost' => $r['cost'],
                'setup' => $r['setup'],
                'service' => $r['service'],
                'duration' => $r['duration'],
                'waiting_time' => $r['waiting'],
                'priority' => array_sum(array_map(fn ($id) => $jobs[$id]['priority'] ?? 0, $route)),
                'distance' => $r['distance'],
                'steps' => $r['steps'],
            ];

            $summary['routes']++;
            foreach (['cost', 'setup', 'service', 'duration', 'distance'] as $k) {
                $summary[$k] += $r[$k];
            }
            $summary['waiting_time'] += $r['waiting'];
            $summary['priority'] += end($out)['priority'];
        }

        return ['code' => 0, 'summary' => $summary, 'unassigned' => $unassigned, 'routes' => $out, 'solver' => $this->name()];
    }
}
