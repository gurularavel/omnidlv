<?php

namespace Tests\Feature;

use App\Enums\Channel;
use App\Enums\WebhookEvent;
use App\Models\Company;
use App\Models\Task;
use App\Models\WebhookDelivery;
use App\Models\WebhookEndpoint;
use App\Services\TaskService;
use App\Services\Webhooks\UnsafeWebhookUrl;
use App\Services\Webhooks\WebhookUrlGuard;
use App\Support\CurrentCompany;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

class WebhookTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Test receivers have no DNS; the guard's own test below turns this back off.
        config(['services.webhooks.allow_private_networks' => true]);
    }

    public function test_status_change_is_sent_signed_in_qdelivery_shape(): void
    {
        Http::fake(['hooks.example.com/*' => Http::response('ok')]);
        $company = $this->makeCompany();
        $endpoint = $this->endpoint($company);
        $courier = $this->makeCourier($company, ['name' => 'Elvin Quliyev']);
        $task = $this->task($company, ['assignment' => ['type' => 'selected_courier', 'courier_id' => $courier->id]]);

        $delivery = WebhookDelivery::withoutGlobalScopes()->where('event', 'task_status_changed')->latest('id')->firstOrFail();
        $this->assertSame(WebhookDelivery::SUCCESS, $delivery->state);
        $this->assertSame(1, $delivery->attempts);

        Http::assertSent(function (Request $request) use ($endpoint, $task) {
            $body = $request->body();
            $data = json_decode($body, true);
            $ts = $request->header('X-OmniDLV-Timestamp')[0];

            return $request->url() === $endpoint->url
                && $data['event'] === 'task_status_changed'
                && $data['task']['id'] === $task->id
                && $data['task']['status_category_slug'] === 'assigned'
                && $data['courier']['name'] === 'Elvin Quliyev'
                && array_key_exists('courier_location', $data['courier'])
                && $request->header('x-qdelivery-signature')[0] === hash_hmac('sha256', $data['payload_id'], 'whsec_test')
                && $request->header('X-OmniDLV-Signature')[0] === 'v1='.hash_hmac('sha256', "{$ts}.{$body}", 'whsec_test');
        });

        $this->assertNotNull($endpoint->fresh()->last_success_at);
    }

    public function test_only_subscribed_active_endpoints_of_the_same_company_receive(): void
    {
        Http::fake(['*' => Http::response('ok')]);
        $company = $this->makeCompany();
        $other = $this->makeCompany();
        $this->endpoint($company, ['url' => 'https://a.example.com/hook']);
        $this->endpoint($company, ['url' => 'https://b.example.com/hook', 'events' => ['courier_location_updated']]);
        $this->endpoint($company, ['url' => 'https://c.example.com/hook', 'is_active' => false]);
        $this->endpoint($other, ['url' => 'https://d.example.com/hook']);

        $this->task($company);

        Http::assertSentCount(1);
        Http::assertSent(fn (Request $r) => $r->url() === 'https://a.example.com/hook');
    }

    public function test_server_errors_are_retried_and_client_errors_are_not(): void
    {
        $company = $this->makeCompany();
        $this->endpoint($company, ['url' => 'https://down.example.com/hook']);
        $this->endpoint($company, ['url' => 'https://strict.example.com/hook']);

        Http::fake([
            'down.example.com/*' => Http::response('boom', 503),
            'strict.example.com/*' => Http::response('bad', 422),
        ]);

        $this->task($company);

        // The sync queue runs the delayed retries at once: all six attempts happen here.
        $down = WebhookDelivery::withoutGlobalScopes()->whereHas('endpoint', fn ($q) => $q->where('url', 'like', '%down%'))->sole();
        $this->assertSame(WebhookDelivery::FAILED, $down->state);
        $this->assertSame(6, $down->attempts);
        $this->assertSame(503, $down->response_status);

        $strict = WebhookDelivery::withoutGlobalScopes()->whereHas('endpoint', fn ($q) => $q->where('url', 'like', '%strict%'))->sole();
        $this->assertSame(WebhookDelivery::FAILED, $strict->state);
        $this->assertSame(1, $strict->attempts);
    }

    public function test_an_endpoint_that_keeps_failing_is_switched_off(): void
    {
        config(['services.webhooks.disable_after' => 2]);
        Http::fake(['*' => Http::response('no', 400)]);
        $company = $this->makeCompany();
        $endpoint = $this->endpoint($company);

        $this->task($company);
        $this->assertTrue($endpoint->fresh()->is_active);
        $this->assertSame(1, $endpoint->fresh()->failure_streak);

        $this->task($company);
        $endpoint->refresh();
        $this->assertFalse($endpoint->is_active);
        $this->assertNotNull($endpoint->disabled_reason);

        // Nothing more is queued for it.
        $this->task($company);
        $this->assertSame(2, WebhookDelivery::withoutGlobalScopes()->count());
    }

    public function test_guard_blocks_private_and_local_addresses(): void
    {
        config(['services.webhooks.allow_private_networks' => false, 'services.webhooks.allow_http' => false]);
        $guard = app(WebhookUrlGuard::class);

        foreach ([
            'http://93.184.216.34/hook',            // plain http
            'https://127.0.0.1/hook',
            'https://10.1.2.3/hook',
            'https://192.168.0.10/hook',
            'https://169.254.169.254/latest/meta-data',
            'https://100.64.1.1/hook',
            'https://[::1]/hook',
            'https://[::ffff:10.0.0.1]/hook',
            'https://localhost/hook',
            'https://user:pass@93.184.216.34/hook',
        ] as $url) {
            try {
                $guard->check($url);
                $this->fail("{$url} should be rejected");
            } catch (UnsafeWebhookUrl) {
                $this->addToAssertionCount(1);
            }
        }

        $this->assertSame(['93.184.216.34'], $guard->check('https://93.184.216.34/hook')['ips']);
    }

    public function test_panel_rejects_private_urls_and_shows_the_secret_once(): void
    {
        config(['services.webhooks.allow_private_networks' => false]);
        $company = $this->makeCompany();
        $owner = $this->makeStaff($company);

        $this->actingAs($owner)->post('/app/webhooks', ['url' => 'https://10.0.0.5/hook', 'events' => ['task_status_changed']])
            ->assertSessionHasErrors('url');

        $this->actingAs($owner)->post('/app/webhooks', ['url' => 'https://93.184.216.34/hook', 'events' => ['task_status_changed', 'bogus']])
            ->assertSessionHasErrors('events.1');

        $this->actingAs($owner)->post('/app/webhooks', ['url' => 'https://93.184.216.34/hook', 'events' => ['task_status_changed']])
            ->assertRedirect('/app/webhooks')
            ->assertSessionHas('new_secret', fn ($s) => str_starts_with($s, 'whsec_'));

        $endpoint = WebhookEndpoint::withoutGlobalScopes()->sole();
        $this->assertSame($company->id, $endpoint->company_id);
        $this->assertStringNotContainsString(session('new_secret'), (string) $endpoint->getRawOriginal('secret'), 'secrets are stored encrypted');

        $this->actingAs($owner)->get('/app/webhooks')->assertOk()->assertSee('93.184.216.34/hook');
    }

    public function test_test_send_and_redeliver_from_the_panel(): void
    {
        Http::fake(['*' => Http::response('ok')]);
        $company = $this->makeCompany();
        $owner = $this->makeStaff($company);
        $endpoint = $this->endpoint($company);

        $this->actingAs($owner)->post("/app/webhooks/{$endpoint->id}/test")->assertRedirect();
        $first = WebhookDelivery::withoutGlobalScopes()->sole();
        $this->assertSame('test', $first->event);
        $this->assertSame(WebhookDelivery::SUCCESS, $first->state);

        $this->actingAs($owner)->post("/app/webhook-deliveries/{$first->id}/redeliver")->assertRedirect();
        $again = WebhookDelivery::withoutGlobalScopes()->latest('id')->first();
        $this->assertNotSame($first->id, $again->id);
        $this->assertSame($first->payload_id, $again->payload_id, 'a redelivery keeps the payload_id so receivers can dedupe');
    }

    public function test_another_company_cannot_touch_an_endpoint(): void
    {
        $company = $this->makeCompany();
        $endpoint = $this->endpoint($company);
        $intruder = $this->makeStaff($this->makeCompany());

        $this->actingAs($intruder)->post("/app/webhooks/{$endpoint->id}/test")->assertNotFound();
        $this->actingAs($intruder)->delete("/app/webhooks/{$endpoint->id}")->assertNotFound();
        $this->assertTrue($endpoint->fresh()->exists);
    }

    public function test_courier_location_and_shift_events(): void
    {
        Http::fake(['*' => Http::response('ok')]);
        $company = $this->makeCompany();
        $this->endpoint($company, ['events' => ['courier_location_updated', 'courier_working_status_updated']]);
        $courier = $this->makeCourier($company);

        $this->actingAs($courier)->postJson('/courier/shift/start', ['lat' => 40.40, 'lng' => 49.86])->assertOk();
        // Starting an already open shift is not a change.
        $this->actingAs($courier)->postJson('/courier/shift/start')->assertOk();

        $this->actingAs($courier)->postJson('/courier/location', ['lat' => 40.41, 'lng' => 49.87])->assertNoContent();
        $this->travel(20)->seconds();
        $this->actingAs($courier)->postJson('/courier/location', ['lat' => 40.42, 'lng' => 49.88])->assertNoContent();

        $events = WebhookDelivery::withoutGlobalScopes()->orderBy('id')->pluck('event')->all();
        $this->assertSame(['courier_working_status_updated', 'courier_location_updated'], $events, 'one location webhook per 30 s');

        $working = WebhookDelivery::withoutGlobalScopes()->where('event', 'courier_working_status_updated')->sole()->payload;
        $this->assertTrue($working['courier']['courier_location']['is_working']);
        $this->assertArrayHasKey('hubs', $working);

        $location = WebhookDelivery::withoutGlobalScopes()->where('event', 'courier_location_updated')->sole()->payload;
        $this->assertSame(40.41, $location['courier']['courier_location']['lat']);
        $this->assertSame([], $location['tasks']);

        $this->actingAs($courier)->postJson('/courier/shift/end')->assertOk();
        $this->assertSame(2, WebhookDelivery::withoutGlobalScopes()->where('event', 'courier_working_status_updated')->count());
    }

    public function test_no_listener_means_no_payload_work(): void
    {
        Http::fake();
        $this->task($this->makeCompany());

        Http::assertNothingSent();
        $this->assertSame(0, WebhookDelivery::withoutGlobalScopes()->count());
    }

    private function endpoint(Company $company, array $attributes = []): WebhookEndpoint
    {
        return WebhookEndpoint::withoutGlobalScopes()->forceCreate([
            'company_id' => $company->id,
            'url' => 'https://hooks.example.com/omnidlv',
            'events' => [WebhookEvent::TaskStatusChanged->value],
            'secret' => 'whsec_test',
            'is_active' => true,
            ...$attributes,
        ]);
    }

    private function task(Company $company, array $overrides = []): Task
    {
        $hub = $this->makeHub($company);

        return app(CurrentCompany::class)->run($company, fn () => app(TaskService::class)->create(array_replace_recursive([
            'hub_id' => $hub->id,
            'integration_id' => 'ORD-'.Str::random(6),
            'starts_at' => now()->toIso8601String(),
            'ends_at' => now()->addHours(2)->toIso8601String(),
            'destination' => ['address' => 'Nizami küç. 10', 'lat' => 40.3777, 'lng' => 49.8436, 'phone' => '+994551234567'],
        ], $overrides), Channel::Api));
    }
}
