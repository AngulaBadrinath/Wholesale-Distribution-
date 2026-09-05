# AI_CONTEXT.md — High-Signal Project Snapshot

## Wholesale Distribution Management System

**Document Version:** 1.0  
**Last Verified:** September 4, 2026  
**Purpose:** Compact, high-signal operational snapshot for rapid AI agent orientation.

---

## 1. Project Overview

- **Project Name:** Wholesale Distribution Management System (Working title; configurable/replaceable)
- **Domain:** B2B Wholesale Distribution, ERP, Logistics & Financial Accounting
- **Initial Scale:** 500–700 wholesale customers, scaling to thousands
- **Currency:** USD ($)
- **Primary Market:** United States
- **Target Audience:** Solo Full-Stack Developer + AI Coding Agents (Antigravity/Claude/Gemini)

---

## 2. Core Architecture Baseline

- **Architecture Style:** Modular Monolith (Shared Transactional Core)
- **Backend Framework:** Laravel 13 (PHP 8.5)
- **Frontend Framework:** React 19.2 + TypeScript + Inertia 3
- **Styling & UI:** Tailwind CSS 4 + shadcn/ui + Vite
- **Primary Database:** PostgreSQL 18 (Authoritative transactional source of truth)
- **Cache / Queues:** Redis (ElastiCache in production)
- **File / Object Storage:** Amazon S3 (Private storage with short-lived presigned URLs)
- **Document / PDF Rendering:** Chromium-based PDF renderer (e.g. Browsershot / Puppeteer)
- **Observability & Error Tracking:** Sentry + AWS CloudWatch
- **Infrastructure:** AWS EC2 (initially) / Docker Compose (local development)

---

## 3. Application Surfaces & Portals

The system provides three dedicated user portals and two operational role scopes over **ONE shared transaction core**:

1. **Admin Portal (`/admin/*`):** Operational Control Center for reviewing orders, approving adjustments, managing inventory exceptions, coordinating delivery, and reconciling payments.
2. **Salesman Portal (`/salesman/*`):** Mobile-first sales workspace for catalog browsing, price selection within allowed ranges, order creation, payment recording, and evidence upload.
3. **Delivery Partner Portal (`/delivery/*`):** Task-first logistics workspace for assigned delivery acceptance, pickup, out-for-delivery, proof of delivery, and failure reporting.
4. **Warehouse Manager (Operational Scope):** Stock fulfillment, reservation confirmation, physical stock exceptions, and inventory movement logging.
5. **Accountant (Permissioned Scope):** Payment verification, receivable aging, credit notes, refunds, and double-entry general ledger journal posting.

---

## 4. End-to-End Operational Workflow

```text
Salesman (Order Creation)
   ↓
Admin (Review & Approval)
   ↓
Warehouse (Stock Reservation & Exception Handling)
   ↓
Delivery (Assignment, Dispatch & Proof of Delivery)
   ↓
Customer (Fulfillment, Invoicing & Receivables)
```

Independent transaction streams proceed in parallel:
- **Payments:** Recorded at any phase (Pre-pay, Cash on Delivery, Terms).
- **Invoices:** Rendered on demand from immutable historical line snapshots (NO product images).
- **Adjustments:** Handled via explicit non-destructive adjustment records.

---

## 5. Core Domains & Responsibilities

| Domain | Key Entities & Responsibilities |
|---|---|
| **Identity & Access** | `users`, `roles`, `permissions`, `sessions` (RBAC, MFA, resource scoping) |
| **Customers** | `customers`, credit limits, payment terms, assigned salesmen, outstanding balances |
| **Salesmen** | `salesmen`, customer assignment scoping, commission tracking |
| **Products & Categories** | `products`, `categories`, cost price, list price (MRP), default selling price, minimum price |
| **Pricing & Tax** | Price boundaries (`min <= price <= mrp`), product-specific tax profiles, line-item tax snapshots |
| **Orders** | `orders`, `order_items`, independent lifecycle states (order, fulfillment, payment, delivery) |
| **Allocations & Adjustments** | `order_item_allocations`, `order_adjustments`, partial cancellation without destroying original qty |
| **Inventory & Warehouse** | `inventory_balances` (on hand, reserved, available, damaged), `inventory_movements` ledger |
| **Payments** | `payments` (Cash, Cheque, Money Order), `payment_evidence` (JPEG only, private S3) |
| **Invoices / Documents** | `invoices`, `invoice_items`, server-side PDF generation, strictly NO product images |
| **Delivery** | `delivery_assignments`, assigned driver queue, delivery attempts, failure logging |
| **Returns & Credits** | `returns`, inspection, accepted/rejected quantities, `credit_notes`, refund approval |
| **Receivables & Payables** | Customer statements, 0-30/31-60/61-90/90+ day aging, supplier billing |
| **Accounting** | Chart of accounts, `journal_entries`, `journal_lines`, double-entry immutability |
| **Audit & Activity** | Immutable business audit log (`audit_logs`) and security log |

---

## 6. Critical Business Invariants (Top Rules)

1. **RULE-DOM-001 (Non-Destructive Quantities):** Never overwrite `ordered_quantity` when cancelling, adjusting, or returning items.
2. **RULE-ORD-002 (Order Adjustments):** Post-order modifications require explicit `order_adjustments` records.
3. **RULE-PRI-001 & RULE-TAX-002 (Immutable Snapshots):** Historical order line price and tax snapshots are permanently preserved.
4. **RULE-PRI-002 (Price Bounds):** `minimum_allowed_price <= actual_order_price <= mrp/list_price` enforced server-side.
5. **RULE-TAX-001 (Product-Specific Tax):** Tax is line-item specific; multiple rates and exemptions exist within a single order.
6. **RULE-PAY-001 (Payment Methods):** V1 supports Cash, Cheque, and Money Order only.
7. **RULE-PAY-002 (Payment Evidence):** Cheque and Money Order require mandatory JPEG uploads stored privately in S3.
8. **RULE-DOC-001 (Invoice Images):** **Product images must NEVER appear on invoices.**
9. **RULE-INV-001 (Transactional Reservation):** Inventory reservations use row-level locking (`SELECT FOR UPDATE`); available stock cannot be negative.
10. **RULE-ACC-001 (Accounting Immutability):** Posted general ledger entries are never deleted; corrections use reversing journals.
11. **RULE-SEC-001 (Server Authority):** The frontend is never a security boundary. All validations and permissions are server-enforced.

---

## 7. Current Project Status (Actual Repository State)

- **Overall Progress:** 32.0% code implementation (41 / 128 tickets completed; specifications 100% complete)
- **Current Phase:** Phase 06 — Allocation, Adjustment & Inventory Integrity (Status: `IN_PROGRESS`)
- **Current Active Ticket:** `FEAT-ADJ-001` (Order Adjustment Request Flow completed)
- **Active Git Branch:** `feature/FEAT-ADJ-001-adjustment-request-flow`
- **Working Tree State:** Clean; 828 automated tests passing (100%), 5,260 assertions (7 skipped), TypeScript passing, Vite build passing
- **Source Code Status:** Centralized authentication, session lifecycle, account-state validation, active session tracking, IDOR-safe revocation, enumeration-resistant password reset, session invalidation on reset, privileged TOTP multi-factor authentication, single-use recovery codes, step-up password confirmation, canonical six-role definition (`SUPER_ADMIN`, `ADMIN`, `ACCOUNTANT`, `SALESMAN`, `WAREHOUSE_MANAGER`, `DELIVERY_PARTNER`), atomic role assignment with row locking, last-super-admin guard, target session invalidation, security audit logging, 48 canonical permissions registry enum, in-memory role-to-permission mapping, default-deny PermissionService, account lifecycle gating, Gate::before integration, route permission middleware, safe Inertia capability sharing, centralized ApplicationIdentityService / config-backed identity, database-backed singleton CompanyInformationService / business settings, production-grade Customer master-data CRUD foundation, Customer-to-Salesman assignment and scoping foundation, production-grade Customer Profile & Commercial Hub, authoritative Customer Lifecycle Controls, production-grade Salesman Account Management & Lifecycle, comprehensive Salesman Scoped Customer Access Enforcement, production-grade Product Master CRUD (`FEAT-PROD-001`), private S3 Product Image Storage (`FEAT-PROD-002`), authoritative Product Lifecycle Controls (`FEAT-PROD-003`), comprehensive Product Category Management & Hierarchy (`FEAT-CAT-001`), authoritative Price Boundary Constraint Enforcement (`FEAT-PRICE-001`), authorized Price Override Engine with Auditing (`FEAT-PRICE-002`), Product-Specific Tax Profile Engine & Order-Line Snapshot (`FEAT-TAX-001`), flagship Salesman Order Creation Flow (`FEAT-ORD-001`), Draft Order Persistence & Resumption (`FEAT-ORD-002`), Order Line Quantity Stepper & Validation Controls (`FEAT-ORD-003`), Order Review, Line Tax Breakdown & Financial Summary (`FEAT-ORD-004`), Order Submission Idempotency Enforcement (`FEAT-ORD-005`), Salesman Order History & Multi-State Timeline (`FEAT-ORD-006`), Admin Order Queue Framework (`FEAT-ORD-010`), New Order Review Workspace (`FEAT-ORD-011`), Order Approval / Rejection Workflow with Audit (`FEAT-ORD-012`), canonical Order Detail Master Workspace (`FEAT-ORD-013`), canonical Order Item Quantity Allocation Model (`FEAT-ALLOC-001`), Allocation Validation & Mathematical Constraints (`FEAT-ALLOC-002`), and authoritative Order Adjustment Request Flow (`FEAT-ADJ-001`) featuring non-destructive quantity reduction requests via `order_adjustments` and `order_adjustment_items` (migration `2026_09_05_000013`), `restrictOnDelete()` on child lines, single-open-request invariant enforced via PostgreSQL partial unique index `idx_order_adjustments_single_open` and pessimistic row locks, sequential numbering `ADJ-{order}-{seq}`, Case A (unallocated) vs Case B (allocation-impacting with `affected_allocation_quantity`) classification, informational financial projections using BCMath and `TaxCalculationService::roundHalfUp` without mutating baseline order/item financials or inventory allocations, deterministic idempotency replay vs 409 payload conflict detection, requester cancellation/withdrawal restoring order status, full server-side scoping (Salesman assigned orders; Warehouse Manager approved/processing orders; Admin broad scope), lifecycle gating (SUBMITTED/PENDING_APPROVAL/APPROVED/PROCESSING allowed; DRAFT/COMPLETED/CANCELLED/REJECTED blocked 409), minimal responsive UI modal and pending adjustment banner, 30 automated feature and concurrency tests, and 828 total repository tests passing 100%.
- **Active Blockers / Deferrals:** `FEAT-RBAC-003` deferred per DEC-014 pending Delivery domain entity model in Phase 08 (`Delivery`); single central warehouse confirmed for V1 per PRD §39.2; physical warehouse inventory balance and stock ledger reservation deferred to Phase 06 (`FEAT-INV-001..004`)
- **Next Immediate Action:** Advance in Phase 06 with `FEAT-ADJ-002: Adjustment Review Workspace with Real-Time Financial & Tax Impact Preview` or `UI-003: Admin Application Shell`

