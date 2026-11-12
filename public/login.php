<?php
require __DIR__ . '/../src/bootstrap.php';
require_once __DIR__ . '/../src/i18n.php';
$copy = include __DIR__ . '/../src/i18n_copy.php';
$locale = locale_current();
$l = function($dict, $key) use ($copy, $locale) {
    return $dict[$locale][$key] ?? $dict['en'][$key] ?? $key;
};

if (current_user_id()) {
    redirect_to('dashboard.php');
}

$error = null;
$returnTo = (string) ($_GET['return_to'] ?? '');
$safeReturn = is_safe_path($returnTo) ? $returnTo : 'dashboard.php';
$pendingTotp = false;
$totpEmail = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Also read return_to from POST (hidden field)
    $postReturn = (string) ($_POST['return_to'] ?? '');
    if (is_safe_path($postReturn)) {
        $safeReturn = $postReturn;
    }
    csrf_abort_if_invalid($_POST['csrf_token'] ?? null);

    // Bot detection: honeypot (time token skipped for login - rate limited already,
    // and automated clients submit faster than 0.5s).
    if (is_bot_request('login', $_POST, 'website', 0.0)) {
        http_response_code(400);
        exit(e($l($copy['auth'], 'err_rate_limit')));
    }

    // Rate limit: 5 attempts / 15 min per IP+email (DB-backed). The 2FA code
    // step draws from its own bucket so fumbling a code does not burn the
    // password budget, and guessing codes stays throttled either way.
    $rlScope = isset($_POST['totp_code']) ? 'login_totp_' : 'login_';
    $rlKey = $rlScope . hash('sha256', $_SERVER['REMOTE_ADDR'] . ':' . strtolower(trim((string) ($_POST['email'] ?? ''))));
    if (rate_limit_check($rlKey)) {
        http_response_code(429);
        exit(e($l($copy['auth'], 'err_rate_limit')));
    }

    $email = mb_substr(trim((string) ($_POST['email'] ?? '')), 0, FIELD_LIMITS['email'] ?? 255);
    $password = (string) ($_POST['password'] ?? '');

    // Open a full session for a user who passed every required step.
    $establishSession = function (array $u, ?string $message = null) use ($safeReturn, $copy, $l) {
        session_regenerate_id(true);
        $_SESSION['user_id'] = (int) $u['id'];
        $_SESSION['role'] = $u['role'];
        $_SESSION['session_version'] = (int) ($u['session_version'] ?? 0);
        unset($_SESSION['2fa_pending']);

        // Ticking issues a 30-day token; unticked revokes any previous token
        // so a stale cookie can't re-authenticate against explicit choice.
        if (!empty($_POST['remember_me'])) {
            remember_login((int) $u['id']);
        } else {
            remember_revoke((int) $u['id']);
        }

        DB::pdo()
            ->prepare('UPDATE users SET last_login_at = NOW() WHERE id = :id')
            ->execute(['id' => $u['id']]);

        flash_set('success', $message ?? $l($copy['auth'], 'welcome_back'));
        redirect_to($safeReturn);
    };

    // Step 2 of a 2FA sign-in: the password already verified in a prior request.
    $pending = $_SESSION['2fa_pending'] ?? null;
    $isTotpStep = is_array($pending)
        && (int) ($pending['uid'] ?? 0) > 0
        && (time() - (int) ($pending['at'] ?? 0)) < 600
        && isset($_POST['totp_code']);

    if ($isTotpStep) {
        $totpCode = preg_replace('/\D/', '', (string) $_POST['totp_code']);
        $stmt = DB::pdo()->prepare('SELECT id, role, status, session_version, totp_secret, totp_enabled FROM users WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => (int) $pending['uid']]);
        $user = $stmt->fetch();

        if ($user && $user['status'] === 'active'
            && (int) ($user['totp_enabled'] ?? 0) === 1) {
            // The same field takes either an authenticator code or a one-time
            // recovery code; both establish the session, the second is announced.
            if (totp_verify(sensitive_decrypt((string) ($user['totp_secret'] ?? '')), $totpCode)) {
                $establishSession($user);
            }
            // Raw input, not $totpCode: recovery codes contain letters.
            $uid = (int) $user['id'];
            if (recovery_code_verify($uid, trim((string) $_POST['totp_code']))) {
                $establishSession($user, sprintf(
                    $l($copy['account'], 'recovery_code_used'),
                    recovery_codes_count($uid)
                ));
            }
        }

        // Wrong or expired: keep them on the code step, not back at the password.
        unset($_SESSION['2fa_pending']);
        $error = $l($copy['auth'], 'err_totp_invalid');
        $pendingTotp = true;
        $totpEmail = $email;
    } else {
        $stmt = DB::pdo()->prepare('SELECT id, password_hash, role, status, session_version, totp_secret, totp_enabled FROM users WHERE email = :email LIMIT 1');
        $stmt->execute(['email' => $email]);
        $user = $stmt->fetch();

        $withTotp = $user !== false
            && (int) ($user['totp_enabled'] ?? 0) === 1
            && !empty($user['totp_secret']);

        if ($user && password_verify($password, $user['password_hash'])) {
            if ($user['status'] !== 'active') {
                $error = ($user['role'] === 'farmer')
                    ? $l($copy['auth'], 'pending_verification')
                    : $l($copy['auth'], 'not_active');
            } elseif ($withTotp) {
                // Password accepted: hold the session until the TOTP code verifies.
                $_SESSION['2fa_pending'] = ['uid' => (int) $user['id'], 'at' => time()];
                $pendingTotp = true;
                $totpEmail = $email;
            } else {
                $establishSession($user);
            }
        } else {
            $error = $l($copy['auth'], 'invalid_credentials');
        }
    }
}

$pageTitle = $l($copy['auth'], 'login_title');
require __DIR__ . '/partials/header.php';
?>

<main id="main-content" class="auth-wrap">
    <div class="auth-left">
        <div class="auth-left-content" aria-hidden="true">
            <h2><?= $l($copy['auth'], 'login_title') ?></h2>
            <p><?= $l($copy['auth'], 'login_sub') ?></p>
        </div>
    </div>
    <div class="auth-right">
        <div class="auth-card">
            <div class="auth-logo">
                <a href="index.php"><img src="assets/branding/logo-horizontal.svg" alt="AgriMALL"></a>
            </div>
            <h1 class="auth-title"><?= $l($copy['auth'], 'login_title') ?></h1>
            <p class="auth-subtitle"><?= $l($copy['auth'], 'login_sub') ?></p>

            <?php if ($error): ?>
                <div class="alert alert-danger" role="alert">
                    <i class="bi bi-exclamation-circle me-1"></i> <?= e($error) ?>
                </div>
            <?php endif; ?>

            <form method="post" action="login.php">
                <?= csrf_field() ?>
                <?= honeypot_field() ?>
                <?= issue_form_token('login') ?>
                <?php if ($safeReturn !== 'dashboard.php'): ?>
                    <input type="hidden" name="return_to" value="<?= e($safeReturn) ?>">
                <?php endif; ?>
                <?php if ($pendingTotp): ?>
                <?php /* Email rides along as a hidden field so the per-IP+email rate
                         limit keeps throttling code guesses, not just passwords. */ ?>
                <input type="hidden" name="email" value="<?= e($totpEmail) ?>">
                <?php if (!empty($_POST['remember_me'])): ?>
                    <input type="hidden" name="remember_me" value="1">
                <?php endif; ?>
                <?php
                $label = $l($copy['auth'], 'label_totp_code');
                $name = 'totp_code';
                $type = 'text';
                $value = '';
                $placeholder = '123456 or XXXX-XXXX-XX';
                $required = true;
                $autofocus = true;
                // Accepts an authenticator code or a recovery code in the same field.
                $attrs = ['maxlength' => 14, 'pattern' => '[A-Za-z0-9\- ]*', 'autocomplete' => 'one-time-code'];
                $error = '';
                include __DIR__ . '/partials/form-field.php';
                ?>
                <?php else: ?>
                <?php
                $label = $l($copy['auth'], 'email');
                $name = 'email';
                $type = 'email';
                $value = $_POST['email'] ?? '';
                $placeholder = 'you@example.com';
                $required = true;
                $autofocus = true;
                $attrs = ['maxlength' => FIELD_LIMITS['email']];
                $error = $error && str_contains($error, 'email') ? $error : '';
                include __DIR__ . '/partials/form-field.php';
                ?>
                <?php
                $label = $l($copy['auth'], 'password');
                $name = 'password';
                $type = 'password';
                $value = '';
                $placeholder = 'Your password';
                $required = true;
                $attrs = ['maxlength' => FIELD_LIMITS['password']];
                $error = $error && str_contains($error, 'password') ? $error : '';
                include __DIR__ . '/partials/form-field.php';
                ?>
                <div class="remember-me">
                    <label class="form-check d-flex align-items-center gap-2" for="remember_me" style="cursor: pointer; margin-bottom: 0;">
                        <input class="form-check-input mt-1" type="checkbox" value="1" id="remember_me" name="remember_me"
                               <?= !empty($_POST['remember_me']) ? 'checked' : '' ?>>
                        <span class="form-check-label" style="margin-bottom: 0;">
                            <?= $l($copy['auth'], 'remember_me') ?>
                        </span>
                    </label>
                </div>
                <?php endif; ?>
                <button class="btn btn-primary btn-lg w-100" type="submit">
                    <?= $l($copy['auth'], 'login_btn') ?>
                </button>
            </form>

            <div class="auth-footer">
                <a class="auth-link-muted" href="forgot.php"><?= $l($copy['auth'], 'forgot_link') ?></a>
            </div>
            <div class="auth-footer">
                <?= $l($copy['auth'], 'no_account') ?> <a href="register.php"><?= $l($copy['auth'], 'create_one') ?></a>
            </div>
        </div>
    </div>
</main>

<?php require __DIR__ . '/partials/footer.php'; ?>