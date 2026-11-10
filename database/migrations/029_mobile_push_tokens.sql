-- Expo push tokens for the mobile app.
--
-- The web app uses VAPID web push (push_subscriptions); Expo devices get a
-- token from Expo's push service instead. One row per device; re-registering
-- the same token updates it rather than duplicating.

USE `animal_marketplace`;

CREATE TABLE IF NOT EXISTS `mobile_push_tokens` (
  `id`           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`      BIGINT UNSIGNED NOT NULL,
  `token`        VARCHAR(191) NOT NULL,
  `platform`     ENUM('ios','android') NOT NULL DEFAULT 'android',
  `device_name`  VARCHAR(100) NULL DEFAULT NULL,
  `created_at`   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `last_used_at` TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_mobile_push_token` (`token`),
  KEY `idx_mobile_push_user` (`user_id`),
  CONSTRAINT `fk_mobile_push_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
