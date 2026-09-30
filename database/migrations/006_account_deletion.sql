-- =====================================================================
-- 006: Account deletion (anonymization)
-- =====================================================================
-- Supports the user-facing "delete my account" erasure flow.
--
-- Physical deletion of a user is not an option in this schema:
--   * BR-013  historical records shall not be physically deleted through
--             normal user actions
--   * BR-034  personal data shall be deleted or irreversibly anonymized
--   * NFR-REL-004 user-facing records use status changes or soft deletion
--   * admin_audit_log.admin_id, retention_holds.admin_id,
--     admin_order_holds.admin_id, user_suspensions.admin_id and
--     stock_adjustments.created_by are all ON DELETE RESTRICT, and
--     orders.buyer_id / orders.farmer_id are ON DELETE RESTRICT through
--     buyer_profiles / farmer_profiles.
-- Any user with an order or an admin action therefore cannot be physically
-- deleted at all. Deletion is implemented as anonymization instead
-- (NFR-PRIV-006): the row and its primary key survive as the stable internal
-- subject reference, identifying values are overwritten, and historical
-- orders, listings and audit relationships stay intact.
--
-- Adds the terminal 'anonymized' status plus the timestamp it happened.

ALTER TABLE `users`
    MODIFY COLUMN `status`
        ENUM('active','suspended','pending_verification','anonymized')
        NOT NULL DEFAULT 'pending_verification',
    ADD COLUMN `anonymized_at` TIMESTAMP NULL DEFAULT NULL AFTER `last_login_at`;

-- Anonymized users must never be resumable from a remember-me cookie.
-- login.php already refuses any status other than 'active'; this index keeps
-- that lookup cheap as the user table grows.
ALTER TABLE `users`
    ADD KEY `idx_users_status` (`status`);
