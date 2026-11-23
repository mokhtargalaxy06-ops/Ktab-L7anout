<?php

namespace App\Http\Controllers;

use App\Http\Requests\ShopActionRequest;
use App\Http\Requests\ShopRequest;
use App\Http\Resources\ShopDebtRequestResource;
use App\Http\Resources\ShopResource;
use App\Models\DebtCase;
use App\Models\Shop;
use App\Models\ShopDocument;
use App\Support\PhoneNumber;
use App\Services\PublicDiscovery;
use App\Services\ShopWorkflow;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ShopController extends Controller
{
    public function __construct(private readonly ShopWorkflow $workflow) {}

    public function index(Request $request): JsonResponse
    {
        Gate::authorize('create', Shop::class);

        return ShopResource::collection(Shop::with(['city.region', 'locality', 'documents'])
            ->whereHas('owners', fn ($owners) => $owners->whereKey($request->user()->id))
            ->orderByDesc('id')->cursorPaginate(25))->response()->header('Cache-Control', 'no-store');
    }

    public function adminIndex(Request $request): JsonResponse
    {
        Gate::authorize('review', Shop::class);

        $filters = $request->validate(['city_id' => ['nullable', 'uuid'], 'status' => ['nullable', 'in:pending,verified,rejected,suspended,closed']]);

        return ShopResource::collection(Shop::with('city')->where(array_filter($filters))->orderBy('id')->cursorPaginate(25))
            ->response()->header('Cache-Control', 'no-store');
    }

    public function create(ShopRequest $request): JsonResponse
    {
        Gate::authorize('create', Shop::class);

        return $this->resource($this->workflow->create($request->user(), $request->validated()), 201);
    }

    public function show(Shop $shop): JsonResponse
    {
        Gate::authorize('view', $shop);

        return $this->resource($shop);
    }

    public function update(ShopRequest $request, Shop $shop): JsonResponse
    {
        Gate::authorize('update', $shop);

        return $this->resource($this->workflow->update($request->user(), $shop, $request->validated()));
    }

    public function upload(ShopActionRequest $request, Shop $shop): JsonResponse
    {
        Gate::authorize('update', $shop);
        $this->workflow->upload($request->user(), $shop, $request->file('document'));

        return $this->resource($shop->refresh(), 201);
    }

    public function download(Request $request, Shop $shop, string $document): StreamedResponse
    {
        Gate::authorize('view', $shop);
        $file = $shop->documents()->findOrFail($document);
        abort_unless($file->status === 'available' || ($file->isAwaitingLocalReview() && Gate::allows('review', Shop::class)), 404);

        return $this->attachment($file);
    }

    public function uploadRedacted(ShopActionRequest $request, Shop $shop, string $document): JsonResponse
    {
        $this->workflow->upload($request->user(), $shop, $request->file('document'), $document);

        return $this->resource($shop->refresh(), 201);
    }

    public function shareEvidence(ShopActionRequest $request, Shop $shop, string $document): JsonResponse
    {
        $this->workflow->shareEvidence($request->user(), $shop, $document, (bool) $request->validated('share'));

        return $this->resource($shop->refresh());
    }

    public function donorEvidence(PublicDiscovery $discovery, string $reference): JsonResponse
    {
        $files = $this->donorDocuments($discovery, $reference)->get(['shop_documents.id', 'shop_documents.media_type']);

        $case = $discovery->catalog()->where('id', $reference)->first();
        $phone = $case ? DebtCase::query()->join('shops', 'shops.id', '=', 'debt_cases.shop_id')
            ->where('debt_cases.id', $case->internal_id)->where('shops.status', 'verified')
            ->value('shops.contact_phone') : null;

        return response()->json(['data' => $files, 'shop_whatsapp_url' => PhoneNumber::whatsappUrl($phone)])
            ->header('Cache-Control', 'private, no-store');
    }

    public function donorDownload(PublicDiscovery $discovery, string $reference, string $document): StreamedResponse
    {
        $file = $this->donorDocuments($discovery, $reference)->where('shop_documents.id', $document)
            ->firstOrFail(['shop_documents.*']);

        return $this->attachment($file);
    }

    private function donorDocuments(PublicDiscovery $discovery, string $reference): Builder
    {
        $cases = DB::query()->fromSub($discovery->catalog()->where('id', $reference), 'published')->select('internal_id');
        $shops = DebtCase::whereIn('id', $cases)->select('shop_id');

        return ShopDocument::query()->join('shops', 'shops.id', '=', 'shop_documents.shop_id')
            ->whereIn('shop_documents.shop_id', $shops)->where('shops.status', 'verified')
            ->whereColumn('shop_documents.revision', 'shops.revision')->where('shop_documents.status', 'available')
            ->whereNotNull('shop_documents.redacted_from_id')->whereNotNull('shop_documents.shared_at');
    }

    private function attachment(ShopDocument $file): StreamedResponse
    {
        $disk = Storage::disk('local');
        abort_unless($disk->exists($file->storage_key), 404);

        $extension = match ($file->media_type) {
            'application/pdf' => 'pdf',
            'image/png' => 'png',
            'image/jpeg' => 'jpg',
            default => 'bin',
        };

        return $disk->download($file->storage_key, 'evidence-'.$file->id.'.'.$extension, [
            'Content-Type' => 'application/octet-stream',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store',
        ]);
    }

    public function review(ShopActionRequest $request, Shop $shop): JsonResponse
    {
        Gate::authorize('review', Shop::class);

        return $this->resource($this->workflow->review($request->user(), $shop, $request->validated()));
    }

    public function debtRequests(Shop $shop): JsonResponse
    {
        Gate::authorize('view', $shop);

        return ShopDebtRequestResource::collection($shop->debtRequests()->orderByDesc('id')->cursorPaginate(25))
            ->response()->header('Cache-Control', 'no-store');
    }

    public function requestDebt(ShopActionRequest $request, Shop $shop): JsonResponse
    {
        Gate::authorize('review', Shop::class);

        return ShopDebtRequestResource::make($this->workflow->requestDebt($request->user(), $shop, $request->validated()))
            ->response()->setStatusCode(201)->header('Cache-Control', 'no-store');
    }

    public function respondDebt(ShopActionRequest $request, Shop $shop, string $debt): JsonResponse
    {
        Gate::authorize('respond', $shop);

        return ShopDebtRequestResource::make($this->workflow->respondDebt($request->user(), $shop, $debt, $request->validated()))
            ->response()->header('Cache-Control', 'no-store');
    }

    private function resource(Shop $shop, int $status = 200): JsonResponse
    {
        return ShopResource::make($shop->load(['city.region', 'locality', 'documents']))
            ->response()->setStatusCode($status)->header('Cache-Control', 'no-store');
    }
}
