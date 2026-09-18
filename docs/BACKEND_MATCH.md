# Backend / Frontend alignment status — RESOLVED

`main` now carries the current working build end to end (dynamic frontend +
its backend + seeded database). The older Controller-based skeleton was
superseded and removed from the tree; it remains in git history (see
"History" below) for reference.

## What changed in the alignment commit

Adopted (moved into the repo from the working build):

- Backend: `src/bootstrap.php`, `src/DB.php`, `src/functions.php`,
  `src/i18n.php`, `src/i18n_copy.php`.
- Frontend: dynamic pages under `public/` (index, browse, listing,
  listing_new, create_listing, order, order_request, order_action, dashboard,
  settings, cookies, privacy, terms, sitemap, 404, 500, upload_image,
  admin_audit, admin_reports, admin_verify, auth pages), partials
  (`header.php`, `footer.php`, `dashboard-layout.php`), `public/router.php`
  for the PHP built-in server, and offline design assets
  (`public/assets/css/design-system.css`, `motion.min.css`,
  `public/assets/js/motion.min.js`, `swipe.min.js`,
  `public/assets/vendor/`, `public/assets/images/`).
- Data layer: `database/animal_marketplace.sql` (seeded dump, ~50 KB),
  `database/seed.php`, migrations `002_password_resets_and_remember.sql` and
  `003_user_preferences.sql`. Dump import restores the full demo data set.
- Config: `config/config.example.php` synced with the flat bootstrap's keys
  (`app.error_logging`, `app.env`). `config/config.php` stays untracked.

Removed (superseded by the flat build, kept in history):

- `src/Core/*` (bootstrap/DB/auth/CSRF/helpers/router/session).
- `src/Features/*` (Admin/Auth/Dashboard/Listings/Order controllers).
- `database/migrations/001_core_marketplace_tables.sql`,
  `database/seed/seed.sql` (replaced by `schema.sql` + `seed.php` + dump).

## Setup after this commit

1. Clone and copy config: `config/config.php` from
   `config/config.example.php`.
2. Start MySQL, create database `animal_marketplace`.
3. Import: `mysql -u root < database/animal_marketplace.sql`.
4. Serve from the project root:
   `php -S 127.0.0.1:8000 -t public public/router.php`.

Demo accounts: `admin@animal.local` / `admin123`,
`delagracia@animal.local` and `mercado@animal.local` / `password123`.

## History

- `bd65321`-style full-app snapshot exists locally on the dev machine
  (reachable clone), not required anymore — this commit supersedes it.
- The frontend-milestone static pages (`public/*.html`) remain as a design
  reference alongside the dynamic pages.