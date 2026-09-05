# PROJECT_STATUS.md — Living Project Status

## Wholesale Distribution Management System

**Document Version:** 1.1  
**Last Verified:** September 4, 2026  
**Audience:** Developer, Stakeholders, AI Coding Agents  
**Baseline Verification:** Phase 00 Foundation verified; tests passing; build passing.

---

## 1. Executive Status Summary

| Metric | Current Value | Notes |
|---|---|---|
| **Overall Code Completion** | **29.7%** (38 / 128 tickets) | FEAT-ORD-013 verified complete; 754 automated tests passing (753 passed, 4,934 assertions, 1 skipped) |
| **Specification Completion** | **100.0%** (5 / 5 documents) | PRD, Architecture, Security, Frontend, and Tickets are approved baselines |
| **Governance Layer Completion** | **100.0%** (13 / 13 files) | AGENTS, CLAUDE, GEMINI, and all `docs/*` operating system files active |
| **Current Phase** | **Phase 03 — Ordering Workflows** | Status: `IN_PROGRESS` (FEAT-ORD-001 through FEAT-ORD-006, FEAT-ORD-010, FEAT-ORD-011, FEAT-ORD-012, FEAT-ORD-013 complete) |
| **Current Milestone Gate** | **GATE B — Commerce Spine** | Status: In progress (Phase 00, Phase 01, Phase 02 complete; Phase 03/05 in progress) |
| **Current Active Ticket** | **FEAT-ORD-013** (Complete) | Ready to begin `FEAT-ALLOC-001: Order Item Quantity Allocation Model` (Phase 06) / `UI-003: Admin Application Shell` |
| **Git Working Tree** | Clean / Ready to Commit | Main branch tracking remote origin |
| **Active Blockers** | **1** | `FEAT-RBAC-003` deferred pending domain models (`FEAT-ORD-001` order model landed; `FEAT-DLV-001` delivery model pending) |

### Completion Calculation Formula
$$\text{Progress} = \left( \frac{\text{Completed Verified Implementation Tickets}}{\text{Total Non-Deferred Implementation Tickets}} \right) \times 100$$
- Total implementation tickets in backlog: **128** (encompassing Foundation, Features, UI, QA, and Deployment).
- Completed tickets: **38** (`TECH-FOUND-001`, `TECH-FOUND-002`, `TECH-FOUND-003`, `TECH-FOUND-004`, `UI-001`, `UI-002`, `DEPLOY-003`, `FEAT-AUTH-001`, `FEAT-AUTH-002`, `FEAT-AUTH-003`, `FEAT-AUTH-004`, `FEAT-RBAC-001`, `FEAT-RBAC-002`, `FEAT-SYS-001`, `FEAT-SYS-002`, `FEAT-CUS-001`, `FEAT-CUS-002`, `FEAT-CUS-003`, `FEAT-CUS-004`, `FEAT-SLM-001`, `FEAT-SLM-002`, `FEAT-PROD-001`, `FEAT-PROD-002`, `FEAT-PROD-003`, `FEAT-CAT-001`, `FEAT-PRICE-001`, `FEAT-PRICE-002`, `FEAT-TAX-001`, `FEAT-ORD-001`, `FEAT-ORD-002`, `FEAT-ORD-003`, `FEAT-ORD-004`, `FEAT-ORD-005`, `FEAT-ORD-006`, `FEAT-ORD-010`, `FEAT-ORD-011`, `FEAT-ORD-012`, `FEAT-ORD-013`).
- Current progress: **29.7%** (38 / 128).

---

## 2. Ticket Tracking Breakdown

- **Completed Tickets (38):**
  1. `TECH-FOUND-001`: Repository & Laravel 13 / React 19 / Inertia 3 / Vite Bootstrap
  2. `TECH-FOUND-002`: Database & Migration Foundation (PostgreSQL 18)
  3. `TECH-FOUND-003`: Global Error & Logging Foundation (/health & sanitization)
  4. `TECH-FOUND-004`: Queue & Cache Foundation (Redis 7)
  5. `UI-001`: Design Tokens (Typography, Colors, Spacing, Breakpoints)
  6. `UI-002`: Core Component Library (shadcn/ui tailoring: button, card, input, badge, label)
  7. `DEPLOY-003`: GitHub Actions CI Pipeline Foundation (`.github/workflows/ci.yml`)
  8. `FEAT-AUTH-001`: Centralized Multi-Portal Login & Throttling
  9. `FEAT-AUTH-002`: Logout & Session Revocation
  10. `FEAT-AUTH-003`: Secure Password Reset Flow
  11. `FEAT-AUTH-004`: Privileged Multi-Factor Authentication (MFA)
  12. `FEAT-RBAC-001`: Role Model Definition
  13. `FEAT-RBAC-002`: Server-Side Permission Registry
  14. `FEAT-SYS-001`: Configurable Application Identity
  15. `FEAT-SYS-002`: Company Settings & Business Details
  16. `FEAT-CUS-001`: Customer CRUD Operations
  17. `FEAT-CUS-002`: Customer Assignment & Scoping to Salesmen
  18. `FEAT-CUS-003`: Customer Profile, Outstanding Balance & Credit Limit View
  19. `FEAT-CUS-004`: Customer Lifecycle Controls (`ACTIVE`, `ON_HOLD`, `INACTIVE`)
  20. `FEAT-SLM-001`: Salesman Account Management & Lifecycle (`ACTIVE`, `INVITED`, `SUSPENDED`, `DISABLED`)
  21. `FEAT-SLM-002`: Salesman Scoped Customer Access Enforcement
  22. `FEAT-PROD-001`: Product Master CRUD (SKU, Cost, Default Selling, Minimum Price, MRP)
  23. `FEAT-PROD-002`: Product Image Upload & Storage (Private S3)
  24. `FEAT-PROD-003`: Product Lifecycle Controls (`ACTIVE`, `INACTIVE`)
  25. `FEAT-CAT-001`: Product Category Management & Hierarchy
  26. `FEAT-PRICE-001`: Price Boundary Constraint Enforcement
  27. `FEAT-PRICE-002`: Authorized Price Override Engine with Auditing
  28. `FEAT-TAX-001`: Product-Specific Tax Profile Engine & Order-Line Snapshot
  29. `FEAT-ORD-001`: Salesman Order Creation Flow (Customer select, catalogue browse, add to cart, atomic submission)
  30. `FEAT-ORD-002`: Draft Order Persistence & Resumption (Working drafts, optimistic version locking, resume/discard, atomic submission)
  31. `FEAT-ORD-003`: Order Line Quantity Stepper & Validation Controls (Segmented stepper, direct numeric input, boundary disable states, mobile touch targets >= 44px)
  32. `FEAT-ORD-004`: Order Review, Line Tax Breakdown & Financial Summary (Multi-line tax breakdown, tax profile code/rate display, financial summary, mobile review cards, zero client authority)
  33. `FEAT-ORD-005`: Order Submission Idempotency Enforcement (Dual-layer concurrency protection, advisory Redis lock, PostgreSQL unique constraint race recovery, canonical fingerprint 409 conflict detection, cross-salesman 403 authorization, audit log uniqueness)
  34. `FEAT-ORD-006`: Salesman Order History & Multi-State Timeline (Server-scoped order history workspace, multi-column search, enum status filters, date range filters, 15/page pagination, deterministic ordering, authentic milestone timeline, independent status dimension badges, zero cost leakage)
  35. `FEAT-ORD-010`: Admin Operational Order Queues (8 operational queues, live single-query badge counts, multi-column search, 5 independent status filters, salesman & customer filters, date range filtering, allowlisted sorting, bounded 25/page pagination with query string preservation, zero cost/evidence leakage, strict RBAC, and responsive mobile card & desktop dense table UX)
  36. `FEAT-ORD-011`: New Order Review Workspace (Dedicated inspection workspace at `/admin/orders/{order}/review`, bounded eager loading, fail-closed draft 404 isolation, post-review redirection, deterministic derived warnings [CUSTOMER_ON_HOLD, CUSTOMER_INACTIVE, CREDIT_LIMIT_EXCEEDED, PRICE_OVERRIDE_PRESENT, AGING_ORDER, PRODUCT_INACTIVE], authorized price override auditing, multi-line tax breakdown, five independent status dimensions, zero cost/evidence leakage, and responsive two-column/card UX)
  37. `FEAT-ORD-012`: Order Approval / Rejection Workflow with Audit (Authoritative approval and rejection engine, PostgreSQL pessimistic row locking with deterministic lock order, order-level quantity reservations [reserved_quantity = fulfillableQuantity], mandatory rejection reason validation [5-1000 chars], customer & product active state revalidation, soft vs hard blocker enforcement, zero financial mutation, immutable timeline milestones, structured audit logging [commerce.order_event], accessible modal dialogs in Review.tsx, and 33 automated feature tests)
  38. `FEAT-ORD-013`: Order Detail Master Workspace (Canonical administrative detail inspection workspace at `GET /admin/orders/{order}`, read-only operational command center, comprehensive lifecycle state support, 5 independent status dimensions, non-destructive quantity conservation [ordered, cancelled, reserved, fulfillable, picked, dispatched, delivered, returned], immutable historical pricing & tax snapshots vs contextual current master data, multi-line tax profile breakdown table, customer commercial context with current address labeling, historical salesman relationship attribution, authentic milestone timeline, zero cost_price/payment evidence/secret leakage, safe backUrl sanitization, strict RBAC [Admin/Super Admin/Accountant read-only; Salesman/Warehouse/Delivery denied 403], responsive 12-column layout and mobile card UX with touch targets >= 44px, and 20 automated feature tests)
- **In-Progress Tickets (0):** None.
- **Blocked / Deferred Tickets (1):**
  1. `FEAT-RBAC-003`: Resource Scope Enforcement (Deferred per DEC-014; blocked pending Delivery domain models in Phase 08).
- **Upcoming Tickets (Phase 06 — Allocation, Adjustment & Inventory Integrity):**
  1. `FEAT-ALLOC-001`: Order Item Quantity Allocation Model
  2. `UI-003`: Admin Application Shell

---

## 3. Milestone Gate Progress

| Milestone Gate | Target Phases | Scope | Status | Exit Verification |
|---|---|---|---|---|
| **GATE A: FOUNDATION** | Phase 00, 01 | Application boot, DB, Design System, Auth & RBAC | `IN_PROGRESS` | Phase 00 Complete; Phase 01 Pending |
| **GATE B: COMMERCE** | Phase 02, 03, 04, 05 | Master Data, Pricing, Tax, Ordering, Admin Queues | `NOT_STARTED` | Incomplete |
| **GATE C: OPERATIONS** | Phase 06 | Quantity Allocation, Order Adjustment, Inventory | `NOT_STARTED` | Incomplete |
| **GATE D: FINANCE** | Phase 07, 09 | Payments, Invoices, Receivables, General Ledger | `NOT_STARTED` | Incomplete |
| **GATE E: LOGISTICS** | Phase 08 | Delivery Operations, Returns, Credit Notes, Refunds | `NOT_STARTED` | Incomplete |
| **GATE F: PRODUCTION** | Phase 10, 11 | Reports, Auditing, Hardening, CI/CD, AWS Deploy | `NOT_STARTED` | Incomplete |

---

## 4. Known Issues, Risks & Mitigation

### Active Technical Risks
1. **Inventory Concurrency Under Peak Ordering:** Simultaneous checkout by salesmen for scarce stock could lead to over-allocation if database locking is flawed.
   - *Mitigation:* Explicit row locking (`SELECT FOR UPDATE`) and dedicated concurrency automated test suite (`QA-005`).
2. **Order Adjustment Complexity:** Post-order quantity modifications affect allocations, inventory reservations, taxes, and customer outstanding balances simultaneously.
   - *Mitigation:* Isolated `order_adjustments` domain using atomic database transactions (`DB::transaction`) and non-destructive quantity tracking (`RULE-ALLOC-001`).
3. **Payment Evidence Security Leakage:** Cheque and money-order JPEG photos contain sensitive financial data.
   - *Mitigation:* Private Amazon S3 bucket with zero public access; short-lived presigned URLs ($\le 15\text{ mins}$) generated on-demand for authorized roles only (`RULE-FILE-003`).
4. **Historical Financial Mutability:** Future changes to product prices or taxes could corrupt past invoices or journal entries.
   - *Mitigation:* Immutable historical snapshots permanently recorded on order line items (`RULE-PRI-001`, `RULE-TAX-002`).

---

## 5. Open Decisions & TBD Items

The following business items are marked **TBD** in specifications and must be resolved by client confirmation or defaulted using safe, configurable settings:

- **DEC-TBD-001:** Exact sales tax jurisdiction rules and exemption certificate handling (PRD §11.7). Default: single configurable percentage rate per tax profile.
- **DEC-TBD-002:** Tax rounding policy. Default: Banker's rounding (`ROUND_HALF_UP`) to 2 decimal places per line item.
- **DEC-TBD-003:** Exact cheque return/bounce administrative fee policy. Default: restore receivable without automated fee.
- **DEC-TBD-004:** Exact Money Order verification and shift reconciliation workflow (PRD §19.6). Default: manual verification by Admin/Accountant.
- **DEC-TBD-005:** Whether unverified pending cheque payments reduce customer credit exposure immediately or upon verification. Default: exposure reduced only upon verification (`VERIFIED`).
- **DEC-TBD-006:** Default policy for customer balances after downward adjustment: automated account credit vs cash refund. Default: account credit balance created.
- **DEC-TBD-007:** Exact revenue recognition timing: upon dispatch vs upon delivery. Default: recognized upon delivery confirmation.
- **DEC-TBD-008:** Invoice sequential numbering format and prefix. Default: `INV-{YYYY}-{000001}`.

---

## 6. Technical Debt Intentionally Deferred

The following items are recognized as future enterprise enhancements and are intentionally excluded from the V1 build (see PRD §39.2 and Backlog §44):

- `FUTURE-001`: Advanced promotion / coupon discount engine.
- `FUTURE-002`: Automated online payment gateways (Stripe/Authorize.net/Credit Card).
- `FUTURE-003`: Dedicated customer self-service mobile app / portal.
- `FUTURE-004`: Multi-company / multi-warehouse complex routing.
- `FUTURE-005`: Barcode scanning hardware integration for warehouse fulfillment.
- `FUTURE-006`: AI-assisted predictive demand forecasting.

---

## 7. Environment & Infrastructure State

- **Local Development Environment:** Fully bootstrapped and operational. Docker Compose running `postgres:18-alpine` (port 5433) and `redis:7-alpine` (port 6380).
- **Staging Environment:** Not configured (`DEPLOY-001` scheduled for Phase 11).
- **Production AWS Environment:** Architecture-ready (`DEPLOY-002` scheduled for Phase 11).
- **Automated Test Runner:** PHPUnit harness operational (410 tests passing, 2,728 assertions). TypeScript static check operational (`npm run type-check`).
- **CI/CD Pipelines:** GitHub Actions CI workflow implemented in `.github/workflows/ci.yml`.

---

## 8. Next Recommended Action
 
1. Activate next ticket in sequence from Document 05 / BUILD_PHASES.md:
   - **`FEAT-ALLOC-001: Order Item Quantity Allocation Model`** (Phase 06 / Epic 07: Operations: Allocation, Adjustments & Inventory).
