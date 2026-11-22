<?php

namespace App\Services;

use Closure;
use Illuminate\Support\Facades\DB;

class FinancialLock
{
    public static function run(Closure $operation): mixed
    {
        return DB::transaction(function () use ($operation) {
            $control = DB::table('financial_control')->lockForUpdate()->find(1);

            return $operation($control);
        }, 3);
    }
}
