@php $languages = ['az' => 'Azərbaycan', 'en' => 'English', 'ru' => 'Русский', 'tr' => 'Türkçe']; @endphp
<x-layouts::admin :title="__('admin.nav.faq')">
    <x-slot:actions>
        <a href="{{ route('site.faq', ['lang' => $locale]) }}" target="_blank" rel="noopener" class="btn btn-ghost"><x-icon name="external-link" :size="16" /><span class="hidden sm:inline">{{ __('admin.faq.view') }}</span></a>
        <a href="{{ route('admin.faq.create', ['locale' => $locale]) }}" class="btn btn-primary"><x-icon name="plus" :size="16" />{{ __('admin.faq.new') }}</a>
    </x-slot:actions>

    <p class="mb-5 max-w-[80ch] text-[var(--text-2)]">{{ __('admin.faq.intro') }}</p>

    <nav class="mb-5 flex flex-wrap gap-2" aria-label="{{ __('admin.fields.locale') }}">
        @foreach ($languages as $code => $name)
            <a href="{{ route('admin.faq.index', ['locale' => $code]) }}" @class(['btn btn-sm', 'btn-primary' => $locale === $code, 'btn-ghost' => $locale !== $code])>{{ $name }} · {{ $counts[$code] ?? 0 }}</a>
        @endforeach
    </nav>

    <div class="plate overflow-hidden">
        @if ($faqs->isEmpty())
            <x-empty icon="message-square-text" :title="__('admin.faq.empty')" :action="route('admin.faq.create', ['locale' => $locale])" :action-label="__('admin.faq.new')">{{ __('admin.faq.empty_hint') }}</x-empty>
        @else
            <table class="tbl">
                <thead><tr><th class="w-16">{{ __('admin.fields.sort') }}</th><th>{{ __('admin.fields.question') }}</th><th></th></tr></thead>
                <tbody>
                @foreach ($faqs as $faq)
                    <tr @class(['opacity-60' => ! $faq->is_active])>
                        <td class="text-[var(--text-3)]">{{ $faq->sort }}</td>
                        <td><span class="block font-medium">{{ $faq->question }}</span>
                            <span class="text-[13px] text-[var(--text-2)]">{{ Str::limit($faq->answer, 140) }}</span>
                            @unless ($faq->is_active)<span class="tag ml-1">{{ __('admin.plans.hidden') }}</span>@endunless</td>
                        <td>
                            <span class="flex justify-end gap-2">
                                <a href="{{ route('admin.faq.edit', $faq) }}" class="btn btn-sm"><x-icon name="pencil" :size="14" />{{ __('admin.edit') }}</a>
                                <form method="POST" action="{{ route('admin.faq.destroy', $faq) }}" x-data @submit="if (! window.confirm(@js(__('admin.faq.delete_confirm')))) $event.preventDefault()">
                                    @csrf @method('DELETE')
                                    <button class="btn btn-sm btn-ghost" aria-label="{{ __('admin.delete') }}"><x-icon name="trash-2" :size="14" /></button>
                                </form>
                            </span>
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        @endif
    </div>
</x-layouts::admin>
