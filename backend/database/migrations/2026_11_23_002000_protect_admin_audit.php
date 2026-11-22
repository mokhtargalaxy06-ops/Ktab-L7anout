<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::unprepared('CREATE TRIGGER audit_logs_preserve BEFORE UPDATE OR DELETE OR TRUNCATE ON audit_logs FOR EACH STATEMENT EXECUTE FUNCTION funding_append_only()');

            return;
        }
        foreach (['UPDATE', 'DELETE'] as $operation) {
            DB::unprepared("CREATE TRIGGER audit_logs_{$operation}_preserve BEFORE $operation ON audit_logs BEGIN SELECT RAISE(ABORT, 'Audit rows are append-only'); END");
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('DROP TRIGGER audit_logs_preserve ON audit_logs');

            return;
        }
        DB::statement('DROP TRIGGER audit_logs_UPDATE_preserve');
        DB::statement('DROP TRIGGER audit_logs_DELETE_preserve');
    }
};
