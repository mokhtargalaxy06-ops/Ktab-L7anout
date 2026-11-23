<?php

namespace App\Http\Requests;

use App\Support\PhoneNumber;
use Illuminate\Foundation\Http\FormRequest;

class UpdateProfileRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        if ($this->has('phone')) {
            $this->merge(['phone' => PhoneNumber::normalize($this->input('phone'))]);
        }
    }

    public function authorize(): bool
    {
        return $this->user()->can('update', $this->user());
    }

    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:120'],
            'locale' => ['sometimes', 'required', 'in:fr,ar'],
            'phone' => ['sometimes', 'nullable', 'string', PhoneNumber::RULE],
            'whatsapp_contact' => ['sometimes', 'boolean'],
        ];
    }
}
