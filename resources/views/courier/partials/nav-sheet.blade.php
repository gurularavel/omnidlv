{{-- Which app opens directions. Shown on "Navigate" unless the courier saved a choice. --}}
<div x-data="{ remember: false }" x-show="$store.nav.links" x-cloak @keydown.escape.window="$store.nav.close()">
    <div x-show="$store.nav.links" x-transition.opacity class="fixed inset-0 z-[60] bg-night-950/45" @click="$store.nav.close()"></div>
    <section x-show="$store.nav.links" x-transition.origin.bottom
             class="fixed inset-x-0 bottom-0 z-[70] mx-auto max-w-[520px] rounded-t-2xl bg-white px-4 pb-[max(1.25rem,env(safe-area-inset-bottom))] pt-5"
             role="dialog" aria-modal="true" aria-labelledby="nav-sheet-title">
        <div class="mb-4 flex items-start gap-3">
            <h2 id="nav-sheet-title" class="t-title flex-1">{{ __('Open directions in') }}</h2>
            <button type="button" class="-mr-2 -mt-2 grid size-11 shrink-0 place-items-center" @click="$store.nav.close()" aria-label="{{ __('Close') }}"><x-icon name="x" :size="20" /></button>
        </div>
        <div class="grid grid-cols-2 gap-3">
            <button type="button" class="btn h-16 text-[16px]" @click="$store.nav.go('google', remember)">Google Maps</button>
            <button type="button" class="btn h-16 text-[16px]" @click="$store.nav.go('waze', remember)">Waze</button>
        </div>
        <label class="mt-4 flex items-center gap-3 py-2 text-[16px]">
            <input type="checkbox" x-model="remember" class="size-5 accent-[var(--action)]">
            {{ __('Always use this app') }}
        </label>
        <p class="text-[14px] text-[var(--text-3)]">{{ __('You can change it later in the menu.') }}</p>
    </section>
</div>
