<?php

namespace App\Http\Requests;

class ResetPasswordRequest extends LoginRequest
{
    public function rules(): array
    {
        $rules = parent::rules();
        $rules['password'][] = 'min:12';
        $rules['password'][] = 'confirmed';
        $rules['token'] = ['required', 'string', 'max:128'];

        return $rules;
    }
}
