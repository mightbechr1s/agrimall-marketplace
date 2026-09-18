# AniMAL Audit Report

**Date:** 2026-09-07
**Auditors:** Claude Code team (6 parallel agents) + direct verification
**Environment:** Windows 11, XAMPP (PHP 8.2.12, MySQL 8.0), `php -S localhost:8000 -t public`
**Web Root:** `D:\Projects\VSCPROJ\AniMAL\public`

---

## Executive Summary

The AniMAL core framework is structurally sound: PDO prepared statements everywhere (emulation off), centralized `e()` output escaping, CSRF on all state-changing forms, `password_hash`/`password_verify`, and hardened session cookies. The application logic, database schema, and helper layer are in good shape.

The problems are concentrated in three areas:

1. **A critical auth-bypass script ships in the web root** (`test_upload.php`) — must be deleted.
2. **`listing.php` has two syntax errors** that break the entire listing detail page (parse error).
3. **The design/motion layer barely exists** — the CSS declares tokens but the site doesn't use a coherent design system, and motion is limited to one hover transition.

---

## HTTP Status Crawl

| Page | Status | Notes |
|---|---|---|
| `index.php` | 200 | Marketplace browse page renders |
| `login.php` | 200 | Auth page |
| `register.php` | 200 | Registration |
| `dashboard.php` | 302→login | Expected (unauthenticated) |
| `create_listing.php` | 302→login | Expected |
| `listing.php` | 200*** | **Parse error — page broken** |
| `listing_new.php` | 404 | Works (correctly 404s without valid id) |
| `update_listing.php` | 200 | **Empty file (0 bytes)** |
| `update_listing2.php` | 200 | **Empty file (0 bytes)** |
| `update_listing3.php` | 200 | **Empty file (0 bytes)** |
| `forgot.php` | 200 | Password reset request |
| `reset.php` | 302→login | Expected |
| `debug.php` | 200 | **Empty file (0 bytes)** |
| `debug2.php` | 200 | **Empty file (0 bytes)** |
| `test_upload.php` | 200 | **CRITICAL auth-bypass script** |
| `upload_image.php` | 302→login | Expected |
| `logout.php` | 302 | Expected |

Assets all load: `styles.css`, Bootstrap CDN, Google Fonts. No broken `<img>` references in base templates.

---

## Findings by Severity

### 🔴 CRITICAL

#### C1. `test_upload.php` — authentication bypass + session forgery
- **File:** `public/test_upload.php`
- **Root cause:** Development test script left in the web root with no `env` guard.
- **Details:**
  - Auto-creates an **active** farmer (`testfarmer@example.com` / `TestPassword123!`) via direct INSERT, bypassing verification and admin approval.
  - Forges a privileged session directly: `session_regenerate_id()` + sets `$_SESSION['user_id']` / `$_SESSION['role']` — no login required.
  - Leaks `session_id`, `user_id`, and internal responses in JSON output.
  - Any anonymous visitor hitting this URL gets an authenticated farmer session.
- **Fix:** **Delete the file.** Verify no references to it elsewhere.

#### C2. `listing.php` — PHP parse error breaks listing detail page
- **File:** `public/listing.php`
- **Line 85:** `<?= e($item['unit_symbol']) %>%</span>` → the closing tag is `%>` instead of `?>` — causes a **fatal parse error**, so the listing page never renders.
- **Line 100:** `<dt class="col-sm-4">Best before</dt` → missing closing `>`.
- **Fix:** Correct both syntax errors. (`listing_new.php` is a clean copy — either consolidate or delete one.)

### 🟠 HIGH

#### H1. No security headers
- **Root cause:** Nothing in `bootstrap.php` or `.htaccess` emits `X-Frame-Options`, `X-Content-Type-Options`, `Content-Security-Policy`, `Referrer-Policy`, or `Strict-Transport-Security`.
- **Fix:** Add security headers in `bootstrap.php` (or `.htaccess` for Apache; a small router needed for `php -S`).

#### H2. Session cookie `secure = false` even though `env` is configurable
- **File:** `src/bootstrap.php:45`
- **Root cause:** `secure => false` is hardcoded; no branch on `$config['app']['env']`.
- **Fix:** Flip cookie `secure` to `true` when `env === 'production'`.

#### H3. Session lifetime configured but never enforced
- **Files:** `config/config.php:19`, `src/bootstrap.php`
- **Root cause:** `session_lifetime_minutes => 30` is declared but no code sets `session.gc_maxlifetime` or cookie lifetime.
- **Fix:** Enforce the configured lifetime in `bootstrap.php`.

#### H4. Empty stub files in web root
- **Files:** `debug.php`, `debug2.php`, `update_listing.php`, `update_listing2.php`, `update_listing3.php` (all 0 bytes)
- **Root cause:** Leftover development scaffolding committed to the web root.
- **Fix:** Delete. `update_listing.php` is clearly a half-finished edit path — `create_listing.php` handles creation, so records must have an update path; verify and implement or remove.

#### H5. No rate limiting on forgot-password
- **File:** `public/forgot.php`
- **Root cause:** POST handler has no throttle; an attacker can enumerate emails or spam DB rows.
- **Fix:** Add attempt limiting.

### 🟡 MEDIUM

#### M1. Upload directory may execute PHP (if Apache serves it)
- **File:** `public/uploads/listings/`, `.htaccess`
- **Root cause:** `uploads/` has no execution-blocking config (only global `Options -Indexes`).
- **Fix:** Add `.htaccess` in uploads denying PHP execution, or block script execution at server config.

#### M2. Un-`e()`-escaped values in `create_listing.php`
- **Files:** `public/create_listing.php:252,265`
- **Details:** Category/unit option `value` attributes echo raw DB ids (integers) — low practical risk, but wrap in `e()` for consistency.

#### M3. `<value>` typo in `create_listing.php`
- **File:** `public/create_listing.php:250`
- **Details:** `<value="" >Select a category</option>` should be `<option value="">`.

#### M4. `LIMIT`/`OFFSET` interpolated in `index.php`
- **File:** `public/index.php:32`
- **Details:** Values are `(int)`-cast/clamped so not exploitable, but parameterizing would be cleaner.

### 🔵 LOW

- **L1.** `logout.php` deletes the cookie without `samesite` param — cosmetic.
- **L2.** Font mismatch: CSS declares `Plus Jakarta Sans` (`styles.css:83`) but HTML loads Atkinson Hyperlegible + Source Sans 3 (`header.php:14`). The declared font is never loaded.
- **L3.** No GD extension (`test_upload.php` requires it — moot once deleted; the real upload pipeline only needs `finfo`, which is present).

---

## Accessibility Failures (WCAG 2.1 AA)

| Element | Contrast | Required | Status |
|---|---|---|---|
| `--animal-outline-variant` (#c0c9c3) on white | 1.70:1 | 3:1 | **FAIL** |
| State chip `pending` (#D97706 on #FEF3C7) | 2.86:1 | 3:1 | **FAIL** |
| Tertiary accent (#C06C3E) on white | 3.85:1 | 4.5:1 (normal text) | **FAIL** |
| Body text | 16.24:1 | 4.5:1 | PASS |
| Primary button/text | 9.64:1 | 4.5:1 | PASS |
| Focus ring (#FFD166) | visible | — | PASS |

Other gaps: `prefers-reduced-motion` blanket-kills all animation (too aggressive); touch targets 48px enforced; semantic landmarks present (header/nav/main/footer); only one `h1` per page pattern holds.

---

## Design System & Motion Audit

### State
- **Tokens:** Colors/spacing/radius declared in `styles.css:1-106` but **partially inconsistent** with DESIGN.md source of truth (primary/secondary/tertiary naming swapped; base hues drift).
- **Usage:** Spacing is a mix of tokens and hardcoded values. Zero CSS variables used in templates — everything is Bootstrap utility classes.
- **Typography:** No type scale utilities implemented; relies on Bootstrap `display-*`/`fs-*`.
- **Missing components:** button variants, form extensions (labels/help/validation), table styling, empty/loading/error states, price tag component, elevation utilities.

### Motion (Emil Kowalski standard)
| Area | Today | Need |
|---|---|---|
| Product cards | `transform` + `box-shadow` 0.15s hover | OK base, add active state |
| Flash/toasts | Bootstrap static alert | Slide+fade in, auto-dismiss, animate out |
| Modals | None exist | Scale/fade, backdrop, focus trap, exit anim |
| Dropdowns | Native `<select>` only | Custom enter animation on options |
| Locale toggle | **Does not exist** | Required for Phase 2 landing page |
| Scroll-reveal | None | Hero/sections fade-up entrance |
| Reduced motion | Kills all to 0.01ms | Keep ≤100ms critical fades |
| Motion tokens | None | Define duration/easing system |

---

## Database / Seed

- **Idempotent:** users, profiles, categories, units (checked by unique keys).
- **NOT idempotent:** listings and orders duplicate on re-seed. Acceptable for dev workflow; noted.
- Migration system exists (`database/migrations/`).

---

## Root Causes (grouped)

1. **Dev scaffolding shipped:** `test_upload.php` + empty stubs are unguarded dev artifacts → delete + `env` guard.
2. **Environment branch missing:** `env` in config is never read by the code (error level, cookie secure, session lifetime) → centralize env handling in `bootstrap.php`.
3. **Half-migrated redesign:** `listing.php` vs `listing_new.php` (one broken), `update_listing*.php` empty → consolidate to one canonical path each.
4. **Design system declared but not applied:** tokens exist, templates use Bootstrap classes → Phase 4 work.
5. **No motion language:** one hover transition → Phase 3 work.

---

## What We Will Fix (priority order)

1. Delete `test_upload.php`, `debug.php`, `debug2.php`, `update_listing.php`, `update_listing2.php`, `update_listing3.php`.
2. Fix `listing.php` parse errors; consolidate with `listing_new.php`.
3. Env-aware `bootstrap.php`: `secure` cookie, session lifetime, error reporting.
4. Security headers in `bootstrap.php`.
5. Rate-limit forgot-password.
6. Block script execution in `uploads/`.
7. Fix contrast failures (outline-variant, pending chip, tertiary accent).
8. Build landing page (Phase 2) with i18n toggle, real listings, market preview.
9. Establish motion language (Phase 3): tokens, toasts, modals, scroll-reveal, reduced-motion ≤100ms fades.
10. Extend design system (Phase 4): components, type scale, states.
11. Feature work (Phase 6): freshness/urgency, trust loop, fair-pricing hints, low-bandwidth mode.

---

*Consolidated from 6 parallel audit agents + direct source verification.*