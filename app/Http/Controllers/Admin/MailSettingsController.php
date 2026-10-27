<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\PlatformSettings;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Throwable;

/** Outgoing mail (password reset links and other emails), set by the platform admin. */
class MailSettingsController extends Controller
{
    public function edit()
    {
        $s = PlatformSettings::all();

        return view('admin.mail', [
            'settings' => [
                'mailer' => $s['mail.mailer'] ?? 'smtp',
                'host' => $s['mail.host'] ?? null,
                'port' => $s['mail.port'] ?? '587',
                'encryption' => $s['mail.encryption'] ?? 'tls',
                'username' => $s['mail.username'] ?? null,
                'from_address' => $s['mail.from_address'] ?? config('mail.from.address'),
                'from_name' => $s['mail.from_name'] ?? config('mail.from.name'),
            ],
            'hasPassword' => ! empty($s['mail.password']),
            'configured' => ($s['mail.mailer'] ?? null) === 'smtp' && ! empty($s['mail.host']),
            'envMailer' => config('mail.default'),
        ]);
    }

    public function update(Request $request)
    {
        $smtp = $request->input('mailer') === 'smtp';
        $data = $request->validate([
            'mailer' => ['required', Rule::in(['smtp', 'log'])],
            'host' => [Rule::requiredIf($smtp), 'nullable', 'string', 'max:255'],
            'port' => [Rule::requiredIf($smtp), 'nullable', 'integer', 'between:1,65535'],
            'encryption' => ['required', Rule::in(['tls', 'ssl', 'none'])],
            'username' => ['nullable', 'string', 'max:255'],
            'password' => ['nullable', 'string', 'max:255'],
            'from_address' => ['required', 'email', 'max:255'],
            'from_name' => ['required', 'string', 'max:100'],
        ]);

        $values = [
            'mail.mailer' => $data['mailer'],
            'mail.host' => $data['host'] ?? null,
            'mail.port' => isset($data['port']) ? (string) $data['port'] : null,
            'mail.encryption' => $data['encryption'],
            'mail.username' => $data['username'] ?? null,
            'mail.from_address' => Str::lower($data['from_address']),
            'mail.from_name' => $data['from_name'],
        ];
        // An empty password field keeps the saved one; the checkbox clears it.
        if (filled($data['password'] ?? null) || $request->boolean('clear_password')) {
            $values['mail.password'] = $request->boolean('clear_password') ? null : $data['password'];
        }

        PlatformSettings::put($values);
        // Queue workers keep their mailer for their lifetime; restart them to pick up the change.
        Artisan::call('queue:restart');

        return back()->with('status', __('admin.saved'));
    }

    public function test(Request $request)
    {
        $data = $request->validate(['to' => ['required', 'email']]);

        try {
            Mail::raw(__('admin.mail.test_body', ['app' => config('app.url')]), fn ($m) => $m
                ->to($data['to'])
                ->subject(__('admin.mail.test_subject')));
        } catch (Throwable $e) {
            report($e);

            return back()->withInput()->with('error', __('admin.mail.test_failed', ['error' => Str::limit($e->getMessage(), 300)]));
        }

        return back()->with('status', config('mail.default') === 'log'
            ? __('admin.mail.test_logged')
            : __('admin.mail.test_sent', ['to' => $data['to']]));
    }
}
