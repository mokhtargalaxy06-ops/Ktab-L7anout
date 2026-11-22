<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class City extends Model
{
    use HasUuids;

    protected $fillable = ['code', 'name_fr', 'name_ar', 'source_url', 'source_date', 'active', 'region_id'];

    protected $casts = ['active' => 'boolean'];

    public function region(): BelongsTo
    {
        return $this->belongsTo(Region::class);
    }
}
