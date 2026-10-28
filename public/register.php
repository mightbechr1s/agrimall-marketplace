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

$errors = [];
$old = [];
$passwordError = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_abort_if_invalid($_POST['csrf_token'] ?? null);

    $old = $_POST;
    $fullName = trim((string) ($_POST['full_name'] ?? ''));
    $email = trim((string) ($_POST['email'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');
    $passwordConfirm = (string) ($_POST['password_confirm'] ?? '');
    $role = ($_POST['role'] ?? '') === 'farmer' ? 'farmer' : 'buyer';
    $old['role'] = $role;

    $farmer = [];
    $allowedIdTypes = ['national_id', 'drivers_license', 'passport', 'voters_id', 'postal_id', 'senior_citizen_id', 'pwd_id'];
    if ($role === 'farmer') {
        $farmerLabels = [
            'farm_name' => $l($copy['auth'], 'farm_name'),
            'contact_person' => $l($copy['auth'], 'contact_person'),
            'barangay' => $l($copy['auth'], 'barangay'),
            'municipality_city' => $l($copy['auth'], 'municipality'),
            'province' => $l($copy['auth'], 'province'),
            'region' => $l($copy['auth'], 'region'),
            'valid_id_type' => $l($copy['auth'], 'valid_id_type'),
            'valid_id_number' => $l($copy['auth'], 'valid_id_number'),
        ];
        foreach ($farmerLabels as $key => $label) {
            $farmer[$key] = trim((string) ($_POST[$key] ?? ''));
            if ($farmer[$key] === '') {
                $errors[] = str_replace(':label', $label, $l($copy['auth'], 'err_fill'));
            }
        }
        if (!in_array($farmer['valid_id_type'] ?? '', $allowedIdTypes, true)) {
            $errors[] = $l($copy['auth'], 'err_valid_id');
        }
        if (!preg_match('/^(09|\+639)\d{9}$/', $farmer['contact_phone'] ?? '')) {
            $errors[] = 'Contact phone may only contain digits, spaces, +, -, and parentheses.';
        }
        if (!preg_match('/^[A-Za-z0-9\-]{3,50}$/', $farmer['valid_id_number'] ?? '')) {
            $errors[] = 'Valid ID number may only contain letters, digits, and hyphens.';
        }
    }

    if ($fullName === '') {
        $errors[] = $l($copy['auth'], 'err_full_name');
    } elseif (!preg_match('/^[A-Za-zÀ-ÿ\s\'\-\.]+$/u', $fullName) || !preg_match('/[A-Za-zÀ-ÿ]/u', $fullName)) {
        $errors[] = 'Full name may only contain letters, spaces, hyphens, and apostrophes.';
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || !preg_match('/^[a-zA-Z0-9._%+\-]+@[a-zA-Z0-9.\-]+\.[a-zA-Z]{2,}$/', $email) || !preg_match('/@(phinmaed\.com|gmail\.com|animal\.local)$/i', $email)) {
        $errors[] = $l($copy['auth'], 'err_email_valid');
    }
    $passwordError = password_policy_error($password);
    if ($passwordError !== null) {
        $errors[] = $l($copy['auth'], $passwordError);
    }
    if ($password !== $passwordConfirm) {
        $errors[] = $l($copy['auth'], 'err_pass_match');
    }

    foreach (over_limit_fields($_POST) as $field => $max) {
        $errors[] = sprintf($l($copy['auth'], 'err_too_long'), $max);
    }

    $consent = isset($_POST['consent']) && $_POST['consent'] === '1';
    if (!$consent) {
        $errors[] = $l($copy['auth'], 'err_consent');
    }

    if (!$errors) {
        $check = DB::pdo()->prepare('SELECT id FROM users WHERE email = :email LIMIT 1');
        $check->execute(['email' => $email]);
        if ($check->fetch()) {
            $errors[] = $l($copy['auth'], 'err_email_exists');
        }
    }

    if (!$errors) {
        try {
            $status = $role === 'farmer' ? 'pending_verification' : 'active';
            $insert = DB::pdo()->prepare(
                'INSERT INTO users (email, password_hash, role, status)
                 VALUES (:email, :hash, :role, :status)'
            );
            $insert->execute([
                'email' => $email,
                'hash' => password_hash($password, PASSWORD_DEFAULT),
                'role' => $role,
                'status' => $status,
            ]);
            $userId = (int) DB::pdo()->lastInsertId();

            if ($role === 'buyer') {
                DB::pdo()
                    ->prepare('INSERT INTO buyer_profiles (user_id, full_name) VALUES (:user_id, :full_name)')
                    ->execute(['user_id' => $userId, 'full_name' => $fullName]);
            } else {
                $contactPerson = $farmer['contact_person'] !== '' ? $farmer['contact_person'] : $fullName;
                DB::pdo()
                    ->prepare(
                        'INSERT INTO farmer_profiles
                            (user_id, farm_name, contact_person, contact_email,
                             address, barangay, municipality_city, province, region,
                             valid_id_type, valid_id_number, verification_status)
                         VALUES
                            (:user_id, :farm_name, :contact_person, :email,
                             :address, :barangay, :municipality, :province, :region,
                             :valid_id_type, :valid_id_number, :verification_status)'
                    )
                    ->execute([
                        'user_id' => $userId,
                        'farm_name' => $farmer['farm_name'],
                        'contact_person' => $contactPerson,
                        'email' => $email,
                        'address' => sprintf('%s, %s, %s', $farmer['barangay'], $farmer['municipality_city'], $farmer['province']),
                        'barangay' => $farmer['barangay'],
                        'municipality' => $farmer['municipality_city'],
                        'province' => $farmer['province'],
                        'region' => $farmer['region'],
                        'valid_id_type' => $farmer['valid_id_type'],
                        'valid_id_number' => $farmer['valid_id_number'],
                        'verification_status' => 'pending',
                    ]);
            }

            flash_set('success', $role === 'farmer'
                ? $l($copy['auth'], 'flash_farmer_reg')
                : $l($copy['auth'], 'flash_buyer_reg'));
            redirect_to('login.php');
        } catch (PDOException $ex) {
            error_log($ex->getMessage());
            $errors[] = $l($copy['auth'], 'err_generic');
        }
    }
}

$pageTitle = $l($copy['auth'], 'reg_title');
require __DIR__ . '/partials/header.php';
?>
<main id="main-content" class="auth-wrap">
    <div class="auth-left">
        <div class="auth-left-content" aria-hidden="true">
            <img class="auth-left-logo" src="assets/branding/logo-reverse.svg" alt="" width="150" height="42">
            <h2><?= $l($copy['auth'], 'reg_title') ?></h2>
            <p><?= $l($copy['auth'], 'reg_sub') ?></p>
        </div>
    </div>
    <div class="auth-right">
        <div class="auth-card">
            <div class="auth-logo">
                <a href="index.php"><img src="assets/branding/logo-horizontal.svg" alt="AniMALL"></a>
            </div>
            <h1 class="auth-title"><?= $l($copy['auth'], 'reg_title') ?></h1>
            <p class="auth-subtitle"><?= $l($copy['auth'], 'reg_sub') ?></p>

            <?php if ($errors): ?>
                <div class="alert alert-danger" role="alert">
                    <i class="bi bi-exclamation-circle me-1"></i>
                    <ul class="mb-0" style="padding-left: 18px; margin-top: 4px;">
                        <?php foreach ($errors as $err): ?>
                            <li><?= e($err) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <form method="post" action="register.php">
                <?= csrf_field() ?>

                <div style="margin-bottom: 20px;">
                    <span class="form-label d-block" style="font-size:14px; font-weight:600; margin-bottom:8px;"><?= $l($copy['auth'], 'register_as') ?></span>
                    <div class="role-selection">
                        <label class="role-option">
                            <input type="radio" name="role" value="buyer"
                                   <?= ($old['role'] ?? 'buyer') === 'buyer' ? 'checked' : '' ?> />
                            <i class="bi bi-cart role-icon"></i>
                            <span class="role-text"><?= $l($copy['auth'], 'buyer') ?></span>
                        </label>
                        <label class="role-option">
                            <input type="radio" name="role" value="farmer"
                                   <?= ($old['role'] ?? 'buyer') === 'farmer' ? 'checked' : '' ?> />
                            <i class="bi bi-tree-fill role-icon"></i>
                            <span class="role-text"><?= $l($copy['auth'], 'farmer') ?></span>
                        </label>
                    </div>
                </div>

                <div style="margin-bottom: 18px;">
                    <label class="form-label required" for="full_name"><?= $l($copy['auth'], 'full_name') ?></label>
                    <input class="form-control" type="text" id="full_name" name="full_name"
                           value="<?= e($old['full_name'] ?? '') ?>" required placeholder="Juan Dela Cruz" maxlength="<?= FIELD_LIMITS['full_name'] ?>" />
                </div>

                <fieldset id="farmer-fields" class="farmer-fields<?= ($old['role'] ?? 'buyer') === 'farmer' ? ' is-open' : '' ?>">
                    <legend><?= $l($copy['auth'], 'farm_details') ?></legend>
                    <div class="collapse">
                    <div class="collapse-inner">
                    <div class="farm-grid">
                        <div class="span-2">
                            <label class="form-label required" for="farm_name"><?= $l($copy['auth'], 'farm_name') ?></label>
                            <input class="form-control" type="text" id="farm_name" name="farm_name"
                                   value="<?= e($old['farm_name'] ?? '') ?>" placeholder="Green Valley Farm" maxlength="<?= FIELD_LIMITS['farm_name'] ?>" />
                        </div>
                        <div class="span-2">
                            <label class="form-label required" for="contact_person"><?= $l($copy['auth'], 'contact_person') ?></label>
                            <input class="form-control" type="text" id="contact_person" name="contact_person"
                                   value="<?= e($old['contact_person'] ?? '') ?>" placeholder="Juan dela Cruz" maxlength="<?= FIELD_LIMITS['contact_person'] ?>"
                                   aria-describedby="contact_person-help" />
                            <div class="form-text" id="contact_person-help"><?= $l($copy['auth'], 'contact_helper') ?></div>
                        </div>
                        <div>
                            <label class="form-label required" for="barangay"><?= $l($copy['auth'], 'barangay') ?></label>
                            <input class="form-control" type="text" id="barangay" name="barangay"
                                   value="<?= e($old['barangay'] ?? '') ?>" placeholder="San Roque" maxlength="<?= FIELD_LIMITS['barangay'] ?>" />
                        </div>
                        <div>
                            <label class="form-label required" for="municipality_city"><?= $l($copy['auth'], 'municipality') ?></label>
                            <input class="form-control" type="text" id="municipality_city" name="municipality_city"
                                   value="<?= e($old['municipality_city'] ?? '') ?>" placeholder="Lucban" maxlength="<?= FIELD_LIMITS['municipality_city'] ?>" />
                        </div>
                        <div>
                            <label class="form-label" for="province"><?= $l($copy['auth'], 'province') ?></label>
                            <input class="form-control" type="text" id="province" name="province"
                                   value="<?= e($old['province'] ?? '') ?>" placeholder="Quezon" maxlength="<?= FIELD_LIMITS['province'] ?>" />
                        </div>
                        <div>
                            <label class="form-label" for="region"><?= $l($copy['auth'], 'region') ?></label>
                            <input class="form-control" type="text" id="region" name="region"
                                   placeholder="<?= $l($copy['auth'], 'region_placeholder') ?>"
                                   value="<?= e($old['region'] ?? '') ?>" maxlength="<?= FIELD_LIMITS['region'] ?>" />
                        </div>
                        <div>
                            <label class="form-label" for="valid_id_type"><?= $l($copy['auth'], 'valid_id_type') ?></label>
                            <select class="form-select" id="valid_id_type" name="valid_id_type">
                                <option value=""><?= $l($copy['auth'], 'choose') ?></option>
                                <?php foreach (['national_id', 'drivers_license', 'passport', 'voters_id', 'postal_id', 'senior_citizen_id', 'pwd_id'] as $idType): ?>
                                    <option value="<?= e($idType) ?>" <?= ($old['valid_id_type'] ?? '') === $idType ? 'selected' : '' ?>><?= e(str_replace('_', ' ', ucwords($idType, '_'))) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label class="form-label" for="valid_id_number"><?= $l($copy['auth'], 'valid_id_number') ?></label>
                            <input class="form-control" type="text" id="valid_id_number" name="valid_id_number"
                                   value="<?= e($old['valid_id_number'] ?? '') ?>" placeholder="ABC-123456" maxlength="<?= FIELD_LIMITS['valid_id_number'] ?>"
                                   aria-describedby="valid_id_number-help" />
                            <div class="form-text" id="valid_id_number-help"><?= $l($copy['auth'], 'valid_id_number_hint') ?></div>
                        </div>
                    </div>
                    <p class="farm-note">
                        <i class="bi bi-info-circle me-1"></i><?= $l($copy['auth'], 'farmer_verify_note') ?>
                    </p>
                    </div>
                    </div>
                </fieldset>

                <div style="margin-bottom: 18px;">
                    <?php
                    $label = $l($copy['auth'], 'email');
                    $name = 'email';
                    $type = 'email';
                    $value = $old['email'] ?? '';
                    $placeholder = 'you@example.com';
                    $required = true;
                    $attrs = ['maxlength' => FIELD_LIMITS['email']];
                    $error = in_array($l($copy['auth'], 'err_email_valid'), $errors) ? $l($copy['auth'], 'err_email_valid') : '';
                    include __DIR__ . '/partials/form-field.php';
                    ?>
                </div>
                <div class="auth-row-2">
                    <div>
                        <?php
                        $label = $l($copy['auth'], 'password');
                        $name = 'password';
                        $type = 'password';
                        $value = '';
                        $placeholder = 'Min. 8 characters';
                        $required = true;
                        $attrs = ['minlength' => 8, 'maxlength' => FIELD_LIMITS['password'], 'autocomplete' => 'new-password'];
                        $error = $passwordError !== null ? $l($copy['auth'], $passwordError) : (in_array($l($copy['auth'], 'err_pass_match'), $errors) ? $l($copy['auth'], 'err_pass_match') : '');
                        include __DIR__ . '/partials/form-field.php';
                        ?>
                        <ul class="pw-rules" id="pw-rules" hidden aria-label="<?= $l($copy['auth'], 'pw_strength') ?>">
                            <li data-rule="length"><i class="bi bi-check-circle-fill" aria-hidden="true"></i><?= $l($copy['auth'], 'pw_rule_length') ?></li>
                            <li data-rule="case"><i class="bi bi-check-circle-fill" aria-hidden="true"></i><?= $l($copy['auth'], 'pw_rule_case') ?></li>
                            <li data-rule="number"><i class="bi bi-check-circle-fill" aria-hidden="true"></i><?= $l($copy['auth'], 'pw_rule_number') ?></li>
                        </ul>
                    </div>
                    <div>
                        <?php
                        $label = $l($copy['auth'], 'confirm_password');
                        $name = 'password_confirm';
                        $type = 'password';
                        $value = '';
                        $placeholder = 'Confirm';
                        $required = true;
                        $attrs = ['minlength' => 8, 'maxlength' => FIELD_LIMITS['password']];
                        $error = in_array($l($copy['auth'], 'err_pass_match'), $errors) ? $l($copy['auth'], 'err_pass_match') : '';
                        include __DIR__ . '/partials/form-field.php';
                        ?>
                    </div>
                    </div>
                    <div style="margin-bottom: 1.5rem;">
                        <label class="form-check d-flex align-items-start gap-2 w-100" for="consent" style="cursor: pointer; margin-bottom: 0.4rem;">
                            <input class="form-check-input mt-1" type="checkbox" value="1" id="consent" name="consent"
                                   <?= isset($old['consent']) && $old['consent'] === '1' ? 'checked' : '' ?> required disabled>
                            <span class="form-check-label" style="margin-bottom: 0;">
                                <?= $l($copy['auth'], 'consent_label') ?>
                            </span>
                        </label>
                        <p class="consent-helper small text-muted mt-1 mb-0" style="line-height:1.5; text-align:justify; padding:0.65rem 0.85rem; background:rgba(0,0,0,0.035); border-left:3px solid var(--gold, #C9A227); border-radius:0.3rem;">
                            <i class="bi bi-shield-check me-1" aria-hidden="true"></i>
                            <?= $l($copy['auth'], 'consent_helper') ?>
                        </p>
                        <p class="small text-muted mt-1 mb-0" id="consent-hint">Please read the Privacy Policy and Terms of Use first.</p>
                        <script>
                        (function(){
                          function init() {
                            var consent = document.getElementById('consent');
                            var hint = document.getElementById('consent-hint');
                            if (!consent) return;
                          var modalBody = document.getElementById('privacyTermsBody');
                          var closeBtn = document.getElementById('privacyTermsClose');
                          if (closeBtn) closeBtn.disabled = true;
                          function syncCloseState() {
                            if (!modalBody || !closeBtn) return;
                            if (modalBody.scrollTop + modalBody.clientHeight >= modalBody.scrollHeight - 5) {
                              closeBtn.disabled = false;
                            }
                          }
                          if (modalBody) {
                            modalBody.addEventListener('scroll', syncCloseState);
                            // Enable immediately when the content already fits (no scrollbar).
                            syncCloseState();
                          } else if (closeBtn) {
                            closeBtn.disabled = false;
                          }
                            function showModal() {
                              var m = document.getElementById('privacyTermsModal');
                              if (modalBody) modalBody.scrollTop = 0;
                              if (closeBtn) closeBtn.disabled = true;
                              syncCloseState();
                              if (m) m.style.display = 'flex';
                            }
                            function closeModal() {
                              var m = document.getElementById('privacyTermsModal');
                              if (m) m.style.display = 'none';
                              consent.disabled = false;
                              if (hint) hint.textContent = 'Thank you for reading. You may now agree.';
                            }
                            document.querySelectorAll('.form-check-label a[href*="privacy.php"], .form-check-label a[href*="terms.php"]').forEach(function(a){
                              a.addEventListener('click', function(e){
                                e.preventDefault();
                                showModal();
                              });
                            });
                            var closeBtn = document.getElementById('privacyTermsClose');
                            if (closeBtn) closeBtn.addEventListener('click', closeModal);
                          }
                          if (document.readyState === 'loading') {
                            document.addEventListener('DOMContentLoaded', init);
                          } else {
                            init();
                          }
                        })();
                        </script>
                        <div id="privacyTermsModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.5);z-index:9999;align-items:center;justify-content:center;padding:1rem;">
                          <div id="privacyTermsBody" style="background:#fff;max-width:700px;width:100%;margin:auto;padding:2rem;border-radius:8px;max-height:70vh;overflow:auto;position:relative;">
                            <h2>Privacy Policy & Terms of Use</h2>
                            <h3>Privacy Policy</h3>
                            <p>We respect your privacy. Your data is used only to operate the AniMALL marketplace and is never sold to third parties.</p>
                            <p>We collect account details, order history, and usage data to provide and improve the service.</p>
                            <p>Your personal information is stored securely and is only accessible to authorized personnel. We do not share your data with third-party advertisers.</p>
                            <p>You may request deletion of your account and associated data at any time through the account settings page.</p>
                            <p>Cookies are used solely for session management and preference storage. No tracking cookies are employed.</p>
                            <h3>Terms of Use</h3>
                            <p>By creating an account, you agree to use AniMALL responsibly and not to post misleading or fraudulent listings.</p>
                            <p>Farmers must verify their identity before selling. Buyers must honor confirmed orders.</p>
                            <p>All transactions are subject to our cancellation and refund policies as described in the order section.</p>
                            <p>AniMALL reserves the right to suspend accounts that violate these terms or engage in fraudulent activity.</p>
                            <p>Prices and availability are set by farmers and may change without notice. Always confirm before ordering.</p>
                            <p>By proceeding, you acknowledge that you have read and understood both documents in full.</p>
                            <div class="text-center mt-3">
                              <button type="button" class="btn btn-primary" id="privacyTermsClose" disabled>I have read both — close</button>
                            </div>
                          </div>
                        </div>
                        <?php if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($old['consent'] ?? '') !== '1'): ?>
                        <div class="invalid-feedback d-block">
                            <?= $l($copy['auth'], 'err_consent') ?>
                        </div>
                        <?php endif; ?>
                    </div>

                <button class="btn btn-primary btn-lg w-100" type="submit" id="create-acc-btn" data-label="<?= e($l($copy['auth'], 'create_acc_btn')) ?>" data-busy-label="<?= e($l($copy['auth'], 'creating_acc')) ?>">
                    <span class="btn-label"><?= $l($copy['auth'], 'create_acc_btn') ?></span>
                    <span class="spinner-border spinner-border-sm btn-spinner" aria-hidden="true" hidden></span>
                </button>
            </form>

            <script>
            (() => {
                const form = document.querySelector('form[action="register.php"]');
                const fields = document.getElementById('farmer-fields');
                const farmerInputs = fields.querySelectorAll('input, select');
                const updateRoleFields = () => {
                    const isFarmer = document.querySelector('input[name="role"]:checked')?.value === 'farmer';
                    fields.classList.toggle('is-open', isFarmer);
                    farmerInputs.forEach((input) => {
                        input.required = isFarmer;
                        input.disabled = !isFarmer;
                    });
                };
                document.querySelectorAll('input[name="role"]').forEach((input) => {
                    input.addEventListener('change', updateRoleFields);
                });
                updateRoleFields();

                // Password strength checklist
                const pw = document.getElementById('field-password');
                const rules = document.getElementById('pw-rules');
                if (pw && rules) {
                    const checks = {
                        length: (v) => v.length >= 8,
                        case: (v) => /[A-Z]/.test(v),
                        number: (v) => /[0-9]/.test(v),
                    };
                    const render = () => {
                        const v = pw.value;
                        rules.hidden = v.length === 0;
                        rules.querySelectorAll('li[data-rule]').forEach((li) => {
                            li.classList.toggle('is-met', checks[li.dataset.rule](v));
                        });
                    };
                    pw.addEventListener('input', render);
                    render();
                }

                // Prevent double submit
                const btn = document.getElementById('create-acc-btn');
                if (btn) {
                    form.addEventListener('submit', () => {
                        btn.disabled = true;
                        btn.setAttribute('aria-busy', 'true');
                        btn.querySelector('.btn-label').textContent = btn.dataset.busyLabel;
                        btn.querySelector('.btn-spinner').hidden = false;
                    });
                }
            })();
            </script>

            <div class="auth-footer">
                <?= $l($copy['auth'], 'have_account') ?> <a href="login.php"><?= $l($copy['auth'], 'login_link') ?></a>
            </div>
        </div>
    </div>
</main>

<?php require __DIR__ . '/partials/footer.php'; ?>