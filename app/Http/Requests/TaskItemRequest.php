<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TaskItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $required = $this->isMethod('POST') ? 'required' : 'sometimes';
        $task = $this->route('task');
        $item = $this->route('item');

        return [
            'integration_id' => [
                $required, 'string', 'max:191',
                Rule::unique('task_items', 'integration_id')->where('task_id', $task?->id)->ignore($item?->id),
            ],
            'name' => [$required, 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'quantity' => ['nullable', 'integer', 'min:0'],
            'currency' => ['nullable', 'string', 'size:3'],
            'amount' => ['nullable', 'numeric', 'min:0'],
            'barcode' => ['nullable', 'string', 'max:128'],
        ];
    }
}
