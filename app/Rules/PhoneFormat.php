<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Digits only, with an optional leading "+" and the separators people type (spaces, dashes,
 * brackets, dots). Letters are rejected here; whether the number exists is checked later by
 * Phone::normalize.
 */
class PhoneFormat implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $value = (string) $value;
        $digits = preg_replace('/\D/', '', $value);

        if (! preg_match('/^\+?[\d\s().\-]+$/', $value) || strlen($digits) < 7 || strlen($digits) > 15) {
            $fail(__('validation.phone'));
        }
    }
}
