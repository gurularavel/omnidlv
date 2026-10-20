<?php

namespace Tests\Feature\Courier;

use App\Enums\Channel;
use App\Models\Company;
use App\Models\CourierLocation;
use App\Models\SmsMessage;
use App\Models\Task;
use App\Models\User;
use App\Services\TaskService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class CourierAppTest extends TestCase
{
    public function test_sms_code_sign_in_in_demo_mode(): void
    {
        $company = $this->makeCompany();
        $courier = $this->makeCourier($company, ['phone' => '050 111 22 33']);

        $this->post('/courier/login', ['phone' => '050 111 22 33'])->assertRedirect('/courier/login/code');
        $code = session('demo_code');

        $this->assertMatchesRegularExpression('/^\d{6}$/', $code);
        $sms = SmsMessage::query()->where('to', '+994501112233')->where('purpose', 'courier_otp')->where('driver', 'demo')->sole();
        $this->assertStringNotContainsString($code, $sms->body, 'sign-in codes must not be stored readable');

        $this->post('/courier/login/code', ['phone' => '0501112233', 'code' => $code === '000000' ? '111111' : '000000'])
            ->assertSessionHasErrors('code');
        $this->post('/courier/login/code', ['phone' => '0501112233', 'code' => $code])->assertRedirect('/courier');

        $this->assertAuthenticatedAs($courier);
        $this->get('/courier')->assertOk();

        // The code is single-use.
        auth()->logout();
        $this->post('/courier/login/code', ['phone' => '0501112233', 'code' => $code])->assertSessionHasErrors('code');
    }

    public function test_unknown_numbers_get_no_sms_and_no_hint(): void
    {
        $this->post('/courier/login', ['phone' => '050 999 88 77'])->assertRedirect('/courier/login/code');

        $this->assertNull(session('demo_code'));
        $this->assertSame(0, SmsMessage::query()->count());
    }

    public function test_staff_cannot_open_the_courier_app_and_couriers_cannot_open_the_panel(): void
    {
        $company = $this->makeCompany();

        $this->actingAs($this->makeStaff($company))->get('/courier')->assertForbidden();
        $this->actingAs($this->makeCourier($company))->get('/app')->assertForbidden();
    }

    public function test_shift_location_and_end_shift_rules(): void
    {
        $company = $this->makeCompany();
        $courier = $this->makeCourier($company);

        $this->actingAs($courier)->postJson('/courier/shift/start', ['lat' => 40.40, 'lng' => 49.86])
            ->assertOk()->assertJsonPath('shift.active', true);

        $this->actingAs($courier)->postJson('/courier/location', ['lat' => 40.41, 'lng' => 49.87, 'battery' => 80])->assertNoContent();

        $location = CourierLocation::query()->find($courier->id);
        $this->assertTrue($location->is_working);
        $this->assertSame(40.41, $location->lat);
        $this->assertSame(1, $courier->locationPings()->count());

        // A second ping within 10 s updates the position but not the history.
        $this->actingAs($courier)->postJson('/courier/location', ['lat' => 40.42, 'lng' => 49.88])->assertNoContent();
        $this->assertSame(1, $courier->locationPings()->count());
        $this->assertSame(40.42, $location->fresh()->lat);

        $task = $this->assignedTask($company, $courier);
        $this->actingAs($courier)->postJson('/courier/shift/end')->assertStatus(422)->assertJsonPath('code', 'shift_has_tasks');

        $this->actingAs($courier)->postJson("/courier/tasks/{$task->id}/fail", ['status' => 'refused', 'notes' => 'İmtina etdi'])->assertOk();
        $this->actingAs($courier)->postJson('/courier/shift/end')->assertOk()->assertJsonPath('shift.active', false);
    }

    public function test_full_delivery_with_mandatory_photo_and_sms_code(): void
    {
        Storage::fake('local');
        $company = $this->makeCompany();
        $courier = $this->makeCourier($company);
        $task = $this->assignedTask($company, $courier, [
            'requirements' => ['delivery_code_mandatory' => true, 'pod_photo_mandatory' => true],
            'items' => [['integration_id' => 'SKU-1', 'name' => 'Telefon', 'barcode' => '4760001112223']],
        ]);
        $as = $this->actingAs($courier);

        $as->postJson("/courier/tasks/{$task->id}/step", ['action' => 'pickup'])->assertOk()->assertJsonPath('next', 'depart');
        $as->postJson("/courier/tasks/{$task->id}/scan", ['code' => '4760001112223'])->assertOk()->assertJsonPath('matched', true)->assertJsonPath('task.items.0.scanned', true);
        $as->postJson("/courier/tasks/{$task->id}/scan", ['code' => 'nope'])->assertNotFound();

        // Going out for delivery texts the recipient the code, once.
        $as->postJson("/courier/tasks/{$task->id}/step", ['action' => 'depart'])->assertOk()->assertJsonPath('next', 'arrive');
        $sms = SmsMessage::query()->where('purpose', 'delivery_code')->sole();
        $code = Task::withoutGlobalScopes()->find($task->id)->delivery_code;
        $this->assertStringContainsString($code, $sms->body);
        $this->assertSame($task->destination_phone, $sms->to);

        $as->postJson("/courier/tasks/{$task->id}/step", ['action' => 'arrive'])->assertOk()->assertJsonPath('next', 'complete');

        $as->postJson("/courier/tasks/{$task->id}/complete", ['status' => 'delivered'])
            ->assertStatus(422)->assertJsonPath('code', 'photo_required');

        $as->post("/courier/tasks/{$task->id}/pod", ['type' => 'photo', 'file' => UploadedFile::fake()->image('door.jpg')], ['Accept' => 'application/json'])
            ->assertOk()->assertJsonPath('has_photo', true);

        $as->postJson("/courier/tasks/{$task->id}/complete", ['status' => 'delivered'])
            ->assertStatus(422)->assertJsonPath('code', 'code_required');

        $as->postJson("/courier/tasks/{$task->id}/code", ['code' => '0000'.($code === '0000' ? '1' : '')])->assertStatus(422)->assertJsonPath('verified', false);
        $as->postJson("/courier/tasks/{$task->id}/code", ['code' => $code])->assertOk()->assertJsonPath('verified', true);

        $as->postJson("/courier/tasks/{$task->id}/complete", ['status' => 'delivered', 'received_by' => 'Qonşu'])
            ->assertOk()->assertJsonPath('category', 'completed')->assertJsonPath('final', true);

        $this->assertSame(1, SmsMessage::query()->where('purpose', 'delivery_code')->count());
        Storage::disk('local')->assertExists(Task::withoutGlobalScopes()->find($task->id)->pods()->first()->path);
    }

    public function test_offline_replay_is_idempotent(): void
    {
        $company = $this->makeCompany();
        $courier = $this->makeCourier($company);
        $task = $this->assignedTask($company, $courier);
        $id = (string) Str::uuid();

        $this->actingAs($courier)->postJson("/courier/tasks/{$task->id}/step", ['action' => 'pickup'], ['X-Action-Id' => $id])->assertOk();
        $this->actingAs($courier)->postJson("/courier/tasks/{$task->id}/step", ['action' => 'pickup'], ['X-Action-Id' => $id])
            ->assertOk()->assertJsonPath('replayed', true);

        $this->assertSame(1, $task->statusLogs()->where('status_category_slug', 'in_progress')->count());
    }

    public function test_couriers_only_touch_their_own_tasks_and_claim_from_their_teams(): void
    {
        $company = $this->makeCompany();
        [$me, $other] = [$this->makeCourier($company), $this->makeCourier($company)];
        $theirs = $this->assignedTask($company, $other);

        $this->actingAs($me)->get("/courier/tasks/{$theirs->id}")->assertNotFound();
        $this->actingAs($me)->postJson("/courier/tasks/{$theirs->id}/step", ['action' => 'pickup'])
            ->assertStatus(422)->assertJsonPath('code', 'not_your_task');

        $pooled = $this->task($company, ['assignment' => ['type' => 'pool']]);
        $this->actingAs($me)->getJson('/courier/data')->assertJsonPath('pool.0.id', $pooled->id);
        $this->actingAs($me)->postJson("/courier/tasks/{$pooled->id}/claim")->assertOk()->assertJsonPath('category', 'assigned');
        $this->actingAs($other)->postJson("/courier/tasks/{$pooled->id}/claim")->assertStatus(409);

        // A locked task offers no next step and refuses courier moves.
        $locked = $this->assignedTask($company, $me, ['locked' => true]);
        $this->actingAs($me)->getJson("/courier/tasks/{$locked->id}/data")->assertJsonPath('next', null);
        $this->actingAs($me)->postJson("/courier/tasks/{$locked->id}/step", ['action' => 'pickup'])
            ->assertStatus(422)->assertJsonPath('code', 'task_locked');
    }

    private function assignedTask(Company $company, User $courier, array $overrides = []): Task
    {
        return $this->task($company, ['assignment' => ['type' => 'selected_courier', 'courier_id' => $courier->id], ...$overrides]);
    }

    private function task(Company $company, array $overrides = []): Task
    {
        $hub = $this->makeHub($company);

        return $this->asTenant($company, fn () => app(TaskService::class)->create(array_replace_recursive([
            'hub_id' => $hub->id,
            'integration_id' => 'ORD-'.Str::random(6),
            'starts_at' => now()->toIso8601String(),
            'ends_at' => now()->addHours(2)->toIso8601String(),
            'destination' => ['address' => 'Nizami küç. 10', 'lat' => 40.3777, 'lng' => 49.8436, 'phone' => '+994551234567', 'name' => 'Aysel'],
        ], $overrides), Channel::Api));
    }
}
