<?php
require __DIR__ . '/../src/bootstrap.php';
require_role('admin');

$pdo = DB::pdo();
$adminId = current_user_id();

$errors = [];
$success = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_abort_if_invalid($_POST['csrf_token'] ?? null);

    $email = mb_substr(trim((string) ($_POST['email'] ?? '')), 0, 255);
    $password = (string) ($_POST['password'] ?? '');
    $role = in_array($_POST['role'] ?? '', ['admin', 'manager'], true) ? (string) $_POST['role'] : '';
    $fullName = mb_substr(trim((string) ($_POST['full_name'] ?? '')), 0, 100);

    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'A valid email is required.';
    }
    if ($password === '' || strlen($password) < 8) {
        $errors[] = 'Password must be at least 8 characters.';
    }
    if ($role === '') {
        $errors[] = 'Select a role (Admin or Manager).';
    }
    if ($fullName === '') {
        $errors[] = 'Full name is required.';
    }

    if (!$errors) {
        $check = $pdo->prepare('SELECT id FROM users WHERE email = :email LIMIT 1');
        $check->execute(['email' => $email]);
        if ($check->fetch()) {
            $errors[] = 'An account with this email already exists.';
        }
    }

    if (!$errors) {
        try {
            $pdo->beginTransaction();
            $pdo->prepare('INSERT INTO users (email, password_hash, role, status) VALUES (:email, :hash, :role, :status)')
                ->execute([
                    'email' => $email,
                    'hash' => password_hash($password, PASSWORD_DEFAULT),
                    'role' => $role,
                    'status' => 'active',
                ]);
            $newUserId = (int) $pdo->lastInsertId();
            // Create a buyer profile so admins/managers can also buy.
            $pdo->prepare('INSERT INTO buyer_profiles (user_id, full_name) VALUES (:uid, :name)')
                ->execute(['uid' => $newUserId, 'name' => $fullName]);
            $pdo->prepare(
                'INSERT INTO admin_audit_log (admin_id, action, entity_type, entity_id, reason, created_at) VALUES (:admin, :action, :type, :id, :reason, NOW())'
            )->execute([
                'admin' => $adminId,
                'action' => 'create_' . $role . '_account',
                'type' => 'user',
                'id' => $newUserId,
                'reason' => 'Created by admin ' . current_user_id(),
            ]);
            $pdo->commit();
            flash_set('success', ucfirst($role) . ' account created for ' . $email . '.');
            redirect_to('admin_create_user.php');
        } catch (Throwable $e) {
            $pdo->rollBack();
            error_log('admin_create_user: ' . $e->getMessage());
            $errors[] = 'Failed to create account. Please try again.';
        }
    }
}

// List existing admin/manager accounts
$staffAccounts = $pdo->query(
    "SELECT id, email, role, status, created_at FROM users WHERE role IN ('admin','manager') ORDER BY created_at DESC"
)->fetchAll(PDO::FETCH_ASSOC);

$activePage = 'users';
$pageTitle = 'Manage Users';
require __DIR__ . '/partials/dashboard-layout.php';
?>
<div class="settings-wrap">
    <div class="settings-container">
        <div class="settings-header">
            <h1>Manage Admin &amp; Manager Accounts</h1>
            <p>Create new admin (technical) or manager (business) accounts. Admins have full system access; managers handle business operations.</p>
        </div>
        <a href="dashboard.php" class="btn btn-outline btn-sm mb-3"><i class="bi bi-arrow-left me-1"></i> Back to dashboard</a>

        <?php if ($errors): ?>
            <div class="alert alert-danger">
                <?php foreach ($errors as $err): ?><div><?= e($err) ?></div><?php endforeach; ?>
            </div>
        <?php endif; ?>
        <?php $flash = flash_get('success'); if ($flash): ?>
            <div class="alert alert-success"><?= e($flash) ?></div>
        <?php endif; ?>

        <div class="card mb-4">
            <div class="card-body">
                <h5 class="mb-3">Create new account</h5>
                <form method="post" action="admin_create_user.php" class="row g-3">
                    <?= csrf_field() ?>
                    <div class="col-md-6">
                        <label class="form-label" for="full_name">Full name</label>
                        <input type="text" class="form-control" id="full_name" name="full_name" required maxlength="100">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="email">Email</label>
                        <input type="email" class="form-control" id="email" name="email" required maxlength="255">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="password">Password</label>
                        <input type="password" class="form-control" id="password" name="password" required minlength="8" maxlength="128">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="role">Role</label>
                        <select class="form-select" id="role" name="role" required>
                            <option value="">Select role...</option>
                            <option value="manager">Manager (business side)</option>
                            <option value="admin">Admin (technical side)</option>
                        </select>
                    </div>
                    <div class="col-12">
                        <button type="submit" class="btn btn-primary">Create account</button>
                    </div>
                </form>
            </div>
        </div>

        <h5 class="mb-3">Existing staff accounts</h5>
        <?php if ($staffAccounts): ?>
        <div class="table-responsive card">
            <table class="table align-middle mb-0">
                <thead>
                    <tr><th>Email</th><th>Name/Role</th><th>Status</th><th>Created</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($staffAccounts as $sa): ?>
                    <tr>
                        <td><?= e($sa['email']) ?></td>
                        <td><span class="badge text-bg-secondary"><?= e(ucfirst($sa['role'])) ?></span></td>
                        <td><?= e(ucfirst($sa['status'])) ?></td>
                        <td class="text-muted small"><?= e(date('M j, Y', strtotime($sa['created_at']))) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php else: ?>
        <p class="text-muted">No staff accounts yet.</p>
        <?php endif; ?>
    </div>
</div>
<?php require __DIR__ . '/partials/dashboard-footer.php'; ?>
PHP;
file_put_contents('C:/Users/your4/Projects/animal-marketplace/public/admin_create_user.php', $content);
echo "written\n";
