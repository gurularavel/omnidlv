<?php

namespace Tests\Feature\Api;

use Tests\TestCase;

class IntegrationMasterDataApiTest extends TestCase
{
    public function test_hub_crud_with_geocoding(): void
    {
        $company = $this->makeCompany();
        $headers = $this->apiHeaders($company);

        $hub = $this->postJson('/api/integration/hubs', [
            'name' => 'Mərkəzi anbar',
            'integration_id' => 'WH-1',
            'address' => 'Babək pr. 1, Bakı',
            'tags' => ['main'],
        ], $headers)->assertCreated()->assertJsonPath('teams_count', 0)->json();

        $this->assertNotNull($hub['lat']);

        $this->postJson('/api/integration/hubs', ['name' => 'X', 'integration_id' => 'WH-1', 'address' => 'Y'], $headers)
            ->assertUnprocessable()->assertJsonValidationErrors('integration_id');

        $this->patchJson("/api/integration/hubs/{$hub['id']}", ['lat' => 40.41, 'lng' => 49.86], $headers)
            ->assertOk()->assertJsonPath('lat', 40.41);

        $this->getJson('/api/integration/hubs', $headers)->assertOk()->assertJsonCount(1, 'data');
        $this->deleteJson("/api/integration/hubs/{$hub['id']}", [], $headers)->assertOk();
        $this->getJson("/api/integration/hubs/{$hub['id']}", $headers)->assertNotFound();
    }

    public function test_team_with_polygon_and_polygon_based_task_routing(): void
    {
        $company = $this->makeCompany();
        $hub = $this->makeHub($company);
        $headers = $this->apiHeaders($company);

        $team = $this->postJson('/api/integration/teams', [
            'name' => 'Nəsimi',
            'hub_id' => $hub->id,
            'polygon' => [[40.37, 49.80], [40.37, 49.90], [40.43, 49.90], [40.43, 49.80]],
        ], $headers)->assertCreated()->json();

        $this->postJson('/api/integration/teams', ['name' => 'Bad', 'polygon' => [[1, 2]]], $headers)
            ->assertUnprocessable()->assertJsonValidationErrors('polygon');

        $task = fn (float $lat, float $lng, string $id) => $this->postJson('/api/integration/tasks', [
            'hub_id' => $hub->id,
            'integration_id' => $id,
            'starts_at' => now()->toIso8601String(),
            'ends_at' => now()->addHour()->toIso8601String(),
            'destination' => ['address' => 'X', 'lat' => $lat, 'lng' => $lng, 'phone' => '+994501112233'],
            'assignment' => ['type' => 'capacity_aware'],
            'assignment_options' => ['team_id_by_polygon' => true],
        ], $headers)->assertCreated();

        $task(40.40, 49.85, 'IN')->assertJsonPath('team_id', $team['id']);
        $task(40.50, 49.85, 'OUT')->assertJsonPath('team_id', null);
    }

    public function test_courier_list_filters_and_update(): void
    {
        $company = $this->makeCompany();
        $headers = $this->apiHeaders($company);
        $courier = $this->makeCourier($company, ['name' => 'Rəşad Əliyev', 'vehicle_type_slug' => 'car', 'capacity' => 5]);
        $this->makeCourier($company, ['name' => 'Elvin Quliyev']);
        $this->makeCourier($this->makeCompany(), ['name' => 'Rəşad Başqa']);

        $this->getJson('/api/integration/couriers', $headers)->assertOk()->assertJsonCount(2, 'data');
        $this->getJson('/api/integration/couriers?filter[name]=rəşad', $headers)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.courier_detail.vehicle_type_slug', 'car')
            ->assertJsonPath('data.0.courier_detail.capacity', 5)
            ->assertJsonPath('data.0.courier_location.is_online', false);

        $this->patchJson("/api/integration/couriers/{$courier->id}", [
            'vehicle_type_slug' => 'truck',
            'capacity' => 20,
            'phone_country' => 'AZ',
            'phone' => '055 555 55 55',
        ], $headers)
            ->assertOk()
            ->assertJsonPath('courier_detail.vehicle_type_slug', 'truck')
            ->assertJsonPath('phone', '+994555555555');

        $this->patchJson("/api/integration/couriers/{$courier->id}", ['vehicle_type_slug' => 'rocket'], $headers)
            ->assertUnprocessable();
    }
}
