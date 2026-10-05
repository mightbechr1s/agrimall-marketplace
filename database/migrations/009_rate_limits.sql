-- =====================================================================
-- 009: RATE LIMITS
-- =====================================================================
-- Why this file exists
-- --------------------
-- rate_limit_check() in src/functions.php queries and upserts this table on
-- every login and password-reset POST (public/login.php, public/forgot.php).
-- The table was only ever defined in database/animal_marketplace.sql, a full
-- dump that tools/db-reset.php does not run -- db-reset loads schema.sql and
-- then database/migrations/*.sql.
--
-- Consequence before this migration: a fresh clone built with the documented
-- reset tool produced a database with 20 tables and no rate_limits, so the
-- first login attempt hit "Unknown table 'rate_limits'". Verified by
-- replaying schema.sql + migrations into a scratch database.
--
-- This migration is additive and idempotent, so it is safe on a database that
-- already has the table from the dump.

CREATE TABLE IF NOT EXISTS `rate_limits` (
    `key`          VARCHAR(128) NOT NULL,
    `attempts`     INT UNSIGNED  NOT NULL DEFAULT 0,
    `window_start` INT UNSIGNED  NOT NULL,
    PRIMARY KEY (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
