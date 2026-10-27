{{--
    Cookie notice. The site sets only strictly necessary cookies, so this records that the visitor
    has been told and agreed; it gates nothing. Plain JS: the guest pages load no Alpine.
--}}
@unless (request()->cookie('cookie_consent'))
    <div id="cookie-consent" role="region" aria-label="{{ __('site.cookie_banner.label') }}"
         class="plate fixed inset-x-4 bottom-4 z-50 flex flex-col gap-3 p-4 shadow-[0_12px_32px_rgb(0_0_0/0.45)] sm:left-auto sm:max-w-[440px]">
        <p class="text-[14px] text-[var(--text-2)]">{{ __('site.cookie_banner.text') }}</p>
        <div class="flex items-center gap-3">
            <button type="button" class="btn btn-primary btn-sm" data-cookie-accept>{{ __('site.cookie_banner.accept') }}</button>
            <a href="{{ route('site.cookies') }}" class="text-[13px] text-[var(--text-2)] underline hover:text-[var(--text)]">{{ __('site.cookie_banner.more') }}</a>
        </div>
    </div>
    <script>
        document.querySelector('[data-cookie-accept]').addEventListener('click', () => {
            document.cookie = 'cookie_consent=1; max-age=' + 60 * 60 * 24 * 365 + '; path=/; SameSite=Lax' + (location.protocol === 'https:' ? '; Secure' : '');
            document.getElementById('cookie-consent').remove();
        });
    </script>
@endunless
