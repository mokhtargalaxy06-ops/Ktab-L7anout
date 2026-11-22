<?php

namespace App\Services;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class DonationLedger
{
    public function event(string $payment, string $type, int $amount, string $key, array $references = []): void
    {
        DB::table('financial_events')->insert($references + [
            'id' => (string) Str::uuid7(), 'payment_transaction_id' => $payment,
            'type' => $type, 'amount_minor' => $amount, 'operation_key' => $key, 'created_at' => now(),
        ]);
    }

    public function allocations(?string $payment = null, ?string $case = null): Builder
    {
        $query = DB::table('donation_allocations as allocation')
            ->when($payment, fn ($query) => $query->where('allocation.payment_transaction_id', $payment))
            ->when($case, fn ($query) => $query->where('allocation.debt_case_id', $case))
            ->select('allocation.*')->selectRaw("allocation.amount_minor - COALESCE((
                SELECT SUM(amount_minor) FROM financial_events
                WHERE donation_allocation_id = allocation.id AND type = 'allocation_released'
            ), 0) AS net_minor");

        return DB::query()->fromSub($query, 'amounts');
    }

    public function balance(object $payment): array
    {
        $net = (int) $this->allocations($payment->id)->sum('net_minor');
        $returns = DB::table('refunds')->where('payment_transaction_id', $payment->id);
        $confirmed = (int) (clone $returns)->where('status', 'confirmed')->sum('amount_minor');
        $reserved = (int) $returns->where('status', 'requested')->sum('amount_minor');

        return [
            'allocated' => $net,
            'returned' => $confirmed,
            'reserved' => $reserved,
            'available' => (int) $payment->amount_minor - $confirmed - $reserved - $net,
        ];
    }

    public function reserveReturn(object $payment, int $amount, string $reason, string $key): void
    {
        if ($amount === 0) {
            return;
        }

        abort_unless($amount > 0 && $amount <= $this->balance($payment)['available'], 409);
        $id = (string) Str::uuid7();
        DB::table('refunds')->insert([
            'id' => $id, 'payment_transaction_id' => $payment->id, 'kind' => 'refund',
            'status' => 'requested', 'amount_minor' => $amount, 'reason' => $reason,
            'operation_key' => $key, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $this->event($payment->id, 'return_reserved', $amount, "reserve:$id", ['refund_id' => $id]);
    }

    public function confirmReturn(object $return): void
    {
        DB::table('refunds')->where('id', $return->id)->where('status', 'requested')
            ->update(['status' => 'confirmed', 'updated_at' => now()]);
        $this->event($return->payment_transaction_id, 'return_confirmed', (int) $return->amount_minor,
            "return:$return->id", ['refund_id' => $return->id]);
    }

    public function projectCase(string $case): void
    {
        $prior = DB::table('case_funding_reports')->where('debt_case_id', $case)->first();
        abort_if($prior && ! $prior->simulated, 409);
        $allocated = (int) $this->allocations(case: $case)->sum('net_minor');
        $settled = (int) ($prior->settled_minor ?? 0);
        abort_unless($allocated >= $settled, 409);
        DB::table('case_funding_reports')->updateOrInsert(['debt_case_id' => $case], [
            'allocated_minor' => $allocated, 'settled_minor' => $settled, 'updated_at' => now(), 'simulated' => true,
        ]);
    }

    public function projectDonation(object $payment): void
    {
        $balance = $this->balance($payment);
        $status = match (true) {
            $balance['allocated'] === 0 && $balance['reserved'] === 0 && $balance['available'] === 0 && $balance['returned'] > 0 => 'returned',
            $balance['allocated'] > 0 && ($balance['available'] > 0 || $balance['returned'] > 0 || $balance['reserved'] > 0) => 'partial',
            $balance['allocated'] > 0 => 'allocated',
            default => 'waiting',
        };
        DB::table('donations')->where('id', $payment->donation_id)->update(['allocation_status' => $status, 'updated_at' => now()]);
    }
}
