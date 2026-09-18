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
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
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

            DB::pdo()
                ->prepare('UPDATE users SET last_login_at = NOW() WHERE id = :id')
                ->execute(['id' => $user['id']]);

            flash_set('success', $l($copy['auth'], 'welcome_back'));
            redirect_to('dashboard.php');
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
            <img src="assets/images/products/seedlings.jpg"
                 alt="Filipino farmer in a rice field"
                 onerror="this.onerror=null; this.src='assets/branding/mark.svg'">
            <h2><?= $l($copy['auth'], 'login_title') ?></h2>
            <p><?= $l($copy['auth'], 'login_sub') ?></p>
            <div class="auth-metrics">
                <div class="auth-metric">
                    <div class="auth-metric-icon"><i class="bi bi-person-fill"></i></div>
                    <div>
                        <div class="auth-metric-number">10,000+</div>
                        <div>Verified Farmers</div>
                    </div>
                </div>
                <div class="auth-metric">
                    <div class="auth-metric-icon"><i class="bi bi-cart-fill"></i></div>
                    <div>
                        <div class="auth-metric-number">50,000+</div>
                        <div>Happy Buyers</div>
                    </div>
                </div>
                <div class="auth-metric">
                    <div class="auth-metric-icon"><i class="bi bi-cash-coin"></i></div>
                    <div>
                        <div class="auth-metric-number">PHP 2B+</div>
                        <div>In Transactions</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="auth-right">
        <div class="auth-card" data-reveal>
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
                <div style="margin-bottom: 18px;">
                    <label class="form-label required" for="email"><?= $l($copy['auth'], 'email') ?></label>
                    <input class="form-control" type="email" id="email" name="email"
                           value="<?= e($_POST['email'] ?? '') ?>" required autofocus placeholder="you@example.com" />
                </div>
                <div style="margin-bottom: 24px;">
                    <label class="form-label required" for="password"><?= $l($copy['auth'], 'password') ?></label>
                    <input class="form-control" type="password" id="password" name="password" required placeholder="Your password" />
                </div>
                <button class="btn btn-primary btn-lg w-100" type="submit">
                    <?= $l($copy['auth'], 'login_btn') ?>
                </button>
            </form>

            <div class="auth-footer">
                <a href="forgot.php"><?= $l($copy['auth'], 'forgot_link') ?></a>
            </div>
            <div class="auth-footer" style="margin-top: 12px;">
                <?= $l($copy['auth'], 'no_account') ?> <a href="register.php"><?= $l($copy['auth'], 'create_one') ?></a>
            </div>
        </div>
    </div>
</main>

<?php require __DIR__ . '/partials/footer.php'; ?>