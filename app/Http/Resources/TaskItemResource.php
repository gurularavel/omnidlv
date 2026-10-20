<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\TaskItem */
class TaskItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'task_id' => $this->task_id,
            'integration_id' => $this->integration_id,
            'name' => $this->name,
            'description' => $this->description,
            'notes' => $this->notes,
            'quantity' => $this->quantity,
            'currency' => $this->currency,
            'amount' => $this->amount !== null ? (float) $this->amount : null,
            'barcode' => $this->barcode,
            'scanned_at' => $this->scanned_at,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
