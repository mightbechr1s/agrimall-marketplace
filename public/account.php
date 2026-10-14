<?php
require __DIR__ . '/../src/bootstrap.php';
require_once __DIR__ . '/../src/i18n.php';
$copy = include __DIR__ . '/../src/i18n_copy.php';
$locale = locale_current();
$l = function ($dict, $key) use ($copy, $locale) {
    return $dict[$locale][$key] ?? $dict['en'][$key] ?? $key;
};

require_login();
$role = current_user_role();
$userId = current_user_id();

$stmt = DB::pdo()->prepare('SELECT id, email, role, status, created_at, last_login_at FROM users WHERE id = :id');
$stmt->execute(['id' => $userId]);
$user = $stmt->fetch();
if (!$user) {
    redirect_to('logout.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_abort_if_invalid($_POST['csrf_token'] ?? null);

    $pwStmt = DB::pdo()->prepare('SELECT password_hash FROM users WHERE id = :id');
    $pwStmt->execute(['id' => $userId]);
    $hash = (string) $pwStmt->fetchColumn();

    $action = $_POST['account_action'] ?? '';

    if ($action === 'email') {
        $newEmail = trim((string) ($_POST['email'] ?? ''));

        if ($newEmail === '') {
            $error = $l($copy['account'], 'err_email_required');
        } elseif ($newEmail === $user['email']) {
            $error = $l($copy['account'], 'email_same');
        } elseif (!filter_var($newEmail, FILTER_VALIDATE_EMAIL)) {
            $error = $l($copy['account'], 'err_email_valid');
        } elseif (mb_strlen($newEmail) > FIELD_LIMITS['email']) {
            $error = sprintf($l($copy['account'], 'err_too_long'), FIELD_LIMITS['email']);
        } elseif (!password_verify((string) ($_POST['current_password'] ?? ''), $hash)) {
            $error = $l($copy['account'], 'invalid_current_password');
        } else {
            $dup = DB::pdo()->prepare('SELECT COUNT(*) FROM users WHERE email = :email AND id <> :id');
            $dup->execute(['email' => $newEmail, 'id' => $userId]);
            if ((int) $dup->fetchColumn() > 0) {
                $error = $l($copy['account'], 'err_email_exists');
            } else {
                DB::pdo()->prepare('UPDATE users SET email = :email WHERE id = :id')
                    ->execute(['email' => $newEmail, 'id' => $userId]);
                $user['email'] = $newEmail;
                $success = $l($copy['account'], 'email_updated');
                audit_log('account_email_changed', 'user', $userId, null, ['email' => $newEmail]);
            }
        }
    } elseif ($action === 'password') {
        $newPass = (string) ($_POST['new_password'] ?? '');

        if ($newPass === '') {
            $error = $l($copy['account'], 'err_pass_required');
        } elseif (strlen($newPass) < 8) {
            $error = $l($copy['account'], 'err_pass_min');
        } elseif (strlen($newPass) > FIELD_LIMITS['password']) {
            $error = sprintf($l($copy['account'], 'err_too_long'), FIELD_LIMITS['password']);
        } elseif ($newPass !== (string) ($_POST['confirm_password'] ?? '')) {
            $error = $l($copy['account'], 'err_pass_match');
        } elseif (!password_verify((string) ($_POST['current_password'] ?? ''), $hash)) {
            $error = $l($copy['account'], 'invalid_current_password');
        } elseif (password_verify($newPass, $hash)) {
            $error = $l($copy['account'], 'password_same');
        } else {
            DB::pdo()->prepare('UPDATE users SET password_hash = :hash WHERE id = :id')
                ->execute(['hash' => password_hash($newPass, PASSWORD_DEFAULT), 'id' => $userId]);
            $success = $l($copy['account'], 'password_updated');
            audit_log('account_password_changed', 'user', $userId);
        }
    } elseif ($action === 'name') {
        // Name change requires current password: hijacked session
        // must not be enough to repoint public identity.
        if (!password_verify((string) ($_POST['current_password'] ?? ''), $hash)) {
            $error = $l($copy['account'], 'invalid_current_password');
        } elseif ($role === 'farmer') {
            $farmName = trim((string) ($_POST['farm_name'] ?? ''));
            $contactPerson = trim((string) ($_POST['contact_person'] ?? ''));

            $cur = DB::pdo()->prepare('SELECT farm_name, contact_person FROM farmer_profiles WHERE user_id = :id');
            $cur->execute(['id' => $userId]);
            $current = $cur->fetch() ?: ['farm_name' => '', 'contact_person' => ''];

            if ($farmName === '' || $contactPerson === '') {
                $error = $l($copy['account'], 'err_name_required');
            } elseif (mb_strlen($farmName) > FIELD_LIMITS['farm_name']) {
                $error = sprintf($l($copy['account'], 'err_too_long'), FIELD_LIMITS['farm_name']);
            } elseif (mb_strlen($contactPerson) > FIELD_LIMITS['contact_person']) {
                $error = sprintf($l($copy['account'], 'err_too_long'), FIELD_LIMITS['contact_person']);
            } elseif ($farmName === $current['farm_name'] && $contactPerson === $current['contact_person']) {
                $error = $l($copy['account'], 'name_same');
            } else {
                DB::pdo()->prepare(
                    'UPDATE farmer_profiles SET farm_name = :farm_name, contact_person = :contact_person
                      WHERE user_id = :id'
                )->execute(['farm_name' => $farmName, 'contact_person' => $contactPerson, 'id' => $userId]);
                $success = $l($copy['account'], 'name_updated');
                security_event_log('account_name_changed', ['fields' => ['farm_name', 'contact_person']]);
                audit_log('account_name_changed', 'user', $userId);
            }
        } elseif ($role === 'buyer') {
            $fullName = trim((string) ($_POST['full_name'] ?? ''));

            $cur = DB::pdo()->prepare('SELECT full_name FROM buyer_profiles WHERE user_id = :id');
            $cur->execute(['id' => $userId]);
            $current = (string) $cur->fetchColumn();

            if ($fullName === '') {
                $error = $l($copy['account'], 'err_name_required');
            } elseif (mb_strlen($fullName) > FIELD_LIMITS['full_name']) {
                $error = sprintf($l($copy['account'], 'err_too_long'), FIELD_LIMITS['full_name']);
            } elseif ($fullName === $current) {
                $error = $l($copy['account'], 'name_same');
            } else {
                DB::pdo()->prepare('UPDATE buyer_profiles SET full_name = :full_name WHERE user_id = :id')
                    ->execute(['full_name' => $fullName, 'id' => $userId]);
                $success = $l($copy['account'], 'name_updated');
                security_event_log('account_name_changed', ['fields' => ['full_name']]);
                audit_log('account_name_changed', 'user', $userId);
            }
        } else {
            $error = $l($copy['account'], 'err_name_required');
        }
    }
}

$roleLabels  = ['farmer' => 'Farmer', 'buyer' => 'Buyer', 'admin' => 'Administrator'];
$statusLabels = [
    'active'            => $l($copy['account'], 'status_active'),
    'suspended'         => $l($copy['account'], 'status_suspended'),
    'pending_verification' => $l($copy['account'], 'status_pending'),
];
$profileRole = $roleLabels[$role] ?? ucfirst((string) $role);
$profileStatus = $statusLabels[$user['status']] ?? ucfirst((string) $user['status']);
$memberSince = $user['created_at'] ? date('M Y', strtotime($user['created_at'])) : '—';
$lastLogin = $user['last_login_at'] ? date('M j, g:i A', strtotime($user['last_login_at'])) : 'Never';

// Prefill for name form: read after POST so successful update
// shows new value. Admins have no profile table.
$nameValues = ['full_name' => '', 'farm_name' => '', 'contact_person' => ''];
if ($role === 'farmer') {
    $nStmt = DB::pdo()->prepare('SELECT farm_name, contact_person FROM farmer_profiles WHERE user_id = :id');
    $nStmt->execute(['id' => $userId]);
    $n = $nStmt->fetch();
    if ($n) {
        $nameValues['farm_name'] = (string) $n['farm_name'];
        $nameValues['contact_person'] = (string) $n['contact_person'];
    }
} elseif ($role === 'buyer') {
    $nStmt = DB::pdo()->prepare('SELECT full_name FROM buyer_profiles WHERE user_id = :id');
    $nStmt->execute(['id' => $userId]);
    $nameValues['full_name'] = (string) $nStmt->fetchColumn();
}

$activePage = 'account';
$pageTitle = $l($copy['account'], 'page_title');
require __DIR__ . '/partials/dashboard-layout.php';
?>
<div class="settings-wrap">
    <div class="settings-container">
        <div class="settings-header">
            <h1><?= e($l($copy['account'], 'page_title')) ?></h1>
            <p><?= e($l($copy['account'], 'page_description')) ?></p>
        </div>

        <fieldset class="account-profile">
            <legend><?= e($l($copy['account'], 'section_profile')) ?></legend>
            <dl class="account-meta">
                <div>
                    <dt><?= e($l($copy['account'], 'label_email')) ?></dt>
                    <dd><?= e($user['email']) ?></dd>
                </div>
                <div>
                    <dt><?= e($l($copy['account'], 'label_role')) ?></dt>
                    <dd><?= e($profileRole) ?></dd>
                </div>
                <div>
                    <dt><?= e($l($copy['account'], 'label_status')) ?></dt>
                    <dd><?= e($profileStatus) ?></dd>
                </div>
                <div>
                    <dt><?= e($l($copy['account'], 'label_member_since')) ?></dt>
                    <dd><?= e($memberSince) ?></dd>
                </div>
                <div>
                    <dt><?= e($l($copy['account'], 'label_last_login')) ?></dt>
                    <dd><?= e($lastLogin) ?></dd>
                </div>
            </dl>
        </fieldset>

        <hr class="account-divider">

        <?php if (!empty($error)): ?>
            <div class="alert alert-danger" role="alert">
                <i class="bi bi-exclamation-circle me-1"></i> <?= e($error) ?>
            </div>
        <?php endif; ?>
        <?php if (!empty($success)): ?>
            <div class="alert alert-success" role="alert">
                <i class="bi bi-check-circle me-1"></i> <?= e($success) ?>
            </div>
        <?php endif; ?>

        <?php if ($role === 'buyer' || $role === 'farmer'): ?>
            <form method="post" action="account.php" class="account-form">
                <?= csrf_field() ?>
                <input type="hidden" name="account_action" value="name">
                <fieldset>
                    <legend><?= e($l($copy['account'], 'section_name')) ?></legend>
                    <p class="account-hint"><?= e($l($copy['account'], 'hint_name')) ?></p>
                    <?php if ($role === 'farmer'): ?>
                        <div class="form-group">
                            <label class="form-label" for="farm_name"><?= e($l($copy['account'], 'label_farm_name')) ?></label>
                            <input class="form-control" type="text" id="farm_name" name="farm_name"
                                   value="<?= e($nameValues['farm_name']) ?>" required
                                   maxlength="<?= FIELD_LIMITS['farm_name'] ?>">
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="contact_person"><?= e($l($copy['account'], 'label_contact_person')) ?></label>
                            <input class="form-control" type="text" id="contact_person" name="contact_person"
                                   value="<?= e($nameValues['contact_person']) ?>" required
                                   maxlength="<?= FIELD_LIMITS['contact_person'] ?>">
                        </div>
                    <?php else: ?>
                        <div class="form-group">
                            <label class="form-label" for="full_name"><?= e($l($copy['account'], 'label_full_name')) ?></label>
                            <input class="form-control" type="text" id="full_name" name="full_name"
                                   value="<?= e($nameValues['full_name']) ?>" autocomplete="name" required
                                   maxlength="<?= FIELD_LIMITS['full_name'] ?>">
                        </div>
                    <?php endif; ?>
                    <div class="form-group">
                        <label class="form-label" for="name_current_password"><?= e($l($copy['account'], 'label_current_password')) ?></label>
                        <input class="form-control" type="password" id="name_current_password" name="current_password"
                               autocomplete="current-password" required>
                    </div>
                    <button class="btn btn-primary" type="submit"><?= e($l($copy['account'], 'btn_save_name')) ?></button>
                </fieldset>
            </form>
        <?php endif; ?>

        <form method="post" action="account.php" class="account-form">
            <?= csrf_field() ?>
            <input type="hidden" name="account_action" value="email">
            <fieldset>
                <legend><?= e($l($copy['account'], 'section_email')) ?></legend>
                <div class="form-group">
                    <label class="form-label" for="email"><?= e($l($copy['account'], 'label_email')) ?></label>
                    <input class="form-control" type="email" id="email" name="email"
                           value="<?= e($user['email']) ?>" autocomplete="email" required maxlength="<?= FIELD_LIMITS['email'] ?>">
                </div>
                <div class="form-group">
                    <label class="form-label" for="email_current_password"><?= e($l($copy['account'], 'label_current_password')) ?></label>
                    <input class="form-control" type="password" id="email_current_password" name="current_password"
                           autocomplete="current-password" required>
                </div>
                <button class="btn btn-primary" type="submit"><?= e($l($copy['account'], 'btn_save_email')) ?></button>
            </fieldset>
        </form>

        <form method="post" action="account.php" class="account-form">
            <?= csrf_field() ?>
            <input type="hidden" name="account_action" value="password">
            <fieldset>
                <legend><?= e($l($copy['account'], 'section_password')) ?></legend>
                <div class="form-group">
                    <label class="form-label" for="current_password"><?= e($l($copy['account'], 'label_current_password')) ?></label>
                    <input class="form-control" type="password" id="current_password" name="current_password"
                           autocomplete="current-password" required>
                </div>
                <div class="form-group">
                    <label class="form-label" for="new_password"><?= e($l($copy['account'], 'label_new_password')) ?></label>
                    <input class="form-control" type="password" id="new_password" name="new_password"
                           autocomplete="new-password" minlength="8" maxlength="<?= FIELD_LIMITS['password'] ?>" required>
                </div>
                <div class="form-group">
                    <label class="form-label" for="confirm_password"><?= e($l($copy['account'], 'label_confirm_password')) ?></label>
                    <input class="form-control" type="password" id="confirm_password" name="confirm_password"
                           autocomplete="new-password" minlength="8" maxlength="<?= FIELD_LIMITS['password'] ?>" required>
                </div>
                <button class="btn btn-primary" type="submit"><?= e($l($copy['account'], 'btn_save_password')) ?></button>
                <a href="dashboard.php" class="btn btn-outline"><?= e($l($copy['account'], 'btn_cancel')) ?></a>
            </fieldset>
        </form>

        <hr class="account-divider">

        <div class="account-danger">
            <h2 class="account-danger__title"><?= e($l($copy['account'], 'section_danger')) ?></h2>
            <p class="account-danger__body"><?= e($l($copy['account'], 'danger_body')) ?></p>
            <?php if ($role === 'admin'): ?>
                <button class="btn btn-danger" type="button" disabled
                        aria-disabled="true"><?= e($l($copy['account'], 'btn_delete')) ?></button>
                <p class="account-danger__note"><?= e($l($copy['account'], 'danger_admin_blocked')) ?></p>
            <?php else: ?>
                <a href="account_delete.php" class="btn btn-danger">
                    <i class="bi bi-trash3 me-1" aria-hidden="true"></i>
                    <?= e($l($copy['account'], 'btn_delete')) ?>
                </a>
            <?php endif; ?>
        </div>
    </div>
</div>
<?php require __DIR__ . '/partials/dashboard-footer.php'; ?>