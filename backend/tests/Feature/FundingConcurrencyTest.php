<?php

namespace Tests\Feature;

use App\Models\BeneficiaryProfile;
use App\Models\City;
use App\Models\DebtCase;
use App\Models\Region;
use App\Models\Shop;
use App\Models\User;
use App\Services\FundingService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class FundingConcurrencyTest extends TestCase
{
    public function test_concurrent_postgres_sessions_deduplicate_intents_and_serialize_allocations(): void
    {
        $database = getenv('FUNDING_CONCURRENCY_DATABASE');
        if (! is_string($database) || ! preg_match('/^mdr_funding_concurrency_[a-z0-9_]+$/D', $database)) {
            $this->markTestSkipped('Set FUNDING_CONCURRENCY_DATABASE to a dedicated migrated PostgreSQL concurrency database.');
        }
        $default = config('database.default');
        $settings = config('database.connections.pgsql');
        config(['database.connections.funding_concurrency' => array_replace($settings, ['database' => $database, 'url' => null]),
            'funding.simulator_enabled' => true]);
        DB::setDefaultConnection('funding_concurrency');

        try {
            $case = $this->fixture();
            $donor = User::factory()->create();
            $label = 'funding_'.Str::lower(Str::random(12));
            [$first, $replayed] = $this->compete($label, 'create', [
                [$donor->id, $case->public_reference], [$donor->id, $case->public_reference],
            ]);
            $this->assertSame($first, $replayed);
            $this->assertSame(1, DB::table('donations')->where('donor_id', $donor->id)->count());

            $other = app(FundingService::class)->create($donor, [
                'scope' => 'specific', 'case_reference' => $case->public_reference, 'amount_minor' => '7000', 'policy_version' => 'mvp-1',
            ], 'other-intent');
            $this->compete($label, 'capture', [[$first], [$other->id]]);
            $this->compete($label, 'capture', [[$first], [$first]]);
            $this->assertSame(10000, (int) DB::table('case_funding_reports')->where('debt_case_id', $case->id)->value('allocated_minor'));
            $payments = DB::table('payment_transactions')->whereIn('donation_id', [$first, $other->id])->pluck('id');
            $this->assertSame(2, DB::table('financial_events')->whereIn('payment_transaction_id', $payments)->where('type', 'receipt_confirmed')->count());
            $this->assertSame(4000, (int) DB::table('refunds')->whereIn('payment_transaction_id', $payments)->sum('amount_minor'));
            $this->assertSame(2, DB::table('donation_allocations')->where('debt_case_id', $case->id)->count());
        } finally {
            DB::setDefaultConnection($default);
            DB::purge('funding_concurrency');
        }
    }

    private function compete(string $label, string $mode, array $arguments): array
    {
        $connection = DB::connection();
        $processes = [];
        $connection->beginTransaction();
        try {
            $connection->table('financial_control')->lockForUpdate()->find(1);
            foreach ($arguments as $args) {
                $process = new Process([
                    PHP_BINARY, '-d', 'display_errors=stderr', '-d', 'extension=pdo_pgsql',
                    base_path('tests/Fixtures/FundingWorker.php'), $label, $mode, ...$args,
                ], base_path(), ['APP_ENV' => 'testing', 'DB_URL' => false], timeout: 20);
                $process->start();
                $processes[] = $process;
            }
            $blocked = 0;
            for ($attempt = 0; $attempt < 100 && $blocked < 2; $attempt++) {
                $connection->select('select pg_stat_clear_snapshot()');
                $blocked = (int) $connection->selectOne(
                    "select count(*) as total from pg_stat_activity where application_name = ? and wait_event_type = 'Lock'", [$label],
                )->total;
                if ($blocked < 2) {
                    usleep(50000);
                }
            }
            $this->assertSame(2, $blocked, 'Both independent PostgreSQL backends must wait on the financial lock. '.implode(' ',
                array_map(fn (Process $process) => $process->getErrorOutput().$process->getOutput(), $processes)));
        } finally {
            $connection->rollBack();
            foreach ($processes as $process) {
                $process->wait();
            }
        }

        return array_map(function (Process $process) {
            $this->assertTrue($process->isSuccessful(), $process->getErrorOutput().$process->getOutput());

            return trim($process->getOutput());
        }, $processes);
    }

    private function fixture(): DebtCase
    {
        $fields = ['code' => (string) Str::uuid(), 'name_fr' => 'Fixture', 'name_ar' => 'مدينة',
            'source_url' => 'https://example.test/source', 'source_date' => '2024-01-01'];
        $region = Region::create($fields);
        $city = City::create($fields + ['region_id' => $region->id]);
        $shop = Shop::create(['name' => 'Fixture', 'city_id' => $city->id, 'contact_phone' => '+212612345678']);
        $shop->forceFill(['status' => 'verified'])->save();
        $profile = BeneficiaryProfile::create(['user_id' => User::factory()->create()->id]);
        $case = new DebtCase(['city_id' => $city->id, 'shop_id' => $shop->id, 'debt_reference' => 'TEST',
            'debt_date' => '2023-01-01', 'amount_minor' => 10000, 'situation' => 'Fixture']);
        $case->profile()->associate($profile);
        $case->forceFill(['status' => 'published', 'published_at' => now()->subDay(), 'approved_minor' => 10000,
            'verified_shop_revision' => 1])->save();

        return $case->refresh();
    }
}
