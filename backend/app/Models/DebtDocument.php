<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class DebtDocument extends Model
{
    use HasUuids;

    public const SUBMISSION_STATUSES = ['available', 'quarantined'];

    protected $table = 'documents';

    protected $fillable = ['uploader_id', 'audience', 'revision', 'storage_key', 'media_type', 'size_bytes', 'sha256'];

    public static function allowsLocalUnscannedReview(): bool
    {
        return app()->environment('local') && config('filesystems.case_local_manual_evidence_review') === true;
    }

    public function isAwaitingLocalReview(): bool
    {
        return self::allowsLocalUnscannedReview() && $this->audience === 'beneficiary' && $this->status === 'quarantined';
    }
}
