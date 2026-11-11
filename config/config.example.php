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
        // token in the browser. When true, the link is also appended to
        // logs/security.log for the operator. When false, no token is written
        // to the log; configure mail above so users can still reset.
        'local_password_reset_links' => false,
        'security_log' => __DIR__ . '/../logs/security.log',
        // Error logging settings
        'error_logging' => [
            'display_errors' => false,
            'log_errors' => true,
            'error_log' => __DIR__ . '/../logs/error.log',
        ],
    ],

    // At-rest encryption for sensitive columns (TOTP seeds, valid ID numbers).
    // Generate once: php -r "echo base64_encode(random_bytes(32)), PHP_EOL;"
    // Keep the value: losing it makes already-encrypted values unreadable
    // (a 2FA user would then need an admin-assisted reset). When empty, 2FA
    // setup is refused and ID numbers fall back to plaintext with a warning.
    'security' => [
        'encryption_key' => '',
    ],

    // Outbound email. Off by default: PHP mail() needs a working local MTA or
    // SMTP relay. When enabled, order status changes and password resets are
    // also emailed; in-app notifications are unaffected.
    // provider: 'mail' (PHP mail, needs MTA) or 'resend' (Resend HTTP API,
    // free tier 3000 emails/mo; set resend_api_key). Resend is recommended on
    // free hosts like InfinityFree where outbound SMTP is blocked.
    'mail' => [
        'enabled' => false,
        'provider' => 'mail',
        'from' => 'no-reply@agrimall.ph',
        'resend_api_key' => '',
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