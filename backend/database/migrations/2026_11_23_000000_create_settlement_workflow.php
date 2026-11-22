<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('settlement_queue', function (Blueprint $table) {
            $table->foreignUuid('debt_case_id')->primary()->constrained()->restrictOnDelete();
            $table->timestampTz('created_at');
        });
        Schema::create('settlement_transfers', function (Blueprint $table) {
            $table->foreignUuid('shop_payment_id')->primary()->constrained()->restrictOnDelete();
            $table->string('reference')->unique();
            $table->json('evidence');
            $table->timestampTz('created_at');
        });
        Schema::create('settlement_actions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('shop_payment_id')->constrained()->restrictOnDelete();
            $table->foreignUuid('actor_id')->constrained('users')->restrictOnDelete();
            $table->string('action', 24);
            $table->string('operation_key', 100);
            $table->string('request_hash', 64);
            $table->string('reason', 500)->nullable();
            $table->timestampTz('created_at');
            $table->unique(['actor_id', 'operation_key']);
            $table->index(['shop_payment_id', 'id']);
        });
        foreach (['settlement_actions', 'settlement_transfers'] as $table) {
            if (DB::getDriverName() === 'pgsql') {
                DB::unprepared("CREATE TRIGGER {$table}_preserve BEFORE UPDATE OR DELETE OR TRUNCATE ON $table FOR EACH STATEMENT EXECUTE FUNCTION funding_append_only()");
            } else {
                foreach (['UPDATE', 'DELETE'] as $operation) {
                    DB::unprepared("CREATE TRIGGER {$table}_{$operation}_preserve BEFORE $operation ON $table BEGIN SELECT RAISE(ABORT, 'Settlement evidence is append-only'); END");
                }
            }
        }
        DB::table('settlement_queue')->insertUsing(['debt_case_id', 'created_at'],
            DB::table('case_funding_reports as r')->join('debt_cases as c', 'c.id', '=', 'r.debt_case_id')
                ->where('r.simulated', true)->where('c.approved_minor', '>', 0)
                ->whereColumn('r.allocated_minor', 'c.approved_minor')
                ->whereColumn('r.settled_minor', '<', 'c.approved_minor')->select('r.debt_case_id', 'r.updated_at'));
    }

    public function down(): void
    {
        Schema::dropIfExists('settlement_actions');
        Schema::dropIfExists('settlement_transfers');
        Schema::dropIfExists('settlement_queue');
    }
};
