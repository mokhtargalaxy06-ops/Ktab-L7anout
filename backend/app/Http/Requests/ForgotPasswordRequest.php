<?php

namespace App\Http\Requests;

class ForgotPasswordRequest extends LoginRequest
{
    public function rules(): array
    {
        return ['email' => parent::rules()['email']];
    }
}
