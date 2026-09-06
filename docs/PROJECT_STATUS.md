# PROJECT_STATUS.md — Living Project Status

## Wholesale Distribution Management System

**Document Version:** 1.2  
**Last Verified:** September 6, 2026  
**Audience:** Developer, Stakeholders, AI Coding Agents  
**Baseline Verification:** Phase 00 through Phase 07 verified; tests passing; build passing.

---

## 1. Executive Status Summary

| Metric | Current Value | Notes |
|---|---|---|
| **Overall Code Completion** | **48.4%** (62 / 128 tickets) | FEAT-PAY-001 through FEAT-PAY-009 + UI-008 verified complete; 1,083 automated tests (1,073 passed, 6,942 assertions, 10 skipped) |
| **Specification Completion** | **100.0%** (5 / 5 documents) | PRD, Architecture, Security, Frontend, and Tickets are approved baselines |
| **Governance Layer Completion** | **100.0%** (13 / 13 files) | AGENTS, CLAUDE, GEMINI, and all `docs/*` operating system files active |
| **Current Phase** | **Phase 07 — Payments & Payment Evidence** | Status: `COMPLETED` (FEAT-PAY-001 through FEAT-PAY-009 + UI-008 complete; multi-method payment engine, private S3 evidence, server-side JPEG verification, verification queue, maker-checker segregation, and reversals landed) |
| **Current Milestone Gate** | **GATE D — Finance & Receivables** | Status: `IN_PROGRESS` (Phase 07 complete; Phase 09 pending) |
| **Current Active Ticket** | **FEAT-PAY-009** (Complete) | Ready to begin Phase 08 (`FEAT-DEL-001: Delivery Assignment Engine & Driver Work Allocation`) or Phase 09 (`FEAT-DOC-001: Invoice Generation Engine`) |
| **Git Working Tree** | Clean / Ready to Commit | Main branch tracking remote origin |
| **Active Blockers** | **1** | `FEAT-RBAC-003` deferred pending domain models (`FEAT-ORD-001` order model landed; `FEAT-DLV-001` delivery model pending) |

### Completion Calculation Formula
$$\text{Progress} = \left( \frac{\text{Completed Verified Implementation Tickets}}{\text{Total Non-Deferred Implementation Tickets}} \right) \times 100$$
- Total implementation tickets in backlog: **128** (encompassing Foundation, Features, UI, QA, and Deployment).
- Completed tickets: **62** (`TECH-FOUND-001`, `TECH-FOUND-002`, `TECH-FOUND-003`, `TECH-FOUND-004`, `UI-001`, `UI-002`, `UI-008`, `DEPLOY-003`, `FEAT-AUTH-001`, `FEAT-AUTH-002`, `FEAT-AUTH-003`, `FEAT-AUTH-004`, `FEAT-RBAC-001`, `FEAT-RBAC-002`, `FEAT-SYS-001`, `FEAT-SYS-002`, `FEAT-CUS-001`, `FEAT-CUS-002`, `FEAT-CUS-003`, `FEAT-CUS-004`, `FEAT-SLM-001`, `FEAT-SLM-002`, `FEAT-PROD-001`, `FEAT-PROD-002`, `FEAT-PROD-003`, `FEAT-CAT-001`, `FEAT-PRICE-001`, `FEAT-PRICE-002`, `FEAT-TAX-001`, `FEAT-ORD-001`, `FEAT-ORD-002`, `FEAT-ORD-003`, `FEAT-ORD-004`, `FEAT-ORD-005`, `FEAT-ORD-006`, `FEAT-ORD-010`, `FEAT-ORD-011`, `FEAT-ORD-012`, `FEAT-ORD-013`, `FEAT-ALLOC-001`, `FEAT-ALLOC-002`, `FEAT-ADJ-001`, `FEAT-ADJ-002`, `FEAT-ADJ-003`, `FEAT-ADJ-004`, `FEAT-ADJ-005`, `FEAT-ADJ-006`, `FEAT-INV-001`, `FEAT-INV-002`, `FEAT-INV-003`, `FEAT-INV-004`, `FEAT-INV-005`, `FEAT-INV-006`, `FEAT-PAY-001`, `FEAT-PAY-002`, `FEAT-PAY-003`, `FEAT-PAY-004`, `FEAT-PAY-005`, `FEAT-PAY-006`, `FEAT-PAY-007`, `FEAT-PAY-008`, `FEAT-PAY-009`).
- Current progress: **48.4%** (62 / 128).

---

## 2. Ticket Tracking Breakdown

- **Completed Tickets (62):**
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
  39. `FEAT-ALLOC-001`: Order Item Quantity Allocation Model (Dedicated canonical `order_item_allocations` table and `OrderItemAllocation` model, preservation of authoritative denormalized quantity rollups on `order_items`, atomic baseline allocation integration inside `OrderWorkflowService::approveOrder()`, cross-row conservation enforcement $\sum \text{allocated} \le \text{fulfillable}$, partial allocation and residual unallocated computation, row-local PostgreSQL CHECK constraints, deterministic lock ordering, lifecycle gating [APPROVED/PROCESSING allowed; DRAFT/SUBMITTED/REJECTED/CANCELLED/COMPLETED blocked 409], idempotent historical approved order backfill service, Admin Order Detail allocation breakdown and summary projection, and 19 automated feature tests)
  40. `FEAT-ALLOC-002`: Allocation Validation & Mathematical Constraints (Mathematical conservation laws $\text{ordered} = \text{cancelled} + \text{fulfillable}$, active allocation sum constraint $\sum \text{allocated}_{\text{active}} \le \text{fulfillable}$, unallocated capacity calculation $\max(0, \text{fulfillable} - \sum \text{allocated}_{\text{active}})$, strict fulfillment progression constraints $0 \le \text{returned} \le \text{delivered} \le \text{dispatched} \le \text{picked} \le \text{allocated}$ and $0 \le \text{reserved} \le \text{allocated}$, migration `2026_09_05_000012` tightening PostgreSQL progression check constraints, non-destructive soft-states for `RELEASED` and `CANCELLED` allocations restoring unallocated pool, single-direction authoritative rollup synchronization via `OrderAllocationService::syncOrderItemRollups()`, collision-free max sequence calculation `ALC-{order}-{item}-{seq}`, adjustment reduction capacity pre-check `canReduceFulfillableQuantity()`, rollup drift detection `OrderAllocationValidationService::detectRollupDrift()`, pessimistic row locking and concurrency serialization, 25 new automated feature tests across `AllocationValidationTest.php` and `AllocationConcurrencyTest.php`, 798 total tests passing 100%)
  41. `FEAT-ADJ-001`: Order Adjustment Request Flow (Authoritative non-destructive post-submission quantity reduction request workflow, migration `2026_09_05_000013` creating `order_adjustments` and `order_adjustment_items` with `RESTRICT` deletion semantics, single-open-request invariant enforced via PostgreSQL partial unique index `idx_order_adjustments_single_open` and row lock, deterministic sequence generation `ADJ-{order}-{seq}`, Case A [unallocated only] vs Case B [allocation-impacting] classification, informational financial projections using BCMath and `TaxCalculationService::roundHalfUp`, deterministic idempotency replay vs 409 payload conflict detection, requester cancellation/withdrawal restoring order status, full server-side scoping [Salesman assigned orders; Warehouse Manager approved/processing orders; Admin broad scope], lifecycle gating [SUBMITTED/PENDING_APPROVAL/APPROVED/PROCESSING allowed; DRAFT/COMPLETED/CANCELLED/REJECTED blocked 409], minimal responsive UI modal and pending adjustment banner, 30 automated feature and concurrency tests, 828 total repository tests passing 100%)
  42. `FEAT-ADJ-002`: Adjustment Review Workspace with Real-Time Financial & Tax Impact Preview (Dual-entry review architecture with dedicated Adjustment Queue at `GET /admin/adjustments` and dedicated Review Workspace at `GET /admin/orders/{order}/adjustments/{adjustment}/review`, strict read-only boundary with zero mutation of orders, allocations, or finances, canonical active allocation definition excluding both `CANCELLED` and `RELEASED` states, exact progression math preventing picked+dispatched double-counting, side-by-side historical request snapshot vs live order state evaluation, pure `OrderAdjustmentReviewService` stale/conflict classification [`READY`, `WARNING_ALLOCATION`, `WARNING_PICKED_ENCROACHMENT`, `STALE`, `CONFLICTED`, `INELIGIBLE_LIFECYCLE`, `TERMINAL_REQUEST`], Option A reviewer access resolution [Super Admin, Admin, and Accountant authorized; Warehouse Manager, Salesman, and Delivery Partner denied], anti-IDOR validation, bounded pagination, zero schema migrations, 26 automated feature tests across queue, review detail, stale state, and security, 854 total repository tests passing 100%)
  43. `FEAT-ADJ-003`: Adjustment Approval / Rejection Workflow (Authoritative dual-control decision engine, deterministic lock ordering Order -> OrderItems ASC -> OrderItemAllocations ASC -> OrderAdjustment, maker-checker segregation of duties preventing self-decision, Super Admin emergency override requiring documented 10-1000 char justification and dedicated audit event, live state revalidation under transaction lock, strict duplicate decision guard returning deterministic 409 Conflict, Case B allocation acknowledgment, order adjustment_status maintenance [REQUESTED on approved, NONE on rejected], zero application mutations of quantities, allocations, or financials, accessible interactive modals in Review.tsx, and 41 new automated feature tests across approval, rejection, maker-checker, and concurrency, 895 total repository tests passing 100%)
  44. `FEAT-ADJ-004`: Atomic Adjustment Application Engine (Authoritative transactional execution engine applying approved order adjustments, deterministic row locking Order -> OrderItems ASC -> OrderItemAllocations ASC -> OrderAdjustment in DB::transaction(..., 3), live re-validation under lock, strict quantity conservation ordered = cancelled + fulfillable [RULE-DOM-001], Case A unallocated reductions and Case B allocation releases, partial release mathematics preserving 0 <= reserved <= allocated with zero negative intermediate state, non-destructive allocation split history creating active remainder + released historical child rows with canonical sequence ALC-{order}-{item}-{seq}, deterministic release priority [ALLOCATED before RESERVED, sequence DESC], prohibition of picked allocation release [409 Conflict], authoritative line and order financial recalculation using TaxCalculationService without rounding drift, immutable historical price/tax snapshots, single order version increment [+1], order adjustment_status transition to APPLIED, exactly-once application protection [409 Conflict on re-apply], Super Admin / Admin RBAC enforcement [Permission::ORDER_ADJUST_APPLY], accessible interactive Apply modal in Review.tsx, structured post-commit observability events, 20 new automated feature tests across application, security, and concurrency, verified directly against PostgreSQL 18.6 container, 915 total repository tests passing 100%)
  45. `FEAT-ADJ-005`: Adjustment Reversal Engine (Authoritative transactional reversal engine for applied adjustments, strictly APPLIED -> REVERSED state machine with 409 rejection on duplicate or non-applied states, canonical row locks Order -> OrderItems ASC -> OrderItemAllocations ASC -> OrderAdjustment in DB::transaction(..., 3), strict deterministic LIFO reversal order enforcement [applied_at DESC, id DESC], Case A unallocated restoration, Case B non-destructive forward restoration allocation with zero reservation fabrication [0 <= reserved <= allocated], immutable historical RELEASED allocation preservation, authoritative line & order financial recalculation via TaxCalculationService preserving historical price snapshots, safe BCMath adjustment_total decrement, single order version increment [+1], order adjustment_status transition to REVERSED if no other applied adjustments remain active, order lifecycle and fulfillment progression guards, maker-checker segregation preventing requester self-reversal except via Super Admin emergency override [10-1000 chars], anti-IDOR validation, accessible interactive ReverseAdjustmentModal in Review.tsx, structured post-commit observability events, migration 2026_09_06_000001 adding reversed_by and reversal_reason, 24 targeted automated tests across core, security, and concurrency suites, 932 full repository tests passing 100%, PostgreSQL 18 verified)
  46. `FEAT-ADJ-006`: Admin Adjustment & Exception Processing Queue (Dedicated administrative operational queue at `GET /admin/adjustments`, 7 canonical views [`attention`, `pending`, `ready_to_apply`, `applied`, `reversed`, `closed`, `all`], strict `READY_TO_APPLY` non-conflicting semantics excluding blocked records, single authoritative domain classifier `OrderAdjustmentClassifier` with priority precedence `CONFLICTED` > `INELIGIBLE_LIFECYCLE` > `PICKED_ENCROACHMENT` > `STALE_VERSION`/`STALE_STATUS` > `AGING` [>24h], single SQL aggregate badge query with zero duplicate counts, multi-column search across adjustment number, order number, customer name/code, requester name/email, allowlisted sort with `id DESC` tie-breaker, bounded pagination, zero schema migrations, constant 10-query budget with zero N+1 scaling, strict RBAC [`Permission::ORDER_ADJUST_REVIEW`], anti-IDOR deep links, responsive table and mobile card layout with touch targets >= 44px, 23 new automated tests, 945 full repository tests passing 100%)
  47. `FEAT-INV-001`: Inventory Item & Stock Balance Foundations (Canonical `warehouses` table with default `MAIN` and partial unique default index, canonical `inventory_balances` table keyed on `UNIQUE (warehouse_id, product_id)` with RESTRICT foreign keys, PostgreSQL CHECK constraints enforcing non-negativity, bounds `reserved + damaged <= on_hand`, and formula `available = on_hand - reserved - damaged`, Product `inventoryBalances()` HasMany relation, idempotent automatic stock initialization on product creation, idempotent catalog backfill via `php artisan inventory:initialize` and `InventoryInitializationService`, deterministic row lock helper `InventoryService::lockBalancesForUpdate` ordered by ID ASC, read-only administrative workspace `GET /admin/inventory` protected by `Permission::INVENTORY_VIEW`, stock status indicators `IN_STOCK`/`LOW_STOCK`/`OUT_OF_STOCK`, multi-column search, bounded pagination, 15 comprehensive automated tests, 967 full repository tests passing 100%, PostgreSQL 18 verified)
  48. `FEAT-INV-002`: Four-Tier Stock Representation & Detail Workspace (Strict physical separation of on-hand, reserved, available, and damaged stock, dedicated product stock detail workspace at `GET /admin/inventory/{inventoryBalance}`, commercial coverage analytics, composition proportions, active order commitments table, responsive UI)
  49. `FEAT-INV-003`: Pessimistic Locking Stock Reservation Engine (Authoritative physical stock reservation engine inside `StockReservationService`, atomic order approval reservation and rejection release, deterministic ascending lock ordering, strict `InsufficientStockException`, 14 comprehensive tests)
  50. `FEAT-INV-004`: Immutable Stock Movement History Ledger (Canonical `inventory_movements` table with database and model-level mutation blocks preventing UPDATE/DELETE, authoritative `recordMovement()`, physical snapshot tracking, dedicated movement history table in Show.tsx, 10 comprehensive tests)
  51. `FEAT-INV-005`: Warehouse Stock Exception Reporting Flow (Dedicated stock exception reporting and review queue at `GET /admin/inventory-exceptions`, automatic quarantine of damaged goods from `AVAILABLE` or `RESERVED` to `DAMAGED`, authoritative resolution and dismissal with optional quarantine reversion, strict RBAC, 6 comprehensive tests)
  52. `FEAT-INV-006`: Authorized Inventory Balance Adjustments (Authorized direct physical stock adjustment engine for `INCREASE_ON_HAND`, `DECREASE_ON_HAND`, `TRANSFER_TO_DAMAGED`, `DAMAGE_DISPOSAL`, optimistic concurrency version check + pessimistic row lock, movement ledger link, direct modal in Show.tsx, 12 comprehensive tests)
  53. `FEAT-PAY-001`: Payment Entity & Multi-Method Domain Model (`CASH`, `CHEQUE`, `MONEY_ORDER` domain modeling, PostgreSQL `chk_payments_amount_positive`, sequence `payment_number_seq`, status state machine, factory, and tests)
  54. `FEAT-PAY-005`: Payment Evidence Upload & Server-Side JPEG Magic-Byte Verification (Private S3 storage `payments/{year}/{month}/{uuid}.jpg`, binary magic bytes `\xFF\xD8\xFF`, MIME `image/jpeg`, structural `getimagesize()`, 5MB limit)
  55. `UI-008`: Payment Evidence Upload & Preview UI Component (Accessible React uploader with drag-and-drop, camera mobile input, touch target >= 44px, client pre-validation)
  56. `FEAT-PAY-006`: Payment Evidence Preview with Secure Presigned URLs (15-minute temporary presigned URLs, streaming fallback, full zoom/pan/rotate modal dialog, anti-IDOR resource scoping)
  57. `FEAT-PAY-002`: Cash Payment Entry Flow (Deterministic number generation `PAY-{YEAR}-{00000X}`, atomic order-linked & unlinked balance recording, customer scoping, auto-verified admin vs pending salesman receipts)
  58. `FEAT-PAY-003`: Cheque Payment Entry Flow (Mandatory JPEG evidence upload, bank name, cheque number, cheque date, race-safe customer duplicate detection, pending verification status)
  59. `FEAT-PAY-004`: Money Order Payment Entry Flow (Mandatory JPEG evidence upload, issuer name, money order number, issue date, race-safe customer duplicate detection, pending verification status)
  60. `FEAT-PAY-007`: Payment Verification & Reconciliation Workflow (Administrative verification queue at `GET /admin/payments`, tabbed views with live badge counts, maker-checker segregation of duties, aggregate Customer -> Order -> Payment row locking, authoritative order payment status rollup `UNPAID`/`PARTIALLY_PAID`/`PAID`/`OVERPAID`)
  61. `FEAT-PAY-008`: Payment Rejection & Correction Flow (Structured rejection reasons, optional rejection notes, correction and resubmission preserving audit trail and payment number)
  62. `FEAT-PAY-009`: Payment Reversal & Bounced Cheque Operational Flow (Terminal reversal `BOUNCED_CHEQUE`/`NSF`/`STOP_PAYMENT`/`ENTRY_ERROR`, strict permission check `payment.reverse` for Super Admin & Accountant, order balance and payment status recalculation)
- **In-Progress Tickets (0):** None.
- **Blocked / Deferred Tickets (1):**
  1. `FEAT-RBAC-003`: Resource Scope Enforcement (Deferred per DEC-014; blocked pending Delivery domain models in Phase 08).
- **Upcoming Tickets (Phase 08 — Logistics & Delivery Operations or Phase 09 — Invoices & Accounting):**
  1. `FEAT-DEL-001`: Delivery Assignment Engine & Driver Work Allocation
  2. `FEAT-DOC-001`: Invoice Generation Engine from Historical Line Snapshots

---

## 3. Milestone Gate Progress

| Milestone Gate | Target Phases | Scope | Status | Exit Verification |
|---|---|---|---|---|
| **GATE A: FOUNDATION** | Phase 00, 01 | Application boot, DB, Design System, Auth & RBAC | `IN_PROGRESS` | Phase 00 Complete; Phase 01 Pending |
| **GATE B: COMMERCE** | Phase 02, 03, 04, 05 | Master Data, Pricing, Tax, Ordering, Admin Queues | `COMPLETED` | Phase 02, 03, 04, 05 Complete |
| **GATE C: OPERATIONS** | Phase 06 | Quantity Allocation, Order Adjustment, Inventory | `COMPLETED` | Phase 06 Complete |
| **GATE D: FINANCE** | Phase 07, 09 | Payments, Invoices, Receivables, General Ledger | `IN_PROGRESS` | Phase 07 Complete; Phase 09 Pending |
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
