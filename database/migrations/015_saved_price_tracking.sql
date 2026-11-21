-- 015: remember the price a buyer saved a listing at, so the UI can show drops.
-- Guarded because MySQL 8 has no ADD COLUMN IF NOT EXISTS.

SET @col_exists = (SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'saved_listings'
    AND COLUMN_NAME = 'price_at_save');
SET @ddl = IF(@col_exists = 0,
  'ALTER TABLE `saved_listings` ADD COLUMN `price_at_save` DECIMAL(10,2) NULL AFTER `listing_id`',
  'SELECT 1');
PREPARE stmt FROM @ddl;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
