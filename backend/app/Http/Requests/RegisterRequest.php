<?php

namespace App\Http\Requests;

class RegisterRequest extends LoginRequest
{
    public function rules(): array
    {
        $credentials = parent::rules();

        return [
            'name' => ['required', 'string', 'max:120'],
            'email' => [...$credentials['email'], 'unique:users,email'],
            'password' => [...$credentials['password'], 'min:12', 'confirmed'],
            'locale' => ['required', 'in:fr,ar'],
        ];
    }
}
