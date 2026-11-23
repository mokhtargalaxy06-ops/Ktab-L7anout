<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ExternalDebtPaymentRequest extends FormRequest
{
    public function rules(): array
    {
        return $this->route()->getActionMethod() === 'declareReceipt' ? [
            'amount_minor' => ['required', 'string', 'regex:/^[1-9][0-9]{0,11}$/D'],
            'reference' => ['required', 'string', 'max:120'],
            'evidence' => ['required', 'file', 'mimes:pdf,png,jpg,jpeg', 'max:5120'],
        ] : [
            'decision' => ['required', 'in:approved,rejected'],
            'reason' => ['required', 'string', 'max:500'],
            'password' => ['required', 'string', 'current_password:web'],
        ];
    }
}
