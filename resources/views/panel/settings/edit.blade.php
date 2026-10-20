@php $plan = $company->plan(); @endphp
<x-layouts::app :title="__('Settings')">
    <form method="POST" action="{{ route('settings.update') }}" enctype="multipart/form-data" class="mx-auto grid max-w-[1000px] gap-6 lg:grid-cols-2">
        @csrf @method('PUT')

        <section class="plate">
            <div class="plate-head"><h2 class="t-label text-[var(--text)]">{{ __('Company') }}</h2></div>
            <div class="flex flex-col gap-5 p-5">
                <x-field :label="__('Company name')" name="name" required><x-input name="name" :value="$company->name" required /></x-field>
                <x-field :label="__('Short name')" name="short_name" :hint="__('Used as the SMS sender name where possible.')"><x-input name="short_name" :value="$company->short_name" maxlength="32" /></x-field>
                <div class="grid grid-cols-2 gap-4">
                    <x-field :label="__('Country')" name="country"><x-input name="country" :value="$company->country" maxlength="2" /></x-field>
                    <x-field :label="__('Currency')" name="currency"><x-input name="currency" :value="$company->currency" maxlength="3" /></x-field>
                </div>
                <x-field :label="__('Timezone')" name="timezone" :hint="__('Delivery windows and reports use this timezone.')">
                    <x-select name="timezone" :options="array_combine($timezones, $timezones)" :value="$company->timezone" />
                </x-field>
                <x-field :label="__('Default language')" name="locale" :hint="__('For recipient SMS and the tracking page.')">
                    <x-select name="locale" :options="['az' => 'Azərbaycan', 'en' => 'English', 'ru' => 'Русский', 'tr' => 'Türkçe']" :value="$company->locale" />
                </x-field>
            </div>
        </section>

        <div class="flex flex-col gap-6">
            <section class="plate">
                <div class="plate-head"><h2 class="t-label text-[var(--text)]">{{ __('Tracking page brand') }}</h2></div>
                <div class="flex flex-col gap-5 p-5">
                    <x-field :label="__('Logo')" name="brand_logo" :hint="__('PNG, JPG, WEBP or SVG up to 1 MB.')">
                        @if ($company->brand_logo)
                            <img src="{{ asset('storage/'.$company->brand_logo) }}" alt="" class="mb-3 h-12 rounded bg-white p-1.5">
                        @endif
                        <input type="file" name="brand_logo" id="brand_logo" accept="image/*" class="block w-full text-[13px] text-[var(--text-2)] file:mr-3 file:rounded-md file:border-[1.5px] file:border-[var(--hairline-strong)] file:bg-transparent file:px-3 file:py-2 file:font-display file:text-[12px] file:font-semibold file:uppercase file:tracking-[0.08em] file:text-[var(--text)]">
                    </x-field>
                    <div class="grid grid-cols-2 gap-4">
                        <x-field :label="__('Main colour')" name="primary_color"><x-input name="primary_color" type="color" class="h-11 p-1" :value="$company->primary_color" /></x-field>
                        <x-field :label="__('Second colour')" name="secondary_color"><x-input name="secondary_color" type="color" class="h-11 p-1" :value="$company->secondary_color" /></x-field>
                    </div>
                </div>
            </section>

            <section class="plate">
                <div class="plate-head"><h2 class="t-label text-[var(--text)]">{{ __('Plan') }}</h2></div>
                <div class="p-5">
                    <p class="t-display">{{ ucfirst($plan['slug']) }}</p>
                    <p class="mt-2 text-[var(--text-2)]">
                        @if ($company->onTrial())
                            {{ __('Trial until :date.', ['date' => \App\Support\Ui::localTime($company->trial_ends_at, 'd M Y')]) }}
                        @elseif ($company->subscription_ends_at)
                            {{ __('Paid until :date.', ['date' => \App\Support\Ui::localTime($company->subscription_ends_at, 'd M Y')]) }}
                        @else
                            {{ __('No active subscription.') }}
                        @endif
                        {{ $plan['history_days'] ? __('Reports cover :days days.', ['days' => $plan['history_days']]) : __('Reports have no history limit.') }}
                    </p>
                </div>
            </section>

            <button class="btn btn-primary">{{ __('Save settings') }}</button>
        </div>
    </form>
</x-layouts::app>
