-- AniMAL Migration 003: Per-user preferences (settings page storage)
-- Run after schema.sql and 002 against `animal_marketplace`
-- Additive only: creates one table, touches no existing data.

USE `animal_marketplace`;

CREATE TABLE IF NOT EXISTS `user_preferences` (
  `user_id`           BIGINT UNSIGNED NOT NULL,
  `locale`            ENUM('en','tl')      NOT NULL DEFAULT 'en',
  `theme`             ENUM('system','light','dark') NOT NULL DEFAULT 'system',
  `notify_inapp`      TINYINT(1) NOT NULL DEFAULT 1,
  `notify_orders`     TINYINT(1) NOT NULL DEFAULT 1,
  `notify_messages`   TINYINT(1) NOT NULL DEFAULT 1,
  `notify_promotions` TINYINT(1) NOT NULL DEFAULT 0,
  `reduce_motion`     TINYINT(1) NOT NULL DEFAULT 0,
  `larger_text`       TINYINT(1) NOT NULL DEFAULT 0,
  `updated_at`        TIMESTAMP  NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`user_id`),
  CONSTRAINT `fk_userprefs_user` FOREIGN KEY (`user_id`)
    REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
