-- Public ratings and reviews (previously out of scope for the first release).
--
-- One review per completed order, written by the buyer. The order_id unique key
-- is what enforces that rule in the database, not only in PHP: a retry or a
-- second tab cannot create a duplicate review.
--
-- status lets an administrator hide abusive content without deleting evidence.
-- The review itself is never edited by the farmer or the buyer after submission.

CREATE TABLE IF NOT EXISTS `farmer_reviews` (
  `id`         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `order_id`   BIGINT UNSIGNED NOT NULL,
  `farmer_id`  BIGINT UNSIGNED NOT NULL,
  `buyer_id`   BIGINT UNSIGNED NOT NULL,
  `rating`     TINYINT UNSIGNED NOT NULL,
  `comment`    VARCHAR(1000) NULL DEFAULT NULL,
  `status`     ENUM('visible','hidden') NOT NULL DEFAULT 'visible',
  `hidden_reason` VARCHAR(255) NULL DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_review_order` (`order_id`),
  KEY `idx_review_farmer` (`farmer_id`, `status`, `created_at`),
  CONSTRAINT `fk_review_order`  FOREIGN KEY (`order_id`)  REFERENCES `orders` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_review_farmer` FOREIGN KEY (`farmer_id`) REFERENCES `users` (`id`)  ON DELETE CASCADE,
  CONSTRAINT `fk_review_buyer`  FOREIGN KEY (`buyer_id`)  REFERENCES `users` (`id`)  ON DELETE CASCADE,
  CONSTRAINT `chk_review_rating` CHECK (`rating` BETWEEN 1 AND 5)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
