<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class DebtApplicationRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'city_id' => ['required', 'uuid'],
            'shop_id' => ['required', 'uuid'],
            'debt_reference' => ['required', 'string', 'max:100', 'regex:/^[\pL\pN_-]+$/u'],
            'debt_date' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'amount_minor' => ['required', 'string', 'regex:/^[1-9][0-9]{0,11}$/'],
            'situation' => ['required', 'string', 'max:3000'],
            'household_size' => ['nullable', 'integer', 'min:1', 'max:50'],
        ];
    }

    public function messages(): array
    {
        return [
            'debt_reference.regex' => __('cases.invalid_reference'),
            'debt_date.before_or_equal' => __('cases.future_debt_date'),
        ];
    }
}
