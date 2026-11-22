<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('beneficiary_profiles', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->unique()->constrained()->restrictOnDelete();
            $table->timestampsTz();
        });
        Schema::create('debt_cases', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('beneficiary_profile_id')->constrained()->restrictOnDelete();
            $table->foreignUuid('shop_id')->constrained()->restrictOnDelete();
            $table->foreignUuid('city_id')->constrained()->restrictOnDelete();
            $table->string('debt_reference', 100);
            $table->date('debt_date');
            $table->unsignedBigInteger('amount_minor');
            $table->unsignedBigInteger('approved_minor')->nullable();
            $table->text('situation');
            $table->unsignedSmallInteger('household_size')->nullable();
            $table->string('consent_version')->nullable();
            $table->timestampTz('consented_at')->nullable();
            $table->unsignedInteger('revision')->default(1);
            $table->unsignedInteger('verified_shop_revision')->nullable();
            $table->boolean('flagged')->default(false);
            $table->enum('status', ['draft', 'submitted', 'pending_verification', 'under_review', 'approved', 'published', 'rejected', 'disputed', 'cancelled'])->default('draft');
            $table->timestampTz('published_at')->nullable();
            $table->timestampsTz();
            $table->index(['shop_id', 'debt_reference']);
            $table->index(['shop_id', 'status', 'id']);
            $table->index(['beneficiary_profile_id', 'id']);
            $table->index(['status', 'published_at', 'id']);
        });
        Schema::create('debt_verifications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('debt_case_id')->constrained()->restrictOnDelete();
            $table->foreignUuid('actor_id')->constrained('users')->restrictOnDelete();
            $table->string('audience', 20);
            $table->string('action', 40);
            $table->unsignedInteger('revision');
            $table->unsignedBigInteger('amount_minor')->nullable();
            $table->text('reason')->nullable();
            $table->timestampTz('created_at');
            $table->index(['debt_case_id', 'id']);
        });
        Schema::create('documents', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('debt_case_id')->constrained()->restrictOnDelete();
            $table->foreignUuid('uploader_id')->constrained('users')->restrictOnDelete();
            $table->enum('audience', ['beneficiary', 'shop']);
            $table->unsignedInteger('revision');
            $table->string('storage_key')->unique();
            $table->string('media_type', 80);
            $table->unsignedBigInteger('size_bytes');
            $table->string('sha256', 64);
            $table->enum('status', ['quarantined', 'available', 'rejected'])->default('quarantined');
            $table->timestampsTz();
            $table->index(['debt_case_id', 'revision', 'audience', 'status']);
            $table->index(['status', 'updated_at']);
        });
    }

    public function down(): void
    {
        foreach (['documents', 'debt_verifications', 'debt_cases', 'beneficiary_profiles'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
