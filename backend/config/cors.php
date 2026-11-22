<?php

return [
    'paths' => ['api/*', 'sanctum/csrf-cookie', 'login', 'register', 'logout', 'forgot-password', 'reset-password'],
    'allowed_methods' => ['GET', 'POST', 'PATCH', 'OPTIONS'],
    'allowed_origins' => array_filter(array_map('trim', explode(',', env('FRONTEND_ORIGINS', '')))),
    'allowed_origins_patterns' => [],
    'allowed_headers' => ['Accept', 'Accept-Language', 'Content-Type', 'X-XSRF-TOKEN', 'X-Requested-With'],
    'exposed_headers' => ['Retry-After'],
    'max_age' => 600,
    'supports_credentials' => true,
];
