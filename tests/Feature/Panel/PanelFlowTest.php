<?php

namespace Tests\Feature\Panel;

use App\Enums\Role;
use App\Models\Company;
use App\Models\Status;
use App\Models\Task;
use App\Models\User;
use App\Support\CurrentCompany;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class PanelFlowTest extends TestCase
{
    public function test_registration_creates_a_company_on_trial_and_signs_in(): void
    {
        $this->post('/register', [
            'company' => 'Ləzzət Market',
            'name' => 'Ülvi Rəhimov',
            'email' => 'ulvi@example.test',
            'phone' => '050 555 44 33',
            'password' => 'secret-pass-1',
            'password_confirmation' => 'secret-pass-1',
            'terms' => '1',
        ])->assertRedirect(route('dashboard'));

        $user = User::query()->where('email', 'ulvi@example.test')->firstOrFail();
        $company = Company::query()->findOrFail($user->active_company_id);

        $this->assertAuthenticatedAs($user);
        $this->assertSame('+994505554433', $user->phone);
        $this->assertTrue($company->onTrial());
        $this->assertSame(Role::Owner, $user->roleIn($company));
        $this->get('/app')->assertOk()->assertSee('Ləzzət Market');
    }

    public function test_pages_render_for_staff_and_couriers_are_kept_out(): void
    {
        $company = $this->makeCompany();
        $owner = $this->makeStaff($company);
        $hub = $this->makeHub($company);
        $task = $this->panelTask($owner, $hub->id);

        foreach (['/app', '/app/live', '/app/tasks', '/app/tasks/create', "/app/tasks/{$task->id}", "/app/tasks/{$task->id}/edit",
            '/app/hubs', '/app/hubs/create', '/app/teams', '/app/teams/create', '/app/couriers', '/app/couriers/create',
            '/app/integration', '/app/settings'] as $url) {
            $this->actingAs($owner)->get($url)->assertOk();
        }

        $dispatcher = $this->makeStaff($company, Role::Dispatcher);
        $this->actingAs($dispatcher)->get('/app/tasks')->assertOk();
        $this->actingAs($dispatcher)->get('/app/settings')->assertForbidden();
        $this->actingAs($dispatcher)->get('/app/integration')->assertForbidden();

        $courier = $this->makeCourier($company);
        $this->actingAs($courier)->get('/app')->assertForbidden();
    }

    public function test_panel_form_times_are_company_local(): void
    {
        $company = $this->makeCompany(['timezone' => 'Asia/Baku']);
        $owner = $this->makeStaff($company);
        $hub = $this->makeHub($company);

        $task = $this->panelTask($owner, $hub->id, ['starts_at' => '2026-11-02T10:00', 'ends_at' => '2026-11-02T12:30']);

        // Baku is UTC+4.
        $this->assertSame('2026-11-02 06:00:00', $task->starts_at->utc()->format('Y-m-d H:i:s'));
        $this->assertSame('2026-11-02 08:30:00', $task->ends_at->utc()->format('Y-m-d H:i:s'));
        $this->assertSame(['express', 'fragile'], $task->tags);
        $this->assertSame('panel', $task->channel->value);
        $this->assertSame(1, $task->items()->count());
    }

    public function test_assign_change_status_and_lock_from_the_panel(): void
    {
        $company = $this->makeCompany();
        $owner = $this->makeStaff($company);
        $hub = $this->makeHub($company);
        $courier = $this->makeCourier($company);
        $task = $this->panelTask($owner, $hub->id);

        $this->actingAs($owner)->post("/app/tasks/{$task->id}/assign", ['courier_id' => $courier->id])->assertSessionHasNoErrors();
        $this->assertSame($courier->id, $task->fresh()->courier_id);
        $this->assertSame('assigned', $task->fresh()->status_category_slug->value);

        $onTheWay = Status::query()->where('slug', 'on_the_way')->value('id');
        $this->actingAs($owner)->post("/app/tasks/{$task->id}/status", ['status_id' => $onTheWay, 'notes' => 'Yola çıxdı']);
        $this->assertSame('on_delivery', $task->fresh()->status_category_slug->value);
        $this->assertSame('Yola çıxdı', $task->statusLogs()->reorder('id', 'desc')->value('notes'));

        $this->actingAs($owner)->post("/app/tasks/{$task->id}/lock");
        $this->assertTrue($task->fresh()->is_locked);

        // Unassigning returns the task to "created" and frees the courier.
        $this->actingAs($owner)->post("/app/tasks/{$task->id}/assign", ['courier_id' => null]);
        $this->assertNull($task->fresh()->courier_id);
    }

    public function test_recipient_can_rate_once_and_the_courier_average_updates(): void
    {
        $company = $this->makeCompany(['plan_slug' => 'scale']);
        $owner = $this->makeStaff($company);
        $hub = $this->makeHub($company);
        $courier = $this->makeCourier($company);
        $task = $this->panelTask($owner, $hub->id);

        $this->actingAs($owner)->post("/app/tasks/{$task->id}/assign", ['courier_id' => $courier->id]);
        foreach (['picked_up', 'on_the_way', 'delivered'] as $slug) {
            $this->actingAs($owner)->post("/app/tasks/{$task->id}/status", ['status_id' => Status::query()->where('slug', $slug)->value('id')]);
        }

        auth()->logout();
        $code = $task->fresh()->tracking_code;

        $this->get("/t/{$code}")->assertOk()->assertSee(__('How was the delivery?'));
        $this->post("/t/{$code}/rate", ['rating' => 4, 'comment' => 'Vaxtında gəldi'])->assertRedirect("/t/{$code}");
        $this->post("/t/{$code}/rate", ['rating' => 1])->assertStatus(422);

        $this->assertSame(4, $task->fresh()->rating);
        $detail = app(CurrentCompany::class)->run($company, fn () => $courier->courierDetail()->first());
        $this->assertSame(4.0, $detail->rating);
        $this->assertSame(1, $detail->scored_tasks_count);
    }

    private function panelTask(User $owner, int $hubId, array $overrides = []): Task
    {
        $this->actingAs($owner)->post('/app/tasks', array_replace_recursive([
            'hub_id' => $hubId,
            'action_slug' => 'drop_off',
            'starts_at' => Carbon::now('Asia/Baku')->format('Y-m-d\TH:i'),
            'ends_at' => Carbon::now('Asia/Baku')->addHours(2)->format('Y-m-d\TH:i'),
            'tags' => 'express, fragile',
            'destination' => ['name' => 'Aysel', 'phone' => '0501234567', 'address' => 'Nizami küç. 10', 'lat' => '40.3777', 'lng' => '49.8436', 'allow_unresolved' => '0', 'phone_country' => 'AZ'],
            'requirements' => ['delivery_code' => '1', 'pod_photo' => '0'],
            'assignment' => ['type' => 'pool'],
            'assignment_options' => ['team_id_by_polygon' => '0'],
            'items' => [['integration_id' => 'SKU-1', 'name' => 'Ayaqqabı', 'quantity' => 1, 'amount' => '49.90'], ['integration_id' => '', 'name' => '']],
        ], $overrides))->assertSessionHasNoErrors()->assertRedirect();

        return Task::withoutGlobalScopes()->latest('id')->firstOrFail();
    }
}
