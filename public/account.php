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

$stmt = DB::pdo()->prepare('SELECT id, email, role, status, created_at, last_login_at, totp_secret, totp_enabled FROM users WHERE id = :id');
$stmt->execute(['id' => $userId]);
$user = $stmt->fetch();
if (!$user) {
    redirect_to('logout.php');
}
// Keep the seed plaintext in memory only; the DB row stays encrypted.
$user['totp_secret'] = sensitive_decrypt((string) ($user['totp_secret'] ?? ''));

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
        } elseif (!filter_var($newEmail, FILTER_VALIDATE_EMAIL) || !preg_match('/^[a-zA-Z0-9._%+\-]+@[a-zA-Z0-9.\-]+\.[a-zA-Z]{2,}$/', $newEmail) || !preg_match('/@(phinmaed\.com|gmail\.com|animal\.local)$/i', $newEmail)) {
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
                audit_log('account_email_changed', 'user', $userId, null, ['changed' => 'email']);
            }
        }
    } elseif ($action === 'password') {
        $newPass = (string) ($_POST['new_password'] ?? '');

        if ($newPass === '') {
            $error = $l($copy['account'], 'err_pass_required');
        } elseif (($pwError = password_policy_error($newPass)) !== null) {
            $error = $l($copy['account'], $pwError);
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
            revoke_user_credentials($userId, true);
            $success = $l($copy['account'], 'password_updated');
            audit_log('account_password_changed', 'user', $userId);
        }
    } elseif ($action === 'twofa_setup') {
        // Storing a seed in plaintext would make a DB dump enough to bypass 2FA,
        // so setup is refused until the deployment has an encryption key.
        if (!sensitive_can_encrypt()) {
            $error = $l($copy['account'], 'err_2fa_no_key');
        } elseif (!empty($user['totp_enabled'])) {
            // A live session must not be able to silently downgrade an account
            // that already has 2FA on; turning it off goes through the password check.
            $error = $l($copy['account'], 'err_2fa_already_on');
        } else {
            // Generate a fresh secret but leave 2FA disabled until a code verifies.
            $secret = totp_generate_secret();
            $stored = sensitive_encrypt($secret);
            if ($stored === null) {
                $error = $l($copy['account'], 'err_2fa_no_key');
            } else {
                DB::pdo()->prepare('UPDATE users SET totp_secret = :s, totp_enabled = 0 WHERE id = :id')
                    ->execute(['s' => $stored, 'id' => $userId]);
                $user['totp_secret'] = $secret;
                $user['totp_enabled'] = 0;
                audit_log('account_2fa_setup_started', 'user', $userId);
            }
        }
    } elseif ($action === 'twofa_enable') {
        $secret = (string) ($user['totp_secret'] ?? '');
        $code = (string) ($_POST['totp_code'] ?? '');
        if ($secret === '') {
            $error = $l($copy['account'], 'err_2fa_setup_first');
        } elseif (!totp_verify($secret, $code)) {
            $error = $l($copy['account'], 'err_2fa_code');
        } else {
            DB::pdo()->prepare('UPDATE users SET totp_enabled = 1 WHERE id = :id')->execute(['id' => $userId]);
            $user['totp_enabled'] = 1;
            $success = $l($copy['account'], 'twofa_enabled_msg');
            audit_log('account_2fa_enabled', 'user', $userId);
        }
    } elseif ($action === 'twofa_disable') {
        if (!password_verify((string) ($_POST['current_password'] ?? ''), $hash)) {
            $error = $l($copy['account'], 'invalid_current_password');
        } else {
            DB::pdo()->prepare('UPDATE users SET totp_secret = NULL, totp_enabled = 0 WHERE id = :id')
                ->execute(['id' => $userId]);
            $user['totp_secret'] = null;
            $user['totp_enabled'] = 0;
            $success = $l($copy['account'], 'twofa_disabled_msg');
            audit_log('account_2fa_disabled', 'user', $userId);
        }
    } elseif ($action === 'name') {
        // Name change requires current password: hijacked session
        // must not be enough to repoint public identity.
        if (!password_verify((string) ($_POST['current_password'] ?? ''), $hash)) {
            $error = $l($copy['account'], 'invalid_current_password');
        } elseif ($role === 'farmer') {
            $farmName = trim((string) ($_POST['farm_name'] ?? ''));
            $contactPhone = trim((string) ($_POST['contact_phone'] ?? ''));

            $cur = DB::pdo()->prepare('SELECT farm_name, contact_phone FROM farmer_profiles WHERE user_id = :id');
            $cur->execute(['id' => $userId]);
            $current = $cur->fetch() ?: ['farm_name' => '', 'contact_phone' => ''];

            if ($farmName === '') {
                $error = $l($copy['account'], 'err_name_required');
            } elseif (mb_strlen($farmName) > FIELD_LIMITS['farm_name']) {
                $error = sprintf($l($copy['account'], 'err_too_long'), FIELD_LIMITS['farm_name']);
            } elseif (!preg_match('/^[A-Za-z0-9\s\'\-\.&,()]+$/u', $farmName) || !preg_match('/[A-Za-z0-9]/u', $farmName)) {
                $error = 'Farm name may only contain letters, numbers, spaces, and basic punctuation.';
            } elseif ($contactPhone !== '' && !preg_match('/^(09|\+639)[\d\s\-\(\)]{9,}$/', $contactPhone)) {
                $error = 'Contact number must be a valid Philippine mobile number (09XXXXXXXXX or +639XXXXXXXXX).';
            } elseif ($farmName === $current['farm_name'] && $contactPhone === ($current['contact_phone'] ?? '')) {
                $error = $l($copy['account'], 'name_same');
            } else {
                DB::pdo()->prepare(
                    'UPDATE farmer_profiles SET farm_name = :farm_name, contact_phone = :contact_phone
                      WHERE user_id = :id'
                )->execute(['farm_name' => $farmName, 'contact_phone' => $contactPhone ?: null, 'id' => $userId]);
                $success = $l($copy['account'], 'name_updated');
                security_event_log('account_name_changed', ['fields' => ['farm_name', 'contact_phone']]);
                audit_log('account_name_changed', 'user', $userId);
            }
        } elseif (in_array($role, ['buyer', 'admin', 'manager'], true)) {
            $fullName = trim((string) ($_POST['full_name'] ?? ''));

            $cur = DB::pdo()->prepare('SELECT full_name FROM buyer_profiles WHERE user_id = :id');
            $cur->execute(['id' => $userId]);
            $current = (string) $cur->fetchColumn();

            if ($fullName === '') {
                $error = $l($copy['account'], 'err_name_required');
            } elseif (mb_strlen($fullName) > FIELD_LIMITS['full_name']) {
                $error = sprintf($l($copy['account'], 'err_too_long'), FIELD_LIMITS['full_name']);
            } elseif (!preg_match('/^[A-Za-zÀ-ÿ\s\'\-\.]+$/', $fullName) || !preg_match('/[A-Za-zÀ-ÿ]/', $fullName)) {
                $error = 'Full name may only contain letters, spaces, hyphens, and apostrophes.';
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
    } elseif ($action === 'contact' && $role === 'farmer') {
        $contactEmail = trim((string) ($_POST['contact_email'] ?? ''));
        $facebook = trim((string) ($_POST['facebook_url'] ?? ''));
        $instagram = trim((string) ($_POST['instagram_url'] ?? ''));
        $tiktok = trim((string) ($_POST['tiktok_url'] ?? ''));
        $website = trim((string) ($_POST['website_url'] ?? ''));
        if ($contactEmail !== '' && !filter_var($contactEmail, FILTER_VALIDATE_EMAIL)) {
            $error = 'Invalid email address.';
        } elseif ($facebook !== '' && !filter_var($facebook, FILTER_VALIDATE_URL)) {
            $error = 'Invalid Facebook URL.';
        } elseif ($instagram !== '' && !filter_var($instagram, FILTER_VALIDATE_URL)) {
            $error = 'Invalid Instagram URL.';
        } elseif ($tiktok !== '' && !filter_var($tiktok, FILTER_VALIDATE_URL)) {
            $error = 'Invalid TikTok URL.';
        } elseif ($website !== '' && !filter_var($website, FILTER_VALIDATE_URL)) {
            $error = 'Invalid website URL.';
        } else {
            DB::pdo()->prepare(
                'UPDATE farmer_profiles
                    SET contact_email = :ce, facebook_url = :fb,
                        instagram_url = :ig, tiktok_url = :tt, website_url = :ws
                  WHERE user_id = :id'
            )->execute([
                'ce' => $contactEmail ?: null,
                'fb' => $facebook ?: null,
                'ig' => $instagram ?: null,
                'tt' => $tiktok ?: null,
                'ws' => $website ?: null,
                'id' => $userId,
            ]);
            $success = 'Contact details updated.';
            security_event_log('account_contact_changed', ['fields' => ['contact_phone','contact_email','facebook_url','instagram_url','tiktok_url','website_url']]);
            audit_log('account_name_changed', 'user', $userId);
        }
    }
}

$roleLabels  = ['farmer' => 'Farmer', 'buyer' => 'Buyer', 'admin' => 'Administrator', 'manager' => 'Manager'];
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
    $nameValues = ['full_name' => '', 'farm_name' => '', 'contact_phone' => ''];
if ($role === 'farmer') {
    $nStmt = DB::pdo()->prepare('SELECT farm_name, contact_phone FROM farmer_profiles WHERE user_id = :id');
    $nStmt->execute(['id' => $userId]);
    $n = $nStmt->fetch();
    if ($n) {
        $nameValues['farm_name'] = (string) $n['farm_name'];
        $nameValues['contact_phone'] = (string) ($n['contact_phone'] ?? '');
    }
} elseif (in_array($role, ['buyer', 'admin', 'manager'], true)) {
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

        <?php if (in_array($role, ['buyer', 'admin', 'manager', 'farmer'], true)): ?>
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
                            <label class="form-label" for="contact_phone">Contact number</label>
                            <input class="form-control" type="tel" id="contact_phone" name="contact_phone"
                                   value="<?= e($nameValues['contact_phone']) ?>"
                                   maxlength="30">
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

        <?php if ($role === 'farmer'): ?>
        <form method="post" action="account.php" class="account-form">
            <?= csrf_field() ?>
            <input type="hidden" name="account_action" value="contact">
            <fieldset>
                <legend>Contact details (shown on your farm profile)</legend>
                <p class="account-hint">Let buyers reach you directly — phone, email, social media. Chat is not available in-app.</p>
                <?php
                $cp = DB::pdo()->prepare('SELECT contact_phone, contact_email, facebook_url, instagram_url, tiktok_url, website_url FROM farmer_profiles WHERE user_id = :id');
                $cp->execute(['id' => $userId]);
                $contact = $cp->fetch() ?: [];
                ?>
                <div class="form-group">
                <div class="form-group">
                    <label class="form-label" for="contact_email">Public email</label>
                    <input class="form-control" type="email" id="contact_email" name="contact_email"
                           value="<?= e($contact['contact_email'] ?? '') ?>" maxlength="255">
                </div>
                <div class="form-group">
                    <label class="form-label" for="facebook_url">Facebook URL</label>
                    <input class="form-control" type="url" id="facebook_url" name="facebook_url"
                           value="<?= e($contact['facebook_url'] ?? '') ?>" maxlength="255" placeholder="https://facebook.com/...">
                </div>
                <div class="form-group">
                    <label class="form-label" for="instagram_url">Instagram URL</label>
                    <input class="form-control" type="url" id="instagram_url" name="instagram_url"
                           value="<?= e($contact['instagram_url'] ?? '') ?>" maxlength="255" placeholder="https://instagram.com/...">
                </div>
                <div class="form-group">
                    <label class="form-label" for="tiktok_url">TikTok URL</label>
                    <input class="form-control" type="url" id="tiktok_url" name="tiktok_url"
                           value="<?= e($contact['tiktok_url'] ?? '') ?>" maxlength="255" placeholder="https://tiktok.com/@...">
                </div>
                <div class="form-group">
                    <label class="form-label" for="website_url">Website</label>
                    <input class="form-control" type="url" id="website_url" name="website_url"
                           value="<?= e($contact['website_url'] ?? '') ?>" maxlength="255" placeholder="https://...">
                </div>
                <button class="btn btn-primary" type="submit">Save contact details</button>
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

        <form method="post" action="account.php" class="account-form">
            <?= csrf_field() ?>
            <fieldset>
                <legend><?= e($l($copy['account'], 'section_2fa')) ?></legend>
                <p class="form-text"><?= e($l($copy['account'], 'twofa_intro')) ?></p>

                <?php if (!empty($user['totp_enabled'])): ?>
                    <p class="mb-2">
                        <i class="bi bi-shield-check me-1" aria-hidden="true"></i>
                        <?= e($l($copy['account'], 'twofa_status_on')) ?>
                    </p>
                    <input type="hidden" name="account_action" value="twofa_disable">
                    <div class="form-group">
                        <label class="form-label" for="twofa_off_password"><?= e($l($copy['account'], 'label_current_password')) ?></label>
                        <input class="form-control" type="password" id="twofa_off_password" name="current_password"
                               autocomplete="current-password" required>
                    </div>
                    <button class="btn btn-outline-danger" type="submit"><?= e($l($copy['account'], 'twofa_disable_btn')) ?></button>
                <?php elseif (!empty($user['totp_secret'])): ?>
                    <p class="mb-2"><?= e($l($copy['account'], 'twofa_step1')) ?></p>
                    <div class="form-group">
                        <label class="form-label" for="twofa_secret"><?= e($l($copy['account'], 'twofa_manual_key')) ?></label>
                        <input class="form-control font-monospace" type="text" id="twofa_secret" readonly
                               value="<?= e(implode(' ', str_split((string) $user['totp_secret'], 4))) ?>">
                    </div>
                    <p class="mb-3">
                        <a href="<?= e(totp_provisioning_uri((string) $user['totp_secret'], (string) $user['email'])) ?>">
                            <?= e($l($copy['account'], 'twofa_open_app')) ?>
                        </a>
                    </p>
                    <input type="hidden" name="account_action" value="twofa_enable">
                    <p class="mb-2"><?= e($l($copy['account'], 'twofa_step2')) ?></p>
                    <div class="form-group">
                        <label class="form-label" for="twofa_code"><?= e($l($copy['account'], 'label_totp_code')) ?></label>
                        <input class="form-control" type="text" id="twofa_code" name="totp_code"
                               inputmode="numeric" maxlength="6" pattern="[0-9]*" autocomplete="one-time-code" required>
                    </div>
                    <button class="btn btn-primary" type="submit"><?= e($l($copy['account'], 'twofa_enable_btn')) ?></button>
                <?php else: ?>
                    <p class="mb-2">
                        <i class="bi bi-shield-exclamation me-1" aria-hidden="true"></i>
                        <?= e($l($copy['account'], 'twofa_status_off')) ?>
                    </p>
                    <input type="hidden" name="account_action" value="twofa_setup">
                    <button class="btn btn-primary" type="submit"><?= e($l($copy['account'], 'twofa_setup_btn')) ?></button>
                <?php endif; ?>
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