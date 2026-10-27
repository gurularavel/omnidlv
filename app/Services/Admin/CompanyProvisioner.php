<?php

namespace App\Services\Admin;

use App\Enums\Role;
use App\Exceptions\DomainException;
use App\Models\Company;
use App\Models\Employment;
use App\Models\User;
use App\Support\Phone;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/** Creates companies and their staff from the platform admin (there is no public sign-up). */
class CompanyProvisioner
{
    /**
     * @return array{0: Company, 1: User, 2: ?string} the company, its owner and the owner's
     *                                                generated password (null for an existing user)
     */
    public function create(array $company, array $owner): array
    {
        return DB::transaction(function () use ($company, $owner) {
            $model = Company::create([
                'name' => $company['name'],
                'short_name' => $company['short_name'] ?? Str::limit($company['name'], 32, ''),
                'slug' => Str::slug($company['name']).'-'.Str::lower(Str::random(6)),
                'timezone' => $company['timezone'] ?? 'Asia/Baku',
                'locale' => $company['locale'] ?? 'az',
                'plan_slug' => $company['plan_slug'],
                'trial_ends_at' => $company['trial_ends_at'] ?? null,
                'subscription_ends_at' => $company['subscription_ends_at'] ?? null,
                'admin_note' => $company['admin_note'] ?? null,
            ]);

            [$user, $password] = $this->addStaff($model, [...$owner, 'role' => Role::Owner->value]);

            return [$model, $user, $password];
        });
    }

    /**
     * Employs a person by email. A new user gets a generated password unless one is given.
     *
     * @return array{0: User, 1: ?string}
     */
    public function addStaff(Company $company, array $data): array
    {
        return DB::transaction(function () use ($company, $data) {
            $password = null;
            $user = User::withTrashed()->where('email', Str::lower($data['email']))->first();

            if ($user?->trashed()) {
                $user->restore();
            }

            if ($user === null) {
                $password = ($data['password'] ?? null) ?: Str::password(14, symbols: false);
                $phone = ! empty($data['phone']) ? Phone::normalize($data['phone'], 'AZ') : null;

                $user = User::create([
                    'name' => $data['name'],
                    'email' => Str::lower($data['email']),
                    'phone' => $phone,
                    'phone_country' => $phone ? Phone::regionOf($phone) : null,
                    'password' => $password,
                    'locale' => $company->locale,
                    'active_company_id' => $company->id,
                ]);

                // A password the admin typed is known to them; only a generated one is shown back.
                $password = ($data['password'] ?? null) ? null : $password;
            }

            $employment = Employment::withoutGlobalScopes()->where('company_id', $company->id)->where('user_id', $user->id)->first();

            if ($employment?->role_slug === Role::Courier->value) {
                throw new DomainException(__('admin.errors.is_courier'), 'is_courier');
            }

            Employment::withoutGlobalScopes()->updateOrCreate(
                ['company_id' => $company->id, 'user_id' => $user->id],
                ['role_slug' => $data['role'], 'is_active' => true],
            );

            return [$user, $password];
        });
    }
}
