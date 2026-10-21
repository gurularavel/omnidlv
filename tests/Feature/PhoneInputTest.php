<?php

namespace Tests\Feature;

use Tests\TestCase;

class PhoneInputTest extends TestCase
{
    public function test_letters_in_a_phone_are_rejected_everywhere(): void
    {
        $company = $this->makeCompany();
        $hub = $this->makeHub($company);

        $this->post('/courier/login', ['phone' => '050 12a 45 67'])->assertSessionHasErrors('phone');

        $this->withHeaders($this->apiHeaders($company))->postJson('/api/integration/tasks', [
            'hub_id' => $hub->id,
            'starts_at' => now()->toIso8601String(),
            'ends_at' => now()->addHour()->toIso8601String(),
            'destination' => ['address' => 'Nizami küç. 10', 'phone' => 'call me'],
        ])->assertUnprocessable()->assertJsonValidationErrors('destination.phone');
        $this->flushHeaders();

        $this->actingAs($this->makeStaff($company))->post('/app/couriers', ['name' => 'Test', 'phone' => '+994 5O 123 45 67'])
            ->assertSessionHasErrors('phone');
    }

    public function test_formatted_numbers_are_accepted_and_stored_as_e164(): void
    {
        $company = $this->makeCompany();

        $this->actingAs($this->makeStaff($company))->post('/app/couriers', ['name' => 'Test', 'phone' => '+994 50 123 45 67'])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('users', ['phone' => '+994501234567']);
    }
}
