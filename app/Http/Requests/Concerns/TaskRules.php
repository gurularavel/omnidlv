<?php

namespace App\Http\Requests\Concerns;

use App\Enums\AssignmentType;
use App\Enums\TaskType;
use App\Rules\PhoneFormat;
use Illuminate\Validation\Rule;

/**
 * Validation for the QDelivery-compatible task payload. Used by the integration API,
 * the panel form and the spreadsheet importer, so all three accept exactly the same shape.
 */
trait TaskRules
{
    use TenantRules;

    protected function taskRules(bool $update = false): array
    {
        $required = $update ? 'sometimes' : 'required';

        return [
            'hub_id' => [$required, 'integer', $this->existsInCompany('hubs')],
            'integration_id' => ['nullable', 'string', 'max:191'],
            'tracking_code' => ['nullable', 'string', 'min:6', 'max:32', 'regex:/^[A-Za-z0-9\-_]+$/'],
            'action_slug' => [$update ? 'sometimes' : 'nullable', Rule::enum(TaskType::class)],
            'starts_at' => [$required, 'date'],
            'ends_at' => [$required, 'date'],
            'slot' => ['nullable', 'string', 'regex:/^\d{16}$/'],
            'slot_starts_at' => ['nullable', 'date'],
            'slot_ends_at' => ['nullable', 'date', 'after_or_equal:slot_starts_at'],
            'slot_auto_closing_time' => ['nullable', 'integer', 'min:0', 'max:1440'],
            'quantity' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'unit_quantity' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'service_time' => ['nullable', 'integer', 'min:0', 'max:1440'],
            'response_time' => ['nullable', 'integer', 'min:0', 'max:1440'],
            'notes' => ['nullable', 'string', 'max:10000'],
            'recipient_notes' => ['nullable', 'string', 'max:10000'],
            'priority' => ['nullable', 'integer', 'min:0', 'max:100'],
            'tags' => ['nullable', 'array', 'max:50'],
            'tags.*' => ['string', 'max:64'],
            'locked' => ['nullable', 'boolean'],
            'confirmed' => ['nullable', 'boolean'],
            'payment_type' => ['nullable', Rule::in(['prepaid', 'cash', 'card'])],
            'amount' => ['nullable', 'numeric', 'min:0', 'max:9999999999'],
            'currency' => ['nullable', 'string', 'size:3'],

            ...$this->sideRules('origin'),
            ...$this->sideRules('destination'),

            'requirements' => ['nullable', 'array'],
            'requirements.custom_delivery_code' => ['nullable', 'string', 'regex:/^\d{4,8}$/'],
            'requirements.delivery_code' => ['nullable', 'boolean'],
            'requirements.delivery_code_mandatory' => ['nullable', 'boolean'],
            'requirements.pod_photo' => ['nullable', 'boolean'],
            'requirements.pod_photo_mandatory' => ['nullable', 'boolean'],
            'requirements.dont_ring_the_bell' => ['nullable', 'boolean'],
            'requirements.contactless' => ['nullable', 'boolean'],

            'assignment' => ['nullable', 'array'],
            'assignment.type' => ['required_with:assignment', Rule::enum(AssignmentType::class)],
            'assignment.courier_id' => [
                'required_if:assignment.type,'.AssignmentType::SelectedCourier->value,
                'nullable', 'integer', $this->existsCourier(),
            ],

            'assignment_options' => ['nullable', 'array'],
            'assignment_options.team_id' => ['nullable', 'integer', $this->existsInCompany('teams')],
            'assignment_options.courier_online_status' => ['nullable', Rule::in(['all', 'online', 'offline'])],
            'assignment_options.obligation' => ['nullable', Rule::in(['mandatory', 'optional'])],
            'assignment_options.courier_duty_status' => ['nullable', Rule::in(['all', 'on_duty', 'idle'])],
            'assignment_options.distribution' => ['nullable', Rule::in(['balanced', 'adaptive'])],
            'assignment_options.min_capacity' => ['required_if:assignment_options.distribution,adaptive', 'nullable', 'integer', 'min:1'],
            'assignment_options.courier_shift_status' => ['nullable', Rule::in(['all', 'working', 'resting'])],
            'assignment_options.team_id_by_polygon' => ['nullable', 'boolean'],

            'slot_options' => ['nullable', 'array'],
            'slot_options.pool' => ['nullable', 'boolean'],
            'slot_options.tour' => ['nullable', 'boolean'],
            'slot_options.dispatch_early' => ['nullable', 'boolean'],
            'slot_options.from_hub' => ['nullable', 'boolean'],
            'slot_options.accelerate' => ['nullable', 'boolean'],
            'slot_options.unassigned_strategy' => ['nullable', Rule::in(['nearest_plan', 'pool', 'keep'])],

            'items' => ['nullable', 'array', 'max:500'],
            'items.*.integration_id' => ['required', 'string', 'max:191', 'distinct'],
            'items.*.name' => ['required', 'string', 'max:255'],
            'items.*.description' => ['nullable', 'string', 'max:2000'],
            'items.*.notes' => ['nullable', 'string', 'max:2000'],
            'items.*.quantity' => ['nullable', 'integer', 'min:0'],
            'items.*.currency' => ['nullable', 'string', 'size:3'],
            'items.*.amount' => ['nullable', 'numeric', 'min:0'],
            'items.*.barcode' => ['nullable', 'string', 'max:128'],
        ];
    }

    private function sideRules(string $side): array
    {
        // A side, when sent, is either a registered hub or a free address. The phone is required
        // for a free-address destination because the recipient gets SMS updates.
        $isFreeAddress = fn () => is_array($this->input($side)) && empty($this->input("{$side}.hub_id"));
        $phoneRule = $side === 'destination' ? Rule::requiredIf($isFreeAddress) : 'nullable';

        return [
            $side => ['nullable', 'array'],
            "{$side}.hub_id" => ['nullable', 'integer', $this->existsInCompany('hubs')],
            "{$side}.address" => [Rule::requiredIf($isFreeAddress), 'nullable', 'string', 'max:500'],
            "{$side}.address_building" => ['nullable', 'string', 'max:191'],
            "{$side}.address_floor" => ['nullable', 'string', 'max:191'],
            "{$side}.address_apartment" => ['nullable', 'string', 'max:191'],
            "{$side}.address_description" => ['nullable', 'string', 'max:500'],
            "{$side}.lat" => ['nullable', 'numeric', 'between:-90,90'],
            "{$side}.lng" => ['nullable', 'numeric', 'between:-180,180'],
            "{$side}.name" => ['nullable', 'string', 'max:191'],
            "{$side}.phone_country" => ['nullable', 'string', 'size:2'],
            "{$side}.phone" => [$phoneRule, 'nullable', 'string', 'max:32', new PhoneFormat],
            "{$side}.allow_unresolved" => ['nullable', 'boolean'],
        ];
    }

    /**
     * QDelivery sends hub_id as string or integer; also map our panel's flat form keys.
     */
    protected function normalizeTaskInput(): void
    {
        if (is_string($this->input('hub_id')) && ctype_digit($this->input('hub_id'))) {
            $this->merge(['hub_id' => (int) $this->input('hub_id')]);
        }

        // The panel posts tags as one comma-separated field.
        if (is_string($this->input('tags'))) {
            $this->merge(['tags' => array_values(array_filter(array_map('trim', explode(',', $this->input('tags')))))]);
        }

        // Empty item rows from the panel's repeater are not items.
        if (is_array($this->input('items'))) {
            $this->merge(['items' => array_values(array_filter($this->input('items'), fn ($i) => filled($i['name'] ?? null) || filled($i['integration_id'] ?? null)))]);
        }
    }
}
