<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('actor_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->string('actor_kind');
            $table->string('action');
            $table->string('target_type');
            $table->uuid('target_id');
            $table->uuid('correlation_id');
            $table->json('metadata');
            $table->timestampTz('created_at');
            $table->index(['target_type', 'target_id', 'created_at']);
            $table->index(['actor_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
