<?php

namespace App\Services;

use App\Models\Team;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class TeamService
{
    public function save(Team $team, array $data): Team
    {
        return DB::transaction(function () use ($team, $data) {
            $team->fill(Arr::only($data, ['name', 'hub_id', 'integration_id', 'color', 'is_default', 'polygon', 'tags']));
            $team->tags ??= [];
            // New teams take the next line ink, so each team reads as its own line on the map.
            $team->color ??= \App\Support\Ui::ink(Team::query()->count());

            if ($team->polygon !== null) {
                $team->polygon = array_map(fn ($p) => [round((float) $p[0], 6), round((float) $p[1], 6)], $team->polygon);
            }

            if ($team->is_default) {
                Team::query()->whereKeyNot($team->id ?? 0)->update(['is_default' => false]);
            }

            $team->save();

            if (array_key_exists('courier_ids', $data)) {
                $team->couriers()->sync($data['courier_ids'] ?? []);
            }

            return $team;
        });
    }
}
