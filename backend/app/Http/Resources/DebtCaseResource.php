<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DebtCaseResource extends JsonResource
{
    public function __construct($resource, private readonly string $audience)
    {
        parent::__construct($resource);
    }

    public function toArray(Request $request): array
    {
        $shop = $this->audience === 'shop';
        $admin = $this->audience === 'admin';
        $data = [
            'id' => $this->id, 'status' => $this->status, 'revision' => $this->revision,
            'city_id' => $this->city_id, 'region_id' => $this->city->region_id, 'shop_id' => $this->shop_id, 'shop_name' => $this->shop->name,
            'debt_reference' => $this->debt_reference, 'debt_date' => $this->debt_date,
            'amount_minor' => (string) $this->amount_minor, 'currency' => 'MAD',
        ];
        if (! $shop) {
            $data += [
                'situation' => $this->situation, 'household_size' => $this->household_size,
                'consent_version' => $this->consent_version, 'consented_at' => $this->consented_at,
            ];
        }
        if ($admin) {
            $data['flagged'] = $this->flagged;
            $data['public_story'] = $this->public_story;
            $data['vulnerability_grade'] = $this->vulnerability_grade;
            $data['access_risk_grade'] = $this->access_risk_grade;
            $data['duplicate_count'] = $this->duplicate_count;
            $data['duplicates'] = $this->duplicates;
        }
        if ($this->resource->relationLoaded('documents')) {
            $data['documents'] = $this->documents->map(fn ($document) => $document->only(['id', 'audience', 'revision', 'media_type', 'status']));
        }
        if ($this->resource->relationLoaded('history')) {
            $data['history'] = $this->history->map(fn ($event) => $event->only(['action', 'audience', 'reason', 'created_at', 'revision']) + [
                'amount_minor' => $event->amount_minor === null ? null : (string) $event->amount_minor,
            ]);
        }

        return $data;
    }
}
