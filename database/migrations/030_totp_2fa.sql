-- 030: TOTP two-factor authentication for accounts.
-- totp_secret holds the base32 shared seed, encrypted at rest by the app
-- ("enc:v1:..." via security.encryption_key); NULL until 2FA is set up.
-- totp_enabled flips to 1 only after a code is verified once.
USE `animal_marketplace`;

SET @col_exists = (SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = 'animal_marketplace' AND TABLE_NAME = 'users'
    AND COLUMN_NAME = 'totp_secret');
SET @ddl = IF(@col_exists = 0,
  'ALTER TABLE `users` ADD COLUMN `totp_secret` VARCHAR(255) NULL AFTER `session_version`',
  'SELECT 1');
PREPARE stmt FROM @ddl;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @col_exists = (SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = 'animal_marketplace' AND TABLE_NAME = 'users'
    AND COLUMN_NAME = 'totp_enabled');
SET @ddl = IF(@col_exists = 0,
  'ALTER TABLE `users` ADD COLUMN `totp_enabled` TINYINT(1) NOT NULL DEFAULT 0 AFTER `totp_secret`',
  'SELECT 1');
PREPARE stmt FROM @ddl;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
