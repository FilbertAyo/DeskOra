<?php

namespace App\Rules;

use App\Support\ShortId as ShortIdValue;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class ShortId implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || ! ShortIdValue::is($value)) {
            $fail('The :attribute must be a valid id.');
        }
    }
}
