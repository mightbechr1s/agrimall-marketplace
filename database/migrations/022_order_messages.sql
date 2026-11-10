-- Order-scoped messages between the buyer and the farmer.
--
-- Deliberately not a general chat system: a thread belongs to one order, which
-- gives both the access check and the context for free. read_at is set when the
-- other party opens the order page.

CREATE TABLE IF NOT EXISTS `order_messages` (
  `id`          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `order_id`    BIGINT UNSIGNED NOT NULL,
  `sender_id`   BIGINT UNSIGNED NOT NULL,
  `sender_role` ENUM('buyer','farmer','admin') NOT NULL,
  `body`        VARCHAR(2000) NOT NULL,
  `read_at`     TIMESTAMP NULL DEFAULT NULL,
  `created_at`  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_message_order` (`order_id`, `created_at`),
  KEY `idx_message_unread` (`order_id`, `read_at`),
  CONSTRAINT `fk_message_order`  FOREIGN KEY (`order_id`)  REFERENCES `orders` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_message_sender` FOREIGN KEY (`sender_id`) REFERENCES `users` (`id`)  ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
