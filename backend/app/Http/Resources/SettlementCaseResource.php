<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SettlementCaseResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id, 'reference' => $this->public_reference, 'shop_name' => $this->shop_name,
            'queued_at' => $this->queued_at, 'approved_minor' => (string) $this->approved_minor,
            'allocated_minor' => (string) $this->allocated_minor, 'settled_minor' => (string) $this->settled_minor,
            'payments' => $this->payments,
        ];
    }
}
