<?php

namespace App\Http\Resources;

use App\Support\Access;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'locale' => $this->locale,
            'roles' => $this->roles->pluck('code')->sort()->values()->all(),
            'status' => $this->status,
            'email_verified' => $this->hasVerifiedEmail(),
            'abilities' => Access::abilities($this->resource),
        ];
    }
}
