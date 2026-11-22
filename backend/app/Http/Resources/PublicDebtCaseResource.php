<?php

namespace App\Http\Resources;

use App\Services\SimulatedProvider;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PublicDebtCaseResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $record = $this->resource;
        $threshold = config('discovery.urgency.urgent_threshold') * 10000;

        return [
            'id' => $record->id,
            'region' => ['name_fr' => $record->region_fr, 'name_ar' => $record->region_ar],
            'city' => ['name_fr' => $record->city_fr, 'name_ar' => $record->city_ar],
            'story' => $record->public_story,
            'amount_minor' => (string) $record->approved_minor,
            'collected_minor' => (string) $record->collected_minor,
            'remaining_minor' => (string) $record->remaining_minor,
            'status' => $record->remaining_minor > 0 ? 'open' : ((int) $record->settled_minor === (int) $record->approved_minor ? 'settled' : 'funded'),
            'progress' => intdiv((int) $record->collected_minor * 100, (int) $record->approved_minor),
            'urgent' => $record->remaining_minor > 0 && $record->urgency_score !== null && $record->urgency_score >= $threshold,
            'verified' => true,
            'simulated' => SimulatedProvider::enabled(),
            'currency' => 'MAD',
        ];
    }
}
