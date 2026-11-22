<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('roles', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('code')->unique();
        });

        Schema::create('user_roles', function (Blueprint $table) {
            $table->foreignUuid('user_id')->constrained()->restrictOnDelete();
            $table->foreignUuid('role_id')->constrained()->restrictOnDelete();
            $table->timestampTz('granted_at');
            $table->primary(['user_id', 'role_id']);
            $table->index(['role_id', 'user_id']);
        });

        foreach (['donor', 'beneficiary', 'shopkeeper', 'administrator'] as $code) {
            DB::table('roles')->insert(['id' => (string) Str::uuid(), 'code' => $code]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('user_roles');
        Schema::dropIfExists('roles');
    }
};
