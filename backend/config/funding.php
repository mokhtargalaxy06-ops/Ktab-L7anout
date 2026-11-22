<?php

return [
    'simulator_enabled' => env('FUNDING_SIMULATOR_ENABLED', false),
    'simulator_secret' => env('FUNDING_SIMULATOR_SECRET'),
    'policy_version' => 'mvp-1',
    'pool_holding_days' => 30,
];
