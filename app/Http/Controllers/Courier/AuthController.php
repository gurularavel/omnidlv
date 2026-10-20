<?php

namespace App\Http\Controllers\Courier;

use App\Http\Controllers\Controller;
use App\Services\Courier\OtpService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuthController extends Controller
{
    public function __construct(private readonly OtpService $otp) {}

    public function phone()
    {
        return view('courier.login');
    }

    public function sendCode(Request $request)
    {
        $data = $request->validate(['phone' => ['required', 'string', 'max:32']]);
        $demoCode = $this->otp->request($data['phone'], $request->ip());

        return redirect()->route('courier.verify')
            ->with('courier_phone', $data['phone'])
            ->with('demo_code', $demoCode);
    }

    public function verifyForm(Request $request)
    {
        $phone = $request->session()->get('courier_phone') ?? $request->old('phone');

        if ($phone === null) {
            return redirect()->route('courier.login');
        }

        $request->session()->keep(['courier_phone']);

        return view('courier.verify', ['phone' => $phone, 'demoCode' => session('demo_code')]);
    }

    public function verify(Request $request)
    {
        $data = $request->validate([
            'phone' => ['required', 'string', 'max:32'],
            'code' => ['required', 'digits:6'],
        ]);

        $request->session()->flash('courier_phone', $data['phone']);
        $user = $this->otp->verify($data['phone'], $data['code']);

        // Couriers stay signed in on their phone; the session is the app's identity.
        Auth::login($user, remember: true);
        $request->session()->regenerate();
        $user->forceFill(['last_login_at' => now()])->save();

        return redirect()->route('courier.home');
    }

    public function logout(Request $request)
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('courier.login');
    }
}
