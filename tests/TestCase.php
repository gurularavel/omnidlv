<?php

namespace Tests;

use App\Enums\Role;
use App\Models\Company;
use App\Models\Employment;
use App\Models\Hub;
use App\Models\User;
use App\Services\CourierService;
use App\Support\CurrentCompany;
use Database\Seeders\StatusSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    protected string $seeder = StatusSeeder::class;

    protected function makeCompany(array $attributes = []): Company
    {
        return Company::factory()->create($attributes);
    }

    protected function makeStaff(Company $company, Role $role = Role::Owner): User
    {
        $user = User::factory()->create(['active_company_id' => $company->id]);
        Employment::withoutGlobalScopes()->create([
            'company_id' => $company->id, 'user_id' => $user->id, 'role_slug' => $role->value,
        ]);

        return $user;
    }

    protected function makeCourier(Company $company, array $data = []): User
    {
        return app(CurrentCompany::class)->run($company, fn () => app(CourierService::class)->create([
            'name' => fake()->name(),
            'phone' => '+99450'.fake()->unique()->numerify('#######'),
            ...$data,
        ]));
    }

    protected function makeHub(Company $company, array $attributes = []): Hub
    {
        return Hub::factory()->create(['company_id' => $company->id, ...$attributes]);
    }

    /** Authorization headers for the integration API, using a company-owned key. */
    protected function apiHeaders(Company $company, bool $bearer = true): array
    {
        // Guards cache the resolved user for the life of the app instance, which in tests spans
        // several requests; forget it so the next request authenticates with this key.
        $this->app['auth']->forgetGuards();

        $token = $company->createToken('test')->plainTextToken;

        return ['Authorization' => ($bearer ? 'Bearer ' : '').$token, 'Accept' => 'application/json'];
    }

    /** Runs model calls inside a tenant, as request middleware would. */
    protected function asTenant(Company $company, callable $callback): mixed
    {
        return app(CurrentCompany::class)->run($company, $callback);
    }
}
