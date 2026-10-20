<?php

namespace App\Services\Courier;

use App\Enums\Role;
use App\Models\CourierOtp;
use App\Models\Employment;
use App\Models\User;
use App\Services\Sms\SmsService;
use App\Support\Phone;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

/**
 * Courier sign-in by SMS code. Codes are hashed, live 5 minutes and allow 5 attempts; a new
 * request voids earlier codes for the number.
 */
class OtpService
{
    public const TTL_MINUTES = 5;

    public const MAX_ATTEMPTS = 5;

    public function __construct(private readonly SmsService $sms) {}

    /**
     * Sends a code when the number belongs to an active courier. Returns the plain code only
     * while SMS runs in demo mode, so it can be shown on screen for testing.
     */
    public function request(string $rawPhone, ?string $ip = null): ?string
    {
        $phone = $this->phone($rawPhone);
        $user = $this->courier($phone);

        if ($user === null) {
            // Same answer as success: do not reveal which numbers are registered.
            return null;
        }

        CourierOtp::query()->where('phone', $phone)->whereNull('consumed_at')->update(['consumed_at' => now()]);

        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        CourierOtp::query()->create([
            'phone' => $phone,
            'code_hash' => Hash::make($code),
            'expires_at' => now()->addMinutes(self::TTL_MINUTES),
            'ip' => $ip,
        ]);

        $this->sms->send($phone, __('courier.otp_sms', ['code' => $code], $user->locale), 'courier_otp', secret: $code);

        return $this->sms->isDemo() ? $code : null;
    }

    public function verify(string $rawPhone, string $code): User
    {
        $phone = $this->phone($rawPhone);

        $otp = CourierOtp::query()
            ->where('phone', $phone)
            ->whereNull('consumed_at')
            ->where('expires_at', '>', now())
            ->latest('id')
            ->first();

        if ($otp === null || $otp->attempts >= self::MAX_ATTEMPTS) {
            throw ValidationException::withMessages(['code' => __('courier.otp_expired')]);
        }

        $otp->increment('attempts');

        if (! Hash::check($code, $otp->code_hash)) {
            $left = self::MAX_ATTEMPTS - $otp->attempts;

            throw ValidationException::withMessages(['code' => $left > 0
                ? __('courier.otp_wrong', ['left' => $left])
                : __('courier.otp_expired')]);
        }

        $otp->forceFill(['consumed_at' => now()])->save();

        return $this->courier($phone) ?? throw ValidationException::withMessages(['code' => __('courier.not_a_courier')]);
    }

    private function phone(string $raw): string
    {
        return Phone::normalize($raw, 'AZ') ?? throw ValidationException::withMessages(['phone' => __('validation.phone')]);
    }

    private function courier(string $phone): ?User
    {
        $user = User::query()->where('phone', $phone)->first();

        if ($user === null) {
            return null;
        }

        $isCourier = Employment::withoutGlobalScopes()
            ->where('user_id', $user->id)
            ->where('role_slug', Role::Courier->value)
            ->where('is_active', true)
            ->exists();

        return $isCourier ? $user : null;
    }
}
