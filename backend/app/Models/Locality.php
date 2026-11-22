<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class Locality extends Model
{
    use HasUuids;

    protected $fillable = ['code', 'name_fr', 'name_ar', 'source_url', 'source_date', 'active', 'city_id'];

    protected $casts = ['active' => 'boolean'];
}
