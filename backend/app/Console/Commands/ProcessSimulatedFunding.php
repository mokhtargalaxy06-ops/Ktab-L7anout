<?php

namespace App\Console\Commands;

use App\Services\FundingService;
use App\Services\SimulatedProvider;
use Illuminate\Console\Command;

class ProcessSimulatedFunding extends Command
{
    protected $signature = 'funding:process';

    protected $description = 'Retry simulated allocations and confirm queued simulated refunds';

    public function handle(FundingService $funding): int
    {
        if (! SimulatedProvider::enabled()) {
            return self::SUCCESS;
        }
        $funding->tick();

        return self::SUCCESS;
    }
}
