<?php
/**
 * Self-service account erasure.
 *
 * Performs anonymization, not physical delete. Schema prevents physical
 * delete for accounts with orders/admin FKs (ON DELETE RESTRICT).
 * BR-013, BR-034, NFR-REL-004 forbid physical delete.
 * See account_anonymize() in src/functions.php.
 */
require __DIR__ . '/../src/bootstrap.php';
require_once __DIR__ . '/../src/i18n.php';
$copy = include __DIR__ . '/../src/i18n_copy.php';
$locale = locale_current();
$l = function ($dict, $key) use ($copy, $locale) {
    return $dict[$locale][$key] ?? $dict['en'][$key] ?? $key;
};

require_login();
$role = current_user_role();
$userId = (int) current_user_id();
$del = $copy['account_delete'];

$stmt = DB::pdo()->prepare('SELECT id, email, role, status FROM users WHERE id = :id');
$stmt->execute(['id' => $userId]);
$user = $stmt->fetch();
if (!$user) {
    redirect_to('logout.php');
}

$error = null;
$done = false;

// Preconditions: recomputed on submit since admin can place hold
// between render and post.
$holdActive = user_has_active_retention_hold($userId);
$openOrders = user_nonterminal_order_count($userId);
$isAdmin = ($role === 'admin');
$confirmPhrase = $l($del, 'confirm_phrase');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$done) {
    csrf_abort_if_invalid($_POST['csrf_token'] ?? null);

    if ($isAdmin) {
        // Deliberately unreachable by form submit: block rendered
        // instead of form, but re-checked server-side so crafted POST
        // cannot bypass.
        $error = $l($del, 'blocked_admin_title');
    } elseif ($holdActive) {
        $error = $l($del, 'blocked_hold');
    } elseif ($openOrders > 0) {
        $error = sprintf($l($del, 'blocked_orders'), $openOrders);
    } else {
        $hashStmt = DB::pdo()->prepare('SELECT password_hash FROM users WHERE id = :id');
        $hashStmt->execute(['id' => $userId]);
        $hash = (string) $hashStmt->fetchColumn();

        $typed = trim((string) ($_POST['confirm_phrase'] ?? ''));

        if (!password_verify((string) ($_POST['current_password'] ?? ''), $hash)) {
            // Same message as wrong confirmation word; password never
            // confirmed by distinguishable response.
            $error = $l($del, 'invalid_current_password');
        } elseif ($typed === '' || strcasecmp($typed, $confirmPhrase) !== 0) {
            $error = $l($del, 'err_confirm_mismatch');
        } else {
            try {
                account_anonymize($userId);
                remember_revoke($userId);

                // Account anonymized; end session before rendering.
                // Any page reached from here must be public.
                $_SESSION = [];
                if (ini_get('session.use_cookies')) {
                    $params = session_get_cookie_params();
                    setcookie(
                        session_name(),
                        '',
                        time() - 42000,
                        $params['path'],
                        $params['domain'],
                        $params['secure'],
                        $params['httponly']
                    );
                }
                session_destroy();

                $done = true;
            } catch (RuntimeException $e) {
                // Never claim success on failed commit.
                $error = $l($del, 'blocked_hold');
                error_log('account deletion failed: ' . $e->getMessage());
            }
        }
    }
}

$pageTitle = $l($del, 'page_title');
require __DIR__ . '/partials/header.php';
?>

<main id="main-content" class="auth-wrap">
    <div class="auth-card auth-card--wide" data-reveal>
        <div class="auth-logo">
            <a href="index.php"><img src="assets/branding/logo-horizontal.svg" alt="AniMALL"></a>
        </div>

        <?php if ($done): ?>
            <h1 class="auth-title"><?= e($l($del, 'done_heading')) ?></h1>
            <div class="alert alert-success" role="status">
                <i class="bi bi-check-circle-fill me-1" aria-hidden="true"></i>
                <?= e($l($del, 'done_body')) ?>
            </div>
            <p class="auth-subtitle"><?= e($l($del, 'done_note')) ?></p>
            <a class="btn btn-primary btn-lg w-100" href="index.php"><?= e($l($del, 'btn_home')) ?></a>
        <?php else: ?>
            <h1 class="auth-title"><?= e($l($del, 'page_title')) ?></h1>
            <p class="auth-subtitle"><?= e($l($del, 'page_description')) ?></p>

            <?php if (!empty($error)): ?>
                <div class="alert alert-danger" role="alert">
                    <i class="bi bi-exclamation-triangle-fill me-1" aria-hidden="true"></i>
                    <?= e($error) ?>
                </div>
            <?php endif; ?>

            <?php if ($isAdmin || $holdActive || $openOrders > 0): ?>
                <div class="danger-panel">
                    <h2 class="danger-panel__title">
                        <i class="bi bi-shield-exclamation me-1" aria-hidden="true"></i>
                        <?= e($isAdmin ? $l($del, 'blocked_admin_title') : $l($del, 'section_heading')) ?>
                    </h2>
                    <?php if ($isAdmin): ?>
                        <p class="danger-panel__body"><?= e($l($del, 'blocked_admin_body')) ?></p>
                    <?php elseif ($holdActive): ?>
                        <p class="danger-panel__body"><?= e($l($del, 'blocked_hold')) ?></p>
                    <?php else: ?>
                        <p class="danger-panel__body">
                            <?= e(sprintf($l($del, 'blocked_orders'), $openOrders)) ?>
                        </p>
                    <?php endif; ?>
                </div>
                <a class="btn btn-outline w-100" href="account.php"><?= e($l($copy['account'], 'btn_cancel')) ?></a>
            <?php else: ?>
                <div class="danger-panel">
                    <h2 class="danger-panel__title">
                        <i class="bi bi-exclamation-triangle-fill me-1" aria-hidden="true"></i>
                        <?= e($l($del, 'section_heading')) ?>
                    </h2>
                    <p class="danger-panel__lead"><?= e($l($del, 'warning_lead')) ?></p>

                    <h3 class="danger-panel__sub"><?= e($l($del, 'keeps_heading')) ?></h3>
                    <p class="danger-panel__body"><?= e($l($del, 'keeps_body')) ?></p>

                    <h3 class="danger-panel__sub"><?= e($l($del, 'removes_heading')) ?></h3>
                    <p class="danger-panel__body"><?= e($l($del, 'removes_body')) ?></p>
                </div>

                <form method="post" action="account_delete.php" class="auth-form" novalidate>
                    <?= csrf_field() ?>

                    <div class="mb-3">
                        <label class="form-label" for="current_password">
                            <?= e($l($del, 'label_current_password')) ?>
                        </label>
                        <input class="form-control" type="password" id="current_password" name="current_password"
                               autocomplete="current-password" required>
                    </div>

                    <div class="mb-4">
                        <label class="form-label" for="confirm_phrase">
                            <?= e(sprintf($l($del, 'confirm_label'), $confirmPhrase)) ?>
                        </label>
                        <input class="form-control" type="text" id="confirm_phrase" name="confirm_phrase"
                               autocomplete="off" spellcheck="false" required>
                    </div>

                    <button class="btn btn-danger btn-lg w-100" type="submit">
                        <i class="bi bi-trash3 me-1" aria-hidden="true"></i>
                        <?= e($l($del, 'btn_delete')) ?>
                    </button>
                    <a class="btn btn-outline w-100 mt-2" href="account.php"><?= e($l($del, 'btn_cancel')) ?></a>
                </form>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</main>

<?php require __DIR__ . '/partials/footer.php'; ?>
