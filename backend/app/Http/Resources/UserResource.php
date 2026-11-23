<?php

namespace App\Http\Resources;

use App\Models\User;
use App\Support\Access;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $viewer = $request->user();
        $canViewContact = $viewer && ($viewer->is($this->resource) || $viewer->can('viewAny', User::class));

        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->when($canViewContact, $this->phone),
            'whatsapp_contact' => $this->when($canViewContact, (bool) $this->whatsapp_contact),
            'locale' => $this->locale,
            'roles' => $this->roles->pluck('code')->sort()->values()->all(),
            'status' => $this->status,
            'email_verified' => $this->hasVerifiedEmail(),
            'abilities' => Access::abilities($this->resource),
        ];
    }
}
