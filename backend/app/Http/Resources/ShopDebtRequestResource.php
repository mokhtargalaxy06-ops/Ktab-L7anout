<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ShopDebtRequestResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id, 'reference' => $this->reference, 'amount_minor' => (string) $this->amount_minor,
            'currency' => 'MAD', 'status' => $this->status, 'reason' => $this->reason,
        ];
    }
}
