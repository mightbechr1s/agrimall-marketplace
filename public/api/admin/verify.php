<?php
require __DIR__ . '/../_bootstrap.php';

$user = api_require_auth();
if (!in_array($user['role'], ['admin', 'manager'], true)) {
    api_error('Staff access only.', 403);
}

$pdo = DB::pdo();

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $filter = (string) ($_GET['status'] ?? 'pending');
    if (!in_array($filter, ['pending', 'approved', 'rejected', 'all'], true)) {
        $filter = 'pending';
    }

    $sql = "SELECT u.id AS user_id, u.email, u.status AS user_status, u.created_at AS registered_at,
                   fp.farm_name, fp.contact_person, fp.contact_phone, fp.contact_email,
                   fp.barangay, fp.municipality_city, fp.province,
                   fp.valid_id_type, fp.valid_id_number,
                   (fp.valid_id_image IS NOT NULL) AS has_id_image,
                   fp.verification_status, fp.rejection_reason, fp.verified_at
            FROM farmer_profiles fp
            JOIN users u ON u.id = fp.user_id";
    $params = [];
    if ($filter !== 'all') {
        $sql .= ' WHERE fp.verification_status = :status';
        $params['status'] = $filter;
    }
    $sql .= ' ORDER BY fp.created_at ASC LIMIT 100';

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    $idLabels = [
        'national_id' => 'National ID',
        'drivers_license' => "Driver's License",
        'passport' => 'Passport',
        'voters_id' => "Voter's ID",
        'postal_id' => 'Postal ID',
        'senior_citizen_id' => 'Senior Citizen ID',
        'pwd_id' => 'PWD ID',
    ];

    $applications = array_map(function ($a) use ($idLabels) {
        return [
            'user_id' => (int) $a['user_id'],
            'email' => $a['email'],
            'farm_name' => $a['farm_name'],
            'contact_person' => $a['contact_person'],
            'contact_phone' => $a['contact_phone'],
            'location' => implode(', ', array_filter([$a['barangay'], $a['municipality_city'], $a['province']])),
            'id_type' => $idLabels[$a['valid_id_type']] ?? $a['valid_id_type'],
            'id_number' => $a['valid_id_number'],
            'has_id_image' => (bool) $a['has_id_image'],
            'verification_status' => $a['verification_status'],
            'rejection_reason' => $a['rejection_reason'],
            'registered_at' => $a['registered_at'],
        ];
    }, $stmt->fetchAll(PDO::FETCH_ASSOC));

    $countStmt = $pdo->query("SELECT COUNT(*) FROM farmer_profiles WHERE verification_status = 'pending'");

    api_ok([
        'applications' => $applications,
        'pending_total' => (int) $countStmt->fetchColumn(),
    ]);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = api_input();
    $farmerId = (int) ($input['farmer_id'] ?? 0);
    $action = (string) ($input['action'] ?? '');
    $reason = trim((string) ($input['reason'] ?? ''));

    if ($farmerId <= 0 || !in_array($action, ['approve', 'reject'], true)) {
        api_error('Invalid request.');
    }

    try {
        $pdo->beginTransaction();
        $stmt = $pdo->prepare(
            'SELECT u.status AS user_status, fp.verification_status
             FROM farmer_profiles fp
             JOIN users u ON u.id = fp.user_id
             WHERE fp.user_id = :id FOR UPDATE'
        );
        $stmt->execute(['id' => $farmerId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            throw new UserFacingException('Application not found.');
        }

        $i18nAll = include __DIR__ . '/../../../src/i18n_copy.php';
        $message = farmer_review_apply(
            $pdo,
            $row,
            $farmerId,
            $action,
            $reason,
            (int) $user['id'],
            $i18nAll['admin_verify'] ?? []
        );

        $pdo->commit();
        api_ok(['message' => $message]);
    } catch (UserFacingException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        api_error($e->getMessage());
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log('api admin verify: ' . $e->getMessage());
        api_error('Could not update the application.', 500);
    }
}

api_error('Method not allowed.', 405);
