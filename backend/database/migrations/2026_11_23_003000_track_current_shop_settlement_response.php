<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('CREATE UNIQUE INDEX settlement_action_payment_identity ON settlement_actions (id, shop_payment_id)');
        Schema::create('settlement_response_heads', function (Blueprint $table) {
            $table->foreignUuid('shop_payment_id')->primary()->constrained()->restrictOnDelete();
            $table->uuid('settlement_action_id');
            $table->foreign(['settlement_action_id', 'shop_payment_id'])->references(['id', 'shop_payment_id'])
                ->on('settlement_actions')->restrictOnDelete();
        });

        $responses = DB::table('settlement_actions as action')->join('shop_payments as payment', 'payment.id', '=', 'action.shop_payment_id')
            ->whereIn('action.action', ['confirm', 'dispute'])
            ->select('action.shop_payment_id', 'action.id as settlement_action_id')
            ->selectRaw("ROW_NUMBER() OVER (PARTITION BY action.shop_payment_id ORDER BY action.created_at DESC,
                CASE WHEN payment.status = 'paid' AND action.action = 'confirm' THEN 0
                     WHEN payment.status <> 'paid' AND action.action = 'dispute' THEN 0
                     ELSE 1 END, action.id DESC) AS position");
        DB::table('settlement_response_heads')->insertUsing(['shop_payment_id', 'settlement_action_id'],
            DB::query()->fromSub($responses, 'responses')->where('position', 1)->select('shop_payment_id', 'settlement_action_id'));
    }

    public function down(): void
    {
        Schema::dropIfExists('settlement_response_heads');
        DB::statement('DROP INDEX settlement_action_payment_identity');
    }
};
