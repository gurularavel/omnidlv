<?php

namespace App\Support;

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;

/**
 * Settings the platform admin edits at runtime (platform_settings table), cached for every
 * process. Keys listed in SECRET are encrypted with APP_KEY.
 */
final class PlatformSettings
{
    private const CACHE_KEY = 'platform-settings:v1';

    private const SECRET = ['mail.password'];

    /** @return array<string, ?string> */
    public static function all(): array
    {
        return Cache::rememberForever(self::CACHE_KEY, function () {
            try {
                $rows = DB::table('platform_settings')->pluck('value', 'key')->all();
            } catch (QueryException) {
                return []; // Before the migration has run.
            }

            foreach (self::SECRET as $key) {
                if (isset($rows[$key])) {
                    $rows[$key] = rescue(fn () => Crypt::decryptString($rows[$key]), null, false);
                }
            }

            return $rows;
        });
    }

    public static function get(string $key, ?string $default = null): ?string
    {
        return self::all()[$key] ?? $default;
    }

    /** @param  array<string, ?string>  $values */
    public static function put(array $values): void
    {
        $now = now();

        foreach ($values as $key => $value) {
            if ($value !== null && in_array($key, self::SECRET, true)) {
                $value = Crypt::encryptString($value);
            }

            DB::table('platform_settings')->upsert(
                [['key' => $key, 'value' => $value, 'created_at' => $now, 'updated_at' => $now]],
                ['key'],
                ['value', 'updated_at'],
            );
        }

        Cache::forget(self::CACHE_KEY);
    }

    /** Points Laravel's mailer at the SMTP server saved in the admin. Without one, .env stays in charge. */
    public static function applyMail(): void
    {
        $s = self::all();

        if (($s['mail.mailer'] ?? null) !== 'smtp' || empty($s['mail.host'])) {
            if (($s['mail.mailer'] ?? null) === 'log') {
                config(['mail.default' => 'log']);
            }

            return;
        }

        config([
            'mail.default' => 'smtp',
            'mail.mailers.smtp.url' => null,
            'mail.mailers.smtp.host' => $s['mail.host'],
            'mail.mailers.smtp.port' => (int) ($s['mail.port'] ?? 587),
            // "smtps" is implicit TLS (port 465); plain "smtp" upgrades with STARTTLS when offered.
            'mail.mailers.smtp.scheme' => ($s['mail.encryption'] ?? null) === 'ssl' ? 'smtps' : 'smtp',
            'mail.mailers.smtp.username' => $s['mail.username'] ?? null,
            'mail.mailers.smtp.password' => $s['mail.password'] ?? null,
            'mail.from.address' => $s['mail.from_address'] ?? config('mail.from.address'),
            'mail.from.name' => $s['mail.from_name'] ?? config('mail.from.name'),
        ]);
    }
}
