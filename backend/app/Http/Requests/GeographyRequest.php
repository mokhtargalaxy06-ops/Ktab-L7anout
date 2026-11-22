<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class GeographyRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'code' => [$this->isMethod('POST') ? 'required' : 'prohibited', 'string', 'regex:/^[A-Za-z0-9_-]{1,40}$/'],
            'name_fr' => ['required', 'string', 'max:150'],
            'name_ar' => ['required', 'string', 'max:150'],
            'source_url' => ['required', 'url:https', 'max:500'],
            'source_date' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'active' => ['required', 'boolean'],
            'parent_id' => [$this->isMethod('POST') && $this->route('level') !== 'regions' ? 'required' : 'prohibited', 'uuid'],
        ];
    }
}
