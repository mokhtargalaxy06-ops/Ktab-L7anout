<?php

namespace App\Services;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class DonationAllocator
{
    public function __construct(private readonly PublicDiscovery $discovery, private readonly DonationLedger $ledger) {}

    public function run(object $payment, object $donation, string $operation, bool $frozen = false): void
    {
        $key = $operation.':'.$payment->id;
        if (DB::table('allocation_runs')->where('operation_key', $key)->exists()) {
            return;
        }
        $available = $this->ledger->balance($payment)['available'];
        if ($available <= 0) {
            return;
        }

        $specific = $donation->scope === 'specific';
        $specificDecided = $specific && DB::table('allocation_runs')->where('payment_transaction_id', $payment->id)->exists();
        $expired = ! $specific && Carbon::parse($payment->confirmed_at, 'UTC')->utc()
            ->addDays(config('funding.pool_holding_days'))->lte(now());
        $query = $this->discovery->catalog($specific ? [] : ['urgent' => '1'])->where('remaining_minor', '>', 0);
        $candidates = match (true) {
            $expired => collect(),
            $frozen => collect(),
            $specificDecided => collect(),
            $specific => $query->where('internal_id', $donation->debt_case_id)->get(),
            default => $this->discovery->ordered($query, true)->limit(100)->get(),
        };
        if ($frozen && ! $expired) {
            return;
        }

        $run = (string) Str::uuid7();
        DB::table('allocation_runs')->insert([
            'id' => $run, 'payment_transaction_id' => $payment->id, 'operation_key' => $key,
            'policy_snapshot' => json_encode($this->discovery->policy() + [
                'decision_date' => now('Africa/Casablanca')->toDateString(),
                'scope' => $donation->scope, 'funding_available_minor' => $available,
                'candidates' => $candidates->map(fn ($case) => [
                    'id' => $case->internal_id, 'score' => $case->urgency_score, 'capacity' => $case->remaining_minor,
                    'vulnerability' => $case->vulnerability_grade, 'access_risk' => $case->access_risk_grade,
                    'debt_date' => $case->debt_date, 'published_at' => $case->published_at,
                ])->all(),
            ], JSON_THROW_ON_ERROR),
            'created_at' => now(),
        ]);

        foreach ($candidates as $rank => $case) {
            $amount = min($available, (int) $case->remaining_minor);
            if ($amount === 0) {
                break;
            }
            $id = (string) Str::uuid7();
            DB::table('donation_allocations')->insert([
                'id' => $id, 'allocation_run_id' => $run, 'payment_transaction_id' => $payment->id,
                'debt_case_id' => $case->internal_id, 'amount_minor' => $amount,
                'rank' => $rank + 1, 'score' => $specific ? null : $case->urgency_score, 'created_at' => now(),
            ]);
            $this->ledger->event($payment->id, 'allocation_created', $amount, "allocate:$id", ['donation_allocation_id' => $id]);
            $this->ledger->projectCase($case->internal_id);
            $available -= $amount;
        }

        if (($specific || $expired) && $available > 0) {
            $this->ledger->reserveReturn($payment, $available, $expired ? 'pool_expiry' : 'specific_excess', "excess:$run");
        }
        $this->ledger->projectDonation($payment);
    }

    public function waiting(string $operation, bool $frozen): void
    {
        $payments = DB::table('payment_transactions as payments')->join('donations as donations', 'donations.id', '=', 'payments.donation_id')
            ->where('payments.status', 'confirmed')->whereIn('donations.allocation_status', ['waiting', 'partial'])
            ->orderBy('payments.confirmed_at')->orderBy('payments.id')
            ->select(['payments.*', 'donations.scope', 'donations.debt_case_id'])
            ->cursor();

        $processed = 0;
        foreach ($payments as $payment) {
            if ($this->ledger->balance($payment)['available'] <= 0) {
                continue;
            }
            $this->run($payment, $payment, $operation, $frozen);
            if (++$processed >= 100 || (! $frozen && $payment->scope === 'urgent_pool' && $this->ledger->balance($payment)['available'] > 0)) {
                break;
            }
        }
    }
}
