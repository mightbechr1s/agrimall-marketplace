-- AgriMALL Migration 019: administrative hold on orders (FR-STAT-011..013, FR-ADM-014).
-- TiDB does not accept multiple ADD COLUMN with AFTER in one ALTER.

SET @col_exists = (SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'orders'
    AND COLUMN_NAME = 'admin_hold');
SET @ddl = IF(@col_exists = 0,
  'ALTER TABLE `orders` ADD COLUMN `admin_hold` TINYINT(1) NOT NULL DEFAULT 0',
  'SELECT 1');
PREPARE stmt FROM @ddl;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @col_exists = (SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'orders'
    AND COLUMN_NAME = 'admin_hold_reason');
SET @ddl = IF(@col_exists = 0,
  'ALTER TABLE `orders` ADD COLUMN `admin_hold_reason` VARCHAR(500) NULL',
  'SELECT 1');
PREPARE stmt FROM @ddl;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
