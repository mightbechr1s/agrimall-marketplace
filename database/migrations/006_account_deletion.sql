-- 006: account deletion is anonymization, not physical delete (NFR-PRIV-006).
-- BR-013, BR-034, NFR-REL-004 and the ON DELETE RESTRICT FKs (orders, admin_audit_log,
-- retention_holds, user_suspensions, stock_adjustments) make a real delete impossible;
-- the row and its PK survive as the subject reference. Adds 'anonymized' status + anonymized_at.

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

-- login.php refuses any status but 'active'; this index keeps that lookup cheap.
SET @idx_exists = (SELECT COUNT(*) FROM information_schema.STATISTICS
  WHERE TABLE_SCHEMA = 'animal_marketplace' AND TABLE_NAME = 'users'
    AND INDEX_NAME = 'idx_users_status');

SET @ddl = IF(@idx_exists = 0,
  'ALTER TABLE `users` ADD INDEX `idx_users_status` (`status`)',
  'SELECT 1');
PREPARE stmt FROM @ddl;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
