# Database migrations

`database/schema.sql` holds 17 base tables. The migrations below take that to
21 by adding `password_resets`, `user_preferences`, `notifications` and
`rate_limits`, plus columns on `users`. `tools/db-reset.php` loads `schema.sql`
and then replays every file in this directory in sorted filename order, so a
fresh rebuild only needs that one command.

## The migrations

| File | Adds |
|------|------|
| `002_password_resets_and_remember.sql` | `password_resets` table; `users.remember_token`, `users.remember_expires` and `idx_users_remember_token` |
| `003_user_preferences.sql` | `user_preferences` table |
| `004_cart_and_saved.sql` | `cart_items` and `saved_listings` - both already in `schema.sql`, so this is a no-op on a fresh build |
| `005_notifications.sql` | `notifications` table |
| `006_account_deletion.sql` | `users.anonymized_at`; adds `'anonymized'` to the `users.status` enum |
| `007_password_reset_expiry.sql` | rewrites `password_resets.expires_at` as `DATETIME` |

There is no `001`. Numbering starts at `002` because the base tables it would
have followed are now folded into `schema.sql`.

## Re-applying a migration

Every file in this directory is safe to run more than once. Verified on
2026-09-30 by applying the full set to a scratch database and then applying the
whole set twice more:

| File | Repeat runs |
|------|-------------|
| `002_password_resets_and_remember.sql` | clean |
| `003_user_preferences.sql` | clean |
| `004_cart_and_saved.sql` | clean |
| `005_notifications.sql` | clean |
| `006_account_deletion.sql` | clean |
| `007_password_reset_expiry.sql` | clean |

Also verified by re-applying all six to the live database, which already had
them: every file exited 0 and the schema and row counts were unchanged.

The unguarded forms do not survive a second run. `006` originally used a bare
`ALTER TABLE ... ADD COLUMN anonymized_at` and `ADD KEY idx_users_status`, which
abort with `ERROR 1060 Duplicate column name 'anonymized_at'`. `002` and `007`
already guarded theirs against `information_schema` and execute a no-op
(`SELECT 1` and `DO 0` respectively) when the change is already present. `006`
now follows the same pattern.

Note that `002`–`005` print a `1` on every guarded no-op, because their
fallback is `SELECT 1`. `007` uses `DO 0` specifically to avoid that, since
anything a migration prints is read as a failure by the test harness. Judge a
migration by its exit code, not its output.

Every file also carries its own `USE \`animal_marketplace\`;`, so it does not
depend on the caller having selected a database. `006` and `007` were missing
this and failed with `ERROR 1046 No database selected` when run without one.

## Applied state

The live development database has all six applied. Last confirmed 2026-09-30:
21 tables in total, and `password_resets.expires_at` is `DATETIME`:

```sql
SELECT COLUMN_NAME, DATA_TYPE, EXTRA
  FROM information_schema.COLUMNS
 WHERE TABLE_SCHEMA = 'animal_marketplace'
   AND TABLE_NAME   = 'password_resets'
   AND COLUMN_NAME  = 'expires_at';
```

`DATETIME` with an empty `EXTRA` is the expected result. If `EXTRA` still shows
`on update CURRENT_TIMESTAMP`, migration `007` has not been applied and reset
tokens will keep extending themselves every time their row is written to.

### Why 007 exists

`password_resets.expires_at` was created as
`TIMESTAMP ... DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP`. The
`ON UPDATE` clause meant any write to the row - including touching an unrelated
column - pushed the expiry into the future, so a token that had already expired
became valid again. `007` narrows the column to a plain `DATETIME` and
preserves `idx_pwreset_expires`. `database/animal_marketplace.sql`, the full
dump, carries the corrected definition too.

## Adding a new migration

Number it `008_`, give it a filename that says what it changes, and add a row
to the table above. It has to be safe to run against a database that does not
have it, and against one that does, because `db-reset.php` replays the whole
directory every time someone rebuilds.
