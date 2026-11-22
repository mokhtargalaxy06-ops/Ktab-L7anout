<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class FundingService
{
    public function __construct(
        private readonly PublicDiscovery $discovery,
        private readonly DonationAllocator $allocator,
        private readonly DonationLedger $ledger,
        private readonly SimulatedProvider $provider,
    ) {}

    public function create(User $donor, array $data, string $key): object
    {
        return FinancialLock::run(function () use ($donor, $data, $key) {
            $amount = (int) $data['amount_minor'];
            $reference = $data['case_reference'] ?? null;
            $hash = hash('sha256', json_encode([$data['scope'], $reference, $amount, $data['policy_version']], JSON_THROW_ON_ERROR));
            $existing = DB::table('donations')->where('donor_id', $donor->id)->where('idempotency_key', $key)->first();
            if ($existing) {
                abort_unless(hash_equals($existing->request_hash, $hash), 409);

                return $existing;
            }
            abort_unless($data['policy_version'] === config('funding.policy_version'), 409);

            $case = null;
            if ($data['scope'] === 'specific') {
                $case = $this->discovery->catalog()->where('id', $reference)->first();
                abort_unless($case && $case->remaining_minor >= $amount, 409);
            }
            $id = (string) Str::uuid7();
            DB::table('donations')->insert([
                'id' => $id, 'donor_id' => $donor->id, 'debt_case_id' => $case?->internal_id,
                'scope' => $data['scope'], 'amount_minor' => $amount, 'policy_version' => $data['policy_version'],
                'idempotency_key' => $key, 'request_hash' => $hash, 'created_at' => now(), 'updated_at' => now(),
            ]);
            $payment = (string) Str::uuid7();
            DB::table('payment_transactions')->insert([
                'id' => $payment, 'donation_id' => $id, 'provider_reference' => 'sim:'.$payment,
                'amount_minor' => $amount, 'created_at' => now(), 'updated_at' => now(),
            ]);

            return DB::table('donations')->find($id);
        });
    }

    public function simulate(object $donation, string $outcome): void
    {
        FinancialLock::run(function ($control) use ($donation, $outcome) {
            $payment = DB::table('payment_transactions')->where('donation_id', $donation->id)->lockForUpdate()->first();
            if ($payment->status !== 'pending') {
                $expected = $outcome === 'success' ? 'confirmed' : $outcome;
                abort_unless($payment->status === $expected, 409);

                return;
            }
            $this->record($this->provider->event($payment, $outcome), $payment, $control->frozen);
        });
    }

    public function receive(array $event): void
    {
        FinancialLock::run(function ($control) use ($event) {
            $payment = DB::table('payment_transactions')->where('provider_reference', $event['reference'])->lockForUpdate()->first();
            abort_unless($payment, 404);
            $this->record($event, $payment, $control->frozen);
        });
    }

    private function record(array $event, object $payment, bool $frozen): void
    {
        $hash = hash('sha256', json_encode([
            $event['reference'], $event['currency'], $event['amount_minor'], $event['outcome'],
        ], JSON_THROW_ON_ERROR));
        $seen = DB::table('webhook_events')->where('event_id', $event['event_id'])->first();
        if ($seen) {
            abort_unless(hash_equals($seen->payload_hash, $hash), 409);

            return;
        }
        abort_unless($event['amount_minor'] === (string) $payment->amount_minor && $event['currency'] === 'MAD', 422);
        DB::table('webhook_events')->insert([
            'event_id' => $event['event_id'], 'payment_transaction_id' => $payment->id,
            'payload_hash' => $hash, 'outcome' => $event['outcome'], 'created_at' => now(),
        ]);
        if ($payment->status === 'confirmed') {
            return;
        }
        if ($event['outcome'] !== 'success') {
            if ($payment->status === 'pending') {
                DB::table('payment_transactions')->where('id', $payment->id)->update(['status' => $event['outcome'], 'updated_at' => now()]);
            }

            return;
        }

        // A signed success may arrive after a checkout failed or was cancelled.
        DB::table('payment_transactions')->where('id', $payment->id)
            ->update(['status' => 'confirmed', 'confirmed_at' => now(), 'updated_at' => now()]);
        $this->ledger->event($payment->id, 'receipt_confirmed', (int) $payment->amount_minor, "receipt:$payment->id");
        DB::table('funding_receipt_reports')->insert([
            'receipt_id' => $payment->id, 'received_minor' => $payment->amount_minor, 'simulated' => true,
        ]);
        $payment = DB::table('payment_transactions')->find($payment->id);
        $donation = DB::table('donations')->find($payment->donation_id);
        if ($donation->scope === 'specific') {
            $this->allocator->run($payment, $donation, 'capture', $frozen);
        } else {
            $this->allocator->waiting("capture:$payment->id", $frozen);
        }
    }

    public function tick(): void
    {
        FinancialLock::run(function ($control) {
            $this->allocator->waiting('tick:'.now()->utc()->format('YmdHi'), $control->frozen);
            if ($control->frozen) {
                return;
            }
            foreach (DB::table('refunds')->where('status', 'requested')->orderBy('id')->limit(100)->get() as $return) {
                $this->ledger->confirmReturn($return);
                $this->ledger->projectDonation(DB::table('payment_transactions')->find($return->payment_transaction_id));
            }
        });
    }
}
