# BUILD_PHASES.md — Authoritative Implementation Roadmap

## Wholesale Distribution Management System

**Document Version:** 1.0  
**Effective Date:** September 2026  
**Status:** Authoritative Baseline  
**Alignment:** Fully reconciled with [docs/Wholesale_Distribution_Management_System_Feature_Ticket_List.md](file:///f:/Wholesale%20Distribution%20Management%20System/docs/Wholesale_Distribution_Management_System_Feature_Ticket_List.md) (§38) and Milestone Gates A through F.

---

## 1. Roadmap & Milestone Gates Overview

The implementation roadmap is divided into **12 vertical-slice execution phases** (Phase 00 through Phase 11). Each phase delivers a testable, end-to-end capability that satisfies a formal Milestone Gate before the next phase may begin.

```text
GATE A: FOUNDATION
  ├── Phase 00: Foundation & Core Application Infrastructure
  └── Phase 01: Identity, Authentication & Access Control (RBAC)

GATE B: COMMERCE SPINE
  ├── Phase 02: Master Data (Company, Customers, Salesmen, Products, Categories)
  ├── Phase 03: Commerce Rules (Pricing Boundaries & Product-Specific Tax)
  ├── Phase 04: Salesman Order Creation Slice
  └── Phase 05: Admin Order Processing & Operational Queues

GATE C: OPERATIONS & INVENTORY INTEGRITY
  └── Phase 06: Quantity Allocation, Order Adjustment & Inventory Reservation

GATE D: FINANCIAL TRANSACTIONS
  ├── Phase 07: Payments (Cash/Cheque/Money Order + JPEG) & Invoicing
  └── Phase 09: Receivables, Payables & Double-Entry General Ledger

GATE E: LOGISTICS & POST-DELIVERY EXCEPTIONS
  └── Phase 08: Delivery Operations, Returns & Credit Notes / Refunds

GATE F: PRODUCTION READINESS & GO-LIVE
  ├── Phase 10: Reporting, Analytics, Notifications & Activity Auditing
  └── Phase 11: Production Hardening, Security/E2E QA & AWS Deployment
```

---

## PHASE 00 — Foundation & Application Bootstrap

- **Milestone Gate:** Part of Gate A (Foundation)
- **Objective:** Establish the baseline Laravel 13, React 19.2, Inertia 3, PostgreSQL 18, Tailwind CSS 4, and shadcn/ui application skeleton.
- **Why this phase exists:** Provides the bedrock runtime, database connectivity, build pipelines, error handling, and visual primitives required for all subsequent domain features.
- **Included Tickets:**
  - `TECH-FOUND-001`: Repository & Application Bootstrap
  - `TECH-FOUND-002`: Database & Migration Foundation
  - `TECH-FOUND-003`: Global Error & Logging Foundation
  - `TECH-FOUND-004`: Queue & Cache Foundation (Redis)
  - `UI-001`: Design Tokens (Typography, Colors, Spacing, Breakpoints)
  - `UI-002`: Core Component Library (shadcn/ui tailoring)
- **Dependencies:** None (initial phase).
- **Inputs:** Architecture specification (§1, §2, §6), Design System specification (Doc 04 §4).
- **Outputs:** Bootable full-stack application, working database migrations, test database configuration, foundational UI components.
- **Expected Files/Modules:**
  - `docker-compose.yml`, `.env.example`
  - `vite.config.ts`, `tailwind.config.ts`, `tsconfig.json`
  - `app/Providers/*`, `config/database.php`, `config/logging.php`
  - `resources/js/app.tsx`, `resources/js/Components/ui/*`
- **Critical Business Risks:** Fragile local environment setup, incorrect database collation/timezone, unconfigured secrets.
- **Required Tests:** Application boot smoke test, PostgreSQL connection test, Vite asset build validation.
- **Required Security Validation:** Verify no default passwords or API keys are committed to git.
- **Responsive Requirements:** Verify design token breakpoints: Mobile (375px), Tablet (768px), Desktop (1280px).
- **Exit Criteria:** `php artisan test` runs green; `npm run build` succeeds; database seeds clean sample record.
- **Demo Checklist:** Developer boots container, runs migrations, displays styled UI component showcase in browser.
- **Phase Report Requirement:** Create `docs/reports/PHASE-00-FOUNDATION.md`.
- **Next-Phase Dependency:** Unlocks Phase 01.

---

## PHASE 01 — Identity, Authentication & Access Control (RBAC)

- **Milestone Gate:** Completion of Gate A (Foundation)
- **Objective:** Implement centralized multi-role authentication, session management, privileged MFA, role models, permission registries, and resource scoping.
- **Why this phase exists:** Every subsequent business action requires a verified identity, assigned role, and enforced permissions.
- **Included Tickets:**
  - `FEAT-AUTH-001`: Centralized Login
  - `FEAT-AUTH-002`: Logout & Session Revocation
  - `FEAT-AUTH-003`: Password Reset
  - `FEAT-AUTH-004`: Privileged MFA
  - `FEAT-RBAC-001`: Role Model (`SUPER_ADMIN`, `ADMIN`, `ACCOUNTANT`, `SALESMAN`, `WAREHOUSE_MANAGER`, `DELIVERY_PARTNER`)
  - `FEAT-RBAC-002`: Permission Registry (`module.action` default-deny)
  - `FEAT-RBAC-003`: Resource Scope Enforcement
- **Dependencies:** Phase 00.
- **Inputs:** Security & Access document (Doc 03 §5, §6, §7).
- **Outputs:** Multi-guard authentication, login throttling, permission middleware, user session controls.
- **Expected Files/Modules:**
  - `app/Models/User.php`, `app/Models/Role.php`, `app/Models/Permission.php`
  - `app/Http/Middleware/CheckPermission.php`, `app/Http/Middleware/EnsureResourceScope.php`
  - `resources/js/Pages/Auth/*`
- **Critical Business Risks:** Broken access control, privilege escalation, bypass of resource scoping (IDOR).
- **Required Tests:** Login throttling test, invalid password lockout, unauthorized route 403 test, cross-resource scope isolation test.
- **Required Security Validation:** Strict server-side permission checks; sessions invalidated on role change.
- **Responsive Requirements:** Mobile-friendly login and password reset forms with clear touch targets.
- **Exit Criteria:** All 6 roles can authenticate, redirect to their designated workspaces, and are strictly blocked from unauthorized actions.
- **Demo Checklist:** Log in as Salesman (cannot access `/admin`); log in as Admin (access granted); log in with bad password (throttled).
- **Phase Report Requirement:** Create `docs/reports/PHASE-01-IDENTITY-ACCESS.md`.
- **Next-Phase Dependency:** Unlocks Phase 02.

---

## PHASE 02 — Master Data Management

- **Milestone Gate:** Part of Gate B (Commerce Spine)
- **Objective:** Implement CRUD, lifecycle management, and assignment logic for Customers, Salesmen, Products, and Categories.
- **Why this phase exists:** Commercial transactions cannot exist without customers, salesmen, and product inventory items.
- **Included Tickets:**
  - `FEAT-SYS-001`: Configurable Application Identity
  - `FEAT-SYS-002`: Company Settings
  - `FEAT-CUS-001`: Customer CRUD
  - `FEAT-CUS-002`: Customer Assignment to Salesman
  - `FEAT-CUS-003`: Customer Profile & Outstanding View
  - `FEAT-CUS-004`: Customer Lifecycle Controls (`ACTIVE`, `ON_HOLD`, `INACTIVE`)
  - `FEAT-SLM-001`: Salesman Account Management
  - `FEAT-SLM-002`: Salesman Customer Scope Verification
  - `FEAT-PROD-001`: Product CRUD
  - `FEAT-PROD-002`: Product Image Management (Private S3 storage)
  - `FEAT-PROD-003`: Product Lifecycle (`ACTIVE`, `INACTIVE`)
  - `FEAT-CAT-001`: Category Management
- **Dependencies:** Phase 01.
- **Inputs:** PRD §7, §8, §9; Technical Architecture §10.
- **Outputs:** Master data database tables, Eloquent models, validation requests, admin management screens.
- **Expected Files/Modules:**
  - `app/Models/Customer.php`, `app/Models/Salesman.php`, `app/Models/Product.php`, `app/Models/Category.php`
  - `app/Http/Controllers/Admin/CustomerController.php`, `app/Http/Controllers/Admin/ProductController.php`
  - `resources/js/Pages/Admin/Customers/*`, `resources/js/Pages/Admin/Products/*`
- **Critical Business Risks:** Accidental cross-assignment of customers; soft-deleted records breaking referential integrity.
- **Required Tests:** Validation tests for unique SKUs/phone numbers; inactive customer order blocking; salesman scoping assertions.
- **Required Security Validation:** Validate image uploads via magic bytes; ensure S3 private storage; prevent IDOR on customer updates.
- **Responsive Requirements:** Data tables with server-side pagination on desktop; responsive stacked cards on mobile.
- **Exit Criteria:** Admin can manage customers, salesmen, categories, and products; salesman can only see assigned customers.
- **Demo Checklist:** Create customer; assign to Salesman A; log in as Salesman B (verify customer is invisible); upload product image.
- **Phase Report Requirement:** Create `docs/reports/PHASE-02-MASTER-DATA.md`.
- **Next-Phase Dependency:** Unlocks Phase 03.

---

## PHASE 03 — Commerce Rules (Pricing & Tax)

- **Milestone Gate:** Part of Gate B (Commerce Spine)
- **Objective:** Implement core commercial pricing boundary enforcement and product-specific line-level tax calculation engines.
- **Why this phase exists:** Orders require guaranteed, server-enforced pricing calculations and line-level tax snapshots before placement.
- **Included Tickets:**
  - `FEAT-PRICE-001`: Pricing Rule Foundation (`min_price <= order_price <= mrp`)
  - `FEAT-PRICE-002`: Authorized Price Override (Audit & permissions)
  - `FEAT-TAX-001`: Product-Level Tax Configuration & Calculation Engine
- **Dependencies:** Phase 02.
- **Inputs:** PRD §10, §11; Technical Architecture §23.
- **Outputs:** `PricingService`, `TaxCalculationService`, `product_tax_profiles` tables, pricing boundary validators.
- **Expected Files/Modules:**
  - `app/Services/Pricing/PricingService.php`
  - `app/Services/Tax/TaxCalculationService.php`
  - `app/Models/TaxProfile.php`, `app/Models/TaxRule.php`
- **Critical Business Risks:** Financial miscalculation, unauthorized discounts below floor price, rounding discrepancies.
- **Required Tests:** Unit tests for price bound violations (reject price < min); unit tests for mixed-tax order lines; precision rounding tests.
- **Required Security Validation:** Permission `pricing.override` strictly required for prices outside bounds; audit record generated.
- **Responsive Requirements:** Clean price selection and tax breakdown preview in product configuration screens.
- **Exit Criteria:** Pricing engine rejects out-of-bound prices; tax engine accurately computes line taxes for mixed tax scenarios.
- **Demo Checklist:** Test valid price; attempt price below minimum (fails); authorize override with reason (succeeds and logs audit).
- **Phase Report Requirement:** Create `docs/reports/PHASE-03-COMMERCE-RULES.md`.
- **Next-Phase Dependency:** Unlocks Phase 04.

---

## PHASE 04 — Salesman Order Creation Slice

- **Milestone Gate:** Part of Gate B (Commerce Spine)
- **Objective:** Deliver a mobile-first salesman ordering workflow: customer selection, product catalogue search, pricing selection, tax calculation, and idempotent order submission.
- **Why this phase exists:** This is the primary commercial entry point for wholesale revenue generation.
- **Included Tickets:**
  - `FEAT-ORD-001`: Salesman Starts New Order
  - `FEAT-ORD-002`: Draft Order Persistence
  - `FEAT-ORD-003`: Order Line Quantity Controls
  - `FEAT-ORD-004`: Order Review & Financial Summary
  - `FEAT-ORD-005`: Order Submission Idempotency
  - `FEAT-ORD-006`: Salesman Order History & Status View
  - `UI-004`: Salesman Application Shell
  - `UI-009`: Order Creation Mobile/Tablet Flow
  - `QA-003`: Order E2E Test Suite
- **Dependencies:** Phase 03.
- **Inputs:** PRD §12; Frontend Specification §7.
- **Outputs:** Working salesman ordering interface, `orders` and `order_items` tables, order submission API.
- **Expected Files/Modules:**
  - `app/Services/Orders/CreateOrderService.php`, `app/Services/Orders/SubmitOrderService.php`
  - `app/Models/Order.php`, `app/Models/OrderItem.php`
  - `resources/js/Pages/Salesman/Orders/*`
- **Critical Business Risks:** Duplicate orders caused by repeated clicks; browser-tampered prices/totals; ordering for unassigned customers.
- **Required Tests:** End-to-end order placement test; idempotency token replay test; zero/negative quantity rejection test.
- **Required Security Validation:** Validate salesman assignment to customer; ensure server recalculates all line and grand totals.
- **Responsive Requirements:** Touch-first catalogue with quick search, stepper controls (+/-), sticky checkout summary bar.
- **Exit Criteria:** Salesman can build, review, and submit an order from a mobile browser; submission creates exact database records.
- **Demo Checklist:** Place order on simulated mobile screen (390px); verify idempotency with double submit; verify order appears in history.
- **Phase Report Requirement:** Create `docs/reports/PHASE-04-SALESMAN-ORDERING.md`.
- **Next-Phase Dependency:** Unlocks Phase 05.

---

## PHASE 05 — Admin Order Operations & Operational Queues

- **Milestone Gate:** Completion of Gate B (Commerce Spine)
- **Objective:** Implement the Admin operational workspace with sub-queues (`New Orders`, `Active Orders`, `Delivery`, `Adjustments`, `Completed`, `Cancelled`, `All Orders`) and order approval/rejection workflows.
- **Why this phase exists:** Admin is the operational middle layer that reviews, verifies credit, and approves orders for warehouse fulfillment.
- **Included Tickets:**
  - `FEAT-ORD-010`: Admin Order Queue Framework (Filtered views over single source of truth)
  - `FEAT-ORD-011`: New Order Review Workspace
  - `FEAT-ORD-012`: Order Approval / Rejection Workflow
  - `FEAT-ORD-013`: Order Detail Master Workspace
  - `UI-003`: Admin Application Shell
- **Dependencies:** Phase 04.
- **Inputs:** PRD §22; Frontend Specification §8.
- **Outputs:** Admin order management interface, operational queues with live badge counts, order status state machine.
- **Expected Files/Modules:**
  - `app/Queries/AdminOrderQueueQuery.php`
  - `app/Services/Orders/ApproveOrderService.php`, `app/Services/Orders/RejectOrderService.php`
  - `resources/js/Pages/Admin/Orders/*`
- **Critical Business Risks:** Concurrency issues during simultaneous admin approvals; orders misplaced across queue views.
- **Required Tests:** Queue filtering tests; transition tests from `SUBMITTED` to `APPROVED` or `REJECTED`; badge count accuracy tests.
- **Required Security Validation:** `order.approve` and `order.reject` permissions required; state machine rejects invalid transitions.
- **Responsive Requirements:** Dense desktop tables with badge indicators, collapsible filters, and side detail inspection sheets.
- **Exit Criteria:** Admin can process incoming orders from `New Orders` queue; approved orders advance to fulfillment queue.
- **Demo Checklist:** Salesman submits order; Admin sees `New Orders (1)` badge; Admin reviews items, approves order; order moves to `Active`.
- **Phase Report Requirement:** Create `docs/reports/PHASE-05-ADMIN-PROCESSING.md`.
- **Next-Phase Dependency:** Unlocks Phase 06.

---

## PHASE 06 — Allocation, Adjustment & Inventory Integrity

- **Milestone Gate:** Completion of Gate C (Operations & Inventory Integrity)
- **Objective:** Implement quantity-based allocation, atomic stock reservations, the Order Adjustment framework, damaged stock classification, and warehouse stock exception workflows.
- **Why this phase exists:** This is the core operational differentiator: orders must tolerate partial fulfillment, post-order damage, and quantity adjustments without destroying original order history.
- **Included Tickets:**
  - `FEAT-ALLOC-001`: Order Item Quantity Allocation Model
  - `FEAT-ALLOC-002`: Allocation Validation & Mathematical Constraints
  - `FEAT-ADJ-001`: Adjustment Request (Salesman/Warehouse)
  - `FEAT-ADJ-002`: Adjustment Review Workspace & Financial Preview
  - `FEAT-ADJ-003`: Adjustment Approval / Rejection
  - `FEAT-ADJ-004`: Atomic Adjustment Application Engine
  - `FEAT-ADJ-005`: Adjustment Reversal
  - `FEAT-ADJ-006`: Adjustment / Exception Queue
  - `FEAT-INV-001`: Inventory Item & Stock Foundation
  - `FEAT-INV-002`: On-Hand / Reserved / Available / Damaged State Balances
  - `FEAT-INV-003`: Reservation / Allocation Integrity (Pessimistic locking)
  - `FEAT-INV-004`: Stock Movement History Ledger
  - `FEAT-INV-005`: Stock Exception Reporting
  - `FEAT-INV-006`: Authorized Inventory Adjustment
  - `QA-004`: Adjustment E2E Test Suite
  - `QA-005`: Inventory Concurrency Test Suite
- **Dependencies:** Phase 05.
- **Inputs:** PRD §13, §14, §15, §16; Technical Architecture §14, §15, §18.
- **Outputs:** `order_adjustments`, `order_adjustment_items`, `inventory_balances`, `inventory_movements` tables, atomic reservation engine.
- **Expected Files/Modules:**
  - `app/Services/Inventory/ReservationService.php`
  - `app/Services/Orders/AdjustOrderService.php`
  - `app/Models/OrderAdjustment.php`, `app/Models/InventoryBalance.php`, `app/Models/InventoryMovement.php`
  - `resources/js/Pages/Admin/Adjustments/*`
- **Critical Business Risks:** Race conditions causing negative inventory; destructive overwrites of original ordered quantities; mismatched tax/financial recalculations.
- **Required Tests:** Concurrent stock reservation test (simulating 2 orders for 1 item); non-destructive quantity test; multi-adjustment math test.
- **Required Security Validation:** Database row locking (`SELECT FOR UPDATE`); permissions for adjustment approval; immutable movement logs.
- **Responsive Requirements:** Clear adjustment preview modal showing original vs adjusted quantities, tax impact, and financial delta.
- **Exit Criteria:** 10 items ordered, 2 damaged → adjustment cancels 2, reserves 8, records damaged stock, recalculates total to 8 items, keeps `ordered_quantity = 10`.
- **Demo Checklist:** Trigger stock exception for damaged goods; Admin inspects adjustment preview; approve adjustment; verify inventory movement and order totals.
- **Phase Report Requirement:** Create `docs/reports/PHASE-06-ALLOCATION-INVENTORY.md`.
- **Next-Phase Dependency:** Unlocks Phase 07 and Phase 08.

---

## PHASE 07 — Payments & Invoicing

- **Milestone Gate:** Part of Gate D (Financial Transactions)
- **Objective:** Implement Cash, Cheque, and Money Order payment workflows, JPEG evidence uploads, payment verification/reversal, and professional invoice generation without product images.
- **Why this phase exists:** Captures wholesale revenue, enforces legal payment evidence controls, and produces authoritative customer billing documents.
- **Included Tickets:**
  - `FEAT-PAY-001`: Payment Method Model (Cash, Cheque, Money Order)
  - `FEAT-PAY-002`: Cash Payment Entry
  - `FEAT-PAY-003`: Cheque Payment Entry
  - `FEAT-PAY-004`: Money Order Payment Entry
  - `FEAT-PAY-005`: Payment Evidence Upload (JPEG validation & private S3 storage)
  - `FEAT-PAY-006`: Payment Evidence Preview (Presigned URLs)
  - `FEAT-PAY-007`: Payment Verification Workflow
  - `FEAT-PAY-008`: Payment Rejection & Rework
  - `FEAT-PAY-009`: Payment Reversal & Bounced Cheque Handling
  - `FEAT-DOC-001`: Invoice Generation Engine
  - `FEAT-DOC-002`: Invoice Print / HTML View (Strictly NO product images)
  - `FEAT-DOC-003`: Invoice PDF Download (Chromium rendering)
  - `FEAT-DOC-004`: Historical Document Snapshot Integrity
  - `UI-008`: Payment Evidence Upload/Preview UI
  - `QA-006`: Payment Evidence Security Tests
  - `QA-007`: Payment Financial Integrity Tests
- **Dependencies:** Phase 06.
- **Inputs:** PRD §19, §20, §21; Technical Architecture §20, §21, §22.
- **Outputs:** `payments`, `payment_evidence`, `invoices`, `invoice_items` tables, S3 upload service, PDF rendering pipeline.
- **Expected Files/Modules:**
  - `app/Services/Payments/RecordPaymentService.php`, `app/Services/Payments/VerifyPaymentService.php`
  - `app/Services/Invoices/InvoiceGeneratorService.php`
  - `app/Models/Payment.php`, `app/Models/Invoice.php`
  - `resources/js/Pages/Admin/Payments/*`, `resources/js/Pages/Shared/InvoiceView.tsx`
- **Critical Business Risks:** Public leak of sensitive cheque images; fake JPEG upload bypass; product images appearing on invoices; duplicate payment entries.
- **Required Tests:** Server-side MIME magic-byte test (reject PNG/exe disguised as JPG); payment verification idempotency test; invoice HTML snapshot test asserting `<img>` tags are absent.
- **Required Security Validation:** S3 bucket configured with `BlockPublicAcls`; presigned URLs expire in $\le 15\text{ minutes}$; `payment.verify` required.
- **Responsive Requirements:** Mobile camera capture for cheque upload; responsive invoice preview; print stylesheet optimization.
- **Exit Criteria:** Cheque recorded with JPEG evidence; verified by Admin; invoice generated with zero product images; PDF downloaded.
- **Demo Checklist:** Record $2,000 cheque with sample JPEG; inspect in Admin; verify payment; preview invoice (verify no images); export PDF.
- **Phase Report Requirement:** Create `docs/reports/PHASE-07-PAYMENTS-INVOICING.md`.
- **Next-Phase Dependency:** Unlocks Phase 08 and Phase 09.

---

## PHASE 08 — Delivery, Returns & Credit Notes / Refunds

- **Milestone Gate:** Completion of Gate E (Logistics & Post-Delivery Exceptions)
- **Objective:** Implement the Delivery Partner logistics workspace, delivery assignment, status lifecycle, proof of delivery, failed delivery logging, return inspections, and credit note / refund processing.
- **Why this phase exists:** Completes physical fulfillment at the customer location and handles real-world post-delivery discrepancies cleanly.
- **Included Tickets:**
  - `FEAT-DEL-001`: Delivery Assignment Engine
  - `FEAT-DEL-002`: Delivery Partner Assigned Queue
  - `FEAT-DEL-003`: Pickup Confirmation
  - `FEAT-DEL-004`: Out-for-Delivery State Transition
  - `FEAT-DEL-005`: Delivered Confirmation
  - `FEAT-DEL-006`: Delivery Failure Logging (Structured reason codes)
  - `FEAT-DEL-007`: Delivery Reschedule Workflow
  - `FEAT-DEL-008`: Delivery History & Audit
  - `FEAT-RET-001`: Return Request Creation
  - `FEAT-RET-002`: Return Review & Warehouse Inspection
  - `FEAT-RET-003`: Return Approval & Stock Disposition
  - `FEAT-RET-004`: Return Inventory Movement Execution
  - `FEAT-CR-001`: Credit Eligibility Calculation Engine
  - `FEAT-CR-002`: Credit Note Generation
  - `FEAT-CR-003`: Refund Request
  - `FEAT-CR-004`: Refund Approval
  - `FEAT-CR-005`: Refund Processing / Reversal Safety
  - `UI-005`: Delivery Application Shell (Mobile-first)
- **Dependencies:** Phase 06, Phase 07.
- **Inputs:** PRD §18, §23, §24; Frontend Specification §9.
- **Outputs:** `delivery_assignments`, `returns`, `return_items`, `credit_notes`, `refunds` tables, mobile delivery workspace.
- **Expected Files/Modules:**
  - `app/Services/Delivery/DeliveryWorkflowService.php`
  - `app/Services/Returns/ProcessReturnService.php`
  - `app/Services/Finance/CreditNoteService.php`
  - `resources/js/Pages/Delivery/*`
- **Critical Business Risks:** Delivery partner modifying financial terms; driver viewing unassigned deliveries; returns creating duplicate inventory or unapproved refunds.
- **Required Tests:** Delivery driver scoping tests; failed delivery reason validation; return quantity $\le$ delivered quantity constraint test; credit note calculation test.
- **Required Security Validation:** Delivery driver role strictly confined to assigned delivery transitions; `refund.approve` required for cash refunds.
- **Responsive Requirements:** High-contrast mobile driver screen with large touch targets ($\ge 44\text{px}$) and offline-resilient submission.
- **Exit Criteria:** Driver accepts assignment, marks delivered; customer returns 1 unit; warehouse inspects and accepts; Credit Note issued.
- **Demo Checklist:** Admin assigns driver; driver logs in on mobile, marks out for delivery and delivered; submit return; generate credit note.
- **Phase Report Requirement:** Create `docs/reports/PHASE-08-DELIVERY-RETURNS.md`.
- **Next-Phase Dependency:** Unlocks Phase 09.

---

## PHASE 09 — Receivables, Payables & General Ledger Accounting

- **Milestone Gate:** Completion of Gate D (Financial Transactions)
- **Objective:** Implement the customer receivable ledger, aging buckets (0-30, 31-60, 61-90, 90+ days), customer statements, supplier payables foundation, and immutable double-entry general ledger journal posting.
- **Why this phase exists:** Provides financial transparency, accounts receivable management, audit-proof reconciliations, and general ledger integrity.
- **Included Tickets:**
  - `FEAT-AR-001`: Customer Receivable Ledger
  - `FEAT-AR-002`: Customer Aging Engine (0-30, 31-60, 61-90, 90+ days)
  - `FEAT-AR-003`: Customer Statement Generation
  - `FEAT-AP-001`: Supplier Payables Foundation
  - `FEAT-ACC-001`: Chart of Accounts
  - `FEAT-ACC-002`: Journal Entry Foundation (Double-entry validation)
  - `FEAT-ACC-003`: Event-to-Journal Mapping Engine
  - `FEAT-ACC-004`: General Ledger
  - `FEAT-ACC-005`: Trial Balance Report
  - `FEAT-ACC-006`: Profit & Loss Report
  - `FEAT-ACC-007`: Balance Sheet Report
  - `FEAT-ACC-008`: Controlled Accounting Reversals (Immutability rule)
  - `FEAT-ACC-009`: Cash Collection Reconciliation
  - `QA-008`: Accounting Integrity Test Suite
- **Dependencies:** Phase 07, Phase 08.
- **Inputs:** PRD §26, §27, §28; Technical Architecture §25.
- **Outputs:** `customer_transactions`, `accounts`, `journal_entries`, `journal_lines` tables, financial statement generator.
- **Expected Files/Modules:**
  - `app/Services/Accounting/JournalPostingService.php`
  - `app/Services/Accounting/ReceivableService.php`
  - `app/Models/JournalEntry.php`, `app/Models/JournalLine.php`, `app/Models/Account.php`
  - `resources/js/Pages/Admin/Accounting/*`
- **Critical Business Risks:** Unbalanced journal entries ($\text{Debits} \ne \text{Credits}$); deletion of posted accounting records; inaccurate aging calculations.
- **Required Tests:** Double-entry balancing assertion; rejection of `UPDATE`/`DELETE` queries on posted journal lines; customer statement balance verification.
- **Required Security Validation:** Role `ACCOUNTANT` or `SUPER_ADMIN` strictly required; all manual journal entries audited.
- **Responsive Requirements:** Dense financial tabular layouts on desktop with aligned numbers (`font-mono`), export to CSV/PDF.
- **Exit Criteria:** Sale event posts Debit AR / Credit Revenue; payment posts Debit Cash / Credit AR; Trial Balance nets to zero; customer statement is accurate.
- **Demo Checklist:** Trigger invoice, view generated journal; trigger payment, view journal; view Customer Statement and Trial Balance.
- **Phase Report Requirement:** Create `docs/reports/PHASE-09-RECEIVABLES-ACCOUNTING.md`.
- **Next-Phase Dependency:** Unlocks Phase 10.

---

## PHASE 10 — Reporting, Notifications & Activity Auditing

- **Milestone Gate:** Part of Gate F (Production Readiness)
- **Objective:** Implement sales, customer, salesman, inventory, delivery, and accounting reports; in-app operational notifications; and immutable business/security audit logging.
- **Why this phase exists:** Equips management with operational intelligence, informs users of urgent workflow tasks, and satisfies regulatory compliance.
- **Included Tickets:**
  - `FEAT-REP-001`: Sales Reports (Day, period, customer, product)
  - `FEAT-REP-002`: Customer Reports (Outstanding & purchase history)
  - `FEAT-REP-003`: Salesman Performance Reports
  - `FEAT-REP-004`: Inventory Reports (Valuation, movement, low stock)
  - `FEAT-REP-005`: Delivery Performance Reports
  - `FEAT-REP-006`: Financial & Accounting Reports
  - `FEAT-NOTIF-001`: In-App Operational Notifications
  - `FEAT-NOTIF-002`: Notification Preferences Foundation
  - `FEAT-AUD-001`: Business Audit Event Logger
  - `FEAT-AUD-002`: Security Event Logging
  - `FEAT-AUD-003`: Audit Immutability Enforcement
  - `FEAT-AUD-004`: Activity Timeline UI
- **Dependencies:** Phase 09.
- **Inputs:** PRD §29, §30, §32; Technical Architecture §27.
- **Outputs:** Reporting query services, `notifications`, `audit_logs` tables, activity timeline components.
- **Expected Files/Modules:**
  - `app/Queries/Reports/*`, `app/Services/Audit/AuditLogger.php`
  - `app/Models/AuditLog.php`
  - `resources/js/Pages/Admin/Reports/*`, `resources/js/Components/ActivityTimeline.tsx`
- **Critical Business Risks:** Slow analytical queries locking operational tables; unauthorized access to audit trails; audit record tampering.
- **Required Tests:** Report reconciliation against transactional tables; audit logging assertions on critical actions; audit table immutability tests.
- **Required Security Validation:** Audit logs are read-only; permissions enforced on financial report views.
- **Responsive Requirements:** Mobile-friendly dashboard summary cards, adaptive charts that do not overlap labels on tablet screens.
- **Exit Criteria:** Admin can run sales, inventory, and accounting reports; all sensitive state mutations appear in activity timelines.
- **Demo Checklist:** Run Sales by Customer report; review Audit Log for recent order adjustment; check notification bell for new orders.
- **Phase Report Requirement:** Create `docs/reports/PHASE-10-REPORTS-AUDIT.md`.
- **Next-Phase Dependency:** Unlocks Phase 11.

---

## PHASE 11 — Production Hardening, Security/E2E QA & AWS Deployment

- **Milestone Gate:** Final Gate F (Production Readiness & Go-Live)
- **Objective:** Execute comprehensive cross-browser and mobile responsive testing, perform security and penetration testing, run end-to-end edge-case suites, configure CI/CD pipelines, and deploy to AWS infrastructure.
- **Why this phase exists:** Ensures the application is secure, resilient, performant, backed up, and production-ready for client handover.
- **Included Tickets:**
  - `UI-006`: Responsive Table System Hardening
  - `UI-007`: Form State System Polish
  - `UI-010`: Responsive QA Width Matrix Validation (320px to 1920px)
  - `QA-001`: Authentication Test Suite
  - `QA-002`: Authorization & IDOR Penetration Test Suite
  - `QA-009`: Responsive Regression Suite
  - `QA-010`: Accessibility Baseline Audit (WCAG 2.1 AA)
  - `TECH-QA-001`: Performance & Query Optimization Baseline
  - `DEPLOY-001`: Staging Environment Configuration
  - `DEPLOY-002`: Production AWS Infrastructure Baseline (EC2/RDS/ElastiCache/S3)
  - `DEPLOY-003`: GitHub Actions CI/CD Pipeline
  - `DEPLOY-004`: Database Backup & Restore Disaster Recovery Validation
  - `DEPLOY-005`: Production Security & SSL/TLS Hardening Checklist
- **Dependencies:** Phase 10.
- **Inputs:** Technical Architecture §30, §31, §32; Security & Access §14, §15, §16.
- **Outputs:** GitHub Actions CI/CD workflows, AWS deployment scripts, backup automation, final QA sign-off report.
- **Expected Files/Modules:**
  - `.github/workflows/ci.yml`, `.github/workflows/deploy.yml`
  - `docker/production/*`, AWS CloudFormation / Terraform scripts
- **Critical Business Risks:** Uncaught edge cases in production; deployment failures; unencrypted data in transit or at rest.
- **Required Tests:** Complete edge case register (`EDGE-001` through `EDGE-025`); full automated regression suite; Lighthouse audit score $\ge 90$.
- **Required Security Validation:** OWASP Top 10 verification, SSL Labs A+ rating, AWS S3 block public access confirmed.
- **Responsive Requirements:** 100% of screens pass visual regression on Mobile S (320px), Mobile (375px), Tablet (768px), and Desktop XL (1920px).
- **Exit Criteria:** Staging environment operational; CI/CD deploys automatically on tag; zero failing tests; backup restore verified; client sign-off.
- **Demo Checklist:** Automated CI pipeline passes; staging deployment verified; execute sample order-to-accounting flow on live staging.
- **Phase Report Requirement:** Create `docs/reports/PHASE-11-PRODUCTION-READINESS.md`.
- **Next-Phase Dependency:** Production Go-Live and Handover.
