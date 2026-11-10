-- Web Push subscriptions (previously out of scope).
--
-- One row per browser/device. The endpoint is the push service URL and is
-- unique per subscription, so re-subscribing the same browser updates rather
-- than duplicates. Rows are deleted when the push service answers 404/410.

CREATE TABLE IF NOT EXISTS `push_subscriptions` (
  `id`         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`    BIGINT UNSIGNED NOT NULL,
  `endpoint`   VARCHAR(500) NOT NULL,
  `p256dh`     VARCHAR(200) NOT NULL,
  `auth`       VARCHAR(100) NOT NULL,
  `user_agent` VARCHAR(255) NULL DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_push_endpoint` (`endpoint`(255)),
  KEY `idx_push_user` (`user_id`),
  CONSTRAINT `fk_push_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
