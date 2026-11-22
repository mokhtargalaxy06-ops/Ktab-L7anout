<?php

namespace App\Services;

use App\Models\User;
use App\Policies\SettlementPolicy;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ShopSettlement
{
    public function __construct(private readonly DonationLedger $ledger, private readonly PublicDiscovery $discovery,
        private readonly SettlementPolicy $policy, private readonly SettlementReports $reports) {}

    public function act(User $actor, string $caseId, ?string $paymentId, array $input, bool $admin): void
    {
        abort_unless(SimulatedProvider::enabled(), 404);
        FinancialLock::run(function ($control) use ($actor, $caseId, $paymentId, $input, $admin) {
            $case = DB::table('debt_cases')->find($caseId);
            abort_unless($case, 404);
            abort_unless($admin ? $this->policy->administer($actor) : $this->policy->respond($actor, $case->shop_id), 403);
            abort_if($admin && $this->policy->ownsShop($actor, $case->shop_id), 403);
            $action = $input['action'];
            $key = $input['operation_key'];
            $reason = $input['reason'] ?? null;
            $hash = hash('sha256', json_encode([$caseId, $paymentId, $action, $reason], JSON_THROW_ON_ERROR));
            $prior = DB::table('settlement_actions')->where('actor_id', $actor->id)->where('operation_key', $key)->first();
            if ($prior) {
                abort_unless(hash_equals($prior->request_hash, $hash), 409);

                return;
            }

            if ($action === 'initiate') {
                $previous = null;
                abort_if($paymentId !== null || $control->frozen, 409);
                $paymentId = $this->initiate($case, $actor);
            } else {
                $payment = DB::table('shop_payments')->where('debt_case_id', $caseId)->find($paymentId);
                abort_unless($payment, 404);
                abort_if(! $admin && in_array($actor->id, [$payment->initiator_id, $payment->approver_id], true), 403);
                $previous = $payment->status;
                $this->transition($payment, $case, $actor, $action, $control->frozen);
            }
            DB::table('settlement_actions')->insert([
                'id' => (string) Str::uuid7(), 'shop_payment_id' => $paymentId, 'actor_id' => $actor->id,
                'action' => $action, 'operation_key' => $key, 'request_hash' => $hash, 'reason' => $reason, 'created_at' => now(),
            ]);
            if ($admin) {
                DB::table('audit_logs')->insert([
                    'id' => (string) Str::uuid7(), 'actor_id' => $actor->id, 'actor_kind' => 'user',
                    'action' => 'settlement.'.$action, 'target_type' => 'shop_payment', 'target_id' => $paymentId,
                    'correlation_id' => (string) Str::uuid(), 'created_at' => now(),
                    'metadata' => json_encode(['from' => $previous, 'to' => DB::table('shop_payments')->where('id', $paymentId)->value('status'),
                        'reason' => $reason], JSON_THROW_ON_ERROR),
                ]);
            }
        });
    }

    private function initiate(object $case, User $actor): string
    {
        $this->checkEligibility($case);
        abort_if(DB::table('shop_payments')->where('debt_case_id', $case->id)->whereNotIn('status', ['failed', 'canceled'])->exists(), 409);
        $allocations = $this->ledger->allocations(case: $case->id)->where('net_minor', '>', 0)->orderBy('id')->get();
        $amount = (int) $allocations->sum('net_minor');
        abort_unless($amount > 0 && $amount === (int) $case->approved_minor, 409);
        $id = (string) Str::uuid7();
        DB::table('shop_payments')->insert([
            'id' => $id, 'debt_case_id' => $case->id, 'initiator_id' => $actor->id,
            'operation_key' => "settlement:$id", 'destination_reference' => "simulation:$case->shop_id:$case->verified_shop_revision",
            'amount_minor' => $amount, 'status' => 'requested', 'created_at' => now(), 'updated_at' => now(),
        ]);
        foreach ($allocations as $allocation) {
            DB::table('shop_payment_lines')->insert([
                'shop_payment_id' => $id, 'donation_allocation_id' => $allocation->id, 'amount_minor' => $allocation->net_minor,
            ]);
        }
        $this->journal($id, 'payout_reserved');

        return $id;
    }

    private function checkEligibility(object $case): void
    {
        abort_unless($this->discovery->catalog()->where('internal_id', $case->id)->exists(), 409);
    }

    private function transition(object $payment, object $case, User $actor, string $action, bool $frozen): void
    {
        $updates = match ($action) {
            'submit' => $this->submit($payment, $case, $actor, $frozen),
            'transfer' => $this->transfer($payment),
            'fail' => $this->fail($payment),
            'confirm', 'dispute' => $this->respond($payment, $action),
            'reconcile' => $this->reconcile($payment, $case, $actor),
            'recover' => $this->recover($payment, $actor, $frozen),
        };
        if ($updates !== []) {
            DB::table('shop_payments')->where('id', $payment->id)->update($updates + ['updated_at' => now()]);
        }
    }

    private function submit(object $payment, object $case, User $actor, bool $frozen): array
    {
        abort_if($payment->status !== 'requested' || $frozen, 409);
        abort_if($payment->initiator_id === $actor->id, 403);
        $this->checkEligibility($case);
        abort_unless($payment->destination_reference === "simulation:$case->shop_id:$case->verified_shop_revision", 409);

        return ['status' => 'submitted', 'approver_id' => $actor->id];
    }

    private function transfer(object $payment): array
    {
        abort_unless($payment->status === 'submitted', 409);
        $reference = 'SIM-PAYOUT-'.$payment->id;
        DB::table('settlement_transfers')->insert([
            'shop_payment_id' => $payment->id, 'reference' => $reference, 'created_at' => now(),
            'evidence' => json_encode(['source' => 'local_simulator', 'reference' => $reference,
                'amount_minor' => (string) $payment->amount_minor, 'currency' => 'MAD',
                'destination' => $payment->destination_reference], JSON_THROW_ON_ERROR),
        ]);
        $this->journal($payment->id, 'payout_transferred');

        return ['status' => 'evidence_pending'];
    }

    private function fail(object $payment): array
    {
        abort_unless(in_array($payment->status, ['requested', 'submitted'], true), 409);
        $this->journal($payment->id, 'payout_released');

        return ['status' => 'failed'];
    }

    public function cancelUnsubmitted(object $receipt): void
    {
        $payments = DB::table('shop_payments')->where('status', 'requested')
            ->whereExists(fn ($query) => $query->selectRaw('1')->from('shop_payment_lines as line')
                ->join('donation_allocations as allocation', 'allocation.id', '=', 'line.donation_allocation_id')
                ->whereColumn('line.shop_payment_id', 'shop_payments.id')->where('allocation.payment_transaction_id', $receipt->id))
            ->orderBy('id')->pluck('id');
        foreach ($payments as $payment) {
            $this->journal($payment, 'payout_released');
            DB::table('shop_payments')->where('id', $payment)->update(['status' => 'canceled', 'updated_at' => now()]);
        }
    }

    private function respond(object $payment, string $action): array
    {
        abort_unless($payment->status === 'evidence_pending' && DB::table('settlement_transfers')->where('shop_payment_id', $payment->id)->exists(), 409);
        abort_if($this->response($payment->id) === $action, 409);

        return [];
    }

    private function reconcile(object $payment, object $case, User $actor): array
    {
        abort_unless($payment->status === 'evidence_pending' && $this->response($payment->id) === 'confirm', 409);
        abort_if($payment->initiator_id === $actor->id, 403);
        $transfer = DB::table('settlement_transfers')->where('shop_payment_id', $payment->id)->first();
        $evidence = $transfer ? json_decode($transfer->evidence, true, flags: JSON_THROW_ON_ERROR) : [];
        abort_unless(($evidence['amount_minor'] ?? null) === (string) $payment->amount_minor
            && ($evidence['reference'] ?? null) === $transfer->reference
            && ($evidence['source'] ?? null) === 'local_simulator'
            && ($evidence['currency'] ?? null) === 'MAD'
            && ($evidence['destination'] ?? null) === $payment->destination_reference, 409);
        $this->journal($payment->id, 'payout_evidenced');
        abort_unless(DB::table('case_funding_reports')->where('debt_case_id', $case->id)->where('simulated', true)
            ->where('settled_minor', 0)->where('allocated_minor', $payment->amount_minor)
            ->update(['settled_minor' => $payment->amount_minor, 'updated_at' => now()]) === 1, 409);

        return ['status' => 'paid'];
    }

    private function response(string $payment): ?string
    {
        return DB::table('settlement_actions')->where('shop_payment_id', $payment)
            ->whereIn('action', ['confirm', 'dispute'])->orderByDesc('id')->value('action');
    }

    private function recover(object $payment, User $actor, bool $frozen): array
    {
        abort_unless($frozen && $payment->status === 'failed', 409);
        abort_if($payment->initiator_id === $actor->id, 403);
        $receipts = DB::table('shop_payment_lines as line')
            ->join('donation_allocations as allocation', 'allocation.id', '=', 'line.donation_allocation_id')
            ->join('payment_transactions as receipt', 'receipt.id', '=', 'allocation.payment_transaction_id')
            ->where('line.shop_payment_id', $payment->id)->select('receipt.*')->distinct()->get();
        foreach ($receipts as $receipt) {
            $needed = max(0, -$this->ledger->balance($receipt)['available']);
            $this->ledger->releaseAllocations($receipt, $needed, 'recovery:'.Str::uuid7());
            $this->ledger->projectDonation($receipt);
        }
        if (! DB::query()->fromSub($this->reports->reconciliation(), 'balance')->where('available_minor', '<', 0)->exists()) {
            DB::table('financial_control')->where('id', 1)->update(['frozen' => false]);
        }

        return [];
    }

    private function journal(string $payment, string $event): void
    {
        $lines = DB::table('shop_payment_lines as line')->join('donation_allocations as allocation', 'allocation.id', '=', 'line.donation_allocation_id')
            ->where('line.shop_payment_id', $payment)->get(['allocation.payment_transaction_id', 'line.donation_allocation_id', 'line.amount_minor']);
        foreach ($lines as $line) {
            $this->ledger->event($line->payment_transaction_id, $event, (int) $line->amount_minor,
                "$event:$payment:$line->donation_allocation_id", ['shop_payment_id' => $payment, 'donation_allocation_id' => $line->donation_allocation_id]);
        }
    }
}
