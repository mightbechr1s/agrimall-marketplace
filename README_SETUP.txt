# AniMAL — Team Laptop Setup Guide (via GitHub)

> **Source:** `https://github.com/mightbechr1s/animal-marketplace` (branch `main`)
> **Last verified:** 2026-09-25 — 21 tables full schema + migrations, seeded, offline-ready.

---

## 1. Prerequisites (once per machine)

| Tool | Version | Install notes |
|------|---------|---------------|
| **Git** | any | Required — used to pull the project |
| **XAMPP** | PHP 8.1+, MySQL 8.0 | Default install — `C:\xampp` |

Add to **PATH** (or use full paths below):
```
C:\xampp\php
C:\xampp\mysql\bin
```

The `php` that **PATH** resolves to must be the XAMPP build. If some other
PHP comes first — a winget or Scoop install, a version manager — it wins, and
the pages that need `mbstring` (the dashboard) fail with a **blank white page**
rather than a readable error. Preflight before blaming the app:

```powershell
php -r "echo extension_loaded('mbstring') ? 'ok' : 'MISSING mbstring';"
```

If that prints `MISSING`, call the full `C:\xampp\php\php.exe` path in every
command below — including `npm run dev`, which resolves `php` from PATH.

---

## 2. Get the project (clone, then pull for updates)

```powershell
cd C:\xampp\htdocs
git clone https://github.com/mightbechr1s/animal-marketplace.git animal
```

Result — web root is `C:\xampp\htdocs\animal\public`.

**Later updates** (the repo is the single source of truth):
```powershell
cd C:\xampp\htdocs\animal
git pull
```
After a pull that changes the database, re-run step 5 (reset) to apply new
migrations and re-seed.

---

## 3. Create the local config

From here on, run every command from the project root `C:\xampp\htdocs\animal`.

`config/config.php` is never committed (holds credentials). On a fresh
clone only `config/config.example.php` exists — copy it once:

```powershell
Copy-Item config\config.example.php config\config.php
```

XAMPP defaults are already inside the example (`127.0.0.1`, port `3306`,
user `root`, empty password), so no editing is needed for a local setup.
Only edit it for a different DB or a production `base_url`.

Two settings are worth knowing about before you blame something else:

- **`'secure_cookies' => true`** (the default) marks the session cookie
  `Secure`, which browsers refuse to store over plain `http`. Browsers special-case
  `localhost` so the app works, but command-line HTTP clients will silently fail to
  keep a session. The bundled test suites turn it off for the duration of a run and
  restore it afterwards; do the same by hand only if you script against the app
  over `http://127.0.0.1`.
- **`'env' => 'production'`** hides PHP errors from the browser, which is the safe
  default. `'development'` shows them and is only worth switching on while
  debugging.

---

## 4. Start the local stack (MySQL + PHP dev server)

```powershell
powershell -ExecutionPolicy Bypass -File tools\dev-server.ps1
```

This starts MariaDB (using `C:\xampp\mysql\my.ini`) and the PHP dev server,
waits for both to answer, and is safe to re-run. Stop everything with:

```powershell
powershell -ExecutionPolicy Bypass -File tools\dev-server.ps1 -Stop
```

To start MariaDB alone:

```powershell
# Run as Administrator the first time
Start-Process "C:\xampp\mysql\bin\mysqld.exe" -WorkingDirectory "C:\xampp\mysql" -WindowStyle Hidden
Start-Sleep 3

# Verify
& "C:\xampp\mysql\bin\mysqladmin.exe" -u root ping
# -> "mysqld is alive"
```

---

## 5. Create the database (use the reset tool — it applies ALL migrations)

The reset tool drops and rebuilds the DB from `database/schema.sql

(The Entity Relationship Diagram (ERD) is in docs/AniMAL_ERD_full.drawio, editable in diagrams.net, and docs/AniMAL_ERD_Documentation.pdf. It models all 15 tables and 2 views with Crow's Foot notation.)` + every
`database/migrations/*.sql` and seeds demo data. It needs `config/config.php`
(created in step 3) to read DB credentials:

```powershell
C:\xampp\php\php.exe tools\db-reset.php --yes
```

Expected output: `Reset complete ... (5 users, 6 listings)`.

Result — **21 tables + 2 views**. `database/schema.sql` creates the 17 base
tables; migrations add `password_resets`, `user_preferences`, and `notifications`;
`rate_limits` is created on first use by `rate_limit_check()`. The 2 views are
`v_active_listings` and `v_order_summary`.

Note: remember-me is **not** a separate table. It is stored on `users` as the
`remember_token` and `remember_expires` columns (see
`database/migrations/002_password_resets_and_remember.sql`).

Seed accounts created by `database/seed.php`:
`admin@animal.local` (admin123, from `schema.sql`),
`delagracia@animal.local` and `encarnacion@animal.local` (farmers),
`mercado@animal.local` and `dizon@animal.local` (buyers) — the last four
use password `password123`.

### Alternative — phpMyAdmin (GUI)

1. Start **Apache** in XAMPP Control Panel.
2. Open **http://127.0.0.1/phpmyadmin**
3. **Databases** tab — create `animal_marketplace` (collation `utf8mb4_unicode_ci`).
4. Select the database — **Import** tab — choose `database\animal_marketplace.sql` — **Go**.

> Note: `database/animal_marketplace.sql` is a static snapshot (21 tables + 2 views). Use
> `tools/db-reset.php` (step 5) instead whenever possible — it includes the
> latest cart/saved migration tables.

---

## 6. Start the PHP dev server

```powershell
C:\xampp\php\php.exe -S 127.0.0.1:8000 -t public public/router.php
```
*Keep this console open — it IS the web server.*

> **`public/router.php` is mandatory as the last argument.** It is what blocks
> script execution inside `public/uploads/`. Without it, PHP serves files
> directly, `uploads/.htaccess` is ignored, and an uploaded `.php` file runs as
> code. `tools/dev-server.ps1` (step 4) always passes it.

---

## 7. Smoke-test in browser

| URL | Expected |
|-----|----------|
| `http://127.0.0.1:8000/index.php` | Hero, two centered CTAs |
| `http://127.0.0.1:8000/register.php` | Inline consent, centered button |
| `http://127.0.0.1:8000/login.php` | Centered "Log in" |
| `http://127.0.0.1:8000/browse.php` | Cards, Add to Cart + Save buttons |
| `http://127.0.0.1:8000/cart.php` | Cart items, quantities, checkout |
| `http://127.0.0.1:8000/dashboard.php` (after login) | Role sidebar, orders, saved listings |
| `http://127.0.0.1:8000/account.php` | Profile, name, email and password forms, danger zone |
| `http://127.0.0.1:8000/account_delete.php` | Deletion briefing + confirmation (non-admin, no open orders) |

**Demo accounts** (created by the reset tool):
```
admin    admin@animal.local    admin123
farmer   delagracia@animal.local  password123
buyer    mercado@animal.local  password123
```

---

## 8. Verify OFFLINE (unplug network / disable Wi-Fi)

- Reload all URLs above — must render.
- DevTools — **Console** — 0 errors.
- **Network** tab — every request to `127.0.0.1` (no CDN, no Google Fonts).
- CSP header (`default-src 'self'`) guarantees nothing external loads.

---

## 9. Common fixes

| Symptom | Fix |
|---------|-----|
| `db-reset.php` says config missing | Run step 3 first (`Copy-Item config\config.example.php config\config.php`) |
| Port 8000 busy | `-S 127.0.0.1:8001` for the dev server (the test suites pick a free port themselves) |
| MySQL not running | Re-run step 4 |
| Wrong doc root | Must be `public/` |
| Firewall | Allow `php.exe`, `mysqld.exe` |
| Cache | `Ctrl+Shift+R` or incognito |

---

## 10. One-liner for daily starts (after first DB setup)

```powershell
Start-Process "C:\xampp\mysql\bin\mysqld.exe" -WindowStyle Hidden
Start-Sleep 2
C:\xampp\php\php.exe -S 127.0.0.1:8000 -t public public/router.php
```

---

## 11. Updating the project later

```powershell
# Pull latest code (and re-apply migrations if the DB changed)
git pull
C:\xampp\php\php.exe tools\db-reset.php --yes   # only if the pull touched the schema
```

There is no USB sync anymore — the GitHub repo is the single source of
truth. Local-only files (`config/config.php`, `node_modules`, `logs/`,
`public/uploads/`) are gitignored and survive a pull. Never edit `config.php`
out of sync with `config.example.php`.

`database/migrations/README.txt` records which migrations are applied to the
live database, and which of them are safe to run twice.

---

## 12. What MUST NOT be changed

| Area | Reason |
|------|--------|
| `src/functions.php` helpers (`e()`, `csrf_*`, `asset()`, `money()`, `audit_log()`) | Core security / i18n |
| `public/assets/vendor/*` | Offline assets — no CDN fallback |
| CSP header in `src/bootstrap.php` | Keeps site fully offline |
| `database/migrations/*.sql` (already applied) | Schema adds — new migration + approval; see `database/migrations/README.txt` for what is applied and which files are safe to re-run |
| `config/config.php` content | Local credentials; stay in sync with `config.example.php` |

---

## 13. Quick checklist for teammates

- [ ] Git + XAMPP installed, paths in PATH
- [ ] `git clone https://github.com/mightbechr1s/animal-marketplace.git animal`
- [ ] `Copy-Item config\config.example.php config\config.php`
- [ ] MySQL started, `php tools\db-reset.php --yes` done (21 tables)
- [ ] Dev server running on `127.0.0.1:8000`
- [ ] All smoke-test URLs load, cart/saved/account work
- [ ] Offline test passes (0 errors, 0 external requests)
- [ ] Demo accounts work for each role

---

## 14. Running the test suites

```powershell
npm install             # once, for the browser test
npm test                # syntax, i18n, validation, PDF, security, doc drift
npm run test:http       # HTTP flows against a throwaway database
npm run test:panels     # sidebar links, role gating, admin workflows
npm run test:e2e        # browser smoke test
npm run test:toast      # toast behaviour in a real browser (needs `npm run dev` up)
```

---

## 15. Where the documentation lives

| Document | Read it for |
|---|---|
| `docs/SRS.txt` | What the system is required to do. The single source of truth for requirements. |
| `docs/traceability-matrix.txt` | Whether each requirement is met, and by what evidence. **Generated** - do not edit by hand; regenerate with `php tools\gen_traceability.php`. |
| `docs/decisions.txt` | Measures taken that no requirement covers, and their limits. Read this before proposing a larger scale. |
| `docs/reviewer-guide.txt` | How to review changes here, and the traps that have already fooled us. |
| `docs/nginx-hardening.conf` | Suggested production web-server configuration. |

If you change a constant that `docs/decisions.txt` quotes (a page size, an export
cap, the sitemap chunking), `npm test` will fail until the document is updated.
That is intentional: the document is a claim about the code, and drift in either
direction is a defect.

- Leave `npm run dev` running if you like. Each suite starts its own `php -S` on
  a free port and stops only the server it started.
- The suites build and drop `animal_marketplace_test` only, then restore
  `config\config.php`. Your live `animal_marketplace` database is never touched.
- MySQL must be running. If it is not, a suite stops with one clear message
  instead of reporting a migration error per file.

If your tools are not under `C:\xampp`, point the suites at yours once per
PowerShell session:

```powershell
$env:ANIMAL_PHP   = 'D:\xampp\php\php.exe'
$env:ANIMAL_MYSQL = 'D:\xampp\mysql\bin\mysql.exe'
$env:ANIMAL_PORT  = 8001          # optional: pin the port instead of auto-picking
```

---

**That's it.** The GitHub repo contains the exact, vetted codebase and the
reset tool produces a fresh, fully-migrated DB. Following these steps
reproduces the same working, fully-offline environment on any Windows laptop
with Git + XAMPP.