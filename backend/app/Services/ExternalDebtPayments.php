<?php

namespace App\Services;

use App\Models\User;
use App\Policies\SettlementPolicy;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

class ExternalDebtPayments
{
    public function __construct(private SettlementPolicy $policy, private DonationLedger $ledger) {}

    public function query(): Builder
    {
        return DB::table('donations as d')->join('debt_cases as c', 'c.id', '=', 'd.debt_case_id')
            ->join('shops as s', 's.id', '=', 'c.shop_id')
            ->join('payment_transactions as p', 'p.donation_id', '=', 'd.id')
            ->leftJoin('external_debt_payments as e', 'e.donation_id', '=', 'd.id')
            ->where('d.whatsapp_pledge', true);
    }

    public function declare(User $actor, string $id, array $input): void
    {
        $key = null;
        $filename = Str::uuid().'.'.$input['evidence']->extension();
        try {
            FinancialLock::run(function ($control) use ($actor, $id, $input, $filename, &$key) {
                $row = $this->query()->where('d.id', $id)->first([
                    'c.id as case_id', 'c.shop_id', 'c.status as case_status', 'c.approved_minor', 'c.flagged',
                    'c.verified_shop_revision', 's.revision as shop_revision', 's.status as shop_status',
                    'p.status', 'd.amount_minor', 'e.status as review_status',
                ]);
                abort_unless($row && $this->policy->respond($actor, $row->shop_id), 404);
                abort_unless($row->shop_status === 'verified' && $row->status === 'pending' && $row->review_status === null
                    && (int) $input['amount_minor'] <= (int) $row->amount_minor, 409);
                $reference = mb_strtolower(trim($input['reference']));
                abort_if(DB::table('external_debt_payments')->where('shop_id', $row->shop_id)->where('reference', $reference)->exists(), 409);
                $this->credit($row, (int) $input['amount_minor'], $control->frozen);
                $key = $input['evidence']->storeAs('external-debt-payments', $filename, 'local');
                abort_unless($key, 503);
                DB::table('external_debt_payments')->insert([
                    'donation_id' => $id, 'declared_by' => $actor->id, 'amount_minor' => $input['amount_minor'],
                    'reference' => $reference, 'shop_id' => $row->shop_id, 'evidence_key' => $key, 'created_at' => now(),
                    'status' => 'approved',
                ]);
                $this->audit($actor, $id, 'confirmed_by_shop', null, 'approved', ['amount_minor' => $input['amount_minor']]);
            });
        } catch (Throwable $error) {
            if ($key) {
                Storage::disk('local')->delete($key);
            }
            throw $error;
        }
    }

    public function review(User $actor, string $id, array $input): void
    {
        abort_unless($this->policy->administer($actor), 403);
        FinancialLock::run(function ($control) use ($actor, $id, $input) {
            $row = $this->query()->where('d.id', $id)->first(['c.id as case_id', 'c.shop_id', 'c.status as case_status',
                'c.approved_minor', 'c.flagged', 'c.verified_shop_revision', 's.revision as shop_revision',
                's.status as shop_status', 'p.status as payment_status', 'e.status', 'e.declared_by',
                'e.amount_minor', 'e.evidence_key']);
            abort_unless($row && $row->status !== null, 404);
            abort_if($row->declared_by === $actor->id || $this->policy->ownsShop($actor, $row->shop_id), 403);
            abort_unless($row->status === 'pending', 409);
            if ($input['decision'] === 'approved') {
                abort_unless($row->payment_status === 'pending'
                    && Storage::disk('local')->exists($row->evidence_key), 409);
                $this->credit($row, (int) $row->amount_minor, $control->frozen);
            }
            DB::table('external_debt_payments')->where('donation_id', $id)->update([
                'reviewed_by' => $actor->id, 'status' => $input['decision'], 'reason' => $input['reason'], 'reviewed_at' => now(),
            ]);
            $this->audit($actor, $id, $input['decision'], 'pending', $input['decision'], ['reason' => $input['reason']]);
        });
    }

    private function credit(object $row, int $amount, bool $frozen): void
    {
        $remainingCap = (int) $row->approved_minor - $amount;
        $allocated = (int) $this->ledger->allocations(case: $row->case_id)->sum('net_minor');
        abort_unless(! $frozen && $row->case_status === 'published' && ! $row->flagged && $row->shop_status === 'verified'
            && $row->verified_shop_revision === $row->shop_revision && $remainingCap >= $allocated, 409);
        DB::table('debt_cases')->where('id', $row->case_id)->update(['approved_minor' => $remainingCap, 'updated_at' => now()]);
        if ($allocated > 0 && $allocated === $remainingCap) {
            DB::table('settlement_queue')->insertOrIgnore(['debt_case_id' => $row->case_id, 'created_at' => now()]);
        }
    }

    private function audit(User $actor, string $id, string $action, ?string $from, string $to, array $metadata): void
    {
        DB::table('audit_logs')->insert([
            'id' => (string) Str::uuid7(), 'actor_id' => $actor->id, 'actor_kind' => 'user',
            'action' => 'external_debt_payment.'.$action, 'target_type' => 'donation', 'target_id' => $id,
            'correlation_id' => (string) Str::uuid(), 'created_at' => now(),
            'metadata' => json_encode(['from' => $from, 'to' => $to] + $metadata, JSON_THROW_ON_ERROR),
        ]);
    }
}
