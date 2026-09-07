# BASE SPLIT — what ships vs what the team builds

Read this before claiming anything is "done". The skeleton is a **plate, not a meal**.
Everything marked *built* was done deliberately to unblock you. Everything else is a
real task for your role. Do not copy-paste from the AniMAL backup repo — use it as a
**reference** only.

## Shipped (real, working)

| File | What it does |
|---|---|
| `config/config.example.php` + `config/config.php` (local) | DB + app config. Copy example over config.php on a new machine. |
| `src/Core/bootstrap.php` | Boots every page: session, helpers, config, timezone, error logging |
| `src/Core/DB.php` | Lazy PDO singleton (prepared statements, emulation off) |
| `src/Core/helpers.php` | `e()`, `url()`, `redirect()`, `csrf_token()`, `csrf_field()`, `csrf_check()` |
| `public/partials/header.php` + `footer.php` | Shared chrome all pages include |
| `public/index.php`, `login.php`, `register.php`, `listing.php` | Stub pages that run right now |
| `public/assets/css/styles.css` | Placeholder design tokens only |
| `database/schema.sql` | **Roles + Users only** — auth boots; everything else is DBA's job |

## Still to build (your week 1–3 work)

### Frontend (designs/ + public/)
- [ ] Design direction + wireframes (8 pages) — **Sprint 1**
- [ ] High-fi mockup: home + listing grid — **Sprint 1**
- [ ] Clickable prototype: browse → detail → basket → register → login — **Sprint 2**
- [ ] Convert prototype into real pages; own `styles.css` tokens — **Sprint 2**
- [ ] Responsive + a11y + empty/loading/error states — **Sprint 3**

### Backend (src/Features/)
- [ ] Auth feature: register/login/logout with buyer/farmer/admin roles — **Sprint 1**
- [ ] CSRF check wired + role guards — **Sprint 1**
- [ ] Listings feature: create (with image upload), browse grid, detail — **Sprint 2**
- [ ] Dashboard per role — **Sprint 2**
- [ ] Integration cleanup + zero-warning walkthrough — **Sprint 3**

### DBA (database/)
- [ ] Design + migrate: farms, listings (+ categories), orders, support messages — **Sprint 1**
- [ ] `seed/seed.php`: 3 farmers, 5 buyers, 1 admin, 30+ realistic listings — **Sprint 1–2**
- [ ] Index browse queries, prove < 100ms with EXPLAIN — **Sprint 2**
- [ ] Backup/restore drill, final indexes — **Sprint 3**

### Tester (tests/)
- [ ] Test-case template from requirements (ID, steps, expected, requirement link) — **Sprint 1**
- [ ] Environment verified on 2 machines; log setup bugs — **Sprint 1**
- [ ] Auth suite green; listings suite drafted — **Sprint 2**
- [ ] Full regression + XSS/SQLi spot-checks; sign-off → freeze — **Sprint 3**

## Rules of the skeleton

1. **Skeleton ≠ solution.** If a feature is fully implemented in the backup but not here, you are expected to build it to *your* quality bar — not import the answer.
2. Migration rule: after Sprint 1, schema changes go in `database/migrations/001_*.sql`, never rewrites of `schema.sql`.
3. Every POST form sends `csrf_field()` and the handler calls `csrf_check()` before anything else.
4. Tester commits only under `tests/`. Everyone branches; merge via Pull Request.