{{-- Shared by the add form and each endpoint's edit form; $prefix keeps the ids unique. --}}
@php
    $isNew = $endpoint === null;
    $chosen = $isNew ? old('events', [\App\Enums\WebhookEvent::TaskStatusChanged->value]) : $endpoint->events;
@endphp
<x-field :label="__('webhooks.url')" name="url" :id="$prefix.'-url'" required :hint="__('webhooks.url_hint')">
    <x-input name="url" type="url" :id="$prefix.'-url'" :value="$isNew ? null : $endpoint->url" required maxlength="2048" placeholder="https://shop.example.com/omnidlv/webhook" class="t-code" />
</x-field>
<x-field :label="__('webhooks.description')" name="description" :id="$prefix.'-description'">
    <x-input name="description" :id="$prefix.'-description'" :value="$isNew ? null : $endpoint->description" maxlength="191" :placeholder="__('webhooks.description_placeholder')" />
</x-field>
<fieldset>
    <legend class="field-label t-label">{{ __('webhooks.events_label') }}</legend>
    <div class="flex flex-col gap-2">
        @foreach (\App\Enums\WebhookEvent::subscribable() as $event)
            <label class="flex items-start gap-3 rounded-md border border-[var(--hairline)] px-3 py-2.5 hover:bg-[var(--surface-hover)]">
                <input type="checkbox" name="events[]" value="{{ $event->value }}" class="mt-1 size-4 accent-[var(--action)]" @checked(in_array($event->value, $chosen, true))>
                <span>
                    <span class="block font-medium">{{ $event->label() }}</span>
                    <span class="block text-[13px] text-[var(--text-2)]">{{ $event->description() }}</span>
                </span>
            </label>
        @endforeach
    </div>
    @if ($isNew)
        @error('events')<p class="field-error">{{ $message }}</p>@enderror
    @endif
</fieldset>
