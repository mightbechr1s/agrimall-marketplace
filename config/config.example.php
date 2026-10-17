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
          // Leave empty: app_base_url() derives the host from the incoming request,
          // which is correct on localhost, on `php -S`, and on whatever host you
          // deploy to. Set this only if you need a fixed canonical host (e.g.
          // behind a proxy that rewrites Host): 'https://animal.example'.
          // A placeholder value is NOT safe here: it was shipped as
          // https://your-domain.example and silently turned every password-reset
          // link into a dead URL. app_base_url() now treats that string as unset.
          'base_url' => '',
        'timezone' => 'Asia/Manila',
        'session_lifetime_minutes' => 30,
        'env' => 'production',      // 'development' | 'production'
        // Local-only password reset delivery. Leave false in production: with
        // no mail transport configured, a true value would print a live reset
        // token in the browser. The reset link is always appended to
        // logs/security.log regardless of this flag.
        'local_password_reset_links' => false,
        'security_log' => __DIR__ . '/../logs/security.log',
        // Error logging settings
        'error_logging' => [
            'display_errors' => false,
            'log_errors' => true,
            'error_log' => __DIR__ . '/../logs/error.log',
        ],
    ],
];