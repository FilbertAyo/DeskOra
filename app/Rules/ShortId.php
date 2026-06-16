<?php

namespace App\Rules;

use App\Support\ShortId;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class ShortId implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || ! ShortId::is($value)) {
            $fail('The :attribute must be a valid id.');
        }
    }
}
