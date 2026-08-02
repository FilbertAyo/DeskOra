<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StorePettyCashRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'user_id' => ['required', 'string', 'short_id', 'exists:users,id'],
            'department_id' => ['required', 'string', 'short_id', 'exists:departments,id'],
            'request_type' => 'required|string|in:Petty Cash,Reimbursement',
            'request_for' => 'required|string|in:Sales Delivery,Transport,Office Supplies',
            'amount' => 'required|numeric|min:0',
            'reason' => 'required|string|max:1000',
        ];
    }
}
