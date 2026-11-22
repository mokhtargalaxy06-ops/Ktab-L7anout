<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PublicCaseSearchRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'q' => ['nullable', 'string', 'max:100'],
            'region_id' => ['nullable', 'uuid'],
            'city_id' => ['nullable', 'uuid'],
            'min_minor' => ['nullable', 'regex:/^[0-9]{1,12}$/'],
            'max_minor' => ['nullable', 'regex:/^[0-9]{1,12}$/'],
            'urgent' => ['nullable', 'in:1'],
            'status' => ['nullable', 'in:open,funded,settled'],
            'page' => ['nullable', 'integer', 'min:1', 'max:100000'],
        ];
    }

    public function after(): array
    {
        return [function ($validator) {
            if ($validator->errors()->isEmpty() && $this->filled('min_minor') && $this->filled('max_minor')
                && (int) $this->input('min_minor') > (int) $this->input('max_minor')) {
                $validator->errors()->add('max_minor', __('validation.min.numeric', ['attribute' => 'max_minor', 'min' => $this->input('min_minor')]));
            }
        }];
    }
}
