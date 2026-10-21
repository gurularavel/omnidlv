<?php

namespace Tests\Feature\Api;

use App\Enums\Channel;
use App\Enums\StatusCategory;
use App\Models\Company;
use App\Models\CourierDetail;
use App\Models\CourierLocation;
use App\Models\Hub;
use App\Models\Task;
use App\Models\TaskPlan;
use App\Models\Team;
use App\Models\User;
use App\Services\StatusMachine;
use App\Services\TaskService;
use App\Services\TeamService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Tests\TestCase;

class PlanningApiTest extends TestCase
{
    private const SLOT = '2026102112001400';

    protected function setUp(): void
    {
        parent::setUp();
        // 21 Oct 2026, 09:00 Baku; slot 12:00–14:00 Baku = 08:00–10:00 UTC.
        $this->travelTo(Carbon::parse('2026-10-21 05:00:00', 'UTC'));
    }

    public function test_distance_basic_and_validation(): void
    {
        $company = $this->makeCompany();

        $this->withHeaders($this->apiHeaders($company))->postJson('/api/integration/routing/distance', [
            'pickup_lat' => 40.377700, 'pickup_lng' => 49.843600,
            'destination_lat' => 40.409300, 'destination_lng' => 49.867100,
        ])->assertOk()
            ->assertJsonPath('type', 'haversine')
            ->assertJson(fn ($json) => $json->where('distance', fn ($d) => $d > 3500 && $d < 4500)->etc());

        $this->withHeaders($this->apiHeaders($company))->postJson('/api/integration/routing/distance', [])
            ->assertUnprocessable()->assertJsonValidationErrors(['pickup_lat', 'destination_lng']);
    }

    public function test_solve_respects_capacity_and_time_windows(): void
    {
        $company = $this->makeCompany();
        $hub = [49.8436, 40.3777];

        $response = $this->withHeaders($this->apiHeaders($company))->postJson('/api/integration/routing/solve', [
            'vehicles' => [
                ['id' => 1, 'profile' => 'az-car', 'start' => $hub, 'end' => $hub, 'capacity' => [2]],
                ['id' => 2, 'profile' => 'az-car', 'start' => $hub, 'end' => $hub, 'capacity' => [2]],
            ],
            'jobs' => [
                ['id' => 11, 'location' => [49.85, 40.38], 'delivery' => [1]],
                ['id' => 12, 'location' => [49.86, 40.39], 'delivery' => [1]],
                ['id' => 13, 'location' => [49.87, 40.40], 'delivery' => [1]],
                ['id' => 14, 'location' => [49.88, 40.41], 'delivery' => [1]],
                // Window already closed before anyone can get there.
                ['id' => 15, 'location' => [49.90, 40.45], 'delivery' => [1], 'time_windows' => [[0, 10]]],
            ],
        ])->assertOk();

        $this->assertSame('insertion', $response->json('solver'));
        $this->assertSame([15], array_column($response->json('unassigned'), 'id'));

        $routes = $response->json('routes');
        $served = collect($routes)->flatMap(fn ($r) => collect($r['steps'])->where('type', 'job')->pluck('id'))->sort()->values()->all();
        $this->assertSame([11, 12, 13, 14], $served);
        foreach ($routes as $route) {
            $this->assertLessThanOrEqual(2, collect($route['steps'])->where('type', 'job')->count());
            $this->assertSame('start', $route['steps'][0]['type']);
            $this->assertSame('end', end($route['steps'])['type']);
        }
    }

    public function test_capacity_aware_tasks_go_to_the_least_loaded_courier_and_wait_when_all_are_full(): void
    {
        $company = $this->makeCompany();
        $hub = $this->makeHub($company, ['lat' => 40.3777, 'lng' => 49.8436]);
        [$a, $b] = $this->couriersOnShift($company, $hub, 2, capacity: 1);

        $first = $this->createTask($company, $hub, ['assignment' => ['type' => 'capacity_aware']]);
        $second = $this->createTask($company, $hub, ['assignment' => ['type' => 'capacity_aware']]);
        $third = $this->createTask($company, $hub, ['assignment' => ['type' => 'capacity_aware']]);

        $this->assertEqualsCanonicalizing([$a->id, $b->id], [$first->courier_id, $second->courier_id]);
        $this->assertNull($third->courier_id);
        $this->assertSame('created', $third->status_category_slug->value);

        // One courier is freed; the waiting task is picked up by the scheduler.
        $this->asTenant($company, fn () => app(StatusMachine::class)->toCategory($first->fresh(), StatusCategory::Cancelled, Channel::Panel));
        $this->artisan('tasks:assign-pending')->assertSuccessful();

        $this->assertSame($first->courier_id, $third->fresh()->courier_id);
    }

    public function test_capacity_unaware_ignores_capacity_but_not_the_shift(): void
    {
        $company = $this->makeCompany();
        $hub = $this->makeHub($company);
        [$courier] = $this->couriersOnShift($company, $hub, 1, capacity: 1);
        $resting = $this->makeCourier($company);
        $this->joinHub($company, $resting, $hub);

        $tasks = collect(range(1, 3))->map(fn () => $this->createTask($company, $hub, [
            'assignment' => ['type' => 'capacity_unaware'],
            'assignment_options' => ['courier_duty_status' => 'all'],
        ]));

        $this->assertSame([$courier->id], $tasks->pluck('courier_id')->unique()->values()->all());
    }

    public function test_closing_a_slot_builds_one_route_per_courier_with_etas(): void
    {
        $company = $this->makeCompany(['timezone' => 'Asia/Baku']);
        $hub = $this->makeHub($company, ['lat' => 40.3777, 'lng' => 49.8436]);
        $this->couriersOnShift($company, $hub, 2, capacity: 3);
        $tasks = $this->slotTasks($company, $hub, 5);
        $headers = $this->apiHeaders($company);

        $this->withHeaders($headers)->getJson('/api/integration/task-slots')
            ->assertOk()
            ->assertJsonPath('data.0.slot', self::SLOT)
            ->assertJsonPath('data.0.tasks_count', 5)
            ->assertJsonPath('data.0.hub.id', $hub->id);

        $result = $this->withHeaders($headers)->postJson('/api/integration/task-slots/'.self::SLOT.'/close', ['hub_id' => $hub->id, 'shift' => 'working'])
            ->assertOk()->json();

        $this->assertCount(2, $result['plans']);
        $this->assertSame(5, $result['assigned']);
        $this->assertSame([], $result['unassigned']);

        foreach (TaskPlan::withoutGlobalScopes()->get() as $plan) {
            $stops = Task::withoutGlobalScopes()->where('plan_id', $plan->id)->orderBy('route_order')->get();
            $this->assertLessThanOrEqual(3, $stops->count());
            $this->assertSame(range(1, $stops->count()), $stops->pluck('route_order')->all());
            $this->assertTrue($stops->every(fn (Task $t) => $t->courier_id === $plan->courier_id && $t->status_category_slug->value === 'assigned'));
            // ETAs run forward and never before the slot opens.
            $etas = $stops->pluck('eta_at')->map->getTimestamp()->all();
            $sorted = $etas;
            sort($sorted);
            $this->assertSame($sorted, $etas);
            $this->assertGreaterThanOrEqual(Carbon::parse('2026-10-21 08:00:00', 'UTC')->getTimestamp(), $etas[0]);
            $this->assertNotEmpty($plan->geometry);
        }

        $this->withHeaders($headers)->getJson('/api/integration/task-slots')->assertOk()->assertJsonCount(0, 'data');

        $planId = $result['plans'][0];
        $this->withHeaders($headers)->getJson("/api/integration/task-plans/{$planId}")
            ->assertOk()
            ->assertJsonPath('status_slug', 'planned')
            ->assertJsonPath('slot', self::SLOT)
            ->assertJsonStructure(['tasks' => [['id', 'tracking_code', 'eta_at', 'route_order', 'status', 'hub', 'status_logs']]]);

        $this->withHeaders($headers)->getJson('/api/integration/task-plans?date=2026-10-21')->assertOk()->assertJsonCount(2, 'data');
        $this->assertCount(5, $tasks);
    }

    public function test_tasks_that_do_not_fit_follow_the_unassigned_strategy(): void
    {
        $company = $this->makeCompany();
        $hub = $this->makeHub($company, ['lat' => 40.3777, 'lng' => 49.8436]);
        $this->couriersOnShift($company, $hub, 1, capacity: 2);
        $this->slotTasks($company, $hub, 3, ['slot_options' => ['unassigned_strategy' => 'pool']]);

        $result = $this->withHeaders($this->apiHeaders($company))
            ->postJson('/api/integration/task-slots/'.self::SLOT.'/close', ['hub_id' => $hub->id])->assertOk()->json();

        $this->assertSame(2, $result['assigned']);
        $this->assertCount(1, $result['unassigned']);
        $this->assertSame('pool', Task::withoutGlobalScopes()->find($result['unassigned'][0])->status_category_slug->value);
    }

    public function test_closing_without_couriers_or_tasks_explains_why(): void
    {
        $company = $this->makeCompany();
        $hub = $this->makeHub($company);
        $headers = $this->apiHeaders($company);

        $this->withHeaders($headers)->postJson('/api/integration/task-slots/'.self::SLOT.'/close', ['hub_id' => $hub->id])
            ->assertStatus(422)->assertJsonPath('code', 'empty_slot');

        $this->slotTasks($company, $hub, 1);
        $this->withHeaders($headers)->postJson('/api/integration/task-slots/'.self::SLOT.'/close', ['hub_id' => $hub->id])
            ->assertStatus(422)->assertJsonPath('code', 'no_couriers');

        $other = $this->makeHub($this->makeCompany());
        $this->withHeaders($headers)->postJson('/api/integration/task-slots/'.self::SLOT.'/close', ['hub_id' => $other->id])
            ->assertUnprocessable()->assertJsonValidationErrors('hub_id');
    }

    public function test_shifting_a_planned_task_takes_it_off_the_route(): void
    {
        $company = $this->makeCompany();
        $hub = $this->makeHub($company, ['lat' => 40.3777, 'lng' => 49.8436]);
        $this->couriersOnShift($company, $hub, 1);
        $tasks = $this->slotTasks($company, $hub, 3);
        $headers = $this->apiHeaders($company);

        $planId = $this->withHeaders($headers)->postJson('/api/integration/task-slots/'.self::SLOT.'/close', ['hub_id' => $hub->id])->json('plans.0');

        $this->withHeaders($headers)->postJson('/api/integration/task-slots/shift', [
            'task_ids' => [$tasks[0]->id],
            'slot' => '2026102114001600',
            'starts_at' => '2026-10-21T10:00:00Z',
            'ends_at' => '2026-10-21T12:00:00Z',
        ])->assertOk()->assertJsonPath('moved', 1);

        $moved = $tasks[0]->fresh();
        $this->assertNull($moved->plan_id);
        $this->assertNull($moved->courier_id);
        $this->assertSame('2026102114001600', $moved->slot);
        $this->assertSame(2, TaskPlan::withoutGlobalScopes()->find($planId)->quantity);
        $this->assertSame([1, 2], Task::withoutGlobalScopes()->where('plan_id', $planId)->orderBy('route_order')->pluck('route_order')->all());

        $this->withHeaders($headers)->getJson('/api/integration/task-slots')->assertJsonPath('data.0.slot', '2026102114001600');
    }

    public function test_plans_follow_their_stops_and_stay_private(): void
    {
        $company = $this->makeCompany();
        $hub = $this->makeHub($company, ['lat' => 40.3777, 'lng' => 49.8436]);
        [$courier] = $this->couriersOnShift($company, $hub, 1);
        $this->slotTasks($company, $hub, 2);
        $planId = $this->withHeaders($this->apiHeaders($company))
            ->postJson('/api/integration/task-slots/'.self::SLOT.'/close', ['hub_id' => $hub->id])->json('plans.0');

        $first = Task::withoutGlobalScopes()->where('plan_id', $planId)->orderBy('route_order')->first();
        $this->actingAs($courier)->postJson("/courier/tasks/{$first->id}/step", ['action' => 'pickup'])->assertOk();
        $this->assertSame('in_progress', TaskPlan::withoutGlobalScopes()->find($planId)->status_slug);

        $this->withHeaders($this->apiHeaders($this->makeCompany()))->getJson("/api/integration/task-plans/{$planId}")->assertNotFound();
    }

    public function test_dispatcher_plans_a_slot_and_reorders_stops_in_the_panel(): void
    {
        $company = $this->makeCompany(['timezone' => 'Asia/Baku']);
        $owner = $this->makeStaff($company);
        $hub = $this->makeHub($company, ['lat' => 40.3777, 'lng' => 49.8436]);
        $this->couriersOnShift($company, $hub, 1);
        $this->slotTasks($company, $hub, 3);

        $this->actingAs($owner)->get('/app/plans')->assertOk()->assertSee(self::SLOT)->assertSee('12:00–14:00');

        $this->actingAs($owner)->post('/app/plans/close', [
            'slot' => self::SLOT, 'hub_id' => $hub->id, 'shift' => 'working', 'tour' => 1, 'dispatch_early' => 0, 'from_hub' => 1,
        ])->assertRedirect('/app/plans?date=2026-10-21')->assertSessionHas('status');

        $plan = TaskPlan::withoutGlobalScopes()->sole();
        $order = Task::withoutGlobalScopes()->where('plan_id', $plan->id)->orderBy('route_order')->pluck('id')->all();

        $this->actingAs($owner)->get('/app/plans?date=2026-10-21')->assertOk()->assertSee(route('plans.show', $plan), false);
        $this->actingAs($owner)->get("/app/plans/{$plan->id}")->assertOk()->assertSee('planMap', false);

        $this->actingAs($owner)->post("/app/plans/{$plan->id}/move", ['task_id' => (string) $order[2], 'direction' => 'up'] /* forms post strings */)->assertRedirect();

        $this->assertSame([$order[0], $order[2], $order[1]], Task::withoutGlobalScopes()->where('plan_id', $plan->id)->orderBy('route_order')->pluck('id')->all());
        $this->assertSame('manual', $plan->fresh()->solver);

        $this->actingAs($this->makeStaff($this->makeCompany()))->get("/app/plans/{$plan->id}")->assertNotFound();
    }

    /** @return list<User> */
    private function couriersOnShift(Company $company, Hub $hub, int $count, ?int $capacity = null): array
    {
        return collect(range(1, $count))->map(function ($i) use ($company, $hub, $capacity) {
            $courier = $this->makeCourier($company);
            $this->joinHub($company, $courier, $hub);
            CourierDetail::withoutGlobalScopes()->where('user_id', $courier->id)->update(['capacity' => $capacity]);
            CourierLocation::query()->updateOrCreate(['user_id' => $courier->id], [
                'is_working' => true, 'is_online' => true, 'lat' => $hub->lat, 'lng' => $hub->lng, 'last_seen_at' => now(),
            ]);

            return $courier;
        })->all();
    }

    private function joinHub(Company $company, User $courier, Hub $hub): void
    {
        $this->asTenant($company, function () use ($hub, $courier) {
            $team = $hub->teams()->first() ?? app(TeamService::class)->save(new Team, ['hub_id' => $hub->id, 'name' => 'Team '.Str::random(4)]);
            $team->couriers()->syncWithoutDetaching([$courier->id]);
        });
    }

    private function slotTasks(Company $company, Hub $hub, int $count, array $overrides = []): array
    {
        $points = [[40.3800, 49.8500], [40.3900, 49.8600], [40.4000, 49.8700], [40.4100, 49.8800], [40.4200, 49.8900], [40.4300, 49.9000]];

        return collect(range(0, $count - 1))->map(fn ($i) => $this->createTask($company, $hub, array_replace_recursive([
            'slot' => self::SLOT,
            'starts_at' => '2026-10-21T08:00:00Z',
            'ends_at' => '2026-10-21T10:00:00Z',
            'assignment' => ['type' => 'slot'],
            'destination' => ['lat' => $points[$i][0], 'lng' => $points[$i][1]],
        ], $overrides)))->all();
    }

    private function createTask(Company $company, Hub $hub, array $overrides = []): Task
    {
        return $this->asTenant($company, fn () => app(TaskService::class)->create(array_replace_recursive([
            'hub_id' => $hub->id,
            'integration_id' => 'ORD-'.Str::random(8),
            'starts_at' => now()->toIso8601String(),
            'ends_at' => now()->addHours(2)->toIso8601String(),
            'destination' => ['address' => 'Nizami küç. 10', 'lat' => 40.3800, 'lng' => 49.8500, 'phone' => '+994551234567'],
        ], $overrides), Channel::Api));
    }
}
