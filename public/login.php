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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Also read return_to from POST (hidden field)
    $postReturn = (string) ($_POST['return_to'] ?? '');
    if (is_safe_path($postReturn)) {
        $safeReturn = $postReturn;
    }
    csrf_abort_if_invalid($_POST['csrf_token'] ?? null);

    // Rate limit: max 5 attempts per 15 minutes per IP + email (DB-backed)
    $rlKey = 'login_' . md5($_SERVER['REMOTE_ADDR'] . ':' . strtolower(trim((string) ($_POST['email'] ?? ''))));
    if (rate_limit_check($rlKey)) {
        http_response_code(429);
        exit(e($l($copy['auth'], 'err_rate_limit')));
    }

    $email = trim((string) ($_POST['email'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');

    $stmt = DB::pdo()->prepare('SELECT id, password_hash, role, status FROM users WHERE email = :email LIMIT 1');
    $stmt->execute(['email' => $email]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['password_hash'])) {
        if ($user['status'] !== 'active') {
            $error = ($user['role'] === 'farmer')
                ? $l($copy['auth'], 'pending_verification')
                : $l($copy['auth'], 'not_active');
        } else {
            session_regenerate_id(true);
            $_SESSION['user_id'] = (int) $user['id'];
            $_SESSION['role'] = $user['role'];

            // Ticking the box issues a 30-day token; leaving it unticked revokes
            // any token from a previous login, otherwise a stale cookie would
            // keep re-authenticating against the user's explicit choice.
            if (!empty($_POST['remember_me'])) {
                remember_login((int) $user['id']);
            } else {
                remember_revoke((int) $user['id']);
            }

            DB::pdo()
                ->prepare('UPDATE users SET last_login_at = NOW() WHERE id = :id')
                ->execute(['id' => $user['id']]);

            flash_set('success', $l($copy['auth'], 'welcome_back'));
            redirect_to($safeReturn);
        }
    } else {
        $error = $l($copy['auth'], 'invalid_credentials');
    }
}

$pageTitle = $l($copy['auth'], 'login_title');
require __DIR__ . '/partials/header.php';
?>

<main id="main-content" class="auth-wrap">
    <div class="auth-left">
        <div class="auth-left-content">
            <h2><?= $l($copy['auth'], 'login_title') ?></h2>
            <p><?= $l($copy['auth'], 'login_sub') ?></p>
        </div>
    </div>
    <div class="auth-right">
        <div class="auth-card">
            <div class="auth-logo">
                <a href="index.php"><img src="assets/branding/logo-horizontal.svg" alt="AniMAL"></a>
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
                <?php if ($safeReturn !== 'dashboard.php'): ?>
                    <input type="hidden" name="return_to" value="<?= e($safeReturn) ?>">
                <?php endif; ?>
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