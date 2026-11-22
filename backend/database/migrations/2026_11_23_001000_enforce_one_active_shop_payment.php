<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("CREATE UNIQUE INDEX shop_payments_one_active_case ON shop_payments (debt_case_id) WHERE status NOT IN ('failed', 'canceled')");
    }

    public function down(): void
    {
        DB::statement('DROP INDEX shop_payments_one_active_case');
    }
};
