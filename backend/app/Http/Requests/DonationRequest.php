<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class DonationRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge(['operation_key' => $this->header('Idempotency-Key')]);
    }

    public function rules(): array
    {
        $amount = ['required', 'string', 'regex:/^[1-9][0-9]{0,11}$/D'];
        $key = ['required', 'string', 'regex:/^[A-Za-z0-9._-]{1,80}$/D'];

        return match ($this->route()->getActionMethod()) {
            'create' => [
                'scope' => ['required', 'in:specific,urgent_pool'],
                'case_reference' => ['required_if:scope,specific', 'prohibited_if:scope,urgent_pool', 'nullable', 'uuid'],
                'amount_minor' => $amount, 'policy_version' => ['required', 'string', 'max:30'],
                'currency' => ['required', 'in:MAD'], 'operation_key' => $key,
            ],
            'simulate' => ['outcome' => ['required', 'in:success,pending,failed,cancelled']],
            'simulateReturn' => [
                'kind' => ['required', 'in:refund,reversal'], 'amount_minor' => $amount, 'operation_key' => $key,
            ],
        };
    }
}
