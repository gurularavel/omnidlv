<?php

namespace Tests\Feature\Api;

use App\Models\Status;
use App\Models\Task;
use Tests\TestCase;

class IntegrationTaskApiTest extends TestCase
{
    private function payload(int $hubId, array $overrides = []): array
    {
        return array_replace_recursive([
            'hub_id' => (string) $hubId, // QDelivery documents hub_id as a string
            'integration_id' => 'ORD-1001',
            'action_slug' => 'drop_off',
            'starts_at' => now()->addHour()->toIso8601String(),
            'ends_at' => now()->addHours(3)->toIso8601String(),
            'quantity' => 1,
            'notes' => 'Ehtiyatla daşıyın',
            'tags' => ['express', 'fragile'],
            'destination' => [
                'address' => 'Nizami küç. 10, Bakı',
                'name' => 'Aysel Məmmədova',
                'phone_country' => 'AZ',
                'phone' => '050 123 45 67',
            ],
            'requirements' => ['delivery_code' => true, 'pod_photo_mandatory' => true],
            'assignment' => ['type' => 'pool'],
            'items' => [
                ['integration_id' => 'SKU-1', 'name' => 'Ayaqqabı', 'quantity' => 1, 'amount' => 89.9, 'currency' => 'AZN'],
                ['integration_id' => 'SKU-2', 'name' => 'Çanta', 'quantity' => 2],
            ],
        ], $overrides);
    }

    public function test_requests_without_a_key_are_rejected(): void
    {
        $this->getJson('/api/integration/tasks')->assertUnauthorized();
    }

    public function test_a_raw_key_without_bearer_prefix_is_accepted(): void
    {
        $company = $this->makeCompany();

        $this->getJson('/api/integration/tasks', $this->apiHeaders($company, bearer: false))->assertOk();
    }

    public function test_an_expired_subscription_blocks_the_api(): void
    {
        $company = $this->makeCompany(['trial_ends_at' => now()->subDay()]);

        $this->getJson('/api/integration/tasks', $this->apiHeaders($company))
            ->assertStatus(402)
            ->assertJsonPath('code', 'subscription_inactive');
    }

    public function test_it_creates_a_task_in_the_qdelivery_shape(): void
    {
        $company = $this->makeCompany();
        $hub = $this->makeHub($company);

        $response = $this->postJson('/api/integration/tasks', $this->payload($hub->id), $this->apiHeaders($company))
            ->assertCreated()
            ->assertJsonPath('integration_id', 'ORD-1001')
            ->assertJsonPath('type_slug', 'drop_off')
            ->assertJsonPath('status_category_slug', 'pool')
            ->assertJsonPath('channel', 'api')
            ->assertJsonPath('hub_id', $hub->id)
            ->assertJsonPath('origin_hub_id', $hub->id)
            ->assertJsonPath('origin_address', $hub->address)
            ->assertJsonPath('destination_phone', '+994501234567')
            ->assertJsonPath('is_destination_resolved', true)
            ->assertJsonPath('requires_delivery_code', true)
            ->assertJsonPath('requires_pod_photo', true)
            ->assertJsonPath('requires_pod_photo_mandatory', true)
            ->assertJsonPath('tags', ['express', 'fragile'])
            ->assertJsonCount(2, 'items')
            ->assertJsonCount(2, 'contents')
            ->assertJsonCount(2, 'status_logs');

        $this->assertMatchesRegularExpression('/^\d{4}$/', $response->json('requirements_delivery_code'));
        $this->assertMatchesRegularExpression('/^OD[A-Z2-9]{12}$/', $response->json('tracking_code'));
        $this->assertSame(['created', 'pool'], array_column($response->json('status_logs'), 'status_category_slug'));
    }

    public function test_duplicate_integration_id_returns_409_with_the_existing_task(): void
    {
        $company = $this->makeCompany();
        $hub = $this->makeHub($company);
        $headers = $this->apiHeaders($company);

        $first = $this->postJson('/api/integration/tasks', $this->payload($hub->id), $headers)->assertCreated();

        $this->postJson('/api/integration/tasks', $this->payload($hub->id), $headers)
            ->assertStatus(409)
            ->assertJsonPath('code', 'duplicate_task')
            ->assertJsonPath('task_id', $first->json('id'));

        // Same integration_id with a different action is a separate (return) task.
        $this->postJson('/api/integration/tasks', $this->payload($hub->id, ['action_slug' => 'pickup']), $headers)
            ->assertCreated();
    }

    public function test_unresolvable_address_fails_unless_allowed(): void
    {
        $company = $this->makeCompany();
        $hub = $this->makeHub($company);
        $headers = $this->apiHeaders($company);

        $this->postJson('/api/integration/tasks', $this->payload($hub->id, [
            'destination' => ['address' => 'unresolvable place'],
        ]), $headers)->assertUnprocessable()->assertJsonValidationErrors('destination.address');

        $this->postJson('/api/integration/tasks', $this->payload($hub->id, [
            'integration_id' => 'ORD-2',
            'destination' => ['address' => 'unresolvable place', 'allow_unresolved' => true],
        ]), $headers)->assertCreated()->assertJsonPath('is_destination_resolved', false);
    }

    public function test_selected_courier_assignment_and_capacity_limit(): void
    {
        $company = $this->makeCompany();
        $hub = $this->makeHub($company);
        $courier = $this->makeCourier($company, ['capacity' => 1]);
        $headers = $this->apiHeaders($company);
        $assignment = ['assignment' => ['type' => 'selected_courier', 'courier_id' => $courier->id]];

        $this->postJson('/api/integration/tasks', $this->payload($hub->id, $assignment), $headers)
            ->assertCreated()
            ->assertJsonPath('status_category_slug', 'assigned')
            ->assertJsonPath('courier_id', $courier->id)
            ->assertJsonPath('courier.name', $courier->name);

        $this->postJson('/api/integration/tasks', $this->payload($hub->id, ['integration_id' => 'ORD-2', ...$assignment]), $headers)
            ->assertUnprocessable()
            ->assertJsonPath('code', 'capacity_exceeded');

        // The failed create must roll back completely.
        $this->assertSame(1, Task::withoutGlobalScopes()->count());
    }

    public function test_status_flow_follows_the_state_machine(): void
    {
        $company = $this->makeCompany();
        $hub = $this->makeHub($company);
        $courier = $this->makeCourier($company);
        $headers = $this->apiHeaders($company);
        $status = fn (string $slug) => Status::withoutGlobalScopes()->where('slug', $slug)->value('id');

        $taskId = $this->postJson('/api/integration/tasks', $this->payload($hub->id), $headers)->json('id');

        // Skipping steps is not allowed, and active statuses need a courier.
        $this->patchJson("/api/integration/task-status/{$taskId}", ['status_id' => $status('delivered')], $headers)
            ->assertUnprocessable()
            ->assertJsonPath('code', 'invalid_transition');
        $this->patchJson("/api/integration/task-status/{$taskId}", ['status_id' => $status('assigned')], $headers)
            ->assertUnprocessable()
            ->assertJsonPath('code', 'courier_required');

        Task::withoutGlobalScopes()->whereKey($taskId)->update(['courier_id' => $courier->id]);

        foreach (['assigned', 'picked_up', 'on_the_way', 'delivered'] as $slug) {
            $this->patchJson("/api/integration/task-status/{$taskId}", ['status_id' => $status($slug), 'notes' => $slug], $headers)
                ->assertOk()
                ->assertJsonPath('status.slug', $slug);
        }

        $task = Task::withoutGlobalScopes()->find($taskId);
        $this->assertNotNull($task->started_at);
        $this->assertNotNull($task->ended_at);
        $this->assertTrue($task->was_finalized_duly);

        // Completed is final.
        $this->patchJson("/api/integration/task-status/{$taskId}", ['status_id' => $status('failed')], $headers)
            ->assertUnprocessable()
            ->assertJsonPath('code', 'task_final');
    }

    public function test_tenants_cannot_see_or_reference_each_others_data(): void
    {
        [$a, $b] = [$this->makeCompany(), $this->makeCompany()];
        $hubA = $this->makeHub($a);
        $courierA = $this->makeCourier($a);
        $taskId = $this->postJson('/api/integration/tasks', $this->payload($hubA->id), $this->apiHeaders($a))->json('id');
        $hb = $this->apiHeaders($b);

        $this->getJson("/api/integration/tasks/{$taskId}", $hb)->assertNotFound();
        $this->patchJson("/api/integration/task-status/{$taskId}", ['status_id' => 1], $hb)->assertNotFound();
        $this->getJson("/api/integration/tasks/{$taskId}/items", $hb)->assertNotFound();
        $this->getJson("/api/integration/hubs/{$hubA->id}", $hb)->assertNotFound();
        $this->getJson("/api/integration/couriers/{$courierA->id}", $hb)->assertNotFound();
        $this->getJson('/api/integration/tasks', $hb)->assertOk()->assertJsonCount(0, 'data');

        $hubB = $this->makeHub($b);
        $this->postJson('/api/integration/tasks', $this->payload($hubA->id), $hb)
            ->assertUnprocessable()->assertJsonValidationErrors('hub_id');
        $this->postJson('/api/integration/tasks', $this->payload($hubB->id, [
            'assignment' => ['type' => 'selected_courier', 'courier_id' => $courierA->id],
        ]), $hb)->assertUnprocessable()->assertJsonValidationErrors('assignment.courier_id');
    }

    public function test_list_filters_and_pagination_shape(): void
    {
        $company = $this->makeCompany();
        $hub = $this->makeHub($company);
        $headers = $this->apiHeaders($company);

        $this->postJson('/api/integration/tasks', $this->payload($hub->id), $headers)->assertCreated();
        $this->postJson('/api/integration/tasks', $this->payload($hub->id, [
            'integration_id' => 'ORD-2', 'assignment' => ['type' => 'capacity_aware'],
        ]), $headers)->assertCreated();

        $this->getJson('/api/integration/tasks?status_category_slug=pool', $headers)
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonStructure(['data', 'links' => ['first', 'next'], 'meta' => ['current_page', 'per_page']]);

        $this->getJson('/api/integration/tasks?filter[status_category_slug]=created,pool', $headers)->assertJsonCount(2, 'data');

        // date filters on ends_at, as a day range in the company timezone (Asia/Baku).
        $endDay = now()->addHours(3)->setTimezone('Asia/Baku')->toDateString();
        $this->getJson("/api/integration/tasks?date={$endDay},{$endDay}", $headers)->assertJsonCount(2, 'data');
        $this->getJson('/api/integration/tasks?date=2020-01-01,2020-01-02', $headers)->assertJsonCount(0, 'data');
        $this->getJson('/api/integration/tasks?tags=fragile', $headers)->assertJsonCount(2, 'data');
    }

    public function test_lock_items_and_public_tracking(): void
    {
        $company = $this->makeCompany();
        $hub = $this->makeHub($company);
        $headers = $this->apiHeaders($company);
        $task = $this->postJson('/api/integration/tasks', $this->payload($hub->id), $headers)->json();

        $this->postJson("/api/integration/tasks/{$task['id']}/lock", ['action' => 'lock'], $headers)
            ->assertOk()->assertJsonPath('locked', true);

        $this->postJson("/api/integration/tasks/{$task['id']}/items", ['integration_id' => 'SKU-1', 'name' => 'Dup'], $headers)
            ->assertUnprocessable()->assertJsonValidationErrors('integration_id');
        $this->postJson("/api/integration/tasks/{$task['id']}/items", ['integration_id' => 'SKU-3', 'name' => 'Kəmər'], $headers)
            ->assertCreated();

        // PATCH with items replaces the whole list, as in QDelivery.
        $this->patchJson("/api/integration/tasks/{$task['id']}", [
            'recipient_notes' => 'Zəng edin',
            'items' => [['integration_id' => 'SKU-9', 'name' => 'Yeni']],
        ], $headers)->assertOk()->assertJsonCount(1, 'items')->assertJsonPath('recipient_notes', 'Zəng edin');

        $this->getJson('/api/tracking?code='.strtolower($task['tracking_code']))
            ->assertOk()
            ->assertJsonPath('tracking_code', $task['tracking_code'])
            ->assertJsonPath('destination_phone', '+99450***4567')
            ->assertJsonPath('destination_name', 'A**** M********')
            ->assertJsonPath('status.category', 'pool')
            ->assertJsonPath('company.name', $company->name)
            ->assertJsonMissingPath('requirements_delivery_code');

        $this->getJson('/api/tracking?code=NOPE')->assertNotFound();
        $this->getJson('/api/tracking')->assertUnprocessable();
    }
}
