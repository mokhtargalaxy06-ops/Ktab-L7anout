<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->user());
    }

    public function rules(): array
    {
        return ['name' => ['sometimes', 'required', 'string', 'max:120'], 'locale' => ['sometimes', 'required', 'in:fr,ar']];
    }
}
