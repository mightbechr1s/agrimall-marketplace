-- 032: one-time 2FA recovery codes.
-- Safe to re-run: the table is created only when absent. TiDB has no
-- multi-clause ALTER with AFTER, so this stays a plain CREATE TABLE.
USE `animal_marketplace`;

CREATE TABLE IF NOT EXISTS `user_recovery_codes` (
  `id`         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`    BIGINT UNSIGNED NOT NULL,
  `code_hash`  VARCHAR(255)    NOT NULL,
  `used_at`    TIMESTAMP       NULL,
  `created_at` TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_recovery_user` (`user_id`),
  CONSTRAINT `fk_recovery_user` FOREIGN KEY (`user_id`)
    REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
