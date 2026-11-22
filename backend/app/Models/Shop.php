<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Shop extends Model
{
    use HasUuids;

    protected $fillable = ['name', 'city_id', 'locality_id', 'contact_phone', 'contact_email'];

    public function city(): BelongsTo
    {
        return $this->belongsTo(City::class);
    }

    public function locality(): BelongsTo
    {
        return $this->belongsTo(Locality::class);
    }

    public function owners(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'shop_memberships')->wherePivot('role', 'owner')->wherePivot('status', 'active');
    }

    public function documents(): HasMany
    {
        return $this->hasMany(ShopDocument::class);
    }

    public function debtRequests(): HasMany
    {
        return $this->hasMany(ShopDebtRequest::class);
    }
}
