<?php

namespace App\Services;

use App\Enums\Role;
use App\Exceptions\DomainException;
use App\Models\CourierDetail;
use App\Models\CourierLocation;
use App\Models\Employment;
use App\Models\Team;
use App\Models\User;
use App\Support\CurrentCompany;
use App\Support\Phone;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * A courier is a global User (identified by phone, signs in with OTP) employed by one or
 * more companies. Company-specific data lives in CourierDetail and team membership.
 */
class CourierService
{
    private const DETAIL_FIELDS = [
        'integration_id', 'code', 'vehicle_type_slug', 'vehicle_description', 'vehicle_license_plate',
        'vehicle_color', 'capacity', 'pool_capacity', 'notes', 'tags',
    ];

    public function __construct(private readonly CurrentCompany $tenant) {}

    public function create(array $data): User
    {
        $company = $this->tenant->getOrFail();
        $phone = $this->phone($data);

        return DB::transaction(function () use ($company, $data, $phone) {
            $user = User::withTrashed()->where('phone', $phone)->first();

            if ($user?->trashed()) {
                $user->restore();
            }

            $user ??= User::create([
                'name' => $data['name'],
                'phone' => $phone,
                'phone_country' => Phone::regionOf($phone),
                'email' => $data['email'] ?? null,
                'locale' => $data['locale'] ?? $company->locale,
                'active_company_id' => $company->id,
            ]);

            $existing = Employment::query()->where('user_id', $user->id)->first();

            if ($existing && $existing->role_slug !== Role::Courier->value) {
                throw new DomainException(__('couriers.errors.already_staff'), 'already_staff');
            }

            $limit = $company->plan()['max_couriers'];
            if ($limit !== null && ! $existing?->is_active && User::query()->couriers()->count() >= $limit) {
                throw new DomainException(__('couriers.errors.plan_limit', ['limit' => $limit]), 'plan_limit');
            }

            Employment::query()->updateOrCreate(
                ['user_id' => $user->id],
                ['role_slug' => Role::Courier->value, 'is_active' => true],
            );

            CourierDetail::query()->updateOrCreate(
                ['user_id' => $user->id],
                ['tags' => [], ...Arr::only($data, self::DETAIL_FIELDS)],
            );

            CourierLocation::query()->firstOrCreate(['user_id' => $user->id]);

            $this->syncTeams($user, $data['team_ids'] ?? null);

            return $user;
        });
    }

    public function update(User $courier, array $data): User
    {
        return DB::transaction(function () use ($courier, $data) {
            $userFields = Arr::only($data, ['name', 'email', 'locale']);

            if (array_key_exists('phone', $data)) {
                $phone = $this->phone($data, $courier->id);
                $userFields['phone'] = $phone;
                $userFields['phone_country'] = Phone::regionOf($phone);
            }

            $courier->fill($userFields)->save();

            $detail = CourierDetail::query()->firstOrNew(['user_id' => $courier->id]);
            $detail->fill(Arr::only($data, self::DETAIL_FIELDS));
            $detail->tags ??= [];
            $detail->save();

            if (array_key_exists('team_ids', $data)) {
                $this->syncTeams($courier, $data['team_ids']);
            }

            return $courier;
        });
    }

    /** Ends the employment; the global user and its history stay. */
    public function deactivate(User $courier): void
    {
        Employment::query()->where('user_id', $courier->id)->update(['is_active' => false]);
        $courier->tokens()->delete();
    }

    private function syncTeams(User $user, ?array $teamIds): void
    {
        if ($teamIds === null) {
            return;
        }

        // Only touch this company's teams: the pivot is shared with the courier's other employers.
        $companyTeamIds = Team::query()->pluck('id');
        $user->teams()->detach($companyTeamIds->diff($teamIds)->all());
        $user->teams()->syncWithoutDetaching($companyTeamIds->intersect($teamIds)->all());
    }

    private function phone(array $data, ?int $ignoreUserId = null): string
    {
        $phone = Phone::normalize($data['phone'] ?? null, $data['phone_country'] ?? $this->tenant->get()?->country);

        if ($phone === null) {
            throw ValidationException::withMessages(['phone' => __('validation.phone')]);
        }

        if ($ignoreUserId && User::query()->where('phone', $phone)->whereKeyNot($ignoreUserId)->exists()) {
            throw ValidationException::withMessages(['phone' => __('validation.unique', ['attribute' => 'phone'])]);
        }

        return $phone;
    }
}
