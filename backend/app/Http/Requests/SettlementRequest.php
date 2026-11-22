<?php

namespace App\Http\Requests;

use App\Policies\SettlementPolicy;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\DB;

class SettlementRequest extends FormRequest
{
    public function authorize(SettlementPolicy $policy): bool
    {
        if ($this->route('audience') === 'admin') {
            return $policy->administer($this->user());
        }
        $shop = DB::table('debt_cases')->where('id', $this->route('case'))->value('shop_id');

        return $shop && $policy->respond($this->user(), $shop);
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['operation_key' => $this->header('Idempotency-Key')]);
    }

    public function rules(): array
    {
        $admin = $this->route('audience') === 'admin';

        return [
            'action' => ['required', $admin
                ? 'in:initiate,submit,transfer,fail,reconcile,recover'
                : 'in:confirm,dispute'],
            'operation_key' => ['required', 'string', 'max:100', 'regex:/^[A-Za-z0-9._:-]+$/D'],
            'password' => $admin ? ['required', 'string', 'current_password'] : ['prohibited'],
            'reason' => ['nullable', 'required_if:action,fail,dispute', 'string', 'max:500'],
        ];
    }
}
