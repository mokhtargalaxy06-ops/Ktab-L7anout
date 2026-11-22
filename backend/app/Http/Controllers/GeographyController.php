<?php

namespace App\Http\Controllers;

use App\Http\Requests\GeographyRequest;
use App\Models\City;
use App\Models\Locality;
use App\Models\Region;
use App\Support\Access;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class GeographyController extends Controller
{
    private const LEVELS = [
        'regions' => [Region::class, null, null],
        'cities' => [City::class, Region::class, 'region_id'],
        'localities' => [Locality::class, City::class, 'city_id'],
    ];

    public function publicIndex(Request $request, string $level): JsonResponse
    {
        return $this->index($request, $level, true);
    }

    public function adminIndex(Request $request, string $level): JsonResponse
    {
        $this->authorizeManager($request);

        return $this->index($request, $level, false);
    }

    public function save(GeographyRequest $request, string $level, ?string $id = null): JsonResponse
    {
        $this->authorizeManager($request);
        [$model, $parentModel, $parentKey] = self::LEVELS[$level];
        $attributes = Arr::except($request->validated(), ['parent_id']);
        $record = $id ? $model::findOrFail($id) : new $model;

        if (! $id && $parentKey) {
            $parent = $parentModel::find($request->validated('parent_id'));
            if (! $parent) {
                throw ValidationException::withMessages(['parent_id' => __('shops.city_mismatch')]);
            }
            $attributes[$parentKey] = $parent->id;
        }

        try {
            DB::transaction(fn () => $record->fill($attributes)->save());
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['code' => __('shops.duplicate_code')]);
        }

        return response()->json(['data' => $record], $id ? 200 : 201);
    }

    private function index(Request $request, string $level, bool $public): JsonResponse
    {
        [$model, $parentModel, $parentKey] = self::LEVELS[$level];
        $query = $model::query();

        if ($parentKey) {
            $id = $request->validate([$parentKey => ['required', 'uuid']])[$parentKey];
            $parent = $parentModel::query();
            if ($public) {
                $parent->where('active', true);
                if ($level === 'localities') {
                    $parent->whereHas('region', fn ($region) => $region->where('active', true));
                }
            }
            $parent->findOrFail($id);
            $query->where($parentKey, $id);
        }

        if ($public) {
            $query->where('active', true);
        }

        return response()->json(['data' => $query->orderBy(app()->getLocale() === 'ar' ? 'name_ar' : 'name_fr')->orderBy('id')->get()]);
    }

    private function authorizeManager(Request $request): void
    {
        abort_unless(in_array('geography.manage', Access::abilities($request->user()), true), 403);
    }
}
