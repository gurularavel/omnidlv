<?php

namespace Tests\Feature;

use App\Enums\Channel;
use App\Models\Task;
use App\Services\TaskService;
use Tests\TestCase;

class TrackingLookupTest extends TestCase
{
    private function task(): Task
    {
        $company = $this->makeCompany();
        $hub = $this->makeHub($company);

        return $this->asTenant($company, fn () => app(TaskService::class)->create([
            'hub_id' => $hub->id,
            'integration_id' => 'ORD-5501',
            'starts_at' => now()->toIso8601String(),
            'ends_at' => now()->addHours(2)->toIso8601String(),
            'destination' => ['address' => 'Nizami küç. 10', 'lat' => 40.3777, 'lng' => 49.8436, 'phone' => '+994551234567', 'name' => 'Aysel Məmmədova'],
            'assignment' => ['type' => 'pool'],
        ], Channel::Api));
    }

    public function test_recipient_types_the_code_in_any_shape(): void
    {
        $task = $this->task();
        $messy = ' '.strtolower(substr($task->tracking_code, 0, 6)).' '.substr($task->tracking_code, 6).' ';

        $this->get('/track')->assertOk()->assertSee(__('Track your order'));
        $this->get('/track?code='.urlencode($messy))->assertRedirect('/t/'.$task->tracking_code);
        $this->get('/t/'.strtolower($task->tracking_code))->assertRedirect('/t/'.$task->tracking_code);
        $this->get('/t/'.$task->tracking_code)->assertOk()->assertSee('A**** M********');
    }

    public function test_unknown_code_shows_a_clear_message(): void
    {
        $this->get('/track?code=ODNOPE')->assertOk()->assertSee(__('No order has this code. Check it in the SMS: it starts with OD and has 14 characters.'));
    }

    public function test_order_number_needs_the_matching_phone_digits(): void
    {
        $task = $this->task();

        $this->get('/track?order=ORD-5501&phone=4567')->assertRedirect('/t/'.$task->tracking_code);
        $this->get('/track?order=ORD-5501&phone=0000')->assertOk()->assertSee(__('No order matches this number and phone. Check both, or ask the shop for the tracking code.'));
        $this->get('/track?order=ORD-5501')->assertOk();

        $this->getJson('/api/tracking?order=ORD-5501&phone=4567')->assertOk()->assertJsonPath('tracking_code', $task->tracking_code);
        $this->getJson('/api/tracking?order=ORD-5501&phone=1111')->assertNotFound();
        $this->getJson('/api/tracking?order=ORD-5501')->assertUnprocessable()->assertJsonValidationErrors('phone');
    }

    public function test_shops_can_call_the_api_from_the_browser_and_embed_the_page(): void
    {
        $task = $this->task();

        $this->getJson('/api/tracking?code='.$task->tracking_code, ['Origin' => 'https://shop.example.az'])
            ->assertOk()
            ->assertHeader('Access-Control-Allow-Origin', '*');

        $page = $this->get('/t/'.$task->tracking_code.'?embed=1')->assertOk();
        $this->assertNull($page->headers->get('X-Frame-Options'));
        $page->assertDontSee(__('Delivery tracking by'));
    }

    public function test_order_lookups_are_rate_limited(): void
    {
        $this->task();

        for ($i = 0; $i < 10; $i++) {
            $this->get('/track?order=ORD-5501&phone=000'.$i)->assertOk();
        }

        $this->get('/track?order=ORD-5501&phone=4567')->assertStatus(429);
    }
}
