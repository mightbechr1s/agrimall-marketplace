-- AgriMALL Migration 004: cart and saved listings.
-- Run after 003 against `animal_marketplace`; additive, creates two tables.

USE `animal_marketplace`;

CREATE TABLE IF NOT EXISTS `cart_items` (
  `id`          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `buyer_id`    BIGINT UNSIGNED NOT NULL,
  `listing_id`  BIGINT UNSIGNED NOT NULL,
  `quantity`    DECIMAL(12,4)   NOT NULL,
  `created_at`  TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`  TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_cart_buyer_listing` (`buyer_id`, `listing_id`),
  KEY `idx_cart_buyer` (`buyer_id`),
  KEY `idx_cart_listing` (`listing_id`),
  CONSTRAINT `fk_cart_buyer` FOREIGN KEY (`buyer_id`) REFERENCES `buyer_profiles`(`user_id`) ON DELETE CASCADE,
  CONSTRAINT `fk_cart_listing` FOREIGN KEY (`listing_id`) REFERENCES `produce_listings`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `saved_listings` (
  `id`          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`     BIGINT UNSIGNED NOT NULL,
  `listing_id`  BIGINT UNSIGNED NOT NULL,
  `created_at`  TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_saved_user_listing` (`user_id`, `listing_id`),
  KEY `idx_saved_user` (`user_id`),
  KEY `idx_saved_listing` (`listing_id`),
  CONSTRAINT `fk_saved_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_saved_listing` FOREIGN KEY (`listing_id`) REFERENCES `produce_listings`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;