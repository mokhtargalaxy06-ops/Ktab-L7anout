<?php

namespace App\Http\Controllers;

use App\Http\Requests\DiscoveryReviewRequest;
use App\Http\Requests\PublicCaseSearchRequest;
use App\Http\Resources\PublicDebtCaseResource;
use App\Models\DebtCase;
use App\Services\DebtWorkflow;
use App\Services\PublicDiscovery;
use App\Services\SimulatedProvider;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PublicDiscoveryController extends Controller
{
    public function __construct(private readonly PublicDiscovery $discovery) {}

    public function index(PublicCaseSearchRequest $request): JsonResponse
    {
        $filters = $request->validated();
        $page = $this->discovery->ordered($this->discovery->catalog($filters), isset($filters['urgent']))
            ->paginate(12, ['*'], 'page', (int) ($filters['page'] ?? 1));

        return response()->json([
            'data' => PublicDebtCaseResource::collection($page->items())->resolve(),
            'meta' => ['page' => $page->currentPage(), 'last_page' => $page->lastPage(), 'total' => $page->total()],
        ])->header('Cache-Control', 'no-store');
    }

    public function show(Request $request, string $reference): JsonResponse
    {
        $record = $this->discovery->catalog()->where('id', $reference)->first();
        abort_unless($record, 404);
        $updates = DB::table('debt_verifications')->where('debt_case_id', $record->internal_id)->where('revision', $record->revision)
            ->whereIn('action', ['confirm', 'approve', 'publish'])->orderByDesc('id')->get(['action', 'created_at'])
            ->map(fn ($event) => ['type' => $event->action, 'date' => substr($event->created_at, 0, 10)]);
        if ($record->funding_updated_at !== null) {
            $updates->prepend(['type' => 'funding', 'date' => substr($record->funding_updated_at, 0, 10)]);
        }

        return response()->json(['data' => PublicDebtCaseResource::make($record)->resolve($request) + ['updates' => $updates]])
            ->header('Cache-Control', 'no-store');
    }

    public function home(): JsonResponse
    {
        return response()->json(['data' => [
            'statistics' => $this->discovery->statistics(),
            'simulated' => SimulatedProvider::enabled(),
            'recent' => PublicDebtCaseResource::collection($this->discovery->ordered($this->discovery->catalog(), false)->limit(3)->get())->resolve(),
            'urgent' => PublicDebtCaseResource::collection($this->discovery->ordered($this->discovery->catalog(['urgent' => '1']), true)->limit(3)->get())->resolve(),
            'urgency_policy' => $this->discovery->policy(),
        ]])->header('Cache-Control', 'no-store');
    }

    public function review(DiscoveryReviewRequest $request, DebtCase $case, DebtWorkflow $workflow): JsonResponse
    {
        $workflow->assessDiscovery($request->user(), $case, $request->validated());

        return response()->json(['data' => ['saved' => true]]);
    }
}
