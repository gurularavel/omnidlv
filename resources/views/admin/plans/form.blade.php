@php $selected = old('features', $plan->features ?? []); @endphp
<x-layouts::admin :title="$plan->exists ? $plan->name : __('admin.plans.new')">
    <form method="POST" action="{{ $plan->exists ? route('admin.plans.update', $plan) : route('admin.plans.store') }}" class="mx-auto grid max-w-[1000px] gap-6 lg:grid-cols-2">
        @csrf
        @if ($plan->exists) @method('PUT') @endif

        <section class="plate">
            <div class="plate-head"><h2 class="t-label text-[var(--text)]">{{ __('admin.plans.details') }}</h2></div>
            <div class="flex flex-col gap-5 p-5">
                <div class="grid gap-4 sm:grid-cols-2">
                    <x-field :label="__('admin.fields.plan_name')" name="name" required><x-input name="name" :value="$plan->name" required maxlength="100" /></x-field>
                    <x-field :label="__('admin.fields.slug')" name="slug" :required="! $plan->exists" :hint="$plan->exists ? __('admin.hints.slug_fixed') : __('admin.hints.slug')">
                        @if ($plan->exists)
                            <input class="input" value="{{ $plan->slug }}" disabled id="slug">
                        @else
                            <x-input name="slug" required maxlength="32" pattern="[a-z0-9_-]+" />
                        @endif
                    </x-field>
                </div>
                <x-field :label="__('admin.fields.description')" name="description">
                    <textarea name="description" id="description" rows="2" maxlength="1000" class="input h-auto py-2.5">{{ old('description', $plan->description) }}</textarea>
                </x-field>
                <div class="grid gap-4 sm:grid-cols-2">
                    <x-field :label="__('admin.fields.price_monthly')" name="price_monthly" :hint="__('admin.hints.price')"><x-input name="price_monthly" type="number" step="0.01" min="0" :value="$plan->price_monthly" /></x-field>
                    <x-field :label="__('admin.fields.price_yearly')" name="price_yearly" :hint="__('admin.hints.price_yearly')"><x-input name="price_yearly" type="number" step="0.01" min="0" :value="$plan->price_yearly" /></x-field>
                </div>
                <div class="grid gap-4 sm:grid-cols-2">
                    <x-field :label="__('admin.fields.history_days')" name="history_days" :hint="__('admin.hints.empty_unlimited')"><x-input name="history_days" type="number" min="1" :value="$plan->history_days" /></x-field>
                    <x-field :label="__('admin.fields.max_couriers')" name="max_couriers" :hint="__('admin.hints.empty_unlimited')"><x-input name="max_couriers" type="number" min="1" :value="$plan->max_couriers" /></x-field>
                </div>
                <div class="grid gap-4 sm:grid-cols-2">
                    <x-field :label="__('admin.fields.sort')" name="sort"><x-input name="sort" type="number" min="0" :value="$plan->sort" required /></x-field>
                    <label class="flex items-center gap-3 self-end pb-2.5 text-[var(--text-2)]">
                        <input type="hidden" name="is_active" value="0">
                        <input type="checkbox" name="is_active" value="1" class="check" @checked(old('is_active', $plan->is_active))> {{ __('admin.plans.active') }}
                    </label>
                </div>
            </div>
        </section>

        <div class="flex flex-col gap-6">
            <section class="plate">
                <div class="plate-head"><h2 class="t-label text-[var(--text)]">{{ __('admin.plans.features') }}</h2></div>
                <fieldset class="grid gap-2.5 p-5 sm:grid-cols-2">
                    <legend class="sr-only">{{ __('admin.plans.features') }}</legend>
                    @foreach (\App\Support\Plans::FEATURES as $feature)
                        <label class="flex items-center gap-3 text-[var(--text-2)]">
                            <input type="checkbox" name="features[]" value="{{ $feature }}" class="check" @checked(in_array($feature, $selected, true))> {{ __('admin.features.'.$feature) }}
                        </label>
                    @endforeach
                    @error('features.*')<p class="field-error sm:col-span-2">{{ $message }}</p>@enderror
                </fieldset>
            </section>

            <div class="flex gap-3">
                <button class="btn btn-primary flex-1">{{ __('admin.save') }}</button>
                <a href="{{ route('admin.plans.index') }}" class="btn btn-ghost">{{ __('admin.cancel') }}</a>
            </div>
        </div>
    </form>
</x-layouts::admin>
