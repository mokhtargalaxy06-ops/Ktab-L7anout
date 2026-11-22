<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class ShopDebtRequest extends Model
{
    use HasUuids;

    protected $fillable = ['requested_by', 'reference', 'amount_minor'];
}
