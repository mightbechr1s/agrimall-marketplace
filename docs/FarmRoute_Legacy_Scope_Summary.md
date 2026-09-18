# FarmRoute → AniMAL: Scope-Cut Summary

**Status:** Informational
**Purpose:** Help the team stop tripping over the older FarmRoute blueprint by recording what changed and why.
**Authoritative source:** `SRS.md` (this file is not a requirement source).

## 1. Background

FarmRoute and AniMAL are the **same capstone project under two names**.

- **FarmRoute Technical Blueprint** (`thesis-proposal/FarmRoute_Technical_Blueprint.pdf`) is the original, pre-scope draft: an ambitious farm-to-market *logistics* platform with forecasting, route optimization, transport bidding, SMS alerts, and maps.
- **AniMAL SRS** (`docs/SRS.md`) is the approved scope baseline written after the team received its official capstone scopes. It is the ground truth for all design and implementation work.

Per SRS Section 1: *the FarmRoute blueprint is a legacy artifact and may be used only for ideas that do not conflict with this SRS.*

## 2. What Changed in One Line

AniMAL dropped every algorithmic, infrastructure, and third-party service feature from FarmRoute and became a **plain-PHP market access and order coordination system** that a four-member BSIT team can build and defend in 4-5 months.

## 3. What Was Kept (Non-Conflicting Carryover)

These FarmRoute ideas survive inside AniMAL without conflict:

| FarmRoute idea | Where it lives in AniMAL |
|---|---|
| Barangay / municipality / province location model | SRS FR-PROF-002, FR-LIST-002 |
| Managed quantity units (kg, sack, crate, ...) | SRS FR-LIST-004, FR-ADM-011, FR-ADM-020 |
| Soft deletes / historical preservation | SRS FR-LIST-009, BR-013, BR-014 |
| Activity / audit logging | SRS FR-ADM-007, FR-ADM-015 to FR-ADM-017 |
| Image uploads re-encoded to safe application copies | SRS FR-LIST-019, NFR-SEC-009, NFR-SEC-010 |
| Philippine locale conventions (PHP prices, Philippine local time) | SRS BR-016, BR-017 |

## 4. What Was Dropped and Why

These FarmRoute features were cut because they fall outside the approved scope (SRS Section 4.2). Do not add them back.

| FarmRoute feature | AniMAL decision | Reason |
|---|---|---|
| Laravel 11 + Tailwind + Alpine + Redis + S3 | Plain PHP + PDO + Bootstrap 5 + XAMPP (SRS Section 5) | Framework and services must stay realistic for the team and timeline; SRS explicitly excludes them |
| Roles: farmer, cooperative, transport, admin | Roles: farmer, buyer, administrator (SRS Section 6) | Cooperative and transport operator roles were out of scope |
| Demand forecasting (Holt-Winters) | Not in scope | No AI or machine-learning features (SRS 4.2) |
| Route optimization (greedy + 2-opt) | Not in scope | No GPS, maps, or route optimization (SRS 4.2) |
| Price trend analysis (linear regression, anomaly detection) | Not in scope | No AI or machine-learning features (SRS 4.2) |
| Transport bidding and routes | Not in scope | Replaced by farmer-arranged local delivery coordination (SRS FR-ORD) |
| SMS alerts (Semaphore) | Not in scope | No SMS integration (SRS 4.2) |
| Leaflet.js maps / geo coordinates | Not in scope | No GPS or maps; addresses shown only to authorized parties (SRS 4.2) |
| Market price feed / monitoring | Not in scope | Reports are recorded values, not price feeds (SRS FR-REP) |
| Online payment or wallet | Not in scope | Payment is coordinated offline (SRS 4.2, BR-015) |
| 18-week timeline, 5 roles, 14 tables | 4-5 months, 4 members | Matches approved scope and team size |

## 5. Rules of Thumb for the Team

1. **The SRS is the contract.** If FarmRoute says one thing and the SRS says another, the SRS wins.
2. **Never resurrect a dropped feature** (forecasting, routes, SMS, maps, payments, extra roles) without an approved scope change from the adviser.
3. **FarmRoute is only a reference** for terminology and non-conflicting design ideas already listed in Section 3 above.
4. **Do not cite FarmRoute numbers** (KPIs like "25-30% waste reduction", "15-20% price improvement") in reports or defenses as AniMAL targets; AniMAL has its own acceptance criteria in SRS Section 14.
