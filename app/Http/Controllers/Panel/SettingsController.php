<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Support\CurrentCompany;
use DateTimeZone;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class SettingsController extends Controller
{
    public function __construct(private readonly CurrentCompany $tenant) {}

    public function edit()
    {
        return view('panel.settings.edit', [
            'company' => $this->tenant->getOrFail(),
            'timezones' => DateTimeZone::listIdentifiers(),
        ]);
    }

    public function update(Request $request)
    {
        $company = $this->tenant->getOrFail();

        $data = $request->validate([
            'name' => ['required', 'string', 'max:191'],
            'short_name' => ['nullable', 'string', 'max:32'],
            'timezone' => ['required', Rule::in(DateTimeZone::listIdentifiers())],
            'locale' => ['required', Rule::in(['az', 'en', 'ru', 'tr'])],
            'country' => ['required', 'string', 'size:2'],
            'currency' => ['required', 'string', 'size:3'],
            'primary_color' => ['required', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'secondary_color' => ['required', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'brand_logo' => ['nullable', 'image', 'mimes:png,jpg,jpeg,webp,svg', 'max:1024'],
        ]);

        if ($request->hasFile('brand_logo')) {
            if ($company->brand_logo) {
                Storage::disk('public')->delete($company->brand_logo);
            }
            $data['brand_logo'] = $request->file('brand_logo')->store("companies/{$company->id}", 'public');
        } else {
            unset($data['brand_logo']);
        }

        $data['country'] = strtoupper($data['country']);
        $data['currency'] = strtoupper($data['currency']);
        $company->update($data);

        return back()->with('status', __('Settings saved.'));
    }
}
