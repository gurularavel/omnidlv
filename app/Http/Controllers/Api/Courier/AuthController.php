<?php

namespace App\Http\Controllers\Api\Courier;

use App\Http\Controllers\Controller;
use App\Models\CourierDevice;
use App\Rules\PhoneFormat;
use App\Services\Courier\OtpService;
use Illuminate\Http\Request;

/**
 * Native app sign-in: phone → SMS code → Sanctum token with the "courier" ability. The same
 * OtpService as the PWA, so limits and the reviewer number work the same way.
 */
class AuthController extends Controller
{
    public function __construct(private readonly OtpService $otp) {}

    public function requestCode(Request $request)
    {
        $data = $request->validate(['phone' => ['required', 'string', 'max:32', new PhoneFormat]]);
        $demoCode = $this->otp->request($data['phone'], $request->ip());

        // Always the same answer, so the endpoint does not reveal which numbers are couriers.
        return response()->json(array_filter([
            'sent' => true,
            'expires_in' => OtpService::TTL_MINUTES * 60,
            'demo_code' => $demoCode, // only while SMS runs in demo mode
        ], fn ($v) => $v !== null));
    }

    public function verify(Request $request)
    {
        $data = $request->validate([
            'phone' => ['required', 'string', 'max:32', new PhoneFormat],
            'code' => ['required', 'digits:6'],
            'device_name' => ['nullable', 'string', 'max:100'],
        ]);

        $user = $this->otp->verify($data['phone'], $data['code']);
        $user->forceFill(['last_login_at' => now()])->save();

        $expires = now()->addDays(config('courier_app.token_days'));
        $token = $user->createToken($data['device_name'] ?? 'courier-app', ['courier'], $expires);

        return response()->json([
            'token' => $token->plainTextToken,
            'expires_at' => $expires->toIso8601String(),
            'courier' => AccountController::courier($user),
        ]);
    }

    public function logout(Request $request)
    {
        $token = $request->user()->currentAccessToken();

        // The phone stops receiving pushes for this courier.
        CourierDevice::query()->where('token_id', $token->id)->delete();
        $token->delete();

        return response()->noContent();
    }
}
