<?php

namespace App\Services;

use App\Exceptions\DebtCaseConflict;
use App\Models\BeneficiaryProfile;
use App\Models\DebtCase;
use App\Models\DebtDocument;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

class DebtWorkflow
{
    public function assessDiscovery(User $actor, DebtCase $case, array $assessment): void
    {
        FinancialLock::run(function () use ($actor, $case, $assessment) {
            $case = DebtCase::lockForUpdate()->findOrFail($case->id);
            Gate::forUser($actor)->authorize('review', DebtCase::class);
            abort_if($case->profile->user_id === $actor->id, 403);
            abort_unless((int) $assessment['revision'] === $case->revision
                && in_array($case->status, ['under_review', 'approved', 'published'], true), 409);

            $grades = [
                'public_story' => $assessment['story'],
                'vulnerability_grade' => (int) $assessment['vulnerability'],
                'access_risk_grade' => (int) $assessment['access_risk'],
            ];
            $case->forceFill($grades)->save();
            $case->history()->create([
                'actor_id' => $actor->id, 'audience' => 'admin', 'action' => 'public_assessed',
                'revision' => $case->revision, 'reason' => $assessment['reason'], 'created_at' => now(),
            ]);
            $this->audit($actor, $case, 'case.public_assessed', $grades + ['revision' => $case->revision]);
        });
    }

    public function save(User $actor, ?DebtCase $case, array $input): DebtCase
    {
        return DB::transaction(function () use ($actor, $case, $input) {
            $shop = Shop::whereIn('status', Shop::APPLICATION_DRAFT_STATUSES)
                ->whereHas('city', fn ($city) => $city->where('active', true)->whereHas('region', fn ($region) => $region->where('active', true)))
                ->find($input['shop_id']);
            if (! $shop || $shop->city_id !== $input['city_id']) {
                throw ValidationException::withMessages(['shop_id' => __('cases.invalid_shop')]);
            }
            abort_if($shop->owners()->whereKey($actor->id)->exists(), 403);
            if ($case) {
                $case = DebtCase::lockForUpdate()->findOrFail($case->id);
                Gate::forUser($actor)->authorize('own', $case);
                abort_unless($case->status === 'draft', 409);
            } else {
                $profile = BeneficiaryProfile::firstOrCreate(['user_id' => $actor->id]);
                $case = new DebtCase;
                $case->profile()->associate($profile);
            }
            $input['debt_reference'] = Str::upper($input['debt_reference']);
            $case->fill($input);
            if ($case->exists && $case->isDirty(['shop_id', 'city_id', 'amount_minor', 'debt_reference', 'debt_date'])) {
                $case->revision++;
            }
            $case->save();

            return $case->refresh();
        });
    }

    public function transition(User $actor, DebtCase $case, string $audience, string $action, array $data = []): DebtCase
    {
        return FinancialLock::run(function () use ($actor, $case, $audience, $action, $data) {
            $case = DebtCase::lockForUpdate()->findOrFail($case->id);
            $shop = Shop::lockForUpdate()->findOrFail($case->shop_id);
            $case->setRelation('shop', $shop);
            Gate::forUser($actor)->authorize(match ($audience) {
                'beneficiary' => 'own',
                'shop' => 'assigned',
                'admin' => 'review',
            }, $audience === 'admin' ? DebtCase::class : $case);
            abort_if($audience === 'admin' && $case->profile->user_id === $actor->id, 403);
            throw_if(isset($data['revision']) && (int) $data['revision'] !== $case->revision, DebtCaseConflict::class, 'stale_revision');
            $state = $case->status;
            $wasFlagged = $case->flagged;
            $next = match ([$audience, $action]) {
                ['beneficiary', 'submit'] => $state === 'draft' ? 'submitted' : null,
                ['beneficiary', 'cancel'] => in_array($state, ['draft', 'submitted', 'pending_verification', 'under_review', 'disputed'], true) ? 'cancelled' : null,
                ['admin', 'route'] => $state === 'submitted' ? 'pending_verification' : null,
                ['shop', 'confirm'] => $state === 'pending_verification' ? 'under_review' : null,
                ['shop', 'reject'], ['shop', 'dispute'] => $state === 'pending_verification' ? 'disputed' : null,
                ['admin', 'approve'] => $state === 'under_review' ? 'approved' : null,
                ['admin', 'publish'] => $state === 'approved' ? 'published' : null,
                ['admin', 'unpublish'], ['admin', 'suspend'] => $state === 'published' ? 'approved' : null,
                ['admin', 'reopen_dispute'] => $state === 'disputed' ? 'pending_verification' : null,
                ['admin', 'request_info'] => in_array($state, ['submitted', 'pending_verification', 'disputed', 'under_review', 'approved'], true) ? 'draft' : null,
                ['admin', 'reject'] => in_array($state, ['submitted', 'pending_verification', 'disputed', 'under_review', 'approved'], true) ? 'rejected' : null,
                ['admin', 'flag'], ['admin', 'clear_flag'] => in_array($state, ['submitted', 'pending_verification', 'disputed', 'under_review', 'approved'], true) ? $state : null,
                default => null,
            };
            throw_unless($next, DebtCaseConflict::class, 'invalid_transition');
            if (in_array($action, ['request_info', 'reject'], true)) {
                abort_if(DB::table('donation_allocations')->where('debt_case_id', $case->id)->exists(), 409);
                abort_if(DB::table('external_debt_payments as e')->join('donations as d', 'd.id', '=', 'e.donation_id')
                    ->where('d.debt_case_id', $case->id)->where('e.status', 'approved')->exists(), 409);
            }
            $this->apply($case, $shop, $action, $data);
            $case->status = $next;
            $case->save();
            $case->history()->create([
                'actor_id' => $actor->id, 'audience' => $audience, 'action' => $action, 'revision' => $case->revision,
                'amount_minor' => $data['amount_minor'] ?? null, 'reason' => $data['reason'] ?? null, 'created_at' => now(),
            ]);
            if ($audience === 'admin') {
                $this->audit($actor, $case, 'case.'.$action, ['from' => $state, 'to' => $next, 'reason' => $data['reason'] ?? null,
                    'flagged_before' => $wasFlagged, 'flagged_after' => $case->flagged]);
            }

            return $case;
        });
    }

    private function apply(DebtCase $case, Shop $shop, string $action, array $data): void
    {
        if (in_array($action, ['submit', 'route', 'confirm', 'approve', 'publish', 'reopen_dispute'], true)) {
            throw_unless($shop->status === 'verified' && $shop->city_id === $case->city_id, DebtCaseConflict::class, 'shop_not_eligible');
        }
        if ($action === 'submit') {
            abort_unless($case->documents()->where('audience', 'beneficiary')->where('revision', $case->revision)
                ->whereIn('status', DebtDocument::SUBMISSION_STATUSES)->exists(), 409, __('cases.evidence_required'));
            $case->forceFill(['consent_version' => $data['consent_version'], 'consented_at' => now()]);
        }
        if ($action === 'confirm') {
            abort_unless($data['amount_minor'] === (string) $case->amount_minor, 422, __('cases.amount_mismatch'));
            $case->verified_shop_revision = $shop->revision;
        }
        if (in_array($action, ['approve', 'publish'], true)) {
            throw_if($case->flagged, DebtCaseConflict::class, 'flagged');
            throw_if($case->verified_shop_revision !== $shop->revision, DebtCaseConflict::class, 'shop_revision_changed');
            throw_if(DebtCase::where('shop_id', $shop->id)->where('debt_reference', $case->debt_reference)
                ->whereKeyNot($case->id)->whereIn('status', ['approved', 'published'])->exists(), DebtCaseConflict::class, 'duplicate_approved');
        }
        if ($action === 'approve') {
            $case->approved_minor = $case->amount_minor;
        }
        if ($action === 'publish') {
            $case->published_at = now();
        }
        if (in_array($action, ['unpublish', 'suspend'], true)) {
            $case->published_at = null;
        }
        if ($action === 'request_info') {
            $case->forceFill([
                'revision' => $case->revision + 1, 'verified_shop_revision' => null,
                'approved_minor' => null, 'consent_version' => null, 'consented_at' => null,
                'public_story' => 'essentials', 'vulnerability_grade' => null, 'access_risk_grade' => null,
            ]);
        }
        if (in_array($action, ['flag', 'clear_flag', 'suspend'], true)) {
            $case->flagged = $action !== 'clear_flag';
        }
    }

    public function upload(User $actor, DebtCase $case, string $audience, UploadedFile $file, int $revision): void
    {
        $digest = hash_file('sha256', $file->getPathname());
        abort_if($digest === false, 503);
        $disk = Storage::disk('local');
        $key = $disk->putFileAs('debt-evidence', $file, (string) Str::uuid());
        abort_if($key === false, 503);
        try {
            DB::transaction(function () use ($actor, $case, $audience, $key, $file, $digest, $revision) {
                $case = DebtCase::lockForUpdate()->findOrFail($case->id);
                $action = $audience === 'shop' ? 'assigned' : 'own';
                Gate::forUser($actor)->authorize($action, $case);
                abort_unless($case->revision === $revision, 409);
                abort_unless($case->status === ($audience === 'shop' ? 'pending_verification' : 'draft'), 409);
                abort_if($case->documents()->where('audience', $audience)->where('revision', $case->revision)->count() >= 10, 422);
                $case->documents()->create([
                    'uploader_id' => $actor->id, 'audience' => $audience, 'revision' => $case->revision, 'storage_key' => $key,
                    'media_type' => $file->getMimeType(), 'size_bytes' => $file->getSize(), 'sha256' => $digest,
                ]);
            });
        } catch (Throwable $exception) {
            $disk->delete($key);

            throw $exception;
        }
    }

    public function audit(User $actor, DebtCase $case, string $action, array $metadata = []): void
    {
        DB::table('audit_logs')->insert([
            'id' => (string) Str::uuid(), 'actor_id' => $actor->id, 'actor_kind' => 'user', 'action' => $action,
            'target_type' => 'debt_case', 'target_id' => $case->id, 'correlation_id' => (string) Str::uuid(),
            'metadata' => json_encode($metadata, JSON_THROW_ON_ERROR), 'created_at' => now(),
        ]);
    }
}
