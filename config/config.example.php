<?php
// AniMAL configuration.
// Copy to config/config.php and fill in real values. Never commit config.php.

return [
    'db' => [
        'host' => '127.0.0.1',
        'port' => 3306,
        'name' => 'animal_marketplace',
        'user' => 'root',
        'pass' => '',
        'charset' => 'utf8mb4',
    ],
    'app' => [
        'name' => 'AniMAL',
        'base_url' => 'https://your-domain.example', // Production: replace with your actual domain (e.g. https://animal.example)
        'timezone' => 'Asia/Manila',
        'session_lifetime_minutes' => 30,
        'env' => 'production',      // 'development' | 'production'
        // Error logging settings
        'error_logging' => [
            'display_errors' => false,
            'log_errors' => true,
            'error_log' => __DIR__ . '/../logs/error.log',
        ],
    ],
];