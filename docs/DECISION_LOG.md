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
