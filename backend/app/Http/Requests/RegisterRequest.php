<?php

namespace App\Http\Requests;

use App\Support\Access;
use App\Support\PhoneNumber;
use Illuminate\Validation\Rule;

class RegisterRequest extends LoginRequest
{
    protected function prepareForValidation(): void
    {
        parent::prepareForValidation();
        $this->merge(['phone' => PhoneNumber::normalize($this->input('phone'))]);
    }

    public function rules(): array
    {
        $credentials = parent::rules();

        return [
            'name' => ['required', 'string', 'max:120'],
            'email' => [...$credentials['email'], 'unique:users,email'],
            'password' => [...$credentials['password'], 'min:12', 'confirmed'],
            'locale' => ['required', 'in:fr,ar'],
            'role' => ['sometimes', 'required', Rule::in(Access::PUBLIC_ROLES)],
            'phone' => ['required', 'string', PhoneNumber::RULE],
            'whatsapp_contact' => ['sometimes', 'boolean'],
        ];
    }
}
