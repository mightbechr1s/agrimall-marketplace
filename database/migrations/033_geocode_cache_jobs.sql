-- 033: geocode_cache + geocode_jobs.
-- Geocode results are cached by query hash so the same address is never
-- sent to Nominatim twice. Pending/failed geocodes are queued in
-- geocode_jobs so registration never blocks on an external API call;
-- a background process (or the next page load) can drain the queue.
-- Safe to re-run: CREATE TABLE IF NOT EXISTS.

CREATE TABLE IF NOT EXISTS `geocode_cache` (
  `query_hash`  CHAR(64)      NOT NULL,
  `query`       TEXT          NOT NULL,
  `latitude`    DECIMAL(10,6) NOT NULL,
  `longitude`   DECIMAL(10,6) NOT NULL,
  `display_name`TEXT         NULL,
  `hits`        INT UNSIGNED  NOT NULL DEFAULT 1,
  `created_at`  TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`query_hash`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `geocode_jobs` (
  `id`           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`      BIGINT UNSIGNED NOT NULL,
  `address_query`TEXT          NOT NULL,
  `status`       ENUM('pending','done','failed') NOT NULL DEFAULT 'pending',
  `latitude`     DECIMAL(10,6)  NULL,
  `longitude`    DECIMAL(10,6)  NULL,
  `error`        TEXT          NULL,
  `created_at`   TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `completed_at` TIMESTAMP     NULL,
  PRIMARY KEY (`id`),
  KEY `idx_jobs_user` (`user_id`),
  KEY `idx_jobs_status` (`status`),
  CONSTRAINT `fk_jobs_user` FOREIGN KEY (`user_id`)
    REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
