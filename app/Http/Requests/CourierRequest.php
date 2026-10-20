<?php

namespace App\Http\Requests;

use App\Enums\VehicleType;
use App\Http\Requests\Concerns\TenantRules;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Create (panel invite) and update (panel + PATCH /couriers/{courier}). */
class CourierRequest extends FormRequest
{
    use TenantRules;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $creating = $this->isMethod('POST');
        $required = $creating ? 'required' : 'sometimes';

        return [
            'name' => [$required, 'string', 'max:191'],
            'phone_country' => ['nullable', 'string', 'size:2'],
            'phone' => [$creating ? 'required' : 'required_with:phone_country', 'string', 'max:32'],
            'email' => ['nullable', 'email', 'max:191'],
            'locale' => ['nullable', Rule::in(['az', 'en', 'ru', 'tr'])],
            'integration_id' => ['nullable', 'string', 'max:191'],
            'code' => ['nullable', 'string', 'max:50'],
            'vehicle_type_slug' => ['nullable', Rule::enum(VehicleType::class)],
            'vehicle_description' => ['nullable', 'string', 'max:191'],
            'vehicle_license_plate' => ['nullable', 'string', 'max:20'],
            'vehicle_color' => ['nullable', 'string', 'max:50'],
            'capacity' => ['nullable', 'integer', 'min:0', 'max:10000'],
            'pool_capacity' => ['nullable', 'integer', 'min:0', 'max:10000'],
            'notes' => ['nullable', 'string', 'max:10000'],
            'tags' => ['nullable', 'array', 'max:50'],
            'tags.*' => ['string', 'max:64'],
            'team_ids' => ['nullable', 'array'],
            'team_ids.*' => ['integer', $this->existsInCompany('teams')],
        ];
    }
}
