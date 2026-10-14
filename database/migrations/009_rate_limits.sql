-- 009: rate_limits. rate_limit_check() (src/functions.php) queries this on every login and
-- password-reset POST, but the table only ever existed in animal_marketplace.sql, a dump
-- tools/db-reset.php does not run -- so fresh clones hit "Unknown table 'rate_limits'".
-- Additive and idempotent, safe on a database that already has it from the dump.

CREATE TABLE IF NOT EXISTS `rate_limits` (
    `key`          VARCHAR(128) NOT NULL,
    `attempts`     INT UNSIGNED  NOT NULL DEFAULT 0,
    `window_start` INT UNSIGNED  NOT NULL,
    PRIMARY KEY (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
