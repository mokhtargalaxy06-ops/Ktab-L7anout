<?php

namespace App\Services;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use LogicException;

class PublicDiscovery
{
    private function validFunding(): Builder
    {
        return DB::table('case_funding_reports as report')
            ->where('report.simulated', SimulatedProvider::enabled())
            ->join('debt_cases as approved', 'approved.id', '=', 'report.debt_case_id')
            ->where('approved.approved_minor', '>', 0)
            ->where('report.settled_minor', '>=', 0)
            ->whereColumn('report.settled_minor', '<=', 'report.allocated_minor')
            ->whereColumn('report.allocated_minor', '<=', 'approved.approved_minor')
            ->select('report.*');
    }

    public function policy(): array
    {
        $policy = config('discovery.urgency');
        $weights = $policy['weights'];
        if (count($weights) !== 4 || array_diff(['vulnerability', 'age', 'access_risk', 'remaining'], array_keys($weights))
            || collect($weights)->contains(fn ($value) => ! is_int($value) || $value < 0)
            || array_sum($weights) !== 100 || $policy['age_cap_days'] < 1 || $policy['balance_cap_minor'] < 1
            || $policy['urgent_threshold'] < 1 || $policy['urgent_threshold'] > 100) {
            throw new LogicException('Invalid public urgency policy.');
        }

        return $policy;
    }

    public function catalog(array $filters = []): Builder
    {
        $query = DB::table('debt_cases as c')
            ->join('shops as s', 's.id', '=', 'c.shop_id')
            ->join('cities as city', 'city.id', '=', 'c.city_id')
            ->join('regions as region', 'region.id', '=', 'city.region_id')
            ->leftJoinSub($this->validFunding(), 'funding', 'funding.debt_case_id', '=', 'c.id')
            ->where(fn (Builder $query) => $query->whereNotNull('funding.debt_case_id')
                ->orWhereNotExists(fn (Builder $report) => $report->selectRaw('1')->from('case_funding_reports')
                    ->whereColumn('case_funding_reports.debt_case_id', 'c.id')
                    ->when(! SimulatedProvider::enabled(), fn ($report) => $report->where('simulated', false))))
            ->where('c.status', 'published')->where('c.flagged', false)
            ->whereNotNull('c.public_reference')->where('c.published_at', '<=', now())->where('c.approved_minor', '>', 0)
            ->where('s.status', 'verified')->whereColumn('s.revision', 'c.verified_shop_revision')
            ->whereColumn('s.city_id', 'c.city_id')->where('city.active', true)->where('region.active', true)
            ->select([
                'c.id as internal_id', 'c.public_reference as id', 'c.approved_minor', 'c.published_at', 'c.public_story', 'c.debt_date',
                'c.vulnerability_grade', 'c.access_risk_grade', 'c.revision',
                'city.name_fr as city_fr', 'city.name_ar as city_ar', 'region.name_fr as region_fr', 'region.name_ar as region_ar',
            ])->selectRaw('COALESCE(funding.allocated_minor, 0) AS collected_minor')
            ->selectRaw('COALESCE(funding.settled_minor, 0) AS settled_minor')
            ->selectRaw('funding.updated_at as funding_updated_at');

        foreach (['region_id' => 'region.id', 'city_id' => 'city.id'] as $filter => $column) {
            if (isset($filters[$filter])) {
                $query->where($column, $filters[$filter]);
            }
        }
        foreach (['min_minor' => '>=', 'max_minor' => '<='] as $filter => $comparison) {
            if (isset($filters[$filter])) {
                $query->where('c.approved_minor', $comparison, (int) $filters[$filter]);
            }
        }
        if (isset($filters['q'])) {
            $term = str_replace(['!', '%', '_'], ['!!', '!%', '!_'], mb_strtolower($filters['q']));
            $query->where(function (Builder $search) use ($term) {
                foreach (['city.name_fr', 'city.name_ar', 'region.name_fr', 'region.name_ar', 'CAST(c.public_reference AS text)'] as $column) {
                    $search->orWhereRaw("LOWER($column) LIKE ? ESCAPE '!'", ["%$term%"]);
                }
            });
        }

        $date = now('Africa/Casablanca')->toDateString();
        $elapsed = DB::connection()->getDriverName() === 'sqlite'
            ? 'CAST(julianday(?) - julianday(debt_date) AS INTEGER)'
            : '(CAST(? AS date) - debt_date)';
        $balances = DB::query()->fromSub($query, 'eligible')
            ->select('eligible.*')->selectRaw("$elapsed AS age_days", [$date])
            ->selectRaw('approved_minor - collected_minor AS remaining_minor');
        $policy = $this->policy();
        $weights = $policy['weights'];
        $ageCap = (int) $policy['age_cap_days'];
        $balanceCap = (int) $policy['balance_cap_minor'];
        $score = '(vulnerability_grade * 2500 * ?)
            + ((CASE WHEN age_days < 0 THEN 0 WHEN age_days >= ? THEN 10000 ELSE age_days * 10000 / ? END) * ?)
            + (access_risk_grade * 2500 * ?)
            + ((CASE WHEN remaining_minor >= ? THEN 10000 ELSE remaining_minor * 10000 / ? END) * ?)';
        $scored = DB::query()->fromSub($balances, 'balances')->select('balances.*')->selectRaw("$score AS urgency_score", [
            $weights['vulnerability'], $ageCap, $ageCap, $weights['age'], $weights['access_risk'], $balanceCap, $balanceCap, $weights['remaining'],
        ]);
        $catalog = DB::query()->fromSub($scored, 'catalog');
        if (isset($filters['urgent'])) {
            $catalog->where('remaining_minor', '>', 0)->where('urgency_score', '>=', $policy['urgent_threshold'] * 10000);
        }
        match ($filters['status'] ?? null) {
            'open' => $catalog->where('remaining_minor', '>', 0),
            'funded' => $catalog->where('remaining_minor', 0)->whereColumn('settled_minor', '<', 'approved_minor'),
            'settled' => $catalog->whereColumn('settled_minor', '>=', 'approved_minor'),
            default => null,
        };

        return $catalog;
    }

    public function ordered(Builder $query, bool $urgent): Builder
    {
        if ($urgent) {
            return $query->orderByDesc('urgency_score')->orderBy('debt_date')->orderBy('published_at')->orderBy('internal_id');
        }

        return $query->orderByDesc('published_at')->orderBy('internal_id');
    }

    public function statistics(): array
    {
        $totals = DB::query()->fromSub($this->validFunding(), 'funding')->join('debt_cases as c', 'c.id', '=', 'funding.debt_case_id')
            ->selectRaw('COUNT(DISTINCT CASE WHEN funding.settled_minor > 0 THEN c.beneficiary_profile_id END) AS families_helped')
            ->selectRaw('COUNT(CASE WHEN c.approved_minor > 0 AND funding.settled_minor >= c.approved_minor THEN 1 END) AS fully_settled_cases')
            ->selectRaw('COALESCE(SUM(funding.settled_minor), 0) AS settled_minor, COALESCE(SUM(funding.allocated_minor), 0) AS allocated_minor')
            ->first();
        $received = DB::table('funding_receipt_reports')->where('simulated', SimulatedProvider::enabled())->where('received_minor', '>', 0)
            ->selectRaw('COALESCE(SUM(received_minor), 0) AS total')->first();

        return [
            'families_helped' => (int) $totals->families_helped,
            'fully_settled_cases' => (int) $totals->fully_settled_cases,
            'settled_minor' => (string) $totals->settled_minor,
            'allocated_minor' => (string) $totals->allocated_minor,
            'received_minor' => (string) $received->total,
            'currency' => 'MAD',
        ];
    }
}
