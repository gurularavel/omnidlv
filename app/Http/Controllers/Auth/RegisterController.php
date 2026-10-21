<?php

namespace App\Http\Controllers\Auth;

use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\Employment;
use App\Models\User;
use App\Rules\PhoneFormat;
use App\Support\Phone;
use App\Support\Plans;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

class RegisterController extends Controller
{
    public function create()
    {
        return view('auth.register');
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:191'],
            'company' => ['required', 'string', 'max:191'],
            'email' => ['required', 'email', 'max:191', 'unique:users,email'],
            'phone' => ['nullable', 'string', 'max:32', new PhoneFormat],
            'password' => ['required', 'confirmed', Password::min(8)],
            'terms' => ['accepted'],
        ]);

        $phone = null;
        if (! empty($data['phone'])) {
            $phone = Phone::normalize($data['phone'], 'AZ') ?? throw ValidationException::withMessages(['phone' => __('validation.phone')]);
            if (User::query()->where('phone', $phone)->exists()) {
                throw ValidationException::withMessages(['phone' => __('validation.unique', ['attribute' => __('Phone')])]);
            }
        }

        $user = DB::transaction(function () use ($data, $phone) {
            // The trial runs on Scale so new companies can try proof of delivery and feedback.
            $company = Company::create([
                'name' => $data['company'],
                'short_name' => Str::limit($data['company'], 32, ''),
                'slug' => Str::slug($data['company']).'-'.Str::lower(Str::random(6)),
                'locale' => app()->getLocale(),
                'plan_slug' => 'scale',
                'trial_ends_at' => now()->addDays(Plans::TRIAL_DAYS),
            ]);

            $user = User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'phone' => $phone,
                'phone_country' => $phone ? 'AZ' : null,
                'password' => $data['password'],
                'locale' => app()->getLocale(),
                'active_company_id' => $company->id,
            ]);

            Employment::withoutGlobalScopes()->create([
                'company_id' => $company->id,
                'user_id' => $user->id,
                'role_slug' => Role::Owner->value,
            ]);

            return $user;
        });

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->route('dashboard')->with('status', __('Your company is ready. The 14-day trial has started.'));
    }
}
