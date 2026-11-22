<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['regions' => null, 'cities' => 'region', 'localities' => 'city'] as $name => $parent) {
            Schema::create($name, function (Blueprint $table) use ($name, $parent) {
                $table->uuid('id')->primary();
                $table->string('code', 40);
                $table->string('name_fr', 150);
                $table->string('name_ar', 150);
                $table->string('source_url', 500);
                $table->date('source_date');
                $table->boolean('active')->default(true);
                $table->timestampsTz();
                if ($parent) {
                    $key = $parent.'_id';
                    $table->foreignUuid($key)->constrained()->restrictOnDelete();
                    $table->unique([$key, 'code']);
                    $table->index([$key, 'name_fr']);
                    if ($name === 'localities') {
                        $table->unique(['id', $key]);
                    }
                } else {
                    $table->unique('code');
                }
            });
        }

        Schema::create('shops', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('city_id')->constrained()->restrictOnDelete();
            $table->uuid('locality_id')->nullable();
            $table->foreign(['locality_id', 'city_id'])->references(['id', 'city_id'])->on('localities')->restrictOnDelete();
            $table->string('name', 150);
            $table->string('contact_phone', 25);
            $table->string('contact_email', 254)->nullable();
            $table->enum('status', ['pending', 'verified', 'rejected', 'suspended', 'closed'])->default('pending');
            $table->unsignedInteger('revision')->default(1);
            $table->text('review_reason')->nullable();
            $table->timestampsTz();
            $table->index(['city_id', 'status']);
            $table->index(['status', 'id']);
        });

        Schema::create('shop_memberships', function (Blueprint $table) {
            $table->foreignUuid('shop_id')->constrained()->restrictOnDelete();
            $table->foreignUuid('user_id')->constrained()->restrictOnDelete();
            $table->enum('role', ['owner'])->default('owner');
            $table->enum('status', ['active', 'revoked'])->default('active');
            $table->timestampTz('granted_at');
            $table->primary(['shop_id', 'user_id']);
            $table->index(['user_id', 'status']);
        });

        Schema::create('shop_documents', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('shop_id')->constrained()->restrictOnDelete();
            $table->foreignUuid('uploader_id')->constrained('users')->restrictOnDelete();
            $table->unsignedInteger('revision');
            $table->string('storage_key')->unique();
            $table->string('media_type', 80);
            $table->unsignedBigInteger('size_bytes');
            $table->string('sha256', 64);
            $table->enum('status', ['quarantined', 'available', 'rejected'])->default('quarantined');
            $table->timestampsTz();
            $table->index(['shop_id', 'revision', 'status']);
            $table->index(['status', 'id']);
        });

        Schema::create('shop_debt_requests', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('shop_id')->constrained()->restrictOnDelete();
            $table->foreignUuid('requested_by')->constrained('users')->restrictOnDelete();
            $table->foreignUuid('responded_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->string('reference', 100);
            $table->unsignedBigInteger('amount_minor');
            $table->enum('status', ['pending', 'confirmed', 'disputed'])->default('pending');
            $table->text('reason')->nullable();
            $table->timestampTz('responded_at')->nullable();
            $table->timestampsTz();
            $table->unique(['shop_id', 'reference']);
            $table->index(['shop_id', 'status', 'id']);
        });
    }

    public function down(): void
    {
        foreach (['shop_debt_requests', 'shop_documents', 'shop_memberships', 'shops', 'localities', 'cities', 'regions'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
