<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTaskStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // Status is a system status or one of the current company's own.
            'status_id' => [
                'required', 'integer',
                Rule::exists('statuses', 'id')->where(function ($q) {
                    $q->where('is_active', true)->where(fn ($q) => $q->whereNull('company_id')
                        ->orWhere('company_id', app(\App\Support\CurrentCompany::class)->id()));
                }),
            ],
            'notes' => ['nullable', 'string', 'max:255'],
            'lat' => ['nullable', 'numeric', 'between:-90,90'],
            'lng' => ['nullable', 'numeric', 'between:-180,180'],
        ];
    }
}
