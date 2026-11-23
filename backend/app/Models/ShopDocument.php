<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class ShopDocument extends Model
{
    use HasUuids;

    protected $fillable = ['uploader_id', 'revision', 'storage_key', 'media_type', 'size_bytes', 'sha256'];

    public static function allowsLocalUnscannedReview(): bool
    {
        return app()->environment('local') && config('filesystems.shop_local_manual_evidence_review') === true;
    }

    public function isAwaitingLocalReview(): bool
    {
        return self::allowsLocalUnscannedReview() && $this->redacted_from_id === null && $this->status === 'quarantined';
    }
}
