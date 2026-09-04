# TEST_MATRIX.md — Master Test Coverage Matrix

## Wholesale Distribution Management System

**Document Version:** 1.0  
**Effective Date:** September 2026  
**Status:** Authoritative Quality Assurance Contract  
**Standard:** Every critical path must include positive (happy path), negative (validation & state transitions), security (authorization & resource scoping), transactional (concurrency & atomicity), audit, and responsive tests.

---

## 0. Foundation & Core Infrastructure Coverage Matrix (Phase 00)

### 0.1 Application Bootstrap & Health (`TECH-FOUND-001`, `TECH-FOUND-003`)
- [x] **Framework Boot:** Laravel 13 boots successfully; root route `/` renders Inertia page with 200 OK (`FoundationTest::test_application_boots_and_renders_inertia_welcome_view`).
- [x] **Inertia Props Contract:** Shared props `phpVersion`, `laravelVersion`, `appName`, and `auth.user` passed to frontend view.
- [x] **Health Check Endpoint:** `/health` responds with JSON status contract for application, database, and redis (`FoundationTest::test_health_check_endpoint_contract`).
- [x] **Configuration Baseline:** Application configuration loads correct app name and environment parameters (`FoundationTest::test_application_configuration_baseline`).

### 0.2 Persistence & Cache (`TECH-FOUND-002`, `TECH-FOUND-004`)
- [x] **PostgreSQL 18 Connectivity:** Active database ping (`SELECT 1 as ping`) succeeds (`FoundationTest::test_database_connection_operational`).
- [x] **Migration System:** Default framework migrations execute cleanly creating `users`, `cache`, and `jobs` tables without business schema.
- [x] **Redis 7 Connectivity:** Redis ping via Predis returns PONG and cache put/get operations succeed.

### 0.3 Frontend Static Analysis & Asset Compilation (`UI-001`, `UI-002`)
- [x] **TypeScript Validation:** `npm run type-check` (`tsc --noEmit`) passes with 0 type errors.
- [x] **Vite Production Build:** `npm run build` compiles 2,387 modules into optimized chunks in $\le 2\text{s}$ with 0 warnings.
- [x] **Design Tokens & Primitives:** Tailwind CSS 4 HSL variables, Inter font, Button, Card, Input, and Badge primitives verified.

---

## 1. Domain Coverage Matrices

### 1.1 Authentication & Session Revocation (`AUTH` / `FEAT-AUTH-001` & `FEAT-AUTH-002`)
- [x] **Happy Path:** Valid credentials authenticate and redirect to portal dashboard (`QA-001`, `AUTH-002`).
- [x] **Validation:** Missing email/password returns 422 with structured field error.
- [x] **Security:** Invalid credentials return generic non-enumerating error (`trans('auth.failed')`, `AUTH-003`, `AUTH-004`).
- [x] **Security:** Repeated failed logins trigger rate limiter (`429 Too Many Requests`, `AUTH-005`, `AUTH-006`).
- [x] **Security:** Suspended / inactive accounts cannot authenticate (`AUTH-008`, `AUTH-009`, `AUTH-010`, `AUTH-015`).
- [x] **State Transition:** Logout successfully revokes and invalidates session (`AUTH-012`, `AUTH-SESSION-001`, `AUTH-SESSION-002`, `AUTH-SESSION-003`).
- [x] **Session Tracking:** Authenticated users can list active sessions with approximate device, masked IP, and current session marker (`AUTH-SESSION-004`, `AUTH-SESSION-005`).
- [x] **Single Revocation:** Users can revoke specific active sessions belonging to their identity (`AUTH-SESSION-006`, `AUTH-SESSION-007`).
- [x] **Bulk Revocation:** Users can revoke all other active sessions while preserving current session (`AUTH-SESSION-008`, `AUTH-SESSION-009`).
- [x] **Revoke All Everywhere:** Users can invalidate all sessions including current and redirect to login (`AUTH-SESSION-016`).
- [x] **Security (IDOR):** Attempting to revoke another user's session returns 404 and leaves session untouched (`AUTH-SESSION-010`).
- [x] **Security (Privacy & Secrets):** Raw database session IDs are never exposed in Inertia props/DOM; opaque HMAC tokens used (`AUTH-SESSION-018`).
- [x] **Security (Audit):** Structured audit logs written for all session lifecycle events without passwords or hashes (`AUTH-SESSION-015`).
- [x] **Security Hook:** Application service supports programmatic session revocation for account security events (`AUTH-SESSION-017`).
- [x] **Password Reset (Zero Enumeration):** Forgot-password returns identical generic success for known, unknown, and suspended accounts (`AUTH-PASSWORD-003`, `AUTH-PASSWORD-004`, `AUTH-PASSWORD-005`, `AUTH-PASSWORD-024`, `AUTH-PASSWORD-025`).
- [x] **Password Reset (Rate Limiting):** Layered IP and email rate limiting protects recovery endpoints (`AUTH-PASSWORD-006`).
- [x] **Password Reset (Token Security):** High-entropy cryptographically secure tokens are single-use and time-limited (60 min) (`AUTH-PASSWORD-008`..`011`, `AUTH-PASSWORD-023`).
- [x] **Password Reset (Session Invalidation):** Successful password reset purges all active database sessions across all devices via `SessionRevocationService` (`AUTH-PASSWORD-016`, `AUTH-PASSWORD-017`).
- [x] **Password Reset (Policy & Secrets):** Enforces 8+ char password policy; plaintext passwords and raw tokens never stored or logged (`AUTH-PASSWORD-012`..`015`, `AUTH-PASSWORD-018`..`020`).
- [x] **Privileged MFA:** Super Admin / Admin / Accountant prompted for mandatory TOTP code; standard roles optional; zero bypass (`FEAT-AUTH-004` / `AUTH-MFA-001`..`034`).
- [x] **MFA Recovery & Brute-Force:** 8 hashed single-use recovery codes, atomic consumption, 5 attempts/min rate limiting, challenge expiration (`AUTH-MFA-011`..`015`, `AUTH-MFA-020`..`023`).
- [x] **MFA Step-Up & Session Security:** Password confirmation on security actions, role policy prevents mandatory disable, other sessions revoked (`AUTH-MFA-005`, `AUTH-MFA-024`..`025`, `AUTH-MFA-030`).
- [x] **Responsive:** Login, Session management, Forgot Password, and Reset Password views verified on Desktop (1280px), Tablet (768px), and Mobile (375px) (`AUTH-001`, `Sessions.tsx`, `ForgotPassword.tsx`, `ResetPassword.tsx`).

### 1.2 Roles & Primary Role Model (`RBAC` / `FEAT-RBAC-001`)
- [x] **Canonical Role Model:** All 6 authoritative roles exist (`SUPER_ADMIN`, `ADMIN`, `ACCOUNTANT`, `SALESMAN`, `WAREHOUSE_MANAGER`, `DELIVERY_PARTNER`) and backed enum validation enforces type safety (`RBAC-ROLE-001`..`003`).
- [x] **Privilege & MFA Integration:** Privileged roles (`SUPER_ADMIN`, `ADMIN`, `ACCOUNTANT`) correctly enforce mandatory MFA; standard roles optional (`RBAC-ROLE-004`..`010`).
- [x] **Authorized Role Assignment:** Privileged actor assigns new role atomically inside database transaction with `lockForUpdate` row locking (`RBAC-ROLE-011`, `RBAC-ROLE-021`).
- [x] **Zero Client Trust & Authorization:** Unauthorized roles and inactive actors rejected (`403 Forbidden`) (`RBAC-ROLE-012`, `RBAC-ROLE-032`).
- [x] **Self-Role Modification Guard:** Actors cannot modify their own role; server-side enforcement prevents self-escalation or evasion (`RBAC-ROLE-013`).
- [x] **Super Admin Protection:** Only active `SUPER_ADMIN` can grant `SUPER_ADMIN` or modify an existing `SUPER_ADMIN`; `ADMIN` rejected (`403 Forbidden`) (`RBAC-ROLE-027`, `RBAC-ROLE-028`, `RBAC-ROLE-029C`, `RBAC-ROLE-030`, `RBAC-ROLE-031`).
- [x] **Last Super Admin Guard:** Last remaining Super Administrator cannot be demoted (`422 Unprocessable Entity` / `ValidationException`) (`RBAC-ROLE-029`, `RBAC-ROLE-029B`).
- [x] **Target Session Invalidation:** Target user's active sessions immediately revoked on role transition via `SessionRevocationService`; actor session preserved (`RBAC-ROLE-018`, `RBAC-ROLE-019`).
- [x] **Audit & Secrets:** Structured audit event `auth.security_event` logged with action `ROLE_ASSIGNED`, previous/new role, and zero credentials/tokens/secrets (`RBAC-ROLE-015`..`017`).
- [x] **No-Op Safety:** Assigning identical role returns target without session revocation or audit logging (`RBAC-ROLE-033`).
- [x] **Preservation of User Invariants:** Role change preserves password, account status, email, and confirmed MFA secrets (`RBAC-ROLE-034`).
- [x] **IDOR & Route Protection:** Guessed user IDs and unauthorized route access rejected (`403 Forbidden`) (`RBAC-ROLE-020`, `RBAC-ROLE-035`).
- [x] **Authentication Regressions:** Full regression passed for login, MFA challenge, password reset, and session revocation (`RBAC-ROLE-022`..`026`).
- [x] **Responsive Role Management UI:** `resources/js/Pages/Security/Roles/Index.tsx` verified for desktop tables, mobile cards, accessible modal dialogs, search, filter, and touch targets >= 44px.

### 1.2.1 Server-Side Permission Registry (`RBAC` / `FEAT-RBAC-002`)
- [x] **Canonical Permission Enum:** Exactly 47 canonical permissions defined in `App\Enums\Permission` using `module.action` format (`RBAC-PERM-001`, `RBAC-PERM-002`).
- [x] **Unique & Metadata Rich:** All 47 permission string values unique; exhaustive metadata methods `label()`, `description()`, `module()`, `values()`, and `casesForModule()` verified (`RBAC-PERM-003`, `RBAC-PERM-004`).
- [x] **Authoritative Role-to-Permission Mappings:** Strict in-memory static mapping verified: `SUPER_ADMIN` has all 47, `ADMIN` has exactly 39 (excludes `permission.manage`, `payment.reverse`, `accounting.post`, `accounting.reverse`, `order.adjust.reverse`), `ACCOUNTANT` has exactly 15, `SALESMAN` has exactly 9, `WAREHOUSE_MANAGER` has exactly 7, `DELIVERY_PARTNER` has exactly 3 (`RBAC-PERM-005`..`011`).
- [x] **Default-Deny & Fail-Closed:** Null role, unmapped permissions, unknown permission strings, and malformed inputs return `false` or trigger `AuthorizationException` (`RBAC-PERM-012`..`014`, `RBAC-PERM-017`, `RBAC-PERM-030`).
- [x] **Account Lifecycle Gating:** Inactive statuses (`INVITED`, `SUSPENDED`, `DISABLED`) have zero effective permissions regardless of assigned role (`RBAC-PERM-015`).
- [x] **Permission Service & Helpers:** `PermissionService` provides authoritative evaluation (`has`, `hasAny`, `hasAll`, `authorize`, `getPermissionsForRole`, `getPermissionsForUser`); `User` model exposes delegating `hasPermission()` and `canPermission()` (`RBAC-PERM-016`, `RBAC-PERM-031`).
- [x] **Laravel Gate Integration:** `Gate::before` hook seamlessly evaluates canonical permission strings through `PermissionService` while leaving non-canonical abilities untouched (`RBAC-PERM-020`, `RBAC-PERM-021`, `RBAC-PERM-033`).
- [x] **Route Permission Middleware:** `EnsureUserHasPermission` middleware (`permission:module.action`) enforces authentication, active account state, and required permission; rejects unauthorized requests with 403 (`RBAC-PERM-018`, `RBAC-PERM-019`).
- [x] **Role Management Route Protection:** `/security/roles` and `/security/users/{user}/role` guarded by `permission:role.manage`; grants access to `SUPER_ADMIN` and `ADMIN`, rejects other roles with 403.
- [x] **Zero Database Overhead:** In-memory permission evaluation executes zero database queries (`RBAC-PERM-032`).
- [x] **Capability Sharing & Zero Client Trust:** Inertia shares safe `permissions` array for authenticated active user for UI hints; frontend manipulation cannot bypass server-side authorization (`RBAC-PERM-028`, `RBAC-PERM-029`).
- [x] **Authentication & Role Regressions:** Full regression passed for role transitions, session invalidation, login, MFA, and password reset (`RBAC-PERM-022`..`027`).

### 1.2.2 Resource Scope Enforcement (`RBAC` / `FEAT-RBAC-003`) — *DEFERRED per DEC-014*
> [!NOTE]
> Resource scope enforcement tests (`RBAC-SCOPE-001` through `RBAC-SCOPE-030`) are deferred until concrete domain models (`Customer`, `Order`, `Delivery`) and their assignment relationships are implemented in Phases 03, 05, and 08 (`FEAT-CUS-001`, `FEAT-CUS-002`, `FEAT-ORD-001`, `FEAT-DLV-001`). V1 enforces single central warehouse operational scope per PRD §39.2.

### 1.2.3 Application Identity & System Configuration (`SYS` / `FEAT-SYS-001`)
- [x] **Authoritative Identity Source:** `ApplicationIdentityService` returns deterministic, server-authoritative identity DTO from configuration baseline (`SYS-IDENTITY-001`).
- [x] **Safe Documented Defaults:** When configuration values are missing, empty, or whitespace, fallback defaults are provided deterministically (`SYS-IDENTITY-002`).
- [x] **Trimmed & Normalized String Sanitization:** Whitespace in identity configuration is trimmed and normalized (`SYS-IDENTITY-003`).
- [x] **Safe Inertia Sharing:** `HandleInertiaRequests` middleware safely shares public identity fields (`appName`, `identity`) on all Inertia responses (`SYS-IDENTITY-004`).
- [x] **Zero Secrets Exposure:** Sensitive server configuration, database credentials, and internal paths are strictly omitted from shared public identity props (`SYS-IDENTITY-005`).
- [x] **Auth Surface Identity Rendering:** Dynamic branding displays correctly on Login (`SYS-IDENTITY-006`), Forgot Password (`SYS-IDENTITY-007`), Reset Password (`SYS-IDENTITY-008`), and MFA Challenge (`SYS-IDENTITY-009`) surfaces without hardcoded title strings.
- [x] **Output & XSS Safety:** Identity values containing HTML/script tags are safely escaped by Blade and React rendering engines (`SYS-IDENTITY-010`).
- [x] **Zero Database Queries:** `ApplicationIdentityService` executes zero database queries, ensuring instantaneous resolution without database overhead (`SYS-IDENTITY-011`).
- [x] **Authentication & RBAC Regression Safety:** Authentication flows, session revocation, MFA, and permission checks continue to pass with full fidelity (`SYS-IDENTITY-012`).

### 1.3 Customer Management (`CUSTOMER`)
- [ ] **Happy Path:** Admin creates and updates customer profile with credit limit and payment terms.
- [ ] **Validation:** Duplicate business tax ID or malformed email/phone rejected (422).
- [ ] **Security:** Only `customer.create` and `customer.update` permissions permit modifications.
- [ ] **State Transition:** Changing customer lifecycle from `ACTIVE` to `INACTIVE` immediately blocks new orders.
- [ ] **Audit:** Credit limit changes capture old limit, new limit, actor, and reason.
- [ ] **Responsive:** Customer list dense table on Desktop; mobile card view on Mobile (375px).

### 1.4 Salesman Management (`SALESMAN`)
- [ ] **Happy Path:** Admin creates salesman account and assigns customer portfolio.
- [ ] **Validation:** Duplicate email rejected.
- [ ] **Security:** Suspended salesman immediately prevented from placing orders or recording payments.
- [ ] **Audit:** Reassigning customer to a new salesman logs previous and new salesman IDs.

### 1.5 Product & Category Management (`PRODUCT` & `CAT`)
- [ ] **Happy Path:** Admin creates product with SKU, cost, list price, default selling price, minimum price, and tax profile.
- [ ] **Validation:** Duplicate SKU rejected; minimum price > MRP rejected.
- [ ] **Security:** Product image upload validated for file size ($\le 5\text{MB}$) and MIME type.
- [ ] **State Transition:** Deactivating product blocks new order creation; historical orders remain intact.
- [ ] **Responsive:** Product catalog grid with lazy-loaded images on Desktop and Mobile.

### 1.6 Pricing Engine (`PRICING`)
- [ ] **Happy Path:** Salesman selects actual selling price within allowed bounds (`min_price <= price <= mrp`).
- [ ] **Validation:** Selling price below minimum allowed price rejected with 422 unless override authorized.
- [ ] **Security:** Price override requires `pricing.override` permission and mandatory documented reason (`FEAT-PRICE-002`).
- [ ] **Database / Snapshot:** Order item permanently stores actual transaction price; product master edits do not alter historical orders (`EDGE-007`).
- [ ] **Audit:** Price override event logged with authorized actor, original range, and override value.

### 1.7 Product-Specific Tax (`TAX`)
- [ ] **Happy Path:** Line-item tax calculated correctly using assigned product tax profile rate (`RULE-TAX-001`).
- [ ] **Validation:** Zero-tax (exempt) and standard-rate products mixed in one order calculate correct aggregated tax.
- [ ] **Database / Snapshot:** Historical line records `tax_profile_id`, `tax_rate`, and `tax_amount`. Updating tax rate on product profile does not retroactively change existing orders (`EDGE-008`, `EDGE-023`).
- [ ] **Rounding Test:** Banker's rounding (`ROUND_HALF_UP`) verified to 2 decimal places per line item.

### 1.8 Order Creation & Submission (`ORDER`)
- [ ] **Happy Path:** Salesman selects customer, adds items, sets quantities, reviews totals, and submits order (`FEAT-ORD-001`).
- [ ] **Validation:** Zero or negative quantities rejected.
- [ ] **Validation:** Inactive customer or inactive product rejected.
- [ ] **Security (Zero Client Trust):** Submitting tampered line subtotal or grand total is overridden by server calculations (`RULE-SEC-002`).
- [ ] **Security (Scope):** Salesman attempting to create order for unassigned customer rejected (`403 Forbidden`).
- [ ] **Idempotency:** Submitting same payload with identical idempotency token returns original order without duplicate DB rows (`EDGE-001`, `FEAT-ORD-005`).
- [ ] **Database Transaction:** Order creation failure midway rolls back completely (`DB::transaction`).
- [ ] **Audit:** Order submission creates `orders` audit record.
- [ ] **Responsive:** Full order flow verified on Mobile S (320px), Mobile (375px), Tablet (768px), and Desktop (1440px).

### 1.9 Admin Order Processing (`ORDER PROCESSING`)
- [ ] **Happy Path:** Submitted order appears in `New Orders` queue with correct badge count; Admin approves order.
- [ ] **State Transition:** Approved order moves from `New Orders` to `Active Orders`; status changes to `APPROVED`.
- [ ] **State Transition:** Rejected order requires mandatory rejection reason; moves to `Cancelled` queue.
- [ ] **Security:** Non-admin roles attempting to approve orders receive `403 Forbidden`.
- [ ] **Audit:** Approval and rejection events recorded with actor ID and timestamp.

### 1.10 Quantity Allocation & Adjustments (`ALLOCATION` & `ADJUSTMENT`)
- [ ] **Happy Path:** Damaged stock reported; Admin creates adjustment cancelling 2 units out of 10 (`FEAT-ADJ-004`).
- [ ] **Invariant Assertion:** Original `ordered_quantity` remains 10; `cancelled_quantity` becomes 2; `fulfillable_quantity` becomes 8 (`RULE-DOM-001`, `RULE-ALLOC-001`).
- [ ] **Validation:** Cancelling more units than fulfillable quantity rejected (`EDGE-009`).
- [ ] **Recalculation:** Subtotal, line tax, and grand total recalculated authoritatively for 8 fulfillable units (`FEAT-ADJ-002`).
- [ ] **Concurrency:** Two admins approving conflicting adjustments concurrently handled via row locks (`EDGE-010`).
- [ ] **Reversal:** Adjustment reversal restores fulfillable allocation and updates financial balance via reversing record (`FEAT-ADJ-005`).
- [ ] **Audit:** Full before/after adjustment snapshot recorded in `order_adjustments`.

### 1.11 Inventory Reservation & Warehouse (`INVENTORY`)
- [ ] **Happy Path:** Order approval atomically reserves stock; `reserved` increases, `available` decreases (`RULE-INV-001`).
- [ ] **Concurrency (Race Test):** Two concurrent orders competing for last unit of available stock: exactly one succeeds, one fails cleanly (`EDGE-004`, `QA-005`).
- [ ] **Constraint Assertion:** Normal reservation never allows `available < 0` (`RULE-INV-002`).
- [ ] **Movement Ledger:** Stock movement record created (`AVAILABLE` → `RESERVED`) in `inventory_movements`.
- [ ] **Damaged Stock:** Warehouse stock exception moves stock from `RESERVED` to `DAMAGED` (`RULE-INV-004`).
- [ ] **Security:** Direct manual editing of available stock via API prohibited.

### 1.12 Payment Entry & Verification (`PAYMENT`)
- [ ] **Happy Path (Cash):** Cash payment recorded; customer outstanding balance decreases.
- [ ] **Happy Path (Cheque):** Cheque recorded with mandatory JPEG image, cheque number, date, and bank name.
- [ ] **Happy Path (Money Order):** Money order recorded with mandatory JPEG image and reference.
- [ ] **Validation:** Cheque or Money Order submitted without JPEG evidence rejected with 422 (`RULE-PAY-002`).
- [ ] **Verification:** Authorized accountant verifies payment (`payment.verify`); status transitions to `VERIFIED`.
- [ ] **Idempotency:** Repeated payment submission prevented via unique payment idempotency token (`EDGE-002`).
- [ ] **Reversal / Bounce:** Bounced cheque transitions to `BOUNCED`, reverses customer credit, and logs audit record (`FEAT-PAY-009`).

### 1.13 Payment Evidence Storage (`PAYMENT EVIDENCE`)
- [ ] **Security (MIME Sniffing):** Uploading executable or script renamed to `.jpg` rejected by server-side magic byte inspection (`RULE-FILE-001`, `EDGE-014`).
- [ ] **Security (Storage Isolation):** S3 bucket blocks public ACLs; direct HTTP access to S3 object returns 403.
- [ ] **Security (Access Control):** Previewing evidence generates short-lived presigned URL ($\le 15\text{ mins}$) accessible only to authorized roles (`QA-006`).
- [ ] **Security (IDOR):** Salesman cannot access payment evidence for unassigned customer.

### 1.14 Invoicing & Document Generation (`INVOICE`)
- [ ] **Happy Path:** Invoice generated with unique number (`INV-XXXXXX`) from historical line snapshots.
- [ ] **Hard Rule Assertion (NO PRODUCT IMAGES):** Output HTML and PDF inspected; zero `<img>` tags or thumbnail URLs present for products (`RULE-DOC-001`).
- [ ] **Historical Immutability:** Editing product name, price, or tax in catalog does NOT alter historical invoice PDF or HTML (`RULE-DOC-003`, `EDGE-022`).
- [ ] **Printing:** Print stylesheet hides navigation chrome, buttons, and headers cleanly.
- [ ] **PDF Export:** Chromium PDF renderer outputs clean, multi-page, formatted vector document.

### 1.15 Delivery Operations (`DELIVERY`)
- [ ] **Happy Path:** Order assigned to Delivery Partner; driver accepts, marks picked up, out for delivery, and delivered (`FEAT-DEL-005`).
- [ ] **Quantity Visibility:** Driver manifest displays current deliverable quantity (8), never cancelled quantity (2) (`RULE-DLV-001`).
- [ ] **Security (Scoping):** Driver can only view assigned deliveries in mobile workspace (`RULE-DLV-002`).
- [ ] **Failure Handling:** Failed delivery records mandatory structured reason code (customer unavailable, wrong address, etc.) (`FEAT-DEL-006`, `EDGE-016`).
- [ ] **Idempotency:** Duplicate delivery completion clicks execute exactly once (`EDGE-015`).
- [ ] **Responsive:** Mobile driver view tested with simulated touch interactions on 375px screen.

### 1.16 Returns, Credits & Refunds (`RETURNS`, `CREDITS`, `REFUNDS`)
- [ ] **Happy Path:** Customer returns 2 delivered units; warehouse inspects, accepts 1 good, accepts 1 damaged.
- [ ] **Validation:** Return quantity > delivered quantity rejected.
- [ ] **Inventory Movement:** Accepted good unit returned to `AVAILABLE`; damaged unit moved to `DAMAGED`.
- [ ] **Credit Note:** Credit Note (`CR-XXXXXX`) issued strictly for accepted units.
- [ ] **Refund Approval:** Cash refund requires explicit approval by separate authorized user (`RULE-CR-003`).
- [ ] **Constraint Assertion:** Refund cannot exceed eligible customer credit balance (`EDGE-017`).

### 1.17 Receivables & Customer Statements (`AR`)
- [ ] **Happy Path:** Customer statement lists chronological invoices, payments, credits, and running balance.
- [ ] **Aging Buckets:** Invoices categorized accurately into 0-30, 31-60, 61-90, and 90+ days.
- [ ] **Credit Limit Enforcement:** Orders exceeding available credit limit flagged for admin approval (`PRD §25.3`).

### 1.18 General Ledger Accounting (`ACCOUNTING`)
- [ ] **Happy Path:** Invoice issuance automatically generates balanced double-entry journal (Debit AR, Credit Revenue, Credit Tax Liability) (`FEAT-ACC-003`).
- [ ] **Happy Path:** Payment verification generates balanced journal (Debit Cash, Credit AR).
- [ ] **Balance Assertion:** Trial balance total debits equal total credits ($\sum \text{Debits} = \sum \text{Credits}$) (`FEAT-ACC-005`).
- [ ] **Immutability Assertion:** Direct `UPDATE` or `DELETE` on `journal_lines` rejected by database constraint (`RULE-ACC-001`).
- [ ] **Reversal:** Reversing journal entry successfully offsets original entry and is linked by reference ID (`FEAT-ACC-008`, `EDGE-018`).

### 1.19 Audit & Compliance (`AUDIT`)
- [ ] **Completeness:** All state changes across orders, adjustments, prices, payments, and permissions produce `audit_logs` entries (`FEAT-AUD-001`).
- [ ] **Immutability:** Audit log table rejects `UPDATE` and `DELETE` commands (`FEAT-AUD-003`).
- [ ] **Timeline UI:** Activity timeline renders human-readable chronological event stream.

---

## 2. Cross-Feature Edge Case Register (`EDGE-001` to `EDGE-025`)

| Edge Case ID | Scenario | Expected Behavior | Target Test Suite |
|---|---|---|---|
| `EDGE-001` | Double order submission on network retry | Second request returns original order; no duplicate DB rows | `QA-003` |
| `EDGE-002` | Double payment submission | Idempotency token prevents duplicate financial recording | `QA-007` |
| `EDGE-003` | Double adjustment approval | Only first approval applies changes; second returns error | `QA-004` |
| `EDGE-004` | Concurrent stock allocation for scarce inventory | Row lock prevents overselling; available stock never negative | `QA-005` |
| `EDGE-005` | Product deactivated during active checkout | Server validation rejects submission with descriptive message | `QA-003` |
| `EDGE-006` | Customer suspended during checkout | Server validation rejects submission; order blocked | `QA-003` |
| `EDGE-007` | Product price changed after order placement | Historical order line price snapshot remains unchanged | `QA-003` |
| `EDGE-008` | Product tax configuration changed after placement | Historical order line tax snapshot remains unchanged | `QA-003` |
| `EDGE-009` | Adjustment quantity exceeds eligible remaining units | Request rejected with 422 domain validation error | `QA-004` |
| `EDGE-010` | Two admins adjust same order item concurrently | Pessimistic lock serializes operations; second validates new state | `QA-004` |
| `EDGE-011` | Order reaches disallowed state while adjustment pending | Adjustment approval fails state-machine transition check | `QA-004` |
| `EDGE-012` | Payment verification attempted twice | Second attempt returns already verified status idempotently | `QA-007` |
| `EDGE-013` | Payment evidence file inaccessible in S3 | Controlled error response; no unhandled exception crash | `QA-006` |
| `EDGE-014` | Executable file disguised as JPEG uploaded | Server MIME sniffing detects invalid magic bytes; rejects 422 | `QA-006` |
| `EDGE-015` | Delivery completion submitted twice | Second request recognized as duplicate; downstream hooks fire once | `QA-009` |
| `EDGE-016` | Delivery marked failed then immediately delivered | Invalid state transition rejected by delivery state machine | `QA-009` |
| `EDGE-017` | Refund requested exceeds customer credit balance | Domain validation rejects request; refund cannot exceed balance | `QA-007` |
| `EDGE-018` | Accounting reversal attempted twice on same journal | System detects prior reversal; rejects duplicate reversal | `QA-008` |
| `EDGE-019` | User role changed during active session | Next privileged request triggers permission re-evaluation | `QA-002` |
| `EDGE-020` | User account suspended during active session | Immediate 401/403 on next API or page request | `QA-001` |
| `EDGE-021` | ID substitution (IDOR) on customer or order URL | Server-side query scoping returns 403/404 | `QA-002` |
| `EDGE-022` | Historical invoice viewed after product master rename | Invoice renders immutable product name snapshot | `QA-003` |
| `EDGE-023` | Historical tax report run after tax rate change | Report reflects transaction-time snapshotted tax data | `QA-008` |
| `EDGE-024` | Database connection fails midway through adjustment | Entire multi-table transaction rolls back cleanly | `QA-004` |
| `EDGE-025` | Financial report discrepancy check | Analytical report matches sum of general ledger journal lines | `QA-008` |

---

## 3. Responsive Quality Assurance Breakpoint Matrix

| View / Page | Mobile S (320px) | Mobile (375px) | Tablet (768px) | Desktop (1280px) | Desktop XL (1920px) |
|---|---|---|---|---|---|
| **Login / Auth** | Stacked form | Stacked form | Centered card | Centered card | Centered card |
| **Salesman Catalog** | 1-col card | 1-col card | 2-col grid | 3-col grid | 4-col grid |
| **Order Creation** | Bottom sheet | Bottom sheet | Split sheet | Right drawer | Right panel |
| **Admin Orders** | Stacked list | Stacked list | Priority table | Full data table | Dense command table |
| **Adjustment Modal** | Full screen | Full screen | Centered modal | Centered modal | Centered modal |
| **Delivery Driver** | Touch cards | Touch cards | Split view | Desktop view | Desktop view |
| **Invoice View** | Responsive fit | Responsive fit | Standard page | A4 preview | A4 preview |
| **General Ledger** | Summary cards | Summary cards | Scrollable table | Dense ledger table | Dense ledger table |
