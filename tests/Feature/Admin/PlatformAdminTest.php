<?php

namespace Tests\Feature\Admin;

use App\Enums\Role;
use App\Models\Company;
use App\Models\Plan;
use App\Models\User;
use App\Support\Plans;
use Tests\TestCase;

class PlatformAdminTest extends TestCase
{
    private function superadmin(): User
    {
        $user = User::factory()->create();
        $user->forceFill(['is_super' => true])->save();

        return $user;
    }

    public function test_only_superadmins_open_the_admin_and_public_sign_up_is_gone(): void
    {
        $company = $this->makeCompany();
        $owner = $this->makeStaff($company);
        $admin = $this->superadmin();

        $this->actingAs($owner)->get('/admin')->assertForbidden();
        $this->get('/register')->assertNotFound();

        foreach (['/admin', '/admin/companies', '/admin/companies/create', "/admin/companies/{$company->id}", '/admin/users', '/admin/plans', '/admin/plans/create'] as $url) {
            $this->actingAs($admin)->get($url)->assertOk();
        }

        // A superadmin without a company of their own lands in the admin, not on a 403.
        $this->actingAs($admin)->get('/app')->assertRedirect(route('admin.dashboard'));
    }

    public function test_creating_a_company_makes_its_owner_and_shows_the_password_once(): void
    {
        $response = $this->actingAs($this->superadmin())->post('/admin/companies', [
            'name' => 'Ləzzət Market',
            'plan_slug' => 'start',
            'timezone' => 'Asia/Baku',
            'locale' => 'az',
            'trial_ends_at' => now()->addDays(14)->toDateString(),
            'owner_name' => 'Ülvi Rəhimov',
            'owner_email' => 'Ulvi@Example.test',
            'owner_phone' => '050 555 44 33',
        ]);

        $company = Company::query()->where('name', 'Ləzzət Market')->firstOrFail();
        $owner = User::query()->where('email', 'ulvi@example.test')->firstOrFail();

        $response->assertRedirect(route('admin.companies.show', $company));
        $credentials = session('credentials');
        $this->assertSame('ulvi@example.test', $credentials['email']);
        $this->assertTrue(password_verify($credentials['password'], $owner->password));
        $this->assertSame(Role::Owner, $owner->roleIn($company));
        $this->assertSame('+994505554433', $owner->phone);
        $this->assertTrue($company->onTrial());
        $this->assertSame('start', $company->plan_slug);
    }

    public function test_a_suspended_company_is_locked_out_until_resumed(): void
    {
        $company = $this->makeCompany();
        $owner = $this->makeStaff($company);
        $admin = $this->superadmin();

        $this->actingAs($admin)->post("/admin/companies/{$company->id}/suspend")->assertRedirect();
        $this->assertTrue($company->fresh()->isSuspended());
        $this->assertFalse($company->fresh()->hasActiveSubscription());
        $this->actingAs($owner)->get('/app')->assertForbidden();

        $this->actingAs($admin)->post("/admin/companies/{$company->id}/resume");
        $this->actingAs($owner)->get('/app')->assertOk();
    }

    public function test_sign_in_as_a_company_user_and_back(): void
    {
        $company = $this->makeCompany(['name' => 'Bakı Kargo']);
        $owner = $this->makeStaff($company, Role::Dispatcher);
        $admin = $this->superadmin();

        $this->actingAs($admin)->post("/admin/users/{$owner->id}/impersonate")->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($owner);
        $this->get('/app')->assertOk()->assertSee('Bakı Kargo')->assertSee(__('admin.users.leave'));
        $this->get('/admin')->assertForbidden();

        $this->post('/admin/leave')->assertRedirect(route('admin.companies.show', $company));
        $this->assertAuthenticatedAs($admin);

        // Another superadmin cannot be impersonated.
        $this->actingAs($admin)->post('/admin/users/'.$this->superadmin()->id.'/impersonate')->assertForbidden();
    }

    public function test_plans_are_edited_in_the_admin_and_apply_to_companies(): void
    {
        $this->assertFalse(Plans::allows('start', 'pod'));
        $this->assertTrue(Plans::allows('scale', 'pod'));
        $this->assertTrue(Plans::allows('enterprise', 'pod'));
        $this->assertFalse(Plans::allows('scale', 'white_label_tracking'));

        $admin = $this->superadmin();
        $start = Plan::query()->where('slug', 'start')->firstOrFail();

        $this->actingAs($admin)->put("/admin/plans/{$start->id}", [
            'name' => 'Start', 'price_monthly' => 29, 'history_days' => 30, 'max_couriers' => 1,
            'features' => ['api', 'pod'], 'sort' => 1, 'is_active' => '1',
        ])->assertRedirect(route('admin.plans.index'));

        $this->assertTrue(Plans::allows('start', 'pod'));
        $this->assertFalse(Plans::allows('start', 'webhooks'));
        $this->assertSame(30, Plans::get('start')['history_days']);

        // The courier limit is enforced when the company adds a courier.
        $company = $this->makeCompany(['plan_slug' => 'start']);
        $this->makeCourier($company);
        $this->actingAs($this->makeStaff($company))
            ->post('/app/couriers', ['name' => 'İkinci Kuryer', 'phone' => '050 111 22 33'])
            ->assertSessionHas('error', __('couriers.errors.plan_limit', ['limit' => 1]));

        // A plan in use cannot be deleted; an unused one can.
        $this->actingAs($admin)->delete("/admin/plans/{$start->id}")->assertSessionHas('error');
        $this->actingAs($admin)->post('/admin/plans', ['slug' => 'mini', 'name' => 'Mini', 'features' => ['api'], 'sort' => 9, 'is_active' => '1']);
        $mini = Plan::query()->where('slug', 'mini')->firstOrFail();
        $this->actingAs($admin)->delete("/admin/plans/{$mini->id}");
        $this->assertNull(Plan::query()->find($mini->id));
    }
}
