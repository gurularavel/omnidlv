@php
    $editing = $task->exists;
    $tz = $currentCompany->timezone;
    $local = fn ($t) => $t ? $t->copy()->setTimezone($tz)->format('Y-m-d\TH:i') : null;
    $items = old('items', $editing ? $task->items->map->only(['integration_id', 'name', 'quantity', 'amount'])->values()->all() : []);
    $req = [
        'delivery_code' => [__('SMS delivery code'), __('The courier enters the code the recipient received by SMS.'), $task->requires_delivery_code],
        'delivery_code_mandatory' => [__('Code is mandatory'), __('The task cannot be completed without the code.'), $task->requires_delivery_code_mandatory],
        'pod_photo' => [__('Proof photo'), __('The courier is asked for a photo at the door.'), $task->requires_pod_photo],
        'pod_photo_mandatory' => [__('Photo is mandatory'), __('The task cannot be completed without a photo.'), $task->requires_pod_photo_mandatory],
        'dont_ring_the_bell' => [__("Don't ring the bell"), null, $task->required_dont_ring_the_bell],
        'contactless' => [__('Contactless delivery'), null, $task->requires_contactless],
    ];
@endphp
<x-layouts::app :title="$editing ? __('Edit :code', ['code' => $task->tracking_code]) : __('New task')">
    <form method="POST" action="{{ $editing ? route('tasks.update', $task) : route('tasks.store') }}"
          class="mx-auto grid max-w-[1100px] gap-6 lg:grid-cols-[minmax(0,1fr)_320px]"
          x-data="{ assignment: @js(old('assignment.type', 'pool')), items: @js($items) }">
        @csrf
        @if ($editing) @method('PUT') @endif

        <div class="flex flex-col gap-6">
            @if ($errors->any())
                <div class="flash border-ink-scarlet/60" role="alert">
                    <x-icon name="circle-alert" class="mt-0.5 text-[var(--danger-text)]" />
                    <span>{{ __('Some fields need attention. They are marked below.') }}</span>
                </div>
            @endif

            <section class="plate">
                <div class="plate-head"><h2 class="t-label text-[var(--text)]">{{ __('Order') }}</h2></div>
                <div class="grid gap-5 p-5 sm:grid-cols-2">
                    <x-field :label="__('Hub')" name="hub_id" required>
                        <x-select name="hub_id" :options="$hubs->pluck('name', 'id')" :value="$task->hub_id" required />
                    </x-field>
                    <x-field :label="__('Type')" name="action_slug">
                        <x-select name="action_slug" :options="['drop_off' => __('Delivery to recipient'), 'pickup' => __('Pickup from customer (return)')]" :value="$task->type_slug ?? 'drop_off'" />
                    </x-field>
                    <x-field :label="__('Order number')" name="integration_id" :hint="__('Your own order or invoice number. Must be unique.')">
                        <x-input name="integration_id" :value="$task->integration_id" />
                    </x-field>
                    <x-field :label="__('Tags')" name="tags" :hint="__('Comma separated, e.g. express, fragile')">
                        <x-input name="tags" :value="implode(', ', $task->tags ?? [])" />
                    </x-field>
                </div>
            </section>

            <section class="plate">
                <div class="plate-head"><h2 class="t-label text-[var(--text)]">{{ __('Delivery window') }}</h2>
                    <span class="ml-auto text-[13px] text-[var(--text-3)]">{{ $tz }}</span></div>
                <div class="grid gap-5 p-5 sm:grid-cols-2">
                    <x-field :label="__('Not before')" name="starts_at" required>
                        <x-input name="starts_at" type="datetime-local" :value="$local($task->starts_at)" required />
                    </x-field>
                    <x-field :label="__('Deadline')" name="ends_at" required>
                        <x-input name="ends_at" type="datetime-local" :value="$local($task->ends_at)" required />
                    </x-field>
                    <x-field :label="__('Service time, min')" name="service_time" :hint="__('Time at the door, added to route planning.')">
                        <x-input name="service_time" type="number" min="0" :value="$task->service_time ?: null" />
                    </x-field>
                    <x-field :label="__('Quantity')" name="quantity">
                        <x-input name="quantity" type="number" min="0" :value="$task->quantity" />
                    </x-field>
                </div>
            </section>

            <section class="plate">
                <div class="plate-head"><h2 class="t-label text-[var(--text)]">{{ __('Recipient') }}</h2></div>
                <div class="grid gap-5 p-5 sm:grid-cols-2">
                    <x-field :label="__('Name')" name="destination[name]">
                        <x-input name="destination[name]" :value="$task->destination_name" autocomplete="off" />
                    </x-field>
                    <x-field :label="__('Phone')" name="destination[phone]" required :hint="__('Receives SMS updates and the tracking link.')">
                        <x-input name="destination[phone]" type="tel" inputmode="tel" :value="$task->destination_phone" placeholder="050 123 45 67" />
                    </x-field>
                    <x-field :label="__('Address')" name="destination[address]" class="sm:col-span-2" required :hint="__('Street and house, district, city. Coordinates are found automatically.')">
                        <x-input name="destination[address]" :value="$task->destination_address" />
                    </x-field>
                    <div class="grid grid-cols-3 gap-3 sm:col-span-2">
                        <x-field :label="__('Building')" name="destination[address_building]"><x-input name="destination[address_building]" :value="$task->destination_address_building" /></x-field>
                        <x-field :label="__('Floor')" name="destination[address_floor]"><x-input name="destination[address_floor]" :value="$task->destination_address_floor" /></x-field>
                        <x-field :label="__('Apartment')" name="destination[address_apartment]"><x-input name="destination[address_apartment]" :value="$task->destination_address_apartment" /></x-field>
                    </div>
                    <x-field :label="__('Directions for the courier')" name="destination[address_description]" class="sm:col-span-2">
                        <x-input name="destination[address_description]" :value="$task->destination_address_description" :placeholder="__('Entrance from the yard, code 1234')" />
                    </x-field>
                    <details class="sm:col-span-2" @if ($errors->has('destination.lat') || $task->destination_lat) open @endif>
                        <summary class="t-label cursor-pointer select-none">{{ __('Exact coordinates (optional)') }}</summary>
                        <div class="mt-4 grid gap-3 sm:grid-cols-[1fr_1fr_auto] sm:items-end">
                            <x-field :label="__('Latitude')" name="destination[lat]"><x-input name="destination[lat]" :value="$task->destination_lat" inputmode="decimal" /></x-field>
                            <x-field :label="__('Longitude')" name="destination[lng]"><x-input name="destination[lng]" :value="$task->destination_lng" inputmode="decimal" /></x-field>
                            <label class="flex h-10 items-center gap-2 text-[13px] text-[var(--text-2)]">
                                <input type="hidden" name="destination[allow_unresolved]" value="0">
                                <input type="checkbox" class="check" name="destination[allow_unresolved]" value="1" @checked(old('destination.allow_unresolved'))>
                                {{ __('Save even if the address is not found') }}
                            </label>
                        </div>
                    </details>
                    <input type="hidden" name="destination[phone_country]" value="{{ $task->destination_phone_country ?? $currentCompany->country }}">
                </div>
            </section>

            <section class="plate">
                <div class="plate-head">
                    <h2 class="t-label text-[var(--text)]">{{ __('Items') }}</h2>
                    <button type="button" class="btn btn-sm ml-auto" @click="items.push({ integration_id: '', name: '', quantity: 1, amount: '' })"><x-icon name="plus" :size="14" />{{ __('Add item') }}</button>
                </div>
                <div class="p-5">
                    <p x-show="items.length === 0" class="text-[var(--text-3)]">{{ __('Optional. Items let the courier check and scan what they hand over.') }}</p>
                    <div class="flex flex-col gap-3">
                        <template x-for="(item, i) in items" :key="i">
                            <div class="grid grid-cols-[120px_minmax(0,1fr)_80px_100px_38px] items-end gap-3">
                                <div><label class="field-label t-label" :for="`items-${i}-sku`">SKU</label>
                                    <input class="input" :id="`items-${i}-sku`" :name="`items[${i}][integration_id]`" x-model="item.integration_id" required></div>
                                <div><label class="field-label t-label" :for="`items-${i}-name`">{{ __('Name') }}</label>
                                    <input class="input" :id="`items-${i}-name`" :name="`items[${i}][name]`" x-model="item.name" required></div>
                                <div><label class="field-label t-label" :for="`items-${i}-qty`">{{ __('Qty') }}</label>
                                    <input class="input" type="number" min="0" :id="`items-${i}-qty`" :name="`items[${i}][quantity]`" x-model="item.quantity"></div>
                                <div><label class="field-label t-label" :for="`items-${i}-amount`">{{ $currentCompany->currency }}</label>
                                    <input class="input" type="number" min="0" step="0.01" :id="`items-${i}-amount`" :name="`items[${i}][amount]`" x-model="item.amount"></div>
                                <button type="button" class="btn btn-ghost btn-icon" @click="items.splice(i, 1)" aria-label="{{ __('Remove item') }}"><x-icon name="trash-2" :size="16" /></button>
                            </div>
                        </template>
                    </div>
                    @error('items')<p class="field-error">{{ $message }}</p>@enderror
                    @foreach ($errors->get('items.*') as $messages)<p class="field-error">{{ $messages[0] }}</p>@endforeach
                </div>
            </section>

            <section class="plate">
                <div class="plate-head"><h2 class="t-label text-[var(--text)]">{{ __('Notes') }}</h2></div>
                <div class="grid gap-5 p-5 sm:grid-cols-2">
                    <x-field :label="__('For the team')" name="notes" :hint="__('Not shown to the recipient.')">
                        <textarea name="notes" id="notes" class="input">{{ old('notes', $task->notes) }}</textarea>
                    </x-field>
                    <x-field :label="__('From the recipient')" name="recipient_notes">
                        <textarea name="recipient_notes" id="recipient_notes" class="input">{{ old('recipient_notes', $task->recipient_notes) }}</textarea>
                    </x-field>
                </div>
            </section>
        </div>

        <aside class="flex flex-col gap-6 lg:sticky lg:top-24 lg:self-start">
            @unless ($editing)
                <section class="plate">
                    <div class="plate-head"><h2 class="t-label text-[var(--text)]">{{ __('Assignment') }}</h2></div>
                    <div class="flex flex-col gap-4 p-5">
                        <x-field :label="__('How to assign')" name="assignment.type">
                            <select name="assignment[type]" id="assignment.type" class="input" x-model="assignment">
                                @foreach (\App\Enums\AssignmentType::cases() as $type)
                                    <option value="{{ $type->value }}">{{ $type->label() }}</option>
                                @endforeach
                            </select>
                        </x-field>
                        <div x-show="assignment === 'selected_courier'" x-cloak>
                            <x-field :label="__('Courier')" name="assignment.courier_id">
                                <x-select name="assignment[courier_id]" :options="$couriers" :placeholder="__('Choose a courier')" x-bind:required="assignment === 'selected_courier'" />
                            </x-field>
                        </div>
                        <x-field :label="__('Team')" name="assignment_options.team_id">
                            <x-select name="assignment_options[team_id]" :options="$teams" :placeholder="__('Any team')" />
                        </x-field>
                        <label class="flex items-start gap-3 text-[13px] text-[var(--text-2)]">
                            <input type="hidden" name="assignment_options[team_id_by_polygon]" value="0">
                            <input type="checkbox" class="check mt-0.5" name="assignment_options[team_id_by_polygon]" value="1" @checked(old('assignment_options.team_id_by_polygon'))>
                            {{ __('Pick the team by the delivery zone') }}
                        </label>
                    </div>
                </section>
            @endunless

            <section class="plate">
                <div class="plate-head"><h2 class="t-label text-[var(--text)]">{{ __('Requirements') }}</h2></div>
                <div class="flex flex-col gap-4 p-5">
                    @foreach ($req as $key => [$label, $hint, $checked])
                        <label class="flex items-start gap-3">
                            <input type="hidden" name="requirements[{{ $key }}]" value="0">
                            <input type="checkbox" class="check mt-0.5" name="requirements[{{ $key }}]" value="1" @checked(old("requirements.$key", $checked))>
                            <span><span class="block">{{ $label }}</span>
                                @if ($hint)<span class="block text-[13px] text-[var(--text-3)]">{{ $hint }}</span>@endif</span>
                        </label>
                    @endforeach
                </div>
            </section>

            <section class="plate">
                <div class="plate-head"><h2 class="t-label text-[var(--text)]">{{ __('Payment') }}</h2></div>
                <div class="grid grid-cols-2 gap-4 p-5">
                    <x-field :label="__('Method')" name="payment_type" class="col-span-2">
                        <x-select name="payment_type" :options="['prepaid' => __('Prepaid'), 'cash' => __('Cash on delivery'), 'card' => __('Card on delivery')]" :value="$task->payment_type" :placeholder="__('Not set')" />
                    </x-field>
                    <x-field :label="__('Amount to collect')" name="amount">
                        <x-input name="amount" type="number" min="0" step="0.01" :value="$task->amount" />
                    </x-field>
                    <x-field :label="__('Currency')" name="currency">
                        <x-input name="currency" maxlength="3" :value="$task->currency ?? $currentCompany->currency" />
                    </x-field>
                </div>
            </section>

            <div class="flex gap-3">
                <button class="btn btn-primary flex-1">{{ $editing ? __('Save changes') : __('Create task') }}</button>
                <a href="{{ $editing ? route('tasks.show', $task) : route('tasks.index') }}" class="btn">{{ __('Cancel') }}</a>
            </div>
        </aside>
    </form>
</x-layouts::app>
