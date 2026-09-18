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
    } elseif (strlen($password) < 8) {
        $error = $l($copy['auth'], 'err_pass_min');
    } elseif ($password !== $passwordConfirm) {
        $error = $l($copy['auth'], 'err_pass_match');
    } else {
        // Validate token
        $stmt = DB::pdo()->prepare(
            'SELECT user_id, expires_at FROM password_resets WHERE token = :token'
        );
        $stmt->execute(['token' => $token]);
        $reset = $stmt->fetch();

        if (!$reset) {
            $error = $l($copy['auth'], 'err_invalid_token');
        } else {
            // Check if token has expired
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

                // Delete used token
                $deleteToken = DB::pdo()->prepare(
                    'DELETE FROM password_resets WHERE token = :token'
                );
                $deleteToken->execute(['token' => $token]);

                $success = $l($copy['auth'], 'reset_success_msg');
            }
        }
    }
} elseif ($token === '') {
    // No token provided
    header('Location: forgot.php');
    exit;
}

// Validate token for GET request
$stmt = DB::pdo()->prepare(
    'SELECT user_id, expires_at FROM password_resets WHERE token = :token'
);
$stmt->execute(['token' => $token]);
$reset = $stmt->fetch();

if (!$reset) {
    header('Location: forgot.php');
    exit;
}

// Check if token has expired
$expiresAt = new DateTime($reset['expires_at']);
$now = new DateTime();
if ($now >= $expiresAt) {
    $error = $l($copy['auth'], 'err_token_expired');
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
                           required minlength="8" />
                </div>
                <div class="mb-4">
                    <label class="form-label" for="password_confirm"><?= $l($copy['auth'], 'confirm_password') ?></label>
                    <input class="form-control" type="password" id="password_confirm" name="password_confirm"
                           required minlength="8" />
                </div>
                <button class="btn btn-primary btn-lg w-100" type="submit"><?= $l($copy['auth'], 'reset_btn') ?></button>
            </form>

            <p class="small text-center text-muted mt-4 mb-0"><?= $l($copy['auth'], 'remember_pwd') ?> <a href="login.php"><?= $l($copy['auth'], 'login_link') ?></a>.</p>
        </div>
    </div>
</main>

<?php require __DIR__ . '/partials/footer.php'; ?>