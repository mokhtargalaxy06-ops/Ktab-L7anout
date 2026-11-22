<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class DebtActionRequest extends FormRequest
{
    public function rules(): array
    {
        return match ($this->route()->getActionMethod()) {
            'submit' => ['consent' => ['required', 'accepted'], 'consent_version' => ['required', 'in:1'], 'revision' => ['required', 'integer', 'min:1']],
            'uploadOwner', 'uploadShop' => [
                'document' => ['required', 'file', 'extensions:pdf,jpg,jpeg,png', 'mimetypes:application/pdf,image/jpeg,image/png', 'max:5120'],
                'revision' => ['required', 'integer', 'min:1'],
            ],
            'verify' => [
                'decision' => ['required', 'in:confirm,dispute,reject'],
                'amount_minor' => ['required_if:decision,confirm', 'nullable', 'string', 'regex:/^[1-9][0-9]{0,11}$/'],
                'reason' => ['required_unless:decision,confirm', 'nullable', 'string', 'max:500'],
                'revision' => ['required', 'integer', 'min:1'],
            ],
            'review' => [
                'decision' => ['required', 'in:route,approve,reject,request_info,flag,clear_flag,publish,unpublish,suspend,reopen_dispute'],
                'reason' => ['required_if:decision,reject,request_info,flag,clear_flag,unpublish,suspend,reopen_dispute', 'nullable', 'string', 'max:500'],
                'revision' => ['required', 'integer', 'min:1'],
            ],
        };
    }
}
