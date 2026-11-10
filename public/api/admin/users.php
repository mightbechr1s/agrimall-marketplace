<?php
require __DIR__ . '/../_bootstrap.php';

$user = api_require_auth();
if (!in_array($user['role'], ['admin', 'manager'], true)) {
    api_error('Staff access only.', 403);
}

$pdo = DB::pdo();
$q = trim((string) ($_GET['q'] ?? ''));
$role = (string) ($_GET['role'] ?? '');
$status = (string) ($_GET['status'] ?? '');
$page = max(1, (int) ($_GET['page'] ?? 1));
$perPage = 25;

$where = ['1 = 1'];
$params = [];
if ($q !== '') {
    // Native prepares forbid reusing one named placeholder.
    $where[] = '(u.email LIKE :q1 OR fp.farm_name LIKE :q2 OR bp.full_name LIKE :q3)';
    $params['q1'] = '%' . $q . '%';
    $params['q2'] = '%' . $q . '%';
    $params['q3'] = '%' . $q . '%';
}
if (in_array($role, ['buyer', 'farmer', 'admin', 'manager'], true)) {
    $where[] = 'u.role = :role';
    $params['role'] = $role;
}
if (in_array($status, ['active', 'suspended', 'pending_verification', 'anonymized'], true)) {
    $where[] = 'u.status = :status';
    $params['status'] = $status;
}
$whereSql = 'WHERE ' . implode(' AND ', $where);

$countStmt = $pdo->prepare(
    "SELECT COUNT(*) FROM users u
     LEFT JOIN farmer_profiles fp ON fp.user_id = u.id
     LEFT JOIN buyer_profiles bp ON bp.user_id = u.id
     $whereSql"
);
$countStmt->execute($params);
$total = (int) $countStmt->fetchColumn();
$pages = max(1, (int) ceil($total / $perPage));
$page = min($page, $pages);

$stmt = $pdo->prepare(
    "SELECT u.id, u.email, u.role, u.status, u.created_at, u.last_login_at,
            fp.farm_name, fp.verification_status, bp.full_name
     FROM users u
     LEFT JOIN farmer_profiles fp ON fp.user_id = u.id
     LEFT JOIN buyer_profiles bp ON bp.user_id = u.id
     $whereSql
     ORDER BY u.created_at DESC
     LIMIT :lim OFFSET :off"
);
foreach ($params as $key => $value) {
    $stmt->bindValue(':' . $key, $value, PDO::PARAM_STR);
}
$stmt->bindValue(':lim', $perPage, PDO::PARAM_INT);
$stmt->bindValue(':off', ($page - 1) * $perPage, PDO::PARAM_INT);
$stmt->execute();

$users = array_map(function ($u) {
    return [
        'id' => (int) $u['id'],
        'email' => $u['email'],
        'role' => $u['role'],
        'status' => $u['status'],
        'name' => $u['farm_name'] ?: ($u['full_name'] ?: null),
        'verification_status' => $u['verification_status'],
        'created_at' => $u['created_at'],
        'last_login_at' => $u['last_login_at'],
    ];
}, $stmt->fetchAll(PDO::FETCH_ASSOC));

api_ok(['total' => $total, 'page' => $page, 'total_pages' => $pages, 'users' => $users]);
