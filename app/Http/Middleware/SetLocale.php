<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetLocale
{
    public const LOCALES = ['az', 'en', 'ru', 'tr'];

    public function handle(Request $request, Closure $next): Response
    {
        $locale = $request->query('lang')
            ?? ($request->hasSession() ? $request->session()->get('locale') : null)
            ?? ($request->user()?->locale ?? null)
            ?? $request->getPreferredLanguage(self::LOCALES);

        if (in_array($locale, self::LOCALES, true)) {
            app()->setLocale($locale);

            if ($request->query('lang') && $request->hasSession()) {
                $request->session()->put('locale', $locale);
            }
        }

        return $next($request);
    }
}
