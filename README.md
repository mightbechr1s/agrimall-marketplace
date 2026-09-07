# AnimalMarketplace

Philippine agricultural marketplace where verified farms sell directly to buyers.
Team project — built from scratch by a 5-person BSIT team.

## Team roles (who owns what)

| Role | Owner | Owns | Do NOT touch without asking |
|------|-------|------|------------------------------|
| Project Manager | — | `docs/`, milestone plan, decisions log | others' folders |
| Database Admin | — | `database/` (`schema.sql`, `migrations/`, `seed/`) | `src/`, `public/` |
| Frontend | — | `public/` + `designs/` (web root, mockups, prototype) | `database/`, `src/Core/` |
| Backend | — | `src/` (bootstrap, auth, features) | `designs/` |
| Tester | — | `tests/` (test cases, fixtures, results) | only reviews, never commits code |

> Fill in names after first sync. Two people editing the same file at once is the #1 cause of conflicts — check out, don't collide.

## Folder map

```
AnimalMarketplace/
├── config/            DB + app config (config.example.php is the template; config.php is local-only)
├── database/          DBA territory
│   ├── schema.sql     tables + relationships
│   ├── migrations/    one SQL file per change, numbered (001_, 002_, ...)
│   └── seed/          test data scripts
├── src/               backend territory
│   ├── Core/          bootstrap, DB connection, router, session, CSRF, auth
│   └── Features/      one folder per feature (Auth, Listings, Dashboard)
├── public/            frontend territory — THE WEB ROOT
│   ├── index.php      entry point
│   ├── assets/        css/ js/ img/ fonts/
│   ├── partials/      shared header/footer/nav
│   ├── views/         rendered pages
│   └── uploads/       user-uploaded images (gitignored)
├── designs/           high-fi mockup + clickable prototype (Figma exports, references)
├── docs/              SRS, requirements matrix, test plan, ADRs
├── tests/             tester territory: unit/, feature/, fixtures/
├── tools/             helper scripts (setup, db reset, deploy)
└── logs/              runtime logs (gitignored)
```

## Stack (proposed, confirm at kickoff)

- PHP 8.x + MySQL (XAMPP) — matches curriculum, no install cost.
- Web root is `public/`; every page boots through `src/Core/bootstrap.php`.
- Run dev server: `C:\xampp\php\php.exe -S localhost:8000 -t public`

## Commit conventions

- One feature/fix per commit; short subject line, imperative mood.
  - `feat: add seller listing form`
  - `fix: login redirect loop on forgotten session`
  - `test: regression cases for password reset`
- Tester does not commit source code; they commit test cases under `tests/`.
- Never commit `config/config.php`, uploads, or logs (see `.gitignore`).

## Milestones

1. Design: high-fi mockup → prototype (tester reviews interactively)
2. Backend slice 1: schema + auth
3. Backend slice 2: listings (create/browse) + dashboard skeleton
4. Tester gate: run full matrix, fix, freeze for demo

## Getting started

1. Create `config/config.php` from `config/config.example.php`.
2. Import `database/schema.sql` + run seed scripts.
3. Start MySQL (XAMPP) and the PHP dev server (command above).
4. Open http://localhost:8000