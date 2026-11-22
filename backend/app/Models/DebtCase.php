<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class DebtCase extends Model
{
    use HasUuids;

    protected $fillable = ['city_id', 'shop_id', 'debt_reference', 'debt_date', 'amount_minor', 'situation', 'household_size'];

    protected $casts = ['flagged' => 'boolean', 'published_at' => 'datetime'];

    protected static function booted(): void
    {
        static::creating(fn (DebtCase $case) => $case->public_reference ??= (string) Str::uuid());
    }

    public function profile(): BelongsTo
    {
        return $this->belongsTo(BeneficiaryProfile::class, 'beneficiary_profile_id');
    }

    public function city(): BelongsTo
    {
        return $this->belongsTo(City::class);
    }

    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }

    public function documents(): HasMany
    {
        return $this->hasMany(DebtDocument::class);
    }

    public function history(): HasMany
    {
        return $this->hasMany(DebtVerification::class);
    }
}
