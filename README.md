# AnimalMarketplace

Philippine agricultural marketplace where verified farms sell directly to buyers.
Team project — built from scratch by a 5-person BSIT team.

## Team roles

| Role | Owner | Owns |
|------|-------|------|
| Project Manager | — | `docs/`, plan, decisions |
| Database Admin | — | `database/` (schema, migrations, seed) |
| Frontend | — | `public/` + `designs/` (web root, mockups, prototype) |
| Backend | — | `src/` (bootstrap, auth, features) |
| Tester | — | `tests/` (test cases, fixtures, results) |

## Folder map

```
AnimalMarketplace/
├── config/            DB + app config (config.example.php is the template)
├── database/          DBA territory
│   ├── schema.sql     tables + relationships
│   ├── migrations/    one SQL file per change, numbered (001_, 002_, ...)
│   └── seed/          test data scripts
├── src/               backend territory
│   ├── Core/          bootstrap, DB, router, session, CSRF, auth
│   └── Features/      one folder per feature (Auth, Listings, Dashboard)
├── public/            frontend territory — THE WEB ROOT
│   ├── index.php      entry point
│   ├── assets/        css/ js/ img/ fonts/
│   ├── partials/      shared header/footer/nav
│   ├── views/         rendered pages
│   └── uploads/       user-uploaded images (gitignored)
├── designs/           high-fi mockup + clickable prototype
├── docs/              SRS, requirements matrix, test plan, ADRs
├── tests/             tester territory: unit/, feature/, fixtures/
├── tools/             helper scripts (setup, db reset, deploy)
└── logs/              runtime logs (gitignored)
```

## Stack

PHP 8.x + MySQL (XAMPP). Web root is `public/`; every page boots through `src/Core/bootstrap.php`.

Dev server: `C:\xampp\php\php.exe -S localhost:8000 -t public`

## Conventions

- One feature/fix per commit, imperative subject: `feat: ...`, `fix: ...`, `test: ...`
- Tester commits test cases under `tests/` only.
- Never commit `config/config.php`, uploads, or logs.

## Getting started

1. Create `config/config.php` from `config/config.example.php`.
2. Import `database/schema.sql` + run seed scripts.
3. Start MySQL (XAMPP) and the PHP dev server (above).
4. Open http://localhost:8000