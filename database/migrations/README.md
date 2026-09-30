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

Most of these are safe to run twice, but not all of them. Verified on
2026-09-30 by applying the full set to a scratch database and then applying the
whole set a second time:

| File | Second run |
|------|------------|
| `002_password_resets_and_remember.sql` | succeeds (prints row counts) |
| `003_user_preferences.sql` | idempotent |
| `004_cart_and_saved.sql` | idempotent |
| `005_notifications.sql` | idempotent |
| `006_account_deletion.sql` | **fails** - `ERROR 1060 Duplicate column name 'anonymized_at'` |
| `007_password_reset_expiry.sql` | idempotent |

`006` needs its `ALTER TABLE` statements guarded before it can be re-applied.
Until then, do not re-run it against a database that already has it: the abort
is harmless but noisy, and it leaves the migrations' later steps unrun if you
treat a non-zero exit as fatal.

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
