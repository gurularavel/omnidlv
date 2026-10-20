<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\TaskRules;
use Illuminate\Foundation\Http\FormRequest;

class UpdateTaskRequest extends FormRequest
{
    use TaskRules;

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->normalizeTaskInput();
    }

    public function rules(): array
    {
        $rules = $this->taskRules(update: true);
        // Assignment changes go through the assign/status endpoints, not a plain update.
        unset($rules['assignment'], $rules['assignment.type'], $rules['assignment.courier_id'], $rules['tracking_code']);

        return $rules;
    }
}
