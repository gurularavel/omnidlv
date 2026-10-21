@php
    $stateStyle = [
        'success' => ['solid', '#c3c9da'],
        'pending' => ['dashed', '#c3c9da'],
        'failed' => ['double', '#e21d2d'],
    ];
@endphp
<x-layouts::app :title="__('Webhooks')">
    <div class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_420px]">
        <div class="flex min-w-0 flex-col gap-6">
            @if (session('new_secret'))
                <section class="plate border-ink-amber/70" x-data="{ copied: false }">
                    <div class="plate-head"><x-icon name="key-round" class="text-ink-amber" /><h2 class="t-label text-[var(--text)]">{{ __('webhooks.secret_title') }}</h2></div>
                    <div class="p-5">
                        <p class="mb-3 text-[var(--text-2)]">{{ __('webhooks.secret_help') }}</p>
                        <div class="flex gap-2">
                            <input readonly class="input t-code" value="{{ session('new_secret') }}" x-ref="secret" aria-label="{{ __('webhooks.secret') }}">
                            <button type="button" class="btn" @click="navigator.clipboard.writeText($refs.secret.value); copied = true">
                                <x-icon name="copy" :size="16" /><span x-text="copied ? @js(__('Copied')) : @js(__('Copy'))">{{ __('Copy') }}</span>
                            </button>
                        </div>
                    </div>
                </section>
            @endif

            {{-- Endpoints --}}
            <section class="plate" x-data="{ adding: {{ $endpoints->isEmpty() || $errors->any() ? 'true' : 'false' }} }">
                <div class="plate-head">
                    <h2 class="t-label text-[var(--text)]">{{ __('webhooks.endpoints') }}</h2>
                    <button type="button" class="btn btn-sm ml-auto" x-show="!adding" @click="adding = true"><x-icon name="plus" :size="14" />{{ __('webhooks.add') }}</button>
                </div>

                <form method="POST" action="{{ route('webhooks.store') }}" x-show="adding" x-cloak class="grid gap-4 border-b border-[var(--hairline)] p-5">
                    @csrf
                    @include('panel.webhooks.fields', ['endpoint' => null, 'prefix' => 'new'])
                    <div class="flex gap-2">
                        <button class="btn btn-primary"><x-icon name="plus" :size="16" />{{ __('webhooks.add') }}</button>
                        @if ($endpoints->isNotEmpty())
                            <button type="button" class="btn btn-ghost" @click="adding = false">{{ __('Cancel') }}</button>
                        @endif
                    </div>
                </form>

                @if ($endpoints->isEmpty())
                    <x-empty icon="webhook" :title="__('webhooks.empty_title')">{{ __('webhooks.empty_body') }}</x-empty>
                @endif

                <ul class="divide-y divide-[var(--hairline)]">
                    @foreach ($endpoints as $endpoint)
                        <li class="p-5" x-data="{ editing: false }">
                            <div class="flex flex-wrap items-start gap-x-4 gap-y-2">
                                <div class="min-w-0 flex-1">
                                    <p class="t-code break-all text-[14px]">{{ $endpoint->url }}</p>
                                    @if ($endpoint->description)<p class="mt-0.5 text-[13px] text-[var(--text-2)]">{{ $endpoint->description }}</p>@endif
                                    <div class="mt-2 flex flex-wrap gap-1.5">
                                        @foreach ($endpoint->events as $event)
                                            <span class="tag h-6 text-[12px]">{{ \App\Enums\WebhookEvent::tryFrom($event)?->label() ?? $event }}</span>
                                        @endforeach
                                    </div>
                                </div>
                                <div class="text-right text-[13px]">
                                    @if ($endpoint->is_active)
                                        <span class="status" data-form="solid" style="--status-ink: #c3c9da">{{ __('webhooks.active') }}</span>
                                    @else
                                        <span class="status text-[var(--danger-text)]" data-form="double" style="--status-ink: #e21d2d">{{ __('webhooks.off') }}</span>
                                    @endif
                                    <p class="mt-1 text-[var(--text-3)]">
                                        @if ($endpoint->last_success_at){{ __('webhooks.last_ok', ['time' => $endpoint->last_success_at->diffForHumans()]) }}@else{{ __('webhooks.never_delivered') }}@endif
                                    </p>
                                </div>
                            </div>

                            @if ($endpoint->disabled_reason)
                                <p class="mt-3 flex gap-2 text-[13px] text-[var(--danger-text)]"><x-icon name="circle-alert" :size="15" class="mt-0.5 shrink-0" />{{ $endpoint->disabled_reason }}</p>
                            @elseif ($endpoint->failure_streak > 0)
                                <p class="mt-3 flex gap-2 text-[13px] text-[var(--risk-text)]"><x-icon name="triangle-alert" :size="15" class="mt-0.5 shrink-0" />{{ __('webhooks.streak', ['count' => $endpoint->failure_streak, 'max' => config('services.webhooks.disable_after')]) }}</p>
                            @endif

                            <div class="mt-4 flex flex-wrap gap-2">
                                <form method="POST" action="{{ route('webhooks.test', $endpoint) }}">@csrf
                                    <button class="btn btn-sm" @disabled(! $endpoint->is_active)><x-icon name="send" :size="14" />{{ __('webhooks.send_test') }}</button>
                                </form>
                                <a href="{{ route('webhooks.index', ['endpoint' => $endpoint->id]) }}#deliveries" class="btn btn-sm btn-ghost">{{ __('webhooks.log') }}</a>
                                <button type="button" class="btn btn-sm btn-ghost" @click="editing = !editing" :aria-expanded="editing">{{ __('Edit') }}</button>
                                <form method="POST" action="{{ route('webhooks.toggle', $endpoint) }}">@csrf
                                    <button class="btn btn-sm btn-ghost">{{ $endpoint->is_active ? __('webhooks.turn_off') : __('webhooks.turn_on') }}</button>
                                </form>
                                <form method="POST" action="{{ route('webhooks.rotate', $endpoint) }}" onsubmit="return confirm(@js(__('webhooks.rotate_confirm')))">@csrf
                                    <button class="btn btn-sm btn-ghost"><x-icon name="rotate-ccw" :size="14" />{{ __('webhooks.rotate') }}</button>
                                </form>
                                <form method="POST" action="{{ route('webhooks.destroy', $endpoint) }}" class="ml-auto" onsubmit="return confirm(@js(__('webhooks.delete_confirm')))">@csrf @method('DELETE')
                                    <button class="btn btn-sm btn-danger">{{ __('Delete') }}</button>
                                </form>
                            </div>

                            <form method="POST" action="{{ route('webhooks.update', $endpoint) }}" x-show="editing" x-cloak class="mt-4 grid gap-4 rounded-md border border-[var(--hairline)] p-4">
                                @csrf @method('PUT')
                                @include('panel.webhooks.fields', ['endpoint' => $endpoint, 'prefix' => 'e'.$endpoint->id])
                                <div><button class="btn btn-primary btn-sm">{{ __('Save') }}</button></div>
                            </form>
                        </li>
                    @endforeach
                </ul>
            </section>

            {{-- Delivery log --}}
            <section class="plate overflow-hidden" id="deliveries">
                <div class="plate-head flex-wrap gap-y-2">
                    <h2 class="t-label text-[var(--text)]">{{ __('webhooks.deliveries') }}</h2>
                    <form method="GET" class="ml-auto flex gap-2">
                        <select name="endpoint" class="input h-8 w-[200px] text-[13px]" onchange="this.form.submit()" aria-label="{{ __('webhooks.endpoint') }}">
                            <option value="">{{ __('webhooks.all_endpoints') }}</option>
                            @foreach ($endpoints as $endpoint)
                                <option value="{{ $endpoint->id }}" @selected($endpointId === $endpoint->id)>{{ Str::limit(preg_replace('#^https?://#', '', $endpoint->url), 32) }}</option>
                            @endforeach
                        </select>
                        <select name="state" class="input h-8 w-[130px] text-[13px]" onchange="this.form.submit()" aria-label="{{ __('Status') }}">
                            <option value="">{{ __('All') }}</option>
                            @foreach (['success', 'failed', 'pending'] as $s)
                                <option value="{{ $s }}" @selected($state === $s)>{{ __('webhooks.state.'.$s) }}</option>
                            @endforeach
                        </select>
                    </form>
                </div>

                @if ($deliveries->isEmpty())
                    <x-empty icon="send" :title="__('webhooks.no_deliveries')">{{ __('webhooks.no_deliveries_body') }}</x-empty>
                @else
                    <ul class="divide-y divide-[var(--hairline)]">
                        @foreach ($deliveries as $delivery)
                            @php [$form, $ink] = $stateStyle[$delivery->state]; @endphp
                            <li x-data="{ open: false }">
                                <button type="button" class="grid w-full grid-cols-[minmax(0,1fr)_auto] items-center gap-x-4 gap-y-1 px-5 py-3 text-left hover:bg-[var(--surface-hover)] sm:grid-cols-[110px_minmax(0,1fr)_120px_70px]"
                                        @click="open = !open" :aria-expanded="open">
                                    <span class="text-[13px] text-[var(--text-2)]">{{ \App\Support\Ui::localTime($delivery->created_at, 'd M H:i:s') }}</span>
                                    <span class="min-w-0 sm:order-none">
                                        <span class="block truncate font-medium">{{ \App\Enums\WebhookEvent::tryFrom($delivery->event)?->label() ?? $delivery->event }}</span>
                                        <span class="t-code block truncate text-[12px] text-[var(--text-3)]">{{ preg_replace('#^https?://#', '', $delivery->endpoint?->url ?? '—') }}</span>
                                    </span>
                                    <span class="status text-[13px] @if ($delivery->state === 'failed') text-[var(--danger-text)] @endif" data-form="{{ $form }}" style="--status-ink: {{ $ink }}">
                                        {{ __('webhooks.state.'.$delivery->state) }}@if ($delivery->response_status) · {{ $delivery->response_status }}@endif
                                    </span>
                                    <span class="t-label text-right text-[11px] text-[var(--text-3)]">{{ trans_choice('webhooks.attempts', $delivery->attempts, ['count' => $delivery->attempts]) }}</span>
                                </button>
                                <div x-show="open" x-cloak class="grid gap-4 border-t border-[var(--hairline)] bg-night-950/40 px-5 py-4 lg:grid-cols-2">
                                    <div class="min-w-0">
                                        <p class="t-label mb-2">{{ __('webhooks.payload') }} <span class="t-code normal-case tracking-normal text-[var(--text-3)]">{{ $delivery->payload_id }}</span></p>
                                        <pre class="max-h-[320px] overflow-auto rounded-md border border-[var(--hairline)] bg-night-950 p-3 font-mono text-[12px] leading-relaxed text-[var(--text-2)]">{{ json_encode($delivery->payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}</pre>
                                    </div>
                                    <div class="flex min-w-0 flex-col gap-3">
                                        <p class="t-label">{{ __('webhooks.response') }}</p>
                                        @if ($delivery->error)
                                            <p class="text-[13px] text-[var(--danger-text)]">{{ $delivery->error }}</p>
                                        @endif
                                        <pre class="max-h-[200px] overflow-auto rounded-md border border-[var(--hairline)] bg-night-950 p-3 font-mono text-[12px] text-[var(--text-2)]">{{ $delivery->response_body !== null && $delivery->response_body !== '' ? $delivery->response_body : __('webhooks.no_body') }}</pre>
                                        <dl class="grid grid-cols-2 gap-2 text-[13px]">
                                            <dt class="text-[var(--text-3)]">{{ __('webhooks.duration') }}</dt><dd>{{ $delivery->duration_ms !== null ? $delivery->duration_ms.' ms' : '—' }}</dd>
                                            @if ($delivery->next_retry_at)
                                                <dt class="text-[var(--text-3)]">{{ __('webhooks.next_retry') }}</dt><dd>{{ \App\Support\Ui::localTime($delivery->next_retry_at, 'H:i:s') }}</dd>
                                            @endif
                                        </dl>
                                        @if ($delivery->state !== 'pending')
                                            <form method="POST" action="{{ route('webhooks.redeliver', $delivery) }}">@csrf
                                                <button class="btn btn-sm" @disabled(! $delivery->endpoint?->is_active)><x-icon name="refresh-cw" :size="14" />{{ __('webhooks.redeliver') }}</button>
                                            </form>
                                        @endif
                                    </div>
                                </div>
                            </li>
                        @endforeach
                    </ul>
                    <div class="border-t border-[var(--hairline)] px-4 py-3">{{ $deliveries->links() }}</div>
                @endif
            </section>
        </div>

        {{-- How receivers should handle it --}}
        <section class="plate self-start">
            <div class="plate-head"><h2 class="t-label text-[var(--text)]">{{ __('webhooks.receiving') }}</h2></div>
            <div class="flex flex-col gap-4 p-5 text-[14px]">
                <p class="text-[var(--text-2)]">{{ __('webhooks.receiving_intro') }}</p>
                <dl class="flex flex-col gap-3">
                    @foreach ($events as $event)
                        <div><dt class="t-code text-[13px]">{{ $event->value }}</dt><dd class="text-[13px] text-[var(--text-2)]">{{ $event->description() }}</dd></div>
                    @endforeach
                </dl>
                <div class="rule"></div>
                <p class="t-label">{{ __('webhooks.verify_title') }}</p>
                <p class="text-[var(--text-2)]">{{ __('webhooks.verify_body') }}</p>
                <pre class="overflow-x-auto rounded-md border border-[var(--hairline)] bg-night-950 p-3 font-mono text-[12px] leading-relaxed text-[var(--text-2)]">// PHP
$body = file_get_contents('php://input');
$ts   = $_SERVER['HTTP_X_OMNIDLV_TIMESTAMP'];
$sig  = $_SERVER['HTTP_X_OMNIDLV_SIGNATURE'];

$ok = abs(time() - (int) $ts) &lt; 300
   &amp;&amp; hash_equals('v1=' . hash_hmac('sha256', "$ts.$body", $secret), $sig);</pre>
                <p class="text-[13px] text-[var(--text-3)]">{{ __('webhooks.qdelivery_compat') }}</p>
                <div class="rule"></div>
                <ul class="flex flex-col gap-2 text-[var(--text-2)]">
                    <li class="flex gap-2"><x-icon name="check" :size="15" class="mt-0.5 shrink-0 text-ink-green" />{{ __('webhooks.rule_2xx') }}</li>
                    <li class="flex gap-2"><x-icon name="check" :size="15" class="mt-0.5 shrink-0 text-ink-green" />{{ __('webhooks.rule_retry') }}</li>
                    <li class="flex gap-2"><x-icon name="check" :size="15" class="mt-0.5 shrink-0 text-ink-green" />{{ __('webhooks.rule_dedupe') }}</li>
                    <li class="flex gap-2"><x-icon name="check" :size="15" class="mt-0.5 shrink-0 text-ink-green" />{{ __('webhooks.rule_disable', ['count' => config('services.webhooks.disable_after')]) }}</li>
                </ul>
            </div>
        </section>
    </div>
</x-layouts::app>
