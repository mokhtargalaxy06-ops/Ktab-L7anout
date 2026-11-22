<?php

use App\Models\User;
use App\Services\FundingService;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

require dirname(__DIR__, 2).'/vendor/autoload.php';

$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
config([
    'funding.simulator_enabled' => true,
    'database.default' => 'pgsql',
    'database.connections.pgsql.database' => getenv('FUNDING_CONCURRENCY_DATABASE'),
]);

[$script, $label, $mode, $id, $reference] = array_pad($argv, 5, null);
DB::select("select set_config('application_name', ?, false)", [$label]);
$funding = app(FundingService::class);
if ($mode === 'create') {
    $record = $funding->create(User::findOrFail($id), [
        'scope' => 'specific', 'case_reference' => $reference, 'amount_minor' => '7000', 'policy_version' => 'mvp-1',
    ], 'concurrent-same-key');
    echo $record->id;

    exit;
}
$funding->simulate(DB::table('donations')->find($id), 'success');
echo $id;
