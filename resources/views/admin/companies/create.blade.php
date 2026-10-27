@php $company = new \App\Models\Company(['trial_ends_at' => now()->addDays($trialDays)]); @endphp
<x-layouts::admin :title="__('admin.companies.new')">
    <form method="POST" action="{{ route('admin.companies.store') }}" class="mx-auto grid max-w-[1000px] gap-6 lg:grid-cols-2">
        @csrf

        <section class="plate">
            <div class="plate-head"><h2 class="t-label text-[var(--text)]">{{ __('admin.fields.company') }}</h2></div>
            <div class="flex flex-col gap-5 p-5">
                @include('admin.companies._fields')
            </div>
        </section>

        <div class="flex flex-col gap-6">
            <section class="plate">
                <div class="plate-head"><h2 class="t-label text-[var(--text)]">{{ __('admin.companies.owner') }}</h2></div>
                <div class="flex flex-col gap-5 p-5">
                    <x-field :label="__('admin.fields.person_name')" name="owner_name" required><x-input name="owner_name" required maxlength="191" /></x-field>
                    <x-field :label="__('admin.fields.email')" name="owner_email" required :hint="__('admin.hints.owner_email')"><x-input name="owner_email" type="email" required maxlength="191" /></x-field>
                    <x-field :label="__('admin.fields.phone')" name="owner_phone"><x-input name="owner_phone" type="tel" maxlength="32" /></x-field>
                    <x-field :label="__('admin.fields.password')" name="owner_password" :hint="__('admin.hints.password')"><x-input name="owner_password" type="password" autocomplete="new-password" /></x-field>
                </div>
            </section>

            <button class="btn btn-primary">{{ __('admin.companies.create') }}</button>
        </div>
    </form>
</x-layouts::admin>
