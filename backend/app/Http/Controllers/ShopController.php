<?php

namespace App\Http\Controllers;

use App\Http\Requests\ShopActionRequest;
use App\Http\Requests\ShopRequest;
use App\Http\Resources\ShopDebtRequestResource;
use App\Http\Resources\ShopResource;
use App\Models\Shop;
use App\Services\ShopWorkflow;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ShopController extends Controller
{
    public function __construct(private readonly ShopWorkflow $workflow) {}

    public function index(Request $request): JsonResponse
    {
        Gate::authorize('create', Shop::class);

        return ShopResource::collection(Shop::with('city')
            ->whereHas('owners', fn ($owners) => $owners->whereKey($request->user()->id))
            ->orderBy('id')->cursorPaginate(25))->response()->header('Cache-Control', 'no-store');
    }

    public function adminIndex(): JsonResponse
    {
        Gate::authorize('review', Shop::class);

        return ShopResource::collection(Shop::with('city')->orderBy('id')->cursorPaginate(25))
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
        $file = $shop->documents()->where('status', 'available')->findOrFail($document);
        $disk = Storage::disk('local');
        abort_unless($disk->exists($file->storage_key), 404);

        return $disk->download($file->storage_key, 'evidence-'.$file->id, [
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
