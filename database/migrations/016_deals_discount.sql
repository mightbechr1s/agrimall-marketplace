-- 016: seller-declared discount percent used by the Deals filter.
-- Guarded because MySQL 8 has no ADD COLUMN IF NOT EXISTS.

SET @col_exists = (SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'produce_listings'
    AND COLUMN_NAME = 'discount_percent');
SET @ddl = IF(@col_exists = 0,
  'ALTER TABLE `produce_listings` ADD COLUMN `discount_percent` TINYINT UNSIGNED NOT NULL DEFAULT 0 AFTER `price_per_unit`',
  'SELECT 1');
PREPARE stmt FROM @ddl;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
