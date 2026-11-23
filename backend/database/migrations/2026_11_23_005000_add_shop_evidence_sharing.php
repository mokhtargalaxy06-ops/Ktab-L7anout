<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shop_documents', function (Blueprint $table) {
            $table->foreignUuid('redacted_from_id')->nullable()->constrained('shop_documents')->restrictOnDelete();
            $table->timestampTz('shared_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('shop_documents', fn (Blueprint $table) => $table->dropForeign(['redacted_from_id']));
        Schema::table('shop_documents', fn (Blueprint $table) => $table->dropColumn(['redacted_from_id', 'shared_at']));
    }
};
