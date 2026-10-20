<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\TaskRules;
use Illuminate\Foundation\Http\FormRequest;

class StoreTaskRequest extends FormRequest
{
    use TaskRules;

    public function authorize(): bool
    {
        return true; // route middleware already enforces tenant and role
    }

    protected function prepareForValidation(): void
    {
        $this->normalizeTaskInput();
    }

    public function rules(): array
    {
        return $this->taskRules();
    }
}
