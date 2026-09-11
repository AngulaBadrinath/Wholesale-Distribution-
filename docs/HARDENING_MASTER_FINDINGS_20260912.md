# Master Security Hardening & Technical Debt Findings Matrix

**Document Date:** September 12, 2026  
**Application:** Unique Distributors (Wholesale Distribution Management System)  
**Status:** IMPLEMENTED & VERIFIED  
**Baseline Git Commit:** `3a56e93`  

---

## Executive Summary of Findings

| Severity | Count | Primary Focus Areas | Status |
|---|---|---|---|
| **P0 / Critical** | 0 | No active remote code execution, unauthenticated data destruction, or credential leaks found | N/A |
| **P1 / High** | 3 | Route-level permission defense-in-depth (`SEC-001`), route-level rate limiting (`SEC-002`), MFA session throttle key scoping (`SEC-003`) | **3 / 3 Implemented & Verified** |
| **P2 / Medium** | 4 | HTTP security headers & CSP (`SEC-004`), internal UI identifier removal (`UI-001`), Unique Distributors branding unification (`UI-002`), scaffold `/foundation` removal (`DEAD-001`) | **4 / 4 Implemented & Verified** |
| **P3 / Low / Tech Debt** | 1 | Redundant duplicate create route normalization (`DEAD-002`) | **1 / 1 Implemented & Verified** |
| **Total** | **8** | **All 8 confirmed detailed audit findings fully resolved** | **100% Resolved** |

---

## Detailed Findings Matrix

### Finding SEC-001: Missing Route-Level Permission Middleware on Admin Credit & Refund Endpoints
- **ID:** `SEC-001`
- **Category:** Authorization / Defense-in-Depth
- **Severity:** `P1 / High`
- **Confidence:** `Confirmed`
- **Status:** `IMPLEMENTED & VERIFIED`
- **Affected File(s):** [`routes/web.php`](file:///f:/Wholesale%20Distribution%20Management%20System/routes/web.php), [`app/Enums/Permission.php`](file:///f:/Wholesale%20Distribution%20Management%20System/app/Enums/Permission.php), [`app/Services/Auth/PermissionService.php`](file:///f:/Wholesale%20Distribution%20Management%20System/app/Services/Auth/PermissionService.php), [`app/Policies/CreditNotePolicy.php`](file:///f:/Wholesale%20Distribution%20Management%20System/app/Policies/CreditNotePolicy.php), [`app/Policies/RefundRequestPolicy.php`](file:///f:/Wholesale%20Distribution%20Management%20System/app/Policies/RefundRequestPolicy.php)
- **Route / Component:** `/admin/credits`, `/admin/credits/{id}`, `/admin/returns/{returnRequest}/credit-eligibility`, `/admin/refunds`, `/admin/refunds/{id}`
- **Observed:** In `routes/web.php`, the routes `GET /admin/credits`, `GET /admin/credits/{id}`, `GET /admin/refunds`, and `GET /admin/refunds/{id}` were placed outside of any `Route::middleware('permission:...')` group, relying exclusively on controller-level `Gate::authorize` calls.
- **Expected:** In alignment with `RULE-SEC-001` (Security Defense-in-Depth), all sensitive administrative financial sub-ledger endpoints must be protected at both the route middleware layer (`permission:credit.view`, `permission:refund.view`) and the controller authorization layer.
- **Implementation:** Added `CREDIT_VIEW = 'credit.view'` and `REFUND_VIEW = 'refund.view'` enum cases to `Permission.php`, assigned them to `ADMIN` and `ACCOUNTANT` roles in `PermissionService.php`, updated policies, and wrapped the routes in `routes/web.php` with `permission:credit.view` and `permission:refund.view` middleware.
- **Verification:** Automated tests verify fail-closed 403 Forbidden responses for unauthorized roles (`SALESMAN`, `DELIVERY_PARTNER`, `WAREHOUSE_MANAGER`) while preserving 200 OK for `ADMIN` and `ACCOUNTANT`.
- **Classification:** `FIX`

---

### Finding SEC-002: Missing Route-Level Rate Limiting on Financial & Order Mutation Endpoints
- **ID:** `SEC-002`
- **Category:** Rate Limiting / Abuse Prevention
- **Severity:** `P1 / High`
- **Confidence:** `Confirmed`
- **Status:** `IMPLEMENTED & VERIFIED`
- **Affected File(s):** [`app/Providers/AppServiceProvider.php`](file:///f:/Wholesale%20Distribution%20Management%20System/app/Providers/AppServiceProvider.php), [`routes/web.php`](file:///f:/Wholesale%20Distribution%20Management%20System/routes/web.php)
- **Route / Component:** `/salesman/orders`, `/admin/payments/*`, `/admin/inventory-adjustments`, `/orders/{order}/adjustments`, `/invoices/{invoice}/pdf`, `/admin/reports/*`, `/delivery/*`
- **Observed:** Write endpoints (payment recording, order submission, inventory adjustments, PDF downloads) lacked route-level `throttle:` middleware.
- **Expected:** High-impact business endpoints must enforce defined request rate boundaries to prevent burst abuse, automated script loops, or resource exhaustion.
- **Implementation:** Registered 10 named domain rate limiters in `AppServiceProvider.php`:
  - `login`: 5/min (email|IP)
  - `mfa`: 5/min (user_id|IP)
  - `password-reset`: 3/10min (email|IP)
  - `orders`: 30/min per user (mutation routes)
  - `payments`: 20/min per user
  - `payment-verification`: 30/min per user
  - `inventory`: 20/min per user
  - `deliveries`: 15/min per driver
  - `invoice-pdf`: 15/min per user
  - `reports`: 30/min per user
- **Verification:** Feature tests in `SecurityHardeningAndDeadCodeCleanupTest.php` assert 429 Too Many Requests status upon exceeding threshold while preserving normal human browsing.
- **Classification:** `FIX`

---

### Finding SEC-003: IP-Only MFA Challenge Throttle Key Collision Risk
- **ID:** `SEC-003`
- **Category:** Authentication / Multi-Factor Hardening
- **Severity:** `P1 / High`
- **Confidence:** `Confirmed`
- **Status:** `IMPLEMENTED & VERIFIED`
- **Affected File(s):** [`app/Http/Requests/Auth/MfaChallengeRequest.php`](file:///f:/Wholesale%20Distribution%20Management%20System/app/Http/Requests/Auth/MfaChallengeRequest.php)
- **Route / Component:** `POST /login/mfa`
- **Observed:** `MfaChallengeRequest::throttleKey()` generated the throttle key solely from client IP: `'mfa-challenge:' . Str::transliterate($this->ip())`.
- **Expected:** The throttle key must bind both the user identity in session and the client IP to prevent shared-IP NAT collisions.
- **Implementation:** Modified `MfaChallengeRequest::throttleKey()` to return `'mfa-challenge:' . ($this->session()->get('mfa.challenge.user_id') ?? 'anon') . '|' . Str::transliterate($this->ip())`.
- **Verification:** Feature tests confirm User A failed attempts lock User A only, while User B on the same IP address remains unaffected.
- **Classification:** `FIX`

---

### Finding UI-001: Internal Ticket & Implementation Identifiers Visible in Production UI
- **ID:** `UI-001`
- **Category:** User Experience / Information Hygiene
- **Severity:** `P2 / Medium`
- **Confidence:** `Confirmed`
- **Status:** `IMPLEMENTED & VERIFIED`
- **Affected File(s):**
  - [`resources/js/Pages/Auth/Login.tsx`](file:///f:/Wholesale%20Distribution%20Management%20System/resources/js/Pages/Auth/Login.tsx)
  - [`resources/js/Pages/TaxProfile/Index.tsx`](file:///f:/Wholesale%20Distribution%20Management%20System/resources/js/Pages/TaxProfile/Index.tsx)
  - [`resources/js/Pages/Product/Create.tsx`](file:///f:/Wholesale%20Distribution%20Management%20System/resources/js/Pages/Product/Create.tsx)
  - [`resources/js/Pages/Admin/Reporting/*.tsx`](file:///f:/Wholesale%20Distribution%20Management%20System/resources/js/Pages/Admin/Reporting/)
  - [`resources/js/Pages/Admin/Orders/Partials/ReviewActionHeader.tsx`](file:///f:/Wholesale%20Distribution%20Management%20System/resources/js/Pages/Admin/Orders/Partials/ReviewActionHeader.tsx)
  - [`resources/js/Pages/Admin/Adjustments/Review.tsx`](file:///f:/Wholesale%20Distribution%20Management%20System/resources/js/Pages/Admin/Adjustments/Review.tsx)
  - [`resources/js/Pages/Admin/Adjustments/Partials/ApproveAdjustmentModal.tsx`](file:///f:/Wholesale%20Distribution%20Management%20System/resources/js/Pages/Admin/Adjustments/Partials/ApproveAdjustmentModal.tsx)
- **Route / Component:** Public and authenticated workspace UI views
- **Observed:** Internal development identifiers (`AUTH-001`, `FEAT-TAX-001`, `FEAT-PRD-001`, `FEAT-REP-001`..`006`, etc.) were visible in UI cards, headers, and modal dialogs.
- **Expected:** Production user interfaces must never expose internal phase numbers, ticket IDs, or sprint tags to end-users or clients.
- **Implementation:** Removed all hardcoded internal ticket badges and labels from React view templates while retaining legitimate documentation, tests, and commit records.
- **Verification:** Playwright browser audit verified 0 internal ticket strings across desktop and mobile views.
- **Classification:** `SAFE CLEANUP`

---

### Finding UI-002: Stale Default Application Branding Across Auth & Shell Views
- **ID:** `UI-002`
- **Category:** Client Branding / Application Identity
- **Severity:** `P2 / Medium`
- **Confidence:** `Confirmed`
- **Status:** `IMPLEMENTED & VERIFIED`
- **Affected File(s):**
  - [`config/app_identity.php`](file:///f:/Wholesale%20Distribution%20Management%20System/config/app_identity.php)
  - [`app/Services/System/ApplicationIdentityService.php`](file:///f:/Wholesale%20Distribution%20Management%20System/app/Services/System/ApplicationIdentityService.php)
  - [`config/app.php`](file:///f:/Wholesale%20Distribution%20Management%20System/config/app.php)
  - [`.env.example`](file:///f:/Wholesale%20Distribution%20Management%20System/.env.example)
  - [`phpunit.xml`](file:///f:/Wholesale%20Distribution%20Management%20System/phpunit.xml)
  - [`resources/js/app.tsx`](file:///f:/Wholesale%20Distribution%20Management%20System/resources/js/app.tsx)
  - [`resources/js/Layouts/AppLayout.tsx`](file:///f:/Wholesale%20Distribution%20Management%20System/resources/js/Layouts/AppLayout.tsx)
  - [`resources/js/Pages/Auth/Login.tsx`](file:///f:/Wholesale%20Distribution%20Management%20System/resources/js/Pages/Auth/Login.tsx)
  - [`resources/js/Pages/Auth/ForgotPassword.tsx`](file:///f:/Wholesale%20Distribution%20Management%20System/resources/js/Pages/Auth/ForgotPassword.tsx)
  - [`resources/js/Pages/Auth/ResetPassword.tsx`](file:///f:/Wholesale%20Distribution%20Management%20System/resources/js/Pages/Auth/ResetPassword.tsx)
  - [`resources/js/Pages/Auth/MfaChallenge.tsx`](file:///f:/Wholesale%20Distribution%20Management%20System/resources/js/Pages/Auth/MfaChallenge.tsx)
  - [`resources/js/Pages/Error.tsx`](file:///f:/Wholesale%20Distribution%20Management%20System/resources/js/Pages/Error.tsx)
  - [`resources/js/Pages/Security/MFA/Index.tsx`](file:///f:/Wholesale%20Distribution%20Management%20System/resources/js/Pages/Security/MFA/Index.tsx)
- **Route / Component:** All public and authenticated views
- **Observed:** Default fallback string was `'Wholesale Distribution Management System'` and `'Wholesale Distribution Inc.'`.
- **Expected:** Consistent client presentation as `'Unique Distributors'` and `'Unique Distributors Inc.'`.
- **Implementation:** Updated default configuration constants, services, frontend document title templates, navigation sidebars, error screens, and auth headers to `'Unique Distributors'`.
- **Verification:** Browser tests verify `<title>` and UI layout text render "Unique Distributors".
- **Classification:** `SAFE CLEANUP`

---

### Finding SEC-004: Missing HTTP Security Response Headers (CSP, HSTS, X-Frame-Options)
- **ID:** `SEC-004`
- **Category:** HTTP Security / Browser Hardening
- **Severity:** `P2 / Medium`
- **Confidence:** `Confirmed`
- **Status:** `IMPLEMENTED & VERIFIED`
- **Affected File(s):** [`app/Http/Middleware/SecurityHeadersMiddleware.php`](file:///f:/Wholesale%20Distribution%20Management%20System/app/Http/Middleware/SecurityHeadersMiddleware.php), [`bootstrap/app.php`](file:///f:/Wholesale%20Distribution%20Management%20System/bootstrap/app.php)
- **Route / Component:** Global Web Middleware Stack
- **Observed:** HTTP response headers omitted `X-Frame-Options`, `X-Content-Type-Options`, `Referrer-Policy`, `Permissions-Policy`, and Content-Security-Policy (CSP).
- **Expected:** Production web applications must serve protective HTTP headers without breaking Inertia, React, Vite, or S3 presigned asset delivery.
- **Implementation:** Created `SecurityHeadersMiddleware` setting `X-Frame-Options: SAMEORIGIN`, `X-Content-Type-Options: nosniff`, `Referrer-Policy: strict-origin-when-cross-origin`, `Permissions-Policy: camera=(), microphone=(), geolocation=()`, HSTS (when HTTPS), and a tailored Content-Security-Policy allowing Google Fonts and S3 presigned URLs (`https://*.amazonaws.com`, `https://*.s3.amazonaws.com`).
- **Verification:** PHPUnit tests assert presence and correctness of all security headers. Playwright confirms zero CSP console violations during runtime navigation.
- **Classification:** `FIX`

---

### Finding DEAD-001: Obsolete Scaffold `/foundation` Route and `Welcome.tsx` Component
- **ID:** `DEAD-001`
- **Category:** Dead Code / Scaffold Cleanup
- **Severity:** `P2 / Medium`
- **Confidence:** `Confirmed`
- **Status:** `IMPLEMENTED & VERIFIED`
- **Affected File(s):**
  - [`routes/web.php`](file:///f:/Wholesale%20Distribution%20Management%20System/routes/web.php)
  - [`resources/js/Pages/Welcome.tsx`](file:///f:/Wholesale%20Distribution%20Management%20System/resources/js/Pages/Welcome.tsx)
- **Route / Component:** `GET /foundation` -> `Welcome.tsx`
- **Observed:** `/foundation` was a retained scaffold route from Phase 00 exposing framework version details.
- **Expected:** Unauthenticated/unreferenced scaffold routes should be pruned from production builds.
- **Implementation:** Removed route definition from `routes/web.php` and deleted `resources/js/Pages/Welcome.tsx`.
- **Verification:** Visiting `/foundation` now returns HTTP 404 Not Found. Authenticated dashboard routing remains unaffected.
- **Classification:** `SAFE CLEANUP`

---

### Finding DEAD-002: Redundant Duplicate Route Aliases (`-create` vs `/create`)
- **ID:** `DEAD-002`
- **Category:** Dead Code / Route Hygiene
- **Severity:** `P3 / Low`
- **Confidence:** `Confirmed`
- **Status:** `IMPLEMENTED & VERIFIED`
- **Affected File(s):** [`routes/web.php`](file:///f:/Wholesale%20Distribution%20Management%20System/routes/web.php), [`resources/js/Layouts/AppLayout.tsx`](file:///f:/Wholesale%20Distribution%20Management%20System/resources/js/Layouts/AppLayout.tsx), [`resources/js/Pages/Customer/Index.tsx`](file:///f:/Wholesale%20Distribution%20Management%20System/resources/js/Pages/Customer/Index.tsx), [`resources/js/Pages/Product/Index.tsx`](file:///f:/Wholesale%20Distribution%20Management%20System/resources/js/Pages/Product/Index.tsx), [`resources/js/Pages/Salesman/Index.tsx`](file:///f:/Wholesale%20Distribution%20Management%20System/resources/js/Pages/Salesman/Index.tsx)
- **Route / Component:**
  - `/categories-create` vs `/categories/create`
  - `/tax-profiles-create` vs `/tax-profiles/create`
  - `/customers-create` vs `/customers/create`
  - `/salesmen-create` vs `/salesmen/create`
  - `/products-create` vs `/products/create`
- **Observed:** Kebab-case create aliases were registered alongside RESTful `/create` endpoints, and parameter wildcards on resource routes risked capturing `/create` without regex constraints.
- **Expected:** RESTful canonical routes should be the single authoritative navigation targets, with legacy `-create` URLs providing backward-compatible 301 redirects where needed.
- **Implementation:** Standardized all frontend navigation links to `/customers/create`, `/salesmen/create`, `/products/create`, `/categories/create`, and `/tax-profiles/create`. Added `->whereNumber()` parameter constraints to prevent route wildcard collisions. Added 301 permanent redirects for legacy `-create` URLs.
- **Verification:** Feature tests and Playwright navigation confirm all canonical create routes load with HTTP 200 and legacy URLs redirect safely to canonical endpoints.
- **Classification:** `SAFE CLEANUP`
