-- Payload-less web push needs a marker so the service worker does not show the
-- same notification twice. push_pending.php claims the newest unclaimed row and
-- stamps it here.
--
-- Guarded like 002: db-reset.php replays this whole directory, so a second run
-- must not abort on a duplicate column or index.

USE `animal_marketplace`;

SET @col_exists = (SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = 'animal_marketplace' AND TABLE_NAME = 'notifications'
    AND COLUMN_NAME = 'push_sent_at');

SET @ddl = IF(@col_exists = 0,
  'ALTER TABLE `notifications` ADD COLUMN `push_sent_at` TIMESTAMP NULL DEFAULT NULL AFTER `read_at`',
  'DO 0');
PREPARE stmt FROM @ddl;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @idx_exists = (SELECT COUNT(*) FROM information_schema.STATISTICS
  WHERE TABLE_SCHEMA = 'animal_marketplace' AND TABLE_NAME = 'notifications'
    AND INDEX_NAME = 'idx_notify_push_pending');

SET @ddl = IF(@idx_exists = 0,
  'ALTER TABLE `notifications` ADD INDEX `idx_notify_push_pending` (`user_id`, `push_sent_at`)',
  'DO 0');
PREPARE stmt FROM @ddl;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
