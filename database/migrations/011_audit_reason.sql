-- AgriMALL Migration 011: add reason column to admin_audit_log.
-- Guarded so a fresh schema that already ships the column does not fail.

SET @col_exists = (SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'admin_audit_log'
    AND COLUMN_NAME = 'reason');
SET @ddl = IF(@col_exists = 0,
  'ALTER TABLE `admin_audit_log` ADD COLUMN `reason` TEXT NULL AFTER `entity_id`',
  'SELECT 1');
PREPARE stmt FROM @ddl;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
