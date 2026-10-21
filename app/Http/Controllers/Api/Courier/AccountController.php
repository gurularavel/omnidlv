<?php

namespace App\Http\Controllers\Api\Courier;

use App\Http\Controllers\Controller;
use App\Models\AccountDeletionRequest;
use App\Models\CourierDevice;
use App\Models\CourierShift;
use App\Models\User;
use App\Support\CurrentCompany;
use App\Support\Phone;
use App\Support\Ui;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AccountController extends Controller
{
    /** Settings the app reads at start, before and after sign-in. */
    public function config()
    {
        return response()->json([
            'min_version' => config('courier_app.min_version'),
            'latest_version' => config('courier_app.latest_version'),
            'store_urls' => config('courier_app.store_urls'),
            'location' => config('courier_app.location'),
            'privacy_url' => config('courier_app.privacy_url') ?? url('/privacy'),
            'support_phone' => config('courier_app.support_phone'),
            'locales' => ['az', 'en', 'ru', 'tr'],
        ]);
    }

    public function me(Request $request, CurrentCompany $tenant)
    {
        $user = $request->user();
        $shift = CourierShift::query()->where('user_id', $user->id)->whereNull('ended_at')->latest('id')->first();

        return response()->json([
            'courier' => self::courier($user),
            'company' => [
                'id' => $tenant->id(),
                'name' => $tenant->get()->name,
                'timezone' => $tenant->get()->timezone,
                'primary_color' => $tenant->get()->primary_color,
            ],
            'shift' => [
                'active' => $shift !== null,
                'started_at' => $shift?->started_at?->toIso8601String(),
            ],
        ]);
    }

    public function updateLocale(Request $request)
    {
        $data = $request->validate(['locale' => ['required', Rule::in(['az', 'en', 'ru', 'tr'])]]);
        $request->user()->forceFill(['locale' => $data['locale']])->save();

        return response()->json(['courier' => self::courier($request->user())]);
    }

    /** Registers this phone for push messages; a push token moves with the phone, not the user. */
    public function registerDevice(Request $request)
    {
        $data = $request->validate([
            'platform' => ['required', Rule::in(['android', 'ios'])],
            'push_token' => ['nullable', 'string', 'max:512'],
            'app_version' => ['nullable', 'string', 'max:32'],
            'os_version' => ['nullable', 'string', 'max:64'],
            'model' => ['nullable', 'string', 'max:128'],
            'locale' => ['nullable', 'string', 'max:8'],
        ]);

        $tokenId = $request->user()->currentAccessToken()->id;
        $attributes = [...$data, 'user_id' => $request->user()->id, 'token_id' => $tokenId, 'last_seen_at' => now()];

        if (! empty($data['push_token'])) {
            CourierDevice::query()->updateOrCreate(['push_token' => $data['push_token']], $attributes);
        } else {
            CourierDevice::query()->updateOrCreate(['token_id' => $tokenId], $attributes);
        }

        return response()->json(['registered' => true]);
    }

    public function requestDeletion(Request $request, CurrentCompany $tenant)
    {
        $data = $request->validate(['reason' => ['nullable', 'string', 'max:1000']]);

        AccountDeletionRequest::query()->firstOrCreate(
            ['user_id' => $request->user()->id, 'handled_at' => null],
            ['company_id' => $tenant->id(), 'reason' => $data['reason'] ?? null],
        );

        return response()->json(['message' => __('courier.deletion_requested')], 202);
    }

    public static function courier(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'phone' => $user->phone,
            'phone_display' => Phone::display($user->phone),
            'locale' => $user->locale,
            'ink' => Ui::courierInk($user),
        ];
    }
}
