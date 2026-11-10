<?php
// AgriMALL configuration.
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
        'name' => 'AgriMALL',
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
        // Transport security for the session cookie. True is correct for any real
        // deployment, and is the default when this key is absent. Set it false ONLY
        // when serving plain http on 127.0.0.1: browsers and HTTP clients refuse to
        // store a Secure cookie over http, so the session silently fails to exist
        // and every authenticated request is rejected. Deliberately NOT derived
        // from 'env' -- that setting controls whether PHP diagnostics reach the
        // browser, which is an unrelated concern.
        'secure_cookies' => true,
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

    // Outbound email. Off by default: PHP mail() needs a working local MTA or
    // SMTP relay. When enabled, order status changes and password resets are
    // also emailed; in-app notifications are unaffected.
    'mail' => [
        'enabled' => false,
        'from' => 'no-reply@agrimall.ph',
    ],

    // Web Push (VAPID). Generate a pair with `php tools/vapid-keys.php` and paste
    // the block below into config/config.php. Disabled until both keys exist.
    // The subject must be a mailto: or https: URL the push service can contact.
    'push' => [
        'enabled'     => false,
        'subject'     => 'mailto:admin@agrimall.ph',
        'public_key'  => '',
        'private_key' => '',
    ],
];