<?php

namespace Tests\Feature\Api;

use App\Enums\Channel;
use App\Models\AccountDeletionRequest;
use App\Models\Company;
use App\Models\CourierDevice;
use App\Models\CourierLocation;
use App\Models\CourierOtp;
use App\Models\SmsMessage;
use App\Models\Task;
use App\Models\User;
use App\Services\AssignmentService;
use App\Services\Push\PushDriver;
use App\Services\TaskService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class CourierMobileApiTest extends TestCase
{
    private const BASE = '/api/courier/v1';

    public function test_sign_in_with_sms_code_gives_a_courier_token(): void
    {
        $company = $this->makeCompany();
        $courier = $this->makeCourier($company, ['phone' => '+994501112233', 'name' => 'Ayxan Rüstəmov']);

        $code = $this->postJson(self::BASE.'/auth/otp', ['phone' => '050 111 22 33'])
            ->assertOk()->assertJsonPath('sent', true)->json('demo_code');

        $this->postJson(self::BASE.'/auth/verify', ['phone' => '0501112233', 'code' => $code === '000000' ? '111111' : '000000'])
            ->assertUnprocessable()->assertJsonValidationErrors('code');

        $login = $this->postJson(self::BASE.'/auth/verify', ['phone' => '0501112233', 'code' => $code, 'device_name' => 'Pixel 8'])
            ->assertOk()
            ->assertJsonPath('courier.id', $courier->id)
            ->assertJsonPath('courier.name', 'Ayxan Rüstəmov')
            ->json();

        $this->assertNotEmpty($login['token']);
        $this->assertTrue(Carbon::parse($login['expires_at'])->isAfter(now()->addDays(170)));

        $this->withToken($login['token'])->getJson(self::BASE.'/me')
            ->assertOk()->assertJsonPath('company.id', $company->id)->assertJsonPath('shift.active', false);
        $this->withToken($login['token'])->getJson(self::BASE.'/board')->assertOk()->assertJsonPath('courier.id', $courier->id);
    }

    public function test_unknown_numbers_get_the_same_answer_and_no_sms(): void
    {
        $this->postJson(self::BASE.'/auth/otp', ['phone' => '050 999 88 77'])->assertOk()->assertJsonPath('sent', true)->assertJsonMissingPath('demo_code');
        $this->assertSame(0, SmsMessage::query()->count());
    }

    public function test_tokens_do_not_cross_between_the_courier_app_and_the_integration_api(): void
    {
        $company = $this->makeCompany();
        $courier = $this->makeCourier($company);

        $this->withHeaders($this->apiHeaders($company))->getJson(self::BASE.'/me')->assertUnauthorized();
        $this->flushHeaders();

        $this->app['auth']->forgetGuards();
        $courierToken = $courier->createToken('phone', ['courier'])->plainTextToken;
        $this->withToken($courierToken)->getJson('/api/integration/tasks')->assertUnauthorized();

        // A user token without the courier ability (e.g. a future staff app) is refused too.
        $this->app['auth']->forgetGuards();
        $other = $courier->createToken('other', ['something-else'])->plainTextToken;
        $this->withToken($other)->getJson(self::BASE.'/me')->assertUnauthorized();
    }

    public function test_store_reviewer_number_signs_in_with_a_fixed_code_and_gets_no_sms(): void
    {
        $company = $this->makeCompany();
        $this->makeCourier($company, ['phone' => '+994500000001']);
        config(['courier_app.review' => ['phone' => '050 000 00 01', 'code' => '424242']]);

        $this->postJson(self::BASE.'/auth/otp', ['phone' => '0500000001'])->assertOk();
        $this->assertSame(0, SmsMessage::query()->count());
        $this->assertSame(0, CourierOtp::query()->count());

        $this->postJson(self::BASE.'/auth/verify', ['phone' => '0500000001', 'code' => '111111'])->assertUnprocessable();
        $this->postJson(self::BASE.'/auth/verify', ['phone' => '0500000001', 'code' => '424242'])->assertOk()->assertJsonStructure(['token']);
    }

    public function test_a_full_delivery_from_the_app_with_safe_retries(): void
    {
        $company = $this->makeCompany();
        $courier = $this->makeCourier($company);
        $task = $this->assigned($company, $courier);
        $this->tokenFor($courier);

        $this->postJson(self::BASE.'/shift/start', ['lat' => 40.40, 'lng' => 49.86])->assertOk()->assertJsonPath('shift.active', true);

        foreach (['pickup', 'depart', 'arrive'] as $action) {
            $this->postJson(self::BASE."/tasks/{$task->id}/step", ['action' => $action])->assertOk();
        }

        $id = (string) Str::uuid();
        $this->withHeader('X-Action-Id', $id)->postJson(self::BASE."/tasks/{$task->id}/complete", ['status' => 'delivered'])
            ->assertOk()->assertJsonPath('final', true);

        // The phone lost the answer and sends the same action again: acknowledged, not repeated.
        $this->withHeader('X-Action-Id', $id)->postJson(self::BASE."/tasks/{$task->id}/complete", ['status' => 'delivered'])
            ->assertOk()->assertJsonPath('replayed', true);

        $this->assertSame('completed', $task->fresh()->status_category_slug->value);
        $this->assertSame(1, $task->statusLogs()->where('status_category_slug', 'completed')->count());
    }

    public function test_proof_photo_uploads_and_shows_in_the_app(): void
    {
        Storage::fake('local');
        $company = $this->makeCompany();
        $courier = $this->makeCourier($company);
        $task = $this->assigned($company, $courier);
        $this->tokenFor($courier);

        $url = $this->post(self::BASE."/tasks/{$task->id}/pods", ['type' => 'photo', 'file' => UploadedFile::fake()->image('p.jpg', 800, 600)], ['Accept' => 'application/json'])
            ->assertOk()->json('pods.0.url');

        // The signed link works with the app's token, not without it.
        $this->get($url)->assertOk();
        $this->app['auth']->forgetGuards();
        $this->withoutToken()->get($url, ['Accept' => 'application/json'])->assertUnauthorized();
    }

    public function test_another_companys_task_is_invisible(): void
    {
        $company = $this->makeCompany();
        $courier = $this->makeCourier($company);
        $foreign = $this->assigned($other = $this->makeCompany(), $this->makeCourier($other));
        $this->tokenFor($courier);

        $this->getJson(self::BASE."/tasks/{$foreign->id}")->assertNotFound();
        $this->postJson(self::BASE."/tasks/{$foreign->id}/step", ['action' => 'pickup'])->assertNotFound();
    }

    public function test_location_batches_keep_history_and_never_move_the_courier_back(): void
    {
        $company = $this->makeCompany();
        $courier = $this->makeCourier($company);
        $this->tokenFor($courier);
        $now = now()->startOfMinute();

        $this->postJson(self::BASE.'/locations', ['locations' => [
            ['lat' => 40.4000, 'lng' => 49.8600, 'recorded_at' => $now->copy()->subSeconds(30)->toIso8601String(), 'battery' => 80],
            ['lat' => 40.4010, 'lng' => 49.8610, 'recorded_at' => $now->copy()->subSeconds(25)->toIso8601String()], // < 10 s: thinned
            ['lat' => 40.4020, 'lng' => 49.8620, 'recorded_at' => $now->copy()->subSeconds(10)->toIso8601String(), 'speed' => -1, 'heading' => -1],
            ['lat' => 40.4030, 'lng' => 49.8630, 'recorded_at' => $now->copy()->subDays(2)->toIso8601String()], // too old: dropped
        ]])->assertOk()->assertJsonPath('accepted', 4)->assertJsonPath('stored', 2);

        $location = CourierLocation::query()->find($courier->id);
        $this->assertSame(40.402, $location->lat);
        $this->assertNull($location->speed);
        $this->assertSame(80, $location->battery, 'battery carries over when the newest fix has none');

        // A late batch from when the phone was offline: stored as history, map position unchanged.
        $this->postJson(self::BASE.'/locations', ['locations' => [
            ['lat' => 40.3900, 'lng' => 49.8500, 'recorded_at' => $now->copy()->subMinutes(20)->toIso8601String(), 'is_mock' => true],
        ]])->assertOk()->assertJsonPath('stored', 1);

        $location->refresh();
        $this->assertSame(40.402, $location->lat);
        $this->assertFalse($location->is_mock);
        $this->assertSame(3, $courier->locationPings()->count());
        $this->assertTrue($courier->locationPings()->where('is_mock', true)->exists());

        $this->postJson(self::BASE.'/locations', ['locations' => []])->assertUnprocessable();
    }

    public function test_devices_receive_pushes_and_sign_out_removes_them(): void
    {
        $sent = [];
        $this->app->instance(PushDriver::class, new class($sent) implements PushDriver
        {
            public function __construct(private array &$sent) {}

            public function send(CourierDevice $device, string $title, string $body, array $data): bool
            {
                $this->sent[] = [$device->push_token, $title, $data['type']];

                return true;
            }
        });

        $company = $this->makeCompany();
        $courier = $this->makeCourier($company);
        $this->tokenFor($courier);

        $this->postJson(self::BASE.'/devices', ['platform' => 'android', 'push_token' => 'fcm-abc', 'app_version' => '1.0.0'])->assertOk();
        $this->postJson(self::BASE.'/devices', ['platform' => 'android', 'push_token' => 'fcm-abc', 'app_version' => '1.0.1'])->assertOk();
        $this->assertSame('1.0.1', CourierDevice::query()->sole()->app_version);

        // Dispatch assigns from the panel: the courier's phone hears about it.
        $this->assigned($company, $courier);
        $this->assertSame([['fcm-abc', __('courier.push.assigned_title', [], $courier->locale), 'task_assigned']], $sent);

        $this->postJson(self::BASE.'/auth/logout')->assertNoContent();
        $this->assertSame(0, CourierDevice::query()->count());

        $this->app['auth']->forgetGuards();
        $this->getJson(self::BASE.'/me')->assertUnauthorized();
    }

    public function test_account_deletion_request_and_app_config(): void
    {
        $company = $this->makeCompany();
        $courier = $this->makeCourier($company);

        $this->getJson(self::BASE.'/app-config')->assertOk()->assertJsonStructure(['min_version', 'latest_version', 'location' => ['on_task', 'idle'], 'locales']);

        $this->tokenFor($courier);
        $this->postJson(self::BASE.'/account/deletion-request', ['reason' => 'İşdən çıxıram'])->assertStatus(202);
        $this->postJson(self::BASE.'/account/deletion-request')->assertStatus(202);

        $this->assertSame(1, AccountDeletionRequest::query()->where('user_id', $courier->id)->count());
    }

    private function tokenFor(User $courier): void
    {
        $this->app['auth']->forgetGuards();
        $this->withToken($courier->createToken('test-phone', ['courier'])->plainTextToken);
    }

    private function assigned(Company $company, User $courier): Task
    {
        $hub = $this->makeHub($company);

        return $this->asTenant($company, function () use ($hub, $courier) {
            $task = app(TaskService::class)->create([
                'hub_id' => $hub->id,
                'integration_id' => 'ORD-'.Str::random(6),
                'starts_at' => now()->toIso8601String(),
                'ends_at' => now()->addHours(2)->toIso8601String(),
                'destination' => ['address' => 'Nizami küç. 10', 'lat' => 40.3777, 'lng' => 49.8436, 'phone' => '+994551234567'],
                'assignment' => ['type' => 'pool'],
            ], Channel::Api);

            return app(AssignmentService::class)->assign($task, $courier, Channel::Panel);
        });
    }
}
