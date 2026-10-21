<?php

namespace App\Services;

use App\Enums\StatusCategory;
use App\Models\CourierDetail;
use App\Models\Task;
use App\Support\Phone;
use Illuminate\Support\Facades\DB;

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
            ->where('tracking_code', self::normalizeCode($code))
            ->whereNull('deleted_at')
            ->first();
    }

    /**
     * Recipients who lost the code can find an order by the shop's order number, proven by the
     * last four digits of the phone it was sent to. Order numbers are not secret, so the phone
     * check is what keeps strangers out; the route is throttled against guessing.
     */
    public function findByOrder(string $orderNumber, string $phoneLast4): ?Task
    {
        $digits = preg_replace('/\D/', '', $phoneLast4);

        if (strlen($digits) !== 4 || trim($orderNumber) === '') {
            return null;
        }

        $task = Task::withoutGlobalScopes()
            ->where('integration_id', trim($orderNumber))
            ->where('destination_phone', 'like', '%'.$digits)
            ->whereNull('deleted_at')
            ->latest('id')
            ->first();

        return $task ? $this->find($task->tracking_code) : null;
    }

    /** People type codes with spaces, dashes and lower case; codes are only A–Z and 2–9. */
    public static function normalizeCode(string $code): string
    {
        return strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $code));
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
            // Planned arrival from the route plan; only shown while the task is still open.
            'eta_at' => $task->isFinal() ? null : $task->eta_at,
            'status_at' => $task->status_at,
            'quantity' => $task->quantity,
            'is_final' => $category->isFinal(),
            'can_rate' => $category === StatusCategory::Completed && $task->rating === 0 && $company->hasFeature('customer_feedback'),
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

    /**
     * One rating per completed task. The courier's average is recomputed from their rated
     * tasks in this company, so it can never drift from the source rows.
     */
    public function rate(Task $task, int $rating, ?string $comment): void
    {
        if ($task->status_category_slug !== StatusCategory::Completed || $task->rating > 0 || ! $task->company->hasFeature('customer_feedback')) {
            abort(422, __('tracking.cannot_rate'));
        }

        DB::transaction(function () use ($task, $rating, $comment) {
            Task::withoutGlobalScopes()->whereKey($task->id)->update(['rating' => $rating, 'comment' => $comment]);

            if ($task->courier_id) {
                $stats = Task::withoutGlobalScopes()
                    ->where('company_id', $task->company_id)
                    ->where('courier_id', $task->courier_id)
                    ->where('rating', '>', 0)
                    ->selectRaw('count(*) as n, avg(rating) as avg')
                    ->first();

                CourierDetail::withoutGlobalScopes()
                    ->where('company_id', $task->company_id)
                    ->where('user_id', $task->courier_id)
                    ->update(['rating' => round((float) $stats->avg, 1), 'scored_tasks_count' => $stats->n]);
            }
        });
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
