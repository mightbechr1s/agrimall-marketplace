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
--
-- MODIFY is declarative and safe to repeat, but ADD COLUMN and ADD KEY are not:
-- MySQL has no ADD COLUMN IF NOT EXISTS, so a second run aborts with
-- ERROR 1060. Both are guarded against information_schema the same way
-- 002 does it, which keeps this file re-runnable. db-reset.php replays the
-- whole migrations directory on every rebuild, so a file that fails on its
-- second application is a file that eventually blocks someone.

USE `animal_marketplace`;

ALTER TABLE `users`
    MODIFY COLUMN `status`
        ENUM('active','suspended','pending_verification','anonymized')
        NOT NULL DEFAULT 'pending_verification';

SET @col_exists = (SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = 'animal_marketplace' AND TABLE_NAME = 'users'
    AND COLUMN_NAME = 'anonymized_at');

SET @ddl = IF(@col_exists = 0,
  'ALTER TABLE `users` ADD COLUMN `anonymized_at` TIMESTAMP NULL DEFAULT NULL AFTER `last_login_at`',
  'SELECT 1');
PREPARE stmt FROM @ddl;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Anonymized users must never be resumable from a remember-me cookie.
-- login.php already refuses any status other than 'active'; this index keeps
-- that lookup cheap as the user table grows.
SET @idx_exists = (SELECT COUNT(*) FROM information_schema.STATISTICS
  WHERE TABLE_SCHEMA = 'animal_marketplace' AND TABLE_NAME = 'users'
    AND INDEX_NAME = 'idx_users_status');

SET @ddl = IF(@idx_exists = 0,
  'ALTER TABLE `users` ADD INDEX `idx_users_status` (`status`)',
  'SELECT 1');
PREPARE stmt FROM @ddl;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
