# CHANGE_LOG.md — Controlled Change Management & Audit

## Wholesale Distribution Management System

**Document Version:** 1.0  
**Effective Date:** September 2026  
**Status:** Authoritative Change Registry  
**Protocol:** No client-requested business or architectural change may be implemented directly in code without passing through the formal **Change Impact Assessment Procedure** documented below.

---

## 1. Change Impact Assessment Procedure

When a new business requirement, client change request, or technical modification arrives, the developer/agent must execute this protocol:

```text
1. Record the Request (Assign CHANGE-XXX)
       ↓
2. Identify Affected Specifications (PRD, Technical Architecture, Security, Frontend)
       ↓
3. Assess Domain Impacts:
   - Order & Allocation Impact
   - Inventory & Warehouse Impact
   - Pricing & Tax Impact
   - Payment & Evidence Impact
   - Accounting & General Ledger Impact
       ↓
4. Assess Non-Functional Impacts:
   - Security, Authorization & Audit Impact
   - Database Migration & Schema Impact
   - Performance & Concurrency Impact
   - Automated Testing & QA Impact
       ↓
5. Classify Scope & Versioning:
   - Is this within V1 scope, a new build phase, or deferred to future releases?
       ↓
6. Formal Approval & Document Synchronization:
   - Update PRD, Architecture, Security, or Frontend docs first
   - Create or update tickets in Feature Ticket List
       ↓
7. Implementation & Verification
```

---

## 2. Change Register

### CHANGE-001: Baseline Specification Freeze & Project Operating System Initialization
- **Change ID:** `CHANGE-001`
- **Date:** September 4, 2026
- **Requested By:** Project Architect & Solo Developer
- **Request:** Establish the foundational five authoritative specifications (PRD, Architecture, Security & Access, Frontend Specification, Feature Tickets) and institute the comprehensive Project Operating System governance layer (`AGENTS.md`, `CLAUDE.md`, `GEMINI.md`, and all `docs/*` control files).
- **Reason:** Prevent architectural drift, eliminate unverified AI assumptions, guarantee non-destructive historical data models, and provide a deterministic, auditable engineering process for long-term development.
- **Status:** `APPROVED & COMPLETED`
- **Priority:** `P0` (Critical Prerequisite)
- **Affected PRD Requirements:** Documents 01 through 05 established as baseline v1.0.
- **Affected Architecture:** All sections of Technical Architecture established.
- **Affected Security:** Strict server-side zero-trust security architecture established.
- **Affected Frontend:** "Premium B2B Commerce × Modern SaaS ERP" design direction locked.
- **Affected Tickets:** All tickets (`TECH-FOUND-001` through `DEPLOY-005`).
- **Inventory Impact:** Formalized 4-tier stock representation (`on_hand`, `reserved`, `available`, `damaged`) and pessimistic locking.
- **Order Impact:** Locked non-destructive quantity allocation and explicit order adjustment framework.
- **Payment Impact:** Locked V1 payment methods (`CASH`, `CHEQUE`, `MONEY_ORDER`) with mandatory JPEG evidence.
- **Tax Impact:** Locked product-specific line-level tax calculation and transaction snapshots.
- **Accounting Impact:** Locked immutable double-entry general ledger journal posting.
- **Data Migration Impact:** Clean slate; zero legacy migrations.
- **Testing Impact:** Established 11 QA test suites and cross-feature edge case register (`EDGE-001` through `EDGE-025`).
- **Deployment Impact:** Baseline AWS architecture (EC2, RDS PostgreSQL, ElastiCache, S3, Route 53) specified.
- **Approved By:** Lead Architect / Project Governance
- **Implementation Status:** Verified complete; control layer active.
- **Release/Commit Reference:** Initial commit baseline.

---

### CHANGE-002: Authoritative Price Boundary Constraint Enforcement & Decimal Hardening
- **Change ID:** `CHANGE-002`
- **Date:** September 5, 2026
- **Requested By:** Principal Software Architect
- **Request:** Establish server-side price boundary enforcement (`0 <= cost_price`, `0 < min <= default <= mrp`), eliminate float casting in favor of exact 2-decimal BCMath arithmetic strings via `PriceBoundaryService`, add PostgreSQL `products_pricing_hierarchy_check` constraint, enforce resulting-state validation on updates, and create reusable domain method `validateOrderUnitPrice()` for future Phase 05 Orders.
- **Reason:** Eliminate float-precision rounding anomalies in financial data, enforce defense-in-depth database constraints, and provide reusable domain authority for order lines.
- **Status:** `APPROVED & IMPLEMENTED`
- **Priority:** `P1` (Commerce Spine Prerequisite)
- **Affected PRD Requirements:** PRD §9.1, §9.2, §10.1.
- **Affected Architecture:** Technical Architecture §18, §19, §23.
- **Affected Security:** Permission `product.price.update` strictly enforced on price mutations.
- **Affected Frontend:** Product Create/Edit forms with step="0.01" and real-time visual hierarchy feedback.
- **Affected Tickets:** `FEAT-PRICE-001`.
- **Inventory Impact:** None.
- **Order Impact:** Reusable order line unit price boundary validator (`validateOrderUnitPrice`) established for Phase 05.
- **Payment Impact:** None.
- **Tax Impact:** None.
- **Accounting Impact:** Preserves accurate, non-floating-point financial base prices.
- **Data Migration Impact:** Added PostgreSQL CHECK constraint `products_pricing_hierarchy_check` via migration `2026_09_05_000005_add_pricing_hierarchy_check_to_products_table.php`.
- **Testing Impact:** Added `PriceBoundaryServiceTest` (24 unit tests) and `ProductPriceBoundaryTest` (17 feature tests). Total suite at 492 tests passing.
- **Deployment Impact:** Verified BCMath extension availability in PHP runtime.
- **Approved By:** Principal Software Architect
- **Implementation Status:** Complete and verified.
- **Release/Commit Reference:** `FEAT-PRICE-001`.

---

### CHANGE-003: Authorized Price Override Engine & Permission Registry Expansion
- **Change ID:** `CHANGE-003`
- **Date:** September 5, 2026
- **Requested By:** Principal Software Architect
- **Request:** Establish the server-side price override domain engine (`PricingOverrideService`) to evaluate and authorize selling price exceptions outside normal boundaries (`minimum_allowed_price <= price <= mrp`) with mandatory reason capture (5-500 chars), strict BCMath decimal math, registration of `Permission::PRICING_OVERRIDE = 'pricing.override'` (expanding registry to 48 canonical codes mapped to `SUPER_ADMIN` and `ADMIN`), readonly DTO `PriceOverrideDecision`, non-zero/non-negative invariant enforcement ($> 0.00$), structured audit logging (`PRODUCT_PRICE_OVERRIDE_AUTHORIZED`), and security warning emission for unauthorized attempts.
- **Reason:** Enable commercial flexibility for strategic discounts and expedited surcharges while protecting Product Master immutability, ensuring zero client trust, and preserving complete financial auditability.
- **Status:** `APPROVED & IMPLEMENTED`
- **Priority:** `P1` (Commerce Spine Prerequisite)
- **Affected PRD Requirements:** PRD §9.1, §9.2, §10.1, §10.2.
- **Affected Architecture:** Technical Architecture §18, §19, §23.
- **Affected Security:** Registered `pricing.override` in `Permission` enum; mapped exclusively to `SUPER_ADMIN` (48 permissions) and `ADMIN` (40 permissions); rejected unauthorized roles (Salesman, Warehouse Manager, Accountant, Delivery Partner) with 403.
- **Affected Frontend:** None in this ticket; ready for future Phase 05 Order placement UI integration.
- **Affected Tickets:** `FEAT-PRICE-002`.
- **Inventory Impact:** None.
- **Order Impact:** Reusable price override evaluation and authorization engine (`PricingOverrideService`) established for Phase 05 Order workflows.
- **Payment Impact:** None.
- **Tax Impact:** None.
- **Accounting Impact:** Preserves immutable Product Master prices while providing auditable prospective transaction override context.
- **Data Migration Impact:** None. Product Master `products_pricing_hierarchy_check` PostgreSQL CHECK constraint remains unmodified and un-weakened.
- **Testing Impact:** Added `PricingOverrideServiceTest` (18 unit tests) and `PriceOverrideIntegrationTest` (9 feature tests). Total suite at 521 tests passing (3,127 assertions).
- **Deployment Impact:** None.
- **Approved By:** Principal Software Architect
- **Implementation Status:** Complete and verified.
- **Release/Commit Reference:** `FEAT-PRICE-002`.

### CHANGE-004: Product-Specific Tax Profile Engine & Order-Line Snapshot Contract
- **Change ID:** `CHANGE-004`
- **Date:** September 5, 2026
- **Requested By:** Principal Software Architect
- **Request:** Establish the product-specific tax profile domain engine (`tax_profiles` table, `TaxProfile` model, `TaxProfileStatus` enum), authoritative calculation engine (`TaxCalculationService`) using exact decimal BCMath arithmetic with deterministic `ROUND_HALF_UP` rounding, header tax sum of rounded line taxes invariant, transaction snapshot contract DTOs (`TaxSnapshotData`, `TaxCalculationResult`), Product Master integration (`products.tax_profile_id` FK with `ON DELETE RESTRICT`), active-only assignment guards, non-destructive deactivation, RBAC protection via `Permission::PRODUCT_TAX_UPDATE`, structured audit logging, and responsive frontend UI (Tax Profile Index/Create/Edit and Product Create/Edit/Show selectors).
- **Reason:** Provide product-specific tax configuration with zero float financial authority, protect historical transaction integrity, ensure invoice line-tax reconcilability without half-cent rounding errors, and prepare the commerce spine for future Phase 03/05 Order workflows.
- **Status:** `APPROVED & IMPLEMENTED`
- **Priority:** `P1` (Commerce Spine Prerequisite)
- **Affected PRD Requirements:** PRD §11.1, §11.2, §11.3, §11.4, §11.5.
- **Affected Architecture:** Technical Architecture §20, §23.
- **Affected Security:** Permission `product.tax.update` enforced on Tax Profile CRUD and Product tax profile assignments; unauthorized roles rejected with 403.
- **Affected Frontend:** Created Tax Profile Index/Create/Edit pages (`resources/js/Pages/TaxProfile/*`), updated Product Create/Edit/Show pages with tax profile selectors and displays, and updated sidebar navigation.
- **Affected Tickets:** `FEAT-TAX-001`.
- **Inventory Impact:** None.
- **Order Impact:** Reusable calculation engine (`TaxCalculationService`) and immutable snapshot contract (`TaxSnapshotData`) established for Phase 03/05 Order creation.
- **Payment Impact:** None.
- **Tax Impact:** Comprehensive product-specific tax engine with line-level `ROUND_HALF_UP` rounding and header sum of rounded line taxes established.
- **Accounting Impact:** Guarantees future invoices and tax journals reconcile exactly with line-level tax amounts without rounding discrepancies.
- **Data Migration Impact:** Created `tax_profiles` table (`2026_09_05_000006_create_tax_profiles_table.php`) and added foreign key `fk_products_tax_profile_id` on `products.tax_profile_id` (`2026_09_05_000007_add_foreign_key_to_products_tax_profile_id.php`).
- **Testing Impact:** Added `TaxCalculationServiceTest` (18 unit tests), `TaxProfileManagementTest` (7 feature tests), and `ProductTaxIntegrationTest` (5 feature tests). Total suite at 551 tests passing (3,247 assertions).
- **Deployment Impact:** Verified BCMath extension availability.
- **Approved By:** Principal Software Architect
- **Implementation Status:** Complete and verified.
- **Release/Commit Reference:** `FEAT-TAX-001`.

### CHANGE-004: FEAT-ORD-001 Salesman Order Creation Flow
- **Change ID:** `CHANGE-004`
- **Date:** September 5, 2026
- **Requested By:** Principal Software Architect
- **Request:** Implement the flagship Salesman Order Creation workflow, including customer selection with server-side salesman scoping, product catalogue browsing with signed S3 images, ephemeral cart with LocalStorage draft persistence, boundary pricing enforcement, BCMath line tax calculation (`ROUND_HALF_UP`), historical line snapshots, PostgreSQL sequence-backed `ORD-YYYY-XXXXXX` numbering, unique idempotency token with canonical fingerprint replay conflict safety, atomic `DB::transaction` checkout with deterministic ascending product locking (`SELECT FOR UPDATE`), and structured `ORDER_CREATED` audit event emission.
- **Reason:** Provide the core transactional order intake spine for salesman operations without client trust or downstream workflow entanglement.
- **Status:** `APPROVED & COMPLETED`
- **Priority:** `P0`
- **Affected PRD Requirements:** PRD §12 (Order Workflow), §25.1 (Salesman Ordering), §25.2 (Pricing Boundaries), §32 (Audit).
- **Affected Architecture:** Technical Architecture §5.1 (Ordering Domain Model), §5.2 (Historical Snapshots), §8.2 (Idempotency).
- **Affected Security:** Zero client trust for prices/taxes/totals, customer salesman scoping, authenticated actor derivation.
- **Affected Frontend:** Salesman Order Builder (`Create.tsx`, `Show.tsx`) across Desktop (1024-1920px), Tablet (768-1023px), and Mobile (320-430px).
- **Affected Tickets:** `FEAT-ORD-001` completed.
- **Inventory Impact:** Stock reservation and decrements explicitly out of scope and deferred to Phase 06.
- **Order Impact:** Initial order lifecycle state established (`status = SUBMITTED`, `fulfillment_status = UNALLOCATED`, `payment_status = UNPAID`, `delivery_status = PENDING_ASSIGNMENT`, `adjustment_status = NONE`).
- **Payment Impact:** Payments deferred to Phase 07.
- **Tax Impact:** Integrated authoritative `TaxCalculationService::calculateLineTax()` for snapshotting.
- **Accounting Impact:** Immutable transaction records created; GL posting deferred to Phase 09.
- **Data Migration Impact:** Created `orders` (`2026_09_05_000008_create_orders_table.php`) and `order_items` (`2026_09_05_000009_create_order_items_table.php`) tables with PostgreSQL sequence `order_number_seq` and check constraints.
- **Testing Impact:** Added 23 comprehensive feature tests (`SalesmanOrderCreationTest.php`). Full suite at 575 tests (3,391 assertions, 1 skipped) passing 100%.
- **Deployment Impact:** None.
- **Approved By:** Principal Software Architect
- **Implementation Status:** Complete and verified.
- **Release/Commit Reference:** `FEAT-ORD-001`.

### CHANGE-005: FEAT-ORD-002 Draft Order Persistence & Resumption
- **Change ID:** `CHANGE-005`
- **Date:** September 5, 2026
- **Requested By:** Principal Software Architect
- **Request:** Implement persistent working order drafts using the single `orders` and `order_items` aggregate with `orders.status = DRAFT`, nullable `order_number`, auto-generated UUID `draft_token`, and optimistic version locking (`version`). Support draft creation, line item/quantity/price synchronization, customer selection, draft discard with item cleanup, draft listing (`GET /salesman/orders/drafts`) with search and pagination, draft resumption (`GET /salesman/orders/drafts/{order}/edit`) into the 3-step Order Builder, stale master data warning banners, and atomic draft submission (`DRAFT -> SUBMITTED`) with full re-validation of pricing boundaries, lock-for-update product re-reading, exact line-level tax recalculation (`ROUND_HALF_UP`), and sequential `ORD-YYYY-XXXXXX` assignment.
- **Reason:** Allow salesmen to prepare, pause, resume, and modify incomplete orders across devices without consuming formal order numbers or triggering premature downstream workflows.
- **Status:** `APPROVED & COMPLETED`
- **Priority:** `P0`
- **Affected PRD Requirements:** PRD §12 (Order Workflow), §25.1 (Salesman Ordering), §25.4 (Draft Order Management).
- **Affected Architecture:** Technical Architecture §5.1 (Ordering Domain Model), §5.2 (Historical Snapshots), §8.2 (Concurrency & Optimistic Locking).
- **Affected Security:** Strict salesman scoping, authenticated actor derivation, zero client trust for draft totals/taxes, IDOR protection across draft endpoints.
- **Affected Frontend:** Drafts List (`Drafts.tsx`), Discard Confirmation Modal (`DiscardDraftModal.tsx`), Order Builder Draft Resumption & Autosave (`Create.tsx`), Sidebar Navigation (`AppLayout.tsx`).
- **Affected Tickets:** `FEAT-ORD-002` completed.
- **Inventory Impact:** Zero inventory reservation or decrement on draft save/update/discard; stock reserved only at downstream fulfillment.
- **Order Impact:** Single aggregate `status = DRAFT`, mutable working items, transitions to `status = SUBMITTED` on submit with formal order number.
- **Payment Impact:** Zero payment interaction on drafts.
- **Tax Impact:** Draft tax totals are preview-only; recalculated authoritatively on final submission via `TaxCalculationService`.
- **Accounting Impact:** Zero GL impact on drafts.
- **Data Migration Impact:** Added migration `2026_09_05_000010_update_orders_table_for_drafts.php` making `order_number` nullable, adding `draft_token` UUID NOT NULL UNIQUE, `version` integer NOT NULL DEFAULT 1, and composite index `(salesman_id, status, updated_at)`.
- **Testing Impact:** Added 15 comprehensive feature tests (`DraftOrderPersistenceTest.php`). Full suite at 590 tests (3,487 assertions, 1 skipped) passing 100%.
- **Deployment Impact:** None.
- **Approved By:** Principal Software Architect
- **Implementation Status:** Complete and verified.
- **Release/Commit Reference:** `FEAT-ORD-002`.

### CHANGE-006: FEAT-ORD-003 Order Line Quantity Stepper & Validation Controls
- **Change ID:** `CHANGE-006`
- **Date:** September 5, 2026
- **Requested By:** Principal Software Architect
- **Request:** Implement a unified, accessible, and robust quantity stepper component (`QuantityStepper.tsx`) across all three Order Builder surfaces: Product Catalogue Cards (`ProductOrderCard.tsx`), Quick Cart Drawer (`CartDrawer.tsx`), and Order Review & Confirmation Table (`OrderReviewStep.tsx`). Enforce positive integer domain bounds ($1 \le \text{quantity} \le 999,999$) with boundary disable states (`[-]` disabled at 1, `[+]` disabled at 999,999), intermediate typing buffer, Enter/blur normalization, $\ge 44\text{px}$ touch targets, and full ARIA semantics. Decouple quantity decrement from line removal (preserves explicit deletion via trash/remove controls). Integrate with 2-second debounced draft autosave and server-side FormRequest and Service layer validation.
- **Reason:** Provide seamless, ergonomic, and error-resistant quantity modification for salesmen across desktop, tablet, and mobile devices without risking invalid quantities or premature row deletions.
- **Status:** `APPROVED & COMPLETED`
- **Priority:** `P0`
- **Affected PRD Requirements:** PRD §12 (Order Workflow), §25.1 (Salesman Ordering), §25.3 (Line Quantity Validation).
- **Affected Architecture:** Technical Architecture §5.1 (Ordering Domain Model), §8.2 (Concurrency & Idempotency).
- **Affected Security:** Zero client trust; server-side FormRequest (`CreateOrderRequest`, `SaveOrderDraftRequest`) and `OrderService` enforce strict positive integer validation ($1 \le \text{quantity} \le 999,999$).
- **Affected Frontend:** `QuantityStepper.tsx`, `ProductOrderCard.tsx`, `CartDrawer.tsx`, `OrderReviewStep.tsx`, `Create.tsx`.
- **Affected Tickets:** `FEAT-ORD-003` completed.
- **Inventory Impact:** Zero inventory reservation or decrement; stock allocated downstream.
- **Order Impact:** Mutable line item quantity in draft and ephemeral cart; transitions to immutable snapshot on final order submission.
- **Payment Impact:** None.
- **Tax Impact:** Live UI preview recalculated; authoritative line taxes and totals computed via `TaxCalculationService`.
- **Accounting Impact:** None.
- **Data Migration Impact:** None (schema already supports integer quantities).
- **Testing Impact:** Added 10 comprehensive feature tests (`OrderQuantityValidationTest.php`). Full suite at 600 tests (3,539 assertions, 1 skipped) passing 100%.
- **Deployment Impact:** None.
- **Approved By:** Principal Software Architect
- **Implementation Status:** Complete and verified.
- **Release/Commit Reference:** `FEAT-ORD-003`.

### CHANGE-007: FEAT-ORD-004 Order Review, Line Tax Breakdown & Financial Summary
- **Change ID:** `CHANGE-007`
- **Date:** September 5, 2026
- **Requested By:** Principal Software Architect
- **Request:** Upgrade the Salesman Order Builder review step (`OrderReviewStep.tsx`) into a production-grade pre-submission financial review workspace. Expose transparent, line-by-line financial visibility: Product Name, SKU, Unit, Quantity Stepper, Unit Price, Taxable Amount, Tax Profile Code & Rate (e.g., `STD-825 (8.25%)` / `EXEMPT-0 (0.00%)`), Line Tax Amount, Line Total, and explicit Remove action. Provide structured Financial Summary (Subtotal / Taxable Amount, Estimated Line Taxes, Grand Total) with server-authoritative notice. Implement centralized client preview utilities (`financial.ts`) adhering to `ROUND_HALF_UP` parity without claiming binary-float authority. Implement mobile-tailored review card component (`OrderReviewLineCard.tsx`) with $\ge 44\text{px}$ touch targets to prevent broken horizontal scrolling on screens $\le 430\text{px}$. Maintain zero client financial authority (server BCMath and `OrderService` remain authoritative).
- **Reason:** Guarantee full commercial and tax transparency for wholesale salesmen prior to order submission, ensure mathematical parity between client previews and server transactions, and provide an accessible, responsive B2B review experience.
- **Status:** `APPROVED & COMPLETED`
- **Priority:** `P0`
- **Affected PRD Requirements:** PRD §11 (Product-Specific Tax), §12 (Ordering Workflow), §25.1 (Salesman Order Builder), §25.3 (Order Review & Financials).
- **Affected Architecture:** Technical Architecture §5.1 (Order & Line Item Snapshot Model), §8.2 (Concurrency & Idempotency), §23 (Tax Calculation Engine).
- **Affected Security:** Zero client trust (`RULE-SEC-002`); cost price (`cost_price`) hidden from salesman view; salesman customer scoping enforced; price boundary validation enforced ($0 < \text{min} \le \text{price} \le \text{mrp}$).
- **Affected Frontend:** `financial.ts`, `OrderReviewStep.tsx`, `OrderReviewLineCard.tsx`, `order.ts`, `Create.tsx`.
- **Affected Tickets:** `FEAT-ORD-004` completed.
- **Inventory Impact:** None (stock reserved post-approval downstream).
- **Order Impact:** Live preview of line taxes and totals; immutable historical snapshots persisted in `order_items` upon submission.
- **Payment Impact:** None.
- **Tax Impact:** Multi-line mixed tax rates calculated per line using BCMath `ROUND_HALF_UP`; order tax total equals exact sum of rounded line taxes ($\sum \text{line\_tax\_amount}$).
- **Accounting Impact:** None.
- **Data Migration Impact:** None (existing schema fully supports all snapshot fields).
- **Testing Impact:** Added 7 comprehensive feature tests (`OrderReviewFinancialSummaryTest.php`). Full suite at 607 tests (3,637 assertions, 1 skipped) passing 100%.
- **Deployment Impact:** None.
- **Approved By:** Principal Software Architect
- **Implementation Status:** Complete and verified.
### CHANGE-008: FEAT-ORD-005 Order Submission Idempotency Enforcement
- **Change ID:** `CHANGE-008`
- **Date:** September 5, 2026
- **Requested By:** Principal Software Architect
- **Request:** Harden order submission across direct order placement (`POST /salesman/orders`) and draft submission (`POST /salesman/orders/drafts/{id}/submit`) with dual-layer concurrency protection: advisory non-blocking Redis/cache lock (`Cache::lock("order_submission:{$actor->id}:{$idempotencyKey}", 10)`) with graceful fallback, authoritative PostgreSQL `UNIQUE(idempotency_key)` constraint backstop, and a specialized race collision recovery handler. On unique constraint collision (SQLSTATE `23505`), ensure transaction rollback, perform fresh committed read of the winner order, verify actor ownership (403 Forbidden for cross-salesman access), evaluate canonical fingerprint match (`doesOrderMatchDto`), return existing order on exact match (200/302), and throw HTTP 409 Conflict on payload mismatch. Enforce draft submission lock-for-update synchronization, sequence order number generation strictly on winning commits, and structured audit log uniqueness (exactly ONE `ORDER_CREATED` event per logical order; `ORDER_IDEMPOTENT_REPLAY` and `ORDER_IDEMPOTENCY_CONFLICT` on replays and conflicts).
- **Reason:** Guarantee absolute correctness and eliminate duplicate order placement, duplicate order numbering, duplicate `ORDER_CREATED` audit events, and 500 error crashes during concurrent submissions or rapid client retries under peak or degraded network conditions.
- **Status:** `APPROVED & COMPLETED`
- **Priority:** `P0`
- **Affected PRD Requirements:** PRD §12 (Ordering Workflow), §25.1 (Salesman Ordering), §25.3 (Order Submission & Idempotency).
- **Affected Architecture:** Technical Architecture §5.1 (Ordering Domain Model), §8.2 (Concurrency & Idempotency Architecture), §18 (Database & Transaction Integrity).
- **Affected Security:** Zero client trust (`RULE-SEC-002`); salesman scoping enforced; cross-salesman key reuse blocked with HTTP 403 without data leakage (`RULE-SEC-003`); IDOR prevention; PostgreSQL row locks (`SELECT FOR UPDATE`).
- **Affected Frontend:** `Create.tsx` (stable idempotency key state, disabled submission button, localStorage cleanup on confirmed commit).
- **Affected Tickets:** `FEAT-ORD-005` completed.
- **Inventory Impact:** Zero duplicate reservations or allocations.
- **Order Impact:** Single canonical order identity (`idempotency_key`), single sequence order number (`ORD-YYYY-XXXXXX`), exact replay returns existing order without mutation.
- **Payment Impact:** None.
- **Tax Impact:** None; line taxes snapshotted once upon winning transaction commit.
- **Accounting Impact:** None.
- **Data Migration Impact:** None (schema `orders.idempotency_key` is already `VARCHAR(64) UNIQUE NOT NULL`).
- **Testing Impact:** Added 17 comprehensive feature tests in `OrderSubmissionIdempotencyTest.php`. Full test suite at 624 tests (3,705 assertions, 1 skipped) passing 100%.
- **Deployment Impact:** None.
- **Approved By:** Principal Software Architect
- **Implementation Status:** Complete and verified.
- **Release/Commit Reference:** `FEAT-ORD-005`.

### CHANGE-009: FEAT-ORD-006 Salesman Order History & Multi-State Timeline
- **Change ID:** `CHANGE-009`
- **Date:** September 5, 2026
- **Requested By:** Principal Software Architect
- **Request:** Implement the flagship Salesman Order History Workspace (`GET /salesman/orders`) and enrich the Order Detail view (`GET /salesman/orders/{order}`) with an authentic, non-fabricated Multi-State Timeline. The workspace enforces 100% server-authoritative scoping (`orders.salesman_id = authenticated_actor.id` for Salesmen; Admin/Super Admin/Accountant global visibility), multi-column search (`order_number`, customer `name`, customer `code`), independent status dimension filtering (`status`, `fulfillment_status`, `payment_status`, `delivery_status`), date range filtering (`date_from`, `date_to`), bounded server-side pagination (15 items/page with query string preservation), and deterministic ordering (`submitted_at DESC, id DESC`). Draft orders are strictly excluded from history to preserve separation in `/salesman/orders/drafts`. Order detail view embeds `OrderTimeline` rendering verifiable milestones (`created`, `submitted`, `approved`, `cancelled`, `completed`) with exact persisted database timestamps and recorded actor identities, paired with the live workflow status snapshot across all 5 independent dimensions (`OrderStatusBadge`), without synthesizing fake intermediate transition timestamps.
- **Reason:** Provide sales representatives with an auditable, high-density, accessible, and responsive workspace to track customer orders across their entire lifecycle, while preserving zero-trust security and eliminating data leakage.
- **Status:** `APPROVED & COMPLETED`
- **Priority:** `P1`
- **Affected PRD Requirements:** PRD §12 (Ordering Workflow), §25.1 (Salesman Ordering & Order History).
- **Affected Architecture:** Technical Architecture §5.1 (Ordering Domain Model), §5.6 (Multi-Dimension Status Model), §18 (Database & Transaction Integrity).
- **Affected Security:** Zero client trust (`RULE-SEC-002`); salesman scoping enforced (`RULE-ORD-003`); direct IDOR blocked with HTTP 403 Forbidden (`RULE-SEC-003`); zero exposure of `cost_price` (`RULE-PRI-001`).
- **Affected Frontend:** `resources/js/Pages/Salesman/Orders/Index.tsx`, `resources/js/Pages/Salesman/Orders/Partials/*` (`OrderHistoryFilters.tsx`, `OrderHistoryTable.tsx`, `OrderHistoryCard.tsx`, `OrderStatusBadge.tsx`, `OrderTimeline.tsx`), `Show.tsx`, `AppLayout.tsx`, `order.ts`.
- **Affected Tickets:** `FEAT-ORD-006` completed.
- **Inventory Impact:** None.
- **Order Impact:** Standardized order history query and authentic milestone timeline model.
- **Payment Impact:** Payment status dimension displayed accurately as live snapshot.
- **Tax Impact:** None; line tax snapshots displayed immutably.
- **Accounting Impact:** None.
- **Data Migration Impact:** None (existing table indexes and columns fully support all query paths).
- **Testing Impact:** Added 22 comprehensive feature tests in `SalesmanOrderHistoryTest.php`. Full test suite at 646 tests (3,962 assertions, 1 skipped) passing 100%.
- **Deployment Impact:** None.
- **Approved By:** Principal Software Architect
- **Implementation Status:** Complete and verified.
- **Release/Commit Reference:** `FEAT-ORD-006`.

---

### CHANGE-010: FEAT-ORD-010 Admin Order Queue Framework
- **Change ID:** `CHANGE-010`
- **Date:** September 5, 2026
- **Requested By:** Principal Software Architect
- **Request:** Implement the flagship Admin Order Queue Workspace (`GET /admin/orders`) and authorization-safe detail inspection route (`GET /admin/orders/{order}`) across 8 operational queues: `New Orders`, `Needs Attention`, `Processing`, `Delivery`, `Adjustments`, `Completed`, `Cancelled`, and `All Orders`. Enforce 100% server-authoritative RBAC (`order.view` permission; `ADMIN`, `SUPER_ADMIN`, and `ACCOUNTANT` granted access; `SALESMAN` strictly denied with HTTP 403 Forbidden). Draft orders (`status = DRAFT`) are strictly excluded from all queues. Compute live queue badge counts in a single consolidated SQL aggregation query. Support multi-column parameterized search (`order_number`, customer `name`, customer `code`, salesman `name`), independent status dimension filtering (`status`, `fulfillment_status`, `payment_status`, `delivery_status`, `adjustment_status`), salesman and customer filtering, date range filtering (`date_from`, `date_to`), allowlisted sorting (`submitted_at`, `order_number`, `customer_name`, `grand_total`, `status`), and bounded 25/page pagination with query string preservation. Enforce zero leakage of sensitive data (`cost_price`, private S3 payment evidence photos/URLs). Provide responsive desktop dense table, tablet layout, and mobile cards ($\ge 44\text{px}$ touch targets, zero horizontal scrolling) adhering to WCAG 2.1 AA.
- **Reason:** Provide operations, logistics, customer service, and accounting with an auditable, high-density, real-time command center to monitor orders across all operational stages from placement to delivery and payment.
- **Status:** `APPROVED & COMPLETED`
- **Priority:** `P0`
- **Affected PRD Requirements:** PRD §12 (Ordering Workflow), §25.2 (Admin Order Operations), §25.3 (Operational Queues).
- **Affected Architecture:** Technical Architecture §5.1 (Ordering Domain Model), §5.6 (Multi-Dimension Status Model), §18 (Database & Transaction Integrity).
- **Affected Security:** Zero client trust (`RULE-SEC-001`, `RULE-SEC-002`); permission `order.view` verified; Salesman role denied from Admin routes with 403 Forbidden; zero exposure of `cost_price` or private cheque/money-order payment evidence (`RULE-PRI-001`, `RULE-PAY-002`).
- **Affected Frontend:** `resources/js/Pages/Admin/Orders/Index.tsx`, `resources/js/Pages/Admin/Orders/Partials/*` (`AdminOrderQueueTabs.tsx`, `AdminOrderQueueFilters.tsx`, `AdminOrderQueueTable.tsx`, `AdminOrderQueueCard.tsx`), `resources/js/types/order.ts`, `resources/js/Pages/Salesman/Orders/Show.tsx`, `resources/js/Layouts/AppLayout.tsx`.
- **Affected Tickets:** `FEAT-ORD-010` completed.
- **Inventory Impact:** None (reservation deferred to FEAT-ORD-012/FEAT-INV-003).
- **Order Impact:** Standardized 8-partition operational queue engine over the single PostgreSQL order source of truth.
- **Payment Impact:** Payment status monitored across all queues; private payment evidence withheld from queue payload.
- **Tax Impact:** None; line tax snapshots displayed immutably.
- **Accounting Impact:** Accountant granted read-only operational visibility via `order.view`.
- **Data Migration Impact:** None (existing table schema and indexes fully support all query paths).
- **Testing Impact:** Added 29 comprehensive automated tests in `AdminOrderQueueTest.php`. Full test suite at 675 tests (4,270 assertions, 1 skipped) passing 100%.
- **Deployment Impact:** None.
- **Approved By:** Principal Software Architect
- **Implementation Status:** Complete and verified.
- **Release/Commit Reference:** `FEAT-ORD-010`.

---

### CHANGE-011: FEAT-ORD-011 New Order Review Workspace
- **Change ID:** `CHANGE-011`
- **Date:** September 5, 2026
- **Requested By:** Principal Software Architect
- **Request:** Implement the dedicated New Order Review Workspace (`GET /admin/orders/{order}/review`) for administrative evaluation and readiness inspection of orders in reviewable states (`SUBMITTED`, `PENDING_APPROVAL`). Strictly enforce read-only boundary (no approval, rejection, hold mutation, inventory reservation, or payment verification state transitions). Enforce server-authoritative RBAC (`order.view` permission; `ADMIN` and `SUPER_ADMIN` granted readiness capabilities; `ACCOUNTANT` granted read-only access with `can.approve = false`, `can.reject = false`; `SALESMAN` strictly denied with HTTP 403 Forbidden). Fail-closed draft isolation returns HTTP 404 Not Found for drafts (`status = DRAFT`). Post-review orders (`APPROVED`, `COMPLETED`, `CANCELLED`, `REJECTED`) redirect to `/admin/orders/{order}` with informative notification. Implement bounded eager loading strictly omitting `cost_price` and private payment evidence. Derive deterministic review warnings server-side (`CUSTOMER_ON_HOLD` [blocker], `CUSTOMER_INACTIVE` [blocker], `CREDIT_LIMIT_EXCEEDED` [warning], `PRICE_OVERRIDE_PRESENT` [notice], `AGING_ORDER` [warning > 24h], `PRODUCT_INACTIVE` [blocker]). Separate historical order-time snapshots (`unit_price`, `ordered_quantity`, line tax snapshot, override metadata) from contextual catalog master properties. Render independent 5 status dimensions (`order`, `fulfillment`, `payment`, `delivery`, `adjustment`). Provide responsive 12-column desktop command layout, adaptive tablet views, and purpose-built mobile cards with >=44px touch targets.
- **Reason:** Provide administrators with an authoritative, risk-free pre-approval evaluation cockpit to audit line items, customer credit, authorized pricing overrides, and operational warnings before committing state mutations.
- **Status:** `APPROVED & COMPLETED`
- **Priority:** `P0`
- **Affected PRD Requirements:** PRD §12 (Ordering Workflow), §25.2 (Admin Order Operations), §25.4 (New Order Review Workspace).
- **Affected Architecture:** Technical Architecture §5.1 (Ordering Domain Model), §5.6 (Multi-Dimension Status Model), §18 (Database & Transaction Integrity).
- **Affected Security:** Strict server-side zero-trust security architecture (`RULE-SEC-001`, `RULE-SEC-002`); permission `order.view` verified; Salesman role denied from Admin routes with 403 Forbidden; zero exposure of `cost_price`, purchase cost, or private payment evidence photos/keys (`RULE-PRI-001`, `RULE-PAY-002`).
- **Affected Frontend:** `resources/js/Pages/Admin/Orders/Review.tsx`, `resources/js/Pages/Admin/Orders/Partials/*` (`ReviewActionHeader.tsx`, `ReviewAlerts.tsx`, `ReviewCustomerCard.tsx`, `ReviewItemsTable.tsx`, `ReviewItemsCards.tsx`, `ReviewFinancialSummary.tsx`), `resources/js/types/order.ts`, `resources/js/Pages/Admin/Orders/Partials/AdminOrderQueueTable.tsx`, `resources/js/Pages/Admin/Orders/Partials/AdminOrderQueueCard.tsx`, `resources/js/Pages/Salesman/Orders/Show.tsx`.
- **Affected Tickets:** `FEAT-ORD-011` completed.
- **Inventory Impact:** None (reservation deferred to FEAT-ORD-012/FEAT-INV-003).
- **Order Impact:** Standardized pre-approval review evaluation cockpit with fail-closed draft 404 isolation and post-review redirection.
- **Payment Impact:** None; high-level payment status rendered read-only; payment evidence withheld.
- **Tax Impact:** None; line tax snapshots displayed immutably; multi-line tax profiles aggregated deterministically.
- **Accounting Impact:** Accountant granted read-only operational evaluation visibility via `order.view`.
- **Data Migration Impact:** None (existing schema and snapshots fully support review workspace).
- **Testing Impact:** Added 26 comprehensive automated tests in `AdminOrderReviewTest.php`. Full test suite at 701 tests (4,557 assertions, 1 skipped) passing 100%.
- **Deployment Impact:** None.
- **Approved By:** Principal Software Architect
- **Implementation Status:** Complete and verified.
- **Release/Commit Reference:** `FEAT-ORD-011`.

---

### CHANGE-012: FEAT-ORD-012 Order Approval / Rejection Workflow with Audit
- **Change ID:** `CHANGE-012`
- **Date:** September 5, 2026
- **Requested By:** Principal Software Architect
- **Request:** Implement authoritative order approval and rejection mutations (`POST /admin/orders/{order}/approve`, `POST /admin/orders/{order}/reject`) governed by `OrderWorkflowService`. Enforce pessimistic locking via PostgreSQL row locking (`lockForUpdate()`) using deterministic lock hierarchy (Order -> Customer -> Order Items ordered by ID). Revalidate customer state (`ACTIVE` allowed, `ON_HOLD` / `INACTIVE` rejected with 422), product states (`ACTIVE` allowed, `INACTIVE` rejected with 422), and reviewable order states (`SUBMITTED`, `PENDING_APPROVAL`). On approval: establish order-level reservation (`fulfillment_status = RESERVED`, `reserved_quantity = fulfillableQuantity()`), transition `status = APPROVED`, record `approved_at` and `approved_by`. On rejection: validate mandatory trimmed reason (5-1000 chars), transition `status = REJECTED`, record `cancelled_at`, `cancelled_by`, and `cancellation_reason` in existing schema. Enforce RBAC: `permission:order.approve` and `permission:order.reject` for `ADMIN` and `SUPER_ADMIN`; strictly deny `ACCOUNTANT` (403), `SALESMAN` (403), `WAREHOUSE_MANAGER` (403), and `DELIVERY_PARTNER` (403). Ensure conflict detection (409 Conflict on stale/duplicate mutations). Emit structured post-commit application observability events (`commerce.order_event`) preserving separation between PostgreSQL transactions and application logging. Update `Review.tsx` Action Readiness Deck with accessible approval and rejection modals, soft warning confirmations, and real-time validation.
- **Reason:** Provide administrators with safe, auditable, and authoritative order approval and rejection capabilities while preventing concurrency races, duplicate mutations, and unverified inventory assumptions.
- **Status:** `APPROVED & COMPLETED`
- **Priority:** `P0` (Critical Business Mutation)
- **Affected PRD Requirements:** PRD §12 (Ordering Workflow), §13 (Order Lifecycle & Invariants), §25.2 (Admin Order Operations).
- **Affected Architecture:** Technical Architecture §5.1 (Ordering Domain Model), §5.6 (Multi-Dimension Status Model), §18 (Database & Transaction Integrity), §20 (Concurrency & Locking).
- **Affected Security:** Strict server-side zero-trust security architecture (`RULE-SEC-001`, `RULE-SEC-002`, `RULE-SEC-003`); direct manipulation of order ID prevented by route model binding, authentication, account status verification, and permission checks; non-admin roles denied with 403 Forbidden; zero exposure of `cost_price` or payment evidence.
- **Affected Frontend:** `resources/js/Pages/Admin/Orders/Review.tsx`, `resources/js/Pages/Admin/Orders/Partials/ApproveOrderModal.tsx`, `resources/js/Pages/Admin/Orders/Partials/RejectOrderModal.tsx`.
- **Affected Tickets:** `FEAT-ORD-012` completed.
- **Inventory Impact:** Order-level quantity reservation established (`orders.fulfillment_status = RESERVED`, `order_items.reserved_quantity = fulfillableQuantity()`). Physical stock ledger reservation (balances, movements, warehouse allocations) deferred to Phase 06 (`FEAT-INV-001..004`).
- **Order Impact:** Order transitions to `APPROVED` or `REJECTED` with audit trails (`approved_at`/`approved_by` or `cancelled_at`/`cancelled_by`/`cancellation_reason`). Lines and historical financial values preserved immutably.
- **Payment Impact:** None; payment status remains independent dimension (`UNPAID`).
- **Tax Impact:** None; line item tax snapshots immutable.
- **Accounting Impact:** No direct general ledger postings (financial settlement and invoice posting deferred to Phase 07).
- **Data Migration Impact:** None (zero schema migrations required; existing columns used).
- **Testing Impact:** Added 33 comprehensive automated tests in `AdminOrderApprovalTest.php` (17 tests) and `AdminOrderRejectionTest.php` (16 tests). Full test suite at 734 tests (4,687 assertions, 1 skipped) passing 100%.
- **Deployment Impact:** None.
- **Approved By:** Principal Software Architect
- **Implementation Status:** Complete and verified.
- **Release/Commit Reference:** `FEAT-ORD-012`.

---

### CHANGE-013: FEAT-ORD-013 Order Detail Master Workspace
- **Change ID:** `CHANGE-013`
- **Date:** September 5, 2026
- **Requested By:** Principal Software Architect
- **Request:** Upgrade canonical administrative order detail workspace at `GET /admin/orders/{order}` (`admin.orders.show`) into a read-oriented operational inspection command center. Replace the temporary salesman-rendered view with `resources/js/Pages/Admin/Orders/Show.tsx` and modular partials (`OrderDetailHeader.tsx`, `OrderDetailCustomerCard.tsx`, `OrderDetailItemsTable.tsx`, `OrderDetailItemsCards.tsx`, `OrderDetailFinancialSummary.tsx`, `OrderDetailOperationalCards.tsx`). Enforce strict read-only boundary (zero approval, rejection, cancellation, adjustment, payment, delivery, or inventory mutations inside detail page; reviewable orders provide contextual CTA to dedicated review workspace). Support all 8 current lifecycle states (`DRAFT`, `SUBMITTED`, `PENDING_APPROVAL`, `APPROVED`, `PROCESSING`, `COMPLETED`, `CANCELLED`, `REJECTED`) and preserve 5 independent status dimensions (`order`, `fulfillment`, `payment`, `delivery`, `adjustment`). Present immutable historical order-time snapshots (`product_name_snapshot`, `sku_snapshot`, `unit_snapshot`, `unit_price`, line totals, tax profiles) while visually distinguishing contextual current master data. Enforce quantity conservation tracking (`ordered`, `cancelled`, `reserved`, `fulfillable`, `picked`, `dispatched`, `delivered`, `returned`). Provide customer commercial context with formatted current account addresses explicitly labeled. Exclude all sensitive data (`cost_price`, purchase cost, private payment evidence, S3 object keys, presigned URLs, credentials). Enforce safe internal `backUrl` query parameter sanitization (blocking external hosts, protocol-relative URLs, and javascript: schemes). Implement strict server-side RBAC: `ADMIN` and `SUPER_ADMIN` have full detail access, `ACCOUNTANT` has read-only access, while `SALESMAN`, `WAREHOUSE_MANAGER`, and `DELIVERY_PARTNER` are strictly blocked with 403 Forbidden.
- **Reason:** Provide administrators and accountants with a unified, high-density, auditable operational inspection surface for reviewing orders, financial snapshots, quantity allocations, and lifecycle status across all order stages.
- **Status:** `APPROVED & COMPLETED`
- **Priority:** `P0` (Foundational Operational Surface)
- **Affected PRD Requirements:** PRD §12 (Ordering Workflow), §13 (Order Lifecycle & Invariants), §25.1 (Admin Order Detail Workspace), §25.2 (Admin Order Operations).
- **Affected Architecture:** Technical Architecture §5.1 (Ordering Domain Model), §5.6 (Multi-Dimension Status Model), §17 (Security Architecture & Zero Trust), §23 (Data Contracts & DTOs).
- **Affected Security:** Strict server-side zero-trust security architecture (`RULE-SEC-001`, `RULE-SEC-002`, `RULE-SEC-003`); direct IDOR protection via route model binding, authentication, account status validation, and role scoping; non-admin roles denied with 403 Forbidden; zero exposure of `cost_price` or payment evidence; safe `backUrl` sanitization preventing open redirects.
- **Affected Frontend:** `resources/js/Pages/Admin/Orders/Show.tsx`, `resources/js/Pages/Admin/Orders/Partials/OrderDetailHeader.tsx`, `resources/js/Pages/Admin/Orders/Partials/OrderDetailCustomerCard.tsx`, `resources/js/Pages/Admin/Orders/Partials/OrderDetailItemsTable.tsx`, `resources/js/Pages/Admin/Orders/Partials/OrderDetailItemsCards.tsx`, `resources/js/Pages/Admin/Orders/Partials/OrderDetailFinancialSummary.tsx`, `resources/js/Pages/Admin/Orders/Partials/OrderDetailOperationalCards.tsx`, `resources/js/types/order.ts`.
- **Affected Tickets:** `FEAT-ORD-013` completed.
- **Inventory Impact:** Read-only inspection of order-level quantity reservations; physical warehouse inventory allocation and stock balance ledgers deferred to Phase 06 (`FEAT-INV-001..004`).
- **Order Impact:** Canonical administrative inspection surface established across all 8 lifecycle states. Original line quantities, pricing snapshots, and tax profiles preserved immutably.
- **Payment Impact:** Read-only inspection of payment status and terms; payment capture, cheque/money order verification, and evidence preview deferred to Phase 07 (`FEAT-PAY-001..006`).
- **Tax Impact:** Read-only multi-line tax profile breakdown aggregated from immutable order item snapshots.
- **Accounting Impact:** Financial breakdown inspection; double-entry general ledger journal posting deferred to Phase 09.
- **Data Migration Impact:** None (zero schema migrations required; existing schema fully satisfies requirements).
- **Testing Impact:** Added 20 comprehensive automated feature tests in `AdminOrderDetailTest.php` (247 assertions). Full test suite at 754 tests (753 passed, 4,934 assertions, 1 skipped) passing 100%.
- **Deployment Impact:** None.
- **Approved By:** Principal Software Architect
- **Implementation Status:** Complete and verified.
- **Release/Commit Reference:** `FEAT-ORD-013`.

---

### CHANGE-014: FEAT-ALLOC-001 Order Item Quantity Allocation Model
- **Change ID:** `CHANGE-014`
- **Date:** September 5, 2026
- **Requested By:** Principal Software Architect
- **Request:** Implement canonical Order Item Quantity Allocation model and persistence architecture for Phase 06 (`Operations: Allocation, Adjustments & Inventory`). Create dedicated `order_item_allocations` table and `App\Models\OrderItemAllocation` model while preserving existing denormalized operational rollups on `order_items`. Integrate canonical baseline allocation creation into `OrderWorkflowService::approveOrder()` inside existing atomic transactions without double-counting reservations. Provide `App\Services\Allocation\OrderAllocationService` with deterministic lock ordering (Order -> OrderItems ASC -> OrderItemAllocations ASC), cross-row conservation enforcement ($\sum \text{allocated} \le \text{fulfillable}$), unallocated calculations, and safe backfill for historical approved orders.
- **Reason:** Core operational capability to support partial allocations, post-order adjustments, and fulfillment tracking without mutating original commercial order quantities, price snapshots, or tax snapshots.
- **Status:** `APPROVED & COMPLETED`
- **Priority:** `P0` (Phase 06 Foundation)
- **Affected PRD Requirements:** §13 (Order Processing & Reservation Invariants), §14 (Adjustments & Non-Destructive Quantity Conservation), §39.2 (Central Distribution Warehouse).
- **Affected Architecture:** §14 (Allocation & Operational Models), §15 (Order State Machine & Reservation Lifecycle), §18 (Pessimistic Row Locking Architecture).
- **Affected Security:** Strict server authority; all calculations server-side; zero public CRUD endpoints; RBAC-gated inspection; structured post-commit observability (`commerce.allocation_event`) excluding secrets and cost prices.
- **Affected Frontend:** Admin Order Detail (`OrderDetailItemsTable.tsx`, `OrderDetailItemsCards.tsx`, `order.ts`) enriched with discrete allocation data, allocated/unallocated metrics, and allocation summary.
- **Affected Tickets:** `FEAT-ALLOC-001` completed. Unlocks `FEAT-ALLOC-002` and Phase 06 adjustment workflows.
- **Inventory Impact:** Operational order allocation model established; physical warehouse inventory balances and stock movement ledgers deferred to `FEAT-INV-001..004`.
- **Order Impact:** Orders in `APPROVED` and `PROCESSING` states support allocations; `DRAFT`, `SUBMITTED`, `REJECTED`, `CANCELLED`, `COMPLETED` strictly blocked. Baseline allocations created atomically upon order approval.
- **Payment Impact:** None; financial immutability strictly preserved.
- **Tax Impact:** None; tax snapshots and totals remain immutable.
- **Accounting Impact:** None; financial balances and GL lines untouched.
- **Data Migration Impact:** Added migration `2026_09_05_000011_create_order_item_allocations_table.php` with foreign keys to `orders`, `order_items`, `products`, `users`, indexes, and row-local PostgreSQL CHECK constraints.
- **Testing Impact:** Added 19 comprehensive feature tests in `OrderItemAllocationModelTest.php` (123 assertions). Full test suite at 773 tests (771 passed, 5,057 assertions, 2 skipped) passing 100%. Clean TypeScript and Vite builds.
- **Deployment Impact:** None.
- **Approved By:** Principal Software Architect
- **Implementation Status:** Complete and verified.
- **Release/Commit Reference:** `FEAT-ALLOC-001`.

---

### CHANGE-015: FEAT-ALLOC-002 Allocation Validation & Mathematical Constraints
- **Change ID:** `CHANGE-015`
- **Date:** September 5, 2026
- **Requested By:** Principal Software Architect
- **Request:** Harden mathematical conservation laws and domain validation constraints on quantity allocation (`FEAT-ALLOC-002`). Implement strict conservation law ($\text{ordered} = \text{cancelled} + \text{fulfillable}$), active allocation sum conservation ($\sum \text{allocated}_{\text{active}} \le \text{fulfillable}$), unallocated capacity calculation ($\max(0, \text{fulfillable} - \sum \text{allocated}_{\text{active}})$), and strict fulfillment progression constraints ($0 \le \text{returned} \le \text{delivered} \le \text{dispatched} \le \text{picked} \le \text{allocated}$ and $0 \le \text{reserved} \le \text{allocated}$). Tighten PostgreSQL database CHECK constraints on `order_item_allocations` (`dispatched <= picked` and `delivered <= dispatched`). Provide non-destructive soft-state operations for releasing and cancelling allocations with unallocated restoration. Provide centralized authoritative rollup synchronization from allocation rows to `order_items`. Implement collision-free sequence generation by deriving max integer suffix under row locks. Introduce pre-adjustment reduction capacity check `canReduceFulfillableQuantity()`.
- **Reason:** Guarantee mathematical correctness and prevent data drift, race conditions, over-allocation, and financial mutation across all future picking, packing, dispatching, and order adjustment workflows.
- **Status:** `APPROVED & COMPLETED`
- **Priority:** `P0` (High/Critical Data Integrity)
- **Affected PRD Requirements:** §13 (Order Processing & Reservation Invariants), §14 (Adjustments & Non-Destructive Quantity Conservation), §39.2 (Central Distribution Warehouse).
- **Affected Architecture:** §14 (Allocation & Operational Models), §15 (Order State Machine & Reservation Lifecycle), §18 (Pessimistic Row Locking Architecture).
- **Affected Security:** Zero client authority; server-enforced validation via `OrderAllocationValidationService`; optimistic and pessimistic locking; structured post-commit observability (`commerce.allocation_event`).
- **Affected Frontend:** Admin Order Detail projection and summary compatibility preserved without UI breakage.
- **Affected Tickets:** `FEAT-ALLOC-002` completed. Unlocks `UI-003` and Phase 06 adjustment workflows (`FEAT-ADJ-001+`).
- **Inventory Impact:** Mathematical boundaries locked; physical inventory balances and warehouse ledgers deferred to `FEAT-INV-001..004`.
- **Order Impact:** Order lifecycle restrictions strictly enforced (`APPROVED`/`PROCESSING` allowed; `DRAFT`/`SUBMITTED`/`REJECTED`/`CANCELLED`/`COMPLETED` blocked 409).
- **Payment Impact:** None; financial immutability strictly preserved.
- **Tax Impact:** None; tax snapshots and totals remain immutable.
- **Accounting Impact:** None; general ledger lines and balances untouched.
- **Data Migration Impact:** Added migration `2026_09_05_000012_tighten_order_item_allocations_progression_constraints.php` with PostgreSQL CHECK constraints for `dispatched <= picked` and `delivered <= dispatched`.
- **Testing Impact:** Added 25 comprehensive feature tests across `AllocationValidationTest.php` and `AllocationConcurrencyTest.php`. Full test suite at 798 tests (795 passed, 5,161 assertions, 3 skipped) passing 100%. TypeScript and Vite build clean.
- **Deployment Impact:** None.
- **Approved By:** Principal Software Architect
- **Implementation Status:** Complete and verified.
- **Release/Commit Reference:** `FEAT-ALLOC-002`.

### CHANGE-016: FEAT-ADJ-001 Order Adjustment Request Flow
- **Change ID:** `CHANGE-016`
- **Date:** September 5, 2026
- **Requested By:** Principal Software Architect
- **Request:** Implement the authoritative post-submission order adjustment request flow (`FEAT-ADJ-001`). Allow authorized roles (`SUPER_ADMIN`, `ADMIN`, `SALESMAN`, `WAREHOUSE_MANAGER`) to initiate non-destructive quantity reduction requests on orders in `SUBMITTED`, `PENDING_APPROVAL`, `APPROVED`, `PROCESSING` statuses. Enforce non-destructive transactional history (`order_adjustment_items.adjustment_id -> order_adjustments` uses `restrictOnDelete()`). Enforce single open request per order via PostgreSQL partial unique index `idx_order_adjustments_single_open` and row locking. Classify line reductions into Case A (unallocated only) and Case B (allocation-impacting with `affected_allocation_quantity`). Persist immutable request snapshots, compute financial projections using BCMath and `TaxCalculationService::roundHalfUp` without mutating baseline order/item financials or inventory allocations. Support deterministic idempotency replay (same payload => replay; conflicting payload => 409). Provide requester cancellation/withdrawal restoring `orders.adjustment_status = 'NONE'`. Implement responsive Request Adjustment modal and Pending Adjustment banner.
- **Reason:** Guarantee commercial history preservation (`RULE-DOM-001`, `RULE-ORD-002`), allocation conservation, and transactional concurrency safety for wholesale order modifications before administrative approval.
- **Status:** `APPROVED & COMPLETED`
- **Priority:** `P0` (High/Critical Transactional Workflow)
- **Affected PRD Requirements:** §14 (Order Adjustment Framework & Non-Destructive History), §15 (Single Open Request Invariant), §16 (Snapshot Immutability).
- **Affected Architecture:** §14 (Allocations & Adjustments), §15 (Order State Machine & Adjustment Status), §18 (Deterministic Lock Hierarchy: Order -> Items ASC -> Allocations ASC -> Adjustments).
- **Affected Security:** Strict server-side RBAC (`Permission::ORDER_ADJUST_REQUEST`); resource scoping (Salesmen restricted to assigned accounts; Warehouse Managers restricted to approved/processing orders); withdrawal authorization; structured post-commit event logging (`commerce.order_adjustment_event`).
- **Affected Frontend:** `RequestAdjustmentModal.tsx`, `PendingAdjustmentBanner.tsx`, `OrderDetailHeader.tsx`, `Admin/Orders/Show.tsx`, `Salesman/Orders/Show.tsx`.
- **Affected Tickets:** `FEAT-ADJ-001` completed. Unlocks `FEAT-ADJ-002: Adjustment Review Workspace`.
- **Inventory Impact:** Zero physical inventory or allocation mutation during request creation or withdrawal (`releaseAllocation`/`cancelAllocation` strictly not invoked).
- **Order Impact:** Orders update `adjustment_status = 'REQUESTED'` upon submission and reset to `NONE` (or `APPLIED`) upon withdrawal. Financial totals and line item quantities remain 100% unmutated.
- **Payment Impact:** None; actual credit notes and invoice adjustments deferred to `FEAT-ADJ-004`.
- **Tax Impact:** Projected tax reduction calculated using authoritative rounding semantics; original order tax totals remain permanently immutable.
- **Accounting Impact:** None; general ledger adjustments strictly deferred to adjustment application in `FEAT-ADJ-004`.
- **Data Migration Impact:** Added migration `2026_09_05_000013_create_order_adjustments_tables.php` creating `order_adjustments` and `order_adjustment_items` with PostgreSQL partial unique index and check constraints.
- **Testing Impact:** Added 30 automated tests across `OrderAdjustmentRequestTest.php`, `OrderAdjustmentConcurrencyTest.php`, and `OrderAdjustmentPostgresConstraintTest.php`. Full test suite at 828 tests (821 passed, 5,260 assertions, 7 skipped) passing 100%. TypeScript and Vite build clean.
- **Deployment Impact:** None.
- **Approved By:** Principal Software Architect
- **Implementation Status:** Complete and verified.
- **Release/Commit Reference:** `FEAT-ADJ-001`.

### CHANGE-017: FEAT-ADJ-002 Administrative Adjustment Review Workspace
- **Change ID:** `CHANGE-017`
- **Date:** September 6, 2026
- **Requested By:** Principal Software Architect
- **Request:** Implement the dedicated Administrative Adjustment Review Workspace and Queue (`FEAT-ADJ-002`). Establish dual-entry navigation via `/admin/adjustments` (dedicated review queue with status/impact/reason filters, search, and allowlisted sorting) and `/admin/orders/{order}/adjustments/{adjustment}/review` (dedicated detail review workspace). Maintain strict read-only boundary (no approval, rejection, application, reversal, or financial mutation). Enforce canonical active allocation definition (excluding both `CANCELLED` and `RELEASED` states) and progression math without picked+dispatched double counting (`returned <= delivered <= dispatched <= picked <= allocated`). Deliver pure `OrderAdjustmentReviewService` to separate immutable historical request snapshots from live order state evaluations and detect discrepancies/stale states (`READY`, `WARNING_ALLOCATION`, `WARNING_PICKED_ENCROACHMENT`, `STALE`, `CONFLICTED`, `INELIGIBLE_LIFECYCLE`, `TERMINAL_REQUEST`). Resolve Option A reviewer permissions denying Warehouse Managers, Salesmen, and Delivery Partners while authorizing Super Admin, Admin, and Accountant under `Permission::ORDER_ADJUST_REVIEW`.
- **Reason:** Provide administrators and accountants with authoritative, non-destructive situational awareness over pending order quantity adjustments prior to executing atomic mutations.
- **Status:** `APPROVED & COMPLETED`
- **Priority:** `P0` (High/Critical Review Workflow)
- **Affected PRD Requirements:** §14 (Adjustment Review), §15 (Snapshot Fidelity vs Live Evaluation), §16 (Allocation Impact Inspection).
- **Affected Architecture:** §14 (Review DTOs & Services), §15 (Stale State Resolution), §18 (Dual-Entry Routing & Anti-IDOR Scope Validation).
- **Affected Security:** Route protection via `permission:order.adjust.review`; strict IDOR validation (`adjustment->order_id === order->id`); role authorization for Super Admin, Admin, and Accountant; complete denial of Salesman, Warehouse Manager (Option A), and Delivery Partner; zero audit noise for normal page viewing.
- **Affected Frontend:** `resources/js/Pages/Admin/Adjustments/Index.tsx`, `resources/js/Pages/Admin/Adjustments/Review.tsx`, `resources/js/Layouts/AppLayout.tsx`, `resources/js/Pages/Admin/Orders/Partials/PendingAdjustmentBanner.tsx`, `resources/js/types/order.ts`.
- **Affected Tickets:** `FEAT-ADJ-002` completed. Unlocks `FEAT-ADJ-003: Adjustment Approval / Rejection Workflow`.
- **Inventory Impact:** Zero inventory or allocation modification (`releaseAllocation`/`cancelAllocation` strictly not invoked).
- **Order Impact:** Zero order, item, or total mutation. Pure read-only inspection.
- **Payment Impact:** None.
- **Tax Impact:** Stored request snapshots displayed unchanged; live evaluation re-runs authoritative `TaxCalculationService::normalizeRate` and `roundHalfUp` without mutating order snapshots.
- **Accounting Impact:** None.
- **Data Migration Impact:** Zero database schema modifications (22 existing migration files unchanged).
- **Testing Impact:** Added 26 automated tests across `AdminAdjustmentQueueTest.php`, `AdminAdjustmentReviewDetailTest.php`, `AdminAdjustmentStaleStateTest.php`, and `AdminAdjustmentSecurityTest.php`. Full repository test suite at 854 tests (847 passed, 5,538 assertions, 7 skipped) passing 100%. TypeScript and Vite build clean.
- **Deployment Impact:** None.
- **Approved By:** Principal Software Architect
- **Implementation Status:** Complete and verified.
- **Release/Commit Reference:** `FEAT-ADJ-002`.

---

### CHANGE-018: Adjustment Approval & Rejection Engine (FEAT-ADJ-003)
- **Change ID:** `CHANGE-018`
- **Date:** September 6, 2026
- **Requested By:** Principal Software Architect
- **Request:** Implement the authoritative dual-control **Adjustment Approval & Rejection Engine** (`FEAT-ADJ-003`). Establish deterministic deadlock-free row locking (`orders -> order_items ASC -> order_item_allocations ASC -> order_adjustments`), strict maker-checker segregation of duties preventing administrators from self-approving or self-rejecting requests they submitted, Super Admin emergency override requiring explicit 10-1000 character justification and dedicated `ADJUSTMENT_EMERGENCY_OVERRIDE` audit event, live state revalidation under lock (rejecting stale versions, fulfillable quantity conflicts, picking encroachments, and terminal states with 409 Conflict), deterministic duplicate decision semantics (guarded transitions returning 409 on subsequent attempts rather than idempotent replays), Case B allocation impact mandatory acknowledgment (`acknowledge_allocation_impact: true`), order `adjustment_status` maintenance (`REQUESTED` on approved, `NONE` on rejected unless earlier adjustment was `APPLIED`), strict read/decision-only boundary preserving quantities, allocations, and order financials without premature mutation (application reserved for `FEAT-ADJ-004`), accessible interactive modals (`ApproveAdjustmentModal`, `RejectAdjustmentModal`) in `Review.tsx`, and comprehensive automated testing.
- **Reason:** Guarantee transactional correctness, maker-checker compliance, allocation integrity, and financial immutability during adjustment decisioning prior to downstream execution in FEAT-ADJ-004.
- **Status:** `APPROVED & COMPLETED`
- **Priority:** `P0` (High/Critical Transactional Decision Boundary)
- **Affected PRD Requirements:** §14 (Approval & Rejection Authority), §15 (Maker-Checker Segregation), §16 (Live State Revalidation & Concurrency).
- **Affected Architecture:** §14 (Workflow Services), §15 (Lock Ordering & Deadlock Prevention), §18 (Guarded State Transitions & Dual-Control).
- **Affected Security:** Governed by `Permission::ORDER_ADJUST_APPROVE` (`order.adjust.approve`) for both approval and rejection; hard maker-checker server rejection for regular Admins; Super Admin emergency override validation; complete denial of Accountant, Salesman, Warehouse Manager, and Delivery Partner (403); structured post-commit audit logging.
- **Affected Frontend:** `resources/js/Pages/Admin/Adjustments/Review.tsx`, `resources/js/Pages/Admin/Adjustments/Partials/ApproveAdjustmentModal.tsx`, `resources/js/Pages/Admin/Adjustments/Partials/RejectAdjustmentModal.tsx`.
- **Affected Tickets:** `FEAT-ADJ-003` completed. Unlocks `FEAT-ADJ-004: Atomic Adjustment Application Engine`.
- **Inventory Impact:** Zero inventory or stock balance mutations (`inventory_movements` untouched; allocation rows preserved).
- **Order Impact:** `orders.adjustment_status` maintained as `REQUESTED` upon approval (awaiting FEAT-ADJ-004 application) and reset to `NONE` (or preserved `APPLIED`) upon rejection. Zero mutation of order quantities (`cancelled_quantity`, `reserved_quantity`) or financials (`subtotal`, `tax_total`, `grand_total`).
- **Payment Impact:** None.
- **Tax Impact:** Stored projected reductions remain immutable snapshots. Order and line tax figures remain unchanged.
- **Accounting Impact:** None.
- **Data Migration Impact:** Zero database schema modifications (22 existing migration files unchanged).
- **Testing Impact:** Added 41 automated tests across `AdminAdjustmentApprovalTest.php`, `AdminAdjustmentRejectionTest.php`, `AdminAdjustmentMakerCheckerTest.php`, and `AdminAdjustmentApprovalConcurrencyTest.php`. Full repository test suite at 895 tests (888 passed, 5,685 assertions, 7 skipped) passing 100%. TypeScript and Vite build clean.
- **Deployment Impact:** None.
- **Approved By:** Principal Software Architect
- **Implementation Status:** Complete and verified.
- **Release/Commit Reference:** `FEAT-ADJ-003`.

---

## 3. Template for Future Change Requests

```markdown
### CHANGE-XXX: [Descriptive Title]
- **Change ID:** `CHANGE-XXX`
- **Date:** YYYY-MM-DD
- **Requested By:** [Client / Product Owner / Technical Lead]
- **Request:** [Concise description of the requested modification]
- **Reason:** [Business or technical justification]
- **Status:** [`REQUESTED` | `UNDER_REVIEW` | `APPROVED` | `REJECTED` | `IMPLEMENTED`]
- **Priority:** [`P0` | `P1` | `P2` | `P3`]
- **Affected PRD Requirements:** [Sections in Document 01 affected]
- **Affected Architecture:** [Sections in Document 02 affected]
- **Affected Security:** [Sections in Document 03 affected]
- **Affected Frontend:** [Screens / components in Document 04 affected]
- **Affected Tickets:** [Existing tickets modified or new tickets created]
- **Inventory Impact:** [Impact on reservations, stock states, or movements]
- **Order Impact:** [Impact on order lifecycle, item allocations, or adjustments]
- **Payment Impact:** [Impact on payment methods, evidence, or verification]
- **Tax Impact:** [Impact on line-item tax calculation or snapshots]
- **Accounting Impact:** [Impact on chart of accounts or journal entries]
- **Data Migration Impact:** [Database schema changes, data backfill requirements]
- **Testing Impact:** [New tests or regression suites required]
- **Deployment Impact:** [Infrastructure or CI/CD adjustments required]
- **Approved By:** [Client / Architect Name]
- **Implementation Status:** [Pending / In-Progress / Complete]
- **Release/Commit Reference:** [Git commit hash or release tag]
```
