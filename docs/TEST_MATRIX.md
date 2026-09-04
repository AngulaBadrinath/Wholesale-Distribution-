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

### 1.2.4 Company Information & Business Details Configuration (`SYS` / `FEAT-SYS-002`)
- [x] **Authorized Retrieval:** Active `SUPER_ADMIN` and `ADMIN` can retrieve company settings overview with Inertia view (`SYS-COMPANY-001`).
- [x] **Unauthenticated Access Denial:** Unauthenticated requests to company settings redirect to login (`SYS-COMPANY-002`).
- [x] **Unauthorized Role Denial:** Non-administrative roles (`ACCOUNTANT`, `SALESMAN`, `WAREHOUSE_MANAGER`, `DELIVERY_PARTNER`) rejected with 403 Forbidden (`SYS-COMPANY-003`).
- [x] **Inactive Account Protection:** Inactive administrators (`SUSPENDED`, `DISABLED`, `INVITED`) are blocked by middleware and rejected by service (`SYS-COMPANY-004`).
- [x] **Authorized Mutation Flow:** Authorized admin updates legal name, address, tax IDs, and invoicing defaults atomically (`SYS-COMPANY-005`).
- [x] **Server-Side Validation:** Malformed email, oversized strings, invalid country codes, and invalid timezones rejected with 422 (`SYS-COMPANY-006`).
- [x] **Whitespace & Formatting Normalization:** Whitespace in text and uppercase normalization for country/currency codes executed automatically (`SYS-COMPANY-007`).
- [x] **Unsafe URL Scheme Rejection:** `javascript:`, `data:`, and `vbscript:` schemes in website URL rejected (`SYS-COMPANY-008`).
- [x] **Output & XSS Safety:** Script tags and HTML payloads in company attributes safely stored and rendered as escaped text (`SYS-COMPANY-009`).
- [x] **Safe Public Representation:** Transformation methods format address and omit internal metadata/singleton flags (`SYS-COMPANY-010`).
- [x] **Inertia Sharing:** Safe company summary shared through Inertia props without leaking internal credentials (`SYS-COMPANY-011`).
- [x] **Singleton Invariant:** Exactly one authoritative company configuration record is preserved in the database (`SYS-COMPANY-012`).
- [x] **Atomic Transaction with Row Locking:** Company updates execute inside database transaction with `lockForUpdate` row locking (`SYS-COMPANY-013`).
- [x] **Audit Event Generation:** Successful updates produce `SYSTEM_COMPANY_INFORMATION_UPDATED` audit log with changed field keys (`SYS-COMPANY-014`).
- [x] **SYS-001 Application Identity Independence:** Changes to database company information do not overwrite deployment application branding (`SYS-COMPANY-015`).

### 1.3 Customer Management (`CUSTOMER` / `FEAT-CUS-001`)
- [x] **Authorized List Access:** `SUPER_ADMIN`, `ADMIN`, `ACCOUNTANT`, `SALESMAN` can view customer list (`CUS-CRUD-001`).
- [x] **Unauthorized List Denial:** Roles without `customer.view` (`WAREHOUSE_MANAGER`, `DELIVERY_PARTNER`) rejected with 403 Forbidden (`CUS-CRUD-002`).
- [x] **Unauthenticated Access Denial:** Guest requests to customer endpoints redirect to login (`CUS-CRUD-003`).
- [x] **Authorized Create Form:** Privileged roles (`SUPER_ADMIN`, `ADMIN`) can access customer creation form with suggested sequential code (`CUS-CRUD-004`).
- [x] **Authorized Customer Creation:** Full customer record created with physical and shipping addresses, credit limit, payment terms, and status (`CUS-CRUD-005`).
- [x] **Unauthorized Creation Denial:** Non-admin roles (`ACCOUNTANT`, `SALESMAN`, `WAREHOUSE_MANAGER`, `DELIVERY_PARTNER`) rejected with 403 Forbidden (`CUS-CRUD-006`).
- [x] **Required Field Validation:** Missing code, name, contact name, phone, billing address line 1, city, state, postal code, country, credit limit, payment terms, or status rejected with 422 (`CUS-CRUD-007`).
- [x] **Email Format Validation:** Malformed customer emails rejected with 422 (`CUS-CRUD-008`).
- [x] **Maximum Length Validation:** Oversized codes (>32), names (>255), and country codes (!=2) rejected (`CUS-CRUD-009`).
- [x] **Unique Customer Code Enforcement:** Duplicate customer codes return clean validation errors (`CUS-CRUD-010`).
- [x] **Database Constraint Race Safety:** Database unique constraint on `code` prevents race conditions (`CUS-CRUD-011`).
- [x] **Authorized Detail View:** Roles with `customer.view` can inspect full customer profile and formatted addresses (`CUS-CRUD-012`).
- [x] **Unauthorized Detail Denial:** Roles without `customer.view` rejected with 403 Forbidden (`CUS-CRUD-013`).
- [x] **Authorized Update Flow:** Authorized admin updates customer details and credit terms atomically (`CUS-CRUD-014`).
- [x] **Unauthorized Update Denial:** Non-admin roles rejected with 403 Forbidden on edit form and update mutation (`CUS-CRUD-015`).
- [x] **Enum State Invariant:** Arbitrary or invalid customer status and payment term values rejected (`CUS-CRUD-016`).
- [x] **Lifecycle State Transition & Deactivation:** Authorized admin can transition status to `INACTIVE` or `ON_HOLD`; inactive status blocks order placement (`CUS-CRUD-017`, `CUS-CRUD-029`).
- [x] **Unauthorized Lifecycle Transition Denial:** Unauthorized roles cannot alter lifecycle states (`CUS-CRUD-018`).
- [x] **Server-Side Debounced Search:** Fast case-insensitive search across code, name, contact name, email, and phone (`CUS-CRUD-019`).
- [x] **Database Query Pagination:** Database-level pagination with configurable per-page sizing verified (`CUS-CRUD-020`).
- [x] **Sort and Filter Parameter Validation:** Status filters and multi-column sorting applied securely (`CUS-CRUD-021`).
- [x] **IDOR & Scoping Protection:** Direct customer ID manipulation rejected for unauthorized actors (`CUS-CRUD-022`).
- [x] **Audit Logging:** Structured audit events (`CUSTOMER_CREATED`, `CUSTOMER_UPDATED`, `CUSTOMER_STATUS_CHANGED`, `CUSTOMER_DEACTIVATED`) logged with changed keys and actor metadata (`CUS-CRUD-023`).
- [x] **Secrets & Credential Protection:** Customer audit events strictly contain zero passwords, tokens, or sensitive credentials (`CUS-CRUD-024`).
- [x] **Identity Stability:** Primary key and customer code remain permanent and relational across updates (`CUS-CRUD-025`).
- [x] **Authoritative RBAC Integration:** Verified against closed 47-permission registry and PermissionService (`CUS-CRUD-026`).
- [x] **Sequential Code Generation:** Deterministic sequential customer code generation (`CUS-CRUD-027`).
- [x] **Address Formatting Helpers:** Address formatters handle multi-line and single-line comma formatting accurately (`CUS-CRUD-028`).
- [x] **Lifecycle Invariants & Order Capability:** `ACTIVE` allows order placement; `ON_HOLD` and `INACTIVE` restrict order entry (`CUS-CRUD-029`).
- [x] **Adversarial & XSS Sanitization:** Script tags and HTML payloads safely stored and escaped without evaluation (`CUS-CRUD-030`).

### 1.3.1 Customer Assignment & Scoping to Salesmen (`CUSTOMER` / `FEAT-CUS-002`)
- [x] **Assigned Salesman Visibility:** Admin can view assigned salesman information on customer index and show views (`CUS-SLM-001`).
- [x] **Active Eligible Salesman Assignment:** Admin assigns active eligible salesman to customer atomically (`CUS-SLM-002`).
- [x] **Customer Reassignment:** Admin reassigns customer from Salesman A to Salesman B with audit trail (`CUS-SLM-003`).
- [x] **Customer Unassignment:** Admin unassigns customer with explicit unassignment audit logging (`CUS-SLM-004`).
- [x] **Unauthorized Role Denial:** Non-admin roles without `customer.update` (`ACCOUNTANT`, `WAREHOUSE_MANAGER`, `DELIVERY_PARTNER`) rejected with 403 Forbidden (`CUS-SLM-005`).
- [x] **Salesman Assignment Denial:** Salesmen cannot assign, reassign, or self-assign customers (`CUS-SLM-006`).
- [x] **Nonexistent Salesman Rejection:** Nonexistent user ID rejected with 422 Unprocessable Entity (`CUS-SLM-007`).
- [x] **Non-Salesman Target Rejection:** Assigning user with role other than `SALESMAN` (`ADMIN`, `SUPER_ADMIN`, `ACCOUNTANT`, etc.) rejected with 422 (`CUS-SLM-008`).
- [x] **Inactive Salesman Rejection:** Inactive salesman accounts (`INVITED`, `SUSPENDED`, `DISABLED`) rejected with 422 (`CUS-SLM-009`).
- [x] **Salesman Resource Scoping:** Salesmen only see and access customers in their own assigned portfolio (`CUS-SLM-010`).
- [x] **IDOR Scope Protection:** Direct URL access (`/customers/{id}`) to other salesmen's customers rejected with 403 Forbidden (`CUS-SLM-011`).
- [x] **Scoped Portfolio Search:** Customer search for Salesman role is strictly scoped to assigned customers (`CUS-SLM-012`).
- [x] **Admin Salesman Filtering:** Admin can filter customer directory by specific assigned salesman ID (`CUS-SLM-013`).
- [x] **Admin Unassigned Filtering:** Admin can filter customer directory for unassigned accounts (`CUS-SLM-014`).
- [x] **Assignment Audit Logging:** `CUSTOMER_SALESMAN_ASSIGNED` structured audit log written with actor, customer, and salesman IDs (`CUS-SLM-015`).
- [x] **Reassignment Audit Logging:** `CUSTOMER_SALESMAN_REASSIGNED` structured audit log written with previous and new salesman IDs (`CUS-SLM-016`).
- [x] **Unassignment Audit Logging:** `CUSTOMER_SALESMAN_UNASSIGNED` structured audit log written with previous salesman ID (`CUS-SLM-017`).
- [x] **Audit Secrets Protection:** Zero passwords, MFA tokens, or session secrets present in assignment audit logs (`CUS-SLM-018`).
- [x] **Transactional Row Locking & Atomicity:** Assignment executes in `DB::transaction` with `lockForUpdate` row locking to prevent race conditions (`CUS-SLM-019`).
- [x] **Customer Identity Stability:** Assignment changes preserve customer ID, code, name, addresses, and credit limit without mutation (`CUS-SLM-020`).
- [x] **Suspended Salesman Access Block:** Suspended salesmen cannot log in or access their portfolio (`CUS-SLM-021`).
- [x] **Create-Time Assignment Support:** Initial customer creation supports salesman assignment with full eligibility validation (`CUS-SLM-022`).
- [x] **Update-Time Assignment Preservation:** Customer updates without changing salesman preserve existing assignment (`CUS-SLM-023`).
- [x] **Salesman Deactivation Invariant:** Suspending a salesman preserves historical `customers.salesman_id` link without destructive nullification (`CUS-SLM-024`).
- [x] **Adversarial Query Filter Immunity:** Salesman attempting to query `salesman_id=<other>` is safely ignored and scoped (`CUS-SLM-025`).
- [x] **Centralized Eligibility on Creation:** Creation-time assignment enforces identical `UserRole::SALESMAN` + `AccountStatus::ACTIVE` eligibility rules (`CUS-SLM-026`).
- [x] **Centralized Eligibility on Update:** Update-time assignment enforces identical eligibility rules (`CUS-SLM-027`).
- [x] **Zero Client Trust (Actor Identity):** Server ignores request body actor IDs and authoritative server session actor is used exclusively (`CUS-SLM-028`).
- [x] **Frontend Payload Tampering Rejection:** Malicious types/strings in assignment payloads rejected with 422 (`CUS-SLM-029`).
- [x] **No-Op Reassignment Optimization:** Redundant assignment of same salesman produces no unnecessary database writes or duplicate audit events (`CUS-SLM-030`).

### 1.3.2 Customer Profile, Outstanding Balance & Credit Limit View (`CUSTOMER` / `FEAT-CUS-003`)
- [x] **Super Admin Profile Access:** Super Admin can access any customer profile with full action capabilities (`CUS-PROFILE-001`).
- [x] **Admin Profile Access:** Admin can access any customer profile with edit and assign capabilities (`CUS-PROFILE-002`).
- [x] **Accountant Read-Only Profile:** Accountant can view customer profile and commercial terms read-only (`CUS-PROFILE-003`).
- [x] **Salesman Assigned Portfolio Access:** Salesman can access their assigned customer's profile (`CUS-PROFILE-004`).
- [x] **Salesman IDOR Cross-Account Denial:** Salesman attempting to view another salesman's customer rejected with 403 Forbidden (`CUS-PROFILE-005`).
- [x] **Salesman IDOR Unassigned Denial:** Salesman attempting to view unassigned customer rejected with 403 Forbidden (`CUS-PROFILE-006`).
- [x] **Unauthenticated Guest Redirection:** Unauthenticated guest redirected to login (`CUS-PROFILE-007`).
- [x] **Unauthorized Role Access Denial:** Roles without `customer.view` (`WAREHOUSE_MANAGER`, `DELIVERY_PARTNER`) rejected with 403 Forbidden (`CUS-PROFILE-008`).
- [x] **Inactive Account Interception:** Inactive/suspended accounts intercepted and redirected to login (`CUS-PROFILE-009`).
- [x] **Authoritative Identity Rendering:** Profile renders customer code, name, contact name, email, phone, and notes (`CUS-PROFILE-010`).
- [x] **Complete Address Formatting:** Multi-line billing and shipping destinations formatted correctly (`CUS-PROFILE-011`).
- [x] **Commercial Credit & Terms Presentation:** Credit limit and payment terms with human labels rendered accurately (`CUS-PROFILE-012`).
- [x] **Lifecycle Status & Order Eligibility:** `ACTIVE` enables order eligibility; `ON_HOLD` / `INACTIVE` indicates restricted status (`CUS-PROFILE-013`).
- [x] **Eager-Loaded Sales Representative:** Sales representative details eagerly loaded without N+1 queries (`CUS-PROFILE-014`).
- [x] **Safe Unassigned Handling:** Unassigned customer renders `salesman: null` safely without errors (`CUS-PROFILE-015`).
- [x] **Deferred Financial Contract Reporting:** Financial summary explicitly reports `status: DEFERRED` and `is_authoritative: false` (`CUS-PROFILE-016`).
- [x] **Zero Synthetic Outstanding Balance:** Outstanding balance is `null` (not fabricated as authoritative `$0.00`) (`CUS-PROFILE-017`).
- [x] **Zero Synthetic Available Credit:** Available credit is `null` (not fabricated as authoritative credit limit) (`CUS-PROFILE-018`).
- [x] **Zero Synthetic Aging Breakdown:** Aging bucket values are `null` (not fabricated as authoritative zero balances) (`CUS-PROFILE-019`).
- [x] **No Placeholder Transaction Tables:** Database schema verified to contain zero placeholder `orders` or `payments` tables (`CUS-PROFILE-020`).
- [x] **Informational Reconciliation Notice:** Explanatory notice detailing future live transaction reconciliation returned (`CUS-PROFILE-022`).
- [x] **Secrets & Auth Data Leak Protection:** Zero passwords, MFA tokens, or session secrets present in profile payload (`CUS-PROFILE-023`).
- [x] **Tax ID Presentation:** Tax ID rendered in authorized profile context without leakage to logs (`CUS-PROFILE-024`).
- [x] **Nonexistent Resource Tampering Protection:** Nonexistent customer ID requests return 404 Not Found (`CUS-PROFILE-025`).
- [x] **Optimized Single-Query Profile Retrieval:** Profile loading runs efficiently within strict query count thresholds (`CUS-PROFILE-027`).

### 1.3.3 Customer Lifecycle Controls (`CUSTOMER` / `FEAT-CUS-004`)
- [x] **Enum Order Eligibility Baseline:** `ACTIVE` returns true for `canPlaceOrders()`; `ON_HOLD` and `INACTIVE` return false (`CUS-LIFE-001`).
- [x] **Model Order Readiness Contract - Active:** `Customer::ensureCanPlaceOrders()` completes normally for active customer (`CUS-LIFE-002`).
- [x] **Model Order Readiness Contract - On Hold:** `Customer::ensureCanPlaceOrders()` throws `ValidationException` with operational explanation for on-hold customer (`CUS-LIFE-003`).
- [x] **Model Order Readiness Contract - Inactive:** `Customer::ensureCanPlaceOrders()` throws `ValidationException` with reactivation instructions for inactive customer (`CUS-LIFE-004`).
- [x] **Authorized Transition to On Hold:** Super Admin / Admin transitions customer from `ACTIVE` to `ON_HOLD` with reason (`CUS-LIFE-005`).
- [x] **Authorized Transition to Inactive:** Super Admin / Admin transitions customer from `ACTIVE` to `INACTIVE` with reason (`CUS-LIFE-006`).
- [x] **Authorized Reactivation to Active:** Super Admin / Admin reactivates customer from `INACTIVE` / `ON_HOLD` to `ACTIVE` (`CUS-LIFE-007`).
- [x] **Transition from On Hold to Inactive:** Direct transition from `ON_HOLD` to `INACTIVE` succeeds with appropriate deactivation audit log (`CUS-LIFE-008`).
- [x] **Transition from Inactive to On Hold:** Direct transition from `INACTIVE` to `ON_HOLD` succeeds with appropriate hold audit log (`CUS-LIFE-009`).
- [x] **Unauthorized Role Denial - Salesman:** Salesman cannot update customer lifecycle status (403 Forbidden) (`CUS-LIFE-010`).
- [x] **Unauthorized Role Denial - Accountant:** Accountant cannot update customer lifecycle status (403 Forbidden) (`CUS-LIFE-011`).
- [x] **Unauthorized Role Denial - Warehouse & Delivery:** Warehouse Manager and Delivery Partner rejected with 403 Forbidden (`CUS-LIFE-012`).
- [x] **Unauthenticated Guest Redirection:** Unauthenticated status update requests redirect to login (`CUS-LIFE-013`).
- [x] **Inactive / Suspended Actor Protection:** Suspended administrator rejected from mutating status (`CUS-LIFE-014`).
- [x] **No-Op Status Transition Optimization:** Submitting identical status produces zero database writes and zero duplicate audit logs (`CUS-LIFE-015`).
- [x] **Invalid Status Payload Rejection:** Malformed status string rejected with 422 Unprocessable Entity (`CUS-LIFE-016`).
- [x] **Reason Field Validation:** Optional reason field capped at 500 characters; oversized strings rejected with 422 (`CUS-LIFE-017`).
- [x] **Audit Classification - CUSTOMER_ACTIVATED:** Reactivation produces `CUSTOMER_ACTIVATED` audit log (`CUS-LIFE-018`).
- [x] **Audit Classification - CUSTOMER_PLACED_ON_HOLD:** Placing customer on hold produces `CUSTOMER_PLACED_ON_HOLD` audit log (`CUS-LIFE-019`).
- [x] **Audit Classification - CUSTOMER_DEACTIVATED:** Deactivating customer produces `CUSTOMER_DEACTIVATED` audit log (`CUS-LIFE-020`).
- [x] **Audit Security & Privacy:** Zero passwords, MFA secrets, or session tokens in audit log metadata (`CUS-LIFE-021`).
- [x] **Salesman Portfolio Preservation on Deactivation:** Customer deactivation preserves assigned `salesman_id` link without detachment (`CUS-LIFE-022`).
- [x] **Salesman Portfolio Preservation on Hold:** Placing customer on hold preserves assigned `salesman_id` link (`CUS-LIFE-023`).
- [x] **Salesman Read Visibility of Inactive Customer:** Salesman can still view inactive customer in assigned portfolio read-only (`CUS-LIFE-024`).
- [x] **IDOR & Cross-Customer Isolation:** Nonexistent customer ID returns 404 Not Found (`CUS-LIFE-025`).
- [x] **Generic Update Payload Status Bypass Protection:** Updating status via generic customer update endpoint behaves authoritatively (`CUS-LIFE-026`).
- [x] **Directory Filter by Lifecycle State:** Customer index filtering by `status=active`, `status=on_hold`, and `status=inactive` operates accurately (`CUS-LIFE-027`).
### 1.4 Salesman Management (`SALESMAN`)

### 1.4.1 Salesman Account Management & Lifecycle (`SALESMAN` / `FEAT-SLM-001`)
- [x] **Authorized Directory Listing:** Super Admin and Admin can access salesman directory (`SLM-ACC-001`).
- [x] **Unauthorized Directory Denial:** Accountant, Salesman, Warehouse Manager, Delivery Partner rejected with 403 Forbidden (`SLM-ACC-002`).
- [x] **Unauthenticated Redirection:** Guests redirected to login (`SLM-ACC-003`).
- [x] **Authorized Salesman Provisioning:** Admin provisions salesman account with name, email, password, and status (`SLM-ACC-004`).
- [x] **Forced Role Assignment:** Server strictly enforces `UserRole::SALESMAN` regardless of client payload (`SLM-ACC-005`).
- [x] **Duplicate Email Rejection:** Duplicate email rejected with 422 Unprocessable Entity (`SLM-ACC-006`).
- [x] **Password Validation & Hashing:** Password validated and securely hashed in database (`SLM-ACC-007`).
- [x] **Authorized Profile View:** Admin views salesman details and assigned customer portfolio list (`SLM-ACC-008`).
- [x] **Authorized Profile Edit:** Admin updates salesman name and email (`SLM-ACC-009`).
- [x] **Transition to Suspended:** Admin transitions salesman to suspended with reason and produces `SALESMAN_SUSPENDED` audit log (`SLM-ACC-010`).
- [x] **Session Revocation on Suspension:** Suspending salesman immediately terminates active database sessions (`SLM-ACC-011`).
- [x] **Authentication Block on Suspension:** Suspended salesman cannot authenticate (`SLM-ACC-012`).
- [x] **Customer Assignment Preservation on Suspension:** Suspending salesman preserves `customers.salesman_id` and blocks new assignments (`SLM-ACC-013`).
- [x] **Transition to Disabled:** Admin transitions salesman to disabled and revokes active sessions (`SLM-ACC-014`).
- [x] **Reactivation to Active:** Admin reactivates suspended/disabled salesman to active (`SLM-ACC-015`).
- [x] **Reactivated Login Restoration:** Reactivated salesman can successfully authenticate (`SLM-ACC-016`).
- [x] **Self-Suspension Protection:** Admin attempting to alter own account via salesman endpoint is rejected (`SLM-ACC-017`).
- [x] **No-Op Status Optimization:** Redundant status transition produces zero database writes and zero duplicate audit logs (`SLM-ACC-018`).
- [x] **Directory Status Filtering:** Filtering by `status=active`, `status=suspended`, `status=disabled`, `status=invited` returns exact subsets (`SLM-ACC-019`).
- [x] **Search Scoping:** Search query matches salesman name and email without leaking non-salesman users (`SLM-ACC-020`).
- [x] **Audit Privacy:** Audit logs contain zero passwords, MFA secrets, or session tokens (`SLM-ACC-021`).
- [x] **Non-Salesman IDOR Protection:** Attempting to manage non-salesman user via `/salesmen/{id}` returns 404 Not Found (`SLM-ACC-022`).
- [x] **Enum Metadata & Transition Matrix:** `label()`, `description()`, `allowedTransitions()`, `canTransitionTo()` return correct state matrix (`SLM-ACC-023`).

### 1.4.2 Salesman Scoped Customer Access Enforcement (`SALESMAN` / `FEAT-SLM-002`)
- [x] **Scoped Portfolio Listing:** Salesman can list only customers assigned to their user identity (`SLM-SCOPE-001`).
- [x] **Single Resource IDOR Prevention:** Direct URL access (`/customers/{id}`) to other salesmen's customers rejected with 403 Forbidden (`SLM-SCOPE-002`).
- [x] **Unassigned Customer IDOR Prevention:** Direct URL access to unassigned customers rejected with 403 Forbidden (`SLM-SCOPE-003`).
- [x] **Assigned ON_HOLD Customer Visibility:** Salesman can view profile and directory listing for their assigned customer on hold (`SLM-SCOPE-004`).
- [x] **Assigned INACTIVE Customer Visibility:** Salesman can view profile and directory listing for their assigned inactive customer (`SLM-SCOPE-005`).
- [x] **Other Salesman ON_HOLD Customer Denial:** Salesman attempting to view another salesman's customer on hold rejected with 403 Forbidden (`SLM-SCOPE-006`).
- [x] **Other Salesman INACTIVE Customer Denial:** Salesman attempting to view another salesman's inactive customer rejected with 403 Forbidden (`SLM-SCOPE-007`).
- [x] **Scoped Portfolio Directory Search:** Customer directory search is strictly scoped to assigned customers without cross-portfolio leakage (`SLM-SCOPE-008`).
- [x] **Scoped Portfolio Status Filtering:** Status filtering (`?status=...`) operates exclusively over assigned customer portfolio (`SLM-SCOPE-009`).
- [x] **Pagination & Total Count Isolation:** Pagination metadata and `total` reflect strictly the salesman's assigned portfolio size (`SLM-SCOPE-010`).
- [x] **Query Parameter Tampering Immunity:** Salesman attempting to query `salesman_id=<other_id>` is safely ignored and scoped to self (`SLM-SCOPE-011`).
- [x] **Unassigned Filter Tampering Immunity:** Salesman attempting to query `salesman_id=UNASSIGNED` is safely ignored and scoped to self (`SLM-SCOPE-012`).
- [x] **Customer Create Form Denial:** Salesman accessing `/customers-create` rejected with 403 Forbidden (`SLM-SCOPE-013`).
- [x] **Customer Store Mutation Denial:** Salesman sending `POST /customers` rejected with 403 Forbidden (`SLM-SCOPE-014`).
- [x] **Customer Edit Form Denial:** Salesman accessing `/customers/{id}/edit` rejected with 403 Forbidden (`SLM-SCOPE-015`).
- [x] **Customer Update Mutation Denial:** Salesman sending `PUT /customers/{id}` rejected with 403 Forbidden (`SLM-SCOPE-016`).
- [x] **Customer Status Mutation Denial:** Salesman sending `PATCH /customers/{id}/status` rejected with 403 Forbidden (`SLM-SCOPE-017`).
- [x] **Customer Assignment Mutation Denial:** Salesman sending `PATCH /customers/{id}/assign` rejected with 403 Forbidden (`SLM-SCOPE-018`).
- [x] **Salesman Directory Prop Omission:** Inertia props `eligibleSalesmen` omitted / empty for Salesman role on index and show (`SLM-SCOPE-019`).
- [x] **Immediate Reassignment Access Revocation:** Reassigning customer from Salesman A to B immediately revokes A's access (403) and grants B access (`SLM-SCOPE-020`).
- [x] **Suspended Salesman Access Block:** Suspended salesman cannot access customer directory or customer profile (`SLM-SCOPE-021`).
- [x] **Disabled Salesman Access Block:** Disabled salesman cannot access customer directory or customer profile (`SLM-SCOPE-022`).
- [x] **Admin & Super Admin Unrestricted Access:** Admin and Super Admin retain full visibility and management across all customers (`SLM-SCOPE-023`).
- [x] **Accountant Read-Only Directory & Profile:** Accountant retains unrestricted read-only visibility but cannot mutate or assign (`SLM-SCOPE-024`).
- [x] **Nonexistent Customer 404 Protection:** Requesting nonexistent customer ID returns 404 Not Found (`SLM-SCOPE-025`).
- [x] **Empty Portfolio Clean Handling:** Salesman with zero assigned customers receives clean paginator (`total: 0`, `data: []`) (`SLM-SCOPE-026`).

### 1.5 Product & Category Management (`PRODUCT` & `CAT`)

### 1.5.1 Product Master CRUD (`PRODUCT` / `FEAT-PROD-001`)
- [x] **Super Admin & Admin Directory Access:** Super Admin and Admin can access product catalog and creation capabilities (`PROD-CRUD-001`).
- [x] **Salesman & Warehouse Manager Catalog Access:** Salesman and Warehouse Manager can view product catalog read-only with creation actions hidden (`PROD-CRUD-002`).
- [x] **Accountant & Delivery Partner Forbidden:** Roles without `product.view` (`ACCOUNTANT`, `DELIVERY_PARTNER`) rejected with 403 Forbidden (`PROD-CRUD-003`).
- [x] **Authorized Product Creation:** Admin creates master product with valid attributes and commercial pricing hierarchy (`PROD-CRUD-004`).
- [x] **SKU Uppercase Normalization & Trimming:** SKUs trimmed and normalized to uppercase on server (`PROD-CRUD-005`).
- [x] **Sequential SKU Auto-Generation:** Sequential SKU generated when SKU omitted (`PROD-00001`, `PROD-00002`...) (`PROD-CRUD-006`).
- [x] **Duplicate SKU Rejection:** Duplicate SKU rejected at validation layer and guarded by PostgreSQL unique index (`PROD-CRUD-007`).
- [x] **Pricing Hierarchy - Cost Price gte 0:** Negative cost price rejected with 422 Unprocessable Entity (`PROD-CRUD-008`).
- [x] **Pricing Hierarchy - Minimum Allowed Price gt 0:** Zero or negative minimum price rejected with 422 (`PROD-CRUD-009`).
- [x] **Pricing Hierarchy - Default Selling gte Minimum Allowed:** Selling price below minimum allowed rejected with 422 (`PROD-CRUD-010`).
- [x] **Pricing Hierarchy - MRP gte Default Selling:** MRP below default selling price rejected with 422 (`PROD-CRUD-011`).
- [x] **Pricing Hierarchy - Equal Boundaries Allowed:** Flat pricing boundary (`min = selling = mrp`) allowed (`PROD-CRUD-012`).
- [x] **Unauthorized Creation Rejection:** Salesman and Warehouse Manager cannot create products (403 Forbidden) (`PROD-CRUD-013`).
- [x] **Authoritative Cost Visibility for Admin:** Admin views product detail with authoritative `cost_price` (`PROD-CRUD-014`).
- [x] **Sensitive Cost Price Masking:** Salesman and Warehouse Manager receive masked (`null`) `cost_price` to prevent commercial leak (`PROD-CRUD-015`).
- [x] **Authorized General Metadata Update:** Admin updates product name, unit, description, and category classification (`PROD-CRUD-016`).
- [x] **Authorized Pricing Update:** Actor with `product.price.update` updates commercial pricing bounds (`PROD-CRUD-017`).
- [x] **Pricing Hierarchy Violation Rejection on Update:** Update violating $0 \le \text{cost}$, $0 < \text{min} \le \text{selling} \le \text{mrp}$ rejected with 422 (`PROD-CRUD-018`).
- [x] **Unauthorized Update Denial:** Salesman cannot update product attributes or pricing (403 Forbidden) (`PROD-CRUD-019`).
- [x] **Lifecycle Status Transition:** Admin transitions product between `ACTIVE` and `INACTIVE` with audit trail (`PROD-CRUD-020`).
- [x] **Lifecycle No-Op Suppression:** Redundant lifecycle status transition produces zero database writes and zero duplicate audits (`PROD-CRUD-021`).
- [x] **Future Order Readiness Contract - Active:** `Product::ensureCanOrder()` completes normally for active product (`PROD-CRUD-022`).
- [x] **Future Order Readiness Contract - Inactive:** `Product::ensureCanOrder()` throws `ValidationException` for inactive product (`PROD-CRUD-023`).
- [x] **Directory Search:** Case-insensitive search matches SKU, name, and description (`PROD-CRUD-024`).
- [x] **Directory Filter by Status & Category:** Filtering by lifecycle status (`ACTIVE`, `INACTIVE`) and category ID filters accurately (`PROD-CRUD-025`).
- [x] **Referential Integrity Safety:** Category deletion nulls `products.category_id` without deleting product record (`PROD-CRUD-026`).
- [x] **Physical Deletion Prohibited:** `ProductPolicy::delete()` returns `false` to preserve historical referential integrity (`PROD-CRUD-027`).
- [x] **Authoritative Audit Logging:** Structured audit events (`PRODUCT_CREATED`, `PRODUCT_UPDATED`, `PRODUCT_PRICING_UPDATED`, `PRODUCT_ACTIVATED`, `PRODUCT_DEACTIVATED`) logged with actor and product context (`PROD-CRUD-028`).

### 1.5.2 Product Image Upload & Storage (`PRODUCT` / `FEAT-PROD-002`)
- [x] **Super Admin Image Management:** Super Admin can upload, set primary, and delete product images (`PROD-IMG-001`).
- [x] **Admin Image Management:** Admin can upload, set primary, and delete product images (`PROD-IMG-002`).
- [x] **Salesman View Only:** Salesman can view product images but cannot upload or delete (403 Forbidden) (`PROD-IMG-003`).
- [x] **Warehouse Manager View Only:** Warehouse Manager can view product images but cannot mutate (403 Forbidden) (`PROD-IMG-004`).
- [x] **Unauthorized Roles & Guests Denial:** Accountant, Delivery Partner, and unauthenticated guests are rejected (`PROD-IMG-005`).
- [x] **Valid Formats Upload:** Valid JPEG, PNG, and WebP images upload successfully (`PROD-IMG-006`).
- [x] **Max File Size Limit:** Image larger than 5MB rejected with 422 (`PROD-IMG-007`).
- [x] **SVG Strict Prohibition:** SVG files strictly prohibited and rejected (`PROD-IMG-008`).
- [x] **Magic-Byte Spoofing Protection:** Malicious script / executable renamed to `.jpg` rejected by server magic-byte inspection (`PROD-IMG-009`).
- [x] **Corrupt Binary Rejection:** Corrupt or truncated binary rejected safely (`PROD-IMG-010`).
- [x] **Secure Object Key Generation:** Object key strictly follows `products/{product_id}/{uuid}.{ext}` without user path segments (`PROD-IMG-011`).
- [x] **No URL Persistence in DB:** Database stores only stable `object_key`, never presigned URLs (`PROD-IMG-012`).
- [x] **Dynamic Temporary Presigned URLs:** Temporary presigned URLs generated on-demand for authorized consumers (`PROD-IMG-013`).
- [x] **Client Filename Path Isolation:** Original client filename preserved as display metadata but excluded from storage path (`PROD-IMG-014`).
- [x] **Cross-Product IDOR Primary Set Denial:** Setting primary on image belonging to another product rejected with 404 (`PROD-IMG-015`).
- [x] **Cross-Product IDOR Delete Denial:** Deleting image belonging to another product rejected with 404 (`PROD-IMG-016`).
- [x] **Tampered Image ID 404 Protection:** Nonexistent image ID returns 404 Not Found (`PROD-IMG-017`).
- [x] **Auto-Primary First Image:** First uploaded image automatically designated as primary (`PROD-IMG-018`).
- [x] **Subsequent Upload Non-Primary:** Subsequent uploads default to non-primary (`PROD-IMG-019`).
- [x] **Set Primary Switch:** Setting primary unsets previous primary atomically (`PROD-IMG-020`).
- [x] **Delete Primary Auto-Promotion:** Deleting primary image promotes the next available image in gallery (`PROD-IMG-021`).
- [x] **Compensating S3 Deletion on DB Failure:** DB transaction failure triggers compensating S3 delete to prevent orphaned storage (`PROD-IMG-022`).
- [x] **Audit Logging without Secrets:** Structured audit logs emitted (`PRODUCT_IMAGE_UPLOADED`, `PRODUCT_IMAGE_SET_PRIMARY`, `PRODUCT_IMAGE_DELETED`) without credentials or presigned URLs (`PROD-IMG-023`).

### 1.5.3 Product Lifecycle Controls (`PRODUCT` / `FEAT-PROD-003`)
- [x] **Active Product Order Readiness:** `ACTIVE` product returns true for `canOrder()` and passes `Product::ensureCanOrder()` contract without exception (`PROD-LIFE-001`).
- [x] **Inactive Product Order Block:** `INACTIVE` product returns false for `canOrder()` and throws `ValidationException` on `Product::ensureCanOrder()` with descriptive SKU/name message (`PROD-LIFE-002`).
- [x] **Admin Deactivation Flow:** Active Administrator deactivates product from `ACTIVE` to `INACTIVE` with operational reason note (`PROD-LIFE-003`).
- [x] **Admin Reactivation Flow:** Active Administrator reactivates product from `INACTIVE` to `ACTIVE` with operational reason note (`PROD-LIFE-004`).
- [x] **Super Admin Lifecycle Control:** Super Admin can transition product lifecycle states (`ACTIVE` <-> `INACTIVE`) (`PROD-LIFE-005`).
- [x] **Salesman Status Mutation Denial:** Salesman attempting to update product status rejected with 403 Forbidden (`PROD-LIFE-006`).
- [x] **Warehouse Manager Status Mutation Denial:** Warehouse Manager attempting to update product status rejected with 403 Forbidden (`PROD-LIFE-007`).
- [x] **Accountant & Delivery Partner Denial:** Accountant and Delivery Partner rejected with 403 Forbidden on product lifecycle mutation (`PROD-LIFE-008`).
- [x] **Unauthenticated Guest Redirection:** Unauthenticated status update requests redirect to login (`PROD-LIFE-009`).
- [x] **Inactive / Suspended Actor Protection:** Inactive, suspended, or disabled administrator accounts blocked by middleware and rejected by service layer (`PROD-LIFE-010`).
- [x] **No-Op Status Transition Suppression:** Requesting transition to current status performs zero database writes and emits zero duplicate audit logs (`PROD-LIFE-011`).
- [x] **Invalid Status Payload Rejection:** Malformed status strings (`SUSPENDED`, `DELETED`, `ARCHIVED`, `DRAFT`) rejected with 422 Unprocessable Entity (`PROD-LIFE-012`).
- [x] **Reason Field Length Validation:** Optional operational reason capped at 500 characters; oversized strings rejected with 422 (`PROD-LIFE-013`).
- [x] **Audit Classification:** Structured audit events (`PRODUCT_ACTIVATED`, `PRODUCT_DEACTIVATED`) emitted with previous/new status, actor ID, SKU, and operational reason without secrets (`PROD-LIFE-014`).
- [x] **Image & S3 Object Preservation Invariant:** Deactivating product strictly preserves `product_images` database rows, primary status, and private S3 objects (`PROD-LIFE-015`).
- [x] **General Product Update Lifecycle Protection:** Updating status through general `PUT /products/{id}` endpoint enforces transition validation, locks row, and emits authoritative lifecycle audit events (`PROD-LIFE-016`).
- [x] **RULE-DOC-001 Invariant:** Product images are never exposed on formal invoices (`PROD-IMG-024`).

### 1.5.4 Product Category Management & Hierarchy (`CATEGORY` / `FEAT-CAT-001`)
- [x] **Root Category Creation:** Active Administrator creates root category (`parent_id = null`) with explicit uppercase code (`CAT-MGT-001`).
- [x] **Child Category Creation:** Active Administrator creates child category under valid parent taxonomy (`CAT-MGT-002`).
- [x] **Sequential Code Auto-Generation:** Category code auto-generated in sequential `CAT-00001` pattern when omitted (`CAT-MGT-003`).
- [x] **Duplicate Code Rejection:** Duplicate category code rejected by Form Request and PostgreSQL unique constraint (`CAT-MGT-004`).
- [x] **Sibling Name Uniqueness:** Sibling categories sharing same parent enforce case-insensitive unique names; same name under different parent allowed (`CAT-MGT-005`).
- [x] **Multilevel Hierarchy & Path Generation:** 3+ level hierarchy paths and ancestor chains resolved accurately server-side (`CAT-MGT-006`).
- [x] **Self-Parenting Cycle Prevention:** Category cannot set itself as its own parent (`parent_id = id`) (`CAT-MGT-007`).
- [x] **Descendant-Parent Cycle Prevention:** Category cannot set any existing descendant as parent, preventing taxonomic cycles (`CAT-MGT-008`).
- [x] **Safe Subtree Reparenting:** Reparenting category moves entire subtree atomically while preserving child hierarchy (`CAT-MGT-009`).
- [x] **Deterministic Sibling Sort Order:** Sibling ordering resolved deterministically by `sort_order ASC` then `name ASC` (`CAT-MGT-010`).
- [x] **Category Lifecycle Transitions:** Category transitions between `ACTIVE` and `INACTIVE` with audit logging (`CAT-MGT-011`).
- [x] **Lifecycle No-Op Suppression:** Redundant lifecycle update produces zero database writes and zero audit logs (`CAT-MGT-012`).
- [x] **Product Assignment Guard - Creation:** Product creation with `INACTIVE` category rejected with 422 (`CAT-MGT-013`).
- [x] **Product Assignment Guard - Update:** Changing product category to `INACTIVE` category rejected with 422 (`CAT-MGT-014`).
- [x] **Existing Product Preservation on Category Deactivation:** Deactivating category preserves existing `products.category_id`, prices, status, and images without modification (`CAT-MGT-015`).
- [x] **Unchanged Inactive Category Preservation:** Product update preserves existing inactive category assignment when category is unchanged (`CAT-MGT-016`).
- [x] **Deletion Block with Attached Products:** Attempting to delete category with attached products rejected with 422 (`CAT-MGT-017`).
- [x] **Deletion Block with Subcategories:** Attempting to delete category with child subcategories rejected with 422 (`CAT-MGT-018`).
- [x] **Empty Leaf Category Deletion:** Empty leaf category permanently deleted with structured audit logging (`CAT-MGT-019`).
- [x] **Super Admin & Admin Full CRUD Access:** Privileged administrators possess complete view, create, edit, update, status, and delete authority (`CAT-MGT-020`).
- [x] **Salesman & Warehouse Manager Read-Only:** Read-only access to directory and detail views; mutation endpoints rejected with 403 Forbidden (`CAT-MGT-021`).
- [x] **Accountant & Delivery Partner Denied:** Roles without `product.view` denied all category access with 403 Forbidden (`CAT-MGT-022`).
- [x] **Unauthenticated Guest Redirection:** Unauthenticated requests redirect to login (`CAT-MGT-023`).
- [x] **Inactive / Suspended Actor Protection:** Inactive administrative accounts blocked from category operations (`CAT-MGT-024`).
- [x] **Directory Search:** Search by code, name, and description filters category records accurately (`CAT-MGT-025`).
- [x] **Status & Root Filters:** Category status filter and root-only toggle apply accurately (`CAT-MGT-026`).

### 1.6 Pricing Engine (`PRICING`)
- [x] **Authoritative Boundary Enforcement:** Canonical hierarchy $0 \le \text{cost\_price}$ and $0 < \text{minimum\_allowed\_price} \le \text{default\_selling\_price} \le \text{mrp}$ enforced with BCMath arbitrary-precision string math (`PRICE-BND-001`).
- [x] **Boundary Equalities:** Boundary equality permitted ($\text{min} = \text{default} = \text{mrp}$) for fixed-price products (`PRICE-BND-002`).
- [x] **Decimal Precision & Format:** Numbers with $>2$ decimal places, scientific notation, or non-numeric strings rejected with 422 (`PRICE-BND-003`).
- [x] **Resulting-State Validation:** Product updates validate complete merged resulting state against locked rows (`PRICE-BND-004`).
- [x] **Reusable Order Unit Price Validation:** `PriceBoundaryService::validateOrderUnitPrice()` verifies $\text{min} \le \text{unit\_price} \le \text{mrp}$ (`PRICE-BND-005`).
- [x] **Pricing Update Permission:** Price modifications protected by `product.price.update` (`PRICE-BND-006`).
- [x] **Audit Logging:** Emits `PRODUCT_PRICING_UPDATED` only upon actual price change and successful transaction (`PRICE-BND-007`).
- [x] **Database CHECK Backstop:** PostgreSQL `products_pricing_hierarchy_check` constraint verified (`PRICE-BND-008`).
- [x] **Security (Override):** Price override requires `pricing.override` permission and mandatory documented reason (`FEAT-PRICE-002`).
- [ ] **Database / Snapshot:** Order item permanently stores actual transaction price; product master edits do not alter historical orders (`EDGE-007`).

### 1.7 Product-Specific Tax (`TAX` / `FEAT-TAX-001`)
- [x] **Authoritative Calculation:** Line-item tax calculated authoritatively using assigned product tax profile rate (`RULE-TAX-001`, `TaxCalculationServiceTest::test_standard_tax_calculation`).
- [x] **Exact BCMath & Zero Floats:** Calculations use exact BCMath decimal strings with zero floating-point arithmetic (`TaxCalculationServiceTest::test_precision_and_no_float_corruption`).
- [x] **ROUND_HALF_UP Determinism:** Deterministic line-level `ROUND_HALF_UP` rounding tested for half-cent boundaries (`1.234 -> 1.23`, `1.235 -> 1.24`, `1.236 -> 1.24`, `0.005 -> 0.01`, `0.004 -> 0.00`) (`TaxCalculationServiceTest::test_deterministic_round_half_up_boundaries`).
- [x] **Header Aggregation Invariant:** Header tax total is strictly $\sum \text{line\_tax\_amount}$ (sum of rounded line taxes), never round of sums (`TaxCalculationServiceTest::test_order_header_tax_aggregation_sums_rounded_line_taxes`).
- [x] **Zero-Rate vs Tax-Exempt Identity:** Zero-rate and tax-exempt profiles calculate zero tax while preserving semantic profile identity (`TaxCalculationServiceTest::test_zero_tax_and_exempt_profiles`).
- [x] **Immutable Tax Snapshot Contract:** `TaxSnapshotData` captures immutable decimal strings at calculation time; subsequent profile edits or deactivations do not mutate snapshot values (`TaxCalculationServiceTest::test_tax_snapshot_dto_immutability`).
- [x] **Tax Profile Lifecycle & Active Assignment:** Active profiles assignable to products; inactive profiles rejected for new assignments while existing assignments are non-destructively preserved (`ProductTaxIntegrationTest::test_admin_can_assign_active_tax_profile_to_product`, `test_creating_product_with_inactive_tax_profile_is_rejected`).
- [x] **Deletion Guard & ON DELETE RESTRICT:** Profile referenced by products cannot be deleted; database FK constraint and service guard enforce protection (`TaxProfileManagementTest::test_deleting_tax_profile_referenced_by_product_is_blocked`).
- [x] **RBAC Protection:** Tax profile management and product tax assignment strictly guarded by `Permission::PRODUCT_TAX_UPDATE` (`TaxProfileManagementTest::test_unauthorized_roles_cannot_manage_tax_profiles`).
- [x] **Structured Audit Logging:** Emits `TAX_PROFILE_CREATED`, `TAX_PROFILE_UPDATED`, `TAX_PROFILE_STATUS_CHANGED`, `TAX_PROFILE_DELETED`, and `PRODUCT_TAX_PROFILE_CHANGED` without secrets (`TaxProfileManagementTest::test_tax_profile_lifecycle_emits_audit_events`, `ProductTaxIntegrationTest::test_admin_can_update_product_tax_profile`).
- [x] **Future Order Persistence Note:** Order-level persistence deferred to Phase 03/05 Order engine; contract DTOs `TaxSnapshotData` and `TaxCalculationResult` verified ready.

### 1.8 Order Creation & Submission (`ORDER` / `FEAT-ORD-001`)
- [x] **Happy Path Creation:** Salesman creates order for assigned active customer with multiple products, valid pricing, and default pricing (`ORD-CREATE-001`..`003`).
- [x] **Boundary Pricing Enforcement:** Exact minimum allowed price and exact MRP accepted; prices below minimum or above MRP rejected with 422 (`ORD-CREATE-004`..`007`).
- [x] **Salesman Self-Override Prevention:** Salesman attempting to force price override parameters (`is_price_overridden`, `price_override_reason`) is rejected (`ORD-CREATE-008`).
- [x] **Malformed Price Prevention:** Negative prices, scientific notation, and excessive decimals rejected with 422 (`ORD-CREATE-009`..`011`).
- [x] **Quantity Constraints:** Positive integers (1 to 999,999) accepted; zero, negative, fractional, and excessive quantities rejected (`ORD-CREATE-012`..`015`).
- [x] **Customer Lifecycle & Scope Guards:** Inactive/on-hold customers rejected (`422 Unprocessable Entity`); unassigned customers rejected (`403 Forbidden`) (`ORD-CREATE-016`..`017`).
- [x] **Product Lifecycle & Deactivation:** Inactive products rejected at checkout; stale cart deactivation caught and aborted atomically (`ORD-CREATE-018`..`019`).
- [x] **Tax Rounding & Inactive Profile Invariant:** Product-specific line taxes rounded with `ROUND_HALF_UP` and aggregated; products with retained inactive tax profiles orderable with established tax rate (`ORD-CREATE-020`..`021`).
- [x] **Historical Snapshot Immutability:** Historical product name, SKU, unit, price, and tax snapshots preserved permanently after subsequent product/tax updates (`ORD-CREATE-022`).
- [x] **Deterministic Idempotency & Conflict Safety:** Same idempotency key + identical payload returns existing order; same key + different payload returns 409 Conflict (`ORD-CREATE-023`).
- [x] **Security (Zero Client Trust):** Client-supplied order numbers, salesman IDs, status, and calculated totals completely ignored and derived server-side (`RULE-SEC-002`).
- [x] **Sequential Order Numbering:** PostgreSQL sequence `order_number_seq` generates unique `ORD-YYYY-XXXXXX` numbers safely across concurrent orders (`RULE-ORD-001`).
- [x] **Structured Audit Logging:** Emits `ORDER_CREATED` event on initial commit and suppresses duplicate audit on idempotent replay (`ORD-CREATE-023`).
- [x] **Responsive UI & UX States:** Flagship 3-step Salesman Order Builder (`Create.tsx`, `Show.tsx`) supports Desktop (1024-1920px), Tablet (768-1023px), and Mobile (320-430px) with custom ephemeral cart and LocalStorage draft persistence.

### 1.8.1 Draft Order Persistence & Resumption (`ORDER DRAFTS` / `FEAT-ORD-002`)
- [x] **Draft Creation & Lifecycle State:** Salesman saves new draft order with `status = DRAFT`, nullable `order_number`, auto-generated UUID `draft_token`, and initial `version = 1` (`ORD-DRAFT-001`).
- [x] **Draft Line Mutation & Synchronization:** Draft items, quantities, and prices update atomically; preview subtotal, taxes, and totals recalculate; `version` increments strictly by 1 (`ORD-DRAFT-002`).
- [x] **Customer Reassignment on Draft:** Salesman can switch selected assigned customer on draft prior to final submission (`ORD-DRAFT-003`).
- [x] **Optimistic Version Locking & Concurrency Conflict (409):** Stale updates with mismatched `expected_version` rejected with HTTP 409 Conflict to prevent overwrite races across tabs/devices (`ORD-DRAFT-004`).
- [x] **Draft Scoping & IDOR Protection:** Salesman can only list, view, update, submit, and discard their own drafts; cross-salesman access blocked with 403 Forbidden (`ORD-DRAFT-005`..`007`).
- [x] **Draft List Filtering & Pagination:** `GET /salesman/orders/drafts` filters by customer code/name, displays item counts, estimated totals, and sorts newest updated first (`ORD-DRAFT-008`).
- [x] **Draft Resumption & Edit State:** `GET /salesman/orders/drafts/{order}/edit` populates Order Builder with draft data, customer info, item details, and product catalog (`ORD-DRAFT-009`).
- [x] **Stale Master Data Warnings:** Inactive/on-hold customer and inactive product warnings displayed during resumption without destroying draft state (`ORD-DRAFT-010`).
- [x] **Draft Discard Lifecycle:** Salesman can permanently delete draft and associated items; emits `ORDER_DRAFT_DISCARDED` audit log (`ORD-DRAFT-011`).
- [x] **Submitted Order Protection:** Discard and draft update endpoints reject submitted orders with 404/403/redirect; submitted orders cannot be modified via draft API (`ORD-DRAFT-012`).
- [x] **Draft Final Submission Transition:** Salesman submits draft order; authoritative price boundary validation, lock-for-update product re-reading, and exact line-tax recalculation occur atomically (`ORD-DRAFT-013`).
- [x] **Formal Sequence Assignment on Submission:** Formal `ORD-YYYY-XXXXXX` sequence assigned only at final submission; status transitions `DRAFT -> SUBMITTED` (`ORD-DRAFT-014`).
- [x] **Audit Event Tracing:** Emits `ORDER_DRAFT_SAVED`, `ORDER_DRAFT_DISCARDED`, and `ORDER_CREATED` with `was_draft = true` without credentials or sensitive data (`ORD-DRAFT-015`).
- [x] **Regression Protection (FEAT-ORD-001):** Direct order creation from FEAT-ORD-001 continues operating flawlessly without regressions (`ORD-DRAFT-016`).

### 1.8.2 Order Line Quantity Stepper & Validation Controls (`QUANTITY STEPPER` / `FEAT-ORD-003`)
- [x] **Minimum Boundary Acceptance (Qty = 1):** Positive integer quantity of 1 accepted at minimum boundary (`ORD-QTY-001`).
- [x] **Maximum Boundary Acceptance (Qty = 999,999):** Positive integer quantity of 999,999 accepted at maximum boundary (`ORD-QTY-002`).
- [x] **Zero Quantity Rejection:** Quantity of 0 rejected with 422 Unprocessable Entity (`ORD-QTY-003`).
- [x] **Negative Quantity Rejection:** Negative quantities rejected with 422 Unprocessable Entity (`ORD-QTY-004`).
- [x] **Fractional / Decimal Rejection:** Fractional and decimal quantities rejected with 422 Unprocessable Entity (`ORD-QTY-005`).
- [x] **Oversized Quantity Rejection (>999,999):** Quantities exceeding 999,999 rejected with 422 Unprocessable Entity (`ORD-QTY-006`).
- [x] **Non-Numeric Quantity Rejection:** String / non-numeric input rejected with 422 Unprocessable Entity (`ORD-QTY-007`).
- [x] **Draft Quantity Update & Version Increment:** Updating draft item quantity recalculates line tax, taxable amount, and preview totals accurately, incrementing `version` by 1 (`ORD-QTY-008`).
- [x] **Draft Update Validation Guard:** Updating draft with invalid quantity rejected with 422 (`ORD-QTY-009`).
- [x] **Resumed Draft Submission Recalculation:** Resumed draft with modified quantities commits with exact authoritative totals and sequence number (`ORD-QTY-010`).
- [x] **Unified Frontend Stepper (`QuantityStepper.tsx`):** Reusable segmented control with boundary disable states (`[-]` disabled at 1, `[+]` disabled at 999,999), direct numeric input buffer, Enter/blur normalization, $\ge 44\text{px}$ touch targets, and full ARIA semantics integrated across catalog card, cart drawer, and review table (`ORD-QTY-011`).

### 1.8.3 Order Review, Line Tax Breakdown & Financial Summary (`ORDER REVIEW` / `FEAT-ORD-004`)
- [x] **Multi-Line Mixed Tax Breakdown:** Orders with standard (8.25%), reduced (4.00%), and exempt (0.00%) lines calculate line taxable amounts, taxes, and totals authoritatively (`ORD-REV-001`).
- [x] **Header Tax Sum of Lines Invariant:** Order tax total is strictly the sum of rounded line taxes ($\sum \text{line\_tax\_amount}$), eliminating 1-cent invoice rounding discrepancies (`ORD-REV-002`).
- [x] **Tax Rounding Boundary Verification:** Deterministic line-level `ROUND_HALF_UP` boundary assertions match BCMath string arithmetic (`14.95 * 0.0825 = 1.233375 -> 1.23`, `15.00 * 0.0825 = 1.2375 -> 1.24`) (`ORD-REV-003`).
- [x] **Master Data Drift Snapshot Immutability:** Renaming products, altering catalog prices, or changing tax profile rates after order submission does not mutate committed order item snapshots (`ORD-REV-004`).
- [x] **Draft Preview vs Submission Parity:** Draft order review preview matches final committed transaction values to the exact cent (`ORD-REV-005`).
- [x] **Zero / Exempt Tax Profiles:** Exempt products ($0.00\%$) calculate $\$0.00$ tax while preserving snapshot tax profile code and rate identity (`ORD-REV-006`).
- [x] **Quantity Mutation Financial Recalculation:** Modifying line quantities dynamically recalculates taxable amounts, taxes, and grand totals deterministically (`ORD-REV-007`).
- [x] **Cost Price Invisibility & Security:** Salesman view strictly omits `cost_price` on order creation, draft editing, and show views (`ORD-REV-008`).
- [x] **Customer Scoping & Boundary Validation:** Salesmen cannot submit orders or review drafts for unassigned or inactive customers (`ORD-REV-009`).
- [x] **Price Boundary Validation:** Selling prices below minimum allowed price or above MRP are rejected with 422 Unprocessable Entity (`ORD-REV-010`).
- [x] **Client-Side Financial Preview Utilities (`financial.ts`):** Centralized financial calculation helpers (`formatCurrency`, `calculateLinePreview`, `calculateOrderPreview`) provide non-authoritative preview parity with zero binary-float authority (`ORD-REV-011`).
- [x] **Responsive Review UI (`OrderReviewStep.tsx` & `OrderReviewLineCard.tsx`):** High-density 7-column B2B ERP table for Desktop/Tablet ($\ge 640\text{px}$) and dedicated touch cards with $\ge 44\text{px}$ targets for Mobile ($< 640\text{px}$, 320–430px) without horizontal scrolling (`ORD-REV-012`).

### 1.8.4 Order Submission Idempotency Enforcement (`IDEMPOTENCY` / `FEAT-ORD-005`)
- [x] **Direct Creation Exact Replay:** Submitting duplicate order request with identical idempotency key and payload returns existing order and redirects to show route without duplicate database records (`ORD-IDEMP-001`).
- [x] **Direct Creation Changed Customer Conflict (409):** Replaying same idempotency key with different customer ID rejected with HTTP 409 Conflict without modifying existing order (`ORD-IDEMP-002`).
- [x] **Direct Creation Changed Quantity Conflict (409):** Replaying same idempotency key with modified item quantity rejected with HTTP 409 Conflict (`ORD-IDEMP-003`).
- [x] **Direct Creation Changed Unit Price Conflict (409):** Replaying same idempotency key with modified unit price rejected with HTTP 409 Conflict (`ORD-IDEMP-004`).
- [x] **Direct Creation Changed Notes Conflict (409):** Replaying same idempotency key with modified notes rejected with HTTP 409 Conflict (`ORD-IDEMP-005`).
- [x] **Concurrent Unique Constraint Collision Race Recovery:** Simultaneous race where request bypasses initial select and hits PostgreSQL `UNIQUE(idempotency_key)` constraint rolls back transaction, performs fresh committed read, verifies fingerprint, and returns winning order cleanly without 500 database error (`ORD-IDEMP-006`).
- [x] **Concurrent Collision with Conflicting Payload:** Simultaneous race colliding on unique constraint with conflicting payload throws HTTP 409 Conflict after clean rollback (`ORD-IDEMP-007`).
- [x] **Draft Double Submission Replay:** Rapid double submission of draft order returns already-submitted order safely without duplicate order numbers or duplicate items (`ORD-IDEMP-008`).
- [x] **Draft Submit Key Collision with Existing Order:** Submitting draft with an idempotency key already belonging to another committed order returns HTTP 409 Conflict (`ORD-IDEMP-009`).
- [x] **Cross-Salesman Key Theft / IDOR Protection (403):** Salesman attempting to replay or submit with an idempotency key belonging to another salesman is blocked with HTTP 403 Forbidden without leaking order details (`ORD-IDEMP-010`).
- [x] **Missing Idempotency Key Validation (422):** Request without `idempotency_key` rejected with 422 Unprocessable Entity (`ORD-IDEMP-011`).
- [x] **Oversized Idempotency Key Validation (422):** Idempotency key exceeding 64 characters rejected with 422 Unprocessable Entity (`ORD-IDEMP-012`).
- [x] **Audit Log Uniqueness Contract:** Exactly one `ORDER_CREATED` audit event emitted on initial transaction commit; subsequent idempotent replays emit `ORDER_IDEMPOTENT_REPLAY` and zero duplicate `ORDER_CREATED` events (`ORD-IDEMP-013`).
- [x] **Master Data Price Drift Invariant:** Replaying original order intent after catalog product default price update returns historical committed snapshot without recalculation or 409 mismatch (`ORD-IDEMP-014`).
- [x] **Advisory Cache Lock Fallback:** Failure, timeout, or absence of Redis advisory cache lock gracefully falls back directly to PostgreSQL correctness authority (`ORD-IDEMP-015`).
- [x] **Authoritative Constraint Collision Handler:** Direct `UniqueConstraintViolationException` (SQLSTATE `23505`) caught, rolled back, and recovered into winner order (`ORD-IDEMP-016`).
- [x] **Sequential Order Number Stability:** Idempotent replays preserve existing `order_number` without advancing or consuming PostgreSQL `order_number_seq` (`ORD-IDEMP-017`).

### 1.8.5 Salesman Order History & Multi-State Timeline (`ORDER HISTORY` / `FEAT-ORD-006`)
- [x] **Scoped Salesman History Listing:** Salesman lists exclusively their own submitted orders; other salesmen's orders strictly excluded (`ORD-HIST-001`).
- [x] **Direct Single-Resource IDOR Protection (403):** Salesman attempting direct URL access to another salesman's order rejected with HTTP 403 Forbidden without metadata leakage (`ORD-HIST-002`).
- [x] **Admin Global History Visibility:** Admin and Super Admin retain full visibility across all orders from all salesmen (`ORD-HIST-003`).
- [x] **Unauthenticated Redirection to Login:** Unauthenticated requests to `/salesman/orders` redirect to login (`ORD-HIST-004`).
- [x] **Draft Order Segregation Invariant:** History list strictly excludes `status = DRAFT` orders, preserving draft isolation in `/salesman/orders/drafts` (`ORD-HIST-005`).
- [x] **Search by Order Number:** Case-insensitive search on `order_number` returns exact and partial matches (`ORD-HIST-006`).
- [x] **Search by Customer Name:** Case-insensitive search on customer `name` filters order records accurately (`ORD-HIST-007`).
- [x] **Search by Customer Code:** Search on customer `code` filters order records accurately (`ORD-HIST-008`).
- [x] **Filter by Order Status:** Status filter (`status=APPROVED`, `status=SUBMITTED`, etc.) returns matching subsets (`ORD-HIST-009`).
- [x] **Filter by Fulfillment Status:** Fulfillment filter (`fulfillment_status=DELIVERED`, `fulfillment_status=UNALLOCATED`) returns matching subsets (`ORD-HIST-010`).
- [x] **Filter by Payment Status:** Payment filter (`payment_status=PAID`, `payment_status=UNPAID`) returns matching subsets (`ORD-HIST-011`).
- [x] **Filter by Delivery Status:** Delivery filter (`delivery_status=OUT_FOR_DELIVERY`, `delivery_status=PENDING_ASSIGNMENT`) returns matching subsets (`ORD-HIST-012`).
- [x] **Filter by Date Range:** Date filters (`date_from` and `date_to`) filter accurately on `submitted_at` timestamps (`ORD-HIST-013`).
- [x] **Deterministic Reverse-Chronological Sort:** Default sort orders records newest `submitted_at` first, with deterministic `id` DESC tiebreaker (`ORD-HIST-014`).
- [x] **Bounded Pagination (15 Per Page):** Server-side pagination caps page size at 15 items and preserves filter query parameters (`ORD-HIST-015`).
- [x] **Authentic Milestone Timeline Generation:** Order detail view returns verified milestones (`created`, `submitted`, `approved`, `cancelled`, `completed`) with exact persisted timestamps and actor names without fabricated transition timestamps (`ORD-HIST-016`).
- [x] **Approved Order Milestone Tracing:** Approved order timeline includes `approved_at` timestamp and approver name (`ORD-HIST-017`).
- [x] **Cancelled Order Reason Tracing:** Cancelled/rejected order timeline includes `cancelled_at` timestamp, canceller name, and mandatory cancellation reason (`ORD-HIST-018`).
- [x] **Customer Reassignment Historical Ownership:** Reassigning a customer to a new salesman preserves the original salesman's historical order ownership and visibility (`ORD-HIST-019`).
- [x] **Zero Cost Price Leakage:** Order history list and detail view payloads strictly omit `cost_price` (`ORD-HIST-020`).
- [x] **Invalid Filter Rejection (422):** Malformed status filter values rejected with 422 validation error (`ORD-HIST-021`).
- [x] **Bounded Query Execution (No N+1):** History list query executes in bounded database queries using selective column eager loading and `withCount('items')` (`ORD-HIST-022`).

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
