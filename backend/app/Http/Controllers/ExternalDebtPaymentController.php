<?php

namespace App\Http\Controllers;

use App\Http\Requests\ExternalDebtPaymentRequest;
use App\Models\Shop;
use App\Policies\SettlementPolicy;
use App\Services\ExternalDebtPayments;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ExternalDebtPaymentController extends Controller
{
    public function index(Request $request, SettlementPolicy $policy, ExternalDebtPayments $payments)
    {
        $admin = $request->route('audience') === 'admin';
        $actor = $request->user();
        abort_unless($admin ? $policy->administer($actor) : $policy->browseShop($actor), 403);
        $query = $payments->query();
        if ($admin) {
            $query->whereNotNull('e.donation_id');
        } else {
            $query->whereIn('c.shop_id', Shop::query()->whereHas('owners', fn ($owners) => $owners->whereKey($actor->id))->select('id'))
                ->where(fn ($scope) => $scope->whereNotNull('e.donation_id')->orWhere('p.status', 'pending'));
        }
        $page = $query->orderByDesc('d.id')->paginate(20, ['d.id', 'c.public_reference', 's.name as shop_name',
            'd.amount_minor as pledged_minor', 'e.amount_minor', 'e.reference', 'e.status', 'e.reason']);

        return response()->json(['data' => $page->items(), 'last_page' => $page->lastPage()])->header('Cache-Control', 'private, no-store');
    }

    public function declareReceipt(ExternalDebtPaymentRequest $request, string $pledge, ExternalDebtPayments $payments)
    {
        $payments->declare($request->user(), $pledge, $request->validated());

        return response()->noContent(201);
    }

    public function review(ExternalDebtPaymentRequest $request, string $pledge, ExternalDebtPayments $payments)
    {
        $payments->review($request->user(), $pledge, $request->validated());

        return response()->noContent();
    }

    public function evidence(Request $request, string $pledge, SettlementPolicy $policy, ExternalDebtPayments $payments)
    {
        abort_unless($policy->administer($request->user()), 403);
        $key = $payments->query()->where('d.id', $pledge)->value('e.evidence_key');
        $disk = Storage::disk('local');
        abort_unless($key && $disk->exists($key), 404);

        return $disk->download($key, 'receipt-'.basename($key), [
            'Cache-Control' => 'private, no-store', 'Content-Type' => 'application/octet-stream', 'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
