<?php
/**
 * Farmer application review shared by the web form (public/admin_verify.php)
 * and the JSON API (public/api/admin/verify.php).
 *
 * The caller must already be inside a transaction and hold a row lock on the
 * farmer_profiles row (SELECT ... FOR UPDATE). Auditing and commit stay with
 * the caller so each surface can log in its own style.
 *
 * @param array $row    Locked row: user_status + verification_status.
 * @param array $copy   i18n "admin_verify" copy array.
 * @return string Success message for the caller's flash/JSON body.
 */
function farmer_review_apply(
    PDO $pdo,
    array $row,
    int $farmerId,
    string $action,
    string $reason,
    int $adminId,
    array $copy = []
): string {
    if (!in_array($action, ['approve', 'reject'], true)) {
        throw new UserFacingException(t($copy, 'invalid_request'));
    }
    if ($row['verification_status'] !== 'pending') {
        throw new UserFacingException(t($copy, 'already_reviewed'));
    }
    if ($action === 'reject' && $reason === '') {
        throw new UserFacingException(t($copy, 'reason_required'));
    }
    if (mb_strlen($reason) > FIELD_LIMITS['reason']) {
        throw new UserFacingException(sprintf(t($copy, 'reason_too_long'), FIELD_LIMITS['reason']));
    }

    if ($action === 'approve') {
        $pdo->prepare(
            "UPDATE farmer_profiles
             SET verification_status = 'approved', verified_at = NOW(), verified_by = :admin, rejection_reason = NULL
             WHERE user_id = :id"
        )->execute(['admin' => $adminId, 'id' => $farmerId]);
        $pdo->prepare("UPDATE users SET status = 'active' WHERE id = :id")->execute(['id' => $farmerId]);
        audit_log('verification_approved', 'farmer', $farmerId,
            ['verification_status' => 'pending', 'user_status' => $row['user_status']],
            ['verification_status' => 'approved', 'user_status' => 'active']);
        return 'Farmer application approved.';
    }

    $pdo->prepare(
        "UPDATE farmer_profiles
         SET verification_status = 'rejected', verified_at = NOW(), verified_by = :admin, rejection_reason = :reason
         WHERE user_id = :id"
    )->execute(['admin' => $adminId, 'id' => $farmerId, 'reason' => $reason]);
    audit_log('verification_rejected', 'farmer', $farmerId,
        ['verification_status' => 'pending'],
        ['verification_status' => 'rejected', 'reason' => $reason]);
    return 'Farmer application rejected.';
}
