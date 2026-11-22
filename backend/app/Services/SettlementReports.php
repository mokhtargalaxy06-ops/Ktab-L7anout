<?php

namespace App\Services;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

class SettlementReports
{
    public function payments(): Builder
    {
        return DB::table('shop_payments as p')->leftJoin('settlement_transfers as t', 't.shop_payment_id', '=', 'p.id')
            ->leftJoin('settlement_response_heads as head', 'head.shop_payment_id', '=', 'p.id')
            ->leftJoin('settlement_actions as response', 'response.id', '=', 'head.settlement_action_id')
            ->select(['p.id', 'p.debt_case_id', 'p.status', 'p.amount_minor', 'p.updated_at', 't.reference'])
            ->addSelect('response.action as shop_response')
            ->selectRaw("CASE WHEN p.status = 'failed' THEN (
                SELECT decision.reason FROM settlement_actions AS decision
                WHERE decision.shop_payment_id = p.id AND decision.action = 'fail' LIMIT 1
            ) ELSE response.reason END AS reason");
    }

    public function reconciliation(): Builder
    {
        $journal = DB::table('financial_events')->groupBy('payment_transaction_id')->select('payment_transaction_id')
            ->selectRaw("SUM(CASE WHEN type = 'receipt_confirmed' THEN amount_minor ELSE 0 END) AS received_minor")
            ->selectRaw("SUM(CASE WHEN type = 'return_confirmed' THEN amount_minor ELSE 0 END) AS returned_minor")
            ->selectRaw("SUM(CASE WHEN type = 'payout_transferred' THEN amount_minor ELSE 0 END) AS transferred_minor")
            ->selectRaw("SUM(CASE WHEN type = 'allocation_created' THEN amount_minor WHEN type = 'allocation_released' THEN -amount_minor ELSE 0 END) AS allocated_minor")
            ->selectRaw("SUM(CASE WHEN type = 'return_reserved' THEN amount_minor
                WHEN type = 'return_released' OR (type = 'return_confirmed' AND refund_id IN
                    (SELECT id FROM refunds WHERE kind = 'refund')) THEN -amount_minor ELSE 0 END) AS held_minor");

        return DB::query()->fromSub($journal, 'receipt')->select('receipt.*')
            ->selectRaw('received_minor - returned_minor - allocated_minor - held_minor AS available_minor');
    }

    public function cases(?string $owner): Builder
    {
        return DB::table('debt_cases as c')->join('shops as s', 's.id', '=', 'c.shop_id')
            ->leftJoin('settlement_queue as q', 'q.debt_case_id', '=', 'c.id')
            ->leftJoin('case_funding_reports as f', fn ($join) => $join->on('f.debt_case_id', '=', 'c.id')->where('f.simulated', true))
            ->when($owner, fn ($query) => $query->whereExists(fn ($membership) => $membership->selectRaw('1')
                ->from('shop_memberships')->whereColumn('shop_id', 's.id')->where('user_id', $owner)
                ->where('role', 'owner')->where('status', 'active')))
            ->when(! $owner, fn ($query) => $query->whereNotNull('q.debt_case_id'))
            ->whereIn('c.status', ['approved', 'published'])->where('c.approved_minor', '>', 0)
            ->select(['c.id', 'c.public_reference', 'c.approved_minor', 's.name as shop_name', 'q.created_at as queued_at'])
            ->selectRaw('COALESCE(f.allocated_minor, 0) AS allocated_minor, COALESCE(f.settled_minor, 0) AS settled_minor');
    }

    public function transparency(): array
    {
        $simulated = SimulatedProvider::enabled();
        $events = DB::table('financial_events');
        $received = $simulated ? (clone $events)->where('type', 'receipt_confirmed')->sum('amount_minor') : 0;
        $transferred = $simulated ? (clone $events)->where('type', 'payout_transferred')->sum('amount_minor') : 0;
        $confirmed = $simulated ? DB::query()->fromSub($this->payments(), 'payment')
            ->where('shop_response', 'confirm')->sum('amount_minor') : 0;
        $allocated = $simulated ? DB::table('case_funding_reports')->where('simulated', true)->sum('allocated_minor') : 0;
        $funding = DB::table('debt_cases as c')
            ->join('case_funding_reports as f', 'f.debt_case_id', '=', 'c.id')
            ->join('cities as city', 'city.id', '=', 'c.city_id')
            ->where('f.simulated', true)->where('f.settled_minor', '>', 0)->whereRaw($simulated ? '1=1' : '1=0');
        $impact = 'COUNT(DISTINCT c.beneficiary_profile_id) AS families_helped, SUM(f.settled_minor) AS settled_minor,
            SUM(CASE WHEN f.settled_minor = c.approved_minor THEN 1 ELSE 0 END) AS debts_settled';
        $totals = (clone $funding)->selectRaw($impact)->first();
        $regions = $funding->join('regions as region', 'region.id', '=', 'city.region_id')
            ->groupBy('region.id', 'region.name_fr', 'region.name_ar')->orderBy('region.id')
            ->select(['region.name_fr', 'region.name_ar'])->selectRaw($impact)->get()->map(fn ($region) => [
                'name_fr' => $region->name_fr, 'name_ar' => $region->name_ar, 'families_helped' => (int) $region->families_helped,
                'debts_settled' => (int) $region->debts_settled, 'settled_minor' => (string) $region->settled_minor,
            ]);

        return [
            'simulated' => $simulated, 'received_minor' => (string) $received, 'allocated_minor' => (string) $allocated,
            'transferred_minor' => (string) $transferred, 'confirmed_minor' => (string) $confirmed,
            'settled_minor' => (string) ($totals->settled_minor ?? 0),
            'available_minor' => $simulated ? (string) DB::query()->fromSub($this->reconciliation(), 'balance')
                ->where('available_minor', '>', 0)->sum('available_minor') : '0',
            'families_helped' => (int) $totals->families_helped, 'debts_settled' => (int) $totals->debts_settled,
            'participating_shops' => DB::table('shops')->where('status', 'verified')->count(),
            'regions' => $regions,
        ];
    }
}
