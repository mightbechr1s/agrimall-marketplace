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
$success = null;
$resetLink = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_abort_if_invalid($_POST['csrf_token'] ?? null);

    // Bot detection: honeypot only (time token skipped — rate limited already).
    if (is_bot_request('forgot', $_POST, 'website', 0.0)) {
        http_response_code(400);
        exit(e($l($copy['auth'], 'err_rate_limit')));
    }

    // Rate limit: 5 attempts / 15 min per IP+email (DB-backed)
    $rlKey = 'pwreset_' . md5($_SERVER['REMOTE_ADDR'] . ':' . strtolower(trim((string) ($_POST['email'] ?? ''))));
    if (rate_limit_check($rlKey)) {
        http_response_code(429);
        exit(e($l($copy['auth'], 'err_rate_limit')));
    }

    $email = trim((string) ($_POST['email'] ?? ''));

    if ($email === '') {
        $error = str_replace(':label', $l($copy['auth'], 'email'), $l($copy['auth'], 'err_fill'));
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = $l($copy['auth'], 'err_email_valid');
    } elseif (mb_strlen($email) > FIELD_LIMITS['email']) {
        $error = sprintf($l($copy['auth'], 'err_too_long'), FIELD_LIMITS['email']);
    } else {
        // Check if email exists
        $stmt = DB::pdo()->prepare('SELECT id FROM users WHERE email = :email LIMIT 1');
        $stmt->execute(['email' => $email]);
        $user = $stmt->fetch();

        if ($user) {
            // Generate token; only SHA-256 hash stored so DB leak can't replay reset.
            $token = bin2hex(random_bytes(32));
            $expiresAt = (new DateTime('+1 hour'))->format('Y-m-d H:i:s');

            // Re-request retires previous tokens.
            password_resets_revoke((int) $user['id']);

            // Store token hash
            $insert = DB::pdo()->prepare(
                'INSERT INTO password_resets (user_id, token, expires_at)
                 VALUES (:user_id, :token, :expires_at)'
            );
            $insert->execute([
                'user_id' => $user['id'],
                'token' => hash('sha256', $token),
                'expires_at' => $expiresAt
            ]);

            // No mail transport; link handed to operator over local-only channel.
            // Returns null in production where token must never be displayed.
            $resetLink = password_reset_deliver_local((int) $user['id'], $token);

            $success = $l($copy['auth'], 'reset_success');
        } else {
            // Don't reveal email existence for security
            $success = $l($copy['auth'], 'reset_success');
        }
    }
}

$pageTitle = $l($copy['auth'], 'forgot_title');
require __DIR__ . '/partials/header.php';
?>

<main id="main-content" class="auth-wrap">
    <div class="auth-card" data-reveal>
        <div class="auth-logo">
            <a href="index.php"><img src="assets/branding/logo-horizontal.svg" alt="AgriMALL"></a>
        </div>
        <h1 class="auth-title"><?= $l($copy['auth'], 'forgot_title') ?></h1>
        <p class="auth-subtitle"><?= $l($copy['auth'], 'forgot_sub') ?></p>

        <?php if ($error): ?>
            <div style="background: var(--danger-soft); color: var(--danger); padding: 12px 16px; border-radius: var(--radius-sm); font-size: 14px; margin-bottom: 20px; font-weight: 500;">
                <i class="bi bi-exclamation-circle me-1"></i> <?= e($error) ?>
            </div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div style="background: var(--ok-soft); color: var(--ok); padding: 12px 16px; border-radius: var(--radius-sm); font-size: 14px; margin-bottom: 20px; font-weight: 500; display: flex; gap: 8px; align-items: flex-start; line-height: 1.5;">
                <i class="bi bi-check-circle-fill mt-1"></i> <span><?= e($success) ?></span>
            </div>
        <?php endif; ?>

        <?php if ($resetLink): ?>
            <div class="auth-card-local-link" role="status">
                <p class="auth-card-local-link__title">
                    <i class="bi bi-terminal me-1"></i>
                    <?= e($l($copy['auth'], 'reset_link_local_heading')) ?>
                </p>
                <p class="auth-card-local-link__body">
                    <?= e($l($copy['auth'], 'reset_link_local_body')) ?>
                </p>
                <code class="auth-card-local-link__url" id="reset-link"><?= e($resetLink) ?></code>
                <p class="auth-card-local-link__note">
                    <?= e($l($copy['auth'], 'reset_link_local_note')) ?>
                </p>
            </div>
        <?php endif; ?>

        <form method="post" action="forgot.php">
            <?= csrf_field() ?>
            <?= honeypot_field() ?>
            <?= issue_form_token('forgot') ?>
            <div style="margin-bottom: 18px;">
                <label class="form-label" for="email"><?= $l($copy['auth'], 'email') ?></label>
                <input class="form-control" type="email" id="email" name="email"
                       value="<?= e($_POST['email'] ?? '') ?>" required autofocus placeholder="you@example.com" maxlength="<?= FIELD_LIMITS['email'] ?>" />
            </div>
            <button class="btn btn-primary btn-lg w-100" type="submit">
                <i class="bi bi-envelope-paper me-1"></i> <?= $l($copy['auth'], 'send_reset_btn') ?>
            </button>
        </form>

        <div class="auth-footer">
            <?= $l($copy['auth'], 'remember_pwd') ?> <a href="login.php"><?= $l($copy['auth'], 'login_link') ?></a>
        </div>
    </div>
</main>

<?php require __DIR__ . '/partials/footer.php'; ?>