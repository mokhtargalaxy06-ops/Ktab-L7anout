<?php

namespace App\Http\Controllers;

use App\Http\Requests\SettlementRequest;
use App\Http\Resources\SettlementCaseResource;
use App\Policies\SettlementPolicy;
use App\Services\FinancialLock;
use App\Services\SettlementReports;
use App\Services\ShopSettlement;
use App\Services\SimulatedProvider;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SettlementController extends Controller
{
    public function index(Request $request, SettlementPolicy $policy, SettlementReports $reports): JsonResponse
    {
        $admin = $request->route('audience') === 'admin';
        abort_unless($admin ? $policy->administer($request->user()) : $policy->browseShop($request->user()), 403);
        abort_unless(SimulatedProvider::enabled(), 404);
        $status = $request->validate(['status' => ['nullable', 'in:requested,submitted,evidence_pending,paid,failed,canceled,dispute']])['status'] ?? null;
        $cases = $reports->cases($admin ? null : $request->user()->id);
        if ($status !== null) {
            $cases->whereExists(DB::query()->fromSub($reports->payments(), 'payment')->selectRaw('1')
                ->whereColumn('payment.debt_case_id', 'c.id')
                ->where($status === 'dispute' ? 'payment.shop_response' : 'payment.status', $status));
        }
        $page = $cases->orderBy('c.id')->paginate(25);
        $payments = $reports->payments()->when($admin, fn ($query) => $query->addSelect('t.evidence'))
            ->whereIn('p.debt_case_id', $page->getCollection()->pluck('id'))
            ->orderByDesc('p.id')->get()->groupBy('debt_case_id');
        foreach ($page as $case) {
            $case->payments = $payments->get($case->id, collect())->map(function ($payment) use ($admin) {
                $payment->amount_minor = (string) $payment->amount_minor;
                if ($admin && $payment->evidence !== null) {
                    $payment->evidence = json_decode($payment->evidence, true, flags: JSON_THROW_ON_ERROR);
                }

                return $payment;
            });
        }

        return SettlementCaseResource::collection($page)->response()->header('Cache-Control', 'no-store');
    }

    public function act(SettlementRequest $request, ShopSettlement $settlement): Response
    {
        $settlement->act($request->user(), $request->route('case'), $request->route('payment'),
            $request->validated(), $request->route('audience') === 'admin');

        return response()->noContent();
    }

    public function transparency(SettlementReports $reports): JsonResponse
    {
        return response()->json(['data' => FinancialLock::run(fn () => $reports->transparency())]);
    }

    public function history(Request $request, SettlementPolicy $policy, SettlementReports $reports): JsonResponse
    {
        abort_unless($policy->administer($request->user()), 403);
        abort_unless(SimulatedProvider::enabled(), 404);
        $history = DB::table('financial_events')->orderByDesc('id')->cursorPaginate(50, ['*'], 'journal');
        $refunds = DB::table('refunds')->orderByDesc('id')->cursorPaginate(25, ['*'], 'returns');
        $receipts = DB::table('payment_transactions')->orderByDesc('id')
            ->cursorPaginate(25, ['id', 'status', 'amount_minor', 'confirmed_at', 'created_at'], 'receipts');

        return response()->json(['data' => [
            'frozen' => (bool) DB::table('financial_control')->where('id', 1)->value('frozen'),
            'transactions' => $history->items(), 'refunds' => $refunds->items(),
            'receipts' => $receipts->items(), 'next_receipts' => $receipts->nextCursor()?->encode(),
            'deficits' => DB::query()->fromSub($reports->reconciliation(), 'balance')
                ->where('available_minor', '<', 0)->orderBy('available_minor')->orderBy('payment_transaction_id')->limit(25)->get(),
            'next_journal' => $history->nextCursor()?->encode(), 'next_returns' => $refunds->nextCursor()?->encode(),
        ]])->header('Cache-Control', 'no-store');
    }

    public function export(Request $request, SettlementPolicy $policy, SettlementReports $reports): StreamedResponse
    {
        abort_unless($policy->administer($request->user()), 403);
        abort_unless(SimulatedProvider::enabled(), 404);

        $reconciliation = $request->route('report') === 'reconciliation';
        $columns = $reconciliation
            ? ['payment_transaction_id', 'received_minor', 'returned_minor', 'transferred_minor', 'allocated_minor', 'held_minor', 'available_minor']
            : ['id', 'type', 'amount_minor', 'payment_transaction_id', 'donation_allocation_id', 'shop_payment_id', 'refund_id', 'created_at'];
        $query = $reconciliation ? $reports->reconciliation()->orderBy('payment_transaction_id') : DB::table('financial_events')->orderBy('id');
        $file = $reconciliation ? 'simulated-reconciliation.csv' : 'simulated-financial-journal.csv';

        return response()->streamDownload(function () use ($query, $columns) {
            $output = fopen('php://output', 'w');
            fputcsv($output, $columns, escape: '');
            foreach ($query->cursor() as $event) {
                $row = (array) $event;
                fputcsv($output, array_map(fn ($column) => $row[$column], $columns), escape: '');
            }
            fclose($output);
        }, $file, ['Content-Type' => 'text/csv; charset=UTF-8', 'Cache-Control' => 'no-store']);
    }
}
