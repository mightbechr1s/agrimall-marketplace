-- AniMAL Database Schema
-- Based on SRS v0.3 Section 11 entities, targeting 3NF
-- MySQL 8.0+ compatible

CREATE DATABASE IF NOT EXISTS `animal_marketplace`
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE `animal_marketplace`;

-- =====================================================================
-- CORE USER & AUTHENTICATION
-- =====================================================================

CREATE TABLE `users` (
  `id`            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `email`         VARCHAR(255)    NOT NULL,
  `password_hash` VARCHAR(255)    NOT NULL,
  `role`          ENUM('farmer','buyer','admin') NOT NULL DEFAULT 'buyer',
  `status`        ENUM('active','suspended','pending_verification') NOT NULL DEFAULT 'pending_verification',
  `created_at`    TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`    TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `last_login_at` TIMESTAMP       NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_users_email` (`email`),
  KEY `idx_users_role_status` (`role`, `status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `sessions` (
  `id`            VARCHAR(128)    NOT NULL,
  `user_id`       BIGINT UNSIGNED NOT NULL,
  `ip_address`    VARCHAR(45)     NOT NULL,
  `user_agent`    TEXT            NOT NULL,
  `payload`       TEXT            NOT NULL,
  `last_activity` INT UNSIGNED    NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_sessions_user_id` (`user_id`),
  KEY `idx_sessions_last_activity` (`last_activity`),
  CONSTRAINT `fk_sessions_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================================
-- FARMER & BUYER PROFILES
-- =====================================================================

CREATE TABLE `farmer_profiles` (
  `user_id`              BIGINT UNSIGNED NOT NULL,
  `farm_name`            VARCHAR(150)    NOT NULL,
  `farm_description`     TEXT            NULL,
  `contact_person`       VARCHAR(100)    NOT NULL,
  `contact_phone`        VARCHAR(20)     NULL,
  `contact_email`        VARCHAR(255)    NULL,
  `address`              TEXT            NOT NULL,
  `barangay`             VARCHAR(100)    NOT NULL,
  `municipality_city`    VARCHAR(100)    NOT NULL,
  `province`             VARCHAR(100)    NOT NULL,
  `region`               VARCHAR(100)    NOT NULL,
  `latitude`             DECIMAL(10,8)   NULL,
  `longitude`            DECIMAL(11,8)   NULL,
  `valid_id_type`        ENUM('national_id','drivers_license','passport','voters_id','postal_id','senior_citizen_id','pwd_id') NOT NULL,
  `valid_id_number`      VARCHAR(50)     NOT NULL,
  `valid_id_image`       VARCHAR(500)    NULL,
  `verification_status`  ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  `verified_at`          TIMESTAMP       NULL,
  `verified_by`          BIGINT UNSIGNED NULL,
  `rejection_reason`     TEXT            NULL,
  `created_at`           TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`           TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`user_id`),
  KEY `idx_farmer_verification` (`verification_status`),
  CONSTRAINT `fk_farmer_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_farmer_verified_by` FOREIGN KEY (`verified_by`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `buyer_profiles` (
  `user_id`            BIGINT UNSIGNED NOT NULL,
  `full_name`          VARCHAR(150)    NOT NULL,
  `contact_phone`      VARCHAR(20)     NULL,
  `delivery_address`   TEXT            NULL,
  `barangay`           VARCHAR(100)    NULL,
  `municipality_city`  VARCHAR(100)    NULL,
  `province`           VARCHAR(100)    NULL,
  `region`             VARCHAR(100)    NULL,
  `latitude`           DECIMAL(10,8)   NULL,
  `longitude`          DECIMAL(11,8)   NULL,
  `preferred_pickup`   TINYINT(1)      NOT NULL DEFAULT 1,
  `created_at`         TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`         TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`user_id`),
  CONSTRAINT `fk_buyer_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================================
-- PRODUCE CATEGORIES & UNITS
-- =====================================================================

CREATE TABLE `produce_categories` (
  `id`          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name`        VARCHAR(100)    NOT NULL,
  `slug`        VARCHAR(100)    NOT NULL,
  `description` TEXT            NULL,
  `sort_order`  INT             NOT NULL DEFAULT 0,
  `is_active`   TINYINT(1)      NOT NULL DEFAULT 1,
  `created_at`  TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`  TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_category_slug` (`slug`),
  UNIQUE KEY `uk_category_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `quantity_units` (
  `id`          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name`        VARCHAR(50)     NOT NULL,
  `symbol`      VARCHAR(10)     NOT NULL,
  `type`        ENUM('weight','count','volume') NOT NULL DEFAULT 'weight',
  `base_factor` DECIMAL(10,4)   NOT NULL DEFAULT 1.0000,
  `step`        DECIMAL(10,4)   NOT NULL DEFAULT 0.01,
  `is_active`   TINYINT(1)      NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_unit_symbol` (`symbol`),
  UNIQUE KEY `uk_unit_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================================
-- PRODUCE LISTINGS
-- =====================================================================

CREATE TABLE `produce_listings` (
  `id`                  BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `farmer_id`           BIGINT UNSIGNED NOT NULL,
  `category_id`         BIGINT UNSIGNED NOT NULL,
  `unit_id`             BIGINT UNSIGNED NOT NULL,
  `name`                VARCHAR(150)    NOT NULL,
  `description`         TEXT            NULL,
  `price_per_unit`      DECIMAL(12,2)   NOT NULL,
  `available_quantity`  DECIMAL(12,4)   NOT NULL DEFAULT 0.0000,
  `minimum_order_qty`   DECIMAL(12,4)   NOT NULL DEFAULT 0.0100,
  `maximum_order_qty`   DECIMAL(12,4)   NULL,
  `harvest_date`        DATE            NULL,
  `expiry_date`         DATE            NULL,
  `is_organic`          TINYINT(1)      NOT NULL DEFAULT 0,
  `status`              ENUM('draft','active','paused','sold_out','archived') NOT NULL DEFAULT 'draft',
  `rejection_reason`    TEXT            NULL,
  `created_at`          TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`          TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `published_at`        TIMESTAMP       NULL,
  PRIMARY KEY (`id`),
  KEY `idx_listing_farmer` (`farmer_id`),
  KEY `idx_listing_category` (`category_id`),
  KEY `idx_listing_status` (`status`),
  KEY `idx_listing_published` (`published_at`),
  FULLTEXT KEY `ft_listing_search` (`name`, `description`),
  CONSTRAINT `fk_listing_farmer` FOREIGN KEY (`farmer_id`) REFERENCES `farmer_profiles`(`user_id`) ON DELETE CASCADE,
  CONSTRAINT `fk_listing_category` FOREIGN KEY (`category_id`) REFERENCES `produce_categories`(`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_listing_unit` FOREIGN KEY (`unit_id`) REFERENCES `quantity_units`(`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `listing_images` (
  `id`           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `listing_id`   BIGINT UNSIGNED NOT NULL,
  `image_path`   VARCHAR(500)    NOT NULL,
  `is_primary`   TINYINT(1)      NOT NULL DEFAULT 0,
  `sort_order`   INT             NOT NULL DEFAULT 0,
  `created_at`   TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_image_listing` (`listing_id`),
  KEY `idx_image_primary` (`listing_id`, `is_primary`),
  CONSTRAINT `fk_image_listing` FOREIGN KEY (`listing_id`) REFERENCES `produce_listings`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================================
-- STOCK ADJUSTMENTS (IMMUTABLE LEDGER)
-- =====================================================================

CREATE TABLE `stock_adjustments` (
  `id`                BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `listing_id`        BIGINT UNSIGNED NOT NULL,
  `adjustment_type`   ENUM('initial','restock','sale_deduction','return','spoilage','admin_correction') NOT NULL,
  `quantity_change`   DECIMAL(12,4)   NOT NULL,
  `quantity_before`   DECIMAL(12,4)   NOT NULL,
  `quantity_after`    DECIMAL(12,4)   NOT NULL,
  `reference_type`    ENUM('order','manual','admin') NULL,
  `reference_id`      BIGINT UNSIGNED NULL,
  `notes`             TEXT            NULL,
  `created_by`        BIGINT UNSIGNED NOT NULL,
  `created_at`        TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_stock_listing` (`listing_id`),
  KEY `idx_stock_reference` (`reference_type`, `reference_id`),
  KEY `idx_stock_created` (`created_at`),
  CONSTRAINT `fk_stock_listing` FOREIGN KEY (`listing_id`) REFERENCES `produce_listings`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_stock_created_by` FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================================
-- ORDERS & STATUS WORKFLOW
-- =====================================================================

CREATE TABLE `orders` (
  `id`                      BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `order_number`            VARCHAR(30)     NOT NULL,
  `buyer_id`                BIGINT UNSIGNED NOT NULL,
  `farmer_id`               BIGINT UNSIGNED NOT NULL,
  `listing_id`              BIGINT UNSIGNED NOT NULL,
  `quantity`                DECIMAL(12,4)   NOT NULL,
  `unit_price`              DECIMAL(12,2)   NOT NULL,
  `delivery_fee`            DECIMAL(12,2)   NOT NULL DEFAULT 0.00,
  `delivery_method`         ENUM('pickup','farmer_delivery') NOT NULL DEFAULT 'pickup',
  `delivery_address`        TEXT            NULL,
  `preferred_pickup_date`   DATE            NULL,
  `preferred_pickup_time`   TIME            NULL,
  `buyer_note`              TEXT            NULL,
  `farmer_note`             TEXT            NULL,
  `admin_note`              TEXT            NULL,
  `status`                  ENUM('pending','delivery_fee_review','confirmed','preparing','ready_for_pickup','out_for_delivery','completed','rejected','cancelled') NOT NULL DEFAULT 'pending',
  `rejection_reason`        TEXT            NULL,
  `cancellation_reason`     TEXT            NULL,
  `cancelled_by_role`       ENUM('buyer','farmer','admin') NULL,
  `confirmed_at`            TIMESTAMP       NULL,
  `preparing_at`            TIMESTAMP       NULL,
  `ready_at`                TIMESTAMP       NULL,
  `out_for_delivery_at`     TIMESTAMP       NULL,
  `completed_at`            TIMESTAMP       NULL,
  `rejected_at`             TIMESTAMP       NULL,
  `cancelled_at`            TIMESTAMP       NULL,
  `created_at`              TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`              TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_order_number` (`order_number`),
  KEY `idx_order_buyer` (`buyer_id`),
  KEY `idx_order_farmer` (`farmer_id`),
  KEY `idx_order_listing` (`listing_id`),
  KEY `idx_order_status` (`status`),
  KEY `idx_order_created` (`created_at`),
  CONSTRAINT `fk_order_buyer` FOREIGN KEY (`buyer_id`) REFERENCES `buyer_profiles`(`user_id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_order_farmer` FOREIGN KEY (`farmer_id`) REFERENCES `farmer_profiles`(`user_id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_order_listing` FOREIGN KEY (`listing_id`) REFERENCES `produce_listings`(`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `order_status_history` (
  `id`              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `order_id`        BIGINT UNSIGNED NOT NULL,
  `from_status`     ENUM('pending','delivery_fee_review','confirmed','preparing','ready_for_pickup','out_for_delivery','completed','rejected','cancelled') NULL,
  `to_status`       ENUM('pending','delivery_fee_review','confirmed','preparing','ready_for_pickup','out_for_delivery','completed','rejected','cancelled') NOT NULL,
  `changed_by`      BIGINT UNSIGNED NOT NULL,
  `changed_by_role` ENUM('buyer','farmer','admin') NOT NULL,
  `reason`          TEXT            NULL,
  `created_at`      TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_status_order` (`order_id`),
  KEY `idx_status_created` (`created_at`),
  CONSTRAINT `fk_status_order` FOREIGN KEY (`order_id`) REFERENCES `orders`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_status_changed_by` FOREIGN KEY (`changed_by`) REFERENCES `users`(`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================================
-- ADMIN TOOLS
-- =====================================================================

CREATE TABLE `admin_order_holds` (
  `id`            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `order_id`      BIGINT UNSIGNED NOT NULL,
  `admin_id`      BIGINT UNSIGNED NOT NULL,
  `reason`        TEXT            NOT NULL,
  `is_active`     TINYINT(1)      NOT NULL DEFAULT 1,
  `created_at`    TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `released_at`   TIMESTAMP       NULL,
  `released_by`   BIGINT UNSIGNED NULL,
  PRIMARY KEY (`id`),
  KEY `idx_hold_order` (`order_id`),
  KEY `idx_hold_active` (`is_active`),
  CONSTRAINT `fk_hold_order` FOREIGN KEY (`order_id`) REFERENCES `orders`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_hold_admin` FOREIGN KEY (`admin_id`) REFERENCES `users`(`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_hold_released_by` FOREIGN KEY (`released_by`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `user_suspensions` (
  `id`            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`       BIGINT UNSIGNED NOT NULL,
  `admin_id`      BIGINT UNSIGNED NOT NULL,
  `reason`        TEXT            NOT NULL,
  `suspended_at`  TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `lifted_at`     TIMESTAMP       NULL,
  `lifted_by`     BIGINT UNSIGNED NULL,
  PRIMARY KEY (`id`),
  KEY `idx_suspension_user` (`user_id`),
  KEY `idx_suspension_active` (`lifted_at`),
  CONSTRAINT `fk_suspension_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_suspension_admin` FOREIGN KEY (`admin_id`) REFERENCES `users`(`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_suspension_lifted_by` FOREIGN KEY (`lifted_by`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `retention_holds` (
  `id`              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `entity_type`     ENUM('user','order','listing','verification') NOT NULL,
  `entity_id`       BIGINT UNSIGNED NOT NULL,
  `admin_id`        BIGINT UNSIGNED NOT NULL,
  `reason`          TEXT            NOT NULL,
  `expires_at`      TIMESTAMP       NULL,
  `created_at`      TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `released_at`     TIMESTAMP       NULL,
  `released_by`     BIGINT UNSIGNED NULL,
  PRIMARY KEY (`id`),
  KEY `idx_retention_entity` (`entity_type`, `entity_id`),
  KEY `idx_retention_expires` (`expires_at`),
  CONSTRAINT `fk_retention_admin` FOREIGN KEY (`admin_id`) REFERENCES `users`(`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_retention_released_by` FOREIGN KEY (`released_by`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `admin_audit_log` (
  `id`            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `admin_id`      BIGINT UNSIGNED NOT NULL,
  `action`        VARCHAR(100)    NOT NULL,
  `entity_type`   VARCHAR(50)     NOT NULL,
  `entity_id`     BIGINT UNSIGNED NULL,
  `old_values`    JSON            NULL,
  `new_values`    JSON            NULL,
  `ip_address`    VARCHAR(45)     NULL,
  `user_agent`    TEXT            NULL,
  `created_at`    TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_audit_admin` (`admin_id`),
  KEY `idx_audit_entity` (`entity_type`, `entity_id`),
  KEY `idx_audit_created` (`created_at`),
  CONSTRAINT `fk_audit_admin` FOREIGN KEY (`admin_id`) REFERENCES `users`(`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================================
-- SEED DATA
-- =====================================================================

INSERT INTO `produce_categories` (`name`, `slug`, `description`, `sort_order`) VALUES
('Vegetables', 'vegetables', 'Fresh vegetables and root crops', 1),
('Leafy Greens', 'leafy-greens', 'Leafy vegetables and herbs', 2),
('Fruits', 'fruits', 'Fresh fruits', 3),
('Root Crops', 'root-crops', 'Root vegetables and tubers', 4),
('Herbs & Spices', 'herbs-spices', 'Culinary herbs and spices', 5),
('Grains & Legumes', 'grains-legumes', 'Rice, beans, corn, and other grains', 6);

INSERT INTO `quantity_units` (`name`, `symbol`, `type`, `base_factor`, `step`) VALUES
('Kilogram', 'kg', 'weight', 1.0000, 0.01),
('Gram', 'g', 'weight', 0.0010, 1.00),
('Piece', 'pc', 'count', 1.0000, 1.00),
('Bundle', 'bundle', 'count', 1.0000, 1.00),
('Sack (50kg)', 'sack', 'weight', 50.0000, 0.50),
('Crate', 'crate', 'count', 1.0000, 1.00),
('Liter', 'L', 'volume', 1.0000, 0.01),
('Milliliter', 'mL', 'volume', 0.0010, 1.00);

-- Default admin user (password: admin123 - change in production!)
INSERT INTO `users` (`email`, `password_hash`, `role`, `status`) VALUES
('admin@animal.local', '$2y$12$LQv3c1yqBWVHxkd0LHAkCOYz6TtxMQJqhN8/LewdBPj/RK.PZvO.S', 'admin', 'active');

-- =====================================================================
-- VIEWS FOR COMMON QUERIES
-- =====================================================================

CREATE OR REPLACE VIEW `v_active_listings` AS
SELECT
  pl.*,
  pc.name AS category_name,
  pc.slug AS category_slug,
  qu.symbol AS unit_symbol,
  qu.type AS unit_type,
  qu.step AS unit_step,
  fp.farm_name,
  fp.barangay,
  fp.municipality_city,
  fp.province,
  fp.verification_status,
  (
    SELECT li.image_path
    FROM listing_images li
    WHERE li.listing_id = pl.id AND li.is_primary = 1
    ORDER BY li.sort_order
    LIMIT 1
  ) AS primary_image
FROM `produce_listings` pl
JOIN `produce_categories` pc ON pl.category_id = pc.id
JOIN `quantity_units` qu ON pl.unit_id = qu.id
JOIN `farmer_profiles` fp ON pl.farmer_id = fp.user_id
WHERE pl.status = 'active'
  AND fp.verification_status = 'approved'
  AND pc.is_active = 1
  AND qu.is_active = 1
  AND pl.available_quantity > 0;

CREATE OR REPLACE VIEW `v_order_summary` AS
SELECT
  o.*,
  CONCAT(bp.full_name) AS buyer_name,
  bp.contact_phone AS buyer_phone,
  fp.farm_name,
  fp.contact_person AS farmer_contact_person,
  fp.contact_phone AS farmer_phone,
  pl.name AS listing_name,
  pl.unit_id,
  qu.symbol AS unit_symbol
FROM `orders` o
JOIN `buyer_profiles` bp ON o.buyer_id = bp.user_id
JOIN `farmer_profiles` fp ON o.farmer_id = fp.user_id
JOIN `produce_listings` pl ON o.listing_id = pl.id
JOIN `quantity_units` qu ON pl.unit_id = qu.id;