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

// Tokens stored as SHA-256 hashes; only raw token leaves server.
$tokenHash = static fn(string $raw): string => hash('sha256', $raw);

$token = trim((string) ($_GET['token'] ?? ''));
$error = null;
$success = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_abort_if_invalid($_POST['csrf_token'] ?? null);

    $token = trim((string) ($_POST['token'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');
    $passwordConfirm = (string) ($_POST['password_confirm'] ?? '');

    if ($token === '') {
        $error = $l($copy['auth'], 'err_invalid_request');
    } elseif ($password === '') {
        $error = $l($copy['auth'], 'err_enter_password');
    } elseif (($pwError = password_policy_error($password)) !== null) {
        $error = $l($copy['auth'], $pwError);
    } elseif (strlen($password) > FIELD_LIMITS['password']) {
        $error = sprintf($l($copy['auth'], 'err_too_long'), FIELD_LIMITS['password']);
    } elseif ($password !== $passwordConfirm) {
        $error = $l($copy['auth'], 'err_pass_match');
    } else {
        // Validate token
        $stmt = DB::pdo()->prepare(
            'SELECT user_id, expires_at FROM password_resets WHERE token = :token'
        );
        $stmt->execute(['token' => $tokenHash($token)]);
        $reset = $stmt->fetch();

        if (!$reset) {
            $error = $l($copy['auth'], 'err_invalid_token');
        } else {
            $expiresAt = new DateTime($reset['expires_at']);
            $now = new DateTime();
            if ($now >= $expiresAt) {
                $error = $l($copy['auth'], 'err_token_expired');
            } else {
                // Update password
                $updateUser = DB::pdo()->prepare(
                    'UPDATE users SET password_hash = :hash WHERE id = :id'
                );
                $updateUser->execute([
                    'hash' => password_hash($password, PASSWORD_DEFAULT),
                    'id' => $reset['user_id']
                ]);

                // Invalidate all tokens for this user
                password_resets_revoke((int) $reset['user_id']);

                // Password change revokes remember-me, mobile API tokens, and
                // every other PHP session via session_version.
                revoke_user_credentials((int) $reset['user_id']);

                security_event_log('password_reset_completed', [
                    'target_user_id' => (int) $reset['user_id'],
                ]);

                $success = $l($copy['auth'], 'reset_success_msg');
            }
        }
    }
} elseif ($token === '') {
    // No token provided
    header('Location: forgot.php');
    exit;
}

// Validate token for GET. Skipped on POST: successful reset consumes
// token, so re-checking would bounce user away from result.
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $stmt = DB::pdo()->prepare(
        'SELECT user_id, expires_at FROM password_resets WHERE token = :token'
    );
    $stmt->execute(['token' => $tokenHash($token)]);
    $reset = $stmt->fetch();

    if (!$reset) {
        // Unknown/used token: report it instead of silently bouncing
        // to forgot.php, so user learns why link failed. Form still served.
        $error = $l($copy['auth'], 'err_invalid_token');
    } else {
        $expiresAt = new DateTime($reset['expires_at']);
        $now = new DateTime();
        if ($now >= $expiresAt) {
            $error = $l($copy['auth'], 'err_token_expired');
        }
    }
}

$pageTitle = $l($copy['auth'], 'reset_title');
require __DIR__ . '/partials/header.php';
?>

<main id="main-content" class="container py-5" style="max-width: 420px">
    <div class="card shadow-sm" data-reveal>
        <div class="card-body p-4">
            <h1 class="h3 mb-1"><?= $l($copy['auth'], 'reset_title') ?></h1>
            <p class="text-muted mb-4"><?= $l($copy['auth'], 'reset_sub') ?></p>

            <?php if ($error): ?>
                <div class="alert alert-danger py-2"><?= e($error) ?></div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="alert alert-success py-2"><?= e($success) ?></div>
            <?php endif; ?>

            <form method="post" action="reset.php">
                <?= csrf_field() ?>
                <input type="hidden" name="token" value="<?= e($token) ?>" />
                <div class="mb-3">
                    <label class="form-label" for="password"><?= $l($copy['auth'], 'new_password') ?></label>
                    <input class="form-control" type="password" id="password" name="password"
                           required minlength="8" maxlength="<?= FIELD_LIMITS['password'] ?>" />
                </div>
                <div class="mb-4">
                    <label class="form-label" for="password_confirm"><?= $l($copy['auth'], 'confirm_password') ?></label>
                    <input class="form-control" type="password" id="password_confirm" name="password_confirm"
                           required minlength="8" maxlength="<?= FIELD_LIMITS['password'] ?>" />
                </div>
                <button class="btn btn-primary btn-lg w-100" type="submit"><?= $l($copy['auth'], 'reset_btn') ?></button>
            </form>

            <p class="small text-center text-muted mt-4 mb-0"><?= $l($copy['auth'], 'remember_pwd') ?> <a href="login.php"><?= $l($copy['auth'], 'login_link') ?></a>.</p>
        </div>
    </div>
</main>

<?php require __DIR__ . '/partials/footer.php'; ?>