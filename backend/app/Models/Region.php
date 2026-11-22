<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class Region extends Model
{
    use HasUuids;

    protected $fillable = ['code', 'name_fr', 'name_ar', 'source_url', 'source_date', 'active'];

    protected $casts = ['active' => 'boolean'];
}
