<x-layouts::admin :title="$faq->exists ? __('admin.faq.edit') : __('admin.faq.new')">
    <form method="POST" action="{{ $faq->exists ? route('admin.faq.update', $faq) : route('admin.faq.store') }}" class="plate mx-auto max-w-[760px]">
        @csrf
        @if ($faq->exists) @method('PUT') @endif
        <div class="flex flex-col gap-5 p-5">
            <div class="grid gap-4 sm:grid-cols-3">
                <x-field :label="__('admin.fields.locale')" name="locale"><x-select name="locale" :options="['az' => 'Azərbaycan', 'en' => 'English', 'ru' => 'Русский', 'tr' => 'Türkçe']" :value="$faq->locale" /></x-field>
                <x-field :label="__('admin.fields.sort')" name="sort"><x-input name="sort" type="number" min="0" :value="$faq->sort" required /></x-field>
                <label class="flex items-center gap-3 self-end pb-2.5 text-[var(--text-2)]">
                    <input type="hidden" name="is_active" value="0">
                    <input type="checkbox" name="is_active" value="1" class="check" @checked(old('is_active', $faq->is_active))> {{ __('admin.faq.published') }}
                </label>
            </div>
            <x-field :label="__('admin.fields.question')" name="question" required><x-input name="question" :value="$faq->question" required maxlength="255" /></x-field>
            <x-field :label="__('admin.fields.answer')" name="answer" required :hint="__('admin.faq.answer_hint')">
                <textarea name="answer" id="answer" rows="7" maxlength="5000" required class="input h-auto py-2.5">{{ old('answer', $faq->answer) }}</textarea>
            </x-field>
            <div class="flex gap-3">
                <button class="btn btn-primary">{{ __('admin.save') }}</button>
                <a href="{{ route('admin.faq.index', ['locale' => $faq->locale]) }}" class="btn btn-ghost">{{ __('admin.cancel') }}</a>
            </div>
        </div>
    </form>
</x-layouts::admin>
