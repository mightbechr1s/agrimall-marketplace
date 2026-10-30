-- AgriMALL Migration 005: in-app notifications.
-- Run after 004 against `animal_marketplace`; additive, creates one table.

USE `animal_marketplace`;

CREATE TABLE IF NOT EXISTS `notifications` (
  `id`         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`    BIGINT UNSIGNED NOT NULL,
  `category`   ENUM('orders','messages','promotions') NOT NULL DEFAULT 'orders',
  `title`      VARCHAR(160)  NOT NULL,
  `body`       VARCHAR(400)  NULL,
  `link`       VARCHAR(255)  NULL,
  `read_at`    TIMESTAMP     NULL DEFAULT NULL,
  `created_at` TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_notif_user_unread` (`user_id`, `read_at`, `created_at`),
  KEY `idx_notif_user_recent` (`user_id`, `created_at`),
  CONSTRAINT `fk_notif_user` FOREIGN KEY (`user_id`)
    REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
