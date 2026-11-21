-- AgriMALL Migration 020: session invalidation support (FR-ADM-004).
-- Guarded so a fresh schema that already ships the column does not fail.

SET @col_exists = (SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users'
    AND COLUMN_NAME = 'session_version');
SET @ddl = IF(@col_exists = 0,
  'ALTER TABLE `users` ADD COLUMN `session_version` INT NOT NULL DEFAULT 0 AFTER `status`',
  'SELECT 1');
PREPARE stmt FROM @ddl;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
