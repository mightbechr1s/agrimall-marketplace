-- =====================================================================
-- 007: Password reset expiry must never move on its own
-- =====================================================================
-- `password_resets.expires_at` is the one column in the schema that carries
-- real security weight: it is the deadline on a token that resets a password.
-- It is also the first TIMESTAMP column in its table, and this server is
-- MariaDB 10.4 running `explicit_defaults_for_timestamp = 0` (read-only, so
-- it cannot be overridden per session).
--
-- Under that setting MariaDB silently rewrites a bare TIMESTAMP column into
--
--     timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
--
-- so migration 002's plain `TIMESTAMP NOT NULL` was never the schema that
-- actually got created. The live table carried `ON UPDATE
-- CURRENT_TIMESTAMP`, which means any UPDATE that touched a *different* column
-- in the row restamped the expiry to the current time. An expired token that
-- any maintenance query laid a hand on was silently brought back to life.
--
-- Note that explicitly setting expires_at suppresses ON UPDATE for that
-- statement, so the failure mode is specifically the "touch some other column"
-- case -- which is exactly the sort of query that gets written casually.
--
-- DATETIME is exempt from that injection rule, so the fix is a type change
-- rather than a fight with a read-only server variable. It is also the more
-- honest type here: forgot.php writes a naive `Y-m-d H:i:s` wall-clock string
-- and reset.php reads it back with `new DateTime(...)`, neither of which
-- applies a timezone. Under TIMESTAMP the session `time_zone` was quietly
-- shifting that value on both the write and the read.
--
-- Guarded on DATA_TYPE so this is a no-op on a database that is already
-- correct, and a no-op when `password_resets` does not exist yet. The no-op
-- branch is `DO 0` rather than the `SELECT 1` used by the older migrations,
-- because `SELECT 1` prints a result set and the test harness reads any
-- migration output as a failure.
USE `animal_marketplace`;

SET @col_type = (SELECT DATA_TYPE FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = 'animal_marketplace'
    AND TABLE_NAME = 'password_resets'
    AND COLUMN_NAME = 'expires_at');

SET @ddl = IF(@col_type = 'timestamp',
  'ALTER TABLE `password_resets` MODIFY COLUMN `expires_at` DATETIME NOT NULL',
  'DO 0');
PREPARE stmt FROM @ddl;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
