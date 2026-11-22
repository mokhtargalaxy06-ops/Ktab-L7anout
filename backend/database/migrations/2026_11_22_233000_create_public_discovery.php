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
        Schema::table('debt_cases', function (Blueprint $table) {
            $table->uuid('public_reference')->nullable()->unique();
            $table->enum('public_story', ['essentials', 'income', 'dependants'])->default('essentials');
            $table->unsignedSmallInteger('vulnerability_grade')->nullable();
            $table->unsignedSmallInteger('access_risk_grade')->nullable();
        });
        DB::table('debt_cases')->select('id')->chunkById(250, function ($cases) {
            foreach ($cases as $case) {
                DB::table('debt_cases')->where('id', $case->id)->update(['public_reference' => (string) Str::uuid()]);
            }
        });

        Schema::create('case_funding_reports', function (Blueprint $table) {
            $table->foreignUuid('debt_case_id')->primary()->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('allocated_minor')->default(0);
            $table->unsignedBigInteger('settled_minor')->default(0);
            $table->timestampTz('updated_at')->nullable();
        });
        Schema::create('funding_receipt_reports', function (Blueprint $table) {
            $table->uuid('receipt_id')->primary();
            $table->unsignedBigInteger('received_minor');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('funding_receipt_reports');
        Schema::dropIfExists('case_funding_reports');
        Schema::table('debt_cases', function (Blueprint $table) {
            $table->dropUnique(['public_reference']);
            $table->dropColumn(['public_reference', 'public_story', 'vulnerability_grade', 'access_risk_grade']);
        });
    }
};
