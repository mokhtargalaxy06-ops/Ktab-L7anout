<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class DebtVerification extends Model
{
    use HasUuids;

    public $timestamps = false;

    protected $fillable = ['actor_id', 'audience', 'action', 'revision', 'amount_minor', 'reason', 'created_at'];
}
