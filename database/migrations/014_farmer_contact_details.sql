-- AgriMALL Migration 014: farmer contact & social details (replaces out-of-scope chat).
-- Lets buyers reach farmers directly via phone/email/social instead of in-app messaging.
-- Guarded with information_schema because MySQL 8 has no ADD COLUMN IF NOT EXISTS
-- (MariaDB-only) and this must re-run safely on TiDB.

SET @col_exists = (SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'farmer_profiles'
    AND COLUMN_NAME = 'facebook_url');
SET @ddl = IF(@col_exists = 0,
  'ALTER TABLE `farmer_profiles` ADD COLUMN `facebook_url` VARCHAR(255) NULL AFTER `contact_email`',
  'SELECT 1');
PREPARE stmt FROM @ddl;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @col_exists = (SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'farmer_profiles'
    AND COLUMN_NAME = 'instagram_url');
SET @ddl = IF(@col_exists = 0,
  'ALTER TABLE `farmer_profiles` ADD COLUMN `instagram_url` VARCHAR(255) NULL AFTER `facebook_url`',
  'SELECT 1');
PREPARE stmt FROM @ddl;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @col_exists = (SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'farmer_profiles'
    AND COLUMN_NAME = 'tiktok_url');
SET @ddl = IF(@col_exists = 0,
  'ALTER TABLE `farmer_profiles` ADD COLUMN `tiktok_url` VARCHAR(255) NULL AFTER `instagram_url`',
  'SELECT 1');
PREPARE stmt FROM @ddl;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @col_exists = (SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'farmer_profiles'
    AND COLUMN_NAME = 'website_url');
SET @ddl = IF(@col_exists = 0,
  'ALTER TABLE `farmer_profiles` ADD COLUMN `website_url` VARCHAR(255) NULL AFTER `tiktok_url`',
  'SELECT 1');
PREPARE stmt FROM @ddl;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
