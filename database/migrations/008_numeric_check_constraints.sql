-- =====================================================================
-- 008: Numeric CHECK constraints on money and quantity columns
-- =====================================================================
-- Every numeric write path used to validate quantity and price in PHP only,
-- by casting with (float) and comparing. A cast cannot reject anything:
-- (float)'abc' is 0.0, (float)'12abc' is 12.0, and (float)'1e999' is INF,
-- which satisfies every `> 0` test. The worst case was available_quantity,
-- because its check read `$availableQuantity < 0` -- and `null < 0` is false,
-- so unparseable input passed validation and was written out as NULL.
--
-- This migration moves the floor down into the database, so a bad value is
-- refused by MariaDB itself no matter which endpoint or script issued the
-- INSERT or UPDATE. src/functions.php::number_in() remains the first line of
-- defence and produces the user-facing error message; these constraints are
-- the backstop for anything that reaches SQL another way.
--
-- NULL means "not supplied", so the money columns only check the lower bound.
-- maximum_order_qty is genuinely nullable and stays that way.
--
-- Verified against the live database before writing this file: 46 rows
-- across produce_listings, cart_items and orders, 0 violations, so the
-- ALTERs below apply without a data-repair step.
--
-- One constraint per ALTER TABLE, and each guarded on information_schema so a
-- second run is a no-op and a partially-migrated database picks up only what
-- is missing. The no-op branch is `DO 0` rather than `SELECT 1`, because the
-- test harness reads any migration output as a failure.
--
-- The compact `ADD CONSTRAINT a CHECK (...), CONSTRAINT b CHECK (...)` form
-- is NOT usable here. MariaDB 10.4 accepts only one ADD clause per ALTER, and
-- the tuple spelling `ADD (CONSTRAINT a CHECK (...), ...)` is worse than a
-- syntax error: it parses cleanly and then silently drops every constraint,
-- which looks like a successful migration and leaves the table unprotected.
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
