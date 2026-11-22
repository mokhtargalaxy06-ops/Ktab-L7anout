<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class ShopDocument extends Model
{
    use HasUuids;

    protected $fillable = ['uploader_id', 'revision', 'storage_key', 'media_type', 'size_bytes', 'sha256'];
}
