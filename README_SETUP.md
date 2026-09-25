# AniMAL — Team Laptop Setup Guide (Offline-Ready)

> **Source:** `D:\Projects\VSCPROJ\AniMAL` → USB `F:\AniMAL_Demo`  
> **Last verified:** 2026-09-17 — 129 files synced, DB dump 51.7 KB, 0 console errors, fully offline.

---

## 1. Prerequisites (once per machine)

| Tool | Version | Install notes |
|------|---------|---------------|
| **XAMPP** | PHP 8.1+, MySQL 8.0 | Default install → `C:\xampp` |
| **Git** (optional) | any | Only if you'll pull updates later |

Add to **PATH** (or use full paths below):
```
C:\xampp\php
C:\xampp\mysql\bin
```

---

## 2. Copy the project

```powershell
# From USB (already vetted)
Copy-Item -Recurse F:\AniMAL_Demo\app C:\xampp\htdocs\animal

# Or directly from the repo
# Copy-Item -Recurse D:\Projects\VSCPROJ\AniMAL C:\xampp\htdocs\animal
```
Result → web root is `C:\xampp\htdocs\animal\public`.

---

## 3. Start MySQL

```powershell
# Run as Administrator the first time
Start-Process "C:\xampp\mysql\bin\mysqld.exe" -WindowStyle Hidden
Start-Sleep 3

# Verify
& "C:\xampp\mysql\bin\mysqladmin.exe" -u root ping
# → "mysqld is alive"
```

---

## 4. Import the database

### Option A — Command line (fast, scriptable)
```powershell
cd C:\xampp\htdocs\animal
& "C:\xampp\mysql\bin\mysql.exe" -u root < database\animal_marketplace.sql
```

### Option B — **phpMyAdmin** (GUI, easier)

1. Start **Apache** in XAMPP Control Panel.
2. Open **http://127.0.0.1/phpmyadmin**
3. **Databases** tab → create `animal_marketplace` (collation `utf8mb4_unicode_ci`).
4. Select the database → **Import** tab → choose `C:\xampp\htdocs\animal\database\animal_marketplace.sql` → **Go**.
5. Verify: 21 tables listed (including `user_preferences`, `rate_limits`).

---

## 5. Verify configuration (normally no edits)

File: `C:\xampp\htdocs\animal\config\config.php`
```php
return [
    'host'     => '127.0.0.1',
    'port'     => 3306,
    'name'     => 'animal_marketplace',
    'user'     => 'root',
    'pass'     => '',                 // XAMPP default
    'base_url' => 'http://127.0.0.1:8000',   // cosmetic only
];
```

---

## 6. Start the PHP dev server

```powershell
cd C:\xampp\htdocs\animal
C:\xampp\php\php.exe -S 127.0.0.1:8000 -t public public/router.php
```
*Keep this console open — it IS the web server.*

---

## 7. Smoke-test in browser

| URL | Expected |
|-----|----------|
| `http://127.0.0.1:8000/index.php` | Hero, two centered CTAs |
| `http://127.0.0.1:8000/register.php` | Inline consent, centered button |
| `http://127.0.0.1:8000/login.php` | Centered "Log in" |
| `http://127.0.0.1:8000/browse.php` | Cards, aligned "Load more" |
| `http://127.0.0.1:8000/dashboard.php` (after login) | Role sidebar, centered actions |

**Demo accounts** (from `DEMO_ACCOUNTS.md`):
```
admin    admin@animal.local    admin123
farmer   delagracia@animal.local  password123
buyer    mercado@animal.local  password123
```

---

## 8. Verify OFFLINE (unplug network / disable Wi-Fi)

- Reload all URLs above → must render.
- DevTools → **Console** → 0 errors.
- **Network** tab → every request to `127.0.0.1` (no CDN, no Google Fonts).
- CSP header (`default-src 'self'`) guarantees nothing external loads.

---

## 9. Common fixes

| Symptom | Fix |
|---------|-----|
| Port 8000 busy | `-S 127.0.0.1:8001` |
| MySQL not running | Re-run step 3 |
| Wrong doc root | Must be `public/` |
| Firewall | Allow `php.exe`, `mysqld.exe` |
| Cache | `Ctrl+Shift+R` or incognito |

---

## 10. One-liner for daily starts (after first DB import)

```powershell
Start-Process "C:\xampp\mysql\bin\mysqld.exe" -WindowStyle Hidden
Start-Sleep 2
cd C:\xampp\htdocs\animal
C:\xampp\php\php.exe -S 127.0.0.1:8000 -t public public/router.php
```

---

## 11. Updating the USB later

```powershell
# 1. Code (point $R at your repo, $U at the USB root)
$R='D:\Projects\VSCPROJ\AniMAL'; $U='F:\AniMAL_Demo'
robocopy $R $U\app /E /XD node_modules .git .hallmark .impeccable logs /XF *.tmp.js server.log

# 2. Database dump — ALWAYS use the safe script (it strips DEFINER clauses).
#    Run from the USB root; set -Port to match YOUR MySQL.
#    NEVER export with a raw mysqldump or phpMyAdmin: that re-adds
#    DEFINER=`root`@`localhost` and the dump imports only 19 tables again.
powershell -ExecutionPolicy Bypass -File "$U\sync_db_dump.ps1" -Port 3306 -VerifyImport
```

---

## 12. What MUST NOT be changed

| Area | Reason |
|------|--------|
| `src/functions.php` helpers (`e()`, `csrf_*`, `asset()`, `money()`, `audit_log()`) | Core security / i18n |
| `public/assets/vendor/*` | Offline assets — no CDN fallback |
| CSP header in `src/bootstrap.php` | Keeps site fully offline |
| `database/migrations/003_user_preferences.sql` | Already applied; further schema → new migration + approval |

---

## 13. Quick checklist for teammates

- [ ] XAMPP installed, paths in PATH
- [ ] Project copied to `C:\xampp\htdocs\animal`
- [ ] MySQL started, DB imported (CLI or phpMyAdmin)
- [ ] `config/config.php` matches defaults
- [ ] Dev server running on `127.0.0.1:8000`
- [ ] All 5 smoke-test URLs load, buttons centered, consent inline
- [ ] Offline test passes (0 errors, 0 external requests)
- [ ] Demo accounts work for each role

---

**That's it.** The USB contains the exact, vetted codebase and a fresh DB dump. Following these steps reproduces the same working, fully-offline environment on any Windows laptop with XAMPP.