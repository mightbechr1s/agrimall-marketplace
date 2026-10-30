-- AgriMALL Migration 012: hot-path composite indexes.
-- Run after 011 against `animal_marketplace`. Guarded per index (the information_schema
-- + PREPARE pattern of 002) so a rerun by tools/db-reset.php is a clean no-op.

USE `animal_marketplace`;

-- Every order list / dashboard sorts "my orders" newest first.
SET @idx_exists = (SELECT COUNT(*) FROM information_schema.STATISTICS
  WHERE TABLE_SCHEMA = 'animal_marketplace' AND TABLE_NAME = 'orders'
    AND INDEX_NAME = 'idx_order_buyer_created');
SET @ddl = IF(@idx_exists = 0,
  'ALTER TABLE `orders` ADD INDEX `idx_order_buyer_created` (`buyer_id`, `created_at` DESC)',
  'DO 0');
PREPARE stmt FROM @ddl; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @idx_exists = (SELECT COUNT(*) FROM information_schema.STATISTICS
  WHERE TABLE_SCHEMA = 'animal_marketplace' AND TABLE_NAME = 'orders'
    AND INDEX_NAME = 'idx_order_farmer_created');
SET @ddl = IF(@idx_exists = 0,
  'ALTER TABLE `orders` ADD INDEX `idx_order_farmer_created` (`farmer_id`, `created_at` DESC)',
  'DO 0');
PREPARE stmt FROM @ddl; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Active-order cards filter status within one farmer or one buyer.
SET @idx_exists = (SELECT COUNT(*) FROM information_schema.STATISTICS
  WHERE TABLE_SCHEMA = 'animal_marketplace' AND TABLE_NAME = 'orders'
    AND INDEX_NAME = 'idx_order_farmer_status');
SET @ddl = IF(@idx_exists = 0,
  'ALTER TABLE `orders` ADD INDEX `idx_order_farmer_status` (`farmer_id`, `status`)',
  'DO 0');
PREPARE stmt FROM @ddl; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @idx_exists = (SELECT COUNT(*) FROM information_schema.STATISTICS
  WHERE TABLE_SCHEMA = 'animal_marketplace' AND TABLE_NAME = 'orders'
    AND INDEX_NAME = 'idx_order_buyer_status');
SET @ddl = IF(@idx_exists = 0,
  'ALTER TABLE `orders` ADD INDEX `idx_order_buyer_status` (`buyer_id`, `status`)',
  'DO 0');
PREPARE stmt FROM @ddl; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- v_active_listings primary-image subquery: covering index kills the per-row sort.
SET @idx_exists = (SELECT COUNT(*) FROM information_schema.STATISTICS
  WHERE TABLE_SCHEMA = 'animal_marketplace' AND TABLE_NAME = 'listing_images'
    AND INDEX_NAME = 'idx_listing_image_pick');
SET @ddl = IF(@idx_exists = 0,
  'ALTER TABLE `listing_images` ADD INDEX `idx_listing_image_pick` (`listing_id`, `is_primary`, `sort_order`)',
  'DO 0');
PREPARE stmt FROM @ddl; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Listing rollups group by status per farmer (dashboards + admin report).
SET @idx_exists = (SELECT COUNT(*) FROM information_schema.STATISTICS
  WHERE TABLE_SCHEMA = 'animal_marketplace' AND TABLE_NAME = 'produce_listings'
    AND INDEX_NAME = 'idx_listing_farmer_status');
SET @ddl = IF(@idx_exists = 0,
  'ALTER TABLE `produce_listings` ADD INDEX `idx_listing_farmer_status` (`farmer_id`, `status`)',
  'DO 0');
PREPARE stmt FROM @ddl; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Reports join status history per order and per transition kind.
SET @idx_exists = (SELECT COUNT(*) FROM information_schema.STATISTICS
  WHERE TABLE_SCHEMA = 'animal_marketplace' AND TABLE_NAME = 'order_status_history'
    AND INDEX_NAME = 'idx_status_order_to');
SET @ddl = IF(@idx_exists = 0,
  'ALTER TABLE `order_status_history` ADD INDEX `idx_status_order_to` (`order_id`, `to_status`)',
  'DO 0');
PREPARE stmt FROM @ddl; EXECUTE stmt; DEALLOCATE PREPARE stmt;
