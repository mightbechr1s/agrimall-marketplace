# Environment Verification Log (Sprint 1, issue #8)

Goal: XAMPP install, schema import, and app run verified on 2 machines.
Setup steps are documented in `README_SETUP.md` (USB distribution flow).
Any setup bug found during verification must be logged as a GitHub issue.

## Verification checklist (per machine)

- [ ] XAMPP installed with PHP 8.1+ and MySQL 8.0
- [ ] `C:\xampp\php` and `C:\xampp\mysql\bin` on PATH
- [ ] Project copied to `C:\xampp\htdocs\animal`
- [ ] MySQL started (`mysqladmin -u root ping` -> "mysqld is alive")
- [ ] Database imported (CLI `mysql < database\animal_marketplace.sql` or phpMyAdmin), 21 tables listed
- [ ] `config/config.php` copied from example, defaults match
- [ ] PHP dev server runs: `php -S 127.0.0.1:8000 -t public public/router.php`
- [ ] Smoke URLs load: `/index.php`, `/register.php`, `/login.php`, `/browse.php`
- [ ] Demo login works per role (see `DEMO_ACCOUNTS.md`)
- [ ] Offline pass: 0 console errors, no external requests (CSP `default-src 'self'`)

## Recorded runs

| Machine | Date | Result | Notes |
|---------|------|--------|-------|
| A - source laptop (Windows, XAMPP) | 2026-09-17 | Pass | 129 files synced to USB, DB dump 51.7 KB, 0 console errors, fully offline |
| B - team laptop (Windows, XAMPP) | 2026-09-17 | Pass | Same flow reproduced from USB per `README_SETUP.md` section 2-8 |

Setup bugs logged during these runs: none outstanding.

## Current re-verification (after issue #4 seed changes)

- [ ] Run `php tools\db-reset.php --yes` (fresh schema + seed via CLI)
- [ ] Repeat checklist rows above for both machines
- [ ] Confirm demo accounts from `DEMO_ACCOUNTS.md` all log in
- [ ] Confirm `database/animal_marketplace.sql` re-exported so teammates keep importing a dump that matches the seeded standing