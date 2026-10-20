<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * QDelivery documents the key as "Authorization: <key>" with bearer format, and clients send
 * it both with and without the "Bearer " prefix. Accept both.
 */
class NormalizeBearerToken
{
    public function handle(Request $request, Closure $next): Response
    {
        $header = trim((string) $request->headers->get('Authorization'));

        if ($header !== '' && ! preg_match('/^Bearer\s/i', $header)) {
            $request->headers->set('Authorization', 'Bearer '.$header);
        }

        return $next($request);
    }
}
