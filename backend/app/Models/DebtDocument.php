<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class DebtDocument extends Model
{
    use HasUuids;

    protected $table = 'documents';

    protected $fillable = ['uploader_id', 'audience', 'revision', 'storage_key', 'media_type', 'size_bytes', 'sha256'];
}
