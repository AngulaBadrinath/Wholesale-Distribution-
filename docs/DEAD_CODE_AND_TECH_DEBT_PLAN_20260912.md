# Dead Code, Branding & Technical Debt Cleanup Plan

**Document Version:** 1.0  
**Date:** September 12, 2026  
**Application:** Unique Distributors — Wholesale Distribution Management System  
**Mode:** Plan Only (No code modified)  
**Target Environment:** Pre-Production & Production  

---

## 1. Executive Summary

This plan outlines the systematic, risk-managed cleanup of technical debt, dead code, internal ticket identifiers, duplicate routes, and stale application branding across the codebase.

Cleanup is structured into **9 isolated implementation waves** to ensure non-destructive execution, zero regression in business workflows, and full verification against existing PHPUnit and Playwright test suites.

---

## 2. Classification Schema for Code & Artifact Candidates

Every candidate in this plan is evaluated against the following classification taxonomy:

- **Class A:** Definitely dead — safe cleanup with zero runtime or framework dependencies.
- **Class B:** Possibly dead — investigate indirect references before removal.
- **Class C:** Intentionally retained for backward compatibility or domain invariants.
- **Class D:** Framework-discovered / dynamically resolved (e.g. Artisan commands, policies, middleware).
- **Class E:** Test-only fixture / mock utility.
- **Class F:** Deployment / operational tooling.
- **Class G:** Requires human / stakeholder decision.

---

## 3. Candidate Inventory & Assessment

### 3.1 Dead Routes & Scaffold Components

| Item / File Path | Type | Classification | Assessment & Rationale | Action Recommendation |
|---|---|---|---|---|
| `routes/web.php` (`/foundation`) | Route | **Class A** | Early prototype route rendering `Welcome.tsx` with PHP/Laravel framework versions. Unreferenced in application navigation. | Prune route from `routes/web.php`. |
| `resources/js/Pages/Welcome.tsx` | Component | **Class A** | Prototype component solely backing `/foundation`. Contains developer badges and mock reactive inputs. | Delete component file. |
| `routes/web.php` (`/categories-create`) | Route Alias | **Class A** | Redundant duplicate of canonical RESTful route `/categories/create`. | Prune alias from `routes/web.php`. |
| `routes/web.php` (`/tax-profiles-create`) | Route Alias | **Class A** | Redundant duplicate of canonical RESTful route `/tax-profiles/create`. | Prune alias from `routes/web.php`. |
| `routes/web.php` (`/customers-create`) | Route Alias | **Class A** | Duplicate alias for `/customers/create`. | Prune alias. |
| `routes/web.php` (`/salesmen-create`) | Route Alias | **Class A** | Duplicate alias for `/salesmen/create`. | Prune alias. |
| `routes/web.php` (`/products-create`) | Route Alias | **Class A** | Duplicate alias for `/products/create`. | Prune alias. |

### 3.2 Internal Identifiers & User-Facing Leaks

| Item / Location | Content Found | Classification | Purpose / Reason | Action Recommendation |
|---|---|---|---|---|
| `resources/js/Pages/Auth/Login.tsx:211` | `AUTH-001` | **Class A** | Internal feature ticket ID rendered in footer span of login card. | Remove ticket ID span from JSX. |
| `resources/js/Pages/Welcome.tsx:212` | `TECH-FOUND-001` | **Class A** | Scaffold component footer badge. | Deleted alongside `Welcome.tsx`. |
| `docs/*.md` & `tests/` | Various ticket codes (`FEAT-*`, `RULE-*`) | **Class C** | Legitimate architectural documentation, business rules, and test specifications. | Retain as authoritative references. |

### 3.3 Application Branding Alignment

| File Location | Current Stale String | Approved Client Brand | Scope of Update |
|---|---|---|---|
| `config/app_identity.php:13` | `'Wholesale Distribution Management System'` | `'Unique Distributors'` | Default product name fallback |
| `config/app_identity.php:23` | `'Wholesale Distribution Inc.'` | `'Unique Distributors Inc.'` | Default business legal name fallback |
| `config/app_identity.php:65` | `'Wholesale Distribution Management System'` | `'Unique Distributors'` | Default footer copyright fallback |
| `app/Services/System/ApplicationIdentityService.php:9-16` | Constants referencing old defaults | Updated constants | Authoritative service defaults |
| `resources/js/app.tsx:7` | `'Wholesale Distribution Management System'` | `'Unique Distributors'` | Vite browser title default |
| `resources/js/Layouts/AppLayout.tsx:66` | Default fallback in navigation header | `'Unique Distributors'` | Authenticated app shell header |
| `resources/js/Pages/Auth/Login.tsx:17` | Default fallback on login screen | `'Unique Distributors'` | Login page title & header |
| `resources/js/Pages/Auth/ForgotPassword.tsx:16` | Default fallback | `'Unique Distributors'` | Password reset request view |
| `resources/js/Pages/Auth/ResetPassword.tsx:17` | Default fallback | `'Unique Distributors'` | Password reset form view |
| `resources/js/Pages/Auth/MfaChallenge.tsx:20` | Default fallback | `'Unique Distributors'` | MFA verification view |
| `resources/js/Pages/Error.tsx:108` | Hardcoded string in error footer | `'Unique Distributors'` | Error page footer branding |
| `resources/js/Pages/Security/MFA/Index.tsx:113` | Fallback title in settings | `'Unique Distributors'` | MFA configuration header |
| `.env.example:7` | `APP_NAME="Wholesale Distribution Management System"` | `APP_NAME="Unique Distributors"` | Developer template default |

---

## 4. Implementation Waves

The cleanup must be executed in 9 distinct, sequential waves:

```text
WAVE 1: Critical Security & Authorization Alignment (Routes & Permissions)
WAVE 2: Rate Limiting Implementation (Auth, Mutations, Document Exports)
WAVE 3: HTTP Security Headers Middleware (CSP, HSTS, X-Frame-Options)
WAVE 4: Authentication & MFA Throttle Refinement (Session User Binding)
WAVE 5: User-Facing Internal Identifier Removal (AUTH-001 in Login.tsx)
WAVE 6: Application Branding Standardization (Unique Distributors)
WAVE 7: Dead Code & Scaffold Route Pruning (/foundation, Welcome.tsx, duplicate aliases)
WAVE 8: Documentation & Environment Template Updates (.env.example, README)
WAVE 9: Final PHPUnit & Playwright Real-Browser Verification Across All Viewports
```

### Wave Breakdown & Impact

#### Wave 1: Critical Security & Authorization
- Files: `routes/web.php`
- Action: Wrap `/admin/credits/*` in `permission:credit.view` and `/admin/refunds/*` in `permission:refund.view`.
- Risk: None.

#### Wave 2: Rate Limiting Implementation
- Files: `app/Providers/AppServiceProvider.php` (or `bootstrap/app.php`), `routes/web.php`
- Action: Define named rate limiters (`orders`, `payments`, `documents`, `inventory`) and bind to routes.
- Risk: Low (thresholds set with high margins for human usage).

#### Wave 3: HTTP Security Headers
- Files: `app/Http/Middleware/SecurityHeadersMiddleware.php`, `bootstrap/app.php`
- Action: Create and register middleware with CSP tailored for React, Inertia, and Google Fonts.
- Risk: Low (requires Playwright verification to ensure zero console CSP blocks).

#### Wave 4: MFA Throttle Key Hardening
- Files: `app/Http/Requests/Auth/MfaChallengeRequest.php`
- Action: Bind session `user_id` to throttle key.
- Risk: Zero.

#### Wave 5: Stale UI Identifier Removal
- Files: `resources/js/Pages/Auth/Login.tsx`
- Action: Remove `<span className="font-mono text-[10px]">AUTH-001</span>`.
- Risk: Zero.

#### Wave 6: Client Branding Standardization
- Files: `config/app_identity.php`, `ApplicationIdentityService.php`, `resources/js/app.tsx`, `AppLayout.tsx`, `Login.tsx`, `ForgotPassword.tsx`, `ResetPassword.tsx`, `MfaChallenge.tsx`, `Error.tsx`, `resources/js/Pages/Security/MFA/Index.tsx`
- Action: Replace default fallback strings with `'Unique Distributors'`.
- Risk: Zero.

#### Wave 7: Dead Code & Scaffold Route Pruning
- Files: `routes/web.php`, `resources/js/Pages/Welcome.tsx`
- Action: Delete `Welcome.tsx`, remove `/foundation` and duplicate `-create` aliases.
- Risk: Zero.

#### Wave 8: Documentation & Environment Synchronization
- Files: `.env.example`, `docs/PROJECT_STATUS.md`
- Action: Synchronize defaults.
- Risk: Zero.

#### Wave 9: Verification
- Execute:
  - `php artisan test` (257 PHPUnit tests)
  - `npx playwright test` (Full browser suite across all 11 viewports: 320px to 1920px)
  - Verify zero console errors, zero broken layouts, and 100% green suite.

---

## 5. Acceptance Criteria for Eventual Execution

Upon completing execution:
1. Production UI displays strictly `Unique Distributors` with zero `AUTH-001` or prototype badges.
2. All administrative sub-ledger routes enforce dual-layer permission middleware and controller authorization.
3. Named rate limiting active across authentication, payments, orders, adjustments, and PDF generation.
4. HTTP response headers include `X-Frame-Options`, `X-Content-Type-Options`, `Referrer-Policy`, and CSP.
5. All 257 PHPUnit tests and Playwright browser tests pass with zero regression.
6. Git working tree remains clean with logical atomic commits.
