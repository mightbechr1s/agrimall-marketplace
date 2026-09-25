# AniMAL Requirements Traceability Matrix

**Document version:** 0.2  
**Status:** Provisional comparison pending recovery of the instructor-received file  
**Current local comparison source:** `thesis-proposal/AniMAL_Capstone_Super_Prompt_Updated.md`  
**Requirements baseline:** `AniMAL/docs/SRS.md` version 0.3  
**Prepared for:** BSIT capstone scope alignment

## 1. Purpose

This Requirements Traceability Matrix (RTM) compares the current SRS with the current local project instruction. It cannot claim historical coverage of the instructor-received instructions until that exact file or printed copy is recovered and compared. It also separates source requirements from later implementation details and policies that still require adviser confirmation.

This document does not add, remove, or approve functionality. It is a scope-control artifact and must be updated whenever the approved source or SRS changes.

## 2. Baseline Provenance Warning

The current local source file uses the title **AniMAL: A Web-Based Market Access Platform for Farmers**. Earlier project artifacts and the original working folder used **FarmRoute**. If the instructor received a FarmRoute copy before the local file was renamed or edited, that copy remains the authoritative historical baseline. Its title and complete contents may differ from the current local file; this RTM therefore establishes only provisional coverage against the local comparison source.

Before formal SRS approval, the team shall:

1. Obtain the exact file or printed copy received by the instructor.
2. Compare every section, module, exclusion, deliverable, guideline, and constraint with the current local source file.
3. Confirm the approved title and content differences in writing or meeting minutes.
4. Replace the provisional comparison source with a preserved copy of the instructor-received baseline.
5. Record any instructor-approved changes in this RTM and the SRS change history.

## 3. Coverage Legend

| Coverage | Meaning |
|---|---|
| Exact | The SRS directly retains the source requirement. |
| Clarified | The SRS makes a broad source requirement specific and testable without necessarily adding a new module. |
| Planned | The item is a required future artifact or implementation outcome and cannot be completed in the SRS alone. |
| Gap | The current project documents do not yet explicitly commit to the source item. |
| Approval required | The SRS establishes a policy, workflow, or boundary not explicit in the current local comparison source. |

## 4. Product and Constraint Traceability

| Source ID | Current local prompt requirement | SRS or artifact trace | Coverage | Notes |
|---|---|---|---|---|
| SRC-OBJ-001 | Project title | SRS title and Sections 1-2 | Exact | Current local title matches; historical uncertainty is tracked separately in APR-001. |
| SRC-OBJ-002 | Web-based market access and information-management platform | SRS Sections 2 and 5 | Exact | Web delivery is retained; server-rendered multi-page architecture is an SRS-added implementation choice. |
| SRC-OBJ-003 | Connect local farmers and buyers through listings, requests, and order coordination | SRS Sections 2, 8.4-8.7 | Clarified | Detailed workflows are defined. |
| SRC-OBJ-004 | Improve communication and transaction management | SRS Sections 3 and 8.6-8.7 | Clarified | Communication is structured through records and statuses, not chat. |
| SRC-OBJ-005 | Farmers, buyers, and administrator | SRS Section 6; FR-AUTH-008; BR-001 | Exact | No fourth operational role is introduced. |
| SRC-OBJ-006 | Improve produce management, order processing, and administrative oversight | SRS Section 3.1 and Sections 8.4-8.9 | Exact | Main objective is retained. |
| SRC-OBJ-007 | Do not become another generic marketplace clone; solve practical operational problems | SRS Sections 2-3 | Exact | The SRS retains a focused coordination and records-management boundary. |
| SRC-CON-001 | PHP | SRS Section 5 | Clarified | Plain PHP is selected. |
| SRC-CON-002 | HTML, CSS, and JavaScript | SRS Section 5 | Exact | Retained for the production application. |
| SRC-CON-003 | Bootstrap | SRS Section 5 | Clarified | Bootstrap 5 is selected. |
| SRC-CON-004 | MySQL under XAMPP | SRS Section 5 | Exact | Retained. |
| SRC-CON-005 | Four-to-five-month timeline | SRS metadata and Section 5 | Exact | Used as the feasibility boundary. |
| SRC-CON-006 | Four team members | SRS metadata and Section 5 | Exact | Retained. |
| SRC-CON-007 | Project manager, frontend/UI-UX developer, backend developer, and database administrator | No current project-plan artifact | Gap | Belongs in the project management plan rather than product requirements. |
| SRC-CON-008 | Realistic for undergraduate BSIT students | SRS Sections 1-2, 5, and 14 | Exact | Scope and acceptance boundary reflect this constraint. |

## 5. In-Scope Module Traceability

| Source ID | Current local prompt module | SRS trace | Coverage | Notes |
|---|---|---|---|---|
| SRC-SCOPE-001 | Registration and login | SRS Section 8.1; FR-AUTH-001-021 | Clarified | Includes authentication, sessions, role access, and account security. |
| SRC-SCOPE-002 | Farmer and buyer profiles | SRS Section 8.2; FR-PROF-001-004 | Clarified | Required fields and privacy rules are defined. |
| SRC-SCOPE-003 | Produce listings | SRS Section 8.4; FR-LIST-001-032 | Clarified | Includes quantity, units, statuses, images, and stock integrity. |
| SRC-SCOPE-004 | Categories | SRS Section 8.3; FR-CAT-001-003 | Clarified | Administrator ownership and deactivation behavior are defined. |
| SRC-SCOPE-005 | Search and filtering | SRS Section 8.5; FR-SRCH-001-006 | Clarified | Search, filters, sorting, pagination, and public-data limits are defined. |
| SRC-SCOPE-006 | Order management | SRS Sections 8.6-8.7; FR-ORD-001-029; FR-STAT-001-015 | Clarified | Includes request, confirmation, fulfillment, cancellation, and history rules. |
| SRC-SCOPE-007 | Order-status tracking | SRS Section 8.7; FR-STAT-001-015 | Clarified | Explicit statuses and transition actors are defined. |
| SRC-SCOPE-008 | Administrator dashboard | SRS Section 8.8; FR-ADM-001 | Clarified | Dashboard counts are specified. |
| SRC-SCOPE-009 | User management | SRS Sections 8.1 and 8.8 | Clarified | Activation, suspension, account administration, and audit rules are defined. |
| SRC-SCOPE-010 | Reports | SRS Section 8.9; FR-REP-001-009 | Clarified | Report types and filters are specified; the final set requires approval. |
| SRC-SCOPE-011 | Profile management | SRS Section 8.2; FR-PROF-001-017 | Clarified | Self-service profile updates and verification status are included. |

## 6. Out-of-Scope Traceability

| Source ID | Current local prompt exclusion | SRS trace | Coverage | Notes |
|---|---|---|---|---|
| SRC-OUT-001 | Native mobile application | SRS Section 4.2 | Exact | Responsive web remains required. |
| SRC-OUT-002 | Online payments | SRS Section 4.2; FR-ORD-017 and 021 | Clarified | Coordination totals may be recorded, but no payment is processed. |
| SRC-OUT-003 | GPS tracking | SRS Section 4.2 | Exact | Maps and route optimization are additional exclusions covered by APR-017. |
| SRC-OUT-004 | AI features | SRS Section 4.2 | Clarified | Machine-learning features are also excluded. |
| SRC-OUT-005 | Government API integration | SRS Section 4.2 | Exact | Retained. |
| SRC-OUT-006 | Chat and video calls | SRS Section 4.2 | Exact | The SRS now excludes chat generally and video calls; voice calls are an added exclusion covered by APR-017. |
| SRC-OUT-007 | Push notifications | SRS Section 4.2 | Exact | Retained. |
| SRC-OUT-008 | Inventory forecasting | SRS Section 4.2 | Exact | Current-stock management remains in scope; broader demand forecasting is covered by APR-017. |
| SRC-OUT-009 | Blockchain | SRS Section 4.2 | Exact | Retained. |
| SRC-OUT-010 | IoT | SRS Section 4.2 | Exact | Retained. |
| SRC-OUT-011 | Third-party courier integration | SRS Section 4.2 | Approval required | This boundary is not stated in the current local comparison source. |
| SRC-OUT-012 | Public ratings and reviews in the first release | SRS Section 4.2 | Approval required | This boundary is not stated in the current local comparison source. |
| SRC-OUT-013 | SMS integration | SRS Sections 5 and 15 | Approval required | Push notifications are excluded by the local prompt, but SMS integration is a broader added boundary. |

## 7. Quality, Database, Coding, and Security Traceability

| Source ID | Current local prompt requirement | SRS trace | Coverage | Notes |
|---|---|---|---|---|
| SRC-DES-001 | Modern, professional UI | AniMAL design system and interactive prototype | Planned | The prototype demonstrates direction; production implementation and review remain required. |
| SRC-DES-002 | Responsive UI | NFR-UX-001; NFR-TEST-002 | Clarified | Includes 320-pixel support and tested viewports. |
| SRC-DES-003 | Accessible UI | NFR-UX-002-005 | Clarified | Covers keyboard access, labels, contrast, and non-color status. |
| SRC-DES-004 | Clean layout | AniMAL design system; NFR-UX-006-007 | Planned | The design system documents layout rules; production verification remains required. |
| SRC-DES-005 | Consistent spacing and typography | AniMAL design tokens and style notes | Planned | Defined in the design system but not yet implemented in production PHP. |
| SRC-DES-006 | Use Impeccable if available | Design-process evidence in OpenDesign artifacts | Planned | This is a process preference, not a product feature. |
| SRC-DB-001 | Proper keys, relationships, and constraints | NFR-REL-001; SRS Section 11 | Planned | To be completed in the ERD and relational schema. |
| SRC-DB-002 | Appropriate indexes | NFR-PERF-003; SRS Section 11 | Planned | Must be justified against query needs. |
| SRC-DB-003 | Normalization through 3NF | SRS Section 11 | Gap | The SRS mentions normalization but does not retain the explicit 3NF target. |
| SRC-DB-004 | Explain every database-design decision | No explicit rationale artifact | Gap | Add a database decision log or rationale section to the schema documentation. |
| SRC-CODE-001 | Modular code | NFR-MNT-001-002 | Clarified | Layered separation and centralized controls are required. |
| SRC-CODE-002 | Secure, maintainable code | SRS Sections 10.1 and 10.6 | Clarified | Security and maintainability are testable. |
| SRC-CODE-003 | Prepared statements using PDO or MySQLi | NFR-SEC-001 | Clarified | The project selects PDO, which is permitted. |
| SRC-CODE-004 | Meaningful names | NFR-MNT-003 | Exact | Retained. |
| SRC-SEC-001 | SQL-injection protection | NFR-SEC-001; NFR-TEST-004 | Clarified | PDO prepared statements and tests are required. |
| SRC-SEC-002 | XSS protection | NFR-SEC-003, 014, and 017; NFR-TEST-004 | Clarified | Escaping, CSP, and tests are defined. |
| SRC-SEC-003 | CSRF protection | NFR-SEC-002 and 015; NFR-TEST-004 | Clarified | State-changing requests are covered. |
| SRC-SEC-004 | Role-based access control | FR-AUTH-008; NFR-SEC-007; NFR-TEST-004 | Clarified | Server-side horizontal and vertical authorization is required. |
| SRC-SEC-005 | Password hashing | FR-AUTH-004; NFR-SEC-004 | Clarified | PHP password APIs are required. |
| SRC-SEC-006 | Session security | FR-AUTH-006-007 and 015-021; NFR-SEC-005-006 and 016 | Clarified | Regeneration, invalidation, expiry, cookies, and strict mode are defined. |
| SRC-SEC-007 | Input validation | SRS Section 8.10; FR-VAL-001-006 | Clarified | Field, numeric, calculation, and upload validation are defined. |

### 7.1 Process Instructions

These source items guide how the capstone is prepared. They are not product features and should be tracked in the project plan and defense preparation records.

| Process ID | Current local prompt instruction | Current evidence | Coverage | Required action |
|---|---|---|---|---|
| SRC-PROC-001 | Use multidisciplinary software, analysis, database, UI/UX, QA, security, and writing expertise | SRS and RTM independent reviews; design-system and prototype artifacts | Planned | Assign review ownership in the project plan. |
| SRC-PROC-002 | Delegate to appropriate specialists or skills when available | Review and design workflows used specialist tools and independent verification | Planned | Keep a concise decision and review record. |
| SRC-PROC-003 | Identify weaknesses, trade-offs, improvements, and justified recommendations instead of simply agreeing | SRS review findings; RTM approval register | Exact | Continue documenting accepted and rejected recommendations. |
| SRC-PROC-004 | Prepare for a real Philippine BSIT capstone defense | Philippine currency, local time, RA 10173, XAMPP, scope constraints, and planned defense artifacts | Planned | Complete testing, manuals, slides, and Q&A before defense. |
| SRC-PROC-005 | Optional tools include Impeccable, Context7, Serena, Sequential Thinking, and GitHub MCP | Impeccable/OpenDesign evidence exists; other tools are not required product dependencies | Planned | Use optional tools only when available and useful; no product requirement is created. |

## 8. Deliverable Traceability

| Source ID | Current local prompt deliverable | Current evidence or planned artifact | Coverage | Required action |
|---|---|---|---|---|
| SRC-DEL-001 | SRS | `AniMAL/docs/SRS.md` | Exact | Obtain team and adviser approval. |
| SRC-DEL-002 | Functional and non-functional requirements | SRS Sections 8 and 10 | Exact | Maintain requirement IDs through testing. |
| SRC-DEL-003 | User stories | SRS Section 12 | Exact | Map stories to the instructor-approved UML artifacts and tests. |
| SRC-DEL-004 | Business rules | SRS Section 9 | Exact | Approve policies listed in Section 10 of this RTM. |
| SRC-DEL-005 | UML diagrams | None | Gap | Confirm the expected UML diagram set with the instructor, then produce it after scope approval. |
| SRC-DEL-006 | ER diagram | Deferred by SRS Section 11 | Planned | Create only after SRS approval. |
| SRC-DEL-007 | Relational schema | Deferred by SRS Section 11 | Planned | Derive from the approved ERD. |
| SRC-DEL-008 | Data dictionary | Deferred by SRS Section 11 | Planned | Include data types, constraints, defaults, and definitions. |
| SRC-DEL-009 | Normalization through 3NF | SRS does not retain the explicit target | Gap | Add the source-required 3NF target before database design and demonstrate the required normalization. |
| SRC-DEL-010 | SQL scripts | None | Gap | Confirm the expected SQL-script package and produce the instructor-required scripts. |
| SRC-DEL-011 | Wireframes | High-fidelity interactive prototype exists, but no wireframe artifact exists | Gap | Produce wireframes unless the instructor explicitly accepts the prototype as substitute evidence. |
| SRC-DEL-012 | Responsive UI | AniMAL OpenDesign prototype; NFR-UX-001 | Planned | Implement in PHP and Bootstrap after design approval. |
| SRC-DEL-013 | Complete PHP and MySQL source code | Not started | Planned | Production implementation follows database approval. |
| SRC-DEL-014 | Testing documents | NFR-TEST-001-005 defines scope | Planned | Create test plan, cases, evidence, defect log, and results. |
| SRC-DEL-015 | Technical documentation | NFR-MNT-005 and NFR-PERF-005 | Planned | Consolidate architecture, setup, deployment, backup, and test documentation. |
| SRC-DEL-016 | User manual | None | Gap | Plan role-specific farmer, buyer, and administrator instructions. |
| SRC-DEL-017 | Installation guide | NFR-MNT-005; FR-AUTH-014 | Planned | Include XAMPP, database, configuration, uploads, and administrator setup. |
| SRC-DEL-018 | Presentation slides | None | Gap | Prepare the presentation slides required by the source. |
| SRC-DEL-019 | Defense Q&A | None | Gap | Prepare defense questions and evidence-based answers as required by the source. |

## 9. SRS-Added Implementation Details

The following additions are generally necessary to implement and test the current local comparison scope. They do not require new user-facing modules, but exact thresholds may still be reviewed.

| Detail ID | SRS-added detail | Trace | Classification |
|---|---|---|---|
| DET-001 | Plain PHP, PDO, layered server-rendered architecture, and PHP sessions | SRS Section 5; NFR-MNT-001-002 | Necessary implementation detail |
| DET-002 | Validation lengths, numeric limits, normalization, and fixed-point arithmetic | SRS Section 8.10 | Necessary implementation detail |
| DET-003 | Atomic order confirmation, stale-update rejection, and immutable order snapshots | FR-LIST-027; FR-ORD-010-011 and 018; NFR-REL-002 | Necessary integrity detail |
| DET-004 | Pagination and query indexing | FR-SRCH-005; NFR-PERF-002-003 | Necessary performance detail |
| DET-005 | Browser, responsive, security, and load-test matrices | NFR-TEST-001-005 | Necessary acceptance detail |
| DET-006 | Private-address masking and Data Privacy Act access principles | NFR-PRIV-001-004 | Necessary privacy detail |
| DET-007 | HTTPS, secure cookies, security headers, and safe production errors | NFR-SEC-005 and 011-017 | Necessary deployment hardening |
| DET-008 | Audit-event structure and append-only controls | FR-ADM-007 and 015-017 | Necessary oversight detail |

## 10. Approval-Required Decisions

These items are not clearly determined by the current local comparison source. They remain proposed until confirmed by the instructor or adviser. SRS Section 15 now identifies the same internal choices as proposed rather than instructor-confirmed.

| Approval ID | Proposed SRS decision | Exact SRS trace | Source relationship | Required decision | Status |
|---|---|---|---|---|---|
| APR-001 | Change or retain the AniMAL title if the instructor received FarmRoute | SRS title; Sections 1-2 | Historical baseline issue | Confirm the complete instructor-received baseline, not only its title. | Pending |
| APR-002 | Administrator verification required before farmers publish | FR-PROF-004-017; FR-LIST-001; BR-003 | Added trust workflow | Confirm whether verification is required before publication. | Pending |
| APR-003 | Pickup and farmer-arranged local delivery | FR-ORD-003-005 and 022-026; FR-STAT-001-002 | Added fulfillment policy | Confirm both fulfillment modes before schema design. | Pending |
| APR-004 | Farmer quotes a delivery fee and buyer accepts before confirmation | FR-ORD-019-024 and 027; FR-STAT-002-004; BR-024 and 037 | Added status, totals, and approval step | Confirm or reject this exact delivery-fee workflow. | Pending |
| APR-005 | Pending requests do not reserve stock; confirmation deducts atomically | FR-ORD-008-011 and 028; BR-008-010 | Clarifies order and inventory behavior | Confirm because it affects buyer expectations and concurrency design. | Pending |
| APR-006 | Buyer or farmer may cancel before preparation; administrator controls later cancellation | FR-STAT-004-010 and 014; BR-019-020 | Added cancellation and restoration policy | Confirm actors, cutoff, reasons, and restoration behavior. | Pending |
| APR-007 | Administrative holds for suspended users or deactivated listings | FR-STAT-011-013; FR-ADM-008-010 and 014; BR-038-039 | Added dispute-resolution mechanism | Confirm whether an in-system hold workflow is required. | Pending |
| APR-008 | Administrator-issued temporary passwords and forced password change | FR-AUTH-012-014, 017, and 020-021; FR-ADM-012-013 | Added user-support workflow | Confirm the reset and administrator-provisioning method. | Pending |
| APR-009 | Five-year retention, anonymization, and in-system retention holds | FR-ADM-018-019; BR-033-034 and 042; NFR-PRIV-005-010 | Added privacy-governance workflow | Confirm retention duration and whether legal holds are implemented or documented manually. | Pending |
| APR-010 | Registration, listing, order, verification, fulfillment-value, and cancellation report set | FR-REP-001-005 and 007-009 | Expands the broad reports module | Confirm the minimum report set required for defense. | Pending |
| APR-011 | Administrator-managed units and precision rules | FR-LIST-016-018 and 028; FR-ADM-011 and 020; BR-026-027 | Added reference-data workflow | Confirm managed units or approve a smaller fixed list. | Pending |
| APR-012 | One listing image with validation and server-side processing | FR-LIST-003 and 019; FR-VAL-005; BR-031 | Added listing capability | Confirm that listing images are required; retain upload controls if approved. | Pending |
| APR-013 | Public listing browsing before login | FR-SRCH-001 and 006 | Added access policy | Confirm public browsing or restrict browsing to registered buyers. | Pending |
| APR-014 | Contact and exact-address disclosure timing | FR-ORD-016 and 022-027; NFR-PRIV-008 | Added privacy and coordination policy | Confirm when each party and administrator may view private details. | Pending |
| APR-015 | Third-party couriers and ratings/reviews explicitly excluded | SRS Section 4.2 | Added scope boundaries | Confirm these first-release exclusions. | Pending |
| APR-016 | Full stock-adjustment ledger and restoration-exposure accounting | FR-LIST-029-032; BR-035-036 and 044 | Added integrity mechanism and schema effort | Confirm the ledger design or approve a documented simpler alternative. | Pending |
| APR-017 | Maps, route optimization, voice calls, SMS integration, and broader demand forecasting also excluded | SRS Sections 4.2, 5, and 15 | Broader than the corresponding GPS, video-call, push-notification, and inventory-forecast exclusions | Confirm each additional boundary instead of treating it as a synonym. | Pending |
| APR-018 | One listing per order request | FR-ORD-002 and 018 | Added order-shape policy | Confirm single-listing orders or specify a cart/multi-line order requirement. | Pending |
| APR-019 | Verification evidence checked offline with no identity-document upload | FR-PROF-010-011; BR-018 | Added verification evidence policy | Confirm the offline evidence process and no-document-storage boundary. | Pending |
| APR-020 | Printable HTML reports; PDF export optional | FR-REP-006 | Added output-format policy | Confirm required report formats. | Pending |
| APR-021 | Exact password, login-throttling, session, and temporary-password thresholds | FR-AUTH-016-019; NFR-SEC-008; BR-030 | Added security thresholds | Confirm the values or authorize the team to set technical defaults. | Pending |
| APR-022 | Administrator may cancel abandoned pending requests after defined inactivity | FR-STAT-015; BR-043 | Added stale-request policy | Confirm the seven-day and 48-hour thresholds and administrator authority. | Pending |

## 11. Current Coverage Summary

- All eleven in-scope modules in the current local comparison source are represented in the SRS.
- All ten out-of-scope items in the current local comparison source remain excluded.
- Security and coding guidance have strong, testable coverage.
- The largest unresolved risk is not missing core scope; it is treating later workflow policies as instructor-approved without confirmation.
- The main deliverable gaps are UML diagrams, SQL scripts, wireframes, explicit 3NF evidence, database rationale, user manual, presentation slides, defense Q&A, and the project-role plan.
- The interactive OpenDesign artifact is implementation guidance and design evidence, not the required production PHP application.

## 12. Change-Control Rule

No database table, production module, or acceptance test shall be finalized for an approval-required decision until one of the following is recorded:

1. Approved in the signed SRS.
2. Approved in adviser or instructor meeting minutes.
3. Approved through an official revision of the instructor-approved project instruction.

When approval is recorded, update the applicable `APR` status, the SRS version and change history, and the downstream UML, database, interface, and test artifacts.

## 13. Next Gate

The next gate is a short scope-alignment review using Section 10. Once the instructor-received baseline is recovered and the title and schema-affecting decisions are approved, the team may begin the instructor-approved UML set, ERD, normalization, relational schema, and data dictionary without silently changing the approved project scope.
