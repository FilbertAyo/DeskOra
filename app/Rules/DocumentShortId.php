<?php

namespace App\Rules;

use App\Services\Documents\DocumentShortIdGenerator;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class DocumentShortId implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || ! DocumentShortIdGenerator::isShortId($value)) {
            $fail('The :attribute must be a valid document id.');
        }
    }
}
