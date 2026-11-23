<?php

namespace App\Console\Commands;

use App\Services\SettlementReports;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class CheckOperations extends Command
{
    protected $signature = 'ops:check';

    protected $description = 'Print aggregate operational metrics for monitoring';

    public function handle(SettlementReports $reports): int
    {
        $metrics = [
            'failed_jobs' => DB::table('failed_jobs')->count(),
            'queued_jobs_over_5m' => DB::table('jobs')->where('created_at', '<=', now()->subMinutes(5)->timestamp)->count(),
            'evidence_quarantined_over_30m' => DB::table('documents')->where('status', 'quarantined')
                ->where('created_at', '<=', now()->subMinutes(30))->count(),
            'shop_evidence_quarantined_over_30m' => DB::table('shop_documents')->where('status', 'quarantined')
                ->where('created_at', '<=', now()->subMinutes(30))->count(),
            'funding_frozen' => (bool) DB::table('financial_control')->where('id', 1)->value('frozen'),
            'negative_receipt_balances' => DB::query()->fromSub($reports->reconciliation(), 'balances')
                ->where('available_minor', '<', 0)->count(),
            'payouts_unresolved_over_1h' => DB::table('shop_payments')->whereIn('status', ['submitted', 'unknown', 'evidence_pending'])
                ->where('updated_at', '<=', now()->subHour())->count(),
            'refunds_waiting_over_1h' => DB::table('refunds')->where('status', 'requested')
                ->where('created_at', '<=', now()->subHour())->count(),
        ];
        $this->line(json_encode($metrics, JSON_THROW_ON_ERROR));

        return self::SUCCESS;
    }
}
