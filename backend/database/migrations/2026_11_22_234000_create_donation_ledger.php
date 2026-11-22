<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('financial_control', function (Blueprint $table) {
            $table->integer('id')->primary();
            $table->boolean('frozen')->default(false);
        });
        DB::table('financial_control')->insert(['id' => 1]);
        Schema::create('donations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('donor_id')->constrained('users')->restrictOnDelete();
            $table->foreignUuid('debt_case_id')->nullable()->constrained()->restrictOnDelete();
            $table->enum('scope', ['specific', 'urgent_pool']);
            $table->unsignedBigInteger('amount_minor');
            $table->string('policy_version', 30);
            $table->string('idempotency_key', 100);
            $table->string('request_hash', 64);
            $table->enum('allocation_status', ['waiting', 'partial', 'allocated', 'returned'])->default('waiting');
            $table->timestampsTz();
            $table->unique(['donor_id', 'idempotency_key']);
            $table->index(['donor_id', 'id']);
        });
        Schema::create('payment_transactions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('donation_id')->unique()->constrained()->restrictOnDelete();
            $table->enum('status', ['pending', 'failed', 'cancelled', 'confirmed'])->default('pending');
            $table->string('provider_reference')->unique();
            $table->unsignedBigInteger('amount_minor');
            $table->timestampTz('confirmed_at')->nullable();
            $table->timestampsTz();
            $table->index(['status', 'confirmed_at', 'id']);
        });
        Schema::create('webhook_events', function (Blueprint $table) {
            $table->string('event_id', 100)->primary();
            $table->foreignUuid('payment_transaction_id')->constrained()->restrictOnDelete();
            $table->string('payload_hash', 64);
            $table->string('outcome', 20);
            $table->timestampTz('created_at');
        });
        Schema::create('allocation_runs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('payment_transaction_id')->constrained()->restrictOnDelete();
            $table->string('operation_key', 150)->unique();
            $table->json('policy_snapshot');
            $table->timestampTz('created_at');
            $table->unique(['id', 'payment_transaction_id']);
        });
        Schema::create('donation_allocations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('allocation_run_id');
            $table->foreignUuid('payment_transaction_id')->constrained()->restrictOnDelete();
            $table->foreignUuid('debt_case_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('amount_minor');
            $table->unsignedInteger('rank');
            $table->unsignedInteger('score')->nullable();
            $table->timestampTz('created_at');
            $table->unique(['allocation_run_id', 'debt_case_id']);
            $table->foreign(['allocation_run_id', 'payment_transaction_id'])
                ->references(['id', 'payment_transaction_id'])->on('allocation_runs')->restrictOnDelete();
        });
        Schema::create('shop_payments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('debt_case_id')->constrained()->restrictOnDelete();
            $table->foreignUuid('initiator_id')->constrained('users')->restrictOnDelete();
            $table->foreignUuid('approver_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->foreignUuid('evidence_document_id')->nullable()->constrained('documents')->restrictOnDelete();
            $table->string('operation_key')->unique();
            $table->string('destination_reference');
            $table->unsignedBigInteger('amount_minor');
            $table->enum('status', ['requested', 'submitted', 'unknown', 'evidence_pending', 'paid', 'failed', 'canceled']);
            $table->timestampsTz();
        });
        Schema::create('shop_payment_lines', function (Blueprint $table) {
            $table->foreignUuid('shop_payment_id')->constrained()->restrictOnDelete();
            $table->foreignUuid('donation_allocation_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('amount_minor');
            $table->primary(['shop_payment_id', 'donation_allocation_id']);
        });
        Schema::create('refunds', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('payment_transaction_id')->constrained()->restrictOnDelete();
            $table->enum('kind', ['refund', 'reversal']);
            $table->enum('status', ['requested', 'confirmed', 'canceled']);
            $table->string('reason', 40);
            $table->unsignedBigInteger('amount_minor');
            $table->string('operation_key', 150)->unique();
            $table->timestampsTz();
            $table->index(['payment_transaction_id', 'status']);
        });
        Schema::create('financial_events', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('payment_transaction_id')->constrained()->restrictOnDelete();
            $table->foreignUuid('donation_allocation_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignUuid('refund_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignUuid('shop_payment_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('operation_key', 180)->unique();
            $table->string('type', 40);
            $table->unsignedBigInteger('amount_minor');
            $table->timestampTz('created_at');
            $table->index(['payment_transaction_id', 'type']);
            $table->index(['donation_allocation_id', 'type']);
        });

        Schema::table('case_funding_reports', fn (Blueprint $table) => $table->boolean('simulated')->default(false));
        Schema::table('funding_receipt_reports', fn (Blueprint $table) => $table->boolean('simulated')->default(false));

        if (DB::getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE donations ADD CONSTRAINT donations_scope_case CHECK ((scope = 'specific') = (debt_case_id IS NOT NULL))");
            foreach (['donations', 'payment_transactions', 'donation_allocations', 'shop_payments', 'shop_payment_lines', 'refunds', 'financial_events'] as $table) {
                DB::statement("ALTER TABLE $table ADD CONSTRAINT {$table}_amount_positive CHECK (amount_minor BETWEEN 1 AND 999999999999)");
            }
            DB::unprepared("CREATE OR REPLACE FUNCTION funding_append_only() RETURNS trigger LANGUAGE plpgsql AS \$\$ BEGIN RAISE EXCEPTION 'Funding records are append-only'; END; \$\$");
            DB::unprepared("CREATE OR REPLACE FUNCTION funding_no_delete() RETURNS trigger LANGUAGE plpgsql AS \$\$ BEGIN RAISE EXCEPTION 'Funding records cannot be deleted'; END; \$\$");
        }

        foreach (['financial_events', 'donation_allocations', 'allocation_runs', 'webhook_events', 'shop_payment_lines'] as $table) {
            $this->protect($table, true);
        }
        foreach (['donations', 'payment_transactions', 'refunds', 'shop_payments'] as $table) {
            $this->protect($table, false);
        }
    }

    private function protect(string $table, bool $immutable): void
    {
        if (DB::getDriverName() === 'pgsql') {
            $operations = $immutable ? 'UPDATE OR DELETE OR TRUNCATE' : 'DELETE OR TRUNCATE';
            $function = $immutable ? 'funding_append_only' : 'funding_no_delete';
            DB::unprepared("CREATE TRIGGER {$table}_preserve BEFORE $operations ON $table FOR EACH STATEMENT EXECUTE FUNCTION $function()");

            return;
        }

        foreach ($immutable ? ['UPDATE', 'DELETE'] : ['DELETE'] as $operation) {
            DB::unprepared("CREATE TRIGGER {$table}_{$operation}_preserve BEFORE $operation ON $table BEGIN SELECT RAISE(ABORT, 'Funding record is protected'); END");
        }
    }

    public function down(): void
    {
        foreach (['financial_events', 'refunds', 'shop_payment_lines', 'shop_payments', 'donation_allocations', 'allocation_runs',
            'webhook_events', 'payment_transactions', 'donations', 'financial_control'] as $table) {
            Schema::dropIfExists($table);
        }
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('DROP FUNCTION funding_append_only(), funding_no_delete()');
        }
        Schema::table('case_funding_reports', fn (Blueprint $table) => $table->dropColumn('simulated'));
        Schema::table('funding_receipt_reports', fn (Blueprint $table) => $table->dropColumn('simulated'));
    }
};
