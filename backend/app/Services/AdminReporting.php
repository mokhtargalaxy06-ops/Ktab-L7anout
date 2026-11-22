<?php

namespace App\Services;

use App\Models\DebtCase;
use App\Models\User;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

class AdminReporting
{
    public function overview(): array
    {
        $counts = [
            'users' => DB::table('users')->count(),
            'donors' => User::whereHas('roles', fn ($query) => $query->where('code', 'donor'))->count(),
            'beneficiaries' => User::whereHas('roles', fn ($query) => $query->where('code', 'beneficiary'))->count(),
            'shops' => DB::table('shops')->count(),
            'applications' => DebtCase::whereIn('status', ['submitted', 'pending_verification', 'under_review', 'disputed'])->count(),
            'verification' => DebtCase::where('status', 'pending_verification')->count(),
            'published' => DB::query()->fromSub(app(PublicDiscovery::class)->catalog(), 'catalog')->count(),
        ];

        $funding = DB::table('case_funding_reports as f')->join('debt_cases as c', 'c.id', '=', 'f.debt_case_id')
            ->where('c.approved_minor', '>', 0)->where('f.simulated', SimulatedProvider::enabled());
        $settlements = DB::table('settlement_queue as q')->join('debt_cases as c', 'c.id', '=', 'q.debt_case_id')
            ->join('case_funding_reports as f', 'f.debt_case_id', '=', 'c.id')
            ->where('c.approved_minor', '>', 0)->where('f.simulated', SimulatedProvider::enabled())
            ->whereColumn('f.allocated_minor', 'c.approved_minor')->whereColumn('f.settled_minor', '<', 'c.approved_minor');
        $disputes = DB::query()->fromSub(app(SettlementReports::class)->payments(), 'p')->where('shop_response', 'dispute');

        return $counts + [
            'funded' => (clone $funding)->whereColumn('f.allocated_minor', 'c.approved_minor')->count(),
            'settled' => $funding->whereColumn('f.settled_minor', 'c.approved_minor')->count(),
            'donations_minor' => (string) DB::table('funding_receipt_reports')->where('simulated', SimulatedProvider::enabled())->sum('received_minor'),
            'settlements' => $settlements->count(),
            'disputes' => SimulatedProvider::enabled() ? $disputes->count() : 0,
            'simulated' => SimulatedProvider::enabled(),
        ];
    }

    public function financial(string $kind): Builder
    {
        return match ($kind) {
            'donations' => DB::table('donations as item')->select('item.id', 'item.debt_case_id', 'item.scope', 'item.amount_minor', 'item.allocation_status', 'item.created_at')
                ->join('payment_transactions as receipt', 'receipt.donation_id', '=', 'item.id')
                ->addSelect('receipt.status as payment_status', 'receipt.id as receipt_id'),
            'allocations' => DB::query()->fromSub(app(DonationLedger::class)->allocations(), 'item')
                ->select('item.id', 'item.debt_case_id', 'item.payment_transaction_id', 'item.amount_minor', 'item.net_minor', 'item.created_at'),
            'transactions' => DB::table('financial_events as item')->select('item.id', 'item.payment_transaction_id', 'item.donation_allocation_id', 'item.shop_payment_id',
                'item.refund_id', 'item.type', 'item.amount_minor', 'item.created_at'),
            'refunds' => DB::table('refunds as item')->join('payment_transactions as receipt', 'receipt.id', '=', 'item.payment_transaction_id')
                ->select('item.id', 'item.payment_transaction_id', 'item.kind', 'item.status', 'item.amount_minor', 'item.reason', 'item.created_at', 'receipt.donation_id'),
        };
    }

    public function regions(?string $region): Builder
    {
        $query = DB::table('cities as city')->join('regions as region', 'region.id', '=', 'city.region_id')
            ->select('city.id', 'city.name_fr', 'city.name_ar', 'region.name_fr as region_fr', 'region.name_ar as region_ar', 'city.active', 'region.active as region_active')
            ->selectSub(DB::table('shops')->selectRaw('count(*)')->whereColumn('city_id', 'city.id'), 'shops')
            ->selectSub(DB::table('shops')->selectRaw('count(*)')->whereColumn('city_id', 'city.id')->where('status', 'verified'), 'verified_shops')
            ->selectSub(DB::table('debt_cases')->selectRaw('count(*)')->whereColumn('city_id', 'city.id')->where('status', '<>', 'draft'), 'cases');

        return $query->when($region, fn ($query) => $query->where('city.region_id', $region));
    }

    public function regionalSummary(): Builder
    {
        return DB::table('regions as region')->select('region.id', 'region.name_fr', 'region.name_ar')
            ->selectSub(DB::table('cities as city')->join('debt_cases as c', 'c.city_id', '=', 'city.id')
                ->selectRaw('count(*)')->whereColumn('city.region_id', 'region.id')->where('c.status', '<>', 'draft'), 'cases')
            ->selectSub(DB::table('cities as city')->join('shops as s', 's.city_id', '=', 'city.id')
                ->selectRaw('count(*)')->whereColumn('city.region_id', 'region.id')->where('s.status', 'verified'), 'verified_shops');
    }
}
