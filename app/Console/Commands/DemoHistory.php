<?php

namespace App\Console\Commands;

use App\Enums\Channel;
use App\Models\Company;
use App\Models\Hub;
use App\Models\Status;
use App\Models\Task;
use App\Models\User;
use App\Services\TaskService;
use App\Support\CurrentCompany;
use Database\Seeders\DemoSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

/**
 * Finished tasks for the past weeks, so the demo company's analytics have something to show.
 * Each day gets a believable mix: mostly delivered, some late, a few failed or cancelled,
 * busiest around lunch and the evening. Tagged "demo-history" so --fresh can replace them.
 */
class DemoHistory extends Command
{
    protected $signature = 'demo:history {--days=30 : How many past days to fill} {--company= : Company id (default: the demo company)} {--fresh : Replace earlier demo history}';

    protected $description = 'Create finished demo tasks for past days (analytics demo data)';

    /** Hour of day (company time) => weight. */
    private const HOURS = [9 => 2, 10 => 3, 11 => 4, 12 => 6, 13 => 7, 14 => 5, 15 => 4, 16 => 4, 17 => 5, 18 => 7, 19 => 6, 20 => 4, 21 => 2];

    public function handle(TaskService $tasks, CurrentCompany $tenant): int
    {
        $company = $this->option('company')
            ? Company::query()->findOrFail($this->option('company'))
            : User::query()->where('email', 'demo@omnidlv.test')->firstOrFail()->activeCompany;
        $days = max(1, min(365, (int) $this->option('days')));

        $created = $tenant->run($company, function () use ($tasks, $company, $days) {
            if ($this->option('fresh')) {
                Task::query()->whereJsonContains('tags', 'demo-history')->forceDelete();
            }

            $hubs = Hub::query()->get();
            $couriers = User::query()->couriers()->with('teams:teams.id')->get();
            if ($hubs->isEmpty() || $couriers->isEmpty()) {
                $this->error('The company needs at least one hub and one courier.');

                return 0;
            }

            $status = fn (string $slug) => Status::query()->where('slug', $slug)->firstOrFail();
            $outcomes = [
                ['completed', $status('delivered'), 80], ['completed', $status('left_at_door'), 6], ['completed', $status('delivered_to_other'), 3],
                ['failed', $status('recipient_not_available'), 4], ['failed', $status('wrong_address'), 2], ['failed', $status('refused'), 1],
                ['cancelled', $status('cancelled_by_recipient'), 3], ['cancelled', $status('cancelled'), 1],
            ];
            $tz = $company->timezone;
            $n = 0;

            for ($d = $days; $d >= 1; $d--) {
                $day = Carbon::now($tz)->startOfDay()->subDays($d);
                $volume = ($day->isWeekend() ? 14 : 22) + random_int(-4, 6);

                for ($i = 0; $i < $volume; $i++) {
                    [$street, $lat, $lng] = DemoSeeder::ADDRESSES[array_rand(DemoSeeder::ADDRESSES)];
                    $endsAt = $day->copy()->setTime($this->pick(self::HOURS), random_int(0, 59));
                    [$category, $finalStatus] = $outcomes[$this->pick(array_column($outcomes, 2))];
                    $courier = $couriers->random();
                    $late = $category === 'completed' && random_int(1, 100) <= 12;
                    $startedAt = $endsAt->copy()->subMinutes(random_int(25, 70));
                    $endedAt = $late ? $endsAt->copy()->addMinutes(random_int(5, 40)) : $endsAt->copy()->subMinutes(random_int(0, 30));
                    $cash = random_int(1, 3) === 1;

                    $task = $tasks->create([
                        'hub_id' => $hubs->random()->id,
                        'integration_id' => sprintf('HIST-%s-%03d', $day->format('ymd'), $i),
                        'starts_at' => $endsAt->copy()->subHours(2)->utc()->toIso8601String(),
                        'ends_at' => $endsAt->copy()->utc()->toIso8601String(),
                        'destination' => ['address' => $street.', Bakı', 'lat' => $lat, 'lng' => $lng],
                        'assignment' => ['type' => 'pool'],
                        'tags' => ['demo-history'],
                        'payment_type' => $cash ? 'cash' : 'prepaid',
                        'amount' => $cash ? random_int(15, 180) : null,
                        'currency' => $company->currency,
                    ], Channel::Api);

                    $task->forceFill([
                        'status_id' => $finalStatus->id,
                        'status_category_slug' => $category,
                        'status_at' => $endedAt->copy()->utc(),
                        'courier_id' => $category === 'cancelled' && random_int(0, 1) ? null : $courier->id,
                        'team_id' => $courier->teams->first()?->id,
                        'started_at' => $category === 'cancelled' ? null : $startedAt->copy()->utc(),
                        'ended_at' => $endedAt->copy()->utc(),
                        'was_delayed' => $late,
                        'was_finalized_duly' => $category === 'completed' && ! $late,
                        'distance' => random_int(1500, 9000),
                        'rating' => $category === 'completed' && random_int(1, 4) === 1 ? $this->pick([5 => 7, 4 => 2, 3 => 1]) : 0,
                        'created_at' => $startedAt->copy()->subHours(3)->utc(),
                    ])->save();
                    $n++;
                }
            }

            return $n;
        });

        $this->info("Created {$created} finished tasks over {$days} days.");

        return self::SUCCESS;
    }

    /** @param array<int|string, int> $weights key => weight */
    private function pick(array $weights): mixed
    {
        $roll = random_int(1, array_sum($weights));
        foreach ($weights as $key => $weight) {
            if (($roll -= $weight) <= 0) {
                return $key;
            }
        }

        return array_key_first($weights);
    }
}
