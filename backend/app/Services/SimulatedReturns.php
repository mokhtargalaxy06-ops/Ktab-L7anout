<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class SimulatedReturns
{
    public function __construct(private readonly DonationLedger $ledger) {}

    public function process(User $operator, object $donation, string $kind, int $amount, string $operation): void
    {
        FinancialLock::run(function ($control) use ($operator, $donation, $kind, $amount, $operation) {
            $payment = DB::table('payment_transactions')->where('donation_id', $donation->id)->lockForUpdate()->first();
            abort_unless($payment->status === 'confirmed', 409);
            $key = "manual:$payment->id:$operation";
            $existing = DB::table('refunds')->where('operation_key', $key)->first();
            if ($existing) {
                abort_unless($existing->kind === $kind && (int) $existing->amount_minor === $amount, 409);

                return;
            }
            $balance = $this->ledger->balance($payment);
            abort_unless($amount <= (int) $payment->amount_minor - $balance['returned'], 409);

            if ($kind === 'reversal') {
                $this->releaseHolds($payment, max(0, $amount - $balance['available']), $key);
                $balance = $this->ledger->balance($payment);
            } else {
                abort_if($control->frozen, 409);
            }
            $deficit = $this->releaseAllocations($payment, max(0, $amount - $balance['available']), $key);

            if ($kind === 'refund') {
                abort_if($deficit > 0, 409);
                $this->ledger->reserveReturn($payment, $amount, 'simulated_refund', $key);
                $this->ledger->confirmReturn(DB::table('refunds')->where('operation_key', $key)->first());
            } else {
                $id = (string) Str::uuid7();
                DB::table('refunds')->insert([
                    'id' => $id, 'payment_transaction_id' => $payment->id, 'kind' => 'reversal', 'status' => 'confirmed',
                    'amount_minor' => $amount, 'reason' => 'simulated_reversal', 'operation_key' => $key,
                    'created_at' => now(), 'updated_at' => now(),
                ]);
                $this->ledger->event($payment->id, 'return_confirmed', $amount, "return:$id", ['refund_id' => $id]);
                if ($deficit > 0) {
                    DB::table('financial_control')->where('id', 1)->update(['frozen' => true]);
                }
            }

            $this->ledger->projectDonation($payment);
            DB::table('audit_logs')->insert([
                'id' => (string) Str::uuid7(), 'actor_id' => $operator->id, 'actor_kind' => 'user',
                'action' => 'funding.simulated_'.$kind, 'target_type' => 'donation', 'target_id' => $donation->id,
                'correlation_id' => (string) Str::uuid(), 'metadata' => json_encode(['amount_minor' => $amount], JSON_THROW_ON_ERROR),
                'created_at' => now(),
            ]);
        });
    }

    private function releaseHolds(object $payment, int $needed, string $operation): void
    {
        if ($needed === 0) {
            return;
        }

        foreach (DB::table('refunds')->where('payment_transaction_id', $payment->id)->where('status', 'requested')->orderBy('id')->get() as $return) {
            DB::table('refunds')->where('id', $return->id)->update(['status' => 'canceled', 'updated_at' => now()]);
            $amount = (int) $return->amount_minor;
            $this->ledger->event($payment->id, 'return_released', $amount, "release:$return->id", ['refund_id' => $return->id]);
            $released = min($amount, $needed);
            $this->ledger->reserveReturn($payment, $amount - $released, $return->reason, "$operation:remainder:$return->id");
            $needed -= $released;
            if ($needed === 0) {
                break;
            }
        }
    }

    private function releaseAllocations(object $payment, int $needed, string $operation): int
    {
        if ($needed === 0) {
            return 0;
        }

        foreach ($this->ledger->allocations($payment->id)->where('net_minor', '>', 0)->orderByDesc('id')->get() as $allocation) {
            $held = (int) DB::table('shop_payment_lines as line')->join('shop_payments as payout', 'payout.id', '=', 'line.shop_payment_id')
                ->where('line.donation_allocation_id', $allocation->id)->whereNotIn('payout.status', ['canceled', 'failed'])
                ->sum('line.amount_minor');
            $available = max(0, (int) $allocation->net_minor - $held);
            $release = min($available, $needed);
            if ($release > 0) {
                $this->ledger->event($payment->id, 'allocation_released', $release, "$operation:$allocation->id", ['donation_allocation_id' => $allocation->id]);
                $this->ledger->projectCase($allocation->debt_case_id);
                $needed -= $release;
            }
            if ($needed === 0) {
                break;
            }
        }

        return $needed;
    }
}
