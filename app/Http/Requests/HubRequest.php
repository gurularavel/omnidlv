<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\TenantRules;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class HubRequest extends FormRequest
{
    use TenantRules;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $required = $this->isMethod('POST') ? 'required' : 'sometimes';
        $hub = $this->route('hub');

        return [
            'name' => [$required, 'string', 'max:191'],
            'integration_id' => [
                'nullable', 'string', 'max:191',
                Rule::unique('hubs', 'integration_id')
                    ->where('company_id', $this->companyId())
                    ->whereNull('deleted_at')
                    ->ignore($hub?->id),
            ],
            'address' => [$required, 'string', 'max:500'],
            'address_building' => ['nullable', 'string', 'max:191'],
            'address_apartment' => ['nullable', 'string', 'max:191'],
            'address_floor' => ['nullable', 'string', 'max:191'],
            'lat' => ['nullable', 'required_with:lng', 'numeric', 'between:-90,90'],
            'lng' => ['nullable', 'required_with:lat', 'numeric', 'between:-180,180'],
            'tags' => ['nullable', 'array', 'max:50'],
            'tags.*' => ['string', 'max:64'],
        ];
    }
}
