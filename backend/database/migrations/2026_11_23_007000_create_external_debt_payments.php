<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('external_debt_payments', function (Blueprint $table) {
            $table->foreignUuid('donation_id')->primary()->constrained()->restrictOnDelete();
            $table->foreignUuid('declared_by')->constrained('users')->restrictOnDelete();
            $table->foreignUuid('shop_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('amount_minor');
            $table->string('reference', 120);
            $table->string('evidence_key')->unique();
            $table->foreignUuid('reviewed_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->string('reason', 500)->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamp('created_at');
            $table->index(['status', 'created_at']);
            $table->unique(['shop_id', 'reference']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('external_debt_payments');
    }
};
