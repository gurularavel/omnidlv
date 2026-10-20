<?php

namespace App\Exceptions;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use RuntimeException;

/** A business rule was violated. Rendered as 422 with a stable machine-readable code. */
class DomainException extends RuntimeException
{
    public function __construct(string $message, public readonly string $errorCode = 'domain_error')
    {
        parent::__construct($message);
    }

    public function render(Request $request): JsonResponse|RedirectResponse
    {
        if ($request->expectsJson() || $request->is('api/*')) {
            return response()->json(['message' => $this->getMessage(), 'code' => $this->errorCode], 422);
        }

        return back()->withInput()->with('error', $this->getMessage());
    }
}
