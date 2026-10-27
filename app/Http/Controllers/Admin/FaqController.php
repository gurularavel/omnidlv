<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Middleware\SetLocale;
use App\Models\Faq;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class FaqController extends Controller
{
    public function index(Request $request)
    {
        $locale = in_array($request->input('locale'), SetLocale::LOCALES, true) ? $request->input('locale') : 'az';

        return view('admin.faq.index', [
            'faqs' => Faq::query()->where('locale', $locale)->orderBy('sort')->orderBy('id')->get(),
            'locale' => $locale,
            'counts' => Faq::query()->selectRaw('locale, count(*) as total')->groupBy('locale')->pluck('total', 'locale'),
        ]);
    }

    public function create(Request $request)
    {
        $locale = in_array($request->input('locale'), SetLocale::LOCALES, true) ? $request->input('locale') : 'az';

        return view('admin.faq.form', ['faq' => new Faq([
            'locale' => $locale,
            'is_active' => true,
            'sort' => (int) Faq::query()->where('locale', $locale)->max('sort') + 1,
        ])]);
    }

    public function store(Request $request)
    {
        $faq = Faq::create($this->validated($request));

        return redirect()->route('admin.faq.index', ['locale' => $faq->locale])->with('status', __('admin.saved'));
    }

    public function edit(Faq $faq)
    {
        return view('admin.faq.form', ['faq' => $faq]);
    }

    public function update(Request $request, Faq $faq)
    {
        $faq->update($this->validated($request));

        return redirect()->route('admin.faq.index', ['locale' => $faq->locale])->with('status', __('admin.saved'));
    }

    public function destroy(Faq $faq)
    {
        $faq->delete();

        return redirect()->route('admin.faq.index', ['locale' => $faq->locale])->with('status', __('admin.faq.deleted'));
    }

    private function validated(Request $request): array
    {
        return [
            ...$request->validate([
                'locale' => ['required', Rule::in(SetLocale::LOCALES)],
                'question' => ['required', 'string', 'max:255'],
                'answer' => ['required', 'string', 'max:5000'],
                'sort' => ['required', 'integer', 'min:0', 'max:1000'],
            ]),
            'is_active' => $request->boolean('is_active'),
        ];
    }
}
