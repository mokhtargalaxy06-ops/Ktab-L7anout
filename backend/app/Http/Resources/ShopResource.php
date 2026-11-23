<?php

namespace App\Http\Resources;

use App\Models\Shop;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ShopResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'region_id' => $this->city->region_id,
            'city_id' => $this->city_id,
            'locality_id' => $this->locality_id,
            'contact_phone' => $this->contact_phone,
            'contact_email' => $this->contact_email,
            'status' => $this->status,
            'review_reason' => $this->review_reason,
            'revision' => $this->revision,
            'location' => $this->when($this->resource->relationLoaded('locality'), fn () => [
                'region' => $this->city->region->only(['name_fr', 'name_ar']),
                'city' => $this->city->only(['name_fr', 'name_ar']),
                'locality' => $this->locality?->only(['name_fr', 'name_ar']),
            ], null),
            'documents' => $this->whenLoaded('documents',
                fn () => $this->documents->map(fn ($file) => [
                    'id' => $file->id, 'status' => $file->status, 'media_type' => $file->media_type, 'revision' => $file->revision,
                    'redacted' => $file->redacted_from_id !== null, 'shared' => $file->shared_at !== null,
                    'local_manual_review' => $file->isAwaitingLocalReview() && $request->user()?->can('review', Shop::class),
                ])),
        ];
    }
}
