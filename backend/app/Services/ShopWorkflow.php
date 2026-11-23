<?php

namespace App\Services;

use App\Models\Shop;
use App\Models\ShopDebtRequest;
use App\Models\ShopDocument;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

class ShopWorkflow
{
    public function create(User $actor, array $profile): Shop
    {
        return DB::transaction(function () use ($actor, $profile) {
            $shop = Shop::create($this->profile($profile));
            DB::table('shop_memberships')->insert(['shop_id' => $shop->id, 'user_id' => $actor->id, 'granted_at' => now()]);
            $this->audit($actor, $shop, 'shop.registered');

            return $shop->refresh();
        });
    }

    public function update(User $actor, Shop $shop, array $profile): Shop
    {
        return FinancialLock::run(function () use ($actor, $shop, $profile) {
            $shop = $this->lockedShop($shop);
            Gate::forUser($actor)->authorize('update', $shop);
            $shop->fill($this->profile($profile));
            if ($shop->isDirty()) {
                $shop->forceFill(['status' => 'pending', 'revision' => $shop->revision + 1, 'review_reason' => null])->save();
                $this->audit($actor, $shop, 'shop.profile_updated', ['revision' => $shop->revision]);
            }

            return $shop;
        });
    }

    public function upload(User $actor, Shop $shop, UploadedFile $file, ?string $redactedFrom = null): void
    {
        $digest = hash_file('sha256', $file->getPathname());
        abort_if($digest === false, 503);
        $mediaType = $file->getMimeType();
        $size = $file->getSize();
        $disk = Storage::disk('local');
        $key = $disk->putFileAs('shop-evidence', $file, (string) Str::uuid());
        abort_if($key === false, 503);

        try {
            DB::transaction(function () use ($actor, $shop, $key, $mediaType, $size, $digest, $redactedFrom) {
                $shop = $this->lockedShop($shop);
                if ($redactedFrom !== null) {
                    Gate::forUser($actor)->authorize('review', Shop::class);
                    $shop->documents()->whereNull('redacted_from_id')->where('status', 'available')
                        ->where('revision', $shop->revision)->findOrFail($redactedFrom);
                    abort_if($shop->documents()->whereNull('redacted_from_id')->where('sha256', $digest)->exists(), 422);
                } else {
                    Gate::forUser($actor)->authorize('update', $shop);
                    abort_unless(in_array($shop->status, ['pending', 'rejected'], true), 409);
                }
                if ($redactedFrom === null && $shop->status === 'rejected') {
                    $shop->forceFill(['status' => 'pending', 'revision' => $shop->revision + 1, 'review_reason' => null])->save();
                }
                if ($shop->documents()->where('revision', $shop->revision)
                    ->whereNull('redacted_from_id', not: $redactedFrom !== null)->count() >= 10) {
                    throw ValidationException::withMessages(['document' => __('shops.document_limit')]);
                }
                /** @var ShopDocument $document */
                $document = $shop->documents()->make([
                    'revision' => $shop->revision,
                    'uploader_id' => $actor->id,
                    'storage_key' => $key,
                    'media_type' => $mediaType,
                    'size_bytes' => $size,
                    'sha256' => $digest,
                ]);
                $document->forceFill(['redacted_from_id' => $redactedFrom])->save();
                $this->audit($actor, $shop, $redactedFrom === null ? 'shop.evidence_uploaded' : 'shop.redacted_evidence_uploaded', ['document_id' => $document->id]);
            });
        } catch (Throwable $exception) {
            $disk->delete($key);

            throw $exception;
        }
    }

    public function shareEvidence(User $actor, Shop $shop, string $document, bool $share): void
    {
        FinancialLock::run(function () use ($actor, $shop, $document, $share) {
            Gate::forUser($actor)->authorize('review', Shop::class);
            $shop = $this->lockedShop($shop);
            $copy = $shop->documents()->whereNotNull('redacted_from_id')->lockForUpdate()->findOrFail($document);
            if ($share) {
                abort_unless($shop->status === 'verified' && $copy->status === 'available' && $copy->revision === $shop->revision, 409);
            }
            if ($share === ($copy->shared_at !== null)) {
                return;
            }
            $copy->forceFill(['shared_at' => $share ? now() : null])->save();
            $this->audit($actor, $shop, $share ? 'shop.evidence_shared' : 'shop.evidence_unshared', ['document_id' => $copy->id]);
        });
    }

    public function review(User $actor, Shop $shop, array $review): Shop
    {
        return FinancialLock::run(function () use ($actor, $shop, $review) {
            $shop = $this->lockedShop($shop);
            abort_unless((int) $review['revision'] === $shop->revision, 409);
            $decision = $review['decision'];
            $allowed = match ($shop->status) {
                'pending' => ['verified', 'rejected'],
                'verified' => ['suspended'],
                'suspended' => ['verified'],
                default => [],
            };
            abort_unless(in_array($decision, $allowed, true), 409);
            $manual = false;
            if ($decision === 'verified') {
                $evidence = $shop->documents()->whereNull('redacted_from_id')->where('revision', $shop->revision);
                if (! (clone $evidence)->where('status', 'available')->exists()) {
                    $manual = ShopDocument::allowsLocalUnscannedReview() && $evidence->where('status', 'quarantined')->exists();
                    if (! $manual) {
                        throw ValidationException::withMessages(['decision' => __('shops.clean_evidence_required')]);
                    }
                }
            }
            $previous = $shop->status;
            $shop->forceFill(['status' => $decision, 'review_reason' => $review['reason'] ?? null])->save();
            $this->audit($actor, $shop, 'shop.reviewed', ['from' => $previous, 'to' => $decision, 'reason' => $review['reason'] ?? null,
                'manual_unscanned_review' => $manual]);

            return $shop;
        });
    }

    public function requestDebt(User $actor, Shop $shop, array $input): ShopDebtRequest
    {
        return DB::transaction(function () use ($actor, $shop, $input) {
            $shop = $this->lockedShop($shop);
            abort_unless($shop->status === 'verified', 409);
            $reference = Str::upper($input['reference']);
            if ($shop->debtRequests()->where('reference', $reference)->exists()) {
                throw ValidationException::withMessages(['reference' => __('shops.duplicate_code')]);
            }

            $debt = $shop->debtRequests()->create([
                'requested_by' => $actor->id,
                'reference' => $reference,
                'amount_minor' => $input['amount_minor'],
            ]);
            $this->audit($actor, $shop, 'shop.debt_requested', ['request_id' => $debt->id]);

            return $debt->refresh();
        });
    }

    public function respondDebt(User $actor, Shop $shop, string $id, array $input): ShopDebtRequest
    {
        return DB::transaction(function () use ($actor, $shop, $id, $input) {
            $shop = $this->lockedShop($shop);
            Gate::forUser($actor)->authorize('respond', $shop);
            $debt = $shop->debtRequests()->findOrFail($id);
            abort_unless($debt->status === 'pending', 409);
            $debt->forceFill([
                'status' => $input['decision'],
                'reason' => $input['reason'] ?? null,
                'responded_by' => $actor->id,
                'responded_at' => now(),
            ])->save();
            $this->audit($actor, $shop, 'shop.debt_responded', ['request_id' => $id, 'decision' => $input['decision']]);

            return $debt;
        });
    }

    private function lockedShop(Shop $shop): Shop
    {
        return Shop::lockForUpdate()->findOrFail($shop->id);
    }

    private function profile(array $attributes): array
    {
        return Arr::except($attributes, ['region_id']) + ['locality_id' => null, 'contact_email' => null];
    }

    private function audit(User $actor, Shop $shop, string $action, array $metadata = []): void
    {
        DB::table('audit_logs')->insert([
            'id' => (string) Str::uuid(),
            'actor_id' => $actor->id,
            'actor_kind' => 'user',
            'action' => $action,
            'target_type' => 'shop',
            'target_id' => $shop->id,
            'correlation_id' => (string) Str::uuid(),
            'metadata' => json_encode($metadata, JSON_THROW_ON_ERROR),
            'created_at' => now(),
        ]);
    }
}
