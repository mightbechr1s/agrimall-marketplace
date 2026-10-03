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
| `008_numeric_check_constraints.sql` | `CHECK` constraints on the money and quantity columns of `produce_listings`, `cart_items` and `orders` |

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
| `008_numeric_check_constraints.sql` | clean |

Also verified by re-applying all seven to the live database, which already had
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

The live development database has all eight applied. Last confirmed 2026-10-03:
21 tables plus 2 views, `password_resets.expires_at` is `DATETIME`, and the
eight `CHECK` constraints from `008` are present.

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

Number it `009_`, give it a filename that says what it changes, and add a row
to the table above. It has to be safe to run against a database that does not
have it, and against one that does, because `db-reset.php` replays the whole
directory every time someone rebuilds.

### Why 008 exists

`src/functions.php::number_in()` now reads every submitted quantity and price,
because a bare `(float)` cast cannot validate anything: `(float)'abc'` is
`0.0`, `(float)'12abc'` is `12.0`, and `(float)'1e999'` is `INF`, which
satisfies every `> 0` comparison. The worst case was `available_quantity`,
whose check read `$availableQuantity < 0` — unparseable input produced
`null`, and `null < 0` is false, so the bad value passed validation and was
written out.

`008` puts the same floor in the database, so MariaDB refuses a bad
`INSERT`/`UPDATE` regardless of which endpoint issued it. `number_in()` stays
the first line of defence and supplies the user-facing message; these
constraints are the backstop. Verify them with:

```sql
SELECT TABLE_NAME, CONSTRAINT_NAME
  FROM information_schema.TABLE_CONSTRAINTS
 WHERE CONSTRAINT_SCHEMA = 'animal_marketplace'
   AND CONSTRAINT_TYPE = 'CHECK'
 ORDER BY TABLE_NAME, CONSTRAINT_NAME;
```

Eight rows should come back. `admin_audit_log` also carries two pre-existing
`CHECK` constraints (`old_values`, `new_values`) that have nothing to do with
this migration.

Keep one constraint per `ALTER TABLE`, each guarded individually. MariaDB 10.4
accepts only one `ADD` clause per `ALTER`, and the comma-joined form fails with
`ERROR 1064`. The tuple spelling `ADD (CONSTRAINT a CHECK (...), CONSTRAINT b
CHECK (...))` parses without complaint and then silently discards every
constraint, so the migration reports success and the table stays unprotected.
