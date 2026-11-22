<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AccountStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('changeStatus', $this->route('user'));
    }

    public function rules(): array
    {
        return [
            'status' => ['required', 'in:active,suspended,closed'],
            'current_password' => ['bail', 'required', 'string', 'max:72', 'current_password:web'],
        ];
    }
}
