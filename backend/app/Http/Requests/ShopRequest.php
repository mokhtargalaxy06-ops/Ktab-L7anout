<?php

namespace App\Http\Requests;

use App\Models\City;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class ShopRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:150'],
            'region_id' => ['bail', 'required', 'uuid'],
            'city_id' => ['bail', 'required', 'uuid'],
            'locality_id' => ['bail', 'nullable', 'uuid', Rule::exists('localities', 'id')->where('city_id', is_string($this->input('city_id')) && Str::isUuid($this->input('city_id')) ? $this->input('city_id') : null)->where('active', true)],
            'contact_phone' => ['required', 'string', 'regex:/^\+?[0-9][0-9 ().-]{6,24}$/'],
            'contact_email' => ['nullable', 'email', 'max:254'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator) {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $city = City::whereKey($this->input('city_id'))->where('region_id', $this->input('region_id'))
                ->where('active', true)->whereHas('region', fn ($region) => $region->where('active', true))->exists();
            if (! $city) {
                $validator->errors()->add('city_id', __('shops.city_mismatch'));
            }
        }];
    }
}
