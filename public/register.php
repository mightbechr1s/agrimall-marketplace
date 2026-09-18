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
    }

    if ($fullName === '') {
        $errors[] = $l($copy['auth'], 'err_full_name');
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = $l($copy['auth'], 'err_email_valid');
    }
    if (strlen($password) < 8) {
        $errors[] = $l($copy['auth'], 'err_pass_min');
    }
    if ($password !== $passwordConfirm) {
        $errors[] = $l($copy['auth'], 'err_pass_match');
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
        <div class="auth-left-content">
            <img src="assets/images/products/seedlings.jpg"
                 alt="Filipino farmer in a rice field"
                 onerror="this.onerror=null; this.src='assets/branding/mark.svg'">
            <h2><?= $l($copy['auth'], 'reg_title') ?></h2>
            <p><?= $l($copy['auth'], 'reg_sub') ?></p>
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
                           value="<?= e($old['full_name'] ?? '') ?>" required placeholder="Juan Dela Cruz" />
                </div>

                <fieldset id="farmer-fields" class="farmer-fields" <?= ($old['role'] ?? 'buyer') === 'farmer' ? '' : 'style="display:none;"' ?>>
                    <legend><?= $l($copy['auth'], 'farm_details') ?></legend>
                    <div class="farm-grid">
                        <div class="span-2">
                            <label class="form-label required" for="farm_name"><?= $l($copy['auth'], 'farm_name') ?></label>
                            <input class="form-control" type="text" id="farm_name" name="farm_name"
                                   value="<?= e($old['farm_name'] ?? '') ?>" placeholder="Green Valley Farm" />
                        </div>
                        <div class="span-2">
                            <label class="form-label required" for="contact_person"><?= $l($copy['auth'], 'contact_person') ?></label>
                            <input class="form-control" type="text" id="contact_person" name="contact_person"
                                   placeholder="<?= $l($copy['auth'], 'contact_placeholder') ?>" value="<?= e($old['contact_person'] ?? '') ?>" />
                        </div>
                        <div>
                            <label class="form-label required" for="barangay"><?= $l($copy['auth'], 'barangay') ?></label>
                            <input class="form-control" type="text" id="barangay" name="barangay"
                                   value="<?= e($old['barangay'] ?? '') ?>" />
                        </div>
                        <div>
                            <label class="form-label required" for="municipality_city"><?= $l($copy['auth'], 'municipality') ?></label>
                            <input class="form-control" type="text" id="municipality_city" name="municipality_city"
                                   value="<?= e($old['municipality_city'] ?? '') ?>" />
                        </div>
                        <div>
                            <label class="form-label" for="province"><?= $l($copy['auth'], 'province') ?></label>
                            <input class="form-control" type="text" id="province" name="province"
                                   value="<?= e($old['province'] ?? '') ?>" />
                        </div>
                        <div>
                            <label class="form-label" for="region"><?= $l($copy['auth'], 'region') ?></label>
                            <input class="form-control" type="text" id="region" name="region"
                                   placeholder="<?= $l($copy['auth'], 'region_placeholder') ?>"
                                   value="<?= e($old['region'] ?? '') ?>" />
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
                                   value="<?= e($old['valid_id_number'] ?? '') ?>" />
                        </div>
                    </div>
                    <p class="farm-note">
                        <i class="bi bi-info-circle me-1"></i><?= $l($copy['auth'], 'farmer_verify_note') ?>
                    </p>
                </fieldset>

                <div style="margin-bottom: 18px;">
                    <label class="form-label required" for="email"><?= $l($copy['auth'], 'email') ?></label>
                    <input class="form-control" type="email" id="email" name="email"
                           value="<?= e($old['email'] ?? '') ?>" required placeholder="you@example.com" />
                </div>
                <div class="auth-row-2">
                    <div>
                        <label class="form-label required" for="password"><?= $l($copy['auth'], 'password') ?></label>
                        <input class="form-control" type="password" id="password" name="password"
                               minlength="8" required placeholder="Min. 8 characters" />
                    </div>
                    <div>
                        <label class="form-label required" for="password_confirm"><?= $l($copy['auth'], 'confirm_password') ?></label>
                        <input class="form-control" type="password" id="password_confirm" name="password_confirm"
                               minlength="8" required placeholder="Confirm" />
                    </div>
                    <div style="margin-bottom: 1.5rem;">
                        <label class="form-check form-check-inline d-flex align-items-center gap-2" for="consent" style="cursor: pointer;">
                            <input class="form-check-input" type="checkbox" value="1" id="consent" name="consent"
                                   <?= isset($old['consent']) && $old['consent'] === '1' ? 'checked' : '' ?> required>
                            <span class="form-check-label" style="margin-bottom: 0;">
                                <?= $l($copy['auth'], 'consent_label') ?>
                            </span>
                        </label>
                        <?php if ($_SERVER['REQUEST_METHOD'] === 'POST' && !($old['consent'] ?? '') === '1'): ?>
                        <div class="invalid-feedback d-block">
                            <?= $l($copy['auth'], 'err_consent') ?>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

                <button class="btn btn-primary btn-lg w-100" type="submit">
                    <?= $l($copy['auth'], 'create_acc_btn') ?>
                </button>
            </form>

            <script>
            (() => {
                const fields = document.getElementById('farmer-fields');
                const farmerInputs = fields.querySelectorAll('input, select');
                const updateRoleFields = () => {
                    const isFarmer = document.querySelector('input[name="role"]:checked')?.value === 'farmer';
                    fields.style.display = isFarmer ? '' : 'none';
                    farmerInputs.forEach((input) => {
                        input.required = isFarmer;
                        input.disabled = !isFarmer;
                    });
                };
                document.querySelectorAll('input[name="role"]').forEach((input) => {
                    input.addEventListener('change', updateRoleFields);
                });
                updateRoleFields();
            })();
            </script>

            <div class="auth-footer">
                <?= $l($copy['auth'], 'have_account') ?> <a href="login.php"><?= $l($copy['auth'], 'login_link') ?></a>
            </div>
        </div>
    </div>
</main>

<?php require __DIR__ . '/partials/footer.php'; ?>