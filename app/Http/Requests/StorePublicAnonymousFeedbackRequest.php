<?php

namespace App\Http\Requests;

use App\Models\AnonymousFeedback;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePublicAnonymousFeedbackRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'category' => ['required', 'string', Rule::in(AnonymousFeedback::categories())],
            'body' => ['required', 'string', 'max:5000'],
            'website' => ['prohibited'],
        ];
    }
}
