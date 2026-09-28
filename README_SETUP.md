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

The reset tool drops and rebuilds the DB from `database/schema.sql` + every
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
| `http://127.0.0.1:8000/account.php` | Profile + password forms |

**Demo accounts** (from `DEMO_ACCOUNTS.md`):
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
| Port 8000 busy | `-S 127.0.0.1:8001` |
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

---

## 12. What MUST NOT be changed

| Area | Reason |
|------|--------|
| `src/functions.php` helpers (`e()`, `csrf_*`, `asset()`, `money()`, `audit_log()`) | Core security / i18n |
| `public/assets/vendor/*` | Offline assets — no CDN fallback |
| CSP header in `src/bootstrap.php` | Keeps site fully offline |
| `database/migrations/*.sql` (already applied) | Schema adds — new migration + approval |
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

**That's it.** The GitHub repo contains the exact, vetted codebase and the
reset tool produces a fresh, fully-migrated DB. Following these steps
reproduces the same working, fully-offline environment on any Windows laptop
with Git + XAMPP.