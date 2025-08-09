<?php
// Basic configuration
return [
    'app_name' => 'Simple CRM',
    'timezone' => 'Europe/Warsaw',
    // Session
    'session_name' => 'crm_sess',
    'session_secure' => false, // set true behind https
    'session_samesite' => 'Lax',

    // Storage
    'data_dir' => __DIR__ . '/../data',
    'users_dir' => __DIR__ . '/../data/users',

    // CSRF
    'csrf_key' => 'change_me_' . substr(hash('sha256', __FILE__), 0, 16),

    // CORS for API (set enabled=true and add allowed origins if your frontend runs on a different origin)
    'cors' => [
        'enabled' => false,
        'allowed_origins' => [
            // 'http://localhost:5173',
        ],
        'allowed_headers' => ['Content-Type', 'X-CSRF-Token'],
        'allowed_methods' => ['GET', 'POST', 'OPTIONS'],
    ],
];
