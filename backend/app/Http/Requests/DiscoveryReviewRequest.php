<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class DiscoveryReviewRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'revision' => ['required', 'integer', 'min:1'],
            'story' => ['required', 'in:essentials,income,dependants'],
            'vulnerability' => ['required', 'integer', 'between:0,4'],
            'access_risk' => ['required', 'integer', 'between:0,4'],
            'reason' => ['required', 'string', 'max:500'],
        ];
    }
}
