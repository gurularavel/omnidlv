<?php

namespace App\Services;

use App\Exceptions\DomainException;
use App\Models\Hub;
use App\Models\Task;
use App\Services\Geocoding\Geocoder;
use App\Support\CurrentCompany;
use App\Support\GeoPoint;
use Illuminate\Validation\ValidationException;

class HubService
{
    public function __construct(
        private readonly Geocoder $geocoder,
        private readonly CurrentCompany $tenant,
    ) {}

    public function save(Hub $hub, array $data): Hub
    {
        $hub->fill(collect($data)->only([
            'name', 'integration_id', 'address', 'address_building', 'address_apartment', 'address_floor', 'tags',
        ])->all());

        $point = GeoPoint::tryFrom($data['lat'] ?? null, $data['lng'] ?? null);

        if ($point === null && ($hub->isDirty('address') || ! $hub->exists)) {
            $point = $this->geocoder->geocode($hub->address, $this->tenant->get()?->country);

            if ($point === null) {
                throw ValidationException::withMessages(['address' => __('tasks.errors.address_unresolved')]);
            }
        }

        if ($point !== null) {
            $hub->lat = $point->lat;
            $hub->lng = $point->lng;
        }

        $hub->tags ??= [];
        $hub->save();

        return $hub;
    }

    public function delete(Hub $hub): void
    {
        if (Task::query()->where('hub_id', $hub->id)->open()->exists()) {
            throw new DomainException(__('hubs.errors.has_open_tasks'), 'hub_has_open_tasks');
        }

        $hub->delete();
    }
}
