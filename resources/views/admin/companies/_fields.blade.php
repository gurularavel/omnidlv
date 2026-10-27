{{-- Company fields shared by the create and edit forms. Dates are days in the company's timezone. --}}
@php $day = fn ($t) => $t?->copy()->setTimezone($company->timezone ?? 'Asia/Baku')->toDateString(); @endphp
<x-field :label="__('admin.fields.company_name')" name="name" required><x-input name="name" :value="$company->name" required maxlength="191" /></x-field>
<x-field :label="__('admin.fields.short_name')" name="short_name" :hint="__('admin.hints.short_name')"><x-input name="short_name" :value="$company->short_name" maxlength="32" /></x-field>
<x-field :label="__('admin.fields.plan')" name="plan_slug" required>
    <x-select name="plan_slug" :options="$plans" :value="$company->plan_slug ?? 'scale'" />
</x-field>
<div class="grid gap-4 sm:grid-cols-2">
    <x-field :label="__('admin.fields.trial_ends_at')" name="trial_ends_at" :hint="__('admin.hints.trial')"><x-input name="trial_ends_at" type="date" :value="$day($company->trial_ends_at)" /></x-field>
    <x-field :label="__('admin.fields.subscription_ends_at')" name="subscription_ends_at" :hint="__('admin.hints.subscription')"><x-input name="subscription_ends_at" type="date" :value="$day($company->subscription_ends_at)" /></x-field>
</div>
<div class="grid gap-4 sm:grid-cols-2">
    <x-field :label="__('admin.fields.timezone')" name="timezone"><x-select name="timezone" :options="$timezones" :value="$company->timezone ?? 'Asia/Baku'" /></x-field>
    <x-field :label="__('admin.fields.locale')" name="locale"><x-select name="locale" :options="$locales" :value="$company->locale ?? 'az'" /></x-field>
</div>
<x-field :label="__('admin.fields.admin_note')" name="admin_note" :hint="__('admin.hints.admin_note')">
    <textarea name="admin_note" id="admin_note" rows="3" maxlength="2000" class="input h-auto py-2.5">{{ old('admin_note', $company->admin_note) }}</textarea>
</x-field>
