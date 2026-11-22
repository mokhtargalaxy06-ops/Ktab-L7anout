<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class SimulatedReturns
{
    public function __construct(private readonly DonationLedger $ledger, private readonly ShopSettlement $settlements) {}

    public function process(User $operator, object $donation, string $kind, int $amount, string $operation, string $reason): void
    {
        FinancialLock::run(function ($control) use ($operator, $donation, $kind, $amount, $operation, $reason) {
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
            $journalKey = hash('sha256', $key);
            $deficit = $this->ledger->releaseAllocations($payment, max(0, $amount - $balance['available']), $journalKey);
            if ($kind === 'reversal' && $deficit > 0) {
                $this->settlements->cancelUnsubmitted($payment);
                $deficit = $this->ledger->releaseAllocations($payment, $deficit, "$journalKey:after-hold");
            }

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
                'correlation_id' => (string) Str::uuid(), 'metadata' => json_encode(['amount_minor' => $amount, 'reason' => $reason,
                    'from' => 'confirmed', 'to' => $kind === 'refund' ? 'refund_confirmed' : 'reversal_confirmed'], JSON_THROW_ON_ERROR),
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
            $this->ledger->reserveReturn($payment, $amount - $released, $return->reason, hash('sha256', "$operation:remainder:$return->id"));
            $needed -= $released;
            if ($needed === 0) {
                break;
            }
        }
    }
}
