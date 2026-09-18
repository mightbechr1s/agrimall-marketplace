# Backend / Frontend alignment status

Status: `main` currently carries the Controller-based skeleton backend
(`src/Core/*`, `src/Features/*`) plus the frontend-milestone static pages
(`public/*.html`). The live "current frontend" used on the demo machine is a
separate, fully working dynamic build. This document lists exactly what the
backend on `main` must match so the current frontend runs from a fresh clone.

## References

- Working dynamic build (authoritative source of the current frontend + its
  backend): local working copy on the dev machine, and the USB package
  `F:\AniMAL_Demo\app` (files `public/*.php`, `src/*`, `database/*`).
- Full snapshot of that working build was captured once as commit `bd65321`
  and is still reachable in a local clone on the dev machine (not on `origin`
  after the restore to `fdd8f4b`).

## What is on `main` today

- `src/Core/` — bootstrap, DB, auth, CSRF, helpers, router stubs, session.
- `src/Features/` — `Admin`, `Auth`, `Dashboard`, `Listings`, `Order`
  controllers written against the organized schema.
- `public/` — placeholder `.php` pages (index/login/register/listing) marked
  TODO, plus the 7 static frontend milestone `.html` pages just merged.
- `database/` — `schema.sql`, `migrations/001_core_marketplace_tables.sql`,
  `seed/seed.sql`, no dump file.

## What the current frontend requires (from the working build)

The current frontend pages assume a flat `src/` bootstrap, not
`src/Core` + `src/Features`:

| Current build file | Purpose | Repo status |
|---|---|---|
| `src/bootstrap.php` | loads config, session, error config | missing (repo has `src/Core/bootstrap.php`) |
| `src/DB.php` | PDO connection factory | missing in flat form |
| `src/functions.php` | helpers: `t()`, `e()`, `p()`, `csrf_field()`, auth/session guards, flash | missing |
| `src/i18n.php` | translation runtime (`t()`, `locale_current()`) | missing |
| `src/i18n_copy.php` | UI copy arrays (en + `fill` 3 languages) | missing |
| `config/config.php` | env keys: `app.error_logging[]`, db, session, site | repo has only `config.example.php` |
| `public/router.php` | PHP built-in server router (clean URLs) | missing |

### Pages in the current frontend

`404.php, 500.php, admin_audit.php, admin_reports.php, admin_verify.php,
browse.php, cookies.php, create_listing.php, dashboard.php, forgot.php,
index.php, listing.php, listing_new.php, login.php, logout.php, order.php,
order_action.php, order_request.php, privacy.php, register.php, reset.php,
settings.php, sitemap.php, terms.php, upload_image.php`, static assets
(`manifest.json`, `sw.js`, `robots.txt`), `router.php`, partials
`header.php/footer.php/dashboard-layout.php`, and the design assets
(`public/assets/css/design-system.css`, `motion.min.css`,
`public/assets/js/motion.min.js`, `swipe.min.js`, `public/assets/vendor/`).

### Data layer

- `database/migrations/002_password_resets_and_remember.sql`
- `database/migrations/003_user_preferences.sql`
- `database/seed.php` (seed runner) and the captured seeded dump
  (`database/animal_marketplace.sql`, ~50 KB)
- Demo accounts: `admin@animal.local/admin123`, `delagracia@animal.local` and
  `mercado@animal.local` / `password123`.

## Required work (backend team)

1. Adopt the working build's `src/bootstrap.php`, `src/DB.php`,
   `src/functions.php`, `src/i18n.php`, `src/i18n_copy.php` and the matching
   `public/router.php` — either verbatim from the reference build, then adjust
   for the team's Controller architecture, or document the parity contract
   page-by-page.
2. Decide one backend shape: keep Controllers (`src/Features`) and make the
   flat helpers delegate to it, or replace Controllers with the flat build.
   Do not keep both; route table must be single-sourced.
3. Bring DB to parity: apply migrations 002 and 003 and the seed data so the
   auth/password-reset/preference features in the current frontend resolve.
4. Commit the seeded dump `database/animal_marketplace.sql` so a fresh clone
   can import one file and run.
5. Keep `config/config.example.php` in sync with the config keys the flat
   bootstrap reads (`app.error_logging`, `app.env`).

Not started: Controller-to-frontend porting. The merged static `.html` pages
are a design reference only; the dynamic `.php` pages are the deliverable.