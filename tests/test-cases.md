# Test Case Stubs (Sprint 1, issue #7)

Format: ID, linked requirement, steps, expected result.
Status of all cases below: **Draft** (to be executed in the full regression, issue #18).

Requirement IDs referenced from `docs/SRS.md` and `docs/Requirements_Traceability_Matrix.md`.

## Auth (issue #5)

| ID | Requirement | Steps | Expected |
|----|-------------|-------|----------|
| AUTH-001 | FR-AUTH-001, FR-AUTH-002 | Open `/register.php`, select Buyer, fill valid details, submit | Account created, redirected to `/login.php`, success flash shown |
| AUTH-002 | FR-AUTH-001, FR-AUTH-003 | Open `/register.php`, select Farmer, fill all farm fields, submit | Farmer account created with `status=pending_verification`, redirected to `/login.php` |
| AUTH-003 | FR-AUTH-002, FR-AUTH-003 | Register with an email that already exists | Form errors, email uniqueness enforced server-side |
| AUTH-004 | FR-AUTH-003 | Submit register form with missing required fields | Server-side validation errors, no row inserted |
| AUTH-005 | FR-AUTH-004 | Register, inspect `users.password_hash` in DB | Hash matches `password_hash()` output (bcrypt/argon), never plaintext |
| AUTH-006 | FR-AUTH-005 | Log in with valid creds | Session established, redirected to role-specific `/dashboard.php`, success flash |
| AUTH-007 | FR-AUTH-005 | Log in with wrong password | Error shown, no session created |
| AUTH-008 | FR-AUTH-006 | Log in, then compare PHP session ID before/after | Session ID regenerated after login |
| AUTH-009 | FR-AUTH-007 | Click logout (`/logout.php`) | Session destroyed, redirected to `/index.php`, protected pages require login again |
| AUTH-010 | FR-AUTH-019, NFR-SEC-008 | Submit wrong password 5 times for same email+IP in 15 min | 6th attempt blocked with 429, no success for the pair |

## Role gates (issue #5)

| ID | Requirement | Steps | Expected |
|----|-------------|-------|----------|
| ROLE-001 | FR-AUTH-008 | Log in as buyer, open `/dashboard.php` | Buyer view rendered (orders), no admin/farmer sections |
| ROLE-002 | FR-AUTH-008 | Log in as farmer, open `/dashboard.php` | Farmer view rendered (listings, orders), create-listing link visible |
| ROLE-003 | FR-AUTH-008 | Log in as admin, open `/dashboard.php` | Admin view rendered (platform stats, verifications, audit log) |
| ROLE-004 | FR-AUTH-008 | Unauthenticated user opens `/dashboard.php` | Redirected to `/login.php` |
| ROLE-005 | FR-AUTH-009 | Attempt to register as admin via crafted POST `role=admin` | Rejected; registration only allows `farmer` or `buyer` |

## CSRF (issue #6)

| ID | Requirement | Steps | Expected |
|----|-------------|-------|----------|
| CSRF-001 | NFR-SEC-002, SRC-SEC-003 | Load a form, POST without `csrf_token` | 403 "CSRF token mismatch" |
| CSRF-002 | NFR-SEC-002, SRC-SEC-003 | POST all authenticated endpoints (`/admin_verify.php`, `/order_action.php`, `/create_listing.php`, `/settings.php`, `/register.php`, `/login.php`, `/forgot.php`, `/reset.php`, `/order_request.php`, `/upload_image.php`, `/api/preferences.php`) with a stale token | Each rejected with 403 |
| CSRF-003 | NFR-SEC-002 | Audit scan: every `<form method="post">` contains `csrf_field()` | No POST form without CSRF field |

## Seed + reset (issue #4)

| ID | Requirement | Steps | Expected |
|----|-------------|-------|----------|
| SEED-001 | FR-AUTH-014 | Run `tools/db-reset.php --yes`, count `users` by role | 1 admin, 3 farmers, 5 buyers exist with expected demo emails |
| SEED-002 | FR-AUTH-014 | Log in each demo account listed in `DEMO_ACCOUNTS.md` | All log in successfully |
| SEED-003 | FR-AUTH-014 | Run `tools/db-reset.php --yes`, measure elapsed time | Completes in under 2 minutes |
| SEED-004 | NFR-REL-001 | After reset, run the app smoke URLs from `README_SETUP.md` | Pages render, no missing-table errors (all 21 tables created incl. `migrations`) |
| SEED-005 | NFR-PERF-002 | Load `/browse.php` against seeded data | Listings display with realistic PH data, query returns within budget |

## Ledger / traceability

| ID | Requirement | Steps | Expected |
|----|-------------|-------|----------|
| TRACE-001 | NFR-TEST-001 | Map each stub above to `Requirements_Traceability_Matrix.md` | No stub references an unknown requirement ID |