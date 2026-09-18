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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_abort_if_invalid($_POST['csrf_token'] ?? null);

    // ---- Rate limit: max 5 attempts per 15 minutes per IP + email (DB-backed) ----
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
    } else {
        // Check if email exists
        $stmt = DB::pdo()->prepare('SELECT id FROM users WHERE email = :email LIMIT 1');
        $stmt->execute(['email' => $email]);
        $user = $stmt->fetch();

        if ($user) {
            // Generate token
            $token = bin2hex(random_bytes(32));
            $expiresAt = (new DateTime('+1 hour'))->format('Y-m-d H:i:s');

            // Store token
            $insert = DB::pdo()->prepare(
                'INSERT INTO password_resets (user_id, token, expires_at)
                 VALUES (:user_id, :token, :expires_at)'
            );
            $insert->execute([
                'user_id' => $user['id'],
                'token' => $token,
                'expires_at' => $expiresAt
            ]);

            // In a real application, you would send an email here
            // For now, we'll just show a success message
            $success = $l($copy['auth'], 'reset_success');
        } else {
            // Don't reveal that the email doesn't exist for security
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
            <a href="index.php"><img src="assets/branding/logo-horizontal.svg" alt="AniMAL"></a>
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

        <form method="post" action="forgot.php">
            <?= csrf_field() ?>
            <div style="margin-bottom: 18px;">
                <label class="form-label" for="email"><?= $l($copy['auth'], 'email') ?></label>
                <input class="form-control" type="email" id="email" name="email"
                       value="<?= e($_POST['email'] ?? '') ?>" required autofocus placeholder="you@example.com" />
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