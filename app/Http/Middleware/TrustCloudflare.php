<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\IpUtils;
use Symfony\Component\HttpFoundation\Response;

/**
 * Production sits behind Cloudflare -> nginx -> apache, and every request reaches PHP over
 * plain http from a local address. Without this, all visitors share one IP (so the OTP and
 * tracking rate limits would be shared too) and generated URLs would be http://.
 * Only requests from a private/loopback address are trusted, so a client reaching PHP
 * directly cannot spoof the headers. Off unless BEHIND_CLOUDFLARE=true.
 */
class TrustCloudflare
{
    public function handle(Request $request, Closure $next): Response
    {
        if (config('app.behind_cloudflare') && IpUtils::isPrivateIp((string) $request->server('REMOTE_ADDR'))) {
            $ip = (string) $request->header('CF-Connecting-IP');
            if (filter_var($ip, FILTER_VALIDATE_IP)) {
                $request->server->set('REMOTE_ADDR', $ip);
            }

            if (str_contains((string) $request->header('CF-Visitor'), '"https"')) {
                $request->server->set('HTTPS', 'on');
                $request->server->set('SERVER_PORT', 443);
            }
        }

        return $next($request);
    }
}
