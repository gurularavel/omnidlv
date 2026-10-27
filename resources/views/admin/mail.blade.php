<x-layouts::admin :title="__('admin.nav.mail')">
    <div class="mx-auto grid max-w-[1000px] gap-6 lg:grid-cols-[minmax(0,1fr)_340px]">
        <form method="POST" action="{{ route('admin.mail.update') }}" class="plate" x-data="{ mailer: @js(old('mailer', $settings['mailer'])) }">
            @csrf @method('PUT')
            <div class="plate-head"><h2 class="t-label text-[var(--text)]">{{ __('admin.mail.smtp') }}</h2></div>
            <div class="flex flex-col gap-5 p-5">
                <p class="text-[var(--text-2)]">{{ __('admin.mail.intro') }}</p>

                <x-field :label="__('admin.mail.mailer')" name="mailer">
                    <x-select name="mailer" :options="['smtp' => __('admin.mail.mailers.smtp'), 'log' => __('admin.mail.mailers.log')]" :value="$settings['mailer']" x-model="mailer" />
                </x-field>

                <div class="flex flex-col gap-5" x-show="mailer === 'smtp'">
                    <div class="grid gap-4 sm:grid-cols-[minmax(0,1fr)_120px]">
                        <x-field :label="__('admin.mail.host')" name="host"><x-input name="host" :value="$settings['host']" placeholder="smtp.example.com" maxlength="255" /></x-field>
                        <x-field :label="__('admin.mail.port')" name="port"><x-input name="port" type="number" min="1" max="65535" :value="$settings['port']" /></x-field>
                    </div>
                    <x-field :label="__('admin.mail.encryption')" name="encryption" :hint="__('admin.mail.encryption_hint')">
                        <x-select name="encryption" :options="['tls' => 'STARTTLS (587)', 'ssl' => 'SSL/TLS (465)', 'none' => __('admin.mail.no_encryption')]" :value="$settings['encryption']" />
                    </x-field>
                    <div class="grid gap-4 sm:grid-cols-2">
                        <x-field :label="__('admin.mail.username')" name="username"><x-input name="username" :value="$settings['username']" autocomplete="off" maxlength="255" /></x-field>
                        <x-field :label="__('admin.mail.password')" name="password" :hint="$hasPassword ? __('admin.mail.password_saved') : null">
                            <x-input name="password" type="password" autocomplete="new-password" maxlength="255" />
                        </x-field>
                    </div>
                    @if ($hasPassword)
                        <label class="flex items-center gap-3 text-[var(--text-2)]"><input type="checkbox" name="clear_password" value="1" class="check"> {{ __('admin.mail.clear_password') }}</label>
                    @endif
                </div>

                <div class="grid gap-4 sm:grid-cols-2">
                    <x-field :label="__('admin.mail.from_address')" name="from_address" required><x-input name="from_address" type="email" :value="$settings['from_address']" required maxlength="255" /></x-field>
                    <x-field :label="__('admin.mail.from_name')" name="from_name" required><x-input name="from_name" :value="$settings['from_name']" required maxlength="100" /></x-field>
                </div>

                <button class="btn btn-primary">{{ __('admin.save') }}</button>
            </div>
        </form>

        <div class="flex flex-col gap-6">
            <section class="plate">
                <div class="plate-head"><h2 class="t-label text-[var(--text)]">{{ __('admin.fields.state') }}</h2></div>
                <p class="p-5 text-[var(--text-2)]">
                    @if ($configured)
                        <span class="status" data-form="solid" style="--status-ink:#22b14c">{{ __('admin.mail.state_smtp') }}</span>
                    @elseif ($envMailer === 'log')
                        <span class="status" data-form="solid" style="--status-ink:#e5484d">{{ __('admin.mail.state_log') }}</span>
                        <span class="mt-2 block">{{ __('admin.mail.state_log_hint') }}</span>
                    @else
                        <span class="status" data-form="dashed" style="--status-ink:#8e99b8">{{ __('admin.mail.state_env', ['mailer' => $envMailer]) }}</span>
                    @endif
                </p>
            </section>

            <form method="POST" action="{{ route('admin.mail.test') }}" class="plate">
                @csrf
                <div class="plate-head"><h2 class="t-label text-[var(--text)]">{{ __('admin.mail.test') }}</h2></div>
                <div class="flex flex-col gap-4 p-5">
                    <x-field :label="__('admin.mail.test_to')" name="to"><x-input name="to" type="email" :value="auth()->user()->email" required /></x-field>
                    <button class="btn"><x-icon name="send" :size="16" />{{ __('admin.mail.test_send') }}</button>
                </div>
            </form>
        </div>
    </div>
</x-layouts::admin>
