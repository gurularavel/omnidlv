<?php

namespace App\Services;

use App\Enums\StatusCategory;
use App\Models\Task;
use App\Support\Phone;

/**
 * Public view of a task for the recipient. Nothing here may leak more than the recipient
 * already knows: names and phones are masked and the courier is only located while en route.
 */
class TrackingService
{
    public function find(string $code): ?Task
    {
        return Task::withoutGlobalScopes()
            ->with(['company', 'status', 'courier.courierLocation', 'statusLogs.status'])
            ->where('tracking_code', strtoupper(trim($code)))
            ->whereNull('deleted_at')
            ->first();
    }

    public function present(Task $task): array
    {
        $company = $task->company;
        $category = $task->status_category_slug;
        $showCourier = in_array($category, [StatusCategory::Assigned, StatusCategory::InProgress, StatusCategory::OnDelivery], true);
        $location = $showCourier ? $task->courier?->courierLocation : null;

        return [
            'tracking_code' => $task->tracking_code,
            'type_slug' => $task->type_slug->value,
            'origin_name' => $task->origin_name,
            'origin_address' => $task->origin_address,
            'origin_lat' => $task->origin_lat,
            'origin_lng' => $task->origin_lng,
            'destination_name' => $this->maskName($task->destination_name),
            'destination_address' => $task->destination_address,
            'destination_address_building' => $task->destination_address_building,
            'destination_address_floor' => $task->destination_address_floor,
            'destination_address_apartment' => $task->destination_address_apartment,
            'destination_phone' => Phone::mask($task->destination_phone),
            'destination_lat' => $task->destination_lat,
            'destination_lng' => $task->destination_lng,
            'starts_at' => $task->starts_at,
            'ends_at' => $task->ends_at,
            'status_at' => $task->status_at,
            'quantity' => $task->quantity,
            'is_final' => $category->isFinal(),
            'can_rate' => $category === StatusCategory::Completed && $task->rating === 0,
            'rating' => $task->rating ?: null,
            'created_at' => $task->created_at,
            'updated_at' => $task->updated_at,
            'status' => [
                'slug' => $task->status->slug,
                'category' => $category->value,
                'name' => $task->status->label(),
                'description' => $task->status->descriptionText(),
                'color' => $task->status->color,
            ],
            'courier' => $task->courier ? [
                'name' => $this->firstName($task->courier->name),
                'avatar' => $task->courier->avatar ? asset('storage/'.$task->courier->avatar) : null,
                'courier_location' => $location && $location->point() ? [
                    'is_online' => $location->is_online,
                    'lat' => $location->lat,
                    'lng' => $location->lng,
                    'last_seen_at' => $location->last_seen_at,
                ] : null,
            ] : null,
            'company' => [
                'name' => $company->name,
                'short_name' => $company->short_name,
                'brand_logo' => $company->brand_logo ? asset('storage/'.$company->brand_logo) : null,
                'primary_color' => $company->primary_color,
                'secondary_color' => $company->secondary_color,
            ],
            'status_logs' => $task->statusLogs->map(fn ($log) => [
                'status_slug' => $log->status->slug,
                'category' => $log->status_category_slug->value,
                'name' => $log->status->label(),
                'created_at' => $log->created_at,
            ])->values(),
        ];
    }

    private function maskName(?string $name): ?string
    {
        if ($name === null || $name === '') {
            return $name;
        }

        return collect(preg_split('/\s+/u', trim($name)))
            ->map(fn ($part) => mb_substr($part, 0, 1).str_repeat('*', max(1, mb_strlen($part) - 1)))
            ->implode(' ');
    }

    private function firstName(string $name): string
    {
        return explode(' ', trim($name))[0];
    }
}
