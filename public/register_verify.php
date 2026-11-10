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

$pending = $_SESSION['pending_registration'] ?? null;
if (!is_array($pending) || empty($pending['email'])) {
    flash_set('error', 'No pending registration. Please start over.');
    redirect_to('register.php');
}

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_abort_if_invalid($_POST['csrf_token'] ?? null);
    $code = trim((string) ($_POST['otp_code'] ?? ''));
    if ($code === '' || !preg_match('/^\d{6}$/', $code)) {
        $errors[] = 'Enter the 6-digit code sent to your email.';
    } elseif (!verify_otp((string) $pending['email'], 'register', $code)) {
        $errors[] = 'Invalid or expired code. Request a new one if needed.';
    } else {
        $email = (string) $pending['email'];
        $fullName = (string) $pending['full_name'];
        $password = (string) $pending['password'];
        $role = (string) $pending['role'];
        $farmer = is_array($pending['farmer']) ? $pending['farmer'] : [];
        try {
            $status = $role === 'farmer' ? 'pending_verification' : 'active';
            $insert = DB::pdo()->prepare(
                'INSERT INTO users (email, password_hash, role, status) VALUES (:email, :hash, :role, :status)'
            );
            $insert->execute([
                'email' => $email,
                'hash' => password_hash($password, PASSWORD_DEFAULT),
                'role' => $role,
                'status' => $status,
            ]);
            $userId = (int) DB::pdo()->lastInsertId();
            if ($role === 'buyer') {
                DB::pdo()->prepare('INSERT INTO buyer_profiles (user_id, full_name) VALUES (:user_id, :full_name)')
                    ->execute(['user_id' => $userId, 'full_name' => $fullName]);
            } else {
                DB::pdo()->prepare(
                    'INSERT INTO farmer_profiles
                        (user_id, farm_name, contact_person, contact_phone, contact_email,
                         address, barangay, municipality_city, province, region,
                         valid_id_type, valid_id_number, verification_status)
                     VALUES
                        (:user_id, :farm_name, :contact_person, :contact_phone, :email,
                         :address, :barangay, :municipality_city, :province, :region,
                         :valid_id_type, :valid_id_number, :verification_status)'
                )->execute([
                    'user_id' => $userId,
                    'farm_name' => $farmer['farm_name'] ?? '',
                    'contact_person' => $fullName,
                    'contact_phone' => $farmer['contact_phone'] ?? '',
                    'email' => $email,
                    'address' => sprintf('%s, %s, %s', $farmer['barangay'] ?? '', $farmer['municipality_city'] ?? '', $farmer['province'] ?? ''),
                    'barangay' => $farmer['barangay'] ?? '',
                    'municipality_city' => $farmer['municipality_city'] ?? '',
                    'province' => $farmer['province'] ?? '',
                    'region' => $farmer['region'] ?? '',
                    'valid_id_type' => $farmer['valid_id_type'] ?? '',
                    'valid_id_number' => $farmer['valid_id_number'] ?? '',
                    'verification_status' => 'pending',
                ]);
            }
            unset($_SESSION['pending_registration']);
            flash_set('success', $role === 'farmer'
                ? 'Account created. Your farm is pending verification; you can log in now.'
                : 'Account created. You can now log in.');
            redirect_to('login.php');
        } catch (Throwable $e) {
            error_log('register_verify: ' . $e->getMessage());
            $errors[] = 'Something went wrong. Please try again.';
        }
    }
}

if (isset($_POST['resend_otp'])) {
    csrf_abort_if_invalid($_POST['csrf_token'] ?? null);
    if (otp_rate_limit((string) $pending['email'], 'register')) {
        $errors[] = 'Too many requests. Try again later.';
    } else {
        $code = generate_otp();
        store_otp((string) $pending['email'], 'email', 'register', $code, 600, $_SERVER['REMOTE_ADDR'] ?? null);
        $sent = send_otp_email((string) $pending['email'], $code, 'register');
        if (!$sent) error_log("OTP for {$pending['email']} (register): {$code}");
        flash_set('success', 'A new code was sent.');
        redirect_to('register_verify.php');
    }
}

$pageTitle = 'Verify your email';
$activeNav = '';
$sidebarShell = false;
require __DIR__ . '/partials/header.php';
?>
<main id="main-content" class="container py-5" style="max-width: 480px;">
    <div class="auth-card">
        <h1 class="auth-title">Verify your email</h1>
        <p class="text-muted mb-3">We sent a 6-digit code to <strong><?= e((string) $pending['email']) ?></strong>. Enter it below to finish creating your account.</p>
        <?php if ($errors): ?>
            <div class="alert alert-danger">
                <?php foreach ($errors as $err): ?><div><?= e($err) ?></div><?php endforeach; ?>
            </div>
        <?php endif; ?>
        <?php $flash = flash_get('success'); if ($flash): ?>
            <div class="alert alert-success"><?= e($flash) ?></div>
        <?php endif; ?>
        <form method="post" action="register_verify.php">
            <?= csrf_field() ?>
            <div class="mb-3">
                <label class="form-label" for="otp_code">Verification code</label>
                <input class="form-control" type="text" id="otp_code" name="otp_code" inputmode="numeric" pattern="\d{6}" maxlength="6" required autofocus placeholder="000000">
            </div>
            <button class="btn btn-primary w-100" type="submit">Verify and create account</button>
        </form>
        <form method="post" action="register_verify.php" class="mt-3 text-center">
            <?= csrf_field() ?>
            <input type="hidden" name="resend_otp" value="1">
            <button class="btn btn-link btn-sm" type="submit">Resend code</button>
        </form>
        <p class="text-muted small mt-3 mb-0">Code expires in 10 minutes.</p>
    </div>
</main>
<?php require __DIR__ . '/partials/footer.php'; ?>
PHP;
file_put_contents('C:/Users/your4/Projects/animal-marketplace/public/register_verify.php', $content);
echo "written\n";
