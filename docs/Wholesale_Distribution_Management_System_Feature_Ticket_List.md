# Document 05 — Feature & Delivery Ticket Specification

## Wholesale Distribution Management System

**Document Type:** Feature Ticket List / Execution Specification  
**Document Number:** 05  
**Document Version:** 1.0  
**Status:** Development Execution Baseline  
**Execution Model:** Solo Developer + Antigravity / AI Coding Agent  
**Primary Market:** United States  
**Currency:** USD  
**Depends On:** PRD v1.0, Technical Architecture v1.0, Security & Access v1.0, Frontend Specification v1.0  

> **Execution philosophy:** Build in small, vertically complete, testable slices. Every ticket must be understandable and executable by one developer or an AI coding agent without requiring hidden business assumptions.

---

# 0. Document Control & Authority

## 0.1 Purpose

This document converts the approved product, architecture, security, and frontend specifications into an implementation backlog and execution contract.

Document 05 answers:

- what to build;
- in what order;
- what each implementation unit must accomplish;
- what it depends on;
- what must not be changed;
- how success is tested;
- what security controls apply;
- what frontend states are required;
- what constitutes Done.

It is optimized for a **solo developer using Antigravity or another AI coding agent**, while retaining professional backlog structure suitable for future migration to Jira, Linear, GitHub Issues, or another work-management system.

## 0.2 Source-of-truth hierarchy

When a ticket conflicts with another specification, use:

1. Latest explicitly approved client decision.
2. Latest approved PRD.
3. Approved Technical Architecture.
4. Approved Security & Access.
5. Approved Frontend Specification.
6. This Feature Ticket List.
7. Older drafts / chat decisions.

A ticket must never silently create a new business policy.

## 0.3 Ticket implementation rule

An AI agent must not implement a ticket by guessing missing business rules. It must either:

- use an already-approved rule;
- use a safe configurable placeholder where explicitly permitted;
- or stop and identify the unresolved decision.

## 0.4 Current baseline

The project uses a shared transaction core across Salesman, Admin, Warehouse, and Delivery workflows. The frontend is a usability layer; authorization, resource scope, state transitions, inventory integrity, payment integrity, financial calculations, pricing, tax, and audit authority remain server-side.

---

# 1. Product Execution Model

## 1.1 Portals / workspaces

### Admin — Control Center

Primary concerns:

- orders;
- customers;
- products;
- pricing;
- inventory;
- delivery;
- payments;
- accounting;
- reports;
- users/access;
- exceptions.

### Salesman — Sales Workspace

Primary concerns:

- assigned customers;
- customer outstanding;
- product catalogue;
- permitted pricing;
- order creation;
- order history;
- cancellation / return requests.

### Delivery Partner — Delivery Workspace

Primary concerns:

- assigned deliveries;
- delivery details;
- pickup / out-for-delivery / delivered / failed states;
- return pickup tasks;
- limited customer information required for execution.

### Warehouse Manager — Operational Scope

Warehouse functionality is represented as operational capabilities within the shared system even where a dedicated visual portal is not required in V1.

---

# 2. Ticket Taxonomy

| Prefix | Type | Purpose |
|---|---|---|
| `FEAT` | Business feature | User-visible business capability |
| `TECH` | Technical | Infrastructure, codebase, data, build, deployment |
| `SEC` | Security | Security-specific implementation/hardening |
| `UI` | UX/UI | Cross-feature visual/design-system implementation |
| `QA` | Quality | Automated/manual/E2E testing and hardening |
| `BUG` | Defect | Regression or implementation defect |

## 2.1 Domain codes

```text
AUTH   Authentication
RBAC   Roles / Permissions
SYS    System / Company Settings
CUS    Customers
SLM    Salesmen
PROD   Products
CAT    Categories
PRICE  Pricing
TAX    Tax
ORD    Orders
ADJ    Adjustments
ALLOC  Quantity Allocation
INV    Inventory
PAY    Payments
DOC    Documents / Invoices
DEL    Delivery
RET    Returns
CR     Credit / Refund
AR     Receivables
AP     Payables
ACC    Accounting
REP    Reporting
NOTIF  Notifications
AUD    Audit
UI     User Interface
QA     Quality Assurance
TECH   Technical foundation
DEPLOY Deployment
```

---

# 3. Priority, Risk & Size

## 3.1 Priority

| Priority | Meaning |
|---|---|
| P0 | System blocker / prerequisite |
| P1 | Critical V1 business function |
| P2 | Important V1 supporting function |
| P3 | Polish / optimization / enhancement |
| P4 | Deferred / future |

## 3.2 Risk

| Risk | Meaning |
|---|---|
| R0 | Security, financial, inventory or irreversible-history risk |
| R1 | Core transaction/workflow risk |
| R2 | Important operational risk |
| R3 | UX / reporting risk |
| R4 | Cosmetic / low-risk |

## 3.3 Size

`XS`, `S`, `M`, `L`, `XL`

Size measures implementation complexity, not business importance.

---

# 4. Standard Ticket Contract

Every implementation ticket must contain, at minimum:

```text
Ticket ID
Title
Type
Epic
Priority
Risk
Size
Primary Role
Secondary Roles
Objective
User Story
Preconditions
Main Flow
Alternative / Error Flows
Business Rules
Functional Requirements
UI/UX Requirements
Responsive Requirements
Security Requirements
Data / Domain Impact
Dependencies
Blocks
Impacted Modules
Audit Requirements
Acceptance Criteria
QA Scenarios
Definition of Done
Do Not Change
Future Extension Notes
Traceability
```

High-risk tickets must additionally include an explicit transaction-safety checklist.

---

# 5. Definition of Ready

A ticket may enter implementation only when:

```text
[ ] Scope is clear
[ ] Business objective is known
[ ] Source requirements are linked
[ ] Dependencies are identified
[ ] Acceptance criteria are testable
[ ] Security impact is known
[ ] UI/UX requirements are known for user-facing work
[ ] Responsive behavior is known
[ ] Required states are known
[ ] Open TBDs are explicitly listed
[ ] No unresolved requirement conflict exists
```

A ticket that fails the Definition of Ready should not be “solved” by guessing.

---

# 6. Definition of Done

A ticket is Done only when applicable criteria below are satisfied:

```text
[ ] Approved business behavior implemented
[ ] Server-side validation implemented
[ ] Authorization/resource scope implemented or verified
[ ] Business-state transition validated
[ ] Domain calculations are authoritative server-side
[ ] Database transaction/concurrency behavior considered
[ ] Audit behavior implemented where required
[ ] Idempotency implemented for critical commands where required
[ ] UI implemented where applicable
[ ] Desktop state verified
[ ] Tablet state verified
[ ] Mobile state verified
[ ] Loading state handled
[ ] Empty state handled
[ ] Error state handled
[ ] Unauthorized / unavailable state handled where applicable
[ ] Accessibility basics checked
[ ] Relevant automated tests added/updated
[ ] Relevant integration/E2E tests added/updated
[ ] Acceptance criteria verified
[ ] No critical regression introduced
[ ] Traceability references updated
[ ] Documentation/configuration updated where necessary
```

A clear Definition of Done reduces ambiguity and rework; it is distinct from ticket-specific acceptance criteria. citeturn145246search0turn145246search4

---

# 7. Global AI-Agent Execution Protocol

Before modifying code for any ticket, Antigravity must:

1. Read the ticket completely.
2. Read all linked PRD requirements.
3. Read linked architecture requirements.
4. Read linked security requirements.
5. Read linked frontend requirements.
6. Inspect the current repository state.
7. Inspect the existing implementation of affected modules.
8. Identify dependencies and regressions.
9. Produce a concise implementation plan.
10. Implement only the ticket's approved scope.
11. Run relevant tests.
12. Run appropriate regression tests.
13. Check acceptance criteria one by one.
14. Report changed files/modules.
15. Report any unresolved issue or assumption.

## 7.1 AI-agent prohibitions

The agent must not:

- invent missing business rules;
- bypass backend authorization because the UI hides a button;
- directly set protected business statuses;
- trust browser-supplied prices, tax totals, inventory values, payment verification state, approval state, or role;
- rewrite historical orders;
- mutate posted accounting history instead of creating controlled reversals;
- silently change unrelated modules for cleanup purposes;
- introduce unnecessary dependencies without documenting them;
- mark a ticket Done when acceptance criteria or required tests fail.

---

# 8. Execution Strategy

## 8.1 Vertical-slice principle

Build the application as complete slices rather than isolated technical layers.

```text
Foundation
  ↓
Authentication / Access
  ↓
Master Data
  ↓
Commerce
  ↓
Operations
  ↓
Finance
  ↓
Logistics
  ↓
Reporting
  ↓
Hardening / Production
```

## 8.2 Critical path

```text
TECH-FOUND
 → AUTH
 → RBAC
 → CUSTOMER / SALESMAN / PRODUCT
 → PRICING / TAX
 → ORDER CREATION
 → ADMIN PROCESSING
 → ALLOCATION / ADJUSTMENT
 → INVENTORY
 → PAYMENT
 → INVOICE
 → DELIVERY
 → RETURNS / CREDITS
 → RECEIVABLES
 → ACCOUNTING
 → REPORTS
 → HARDENING / DEPLOYMENT
```

## 8.3 Do not front-load decorative work

The visual system is built early, but full dashboard polish should not block the transaction spine. Build representative screens against real domain behavior, then refine visual polish after core workflow correctness exists.

---

# 9. EPIC 00 — Project Foundation

## TECH-FOUND-001 — Repository & Application Bootstrap

**Priority:** P0  
**Risk:** R1  
**Size:** M

### Objective
Create the baseline Laravel + React/Inertia application structure defined by the architecture document.

### Acceptance Criteria
- Application starts successfully in local development.
- Backend, frontend, TypeScript, Tailwind, shadcn/ui and Vite foundation are operational.
- Environment-specific configuration has safe placeholders.
- Repository has clear development scripts.
- No secrets are committed.

### Do Not Change
Do not introduce a second frontend framework or unrelated application architecture.

### Traceability
Architecture §1.1; Security secret-management requirements.

---

## TECH-FOUND-002 — Database & Migration Foundation

**Priority:** P0  
**Risk:** R0  
**Size:** M

### Objective
Establish PostgreSQL connectivity, migration conventions, transaction patterns, factories/seeders and test database support.

### Acceptance Criteria
- Application connects to PostgreSQL locally.
- Migrations can build a clean database from zero.
- Tests can run against an isolated database.
- Foreign keys and transactional conventions are established.
- No production data is required for local boot.

---

## TECH-FOUND-003 — Global Error / Logging Foundation

**Priority:** P0  
**Risk:** R1  
**Size:** M

### Acceptance Criteria
- User-facing errors are safe and useful.
- Sensitive internals are not exposed.
- Application errors are observable through the configured monitoring path.
- Security-sensitive requests can be correlated.

---

## TECH-FOUND-004 — Queue / Cache Foundation

**Priority:** P1  
**Risk:** R2  
**Size:** S

Establish Redis-backed queue and cache conventions without making Redis the authoritative source of inventory or financial truth.

---

# 10. EPIC 01 — Authentication & Identity

## FEAT-AUTH-001 — Centralized Login

**Priority:** P0  
**Risk:** R0  
**Size:** M  
**Roles:** All portals

### Acceptance Criteria
- Valid users can authenticate.
- Invalid credentials produce a non-enumerating response.
- Login is rate-limited.
- Session security follows the security specification.
- Suspended/disabled users cannot operate.
- Successful authentication establishes correct identity and role context.

---

## FEAT-AUTH-002 — Logout & Session Revocation

**Priority:** P0  
**Risk:** R0  
**Size:** S

### Acceptance Criteria
- Logout invalidates the active session.
- Password/security changes can invalidate sessions as specified.
- Role/status changes trigger appropriate re-evaluation.

---

## FEAT-AUTH-003 — Password Reset

**Priority:** P1  
**Risk:** R0  
**Size:** M

### Acceptance Criteria
- Reset tokens are high-entropy and one-time.
- Expired/reused tokens fail safely.
- Password is never logged.
- Existing sessions are re-evaluated after reset as required.

---

## FEAT-AUTH-004 — Privileged MFA

**Priority:** P1  
**Risk:** R0  
**Size:** L

### Scope
Super Admin required; Admin and Accountant required in production; other roles configurable per approved policy.

### Acceptance Criteria
- Privileged users can complete configured MFA.
- Recovery path is separately secured and audited.
- MFA bypass cannot become a weaker authentication path.

---

# 11. EPIC 02 — Roles, Permissions & Access

## FEAT-RBAC-001 — Role Model

**Priority:** P0  
**Risk:** R0  
**Size:** M

Implement the baseline role model:

```text
SUPER_ADMIN
ADMIN
ACCOUNTANT
SALESMAN
WAREHOUSE_MANAGER
DELIVERY_PARTNER
```

### Acceptance Criteria
- Role is stored independently from account status.
- Role changes are authorized and audited.
- Role changes trigger session re-evaluation where required.

---

## FEAT-RBAC-002 — Permission Registry

**Priority:** P0  
**Risk:** R0  
**Size:** L

Implement permission naming using `module.action`.

Examples include:

```text
customer.view
customer.create
product.price.update
order.create
order.approve
order.adjust.request
order.adjust.approve
order.adjust.apply
payment.verify
payment.reverse
inventory.adjust
delivery.assign
return.approve
refund.approve
accounting.post
accounting.reverse
```

### Acceptance Criteria
- Default deny is enforced.
- Permissions are server-side.
- Sensitive actions have distinct permissions.
- Permission changes are audited.

---

## FEAT-RBAC-003 — Resource Scope Enforcement

**Priority:** P0  
**Risk:** R0  
**Size:** L

### Acceptance Criteria
- Salesman access is limited to allowed customer/order scope.
- Delivery Partner access is limited to assigned deliveries.
- Warehouse access is limited to permitted operational scope.
- Changing IDs cannot bypass resource authorization.

---

# 12. EPIC 03 — System / Company Configuration

## FEAT-SYS-001 — Configurable Application Identity

**Priority:** P2  
**Risk:** R3  
**Size:** S

Company/application display name must be configurable rather than hard-coded.

## FEAT-SYS-002 — Company Settings

**Priority:** P1  
**Risk:** R2  
**Size:** M

Support approved company-level configuration needed for branding, invoice identity, currency, address and operational metadata.

Open business policy fields remain configurable/TBD rather than invented.

---

# 13. EPIC 04 — Customer Management

## FEAT-CUS-001 — Customer CRUD

**Priority:** P1  
**Risk:** R1  
**Size:** M

### Acceptance Criteria
- Admin can create/update/view permitted customers.
- Active/inactive lifecycle is supported.
- Validation prevents malformed required data.
- Audit records capture important changes.

## FEAT-CUS-002 — Customer Assignment to Salesman

**Priority:** P1  
**Risk:** R0  
**Size:** M

### Acceptance Criteria
- Admin can assign/reassign customers to authorized salesmen.
- Resource scope changes take effect correctly.
- Existing historical orders remain attributed to the original actors/context.

## FEAT-CUS-003 — Customer Profile & Outstanding

**Priority:** P1  
**Risk:** R1  
**Size:** L

Display:

- profile;
- assigned salesman;
- purchase history;
- outstanding balance;
- payment history;
- aging/statement information when available.

## FEAT-CUS-004 — Customer Lifecycle Controls

**Priority:** P1  
**Risk:** R1  
**Size:** S

Support Active / Inactive behavior and prevent prohibited new transactions for inactive customers according to business policy.

---

# 14. EPIC 05 — Salesman Management

## FEAT-SLM-001 — Salesman Account Management

**Priority:** P1  
**Risk:** R0  
**Size:** M

Admin can create/manage salesman accounts and states.

States include:

```text
INVITED
ACTIVE
SUSPENDED
DISABLED / INACTIVE
```

Exact naming in UI must remain aligned with approved security/account-state terminology.

## FEAT-SLM-002 — Salesman Customer Scope

**Priority:** P1  
**Risk:** R0  
**Size:** M

Ensure salesman can only operate on assigned/permitted customer records.

---

# 15. EPIC 06 — Product Management

## FEAT-PROD-001 — Product CRUD

**Priority:** P1  
**Risk:** R1  
**Size:** M

Product master supports at minimum:

- name;
- SKU/reference;
- category;
- cost/reference valuation;
- MRP/list price;
- default selling price;
- minimum allowed price;
- product tax configuration;
- active/inactive state;
- product image where catalog UX requires it.

## FEAT-PROD-002 — Product Image Management

**Priority:** P2  
**Risk:** R2  
**Size:** M

### Acceptance Criteria
- Product images use private/object-storage security patterns.
- UI supports preview.
- Invoice rendering never uses product images.

## FEAT-PROD-003 — Product Lifecycle

**Priority:** P1  
**Risk:** R1  
**Size:** S

Inactive products cannot be newly ordered where prohibited while historical transactions remain intact.

## FEAT-CAT-001 — Category Management

**Priority:** P1  
**Risk:** R2  
**Size:** S

CRUD and product assignment with safe handling of categories containing products.

---

# 16. EPIC 07 — Pricing & Tax

## FEAT-PRICE-001 — Pricing Rule Foundation

**Priority:** P1  
**Risk:** R0  
**Size:** L

V1 rule:

```text
Minimum Allowed Price ≤ Actual Order Price ≤ MRP
```

unless an authorized override is recorded.

### Acceptance Criteria
- Product master exposes default selling price, minimum allowed price and MRP.
- Salesman receives only permitted selectable values.
- Server validates actual order price.
- Historical order price is preserved.

The approved requirements define cost price, MRP, default selling price, minimum allowed price and historical actual order price, with controlled override behavior. fileciteturn1file8L471-L483

## FEAT-PRICE-002 — Authorized Price Override

**Priority:** P1  
**Risk:** R0  
**Size:** M

### Acceptance Criteria
- Override requires explicit permission.
- Reason is captured.
- Original permitted range remains auditable.
- Resulting order price is historical and immutable except through approved correction workflow.

## FEAT-TAX-001 — Product-Level Tax Configuration

**Priority:** P1  
**Risk:** R0  
**Size:** L

### Acceptance Criteria
- Tax applicability/rate is associated with product/order-line context.
- Server calculates line tax.
- Tax snapshot is preserved on historical order line.
- Later product-tax changes do not rewrite old transactions.

---

# 17. EPIC 09 — Salesman Ordering

## FEAT-ORD-001 — Salesman Starts New Order

**Priority:** P1  
**Risk:** R1  
**Size:** L

### Main flow

```text
Select assigned customer
 → Browse/search catalogue
 → Add products
 → Set quantity
 → Select permitted price
 → Review
 → Payment input where applicable
 → Submit
```

### Acceptance Criteria
- Only eligible customers are selectable.
- Product search is responsive.
- Quantity cannot violate domain rules.
- Price is server validated.
- Tax is calculated using authoritative rules.
- Order total is server recalculated.
- Successful submit creates exactly one order despite duplicate submission attempts.

## FEAT-ORD-002 — Draft Order

**Priority:** P1  
**Risk:** R2  
**Size:** M

Allow safe editing of an unsubmitted draft.

## FEAT-ORD-003 — Order Line Quantity Controls

**Priority:** P1  
**Risk:** R1  
**Size:** M

### UX
- quantity input;
- +/- controls;
- validation feedback;
- stock visibility where allowed;
- no direct browser mutation of authoritative availability.

## FEAT-ORD-004 — Order Review

**Priority:** P1  
**Risk:** R1  
**Size:** M

Review must clearly show:

- customer;
- product lines;
- ordered quantity;
- unit price;
- tax;
- subtotal;
- total;
- payment method/evidence state if applicable.

## FEAT-ORD-005 — Order Submission Idempotency

**Priority:** P0  
**Risk:** R0  
**Size:** M

Repeated requests must not create duplicate orders or duplicate downstream effects.

## FEAT-ORD-006 — Salesman Order History

**Priority:** P1  
**Risk:** R1  
**Size:** M

Salesman sees only authorized order history and current order state.

---

# 18. EPIC 10 — Admin Order Operations

## FEAT-ORD-010 — Admin Order Queue Framework

**Priority:** P1  
**Risk:** R1  
**Size:** L

Operational views should include, as applicable:

```text
New Orders
Needs Attention
Processing
Delivery
Completed
Cancelled
Returns
All / Search History
```

These are views over the shared order source of truth, not duplicate order stores.

## FEAT-ORD-011 — New Order Review

**Priority:** P1  
**Risk:** R1  
**Size:** M

Admin can review submitted orders and access permitted approval/processing actions.

## FEAT-ORD-012 — Order Approval / Rejection

**Priority:** P1  
**Risk:** R0  
**Size:** M

### Acceptance Criteria
- Only authorized users can approve/reject.
- Current order state is rechecked server-side.
- Rejection requires appropriate reason.
- State transition is audited.

## FEAT-ORD-013 — Order Detail Workspace

**Priority:** P1  
**Risk:** R1  
**Size:** L

Detail workspace must expose independently understandable sections:

- order status/timeline;
- customer;
- items;
- allocation/adjustment;
- payment;
- delivery;
- financial summary;
- returns;
- audit/activity.

---

# 19. EPIC 11 — Order Adjustment & Quantity Allocation

This is a **critical project domain**.

## FEAT-ALLOC-001 — Order Item Quantity Allocation Model

**Priority:** P1  
**Risk:** R0  
**Size:** XL

Represent original and operational quantities separately.

Example:

```text
Ordered     10
Allocated    8
Adjusted     2
```

### Non-negotiable rule
Never overwrite the original ordered quantity to make a partial fulfilment look like the original order was smaller.

## FEAT-ALLOC-002 — Allocation Validation

**Priority:** P1  
**Risk:** R0  
**Size:** L

Validate that allocation cannot exceed eligible quantities and remains consistent under concurrent operations.

## FEAT-ADJ-001 — Adjustment Request

**Priority:** P1  
**Risk:** R0  
**Size:** L

Request captures:

- order;
- item;
- requested quantity change;
- reason;
- notes;
- requester;
- current order state.

## FEAT-ADJ-002 — Adjustment Review

**Priority:** P1  
**Risk:** R0  
**Size:** L

Admin can inspect:

- original ordered quantity;
- current allocated quantity;
- requested adjustment;
- inventory context;
- tax effect;
- financial effect;
- requester;
- reason.

## FEAT-ADJ-003 — Adjustment Approval / Rejection

**Priority:** P1  
**Risk:** R0  
**Size:** L

### Acceptance Criteria
- Permission checked.
- Resource scope checked.
- Current order/adjustment state checked.
- Quantity eligibility checked.
- Approval/rejection is audited.
- Duplicate approval cannot create duplicate effects.

## FEAT-ADJ-004 — Atomic Adjustment Application

**Priority:** P0  
**Risk:** R0  
**Size:** XL

Apply approved adjustment atomically across relevant:

- order item quantities;
- allocation;
- inventory effects;
- tax effects;
- financial totals;
- outstanding balances;
- invoice/credit-note consequences where applicable;
- audit history.

### Security sequence

```text
Authentication
 → Permission
 → Resource Scope
 → Current State
 → Quantity Validation
 → Inventory Validation
 → Tax Calculation
 → Financial Calculation
 → Approval Policy
 → Atomic Application
 → Audit
```

This matches the approved security flow for adjustment operations. fileciteturn0file4L548-L572

## FEAT-ADJ-005 — Adjustment Reversal

**Priority:** P1  
**Risk:** R0  
**Size:** XL

Use controlled reversal rather than mutating historical applied adjustment records.

## FEAT-ADJ-006 — Adjustment / Exception Queue

**Priority:** P1  
**Risk:** R1  
**Size:** M

Provide Admin with a dedicated operational queue for pending/attention-required adjustment work.

---

# 20. EPIC 12 — Inventory & Warehouse

## FEAT-INV-001 — Inventory Item / Stock Foundation

**Priority:** P1  
**Risk:** R0  
**Size:** L

Support authoritative inventory records and movements.

## FEAT-INV-002 — On-Hand / Reserved / Available Views

**Priority:** P1  
**Risk:** R0  
**Size:** L

Conceptually separate:

```text
On Hand
Reserved
Available
Allocated / Fulfilled
Damaged
```

## FEAT-INV-003 — Reservation / Allocation Integrity

**Priority:** P0  
**Risk:** R0  
**Size:** XL

### Acceptance Criteria
- Concurrent operations cannot produce invalid negative availability.
- Authoritative database transaction protects inventory state.
- UI never directly edits available stock.
- Conflicts return recoverable errors.

## FEAT-INV-004 — Stock Movement History

**Priority:** P1  
**Risk:** R1  
**Size:** M

Every meaningful stock movement should be traceable to source event/context.

## FEAT-INV-005 — Stock Exception Reporting

**Priority:** P1  
**Risk:** R0  
**Size:** M

Warehouse can report physical stock exceptions without silently altering commercial order state.

Approved workflow:

```text
Warehouse detects issue
 → Stock Exception
 → Admin
 → Order Adjustment
```

## FEAT-INV-006 — Inventory Adjustment

**Priority:** P1  
**Risk:** R0  
**Size:** L

Authorized inventory adjustment with reason, actor, before/after quantity and audit.

---

# 21. EPIC 13 — Payments

## FEAT-PAY-001 — Payment Method Model

**Priority:** P1  
**Risk:** R0  
**Size:** M

V1 payment methods:

```text
Cash
Cheque
Money Order
```

## FEAT-PAY-002 — Cash Payment Entry

**Priority:** P1  
**Risk:** R0  
**Size:** M

Capture amount and applicable transaction references; server validates resulting financial state.

## FEAT-PAY-003 — Cheque Payment Entry

**Priority:** P1  
**Risk:** R0  
**Size:** M

Capture required cheque details and evidence workflow.

## FEAT-PAY-004 — Money Order Payment Entry

**Priority:** P1  
**Risk:** R0  
**Size:** M

Capture required money-order details and evidence workflow.

## FEAT-PAY-005 — Payment Evidence Upload

**Priority:** P1  
**Risk:** R0  
**Size:** L

### Requirements
- JPEG evidence supported per approved requirement.
- File content/type is validated server-side.
- Size limits are configurable/TBD until policy is finalized.
- Files remain private.
- Preview uses controlled access.
- No predictable/public URL exposure.

## FEAT-PAY-006 — Payment Evidence Preview

**Priority:** P1  
**Risk:** R0  
**Size:** M

Preview UI must not bypass authorization/resource scope.

## FEAT-PAY-007 — Payment Verification

**Priority:** P1  
**Risk:** R0  
**Size:** L

Security flow:

```text
Authenticate
 → payment.verify
 → resource scope
 → current payment state
 → evidence availability
 → verification
 → financial update
 → audit
```

Approved security requirements prohibit accepting a client-supplied `status=CONFIRMED` as authority. fileciteturn0file6L838-L858

## FEAT-PAY-008 — Payment Rejection / Rework

**Priority:** P1  
**Risk:** R1  
**Size:** M

Allow controlled rejection and re-submission where business workflow permits.

## FEAT-PAY-009 — Payment Reversal

**Priority:** P1  
**Risk:** R0  
**Size:** XL

Requirements:

- dedicated permission;
- current state validation;
- reason;
- financial consistency;
- idempotency;
- audit;
- original payment history preserved.

---

# 22. EPIC 14 — Invoice & Documents

## FEAT-DOC-001 — Invoice Generation

**Priority:** P1  
**Risk:** R1  
**Size:** L

Invoice must reflect authoritative historical transaction values.

## FEAT-DOC-002 — Invoice Print

**Priority:** P1  
**Risk:** R1  
**Size:** M

### Acceptance Criteria
- Authorized roles can print.
- Layout is professional.
- Product images are never included.
- Customer/order/tax/payment/total fields reflect authoritative data.

## FEAT-DOC-003 — Invoice Download / Controlled Access

**Priority:** P1  
**Risk:** R0  
**Size:** M

Access uses authorization/resource-scope checks and controlled document delivery.

## FEAT-DOC-004 — Historical Document Integrity

**Priority:** P1  
**Risk:** R0  
**Size:** M

Later product price/tax changes must not rewrite historical invoices.

---

# 23. EPIC 15 — Delivery Operations

## FEAT-DEL-001 — Delivery Assignment

**Priority:** P1  
**Risk:** R1  
**Size:** L

Admin assigns eligible orders to delivery partners.

## FEAT-DEL-002 — Delivery Partner Assigned Queue

**Priority:** P1  
**Risk:** R1  
**Size:** M

Delivery Partner sees only assigned tasks.

## FEAT-DEL-003 — Pickup / Acceptance

**Priority:** P1  
**Risk:** R1  
**Size:** M

## FEAT-DEL-004 — Out-for-Delivery Transition

**Priority:** P1  
**Risk:** R1  
**Size:** M

## FEAT-DEL-005 — Delivered Transition

**Priority:** P1  
**Risk:** R0  
**Size:** L

### Acceptance Criteria
- Transition allowed only from valid current state.
- Actor is authorized for the assigned delivery.
- Completion event is audited.
- Financial/accounting downstream effects trigger only once.

## FEAT-DEL-006 — Failed Delivery

**Priority:** P1  
**Risk:** R1  
**Size:** M

Capture failure reason and next action where business workflow allows.

## FEAT-DEL-007 — Reschedule

**Priority:** P2  
**Risk:** R1  
**Size:** M

Only authorized workflows can reschedule deliveries.

## FEAT-DEL-008 — Delivery History

**Priority:** P2  
**Risk:** R2  
**Size:** M

---

# 24. EPIC 16 — Returns

## FEAT-RET-001 — Return Request

**Priority:** P1  
**Risk:** R1  
**Size:** L

Return request references the original order and eligible quantities.

## FEAT-RET-002 — Return Review / Inspection

**Priority:** P1  
**Risk:** R0  
**Size:** L

Capture condition, received quantity, disposition and authorization.

## FEAT-RET-003 — Return Approval

**Priority:** P1  
**Risk:** R0  
**Size:** L

Only authorized users can approve return workflow states.

## FEAT-RET-004 — Return Inventory Effect

**Priority:** P1  
**Risk:** R0  
**Size:** L

Inventory disposition must be explicit and auditable.

---

# 25. EPIC 17 — Credits & Refunds

## FEAT-CR-001 — Credit Eligibility Calculation

**Priority:** P1  
**Risk:** R0  
**Size:** L

Calculate eligible credit/refund from authoritative transaction and financial state.

## FEAT-CR-002 — Credit Note Generation

**Priority:** P1  
**Risk:** R0  
**Size:** L

Historical transaction remains preserved; credit note represents the correction/financial consequence.

## FEAT-CR-003 — Refund Request

**Priority:** P1  
**Risk:** R0  
**Size:** M

Separate request from approval.

## FEAT-CR-004 — Refund Approval

**Priority:** P1  
**Risk:** R0  
**Size:** L

Approval requires dedicated permission and appropriate financial-state validation.

## FEAT-CR-005 — Refund Processing / Reversal Safety

**Priority:** P1  
**Risk:** R0  
**Size:** XL

Prevent duplicate processing and preserve financial history.

The security baseline explicitly recommends separating refund request, approval and processing where the workflow requires it. fileciteturn0file6L880-L893

---

# 26. EPIC 18 — Receivables & Payables

## FEAT-AR-001 — Customer Receivable Ledger

**Priority:** P1  
**Risk:** R0  
**Size:** XL

Track:

- invoices/charges;
- payments;
- credits;
- refunds;
- outstanding balance;
- transaction history.

## FEAT-AR-002 — Customer Aging

**Priority:** P1  
**Risk:** R1  
**Size:** L

Provide aging categories according to approved accounting policy.

## FEAT-AR-003 — Customer Statement

**Priority:** P1  
**Risk:** R1  
**Size:** M

## FEAT-AP-001 — Supplier Payables Foundation

**Priority:** P1  
**Risk:** R1  
**Size:** L

Support supplier bills/payments/outstanding as required by accounting scope.

---

# 27. EPIC 19 — Accounting

## FEAT-ACC-001 — Chart of Accounts

**Priority:** P1  
**Risk:** R0  
**Size:** L

Hierarchical accounts covering assets, liabilities, equity, revenue and expenses.

## FEAT-ACC-002 — Journal Entry Foundation

**Priority:** P1  
**Risk:** R0  
**Size:** XL

Support system-generated journals and controlled manual journals for authorized accounting users.

## FEAT-ACC-003 — Event-to-Journal Mapping

**Priority:** P1  
**Risk:** R0  
**Size:** XL

Define how authoritative business events map to journal entries.

At minimum document mappings for:

```text
Order/invoice revenue
Tax liability
COGS / inventory movement where applicable
Payment collection
Receivable changes
Supplier payable changes
Credits
Refunds
Approved adjustments
```

## FEAT-ACC-004 — General Ledger

**Priority:** P1  
**Risk:** R0  
**Size:** L

Every posted entry must be traceable to its source transaction/event where applicable.

## FEAT-ACC-005 — Trial Balance

**Priority:** P1  
**Risk:** R0  
**Size:** M

## FEAT-ACC-006 — Profit & Loss

**Priority:** P1  
**Risk:** R0  
**Size:** L

## FEAT-ACC-007 — Balance Sheet

**Priority:** P1  
**Risk:** R0  
**Size:** L

## FEAT-ACC-008 — Accounting Reversal

**Priority:** P1  
**Risk:** R0  
**Size:** XL

Posted journal entries are not deleted. Use:

```text
Original Journal
   ↓
Controlled Reversal
   ↓
Correcting Entry
```

This follows the security requirement for immutable posted accounting history. fileciteturn0file4L635-L648

## FEAT-ACC-009 — Cash / Reconciliation

**Priority:** P1  
**Risk:** R0  
**Size:** L

Support collection and reconciliation controls appropriate to V1.

---

# 28. EPIC 20 — Reporting & Analytics

## FEAT-REP-001 — Sales Reports

**Priority:** P1  
**Risk:** R2  
**Size:** L

Daily / weekly / monthly / yearly, with useful drill-down dimensions.

## FEAT-REP-002 — Customer Reports

**Priority:** P1  
**Risk:** R2  
**Size:** M

Outstanding, aging, last order, purchase history.

## FEAT-REP-003 — Salesman Performance

**Priority:** P2  
**Risk:** R2  
**Size:** L

Orders, sales, collections, outstanding generated, pricing overrides where appropriate.

## FEAT-REP-004 — Inventory Reports

**Priority:** P1  
**Risk:** R1  
**Size:** L

On-hand, reserved, available, movements, returns, damaged.

## FEAT-REP-005 — Delivery Reports

**Priority:** P2  
**Risk:** R2  
**Size:** M

Assigned, delivered, failed, rescheduled, turnaround time.

## FEAT-REP-006 — Accounting Reports

**Priority:** P1  
**Risk:** R0  
**Size:** L

P&L, balance sheet, trial balance, ledger, receivables, payables.

The approved reporting requirements explicitly cover sales, salesman, customers, inventory, delivery and accounting report families. fileciteturn1file3L227-L245

---

# 29. EPIC 21 — Notifications

## FEAT-NOTIF-001 — In-App Operational Notifications

**Priority:** P2  
**Risk:** R2  
**Size:** M

Examples:

- new order;
- adjustment needed;
- payment verification needed;
- delivery assignment;
- failed delivery;
- return request.

## FEAT-NOTIF-002 — Notification Preference Foundation

**Priority:** P3  
**Risk:** R3  
**Size:** S

Architect for future email/SMS/WhatsApp expansion without hard-coding a single transport.

---

# 30. EPIC 22 — Audit & Activity

## FEAT-AUD-001 — Business Audit Events

**Priority:** P0  
**Risk:** R0  
**Size:** L

Capture important:

- actor;
- timestamp;
- action;
- entity;
- relevant before/after values;
- reason where applicable;
- correlation/request ID where appropriate.

## FEAT-AUD-002 — Security Event Logging

**Priority:** P0  
**Risk:** R0  
**Size:** L

Keep security events distinct from business audit events.

## FEAT-AUD-003 — Audit Immutability

**Priority:** P0  
**Risk:** R0  
**Size:** M

Normal users cannot edit/delete audit records. fileciteturn0file9L1517-L1555

## FEAT-AUD-004 — Activity Timeline UI

**Priority:** P2  
**Risk:** R2  
**Size:** M

Show business-relevant chronology without exposing secret/security-sensitive data unnecessarily.

---

# 31. EPIC 23 — Design System & Responsive UX

Document 04 is the source of truth for visual design. These tickets implement it systematically.

## UI-001 — Design Tokens

**Priority:** P1  
**Risk:** R3  
**Size:** M

Implement:

- typography;
- colors;
- spacing;
- radius;
- borders;
- shadows;
- motion;
- z-index;
- breakpoints.

Design direction:

> Premium B2B Commerce × Modern SaaS ERP

## UI-002 — Core Component Library

**Priority:** P1  
**Risk:** R3  
**Size:** XL

Core components include:

```text
Button
IconButton
Input
Select
Combobox
SearchInput
DatePicker
FilterBar
Tabs
Badge
StatusBadge
Card
DataTable
MobileList
Modal
Drawer
BottomSheet
Toast
Alert
Dropdown
Pagination
Stepper
Timeline
FileUploader
ImagePreview
PriceInput
QuantityInput
CurrencyInput
EmptyState
Skeleton
```

## UI-003 — Admin Application Shell

**Priority:** P1  
**Risk:** R3  
**Size:** L

Responsive desktop/tablet/mobile shell per Document 04.

## UI-004 — Salesman Application Shell

**Priority:** P1  
**Risk:** R3  
**Size:** L

Mobile-first workspace with bottom navigation and fast ordering entry.

## UI-005 — Delivery Application Shell

**Priority:** P1  
**Risk:** R3  
**Size:** M

Task-focused mobile workspace.

## UI-006 — Responsive Table System

**Priority:** P1  
**Risk:** R3  
**Size:** L

Desktop tables, tablet prioritized columns/horizontal overflow where justified, mobile cards/lists.

## UI-007 — Form State System

**Priority:** P1  
**Risk:** R3  
**Size:** M

Loading, validation, disabled, success, error and permission/unavailable states.

## UI-008 — Payment Evidence UI

**Priority:** P1  
**Risk:** R0  
**Size:** M

Upload / preview / replace / remove UX for cheque and money-order evidence without exposing storage URLs.

## UI-009 — Order Workflow UI

**Priority:** P1  
**Risk:** R1  
**Size:** XL

Order creation, detail, allocation and adjustment interfaces based on Document 04.

## UI-010 — Responsive QA Width Matrix

**Priority:** P1  
**Risk:** R3  
**Size:** M

Test at minimum:

```text
320
375
390
430
640
768
820
1024
1280
1440
1920
```

---

# 32. EPIC 24 — QA / Security / Performance

## QA-001 — Authentication Test Suite

Test valid login, invalid login, throttling, reset, logout, session revocation, suspended users and privilege changes.

## QA-002 — Authorization / IDOR Test Suite

Attempt unauthorized access across:

- customers;
- orders;
- payments;
- payment evidence;
- invoices;
- adjustments;
- inventory;
- returns;
- accounting.

## QA-003 — Order E2E Test Suite

Primary flow:

```text
Salesman login
 → select customer
 → create order
 → price validation
 → tax
 → submit
 → Admin review
 → processing
```

## QA-004 — Adjustment E2E Test Suite

Test partial cancellation/adjustment including:

- ordered quantity preserved;
- allocated quantity changed;
- inventory impact;
- tax impact;
- financial impact;
- audit;
- concurrency;
- duplicate command.

## QA-005 — Inventory Concurrency Test Suite

Test competing allocations and stock exceptions.

## QA-006 — Payment Evidence Security Tests

Verify:

- invalid file type rejection;
- oversized file behavior;
- access control;
- private object storage;
- signed/controlled retrieval;
- no public URL dependency.

## QA-007 — Payment / Refund Financial Integrity Tests

Test duplicate submission, verification, rejection, reversal, refund eligibility and repeated operations.

## QA-008 — Accounting Integrity Test Suite

Verify journal balancing, source traceability, reversal behavior and report consistency.

## QA-009 — Responsive Regression Suite

Run representative workflows on mobile/tablet/desktop breakpoints.

## QA-010 — Accessibility Baseline

Check:

- keyboard navigation;
- focus visibility;
- semantic controls;
- labels;
- contrast;
- status not communicated by color alone;
- screen-reader relationships;
- touch target usability.

## TECH-QA-001 — Performance Baseline

Implement:

- server-side pagination;
- debounced search;
- lazy loading;
- optimized images;
- query/index review;
- controlled payload sizes.

---

# 33. EPIC 25 — Deployment & Production Readiness

## DEPLOY-001 — Staging Environment

**Priority:** P0  
**Risk:** R0  
**Size:** L

Create staging that closely matches production security behavior.

## DEPLOY-002 — Production Infrastructure Baseline

**Priority:** P0  
**Risk:** R0  
**Size:** XL

Use the approved architecture baseline:

- AWS application environment;
- RDS PostgreSQL;
- ElastiCache Redis;
- S3 private storage;
- CloudWatch;
- Sentry;
- Route 53;
- CI/CD via GitHub Actions.

## DEPLOY-003 — CI/CD Pipeline

**Priority:** P0  
**Risk:** R1  
**Size:** L

Pipeline should run relevant checks before deployment.

## DEPLOY-004 — Backup / Restore Validation

**Priority:** P0  
**Risk:** R0  
**Size:** L

Verify backups exist and restore procedure is documented/tested.

## DEPLOY-005 — Production Security Checklist

**Priority:** P0  
**Risk:** R0  
**Size:** L

Verify:

- HTTPS;
- secret management;
- private database;
- private Redis;
- private S3;
- least privilege;
- secure cookies;
- session controls;
- monitoring;
- error disclosure controls.

---

# 34. Cross-Feature Edge-Case Register

These cases must have explicit tests before V1 is considered complete.

```text
EDGE-001 Duplicate order submission
EDGE-002 Duplicate payment submission
EDGE-003 Duplicate adjustment application
EDGE-004 Concurrent stock allocation
EDGE-005 Product becomes inactive during order workflow
EDGE-006 Customer becomes inactive during order workflow
EDGE-007 Price changes after order creation
EDGE-008 Tax changes after order creation
EDGE-009 Adjustment exceeds remaining eligible quantity
EDGE-010 Two admins attempt the same adjustment concurrently
EDGE-011 Order reaches disallowed state while adjustment is pending
EDGE-012 Payment verification attempted twice
EDGE-013 Payment evidence inaccessible or missing
EDGE-014 Invalid/fake JPEG upload
EDGE-015 Delivery completion attempted twice
EDGE-016 Failed delivery followed by invalid transition
EDGE-017 Refund exceeds eligible financial amount
EDGE-018 Accounting reversal attempted twice
EDGE-019 User role changed during active session
EDGE-020 Account suspended during active workflow
EDGE-021 Unauthorized ID substitution / IDOR attempt
EDGE-022 Historical invoice changes after product-master edit
EDGE-023 Historical tax changes after product-tax edit
EDGE-024 Inventory transaction failure midway through business operation
EDGE-025 Report total differs from authoritative transaction ledger
```

---

# 35. Master State Transition Ownership

## 35.1 Order

```text
DRAFT
  ↓
SUBMITTED
  ↓
PENDING_APPROVAL
  ↓
APPROVED
  ↓
PROCESSING
  ↓
ALLOCATED
  ↓
OUT_FOR_DELIVERY
  ↓
DELIVERED
  ↓
COMPLETED
```

Possible controlled branches:

```text
SUBMITTED → REJECTED
PROCESSING → NEEDS_ADJUSTMENT
PROCESSING → CANCELLED
ALLOCATED → NEEDS_ADJUSTMENT
DELIVERED → RETURN_REQUESTED
```

Exact transition list must remain aligned with the approved PRD state model.

## 35.2 Payment

```text
RECORDED
 → PENDING_VERIFICATION
 → VERIFIED
```

Controlled branches may include rejection and reversal according to approved policy.

## 35.3 Delivery

```text
ASSIGNED
 → ACCEPTED
 → OUT_FOR_DELIVERY
 → DELIVERED
```

Possible branch:

```text
OUT_FOR_DELIVERY → FAILED
```

## 35.4 Adjustment

```text
REQUESTED
 → UNDER_REVIEW
 → APPROVED
 → APPLIED
```

Possible branch:

```text
REQUESTED / UNDER_REVIEW → REJECTED
APPLIED → REVERSED
```

No user should arbitrarily set a status value. Status changes must use authorized domain operations. fileciteturn0file6L1029-L1064

---

# 36. Role × Capability Summary

| Capability | Super Admin | Admin | Accountant | Salesman | Warehouse | Delivery |
|---|---:|---:|---:|---:|---:|---:|
| View permitted orders | Full | Full | Controlled | Scoped | Controlled | Assigned |
| Create order | Yes | Yes | No | Yes | No | No |
| Approve order | Yes | Yes | No | No | No | No |
| Request adjustment | Yes | Yes | No | Yes | Report issue | No |
| Approve adjustment | Yes | Yes | Controlled | No | No | No |
| Create payment | Yes | Yes | Yes | Yes | No | No |
| Verify payment | Yes | Controlled | Yes | No | No | No |
| Reverse payment | Yes | Controlled | Controlled | No | No | No |
| Inventory adjustment | Yes | Controlled | No | No | Controlled | No |
| Assign delivery | Yes | Yes | No | No | No | No |
| Update delivery | Yes | Yes | No | No | No | Assigned |
| Print invoice | Yes | Yes | Controlled | Yes | No | No |
| Approve refund | Yes | Controlled | Controlled | No | No | No |
| Manage roles | Yes | Controlled | No | No | No | No |

“Controlled” must map to explicit permissions in implementation, not informal role checks. The security baseline requires a complete permission matrix and resource-level authorization. fileciteturn0file5L709-L771

---

# 37. Ticket Dependency Matrix — Critical Dependencies

| Ticket | Depends On |
|---|---|
| TECH-FOUND-002 | TECH-FOUND-001 |
| FEAT-AUTH-001 | TECH-FOUND-001 |
| FEAT-RBAC-001 | FEAT-AUTH-001 |
| FEAT-RBAC-002 | FEAT-RBAC-001 |
| FEAT-RBAC-003 | FEAT-RBAC-002 |
| FEAT-CUS-001 | FEAT-RBAC |
| FEAT-SLM-001 | FEAT-RBAC |
| FEAT-PROD-001 | TECH-FOUND-002 |
| FEAT-PRICE-001 | FEAT-PROD-001 |
| FEAT-TAX-001 | FEAT-PROD-001 |
| FEAT-ORD-001 | Customer + Product + Pricing + Tax + RBAC |
| FEAT-ORD-005 | FEAT-ORD-001 |
| FEAT-ORD-012 | FEAT-ORD-001 + RBAC |
| FEAT-ALLOC-001 | FEAT-ORD |
| FEAT-ADJ-004 | FEAT-ALLOC + Inventory + Tax + Financial foundations |
| FEAT-INV-003 | FEAT-ALLOC-001 |
| FEAT-PAY-007 | FEAT-PAY + RBAC + Documents |
| FEAT-DOC-001 | Orders + Tax + Payments where required |
| FEAT-DEL-001 | Approved/processable Orders |
| FEAT-RET-001 | Completed/eligible order states |
| FEAT-CR-001 | Returns + Receivable state |
| FEAT-ACC-003 | Order + Payment + Inventory + Receivable events |
| FEAT-REP-* | Authoritative transaction domains |

---

# 38. Recommended Solo-Developer Build Phases

## Phase 0 — Foundation

```text
TECH-FOUND-001
TECH-FOUND-002
TECH-FOUND-003
TECH-FOUND-004
UI-001
UI-002
```

**Gate:** Application boots, database works, design system foundation exists.

## Phase 1 — Identity & Access

```text
FEAT-AUTH-001..004
FEAT-RBAC-001..003
```

**Gate:** Correct user can access only correct workspace/scope.

## Phase 2 — Master Data

```text
FEAT-SYS-001..002
FEAT-CUS-001..004
FEAT-SLM-001..002
FEAT-PROD-001..003
FEAT-CAT-001
```

**Gate:** Real customers, salesmen and products can be managed safely.

## Phase 3 — Commerce Rules

```text
FEAT-PRICE-001..002
FEAT-TAX-001
```

**Gate:** Authoritative pricing and tax calculations are proven.

## Phase 4 — Salesman Order Slice

```text
FEAT-ORD-001..006
UI-009 (order creation subset)
QA-003
```

**Gate:** Salesman can create a valid real order end-to-end.

## Phase 5 — Admin Processing

```text
FEAT-ORD-010..013
```

**Gate:** Admin can safely review/process orders.

## Phase 6 — Allocation / Adjustment / Inventory

```text
FEAT-ALLOC-001..002
FEAT-ADJ-001..006
FEAT-INV-001..006
QA-004
QA-005
```

**Gate:** Partial fulfilment/adjustment works without corrupting inventory or financial history.

## Phase 7 — Payments / Invoice

```text
FEAT-PAY-001..009
FEAT-DOC-001..004
QA-006
QA-007
```

**Gate:** Cash/cheque/money order lifecycle and invoices are operational.

## Phase 8 — Delivery / Returns / Credits

```text
FEAT-DEL-001..008
FEAT-RET-001..004
FEAT-CR-001..005
```

**Gate:** Fulfilment through delivery and exception/return financial paths work.

## Phase 9 — Finance

```text
FEAT-AR-001..003
FEAT-AP-001
FEAT-ACC-001..009
QA-008
```

**Gate:** Financial events reconcile correctly into accounting/reporting.

## Phase 10 — Reports / Notifications / Audit Polish

```text
FEAT-REP-001..006
FEAT-NOTIF-001..002
FEAT-AUD-001..004
```

## Phase 11 — Hardening & Production

```text
QA-001..010
TECH-QA-001
DEPLOY-001..005
UI-010
```

**Gate:** Production readiness checklist passes.

---

# 39. Critical Path Risk Register

| Risk | Why It Matters | Mitigation |
|---|---|---|
| Inventory concurrency | Can corrupt stock | Atomic transactions + concurrency tests |
| Adjustment complexity | Affects inventory/tax/finance | Separate explicit adjustment model |
| Pricing manipulation | Commercial risk | Server validation + permissioned override |
| Tax history mutation | Financial/compliance risk | Historical snapshots |
| Payment evidence leakage | Sensitive data exposure | Private S3 + controlled access |
| Duplicate operations | Duplicate financial effects | Idempotency |
| Accounting mismatch | Financial reporting risk | Event-to-journal mapping + reconciliation |
| AI agent scope creep | Regression risk | Ticket-level implementation contract |
| Responsive shortcuts | Poor field usability | Dedicated mobile/tablet acceptance tests |
| Premature polish | Deadline risk | Vertical-slice gates |

---

# 40. Git / Branch / Commit Convention

## Branch naming

```text
feature/FEAT-ORD-001-salesman-order
feature/FEAT-ADJ-004-apply-adjustment
fix/BUG-PAY-001-payment-duplicate
chore/TECH-FOUND-001-bootstrap
qa/QA-ORD-004-order-e2e
```

## Commit format

```text
feat(order): implement salesman order creation [FEAT-ORD-001]
feat(order): add submit idempotency [FEAT-ORD-005]
feat(inventory): protect concurrent allocation [FEAT-INV-003]
fix(payment): prevent duplicate verification [BUG-PAY-001]
test(order): add order workflow coverage [QA-ORD-003]
chore(ci): add test pipeline [DEPLOY-003]
```

## Commit rule

One logical ticket or narrowly related sub-change per commit wherever practical. Avoid giant “all modules” commits.

---

# 41. AI Implementation Prompt Contract Per Ticket

When handing a ticket to Antigravity, use:

```text
IMPLEMENT TICKET: [TICKET ID]

Read first:
1. PRD references
2. Technical Architecture references
3. Security references
4. Frontend references
5. This ticket

Before coding:
- inspect existing repository implementation;
- identify dependencies;
- identify affected files/modules;
- identify regression risks;
- do not invent unresolved business policy.

Implementation:
- implement only approved scope;
- preserve existing source-of-truth rules;
- enforce backend authorization and state validation;
- preserve historical data;
- add required tests;
- implement required responsive states.

After coding:
- run targeted tests;
- run regression tests;
- verify every acceptance criterion;
- report changed files;
- report tests run;
- report any unresolved issue.

Do NOT:
- rewrite unrelated architecture;
- bypass authorization;
- trust client financial/inventory values;
- directly set protected state;
- delete historical financial/accounting records;
- mark Done with failing acceptance criteria.
```

---

# 42. Traceability Convention

Use traceability references such as:

```text
PRD-ORD-*
ARCH-ORD-*
SEC-ORD-*
UI-ORD-*
FEAT-ORD-*
QA-ORD-*
```

A production defect should be traceable backward:

```text
BUG
 ↓
FEATURE TICKET
 ↓
FRONTEND / SECURITY / ARCHITECTURE
 ↓
PRD REQUIREMENT
```

---

# 43. V1 Completion Checklist

The project is not V1-complete until:

### Foundation

```text
[ ] Application builds cleanly
[ ] Database migrations cleanly rebuild
[ ] Local/staging/prod environment separation works
[ ] Secrets are not committed
```

### Security

```text
[ ] Authentication works
[ ] Password reset works
[ ] Privileged MFA policy implemented
[ ] RBAC works
[ ] Resource scope works
[ ] IDOR tests pass
[ ] Sensitive documents are private
[ ] Audit records are immutable
```

### Commerce

```text
[ ] Customers
[ ] Salesmen
[ ] Products
[ ] Categories
[ ] Pricing
[ ] Tax
[ ] Salesman ordering
[ ] Admin order processing
```

### Operations

```text
[ ] Quantity allocation
[ ] Adjustment workflow
[ ] Inventory reservation
[ ] Stock exceptions
[ ] Delivery
```

### Finance

```text
[ ] Cash
[ ] Cheque
[ ] Money Order
[ ] Payment verification
[ ] Payment reversal
[ ] Invoice
[ ] Returns
[ ] Credits/refunds
[ ] Receivables
[ ] Payables
[ ] Accounting
[ ] Financial reports
```

### UX

```text
[ ] Admin desktop
[ ] Admin tablet
[ ] Admin mobile
[ ] Salesman desktop/tablet/mobile as applicable
[ ] Salesman mobile optimized
[ ] Delivery mobile optimized
[ ] Loading/empty/error states
[ ] Accessibility baseline
[ ] Responsive regression suite
```

### Production

```text
[ ] Staging verified
[ ] CI/CD verified
[ ] Backups verified
[ ] Monitoring verified
[ ] Security hardening verified
[ ] Production deployment verified
```

---

# 44. Deferred / Future Ticket Register

The architecture must remain extensible without prematurely implementing these in V1 unless the client explicitly expands scope.

```text
FUTURE-001 Advanced promotion engine
FUTURE-002 Item/order discount engine beyond V1 minimal capability
FUTURE-003 Additional payment methods such as bank transfer/card
FUTURE-004 Advanced route optimization
FUTURE-005 Customer self-service portal
FUTURE-006 Multi-company / multi-tenant architecture
FUTURE-007 Advanced purchasing automation
FUTURE-008 Advanced demand forecasting
FUTURE-009 AI/RAG business assistant
FUTURE-010 AI-driven sales/inventory recommendations
FUTURE-011 Advanced warehouse scanning/mobile WMS
FUTURE-012 Advanced notification channels
FUTURE-013 Advanced BI / data warehouse
```

Future work must follow the established change-management process rather than being casually added to V1. The PRD requires new client requests to be assessed for product, workflow, security, frontend, architecture/data and feature-ticket impacts before implementation. fileciteturn1file9L514-L556

---

# 45. Open Decisions / TBD Register

No AI agent may silently decide these where the specifications still mark them as policy-dependent:

```text
TBD-001 Exact password policy
TBD-002 Salesman MFA requirement
TBD-003 Warehouse MFA requirement
TBD-004 Delivery authentication mechanism
TBD-005 Step-up authentication thresholds
TBD-006 Exact refund approval matrix
TBD-007 Exact payment-verification authority matrix
TBD-008 Security-log retention
TBD-009 Business-audit retention
TBD-010 Exact session timeout values
TBD-011 Exact payment-evidence upload size limits
TBD-012 Exact cheque/money-order verification workflow
TBD-013 Exact credit-limit enforcement thresholds
TBD-014 Exact return disposition policies
TBD-015 Exact accounting posting rules not yet approved by client
```

Use conservative, configurable defaults only where the earlier security/architecture documents explicitly permit them.

---

# 46. Final Execution Rules

## Rule 01 — One source of truth

All three portals work against the same transaction core.

## Rule 02 — Never destroy history to simplify UI

Original order quantities, historical prices, tax snapshots and posted financial records remain historically meaningful.

## Rule 03 — Frontend is not security

Hiding or disabling an action does not replace backend authorization.

## Rule 04 — Every sensitive command is state-aware

Permission alone is not enough; current business state and domain constraints must also pass.

## Rule 05 — Inventory is transactional

Never “fix stock” by editing an available-stock number from the frontend.

## Rule 06 — Financial records are non-destructive

Corrections use controlled adjustments/reversals, not destructive edits.

## Rule 07 — AI agents implement tickets, not imagination

An agent must not broaden scope without an explicit change decision.

## Rule 08 — A feature is not Done until its UX is Done

Loading, empty, error, disabled, permission, desktop, tablet and mobile states must be accounted for where applicable.

## Rule 09 — Critical-path domains get deeper testing

Order, allocation, inventory, payments, refunds and accounting receive more validation than ordinary CRUD modules.

## Rule 10 — Optimize for a real production product

The final system must be both operationally correct and visually consistent with the Premium B2B Commerce × Modern SaaS ERP design baseline.

---

# 47. Document 05 Sign-Off

Before implementation begins, confirm:

```text
[ ] Solo-developer + Antigravity execution model approved
[ ] Ticket taxonomy approved
[ ] Priority/risk/size model approved
[ ] Definition of Ready approved
[ ] Definition of Done approved
[ ] Dependency sequence approved
[ ] Critical-path domains identified
[ ] Edge-case register accepted
[ ] State-transition model accepted
[ ] AI-agent protocol accepted
[ ] Git conventions accepted
[ ] V1/future boundary accepted
[ ] TBD items acknowledged
```

## End of Document 05

**Specification chain complete:**

```text
01 — PRD
02 — Technical Architecture
03 — Security & Access
04 — Frontend Specification
05 — Feature & Delivery Ticket Specification
```

**Next operational step:** convert the approved ticket backlog into implementation batches and begin Phase 0 without bypassing the Definition of Ready.
