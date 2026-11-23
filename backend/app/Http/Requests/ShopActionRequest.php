<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ShopActionRequest extends FormRequest
{
    public function rules(): array
    {
        return match ($this->route()->getActionMethod()) {
            'upload', 'uploadRedacted' => ['document' => ['required', 'file', 'mimetypes:application/pdf,image/jpeg,image/png', 'max:5120']],
            'shareEvidence' => ['share' => ['required', 'boolean']],
            'review' => [
                'decision' => ['required', 'in:verified,rejected,suspended'],
                'revision' => ['required', 'integer', 'min:1'],
                'reason' => ['required_unless:decision,verified', 'nullable', 'string', 'max:500'],
            ],
            'requestDebt' => [
                'reference' => ['required', 'string', 'regex:/^[A-Za-z0-9_-]{1,100}$/'],
                'amount_minor' => ['required', 'string', 'regex:/^[1-9][0-9]{0,11}$/'],
            ],
            'respondDebt' => [
                'decision' => ['required', 'in:confirmed,disputed'],
                'reason' => ['required_if:decision,disputed', 'nullable', 'string', 'max:500'],
            ],
        };
    }
}
