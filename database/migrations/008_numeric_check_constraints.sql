-- 008: CHECK constraints on money/quantity columns, so MariaDB refuses bad values
-- whatever endpoint wrote them. number_in() (src/functions.php) stays first-line and
-- owns the user-facing message. MariaDB 10.4 takes one ADD clause per ALTER; the tuple
-- form parses but silently drops every constraint. No-op branch is DO 0, not SELECT 1.
USE `animal_marketplace`;

-- ---- produce_listings: price must be positive ------------------------
SET @ddl = IF(EXISTS(SELECT 1 FROM information_schema.TABLE_CONSTRAINTS
      WHERE CONSTRAINT_SCHEMA = 'animal_marketplace'
        AND TABLE_NAME = 'produce_listings'
        AND CONSTRAINT_NAME = 'chk_listing_price_positive'),
  'DO 0',
  'ALTER TABLE `produce_listings` ADD CONSTRAINT chk_listing_price_positive CHECK (price_per_unit > 0)');
PREPARE stmt FROM @ddl;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- ---- produce_listings: stock cannot go negative ----------------------
SET @ddl = IF(EXISTS(SELECT 1 FROM information_schema.TABLE_CONSTRAINTS
      WHERE CONSTRAINT_SCHEMA = 'animal_marketplace'
        AND TABLE_NAME = 'produce_listings'
        AND CONSTRAINT_NAME = 'chk_listing_stock_nonnegative'),
  'DO 0',
  'ALTER TABLE `produce_listings` ADD CONSTRAINT chk_listing_stock_nonnegative CHECK (available_quantity >= 0)');
PREPARE stmt FROM @ddl;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- ---- produce_listings: minimum order must be positive ----------------
SET @ddl = IF(EXISTS(SELECT 1 FROM information_schema.TABLE_CONSTRAINTS
      WHERE CONSTRAINT_SCHEMA = 'animal_marketplace'
        AND TABLE_NAME = 'produce_listings'
        AND CONSTRAINT_NAME = 'chk_listing_min_qty_positive'),
  'DO 0',
  'ALTER TABLE `produce_listings` ADD CONSTRAINT chk_listing_min_qty_positive CHECK (minimum_order_qty > 0)');
PREPARE stmt FROM @ddl;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- ---- produce_listings: max order cannot undercut min order -----------
SET @ddl = IF(EXISTS(SELECT 1 FROM information_schema.TABLE_CONSTRAINTS
      WHERE CONSTRAINT_SCHEMA = 'animal_marketplace'
        AND TABLE_NAME = 'produce_listings'
        AND CONSTRAINT_NAME = 'chk_listing_max_gte_min'),
  'DO 0',
  'ALTER TABLE `produce_listings` ADD CONSTRAINT chk_listing_max_gte_min CHECK (maximum_order_qty IS NULL OR maximum_order_qty >= minimum_order_qty)');
PREPARE stmt FROM @ddl;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- ---- cart_items: a cart line always holds a positive amount -----------
SET @ddl = IF(EXISTS(SELECT 1 FROM information_schema.TABLE_CONSTRAINTS
      WHERE CONSTRAINT_SCHEMA = 'animal_marketplace'
        AND TABLE_NAME = 'cart_items'
        AND CONSTRAINT_NAME = 'chk_cart_qty_positive'),
  'DO 0',
  'ALTER TABLE `cart_items` ADD CONSTRAINT chk_cart_qty_positive CHECK (quantity > 0)');
PREPARE stmt FROM @ddl;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- ---- orders: ordered amount is positive ------------------------------
SET @ddl = IF(EXISTS(SELECT 1 FROM information_schema.TABLE_CONSTRAINTS
      WHERE CONSTRAINT_SCHEMA = 'animal_marketplace'
        AND TABLE_NAME = 'orders'
        AND CONSTRAINT_NAME = 'chk_order_qty_positive'),
  'DO 0',
  'ALTER TABLE `orders` ADD CONSTRAINT chk_order_qty_positive CHECK (quantity > 0)');
PREPARE stmt FROM @ddl;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- ---- orders: a delivery fee is never negative ------------------------
SET @ddl = IF(EXISTS(SELECT 1 FROM information_schema.TABLE_CONSTRAINTS
      WHERE CONSTRAINT_SCHEMA = 'animal_marketplace'
        AND TABLE_NAME = 'orders'
        AND CONSTRAINT_NAME = 'chk_order_fee_nonnegative'),
  'DO 0',
  'ALTER TABLE `orders` ADD CONSTRAINT chk_order_fee_nonnegative CHECK (delivery_fee >= 0)');
PREPARE stmt FROM @ddl;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- ---- orders: the price snapshot cannot be negative -------------------
SET @ddl = IF(EXISTS(SELECT 1 FROM information_schema.TABLE_CONSTRAINTS
      WHERE CONSTRAINT_SCHEMA = 'animal_marketplace'
        AND TABLE_NAME = 'orders'
        AND CONSTRAINT_NAME = 'chk_order_unit_price_nonnegative'),
  'DO 0',
  'ALTER TABLE `orders` ADD CONSTRAINT chk_order_unit_price_nonnegative CHECK (unit_price >= 0)');
PREPARE stmt FROM @ddl;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
