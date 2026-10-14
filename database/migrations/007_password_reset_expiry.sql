-- 007: password_resets.expires_at must be DATETIME, not TIMESTAMP. With
-- explicit_defaults_for_timestamp=0 MariaDB rewrites a bare TIMESTAMP to add ON UPDATE,
-- so any unrelated UPDATE silently revives an expired token (002's plain TIMESTAMP hit this).
-- Guarded on DATA_TYPE; the no-op branch is DO 0, not SELECT 1, which the harness reads as failure.
USE `animal_marketplace`;

SET @col_type = (SELECT DATA_TYPE FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = 'animal_marketplace'
    AND TABLE_NAME = 'password_resets'
    AND COLUMN_NAME = 'expires_at');

SET @ddl = IF(@col_type = 'timestamp',
  'ALTER TABLE `password_resets` MODIFY COLUMN `expires_at` DATETIME NOT NULL',
  'DO 0');
PREPARE stmt FROM @ddl;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
