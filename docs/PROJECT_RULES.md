# PROJECT_RULES.md — Software & Business Invariant Constitution

## Wholesale Distribution Management System

**Document Version:** 1.0  
**Effective Date:** September 2026  
**Status:** Authoritative Baseline  
**Authority:** Derived from Documents 01 (PRD), 02 (Technical Architecture), 03 (Security & Access), 04 (Frontend Specification), and 05 (Feature Tickets).

---

## 1. Architectural Rules (`RULE-ARCH`)

- **RULE-ARCH-001 (Modular Monolith):** The system must be implemented as a modular monolith in Laravel 13. Services and domain logic must be encapsulated within domain module boundaries (e.g., `App\Modules\Orders`, `App\Modules\Inventory`). Cross-module calls must occur through explicit Application Services or Domain Events, never through direct arbitrary database mutations across domain boundaries.
- **RULE-ARCH-002 (Shared Transactional Core):** All user portals (Admin, Salesman, Delivery Partner) and operational roles (Warehouse Manager, Accountant) operate against one unified PostgreSQL database and transaction core. Creating separate databases or decoupled microservices for portals is strictly prohibited.
- **RULE-ARCH-003 (PostgreSQL Source of Truth):** PostgreSQL 18 is the sole authoritative transactional data store. Redis is strictly a cache, queue, and ephemeral lock manager; it must never be treated as the persistent or authoritative store for inventory, orders, or accounting data.
- **RULE-ARCH-004 (Private Object Storage):** All sensitive business files (payment cheques, money orders, invoices) must be stored in private Amazon S3 buckets. The database stores only object metadata and storage keys. Public S3 bucket policies or public object URLs are prohibited.
- **RULE-ARCH-005 (Configurable Product Identity):** The application display name, branding, and company metadata must be stored as configurable settings (`RULE-SYS-001`). No business logic, route path, database schema, or UI component may hard-code the working product name.

---

## 2. Domain & Entity Integrity (`RULE-DOM`)

- **RULE-DOM-001 (Non-Destructive History):** Business history is permanent. Once an order, allocation, payment, invoice, or journal entry is committed, it must never be destructively overwritten to represent subsequent modifications. Modifications are represented through explicit adjustment, movement, or reversing records.
- **RULE-DOM-002 (Transactional Atomicity):** Any business operation involving multiple table mutations (such as applying an adjustment that alters item quantities, inventory reservations, tax totals, and financial liabilities) must execute within a database transaction (`DB::transaction`). Partial state commits are prohibited.
- **RULE-DOM-003 (Idempotent Critical Commands):** Operations subject to network retries or double clicks (order submission, payment verification, adjustment application, delivery confirmation, refund processing) must enforce idempotency via unique idempotency tokens or deterministic database state locks.

---

## 3. Order Rules (`RULE-ORD`)

- **RULE-ORD-001 (Independent State Dimensions):** An order's lifecycle must never be collapsed into a single status column. The system must independently track:
  - `status`: `DRAFT`, `SUBMITTED`, `PENDING_APPROVAL`, `APPROVED`, `PROCESSING`, `COMPLETED`, `CANCELLED`, `REJECTED`
  - `fulfillment_status`: `UNALLOCATED`, `RESERVED`, `PICKED`, `PACKED`, `DISPATCHED`, `DELIVERED`, `PARTIALLY_DELIVERED`, `RETURNED`
  - `payment_status`: `UNPAID`, `PARTIALLY_PAID`, `PAID`, `OVERPAID`, `REFUNDED`
  - `delivery_status`: `PENDING_ASSIGNMENT`, `ASSIGNED`, `ACCEPTED`, `PICKED_UP`, `OUT_FOR_DELIVERY`, `DELIVERED`, `FAILED`
  - `adjustment_status`: `NONE`, `REQUESTED`, `APPLIED`, `REVERSED`
- **RULE-ORD-002 (Order Adjustment Exclusivity):** Once an order moves past `DRAFT` / `SUBMITTED` into `APPROVED`, line items cannot be directly updated or deleted. Any modification to quantities, prices, or cancellation of lines must proceed via the Order Adjustment Framework (`order_adjustments`).
- **RULE-ORD-003 (Salesman Order Scope):** Salesmen can only create and view orders for customers currently assigned to them (`RULE-SEC-003`). Order submission validates that the customer is `ACTIVE` and within approved credit limits.
- **RULE-ORD-004 (Admin Review Queue):** Submitted orders requiring review enter the `New Orders` queue (`FEAT-ORD-010`). Admin approval transitions the order to `APPROVED` and triggers atomic inventory reservation.
- **RULE-ORD-005 (Derived Order Totals):** `subtotal`, `tax_total`, `adjustment_total`, and `grand_total` must be authoritatively calculated by the backend from line item data. Never store or trust client-calculated totals.

---

## 4. Quantities & Allocation Rules (`RULE-ALLOC`)

- **RULE-ALLOC-001 (Quantity Preservation):** `ordered_quantity` is immutable once an order is submitted. Cancelled, damaged, or returned quantities must be tracked in distinct quantity attributes (`cancelled_quantity`, `reserved_quantity`, `delivered_quantity`, `returned_quantity`).
- **RULE-ALLOC-002 (Quantity Conservation Constraint):**
  For every order item line, the following mathematical constraint must be strictly enforced at database and application levels:
  $$\text{cancelled\_quantity} + \text{fulfillable\_quantity} = \text{ordered\_quantity}$$
  $$\text{delivered\_quantity} \le \text{fulfillable\_quantity}$$
  $$\text{returned\_quantity} \le \text{delivered\_quantity}$$
- **RULE-ALLOC-003 (Sequential Adjustments):** An order item may have multiple sequential adjustments over its lifecycle, provided that total cumulative adjustments do not exceed the original ordered quantity.

---

## 5. Inventory Rules (`RULE-INV`)

- **RULE-INV-001 (Atomic Stock Reservation):** Stock reservation must lock the inventory row using PostgreSQL pessimistic locking (`SELECT ... FOR UPDATE`).
- **RULE-INV-002 (Non-Negative Available Inventory):** Available stock is defined as:
  $$\text{available} = \text{on\_hand} - \text{reserved}$$
  Under no circumstances may normal order reservation cause $\text{available} < 0$. If requested stock exceeds available stock, the reservation must fail with a descriptive domain exception.
- **RULE-INV-003 (Inventory Movement Ledger):** Every stock balance mutation must be accompanied by an immutable record in `inventory_movements`, recording `product_id`, `quantity`, `from_state`, `to_state`, `reference_type`, `reference_id`, `actor_id`, and timestamp.
- **RULE-INV-004 (Damaged Goods Isolation):** Damaged goods reported by warehouse personnel or delivery returns must move from `RESERVED` / `ON_HAND` to `DAMAGED`. Damaged stock must never be returned to available sellable inventory.
- **RULE-INV-005 (No Frontend Stock Editing):** Users cannot manually override available stock quantities directly from UI tables. Inventory changes must occur via approved orders, warehouse stock exception reports, or authorized manual adjustments with documented audit reasons.

---

## 6. Pricing Rules (`RULE-PRI`)

- **RULE-PRI-001 (Historical Price Snapshot):** When an order item is created, the unit price agreed upon at that moment is permanently snapshotted in `order_items.unit_price`. Subsequent modifications to product master prices must never alter this snapshot.
- **RULE-PRI-002 (Price Boundary Constraint):** For every order line, the selling price must satisfy:
  $$\text{minimum\_allowed\_price} \le \text{actual\_order\_price} \le \text{mrp\_list\_price}$$
- **RULE-PRI-003 (Authorized Price Override):** An actual order price below `minimum_allowed_price` or above `mrp_list_price` requires the `pricing.override` permission. Any override must create an audit record capturing the user, the permitted range, the agreed price, and a mandatory business justification.

---

## 7. Tax Rules (`RULE-TAX`)

- **RULE-TAX-001 (Product-Specific Line Tax):** Tax is configured per product via `product_tax_profiles` and must be calculated individually on each order line. Orders with mixed tax rates (e.g., taxable goods, zero-rated food products, exempt items) must be fully supported.
- **RULE-TAX-002 (Historical Tax Snapshot):** The line item must permanently record `tax_profile_id`, `tax_rate`, `taxable_amount`, and `tax_amount` at transaction time. Changes to product tax configurations do not affect existing orders or invoices.
- **RULE-TAX-003 (Tax Recalculation on Adjustment):** When an order line is adjusted or partially cancelled, the corresponding tax liability for the cancelled portion must be recalculated and credited proportionally using the line's historical tax rate.
- **RULE-TAX-004 (Tax Policy TBDs):** Jurisdictional tax nexus rules and exact rounding algorithms are marked **TBD-001 / TBD-002**. Standard banker's rounding (`ROUND_HALF_UP` to 2 decimal places per line item) is the baseline until client policy confirms otherwise.

---

## 8. Payment Rules (`RULE-PAY`)

- **RULE-PAY-001 (V1 Payment Methods):** The system strictly supports three payment methods in V1: `CASH`, `CHEQUE`, and `MONEY_ORDER`.
- **RULE-PAY-002 (Mandatory JPEG Payment Evidence):**
  - Cheque and Money Order payments **REQUIRE** a JPEG photo/scan upload before the payment record can be submitted.
  - Cash payments do not require file attachments.
- **RULE-PAY-003 (Payment as Independent Entity):** A payment is an independent transaction record linked to a customer and optionally to an order. An order may have zero, one, or multiple partial payments across different methods.
- **RULE-PAY-004 (Payment Lifecycle & Verification):**
  - Payments follow the lifecycle: `RECORDED` → `PENDING_VERIFICATION` → `VERIFIED`.
  - Only authorized roles (`payment.verify` permission) can verify payments.
  - Bounced cheques transition to `BOUNCED` / `RETURNED` via controlled workflow, restoring customer outstanding liabilities.
- **RULE-PAY-005 (Payment Reversal):** Verified payments cannot be deleted. A reversal requires `payment.reverse` permission, an explicit reason, and generates an offsetting negative customer transaction and accounting journal.

---

## 9. Invoice & Document Rules (`RULE-DOC`)

- **RULE-DOC-001 (Hard Invoice Image Exclusion Rule):**
  > **PRODUCT IMAGES MUST NEVER APPEAR ON INVOICES.**
  Invoices are formal legal/financial instruments. Product images, catalogue thumbnails, and payment evidence photos must never be embedded in invoice HTML or PDF outputs.
- **RULE-DOC-002 (Distinct Invoice Numbering):** Invoice numbers (`INV-XXXXXX`) must be separate sequential identifiers distinct from order numbers (`ORD-XXXXXX`).
- **RULE-DOC-003 (Immutable Historical Invoices):** An issued invoice is an immutable snapshot. Subsequent cancellations, returns, or adjustments must be reflected through Credit Notes (`CR-XXXXXX`) or revised adjustment statements, never by silently mutating the original invoice.
- **RULE-DOC-004 (Invoice Access Scoping):** Invoices may only be viewed or downloaded by authorized users with access to the underlying customer/order.

---

## 10. Delivery Rules (`RULE-DLV`)

- **RULE-DLV-001 (Current Deliverable Quantity Only):** The delivery manifest and mobile delivery workspace must display current fulfillable quantities, never cancelled or adjusted quantities.
- **RULE-DLV-002 (Delivery Driver Resource Scope):** Delivery partners can only access orders explicitly assigned to them in `delivery_assignments`.
- **RULE-DLV-003 (Delivery Lifecycle):** Delivery transitions strictly follow:
  `PENDING_ASSIGNMENT` → `ASSIGNED` → `ACCEPTED` → `PICKED_UP` → `OUT_FOR_DELIVERY` → `DELIVERED`
  Exception path: `OUT_FOR_DELIVERY` → `FAILED` (with mandatory structured reason code: customer unavailable, address wrong, customer refused, etc.) → `RESCHEDULED` or `RETURN_TO_WAREHOUSE`.
- **RULE-DLV-004 (No Commercial Mutation by Delivery):** Delivery partners have zero authorization to alter prices, change taxes, adjust ordered quantities, or approve refunds.

---

## 11. Returns Rules (`RULE-RET`)

- **RULE-RET-001 (Cancellation vs Return Separation):**
  - `Cancellation` cancels unfulfilled goods before delivery.
  - `Return` brings previously delivered goods back from the customer.
  They must never be combined into a generic "cancel" action.
- **RULE-RET-002 (Return Quantity Validation):** Returned quantity cannot exceed delivered quantity minus previously returned quantity.
- **RULE-RET-003 (Two-Stage Return Inspection):** Return requests must undergo warehouse inspection upon receipt. Units are classified as `ACCEPTED_GOOD` (returned to available stock) or `ACCEPTED_DAMAGED` (transferred to damaged stock). Financial credits apply only to accepted units.

---

## 12. Credit & Refund Rules (`RULE-CR`)

- **RULE-CR-001 (Net Customer Liability Authority):**
  $$\text{Net Liability} = \text{Order Value} + \text{Charges} - \text{Discounts} - \text{Approved Cancellations} - \text{Approved Returns}$$
  $$\text{Outstanding} = \max(\text{Net Liability} - \text{Confirmed Payments}, 0)$$
  $$\text{Credit/Refund Due} = \max(\text{Confirmed Payments} - \text{Net Liability}, 0)$$
- **RULE-CR-002 (Credit Note Requirement):** Downward financial adjustments on invoiced orders mandate the issuance of a sequential Credit Note.
- **RULE-CR-003 (Segregation of Duties for Refunds):** Refund requests must be approved by an authorized user (`refund.approve`) separate from the requester whenever feasible.

---

## 13. Accounting Rules (`RULE-ACC`)

- **RULE-ACC-001 (Double-Entry General Ledger Immutability):** Every posted transaction must balance ($\sum \text{Debits} = \sum \text{Credits}$). Posted journal lines cannot be updated or deleted.
- **RULE-ACC-002 (Controlled Reversals):** Financial corrections require a formal reversing entry linked to the original journal entry ID, followed by an optional correcting entry.
- **RULE-ACC-003 (Event-to-Journal Mapping):** Authoritative operational events (invoice issuance, payment confirmation, return acceptance, credit note issuance) automatically post corresponding journals to the Chart of Accounts according to approved accounting mappings.

---

## 14. Authorization & Security Rules (`RULE-SEC`)

- **RULE-SEC-001 (Server Authority):** The client browser is completely untrusted. All authorizations, validations, business state checks, and calculations must execute server-side.
- **RULE-SEC-002 (Default Deny):** Access to all routes, actions, and entities is denied by default unless an explicit permission grants access.
- **RULE-SEC-003 (Resource Scoping):** Permission checks must always include resource ownership validation (e.g., Salesman ID = Customer Assigned Salesman ID).
- **RULE-SEC-004 (Privileged MFA):** Super Admin, Admin, and Accountant roles must support Multi-Factor Authentication (MFA) for production deployment.
- **RULE-SEC-005 (Privilege Revocation Timing):** Suspended, inactive, or modified user accounts must immediately have active sessions invalidated or re-evaluated.

---

## 15. Audit Rules (`RULE-AUD`)

- **RULE-AUD-001 (Audit Trail Completeness):** All critical state transitions (order approval, adjustment application, price override, payment verification, inventory movement, refund issuance, permission change) must write an immutable record to `audit_logs`.
- **RULE-AUD-002 (Audit Payload Standards):** Audit records must capture: `actor_id`, `role`, `timestamp`, `action`, `entity_type`, `entity_id`, `old_values` (JSON), `new_values` (JSON), `reason`, and client `ip_address`.
- **RULE-AUD-003 (Audit Immutability):** Audit log tables must reject `UPDATE` and `DELETE` queries at database permission and application layer levels.

---

## 16. File & Storage Rules (`RULE-FILE`)

- **RULE-FILE-001 (MIME Validation via Magic Bytes):** Payment evidence uploads must be verified by server-side MIME sniffing (inspecting binary magic bytes `\xFF\xD8\xFF` for JPEG), not solely relying on client-supplied file extensions.
- **RULE-FILE-002 (Storage Organization):** Files in S3 must use cryptographically secure random names and private paths:
  `payments/{year}/{month}/{uuid}.jpg`
- **RULE-FILE-003 (Presigned Access URLs):** Viewing evidence or downloading invoices must generate temporary presigned S3 URLs with an expiration time $\le 15\text{ minutes}$.

---

## 17. Frontend & UI Rules (`RULE-UI`)

- **RULE-UI-001 (Locked Design Direction):** Adhere strictly to "Premium B2B Commerce × Modern SaaS ERP". Use Tailwind CSS 4, shadcn/ui components, subtle micro-interactions, and neutral dark/light surfaces. Avoid generic admin templates.
- **RULE-UI-002 (Responsive Workspaces):**
  - Admin: Desktop-first control center with dense, clear data tables and side drawers.
  - Salesman: Mobile-first ordering workflow with bottom navigation, rapid search, and bottom sheets.
  - Delivery Partner: Mobile-first task list with high-contrast buttons and touch-friendly target sizes ($\ge 44\text{px}$).
- **RULE-UI-003 (Comprehensive State Handling):** Every page and component must explicitly handle:
  - Default / Active
  - Loading (skeleton screens, not full-page blank spinners)
  - Empty (action-oriented with helpful iconography and CTA)
  - Error (actionable message with retry option)
  - Unauthorized (graceful permission feedback)
- **RULE-UI-004 (Accessibility Baseline):** Comply with WCAG 2.1 AA. Never indicate status or severity using color alone; pair color with standard text badges or iconography.

---

## 18. Testing & QA Rules (`RULE-QA`)

- **RULE-QA-001 (Mandatory Test Coverage for PRs):** No ticket may be marked Done without accompanying automated tests covering happy path, negative validation, authorization rejection, and edge cases.
- **RULE-QA-002 (Zero Regression Tolerance):** All existing automated tests must pass before any new commit is finalized.
- **RULE-QA-003 (Concurrency Test Requirement):** Inventory reservation, adjustment application, and payment verification must have explicit concurrent/race-condition automated tests.

---

## 19. Code Quality & Maintenance Rules (`RULE-CODE`)

- **RULE-CODE-001 (Strict Typing):** PHP files must declare `declare(strict_types=1);`. TypeScript files must avoid `any` types; all API/Inertia props must be strictly typed.
- **RULE-CODE-002 (Search Before Create):** Before adding any new helper, component, or service, search the repository for existing equivalents. Reuse or extend whenever possible.
- **RULE-CODE-003 (Pre-Commit Cleanup):** Remove all unused imports, debug statements (`console.log`, `dd()`, `dump()`), dead functions, and temporary comments before committing.

---

## 20. Git & Branching Rules (`RULE-GIT`)

- **RULE-GIT-001 (Branch Naming):** Follow standard format: `feature/[TICKET-ID]-[name]`, `fix/[TICKET-ID]-[name]`, `chore/[TICKET-ID]-[name]`.
- **RULE-GIT-002 (Atomic Commits):** Commits must map to specific tickets using standard prefixes: `feat(scope): message [TICKET-ID]`. Giant multi-domain commits are prohibited.
- **RULE-GIT-003 (No Force Pushing):** Force-pushing to `main` or `develop` branches is strictly forbidden.

---

## 21. Change Management Rules (`RULE-CHG`)

- **RULE-CHG-001 (Change Impact Assessment):** No client-requested business rule modification may be coded directly into the repository without an explicit Change Record (`CHANGE-XXX`) in [docs/CHANGE_LOG.md](file:///f:/Wholesale%20Distribution%20Management%20System/docs/CHANGE_LOG.md) evaluating PRD, architectural, security, inventory, tax, and accounting impacts.
- **RULE-CHG-002 (Traceability):** Any change impacting domain behavior must update the relevant sections of Documents 01–05.

---

## 22. AI Agent Behavior Rules (`RULE-AI`)

- **RULE-AI-001 (No Unprompted Feature Creep):** Implement only what is explicitly specified in the active ticket. Do not introduce unrequested speculative features or third-party libraries.
- **RULE-AI-002 (Mandatory Stop Conditions):** An AI agent must immediately halt execution and request user guidance if an unresolvable specification conflict or an unconfirmed TBD business policy is encountered.
- **RULE-AI-003 (Truthful Reporting):** Never report an unverified test as passed, never report an unbuilt file as completed, and never fabricate commit hashes or deployment statuses.
