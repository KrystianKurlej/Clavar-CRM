<?php
// Basic configuration (can be overridden by environment variables or config.local.php)
$config = [
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

// Environment overrides (e.g., to place SQLite outside public_html)
$dataDir = getenv('CRM_DATA_DIR');
if ($dataDir && is_string($dataDir)) {
    $config['data_dir'] = rtrim($dataDir, '/');
}
$usersDir = getenv('CRM_USERS_DIR');
if ($usersDir && is_string($usersDir)) {
    $config['users_dir'] = rtrim($usersDir, '/');
}
$sessionName = getenv('CRM_SESSION_NAME');
if ($sessionName) {
    $config['session_name'] = $sessionName;
}
$corsEnabled = getenv('CRM_CORS_ENABLED');
if ($corsEnabled !== false) {
    $config['cors']['enabled'] = in_array(strtolower((string)$corsEnabled), ['1','true','yes'], true);
}
$allowedOrigins = getenv('CRM_ALLOWED_ORIGINS');
if ($allowedOrigins) {
    $config['cors']['allowed_origins'] = array_map('trim', explode(',', $allowedOrigins));
}

// Local file override (gitignored): bootstrap/config.local.php should return an array of overrides
$local = __DIR__ . '/config.local.php';
if (is_file($local)) {
    $overrides = require $local;
    if (is_array($overrides)) {
        // shallow merge
        $config = array_replace_recursive($config, $overrides);
    }
}

return $config;
