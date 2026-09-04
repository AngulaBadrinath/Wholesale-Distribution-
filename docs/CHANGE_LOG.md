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
