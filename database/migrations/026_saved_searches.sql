-- Saved searches: buyers can save a browse query and get notified when new
-- listings match it.

CREATE TABLE IF NOT EXISTS `saved_searches` (
    `id`         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id`    BIGINT UNSIGNED NOT NULL,
    `query`      VARCHAR(255) NOT NULL DEFAULT '',
    `category`   VARCHAR(100) NOT NULL DEFAULT '',
    `price_min`  DECIMAL(12,2) NULL DEFAULT NULL,
    `price_max`  DECIMAL(12,2) NULL DEFAULT NULL,
    `notify`     TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_saved_search_user` (`user_id`),
    CONSTRAINT `fk_saved_search_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
