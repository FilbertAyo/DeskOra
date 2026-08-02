<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class StoreAdminUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('users management settings') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'title' => ['nullable', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:'.User::class],
            'password' => ['required', 'string', 'confirmed', Password::defaults()],
            'phone' => ['required', 'regex:/^0[76][0-9]{8}$/'],
            'department_id' => ['nullable', 'string', 'short_id', Rule::exists('departments', 'id')->where(fn ($q) => $q->where('status', 'active')->whereNull('parent_id'))],
            'sub_department_ids' => ['nullable', 'array'],
            'sub_department_ids.*' => ['string', 'short_id', Rule::exists('departments', 'id')->where(fn ($q) => $q->where('status', 'active')->whereNotNull('parent_id'))],
            'is_department_head' => ['nullable', 'boolean'],
            'roles' => ['nullable', 'array'],
            'roles.*' => ['string', Rule::exists('roles', 'name')],
        ];
    }
}
