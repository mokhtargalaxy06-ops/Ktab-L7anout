<?php

namespace App\Http\Controllers;

use App\Models\DebtCase;
use App\Models\User;
use App\Policies\SettlementPolicy;
use App\Services\AdminReporting;
use App\Services\SimulatedProvider;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class AdminWorkspaceController extends Controller
{
    public function overview(Request $request, AdminReporting $reports, SettlementPolicy $policy): JsonResponse
    {
        Gate::authorize('viewAny', User::class);
        Gate::authorize('review', DebtCase::class);
        abort_unless($policy->administer($request->user()), 403);

        return $this->response($reports->overview());
    }

    public function finance(Request $request, AdminReporting $reports, SettlementPolicy $policy, string $kind): JsonResponse
    {
        abort_unless($policy->administer($request->user()), 403);
        abort_unless(SimulatedProvider::enabled(), 404);
        $input = $request->validate(['id' => ['nullable', 'uuid'], 'related_id' => ['nullable', 'uuid']]);
        $query = $reports->financial($kind);
        if ($id = $input['id'] ?? null) {
            $query->where('item.id', $id);
        }
        if ($related = $input['related_id'] ?? null) {
            $query->where(match ($kind) {
                'donations' => 'item.debt_case_id',
                'allocations', 'transactions', 'refunds' => 'item.payment_transaction_id',
            }, $related);
        }
        $page = $query->orderByDesc('item.id')->paginate(25)->appends($input);
        $page->through(function ($row) {
            $row->amount_minor = (string) $row->amount_minor;
            if (isset($row->net_minor)) {
                $row->net_minor = (string) $row->net_minor;
            }

            return $row;
        });

        return response()->json($page)->header('Cache-Control', 'no-store');
    }

    public function regions(Request $request, AdminReporting $reports): JsonResponse
    {
        Gate::authorize('review', DebtCase::class);
        $input = $request->validate(['region_id' => ['nullable', 'uuid'], 'coverage' => ['nullable', 'in:uncovered']]);
        $cities = DB::query()->fromSub($reports->regions($input['region_id'] ?? null), 'coverage');
        if (($input['coverage'] ?? null) === 'uncovered') {
            $cities->where('active', true)->where('region_active', true)->where('verified_shops', 0);
        }

        return $this->response([
            'regions' => $reports->regionalSummary()->orderBy('region.name_fr')->get(),
            'cities' => $cities->orderBy('name_fr')->orderBy('id')->paginate(25)->appends($input),
        ]);
    }

    public function audit(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', User::class);
        $input = $request->validate(['actor_id' => ['nullable', 'uuid'], 'target_id' => ['nullable', 'uuid']]);
        $page = DB::table('audit_logs')->where(array_filter($input))
            ->orderByDesc('created_at')->orderByDesc('id')->paginate(25)->appends($input);
        $page->through(fn ($row) => (array) $row + ['changes' => array_intersect_key(
            json_decode($row->metadata, true, flags: JSON_THROW_ON_ERROR) ?? [],
            array_flip(['from', 'to', 'reason', 'amount_minor', 'flagged_before', 'flagged_after'])
        )]);
        $page->through(function ($row) {
            unset($row['metadata']);

            return $row;
        });

        return response()->json($page)->header('Cache-Control', 'no-store');
    }

    private function response(array $data): JsonResponse
    {
        return response()->json(['data' => $data])->header('Cache-Control', 'no-store');
    }
}
