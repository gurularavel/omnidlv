<?php

namespace App\Http\Controllers\Panel;

use App\Enums\WebhookEvent;
use App\Http\Controllers\Controller;
use App\Models\WebhookDelivery;
use App\Models\WebhookEndpoint;
use App\Services\Webhooks\UnsafeWebhookUrl;
use App\Services\Webhooks\WebhookDispatcher;
use App\Services\Webhooks\WebhookPayloads;
use App\Services\Webhooks\WebhookUrlGuard;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/** Webhook endpoints and their delivery log. A secret is shown once, on create or rotate. */
class WebhookController extends Controller
{
    public function __construct(
        private readonly WebhookDispatcher $webhooks,
        private readonly WebhookUrlGuard $guard,
    ) {}

    public function index(Request $request)
    {
        $endpoints = WebhookEndpoint::query()->orderBy('id')->get();
        $endpointId = $request->integer('endpoint') ?: null;
        $state = in_array($request->input('state'), [WebhookDelivery::SUCCESS, WebhookDelivery::FAILED, WebhookDelivery::PENDING], true)
            ? $request->input('state') : null;

        return view('panel.webhooks.index', [
            'endpoints' => $endpoints,
            'deliveries' => WebhookDelivery::query()
                ->with('endpoint:id,url')
                ->when($endpointId, fn ($q) => $q->where('webhook_endpoint_id', $endpointId))
                ->when($state, fn ($q) => $q->where('state', $state))
                ->latest('id')
                ->paginate(30)
                ->withQueryString(),
            'events' => WebhookEvent::subscribable(),
            'endpointId' => $endpointId,
            'state' => $state,
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $secret = WebhookEndpoint::newSecret();

        WebhookEndpoint::query()->create([...$data, 'secret' => $secret, 'is_active' => true]);

        return redirect()->route('webhooks.index')
            ->with('new_secret', $secret)
            ->with('status', __('webhooks.created'));
    }

    public function update(Request $request, WebhookEndpoint $webhook)
    {
        $webhook->update($this->validated($request));

        return redirect()->route('webhooks.index')->with('status', __('webhooks.saved'));
    }

    /** Turning an endpoint back on clears the streak that switched it off. */
    public function toggle(WebhookEndpoint $webhook)
    {
        $webhook->forceFill($webhook->is_active
            ? ['is_active' => false, 'disabled_at' => now(), 'disabled_reason' => null]
            : ['is_active' => true, 'disabled_at' => null, 'disabled_reason' => null, 'failure_streak' => 0]
        )->save();

        return redirect()->route('webhooks.index')->with('status', $webhook->is_active ? __('webhooks.enabled') : __('webhooks.disabled'));
    }

    public function rotate(WebhookEndpoint $webhook)
    {
        $secret = WebhookEndpoint::newSecret();
        $webhook->forceFill(['secret' => $secret])->save();

        return redirect()->route('webhooks.index')
            ->with('new_secret', $secret)
            ->with('status', __('webhooks.rotated'));
    }

    public function test(WebhookEndpoint $webhook, WebhookPayloads $payloads)
    {
        abort_unless($webhook->is_active, 422, __('webhooks.errors.inactive'));

        $this->webhooks->test($webhook, $payloads);

        return redirect()->route('webhooks.index', ['endpoint' => $webhook->id])->with('status', __('webhooks.test_queued'));
    }

    public function destroy(WebhookEndpoint $webhook)
    {
        $webhook->delete();

        return redirect()->route('webhooks.index')->with('status', __('webhooks.deleted'));
    }

    public function redeliver(WebhookDelivery $delivery)
    {
        abort_unless($delivery->endpoint?->is_active, 422, __('webhooks.errors.inactive'));

        $this->webhooks->redeliver($delivery);

        return back()->with('status', __('webhooks.redelivery_queued'));
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'url' => ['required', 'string', 'max:2048', 'url:http,https'],
            'description' => ['nullable', 'string', 'max:191'],
            'events' => ['required', 'array', 'min:1'],
            'events.*' => ['string', Rule::in(array_map(fn ($e) => $e->value, WebhookEvent::subscribable()))],
        ], [], ['url' => __('webhooks.url'), 'events' => __('webhooks.events_label')]);

        try {
            $this->guard->check($data['url']);
        } catch (UnsafeWebhookUrl $e) {
            throw ValidationException::withMessages(['url' => $e->getMessage()]);
        }

        $data['events'] = array_values(array_unique($data['events']));

        return $data;
    }
}
