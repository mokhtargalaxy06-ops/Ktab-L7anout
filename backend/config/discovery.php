<?php

return [
    'urgency' => [
        'weights' => ['vulnerability' => 35, 'age' => 25, 'access_risk' => 25, 'remaining' => 15],
        'age_cap_days' => 365,
        'balance_cap_minor' => 500000,
        'urgent_threshold' => 60,
    ],
];
