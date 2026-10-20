<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\TenantRules;
use Illuminate\Foundation\Http\FormRequest;

class TeamRequest extends FormRequest
{
    use TenantRules;

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        // The panel's map editor posts the polygon as a JSON string.
        if (is_string($this->input('polygon'))) {
            $this->merge(['polygon' => json_decode($this->input('polygon'), true) ?: null]);
        }
    }

    public function rules(): array
    {
        $required = $this->isMethod('POST') ? 'required' : 'sometimes';

        return [
            'name' => [$required, 'string', 'max:191'],
            'hub_id' => ['nullable', 'integer', $this->existsInCompany('hubs')],
            'integration_id' => ['nullable', 'string', 'max:191'],
            'color' => ['nullable', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'is_default' => ['nullable', 'boolean'],
            'polygon' => ['nullable', 'array', 'min:3', 'max:2000'],
            'polygon.*' => ['array', 'size:2'],
            'polygon.*.0' => ['numeric', 'between:-90,90'],
            'polygon.*.1' => ['numeric', 'between:-180,180'],
            'tags' => ['nullable', 'array', 'max:50'],
            'tags.*' => ['string', 'max:64'],
            'courier_ids' => ['nullable', 'array'],
            'courier_ids.*' => ['integer', $this->existsCourier()],
        ];
    }
}
