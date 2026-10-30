-- AgriMALL Migration 002: Password resets + remember-me tokens
-- Run after schema.sql against `animal_marketplace`

USE `animal_marketplace`;

-- Password reset tokens table
CREATE TABLE IF NOT EXISTS `password_resets` (
  `id`         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`    BIGINT UNSIGNED NOT NULL,
  `token`      VARCHAR(128)    NOT NULL,
  -- DATETIME, not TIMESTAMP: explicit_defaults_for_timestamp=0 rewrites a bare TIMESTAMP to
  -- include ON UPDATE CURRENT_TIMESTAMP, silently extending reset expiry; 007 repairs old DBs.
  `expires_at` DATETIME        NOT NULL,
  `created_at` TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_pwreset_token` (`token`),
  KEY `idx_pwreset_user` (`user_id`),
  KEY `idx_pwreset_expires` (`expires_at`),
  CONSTRAINT `fk_pwreset_user` FOREIGN KEY (`user_id`)
    REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Add remember-me support to users (idempotent, guarded on information_schema).
SET @col_exists = (SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = 'animal_marketplace' AND TABLE_NAME = 'users'
    AND COLUMN_NAME = 'remember_token');

SET @ddl = IF(@col_exists = 0,
  'ALTER TABLE `users` ADD COLUMN `remember_token` VARCHAR(128) NULL AFTER `last_login_at`',
  'SELECT 1');
PREPARE stmt FROM @ddl;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @col_exists = (SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = 'animal_marketplace' AND TABLE_NAME = 'users'
    AND COLUMN_NAME = 'remember_expires');

SET @ddl = IF(@col_exists = 0,
  'ALTER TABLE `users` ADD COLUMN `remember_expires` TIMESTAMP NULL AFTER `remember_token`',
  'SELECT 1');
PREPARE stmt FROM @ddl;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @idx_exists = (SELECT COUNT(*) FROM information_schema.STATISTICS
  WHERE TABLE_SCHEMA = 'animal_marketplace' AND TABLE_NAME = 'users'
    AND INDEX_NAME = 'idx_users_remember_token');

SET @ddl = IF(@idx_exists = 0,
  'ALTER TABLE `users` ADD INDEX `idx_users_remember_token` (`remember_token`)',
  'SELECT 1');
PREPARE stmt FROM @ddl;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;