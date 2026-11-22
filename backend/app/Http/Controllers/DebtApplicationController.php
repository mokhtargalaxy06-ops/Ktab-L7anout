<?php

namespace App\Http\Controllers;

use App\Http\Requests\DebtActionRequest;
use App\Http\Requests\DebtApplicationRequest;
use App\Http\Resources\DebtCaseResource;
use App\Models\DebtCase;
use App\Models\Shop;
use App\Services\DebtWorkflow;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DebtApplicationController extends Controller
{
    public function __construct(private readonly DebtWorkflow $workflow) {}

    public function index(Request $request, string $audience): JsonResponse
    {
        $query = DebtCase::with(['city.region', 'shop']);
        match ($audience) {
            'beneficiary' => $query->whereHas('profile', fn ($profile) => $profile->where('user_id', $request->user()->id)),
            'shop' => $query->whereIn('status', ['pending_verification', 'under_review', 'disputed', 'approved', 'published', 'rejected'])
                ->whereHas('shop', fn ($shop) => $shop->where('status', 'verified')->whereHas('owners', fn ($owner) => $owner->whereKey($request->user()->id)))
                ->whereHas('profile', fn ($profile) => $profile->where('user_id', '!=', $request->user()->id)),
            'admin' => $query->where('status', '!=', 'draft'),
            'public' => $query->where('status', 'published')->whereNotNull('published_at')->whereNotNull('approved_minor')
                ->whereHas('shop', fn ($shop) => $shop->where('status', 'verified')->whereColumn('shops.revision', 'debt_cases.verified_shop_revision')),
        };
        $cases = $query->orderByDesc('id')->cursorPaginate(25);

        return response()->json([
            'data' => $cases->getCollection()->map(fn ($case) => (new DebtCaseResource($case, $audience))->resolve($request)),
            'meta' => ['next_cursor' => $cases->nextCursor()?->encode(), 'prev_cursor' => $cases->previousCursor()?->encode()],
        ])->header('Cache-Control', 'no-store');
    }

    public function shops(Request $request): JsonResponse
    {
        $data = $request->validate(['city_id' => ['required', 'uuid']]);
        $shops = Shop::where('city_id', $data['city_id'])->where('status', 'verified')
            ->whereHas('city', fn ($city) => $city->where('active', true)->whereHas('region', fn ($region) => $region->where('active', true)))
            ->whereDoesntHave('owners', fn ($owner) => $owner->whereKey($request->user()->id))
            ->orderBy('name')->orderBy('id')->get(['id', 'name']);

        return response()->json(['data' => $shops])->header('Cache-Control', 'no-store');
    }

    public function show(Request $request, DebtCase $case, string $audience): JsonResponse
    {
        $this->authorizeAudience($case, $audience);
        if ($audience === 'admin') {
            $this->workflow->audit($request->user(), $case, 'case.viewed');
        }

        return $this->resource($case, $audience);
    }

    public function create(DebtApplicationRequest $request): JsonResponse
    {
        return $this->resource($this->workflow->save($request->user(), null, $request->validated()), 'beneficiary', 201);
    }

    public function update(DebtApplicationRequest $request, DebtCase $case): JsonResponse
    {
        return $this->resource($this->workflow->save($request->user(), $case, $request->validated()), 'beneficiary');
    }

    public function submit(DebtActionRequest $request, DebtCase $case): JsonResponse
    {
        return $this->change($request, $case, 'beneficiary', 'submit', $request->validated());
    }

    public function cancel(Request $request, DebtCase $case): JsonResponse
    {
        return $this->change($request, $case, 'beneficiary', 'cancel');
    }

    public function verify(DebtActionRequest $request, DebtCase $case): JsonResponse
    {
        return $this->change($request, $case, 'shop', $request->validated('decision'), $request->validated());
    }

    public function review(DebtActionRequest $request, DebtCase $case): JsonResponse
    {
        return $this->change($request, $case, 'admin', $request->validated('decision'), $request->validated());
    }

    public function uploadOwner(DebtActionRequest $request, DebtCase $case): JsonResponse
    {
        $this->workflow->upload($request->user(), $case, 'beneficiary', $request->file('document'), (int) $request->validated('revision'));

        return $this->resource($case->refresh(), 'beneficiary', 201);
    }

    public function uploadShop(DebtActionRequest $request, DebtCase $case): JsonResponse
    {
        $this->workflow->upload($request->user(), $case, 'shop', $request->file('document'), (int) $request->validated('revision'));

        return $this->resource($case->refresh(), 'shop', 201);
    }

    public function download(Request $request, DebtCase $case, string $document, string $audience): StreamedResponse
    {
        $this->authorizeAudience($case, $audience);
        $file = $case->documents()->where('status', 'available')
            ->when($audience !== 'admin', fn ($query) => $query->where('audience', $audience))->findOrFail($document);
        $disk = Storage::disk('local');
        abort_unless($disk->exists($file->storage_key), 404);
        $this->workflow->audit($request->user(), $case, 'case.evidence_download', ['document_id' => $file->id]);

        return $disk->download($file->storage_key, 'evidence-'.$file->id, [
            'Content-Type' => 'application/octet-stream', 'X-Content-Type-Options' => 'nosniff', 'Cache-Control' => 'private, no-store',
        ]);
    }

    private function authorizeAudience(DebtCase $case, string $audience): void
    {
        match ($audience) {
            'beneficiary' => Gate::authorize('own', $case),
            'shop' => Gate::authorize('assigned', $case),
            'admin' => Gate::authorize('review', DebtCase::class),
        };
        if ($audience === 'shop') {
            abort_if(in_array($case->status, ['draft', 'submitted', 'cancelled'], true), 404);
        }
        abort_if($audience === 'admin' && $case->status === 'draft', 404);
    }

    private function change(Request $request, DebtCase $case, string $audience, string $action, array $data = []): JsonResponse
    {
        return $this->resource($this->workflow->transition($request->user(), $case, $audience, $action, $data), $audience);
    }

    private function resource(DebtCase $case, string $audience, int $status = 200): JsonResponse
    {
        $case->load(['city', 'shop', 'documents' => fn ($query) => $query
            ->when($audience !== 'admin', fn ($query) => $query->where('audience', $audience))->orderBy('id'),
            'history' => fn ($query) => $query
                ->when($audience === 'shop', fn ($query) => $query->where('audience', 'shop'))
                ->when($audience === 'beneficiary', fn ($query) => $query->whereNotIn('action', ['flag', 'clear_flag']))->orderBy('id'),
        ]);
        if ($audience === 'admin') {
            $duplicates = DebtCase::whereKeyNot($case->id)->where('shop_id', $case->shop_id)
                ->whereNotIn('status', ['draft', 'cancelled', 'rejected'])->where(fn ($query) => $query
                ->where('debt_reference', $case->debt_reference)
                ->orWhere(fn ($query) => $query->where('beneficiary_profile_id', $case->beneficiary_profile_id)->where('amount_minor', $case->amount_minor)));
            $case->duplicate_count = (clone $duplicates)->count();
            $case->duplicates = $duplicates->latest('id')->limit(10)->get(['id', 'debt_reference', 'status']);
        }

        return (new DebtCaseResource($case, $audience))->response()->setStatusCode($status)->header('Cache-Control', 'no-store');
    }
}
