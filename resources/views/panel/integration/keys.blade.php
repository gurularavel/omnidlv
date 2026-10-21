<x-layouts::app :title="__('Integration')">
    <div class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_minmax(0,1fr)]">
        <div class="flex flex-col gap-6">
            @if (session('new_key'))
                <section class="plate border-ink-amber/70" x-data="{ copied: false }">
                    <div class="plate-head"><x-icon name="key-round" class="text-ink-amber" /><h2 class="t-label text-[var(--text)]">{{ __('Your new key') }}</h2></div>
                    <div class="p-5">
                        <p class="mb-3 text-[var(--text-2)]">{{ __('Copy it now. For your security it will not be shown again.') }}</p>
                        <div class="flex gap-2">
                            <input readonly class="input t-code" value="{{ session('new_key') }}" x-ref="key" aria-label="{{ __('API key') }}">
                            <button type="button" class="btn" @click="navigator.clipboard.writeText($refs.key.value); copied = true">
                                <x-icon name="copy" :size="16" /><span x-text="copied ? @js(__('Copied')) : @js(__('Copy'))">{{ __('Copy') }}</span>
                            </button>
                        </div>
                    </div>
                </section>
            @endif

            <section class="plate">
                <div class="plate-head"><h2 class="t-label text-[var(--text)]">{{ __('API keys') }}</h2></div>
                <form method="POST" action="{{ route('integration.keys.store') }}" class="flex gap-3 border-b border-[var(--hairline)] p-5">
                    @csrf
                    <input name="name" class="input" required maxlength="100" placeholder="{{ __('Key name, e.g. Online store') }}" aria-label="{{ __('Key name') }}">
                    <button class="btn btn-primary"><x-icon name="plus" :size="16" />{{ __('Create key') }}</button>
                </form>
                @error('name')<p class="field-error px-5 pt-3">{{ $message }}</p>@enderror
                @if ($tokens->isEmpty())
                    <x-empty icon="key-round" :title="__('No keys yet')">{{ __('Create a key for each system that sends orders, so you can revoke one without touching the others.') }}</x-empty>
                @else
                    <table class="tbl">
                        <thead><tr><th>{{ __('Name') }}</th><th>{{ __('Last used') }}</th><th>{{ __('Created') }}</th><th></th></tr></thead>
                        <tbody>
                        @foreach ($tokens as $token)
                            <tr>
                                <td class="font-medium">{{ $token->name }}</td>
                                <td class="text-[var(--text-2)]">{{ $token->last_used_at?->diffForHumans() ?? __('never') }}</td>
                                <td class="text-[var(--text-2)]">{{ \App\Support\Ui::localTime($token->created_at, 'd M Y') }}</td>
                                <td class="text-right">
                                    <form method="POST" action="{{ route('integration.keys.destroy', $token->id) }}" onsubmit="return confirm(@js(__('Revoke this key? Systems using it stop working immediately.')))">
                                        @csrf @method('DELETE')
                                        <button class="btn btn-sm btn-danger">{{ __('Revoke') }}</button>
                                    </form>
                                </td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                @endif
            </section>
        </div>

        <section class="plate self-start">
            <div class="plate-head"><h2 class="t-label text-[var(--text)]">{{ __('Connecting') }}</h2></div>
            <div class="flex flex-col gap-4 p-5">
                <p class="text-[var(--text-2)]">{{ __('The API follows the QDelivery integration format: same paths and fields. Systems already built for QDelivery only change the base URL and the key.') }}</p>
                <div>
                    <p class="t-label mb-2">{{ __('Base URL') }}</p>
                    <code class="t-code block rounded-md border border-[var(--hairline)] bg-night-950 px-3 py-2 text-[13px]">{{ $baseUrl }}</code>
                </div>
                <div>
                    <p class="t-label mb-2">{{ __('Create a task') }}</p>
                    <pre class="overflow-x-auto rounded-md border border-[var(--hairline)] bg-night-950 p-3 font-mono text-[12px] leading-relaxed text-[var(--text-2)]">curl -X POST {{ $baseUrl }}/tasks \
  -H "Authorization: Bearer $KEY" \
  -H "Content-Type: application/json" \
  -d '{
    "hub_id": 1,
    "integration_id": "ORD-1001",
    "action_slug": "drop_off",
    "starts_at": "{{ now()->addHour()->utc()->format('Y-m-d\TH:i:s\Z') }}",
    "ends_at": "{{ now()->addHours(3)->utc()->format('Y-m-d\TH:i:s\Z') }}",
    "destination": {
      "address": "Nizami küç. 10, Bakı",
      "name": "Aysel Məmmədova",
      "phone": "+994501234567"
    },
    "assignment": { "type": "pool" }
  }'</pre>
                </div>
                <ul class="flex flex-col gap-2 text-[14px] text-[var(--text-2)]">
                    <li class="flex gap-2"><x-icon name="check" :size="15" class="mt-0.5 shrink-0 text-ink-green" />{{ __('Times without a timezone are read as UTC.') }}</li>
                    <li class="flex gap-2"><x-icon name="check" :size="15" class="mt-0.5 shrink-0 text-ink-green" />{{ __('Sending the same order number twice returns 409 with the existing task, so retries are safe.') }}</li>
                    <li class="flex gap-2"><x-icon name="check" :size="15" class="mt-0.5 shrink-0 text-ink-green" />{{ __('GET /statuses lists every status id you can send to /task-status.') }}</li>
                </ul>
            </div>
        </section>

        {{-- Recipient tracking: what a shop shows its own customers. --}}
        <section class="plate xl:col-span-2">
            <div class="plate-head">
                <h2 class="t-label text-[var(--text)]">{{ __('Recipient tracking') }}</h2>
                <a href="{{ url('/demo-shop/') }}" target="_blank" rel="noopener" class="btn btn-sm ml-auto"><x-icon name="external-link" :size="14" />{{ __('See it in a demo shop') }}</a>
            </div>
            <div class="grid gap-6 p-5 lg:grid-cols-3">
                <div class="flex flex-col gap-2">
                    <p class="t-label">{{ __('1. Send the link') }}</p>
                    <p class="text-[var(--text-2)]">{{ __('Every task in the API response carries tracking_code and tracking_url. Put the link in your order email or SMS.') }}</p>
                    <code class="t-code block rounded-md border border-[var(--hairline)] bg-night-950 px-3 py-2 text-[13px]">"tracking_url": "{{ url('/t/OD7KQ2MXR4PA9C') }}"</code>
                    <p class="text-[var(--text-2)]">{{ __('Recipients without the link type the code here:') }}
                        <a href="{{ route('tracking.lookup') }}" target="_blank" rel="noopener" class="text-[var(--text)] underline">{{ route('tracking.lookup') }}</a></p>
                </div>
                <div class="flex flex-col gap-2">
                    <p class="t-label">{{ __('2. Show it on your site') }}</p>
                    <p class="text-[var(--text-2)]">{{ __('Embed the tracking page in your order page. embed=1 hides our header and footer.') }}</p>
                    <pre class="overflow-x-auto rounded-md border border-[var(--hairline)] bg-night-950 p-3 font-mono text-[12px] leading-relaxed text-[var(--text-2)]">&lt;iframe src="{{ url('/t/OD7KQ2MXR4PA9C') }}?embed=1"
        style="width:100%;height:720px;border:0"&gt;&lt;/iframe&gt;

&lt;!-- or a search box for any order --&gt;
&lt;iframe src="{{ route('tracking.lookup') }}?embed=1"
        style="width:100%;height:520px;border:0"&gt;&lt;/iframe&gt;</pre>
                </div>
                <div class="flex flex-col gap-2">
                    <p class="t-label">{{ __('3. Or read the status yourself') }}</p>
                    <p class="text-[var(--text-2)]">{{ __('A public endpoint, no API key, open to browsers (CORS). Names and phones come back masked.') }}</p>
                    <pre class="overflow-x-auto rounded-md border border-[var(--hairline)] bg-night-950 p-3 font-mono text-[12px] leading-relaxed text-[var(--text-2)]">GET {{ url('/api/tracking') }}?code=OD7KQ2MXR4PA9C

GET {{ url('/api/tracking') }}?order=ORD-1001&amp;phone=4567

→ status.category: created | pool | assigned |
  in_progress | on_delivery | completed |
  cancelled | failed</pre>
                    <p class="text-[13px] text-[var(--text-3)]">{{ __('Limits: 30 code lookups and 10 order lookups per minute per IP.') }}</p>
                </div>
            </div>
        </section>
    </div>
</x-layouts::app>
