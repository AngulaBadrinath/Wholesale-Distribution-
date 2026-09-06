# DECISION_LOG.md — Durable Architecture & Business Decision Memory

## Wholesale Distribution Management System

**Document Version:** 1.0  
**Effective Date:** September 2026  
**Status:** Authoritative Repository Decision Log  
**Rule:** Every major architectural, domain, security, or product decision must be recorded here with complete context, rationale, alternatives considered, and consequences.

---

## Confirmed Architectural & Domain Decisions

### DEC-001: Configurable Application & Product Identity
- **Date:** September 2026
- **Status:** `CONFIRMED`
- **Decision:** The working title "Wholesale Distribution Management System" is a temporary placeholder. Application branding, product name, and legal entity details must be stored as configurable database settings.
- **Context:** The client may rebrand or license the software under a distinct corporate identity prior to commercial launch.
- **Reason:** Prevents expensive refactoring across routes, database schemas, and frontend UI templates when the final product name is chosen.
- **Alternatives Considered:**
  - *Hard-coding "Wholesale Distribution System" throughout codebase:* Rejected due to high technical debt during future rebranding.
- **Chosen Approach:** Provide dynamic system configuration (`FEAT-SYS-001`) loaded via Inertia shared props.
- **Affected Domains:** System Settings, UI Shells, Invoice Documents.
- **Affected Documents:** PRD §0.2, Technical Architecture §0.3.
- **Affected Tickets:** `FEAT-SYS-001`, `FEAT-SYS-002`, `FEAT-DOC-002`.
- **Consequences:** All header bars, login screens, email footers, and invoices must read dynamic company metadata.
- **Future Extension Notes:** Allows multi-tenant white-labeling if promoted to scope in future phases.

---

### DEC-002: Modular Monolith Architecture
- **Date:** September 2026
- **Status:** `CONFIRMED`
- **Decision:** Build the backend as a single Laravel 13 modular monolith rather than microservices or separate portal backends.
- **Context:** Initial customer scale is 500–700 wholesale accounts. Primary complexity lies in transactional integrity, pricing, and accounting.
- **Reason:** Microservices introduce distributed transactions, network latency, deployment overhead, and eventual consistency issues that endanger transactional financial correctness.
- **Alternatives Considered:**
  - *Microservices architecture:* Rejected as premature over-engineering that drastically increases solo developer complexity.
  - *Separate applications for Admin, Salesman, and Delivery:* Rejected because all portals share the identical underlying transaction core.
- **Chosen Approach:** Modular monolith with strict internal domain boundaries, application services, and domain events.
- **Affected Domains:** Architecture, Infrastructure, All Modules.
- **Affected Documents:** Technical Architecture §1, §3.
- **Affected Tickets:** `TECH-FOUND-001`, `DEPLOY-002`.
- **Consequences:** Single deployment pipeline, straightforward ACID database transactions, lower hosting costs.
- **Future Extension Notes:** Well-defined module boundaries allow extracting high-traffic components (e.g., Delivery API) into microservices later if scale demands.

---

### DEC-003: Shared Transactional Core Across All Portals
- **Date:** September 2026
- **Status:** `CONFIRMED`
- **Decision:** Salesman, Admin, Warehouse, Delivery, and Accounting portals operate on one shared order/transaction entity in PostgreSQL.
- **Context:** Different roles need different perspectives on the same commercial transaction.
- **Reason:** Eliminates data duplication, synchronization lag, and conflicting order states.
- **Alternatives Considered:**
  - *Portal-specific operational data stores with synchronization queues:* Rejected due to synchronization failure risks and reconciliation nightmares.
- **Chosen Approach:** One PostgreSQL database; portals are role-specific view layers (`/admin/*`, `/salesman/*`, `/delivery/*`) accessing shared application services.
- **Affected Domains:** Orders, Inventory, Delivery, Payments, Accounting.
- **Affected Documents:** PRD §3.1, §48; Technical Architecture §2.
- **Affected Tickets:** `FEAT-ORD-001`, `FEAT-ORD-010`, `FEAT-DEL-002`.
- **Consequences:** State transitions must be atomic and protect all operational perspectives.

---

### DEC-004: Quantity-Based Allocation Model
- **Date:** September 2026
- **Status:** `CONFIRMED`
- **Decision:** Order fulfillment operates on an item-level quantity allocation model tracking `ordered`, `cancelled`, `reserved`, `picked`, `dispatched`, `delivered`, and `returned` quantities.
- **Context:** Wholesale orders frequently experience partial stockouts, damaged units, split shipments, and partial returns.
- **Reason:** Prevents overwriting original commercial intent while giving accurate real-time fulfillable figures.
- **Alternatives Considered:**
  - *Single mutable `quantity` field on order lines:* Rejected because it irreversibly destroys historical order records during partial cancellations.
- **Chosen Approach:** Dedicated quantity fields and allocation records (`order_item_allocations`).
- **Affected Domains:** Orders, Inventory, Delivery.
- **Affected Documents:** PRD §13; Technical Architecture §14.
- **Affected Tickets:** `FEAT-ALLOC-001`, `FEAT-ALLOC-002`.
- **Consequences:** Application math must enforce mathematical conservation ($\text{cancelled} + \text{fulfillable} = \text{ordered}$).

---

### DEC-005: Order Adjustment Framework
- **Date:** September 2026
- **Status:** `CONFIRMED`
- **Decision:** Post-order modifications must use explicit `order_adjustments` and `order_adjustment_items` records rather than in-place database updates.
- **Context:** Warehouse damage or customer cancellation requests require adjusting order lines after order placement.
- **Reason:** Preserves an auditable record of who requested the change, who approved it, the reason, and the exact financial/tax impact.
- **Alternatives Considered:**
  - *Directly updating `order_items`:* Rejected as destructive to financial history and non-compliant with audit standards.
  - *Cancelling the entire order and creating a new one:* Rejected as disruptive to customer relationship and warehouse picking workflow.
- **Chosen Approach:** First-class Order Adjustment domain supporting item cancellation, quantity reductions, and price overrides.
- **Affected Domains:** Orders, Inventory, Tax, Invoicing, Receivables.
- **Affected Documents:** PRD §14, §15; Technical Architecture §15.
- **Affected Tickets:** `FEAT-ADJ-001` through `FEAT-ADJ-006`.
- **Consequences:** Orders maintain both original baseline figures and current adjusted net positions.

---

### DEC-006: Product-Specific Line-Level Tax Architecture
- **Date:** September 2026
- **Status:** `CONFIRMED`
- **Decision:** Tax is configured per product and calculated/snapshotted on individual order lines rather than applying a single order-wide percentage.
- **Context:** Wholesale catalogs contain items with varying tax liabilities (e.g., taxable hardware, tax-exempt grocery/feed, standard rate goods).
- **Reason:** Accurate tax compliance requires calculating tax per line item.
- **Alternatives Considered:**
  - *Single flat order-level tax rate:* Rejected because it violates wholesale tax rules for mixed-category orders.
- **Chosen Approach:** `product_tax_profiles` linked to products; order lines snapshot tax rate, taxable amount, and tax amount at transaction time.
- **Affected Domains:** Products, Tax, Orders, Invoices.
- **Affected Documents:** PRD §11; Technical Architecture §23.
- **Affected Tickets:** `FEAT-TAX-001`, `FEAT-ORD-004`, `FEAT-DOC-001`.
- **Consequences:** Adjustments must recalculate tax liabilities proportionally using each line's snapshotted rate.

---

### DEC-007: V1 Payment Methods (Cash, Cheque, Money Order)
- **Date:** September 2026
- **Status:** `CONFIRMED`
- **Decision:** V1 strictly supports `CASH`, `CHEQUE`, and `MONEY_ORDER`. Online credit card gateways are deferred.
- **Context:** Core B2B wholesale distribution in the client's sector relies heavily on physical payment collection upon delivery or net terms.
- **Reason:** Meets the client's confirmed operational baseline without introducing unnecessary payment gateway complexities in V1.
- **Alternatives Considered:**
  - *Integrating Stripe / Authorize.net immediately:* Rejected to keep V1 focused on physical field logistics and offline payment workflows.
- **Chosen Approach:** Extensible `payments` table with method enum and dedicated handling workflows.
- **Affected Domains:** Payments, Receivables, Accounting.
- **Affected Documents:** PRD §19.1; Technical Architecture §20.
- **Affected Tickets:** `FEAT-PAY-001` through `FEAT-PAY-004`.
- **Consequences:** Reconciliation and verification workflows are mandatory before payment is considered confirmed.
- **Future Extension Notes:** Payment domain architecture is polymorphic to accommodate online gateways or ACH transfers in future phases.

---

### DEC-008: Mandatory JPEG Payment Evidence Uploads
- **Date:** September 2026
- **Status:** `CONFIRMED`
- **Decision:** Cheque and Money Order payment entries **MANDATE** JPEG photo uploads stored in private Amazon S3 buckets.
- **Context:** Field salesmen and delivery drivers collect physical cheques/money orders. Visual evidence is critical for fraud prevention and accounting verification.
- **Reason:** Provides immediate physical proof to accountants prior to bank deposit.
- **Alternatives Considered:**
  - *Optional uploads:* Rejected; client mandates visual verification.
  - *Storing images directly in PostgreSQL BLOB columns:* Rejected due to database bloat and performance degradation.
- **Chosen Approach:** Client uploads file; server validates JPEG magic bytes (`\xFF\xD8\xFF`); stores privately in S3; generates temporary presigned URLs for preview.
- **Affected Domains:** Payments, Security, Storage.
- **Affected Documents:** PRD §19.4, §19.5, §19.7; Technical Architecture §22; Security & Access §8.
- **Affected Tickets:** `FEAT-PAY-005`, `FEAT-PAY-006`, `UI-008`.
- **Consequences:** Backend must handle secure file uploading, MIME validation, and presigned URL generation.

---

### DEC-009: Strict Invoice Product Image Exclusion
- **Date:** September 2026
- **Status:** `CONFIRMED`
- **Decision:** **Product images must NEVER appear on invoices.**
- **Context:** Product images exist in the catalogue for salesman ordering.
- **Reason:** Invoices are formal accounting instruments. Product images bloat PDF file size, clutter printed paper, look unprofessional to corporate accounts payable departments, and violate client specifications.
- **Alternatives Considered:**
  - *Configurable toggle to show thumbnails:* Rejected; client confirmed a hard exclusion rule.
- **Chosen Approach:** Invoice rendering templates (HTML & PDF) strictly omit `<img>` elements for products.
- **Affected Domains:** Invoices, Documents, Frontend.
- **Affected Documents:** PRD §9.2, §21.5; Technical Architecture §24.
- **Affected Tickets:** `FEAT-DOC-001`, `FEAT-DOC-002`, `FEAT-DOC-003`.
- **Consequences:** Invoice generators must never query or load product image URLs.

---

### DEC-010: Admin Order Operational Queues
- **Date:** September 2026
- **Status:** `CONFIRMED`
- **Decision:** The Admin Orders workspace is partitioned into operational sub-queues (`New Orders`, `Active Orders`, `Delivery`, `Adjustments`, `Completed`, `Cancelled`, `All Orders`) rather than one monolithic table.
- **Context:** Admins process hundreds of orders daily and need an "inbox zero" operational workflow.
- **Reason:** Eliminates clutter, surfaces actionable exceptions immediately, and prevents orders from falling through the cracks.
- **Alternatives Considered:**
  - *Single table with complex dropdown filters:* Rejected as slow and operationally confusing for high-volume order desks.
- **Chosen Approach:** Filtered server-side query views with dynamic badge counters over the shared `orders` table.
- **Affected Domains:** Admin Portal, Orders.
- **Affected Documents:** PRD §22, §34; Frontend Specification §8.
- **Affected Tickets:** `FEAT-ORD-010`, `FEAT-ORD-011`, `FEAT-ORD-012`.
- **Consequences:** Order transitions must automatically move orders between queues based on state columns.

---

### DEC-011: Locked Frontend Direction ("Premium B2B Commerce × Modern SaaS ERP")
- **Date:** September 2026
- **Status:** `CONFIRMED`
- **Decision:** The frontend follows a unified design system combining Linear/Vercel interaction discipline, Stripe financial clarity, and tailored shadcn/ui components.
- **Context:** Avoid amateurish, generic admin templates while delivering maximum data density and speed.
- **Reason:** Elevates the application to commercial production standards, builds user trust, and provides purpose-designed mobile interfaces for field drivers and salesmen.
- **Alternatives Considered:**
  - *Off-the-shelf AdminLTE / Bootstrap template:* Rejected due to visual cheapness and poor mobile responsiveness.
- **Chosen Approach:** React 19.2 + TypeScript + Tailwind CSS 4 + shadcn/ui + Inertia 3.
- **Affected Domains:** Frontend, UI Shells, All Portals.
- **Affected Documents:** Frontend Specification §0, §1.
- **Affected Tickets:** `UI-001` through `UI-010`.
- **Consequences:** Full-state coverage (default, skeleton loading, empty state, error state) is mandatory for every screen.

---

### DEC-012: Immutable Double-Entry Accounting
- **Date:** September 2026
- **Status:** `CONFIRMED`
- **Decision:** The general ledger uses strict double-entry principles. Posted journal entries are permanent and cannot be modified or deleted.
- **Context:** Financial adjustments, invoice voids, and payment reversals occur in wholesale operations.
- **Reason:** Preserves an unalterable audit trail required for financial audits, tax filings, and anti-fraud compliance.
- **Alternatives Considered:**
  - *Editing journal records in place:* Rejected as illegal in GAAP accounting and catastrophic for data integrity.
- **Chosen Approach:** Controlled reversal journal entries linked to the original entry ID, followed by new correcting entries.
- **Affected Domains:** Accounting, Receivables, Payables.
- **Affected Documents:** PRD §28.4; Technical Architecture §25; Security & Access §9.
- **Affected Tickets:** `FEAT-ACC-001` through `FEAT-ACC-009`.
- **Consequences:** Database triggers or application policies must reject `UPDATE`/`DELETE` queries on `journal_lines`.

### DEC-013: Local Development Port Mapping and Redis Client Strategy
- **Date:** September 2026
- **Status:** `CONFIRMED`
- **Decision:** Host port mapping for Docker Compose development uses port `5433` for PostgreSQL (mapped to internal `5432`) and port `6380` for Redis (mapped to internal `6379`) via `.env` variables `FORWARD_DB_PORT` and `FORWARD_REDIS_PORT`. Redis client uses `predis` for cross-platform portability without requiring native PECL C-extensions on Windows/Mac/Linux.
- **Context:** Developer machines may host pre-existing containers on standard host ports 5432 and 6379; Windows PHP environments benefit from pure-PHP Predis client.
- **Reason:** Avoids port collisions while ensuring immediate out-of-the-box local developer reproducibility on any machine.
- **Alternatives Considered:**
  - *Hardcoding ports 5432 and 6379:* Rejected due to immediate collision with running services.
  - *Requiring phpredis C-extension:* Rejected due to Windows development complexity and lack of pre-compiled PECL DLLs in some distributions.
- **Chosen Approach:** Configurable port variables with safe non-colliding defaults, and `predis/predis` package.
- **Affected Domains:** Local Infrastructure, Docker, Redis, Database.
- **Affected Documents:** Technical Architecture §1, §3.
- **Affected Tickets:** `TECH-FOUND-001`, `TECH-FOUND-002`, `TECH-FOUND-004`.
- **Consequences:** All developer documentation and `.env.example` state port `5433` for PostgreSQL and `6380` for Redis.

### DEC-014: Deferral of FEAT-RBAC-003 Pending Concrete Domain Entity Models
- **Date:** September 2026
- **Status:** `CONFIRMED`
- **Decision:** Defer `FEAT-RBAC-003 — Resource Scope Enforcement` until the underlying domain entity models (`Customer`, `Order`, `Delivery`) and their assignment relationships are implemented by their respective authoritative feature tickets (`FEAT-CUS-001`, `FEAT-CUS-002`, `FEAT-ORD-001`, `FEAT-DLV-001`). Do NOT introduce premature mock/placeholder models or invented multi-warehouse schemas.
- **Context:** `FEAT-RBAC-003` was scheduled at the end of Phase 01 (Identity & Access). However, resource scoping requires concrete database foreign keys and query scopes (`assigned_salesman_id` on customers, `customer_id`/`salesman_id` on orders, `delivery_partner_id` on deliveries). The codebase currently only contains authentication and user models (`users`, `sessions`, `recovery_codes`). Furthermore, PRD §39.2 explicitly excludes multi-warehouse orchestration in V1, defining a single central warehouse.
- **Reason:** Creating placeholder domain models or synthetic multi-warehouse tables would invent unapproved schemas and violate single responsibility, dead-code protocols, and PRD §39.2. Real resource scoping must be enforced against actual domain schemas.
- **Alternatives Considered:**
  - *Creating minimal forward domain models (Customer, Order, Delivery) during Phase 01:* Rejected by client direction to avoid premature schema creation and maintain phase integrity.
  - *Introducing multi-warehouse tables (warehouses, user.warehouse_id):* Explicitly rejected as contradictory to PRD §39.2 (single central distribution warehouse in V1).
- **Chosen Approach:** Mark `FEAT-RBAC-003` as `DEFERRED / PENDING DOMAIN PREREQUISITES`. Proceed with the next unblocked tickets (`FEAT-SYS-001`, `FEAT-SYS-002`, `FEAT-CUS-001`, `FEAT-CUS-002`). Implement resource scoping rules directly on each domain model as it is built.
- **Affected Domains:** RBAC, Customer Management, Order Management, Delivery Management, Warehouse Operations.
- **Affected Documents:** Feature Ticket List §11 (`FEAT-RBAC-003`), Technical Architecture §18, Security & Access §18.
- **Affected Tickets:** `FEAT-RBAC-003` (deferred), `FEAT-CUS-002`, `FEAT-ORD-003`, `FEAT-DLV-001`.
- **Consequences:** `FEAT-RBAC-003` remains open/deferred. The permission registry (`FEAT-RBAC-002`) remains the authoritative action-authorization boundary until domain resource scoping is bound to real entities.

---

### DEC-015: Database-Backed Singleton Company Information & Business Details Configuration
- **Date:** September 2026
- **Status:** `CONFIRMED`
- **Decision:** Store legal company details, operating address, tax identifiers, base currency, operational timezone, and default invoicing notes in a normalized, database-backed `company_information` singleton table in PostgreSQL with atomic `lockForUpdate` mutations and audit logging.
- **Context:** The system requires an authoritative source for legal entity records, billing/shipping origin addresses, tax registration numbers (e.g. EIN, State Tax ID), and invoice metadata across customer statements and financial documents.
- **Reason:** Separates mutable business entity configuration (`FEAT-SYS-002`) from deployment-level product branding (`FEAT-SYS-001`), ensuring admin-editable corporate metadata without requiring application code changes or server redeployment.
- **Alternatives Considered:**
  - *Storing company details in `.env` / static config:* Rejected because corporate address, phone, and invoice notes need runtime administrative editability.
  - *Arbitrary key-value EAV configuration table:* Rejected due to lack of type safety, poor schema validation, and potential configuration drift.
  - *Multi-company tenancy tables:* Rejected as out of scope for V1 single-business distribution model.
- **Chosen Approach:** Dedicated normalized Eloquent model `CompanyInformation`, singleton constraint (`is_singleton` unique boolean), `CompanyInformationService`, FormRequest validation, `role.manage` permission protection, and structured `SYSTEM_COMPANY_INFORMATION_UPDATED` audit events.
- **Affected Domains:** System Configuration, UI Shell, Invoicing, Financial Reports.
- **Affected Documents:** PRD §0.2; Technical Architecture §43; Security & Access §18.
- **Affected Tickets:** `FEAT-SYS-002`, `FEAT-DOC-001`, `FEAT-ACC-001`.
- **Consequences:** All invoices, dispatch manifests, and customer statements query `CompanyInformationService` for official legal entity and tax registration metadata.

### DEC-016: Order Item Quantity Allocation Model & Operational Reservation Boundaries
- **Date:** September 2026
- **Status:** `CONFIRMED`
- **Decision:** Establish `order_item_allocations` as a dedicated operational entity and `App\Models\OrderItemAllocation` as the canonical model for tracking discrete allocations against order lines, while preserving denormalized operational rollups on `order_items` as authoritative order-level buckets.
- **Context:** Transitioning to Phase 06 requires supporting partial fulfillment, discrete allocations, and future post-order adjustments without overwriting commercial history (`ordered_quantity`), price snapshots, or tax snapshots.
- **Reason:** Separates the commercial order contract (`order_items`) from operational fulfillment units (`order_item_allocations`). Ensures cross-row conservation laws ($\sum \text{allocated} \le \text{fulfillable}$) and row-local PostgreSQL CHECK constraints without circular rollup loops or premature physical inventory dependencies.
- **Alternatives Considered:**
  - *Mutating existing order_items columns directly:* Rejected because it cannot represent split/partial allocations or discrete fulfillment allocations per warehouse unit.
  - *Premature physical warehouse inventory table binding:* Rejected because physical warehouse ledgering (`inventory_balances`, `inventory_movements`) belongs to Phase 06 (`FEAT-INV-001..004`).
  - *Enforcing cross-row summation solely via DB check constraints:* Architecturally impossible in PostgreSQL without triggers; resolved via deterministic row locking, domain service validation, and atomic transaction boundaries.
- **Chosen Approach:** Dedicated table with foreign keys to `orders`, `order_items`, `products`, `users`; canonical baseline allocation creation inside `OrderWorkflowService::approveOrder()` atomic transaction; `OrderAllocationService` domain service with deterministic lock ordering (Order -> OrderItems ASC -> OrderItemAllocations ASC); and backward-compatible idempotent backfill support.
- **Affected Domains:** Orders, Quantity Allocation, Inventory Integrity, Operational Admin.
- **Affected Documents:** PRD §13; Technical Architecture §14, §15; Security & Access §18.
- **Affected Tickets:** `FEAT-ALLOC-001`, `FEAT-ALLOC-002`, `FEAT-ADJ-001..006`, `FEAT-INV-001..004`.
- **Consequences:** All future fulfillment, picking, dispatching, and adjustment workflows anchor to `order_item_allocations` rows while respecting `order_items` denormalized rollups.

### DEC-017: Allocation Mathematical Constraints, Directional Progression, and Single-Direction Rollup Synchronization
- **Date:** September 2026
- **Status:** `CONFIRMED`
- **Decision:** Enforce strict mathematical conservation laws ($\text{ordered} = \text{cancelled} + \text{fulfillable}$ and $\sum \text{allocated}_{\text{active}} \le \text{fulfillable}$) and strict unidirectional fulfillment progression constraints ($0 \le \text{returned} \le \text{delivered} \le \text{dispatched} \le \text{picked} \le \text{allocated}$ and $0 \le \text{reserved} \le \text{allocated}$) via server-side domain validation in `OrderAllocationValidationService` and PostgreSQL CHECK constraints (`order_item_allocations_dispatched_quantity_check`, `order_item_allocations_delivered_quantity_check`). Centralize all rollup calculations from child allocation rows into `order_items` through a single authoritative synchronization path (`OrderAllocationService::syncOrderItemRollups()`) rather than incremental arithmetic.
- **Context:** Preventing quantity drift, race conditions, over-allocation, and impossible state transitions (e.g. dispatched > picked, delivered > dispatched) across concurrent fulfillment steps, partial releases, and upcoming order adjustments.
- **Reason:** Guarantee mathematical correctness and eliminate circular synchronization loops. Non-destructive soft-state operations (`RELEASED`, `CANCELLED`) preserve audit trails while accurately restoring unallocated capacity.
- **Alternatives Considered:**
  - *Incremental +/- arithmetic on order_items rollups during status changes:* Rejected because concurrent transactions or partial failures easily cause permanent drift. Centralized authoritative recalculation from child rows is deterministic and self-healing.
  - *Allowing allocations to be hard-deleted on cancellation or release:* Rejected because it violates non-destructive history (`RULE-DOM-001`) and destroys operational audit trails.
  - *Relying only on database CHECK constraints without domain service validation:* Rejected because cross-row aggregation cannot be enforced by row-local CHECK constraints and domain validation provides actionable 422 error messages to API consumers.
- **Chosen Approach:** Dual-layer defense-in-depth: PostgreSQL CHECK constraints for row-local bounds + `OrderAllocationValidationService` for cross-row conservation and progression + `OrderAllocationService::syncOrderItemRollups()` for authoritative rollup synchronization + pessimistic row locking with deterministic lock order (`Order -> OrderItems ASC -> OrderItemAllocations ASC`).
- **Affected Domains:** Quantity Allocation, Order Management, Fulfillment Progression, Inventory Integrity.
- **Affected Documents:** PRD §13, §14; Technical Architecture §14, §15, §18; Security & Access §18.
- **Affected Tickets:** `FEAT-ALLOC-002`, `FEAT-ALLOC-001`, `FEAT-ADJ-001..006`, `FEAT-INV-001..004`.
- **Consequences:** All future picking, packing, dispatching, delivery, and adjustment operations must use `OrderAllocationService` and pass progression and capacity validation before mutating records.

### DEC-018: Order Adjustment Request Architecture, Non-Destructive History, Single Open Request Invariant & Self-Approval Policy
- **Date:** September 2026
- **Status:** `CONFIRMED`
- **Decision:** Establish `order_adjustments` and `order_adjustment_items` as the authoritative persistence aggregate for post-submission quantity reduction requests (`FEAT-ADJ-001`). Enforce non-destructive history by using `restrictOnDelete()` on `order_adjustment_items.adjustment_id -> order_adjustments` (preventing cascading deletion of historical request lines). Enforce the single-open-request invariant ($ \le 1 $ `SUBMITTED` request per order) via dual controls: pessimistic row lock in `OrderAdjustmentService` and PostgreSQL partial unique index `idx_order_adjustments_single_open ON order_adjustments (order_id) WHERE status = 'SUBMITTED'`. Generate collision-free monotonic sequential identifiers (`ADJ-{order_number}-{seq}`) without sequence reuse. Enforce deterministic idempotency through canonical payload fingerprinting (SHA-256 over order ID, reason code, notes, and sorted item IDs + reductions): identical requests return replay (200), conflicting payloads return 409 Conflict. Classify reductions into Case A (unallocated units only) and Case B (allocation-impacting with `affected_allocation_quantity`). Financial projections and Case A/B metrics are informational snapshots and MUST NOT mutate baseline order financials, pricing snapshots, or active inventory allocations.
- **Future Approval Policy & Self-Approval Rule (for FEAT-ADJ-003):** `requested_by` is persisted permanently on `order_adjustments`. For future review/approval (`FEAT-ADJ-003`), dual-control segregation of duties applies: a user cannot approve an adjustment they personally requested, EXCEPT for `SUPER_ADMIN` emergency override when documented with explicit audit reason. Admins who did not request the adjustment may approve salesman or warehouse requests.
- **Context:** Wholesale distribution orders frequently require post-submission quantity cancellations due to customer request, warehouse damage, or inventory shortages. These must be recorded as explicit audit adjustments without rewriting history, mutating active allocations before approval, or creating conflicting concurrent adjustments.
- **Reason:** Satisfies `RULE-DOM-001` (Non-Destructive History), `RULE-ORD-002` (Order Adjustment Framework), `RULE-PRI-001` (Price Immutability), and `RULE-TAX-002` (Tax Snapshot Immutability). Prevents race conditions and double-reduction errors under concurrent operations.
- **Alternatives Considered:**
  - *Hard-deleting withdrawn adjustment requests:* Rejected because submitted adjustments are historical transactional records; cancellation transitions to `CANCELLED` status with cancellation reason.
  - *Cascading deletion on child lines (`cascadeOnDelete`):* Rejected to prevent accidental loss of adjustment history; `restrictOnDelete` is required.
  - *Relying solely on idempotency key without payload fingerprint:* Rejected because retries with different payloads would return false replays rather than catching conflicting parameters (409).
- **Chosen Approach:** Dedicated schema with PostgreSQL partial unique index and check constraints; `OrderAdjustmentService` with deterministic lock order (`Order -> Items ASC -> Allocations ASC -> Adjustments`); `TaxCalculationService::roundHalfUp` financial projections; FormRequest validation; requester withdrawal capability; and Vue/React Inertia Show integration.
- **Affected Domains:** Order Adjustments, Quantity Allocation, Inventory Integrity, Salesman Portal, Admin Portal.
- **Affected Documents:** PRD §14, §15, §16; Technical Architecture §14, §15, §18; Security & Access §18, §23.
- **Affected Tickets:** `FEAT-ADJ-001`, `FEAT-ADJ-002`, `FEAT-ADJ-003`, `FEAT-ADJ-004`.
- **Consequences:** All future order adjustment review, approval, and application workflows (`FEAT-ADJ-002..006`) must operate over this aggregate, respecting the immutability of baseline order data until atomic application in `FEAT-ADJ-004`.

### DEC-019: Administrative Adjustment Review Architecture, Read-Only Boundary, Option A Reviewer Permission Scoping & Pure State Evaluation
- **Date:** September 2026
- **Status:** `CONFIRMED`
- **Decision:**
  1. **Strict Read-Only Boundary:** The review workspace (`FEAT-ADJ-002`) operates strictly as an inspection and situational awareness interface. Under no circumstances may viewing or evaluating an adjustment mutate database records (`orders`, `order_items`, `order_item_allocations`, `order_adjustments`), execute approvals/rejections, or invoke allocation release/cancellation methods (`releaseAllocation`, `cancelAllocation`). No transactional audit logs are recorded for ordinary page viewing.
  2. **Canonical Active Allocation Definition & Math:** In accordance with `FEAT-ALLOC-001/002`, active allocations are strictly defined as allocations whose status is neither `CANCELLED` nor `RELEASED`. Progression math respects the physical invariant `returned <= delivered <= dispatched <= picked <= allocated`. Unpicked allocated stock is evaluated as `allocated - picked`. Quantities already picked or dispatched must never be double-counted.
  3. **Snapshot Fidelity vs Live Evaluation:** Immutable request snapshots (`unit_price_snapshot`, `tax_rate_snapshot`, `fulfillable_quantity_snapshot`, `allocated_quantity_snapshot`, etc.) are preserved and displayed unchanged. Pure on-the-fly live calculations using authoritative `TaxCalculationService::normalizeRate` and `roundHalfUp` are displayed alongside snapshots and clearly labeled as `CURRENT/LIVE` to highlight version or financial discrepancies without persisting mutations.
  4. **Option A Reviewer Permission Resolution:** In resolving the role registry discrepancy, `WAREHOUSE_MANAGER` remains denied review access alongside `SALESMAN` and `DELIVERY_PARTNER`. Only `SUPER_ADMIN`, `ADMIN`, and `ACCOUNTANT` hold `Permission::ORDER_ADJUST_REVIEW` and have access to the queue (`/admin/adjustments`) and review workspace (`/admin/orders/{order}/adjustments/{adjustment}/review`).
  5. **Pure Stale/Conflict Evaluation:** State evaluation is implemented as a deterministic, pure service (`OrderAdjustmentReviewService`) synthesizing 7 clear states (`READY`, `WARNING_ALLOCATION`, `WARNING_PICKED_ENCROACHMENT`, `STALE`, `CONFLICTED`, `INELIGIBLE_LIFECYCLE`, `TERMINAL_REQUEST`) without relying solely on `order.version`.
- **Context:** An administrative workspace was needed to review pending adjustments, inspect Case A vs Case B impacts, and detect upstream changes in order lifecycle or picking before proceeding to approval/rejection (`FEAT-ADJ-003`).
- **Reason:** Enforces `RULE-DOM-001` (Non-Destructive History), `RULE-SEC-001` (Server Authority), `RULE-SEC-002` (Zero Client Trust), and `RULE-ORD-002` (Order Adjustment Framework).
- **Alternatives Considered:**
  - *Granting Warehouse Managers review access:* Rejected under Option A to preserve strict administrative and financial segregation of duties. Warehouse personnel submit exception requests (`FEAT-ADJ-001`) but do not review or approve commercial order adjustments.
  - *Persisting live evaluation results to the database:* Rejected because review must remain strictly read-only and non-mutating.
- **Affected Domains:** Order Adjustments, Quantity Allocation, Security/RBAC, Admin Portal.
- **Affected Documents:** PRD §14, §15; Technical Architecture §14, §18; Security & Access §18.
- **Affected Tickets:** `FEAT-ADJ-002`, `FEAT-ADJ-003`, `FEAT-ADJ-004`.
- **Consequences:** All future approval and rejection actions (`FEAT-ADJ-003`) will be triggered as explicit mutations from this review workspace.

### DEC-020: Order Adjustment Approval & Rejection Engine Architecture, Maker-Checker Segregation, Super Admin Emergency Override, Deterministic Lock Ordering & Duplicate Decision Semantics
- **Date:** September 2026
- **Status:** `CONFIRMED`
- **Decision:**
  1. **Guarded State Transitions & Duplicate Decision Semantics:** Only `SUBMITTED -> APPROVED` and `SUBMITTED -> REJECTED` represent valid decision transitions. Any attempt to approve or reject a request that has already transitioned out of `SUBMITTED` (whether `APPROVED`, `REJECTED`, `CANCELLED`, `APPLIED`, or `REVERSED`) returns **HTTP 409 Conflict**. A decision endpoint represents a guarded state transition, not an idempotent resource creation; duplicate browser clicks or retries safely yield exactly one committed transition followed by HTTP 409.
  2. **Maker-Checker Segregation of Duties:** An administrator who requested an adjustment cannot approve or reject it. `ADMIN` self-decision is strictly prohibited (403). `SUPER_ADMIN` self-decision is restricted exclusively to an audited emergency override requiring a mandatory `emergency_override_reason` (10–1,000 characters) and emitting a dedicated `ADJUSTMENT_EMERGENCY_OVERRIDE` audit event.
  3. **Supervisory Decision Permission Model:** In alignment with the existing RBAC permission registry, `Permission::ORDER_ADJUST_APPROVE` (`order.adjust.approve`) governs BOTH approval and rejection. No separate `order.adjust.reject` permission is introduced.
  4. **Canonical Transactional Lock Hierarchy:** All decision transitions execute within `DB::transaction(..., 3)` enforcing deterministic row lock acquisition: `orders -> order_items ASC -> order_item_allocations ASC -> order_adjustments`. Locks are held until commit, preventing concurrent allocation progression or order mutation deadlocks.
  5. **Live Revalidation & Allocation Boundary:** Approvals re-read and evaluate authoritative live state under lock (`OrderAdjustmentReviewService::evaluate`). If live fulfillable quantity cannot satisfy requested reductions or allocations have progressed into picked status, approval is blocked (422) with `ADJUSTMENT_APPROVAL_BLOCKED` observability event. Case B adjustments (impacting active allocations) mandate explicit `acknowledge_allocation_impact = true`. Rejections require a validated reason (5–1,000 characters) but are permitted even on stale, conflicted, or picked-encroaching requests without requiring mathematical approvability.
  6. **Strict Non-Mutation Boundary (FEAT-ADJ-004 Boundary):** `FEAT-ADJ-003` operates solely as the decision authority. It mutates ONLY adjustment decision fields (`status`, `reviewed_by`, `reviewed_at`, `rejection_reason`) and order `adjustment_status`. It MUST NOT mutate `order_items` quantities (`cancelled_quantity`, `reserved_quantity`, etc.), `order_item_allocations`, inventory tables, invoice lines, tax/financial totals, or GL entries. Actual application belongs strictly to `FEAT-ADJ-004`.
  7. **Order Adjustment Status Management:** Upon `APPROVED`, `orders.adjustment_status` remains `REQUESTED` to indicate an approved adjustment is awaiting application and prevent new requests. Upon `REJECTED`, `orders.adjustment_status` resets to `NONE` (or preserves `APPLIED` if an earlier adjustment was applied).
  8. **Zero Schema Migration:** Leverages existing PostgreSQL columns (`status`, `reviewed_by`, `reviewed_at`, `rejection_reason`) on `order_adjustments` without creating duplicate fields or running schema migrations.
- **Context:** Transitioning order adjustments from review (`FEAT-ADJ-002`) into authoritative execution requires a resilient decision engine preventing dual-control bypasses, concurrency races, and premature inventory/financial mutations.
- **Reason:** Enforces `RULE-DOM-001` (Non-Destructive History), `RULE-ORD-002` (Order Adjustment Framework), `RULE-SEC-001` (Server Authority), `RULE-SEC-002` (Zero Client Trust), and `RULE-ORD-003` (Independent State Dimensions).
- **Alternatives Considered:**
  - *Returning HTTP 200 idempotent replay on duplicate approval:* Rejected because state transitions are guarded operations, and masking concurrent/duplicate decisions risks masking operational anomalies.
  - *Introducing a separate `order.adjust.reject` permission:* Rejected to avoid bloating the core RBAC permission registry when supervisory approval authority naturally encompasses rejection.
  - *Executing allocation cancellations (`releaseAllocation`) during approval:* Rejected because approval is a managerial authorization step; physical and allocation adjustments belong to the atomic application step (`FEAT-ADJ-004`).
- **Affected Domains:** Order Adjustments, Quantity Allocation, Security/RBAC, Operational Admin.
- **Affected Documents:** PRD §14, §15; Technical Architecture §14, §15, §18; Security & Access §18, §23.
- **Affected Tickets:** `FEAT-ADJ-003`, `FEAT-ADJ-004`.
- **Consequences:** All adjustment requests transition to terminal `APPROVED` or `REJECTED` states, paving the way for `FEAT-ADJ-004` to apply approved adjustments atomically.

### DEC-021: Atomic Adjustment Application Engine Architecture, Quantity Conservation, Case B Split Allocation History, Authoritative Financial Recalculation & Exactly-Once Mutation Guarantees
- **Date:** September 2026
- **Status:** `CONFIRMED`
- **Decision:**
  1. **Guarded State Transition & Exactly-Once Application:** Only adjustments in `OrderAdjustmentStatus::APPROVED` may enter the application engine. Valid transition is strictly `APPROVED -> APPLIED`. Any duplicate attempt or attempt on non-approved requests (whether `SUBMITTED`, `REJECTED`, `CANCELLED`, or already `APPLIED`) immediately returns **HTTP 409 Conflict**. No second version increment, allocation release, or financial deduction can ever execute.
  2. **Canonical Transactional Lock Hierarchy:** Execution is strictly wrapped within `DB::transaction(..., 3)` acquiring row-level pessimistic locks in canonical deadlock-free order: `orders -> order_items ASC -> order_item_allocations ASC -> order_adjustments`. Locks are held until commit, preventing concurrent allocation progression or inventory mutation races.
  3. **Live Re-Validation Under Lock:** All domain quantities and eligibility states are re-read and validated from the database under lock, never trusting client parameters or stale review snapshots. If live fulfillable quantity cannot satisfy requested reductions or allocations have progressed into picked status, the application is rejected with HTTP 409.
  4. **Strict Quantity Conservation & Non-Destructive History (`RULE-DOM-001`):** `order_items.ordered_quantity` is strictly immutable. For every affected line, `cancelled_quantity` increases by requested reduction $R$, fulfillable quantity decreases by $R$, and the invariant $\text{ordered} = \text{cancelled} + \text{fulfillable}$ is verified.
  5. **Critical Case B Allocation Release & Non-Destructive Split:** When reduction exceeds unallocated units ($A = R - \text{unallocated} > 0$), units are released from active, unpicked allocations. Allocations with $\text{picked} > 0$ are non-releasable. For a partial release of $A$ units from an allocation with allocated quantity $Q$ and reserved quantity $R$:
     - Released reserved units: $r = \min(A, R)$.
     - Active remainder row retains: $\text{allocated} = Q - A$, $\text{reserved} = R - r$, preserving $0 \le \text{reserved} \le \text{allocated}$.
     - Released historical child row created with: $\text{allocated} = A$, $\text{reserved} = 0$, $\text{picked} = 0$, $\text{dispatched} = 0$, $\text{delivered} = 0$, $\text{returned} = 0$, $\text{status} = \text{RELEASED}$.
     - Allocation number formatted as $\text{ALC-}\{\text{order\_number}\}\text{-}\{\text{order\_item\_id}\}\text{-}\{\text{sequence}\}$ with 2-digit zero padding (`%02d`), generated under the locked OrderItem boundary.
  6. **Deterministic Release Priority:** Allocations are released least-progressed first (`ALLOCATED` before `RESERVED`), then by sequence `DESC` (newest supplemental allocations released before primary allocations).
  7. **Authoritative Financial Recalculation:** Employs the existing authoritative `TaxCalculationService::calculateLineTax` for remaining fulfillable units (or `'0.00'` if fulfillable reaches zero). Line taxable amount, tax amount, and line totals are updated. Order `subtotal`, `tax_total`, and `grand_total` are summed authoritatively across items, and `orders.adjustment_total` accumulates reductions. Historical snapshot fields (`unit_price`, `tax_rate_snapshot`, `tax_profile_id`) are preserved intact.
  8. **Order Version & Adjustment Status Synchronization:** Upon successful application, `orders.version` is incremented exactly once ($+1$) and `orders.adjustment_status` transitions to `APPLIED`.
  9. **Strict Multi-Line Transactional Atomicity:** For multi-line adjustments, all lines are pre-validated under lock. If any single line fails (e.g. insufficient fulfillable quantity or unpicked capacity), the entire transaction rolls back completely: zero lines modified, zero allocations released, zero financial adjustments, zero version increment.
  10. **RBAC & Authorization:** Governed by `Permission::ORDER_ADJUST_APPLY` (`order.adjust.apply`) restricted to Super Admin and Admin roles. Salesmen, Accountants, Warehouse Managers, and Delivery Partners are strictly forbidden (403). Anti-IDOR ownership validation enforces `adjustment.order_id == order.id`.
- **Context:** Applying an approved adjustment requires modifying active commercial state while maintaining total auditability, financial consistency, and allocation integrity across Case A (unallocated reductions) and Case B (allocation releases).
- **Reason:** Enforces `RULE-DOM-001` (Non-Destructive History), `RULE-ORD-002` (Order Adjustment Framework), `RULE-INV-001` (Atomic Inventory Reservation), `RULE-PRI-001` (Historical Price Snapshots), `RULE-SEC-001` (Server Authority), and `RULE-SEC-002` (Zero Client Trust).
- **Alternatives Considered:**
  - *Blindly decrementing reserved quantity (`reserved -= released`):* Rejected because `ALLOCATED` allocations may have $\text{reserved} = 0$, which would create invalid negative reserved quantities violating database CHECK constraints.
  - *Deleting released allocation rows:* Rejected because historical traceability mandates auditability of all allocation actions; rows are split into active remainders and released historical rows.
  - *Independently summing order totals with ad-hoc arithmetic:* Rejected in favor of the authoritative `TaxCalculationService` and line-item rollup synchronization to avoid rounding drift.
- **Affected Domains:** Order Adjustments, Quantity Allocation, Pricing & Tax Engine, Orders & Invoicing, Security/RBAC.
- **Affected Documents:** PRD §14, §15; Technical Architecture §14, §15, §18; Security & Access §18, §23.
- **Affected Tickets:** `FEAT-ADJ-004`, `FEAT-ADJ-005`.
- **Consequences:** Approved adjustments can now be atomically applied, modifying order quantities, allocations, and financials with full mathematical precision and exact-once concurrency protection.

---

### DEC-016: Adjustment Reversal Engine & Deterministic LIFO Commercial Restoration
- **Date:** September 2026
- **Status:** `CONFIRMED`
- **Decision:**
  1. **Guarded State Transition & Exactly-Once Reversal:** Only adjustments in `OrderAdjustmentStatus::APPLIED` may enter the reversal engine. Valid transition is strictly `APPLIED -> REVERSED`. Any duplicate reversal attempt or attempt on non-applied requests (`SUBMITTED`, `APPROVED`, `REJECTED`, `CANCELLED`, or already `REVERSED`) immediately returns **HTTP 409 Conflict**.
  2. **Canonical Transactional Lock Hierarchy:** Execution is wrapped inside `DB::transaction(..., 3)` acquiring row-level pessimistic locks in canonical deadlock-free order: `orders -> order_items ASC -> order_item_allocations ASC -> order_adjustments`.
  3. **Strict Deterministic LIFO Reversal:** Adjustments applied to an order must be reversed in strict reverse-chronological order (`applied_at DESC, id DESC`). Any attempt to reverse an earlier adjustment while a subsequent applied adjustment remains active on the same order is rejected with **HTTP 409 Conflict** ("LIFO rule violation").
  4. **Non-Destructive Allocation Restoration (Case B):** Historical `RELEASED` allocation rows created during adjustment application remain completely immutable, never deleted, and never altered back to `ALLOCATED`. A brand new forward restoration allocation record is created (`ALC-{order_number}-{order_item_id}-{seq}`) with `allocated_quantity = affected_allocation_quantity`, `reserved_quantity = 0` (no fabricated reservation state; preserves $0 \le \text{reserved} \le \text{allocated}$), unpicked status (`picked=0, dispatched=0, delivered=0, returned=0`), canonical sequence, and warehouse code derived authoritatively from historical records.
  5. **Quantity Conservation (`RULE-DOM-001`):** `order_items.ordered_quantity` remains strictly immutable. `cancelled_quantity` is decremented by requested reduction $R$, fulfillable quantity increases by $R$, and the invariant $\text{ordered} = \text{cancelled} + \text{fulfillable}$ is preserved.
  6. **Authoritative Financial Recalculation:** Recomputes line taxable amount, tax amount, and line totals using `TaxCalculationService::calculateLineTax` with snapshotted line pricing. Recomputes order `subtotal`, `tax_total`, and `grand_total` authoritatively across lines. Decrements `orders.adjustment_total` using BCMath without rounding drift.
  7. **Order Version & Status Synchronization:** Increments `orders.version` exactly once ($+1$). Transitions `orders.adjustment_status` to `REVERSED` if no other applied adjustments exist on the order, or retains `APPLIED` if an earlier applied adjustment remains active.
  8. **Order Lifecycle & Fulfillment Progression Guards:** Reversal is blocked with **HTTP 409 Conflict** if the order has transitioned to `CANCELLED` or `COMPLETED`, or if fulfillment has progressed to `PACKED`, `DISPATCHED`, or `DELIVERED`.
  9. **RBAC & Maker-Checker Segregation of Duties:** Reversal is protected by `Permission::ORDER_ADJUST_REVERSE` (`order.adjust.reverse`), authorized for Super Admin and Admin roles. An Admin cannot reverse an adjustment they personally requested. A Super Admin may self-reverse only through an explicit emergency override with mandatory reason ($\ge 10$ chars), logged to audit.
  10. **Strict Transactional Atomicity:** Reversals are all-or-nothing. Any failure rolls back the entire transaction: zero quantities changed, zero allocations created, zero financial deltas, zero version increment.
- **Context:** Once an adjustment has been applied, customer circumstances or inventory restocking may require reversing the reduction while preserving absolute historical auditability and preventing double-counting or sequence corruption.
- **Reason:** Enforces `RULE-DOM-001` (Non-Destructive History), `RULE-ORD-002` (Order Adjustment Framework), `RULE-INV-001` (Atomic Inventory Reservation), `RULE-PRI-001` (Historical Price Snapshots), `RULE-SEC-001` (Server Authority), and `RULE-SEC-002` (Zero Client Trust).
- **Alternatives Considered:**
  - *Reactivating historical RELEASED allocation rows:* Rejected because historical operational records must remain immutable; resurrected rows corrupt operational timelines and allocation state machines.
  - *Arbitrarily setting `reserved_quantity = affected_quantity` on restoration:* Rejected because historical allocations may have had `reserved = 0`, and fabricating reservation state violates the zero-fabrication invariant and causes inventory ledger drift.
  - *Allowing arbitrary-order reversals without LIFO enforcement:* Rejected because non-LIFO reversals corrupt sequential financial snapshots and cascade quantity allocations unpredictably.
- **Affected Domains:** Order Adjustments, Quantity Allocation, Pricing & Tax Engine, Orders & Invoicing, Security/RBAC.
- **Affected Documents:** PRD §14, §15; Technical Architecture §14, §15, §18; Security & Access §18, §23; Frontend Specification §14.
- **Affected Tickets:** `FEAT-ADJ-005`.
- **Consequences:** Applied adjustments can now be safely, deterministically, and non-destructively reversed with full auditability and mathematical precision.

### DEC-017: Administrative Adjustment & Exception Operational Queue Architecture
- **Date:** September 2026
- **Status:** `CONFIRMED`
- **Decision:**
  1. **Canonical Operational Queue Views:** The canonical endpoint `GET /admin/adjustments` (`AdminOrderAdjustmentController@index`) is evolved into a dedicated operational triage queue supporting seven deterministic queue views: `attention` (Needs Attention / Exceptions), `pending` (Pending Review), `ready_to_apply` (Ready to Apply), `applied` (Applied), `reversed` (Reversed), `closed` (Rejected & Withdrawn), and `all` (Complete Archive).
  2. **Ready-to-Apply Non-Conflicting Semantics:** `READY_TO_APPLY` is strictly defined as `status = APPROVED AND NOT (has_blocker)`. An approved adjustment possessing any current blocking condition (version mismatch, status mismatch, ineligible order lifecycle, quantity conflict, or picked encroachment) is classified into `NEEDS ATTENTION` and strictly excluded from `READY_TO_APPLY`.
  3. **Single Authoritative Exception Classifier (`OrderAdjustmentClassifier`):** Centralizes domain classification rules and SQL query scopes into a single domain service. Evaluates attention flags (`CONFLICTED`, `INELIGIBLE_LIFECYCLE`, `PICKED_ENCROACHMENT`, `STALE_VERSION`, `STALE_STATUS`, `AGING`), primary exception precedence, blocker conditions, and queue scopes. Ensures `OrderAdjustmentReviewService::evaluate` and queue filtering remain 100% consistent.
  4. **Single-Trip Aggregate Badge Counts:** All queue tab badge counts are computed in a single unified SQL query whose CASE conditions mirror the list scopes exactly, eliminating contradictory badge counters and guaranteeing bounded database query execution.
  5. **Zero Database Migrations:** All exception classifications, attention badges, and operational queue memberships are 100% derivable from authoritative domain relationships and timestamps without introducing redundant database columns or triggers.
  6. **Reconciliation of Historical ADJ-006 Price Adjustment Ambiguity:** Formally records that early draft references describing ADJ-006 as a "price adjustment framework" are superseded. Price guardrails and override workflows are governed exclusively by `FEAT-PRICE-002`. FEAT-ADJ-006 is strictly an administrative triage and exception queue for post-submission order adjustments (`QUANTITY_REDUCTION`).
  7. **RBAC & Read-Only Queue Security:** Access is governed by `Permission::ORDER_ADJUST_REVIEW` (`order.adjust.review`), authorized for `SUPER_ADMIN`, `ADMIN`, and `ACCOUNTANT` (read-only). Commercial roles (`SALESMAN`, `WAREHOUSE_MANAGER`, `DELIVERY_PARTNER`) are strictly forbidden (HTTP 403). Viewing the queue emits zero audit logs to prevent audit log spam.
- **Context:** Administrative users needed immediate operational visibility into pending adjustments, blocked applications, allocation conflicts, and aging backlog items without opening each review workspace individually.
- **Reason:** Enforces `RULE-DOM-001` (Non-Destructive History), `RULE-ORD-002` (Order Adjustment Framework), `RULE-ORD-003` (Independent State Dimensions), and `RULE-SEC-001` (Server-Side Authority).
- **Alternatives Considered:**
  - *Creating separate routes for `/admin/adjustments` and `/admin/adjustments/exceptions`:* Rejected to avoid fragmented competing endpoints and broken bookmarks.
  - *Storing persistent exception columns in the database:* Rejected because exception states (e.g. order version mismatch or fulfillment progression) are dynamic properties of the relationship between the adjustment and the order, not static records.
- **Affected Domains:** Order Adjustments, Orders, Security & RBAC, Administration.
- **Affected Documents:** PRD §14, §15; Technical Architecture §14, §15; Security & Access §18; Frontend Specification §14; Feature Ticket List §19.
- **Affected Tickets:** `FEAT-ADJ-006`.
- **Consequences:** Administrators now have an operational command center for triaging, evaluating, and applying adjustments with live exception detection and zero query scaling.

### DEC-018: Physical Inventory Balance Foundation, Canonical Warehouse Entity & Mathematical Invariants
- **Date:** September 2026
- **Status:** `CONFIRMED`
- **Decision:**
  1. **Strict Architectural Separation (Physical Inventory vs Order Allocations):**
     - **Order/Allocation Domain (`order_item_allocations`):** Tracks commercial fulfillment commitments (`ordered`, `cancelled`, `allocated`, `reserved`, `picked`, `dispatched`, `delivered`, `returned`) bound to specific order items and customer deliveries.
     - **Physical Inventory Domain (`inventory_balances`):** Tracks aggregate physical units resting on warehouse shelves for a product (`on_hand`, `reserved`, `available`, `damaged`) independent of customer identities.
     - Physical inventory and order allocations are separate domains. `FEAT-INV-001` provides the authoritative physical balance aggregate and models without prematurely synchronizing them (order reservation bridge belongs strictly to `FEAT-INV-003`).
  2. **Canonical Warehouse Entity (`warehouses`) & Single Central Warehouse V1:**
     - In accordance with PRD §39.2, V1 operates with a single default warehouse (`code: 'MAIN'`, `name: 'Main Distribution Center'`, `is_default: true`).
     - Rather than hardcoding warehouse strings, a canonical `warehouses` table is established with partial unique indexing enforcing exactly one default warehouse (`idx_warehouses_single_default`).
     - Extensible database design guarantees multi-warehouse schema readiness without introducing premature multi-warehouse orchestration or transfer complexity.
  3. **Composite Identity & Foreign Key Integrity:**
     - Physical inventory balance is keyed on `UNIQUE (warehouse_id, product_id)`.
     - Foreign keys to `warehouses` and `products` use `ON DELETE RESTRICT` semantics to protect historical warehouse records and stock balances from accidental cascading drops.
  4. **PostgreSQL Database-Enforced Mathematical Invariants:**
     - Non-negativity constraint (`chk_inventory_balances_quantities`):
       $$\text{on\_hand} \ge 0 \land \text{reserved} \ge 0 \land \text{available} \ge 0 \land \text{damaged} \ge 0 \land \text{reorder\_point} \ge 0 \land \text{safety\_stock} \ge 0$$
     - Physical quantity conservation bound (`chk_inventory_balances_bounds`):
       $$\text{reserved\_quantity} + \text{damaged\_quantity} \le \text{on\_hand\_quantity}$$
     - Authoritative available stock derivation formula (`chk_inventory_balances_math`):
       $$\text{available\_quantity} = \text{on\_hand\_quantity} - \text{reserved\_quantity} - \text{damaged\_quantity}$$
  5. **Product Relationship Discipline:**
     - `Product` exposes only `inventoryBalances(): HasMany`.
     - Generic `Product::hasOne(InventoryBalance)` tied to a fixed warehouse is forbidden. Warehouse-specific lookups must be explicit via scopes (`forWarehouse($warehouseId)`) or service/repository queries.
  6. **Idempotent Automatic Stock Initialization & Catalog Backfill:**
     - Creating a `Product` auto-initializes a default zero-quantity `InventoryBalance` row at the canonical default warehouse (`MAIN`), protected by `insertOrIgnore` against `UNIQUE (warehouse_id, product_id)`.
     - Artisan command `php artisan inventory:initialize` (`InitializeInventoryBalancesCommand` + `InventoryInitializationService`) provides idempotent catalog backfill that preserves all existing non-zero balances and never resets inventory counts.
  7. **Foundational Concurrency & Deadlock Prevention:**
     - Optimistic concurrency column `version` (initialized to 1) provides infrastructure for future stock mutation workflows.
     - `InventoryService::lockBalancesForUpdate` enforces deterministic row locking in ascending primary key ID order (`ORDER BY id ASC`), eliminating intra-table deadlocks.
  8. **Stock Status Interpretation:**
     - `OUT_OF_STOCK`: $\text{available\_quantity} \le 0$
     - `LOW_STOCK`: $\text{available\_quantity} > 0 \land \text{reorder\_point} > 0 \land \text{available\_quantity} \le \text{reorder\_point}$
     - `IN_STOCK`: $\text{available\_quantity} > \text{reorder\_point} \lor (\text{available\_quantity} > 0 \land \text{reorder\_point} \le 0)$
  9. **Administrative Visibility & RBAC:**
     - Canonical read-only index endpoint `GET /admin/inventory` protected by `Permission::INVENTORY_VIEW` (`inventory.view`).
     - Authorized: `SUPER_ADMIN`, `ADMIN`, `WAREHOUSE_MANAGER`.
     - Forbidden: `SALESMAN`, `ACCOUNTANT`, `DELIVERY_PARTNER` (HTTP 403 Forbidden).
     - Responsive UI with high-density table for desktop/tablet and purpose-built cards for mobile.
- **Context:** Establishing the operational physical stock storage aggregate before introducing order reservations (`FEAT-INV-003`), movement ledgers (`FEAT-INV-004`), exception reporting (`FEAT-INV-005`), and manual adjustments (`FEAT-INV-006`).
- **Reason:** Enforces `RULE-DOM-001` (Non-Destructive History), `RULE-INV-001` (Atomic Inventory Reservation), `RULE-SEC-001` (Server Authority), and `RULE-SEC-002` (Zero Client Trust).
- **Alternatives Considered:**
  - *Deriving stock solely from order allocations:* Rejected because allocations represent commercial shipping commitments, not physical goods on warehouse shelves.
  - *Hardcoding 'MAIN' string inside product models:* Rejected because multi-warehouse extensibility requires explicit entity relationships.
- **Affected Domains:** Inventory, Products, Warehouses, Orders & Allocations, Security/RBAC.
- **Affected Documents:** PRD §39.2; Technical Architecture §14, §15, §18; Security & Access §18; Frontend Specification §14; Feature Ticket List §19.
- **Affected Tickets:** `FEAT-INV-001`, `FEAT-INV-002`, `FEAT-INV-003`.
- **Consequences:** The system now possesses an authoritative, mathematically enforced physical stock model and administrative workspace.

---

### DEC-015: Delivery Physical Inventory Custody Model B & Anti-IDOR Fail-Closed Architecture
- **Date:** September 2026
- **Status:** `CONFIRMED`
- **Decision:** 
  1. **Model B Physical Custody & Inventory Relief Lifecycle:**
     - Warehouse Pickup (`PICKED_UP`) transfers physical custody to the driver, sets `OrderItemAllocation::dispatched_quantity = picked_quantity`, and sets `orders.fulfillment_status = DISPATCHED`. Physical warehouse balance remains `reserved` (not deducted).
     - Delivery Completion (`DELIVERED`) relieves physical inventory: `on_hand_quantity -= Q`, `reserved_quantity -= Q`, writes an immutable `DISPATCH` entry to `inventory_movements`, and synchronizes `OrderItemAllocation::delivered_quantity`.
     - Return to Warehouse (`RETURNED_TO_WAREHOUSE`) resets `OrderItemAllocation::dispatched_quantity = 0` and sets `orders.fulfillment_status = RESERVED`. Physical stock remains safely in warehouse `reserved` balance (zero double restock, zero double deduction).
  2. **Fail-Closed Anti-IDOR Architecture:**
     - Delivery partner requests querying out-of-scope/unassigned delivery IDs (`GET /delivery/{id}`, `POST /delivery/{id}/*`, `GET /delivery/{id}/history`) strictly throw `NotFoundHttpException` (HTTP 404), completely eliminating mission ID enumeration attacks.
  3. **Immutable Delivery Events & Failures Ledger:**
     - `delivery_events` table uses `ON DELETE RESTRICT` foreign keys and Eloquent model event listeners blocking `updating` and `deleting` with `LogicException`.
- **Context:** Establishing deterministic chain-of-custody, inventory conservation, and driver security for Phase 08 logistics operations.
- **Reason:** Enforces `RULE-DOM-001` (Non-Destructive History), `RULE-INV-001` (Atomic Inventory Reservation), `RULE-SEC-001` (Server Authority), `RULE-SEC-002` (Zero Client Trust), and `RULE-SEC-003` (Resource Scope & IDOR Protection).
- **Alternatives Considered:**
  - *Model A (Deducting physical stock upon pickup and adding back upon return):* Rejected due to risks of phantom stock leakage and double restock bugs during interrupted returns.
  - *Returning HTTP 403 on driver IDOR access:* Rejected because 403 confirms the existence of a delivery ID to malicious actors, enabling enumeration.
- **Affected Domains:** Logistics, Delivery, Inventory, Orders, Security/RBAC.
- **Affected Documents:** PRD §38; Technical Architecture §16; Security & Access §14; Frontend Specification §15; Feature Ticket List §17.
- **Affected Tickets:** `FEAT-DEL-001` through `FEAT-DEL-008`.
- **Consequences:** The system maintains 100% mathematical inventory conservation and driver transit isolation.

---

## Open Decisions & TBD Register

The following items are recognized as open client policy items and must remain configurable until confirmed:

- **DEC-TBD-001:** Tax jurisdiction logic & exemption certificate processing (PRD §11.7).
- **DEC-TBD-002:** Tax rounding policy per line vs per invoice.
- **DEC-TBD-003:** Exact cheque bounce administrative fee policy.
- **DEC-TBD-004:** Exact Money Order verification and shift reconciliation cadence.
- **DEC-TBD-005:** Whether unverified pending cheque payments reduce customer credit exposure immediately or upon verification.
- **DEC-TBD-006:** Default policy for customer balances after downward adjustment: automated account credit vs cash refund.
- **DEC-TBD-007:** Exact revenue recognition timing: upon dispatch vs upon delivery.
- **DEC-TBD-008:** Invoice sequential numbering format and prefix.
