# Security Hardening & Implementation Plan

**Document Version:** 1.0  
**Date:** September 12, 2026  
**Application:** Unique Distributors — Wholesale Distribution Management System  
**Mode:** Plan Only (No code modified)  
**Target Environment:** Pre-Production & Production  

---

## 1. Executive Summary

This Security Hardening Plan provides an implementation-ready blueprint for elevating the security posture of Unique Distributors across authentication, authorization, rate limiting, HTTP security headers, file upload protections, error handling, and secret hygiene.

Every recommendation adheres to the project's source-of-truth hierarchy, zero-client-trust architecture (`RULE-SEC-001`), server-authoritative calculations (`RULE-SEC-002`), and non-destructive accounting invariants (`RULE-ACC-001`).

---

## 2. Authentication & MFA Hardening

### 2.1 Multi-Factor Authentication Throttle Refinement
- **Current Behavior:** `MfaChallengeRequest` limits failed TOTP/recovery attempts to 5 per minute keyed solely to the client IP address (`'mfa-challenge:' . Str::transliterate($this->ip())`).
- **Target Hardening:** Incorporate the pending session user ID into the throttle key:
  ```php
  public function throttleKey(): string
  {
      $userId = $this->session()->get('mfa.challenge.user_id') ?? 'anon';
      return 'mfa-challenge:' . $userId . '|' . Str::transliterate($this->ip());
  }
  ```
- **Benefit:** Prevents accidental shared-IP branch office lockouts while strictly defending individual accounts against brute-force attacks.

### 2.2 Session Security & Cookie Configuration
- Ensure session cookies enforce:
  - `HttpOnly: true` (prevents JavaScript access to session tokens)
  - `Secure: true` (enforces transmission strictly over TLS in staging/production)
  - `SameSite: lax` (defends against Cross-Site Request Forgery while allowing standard top-level navigation)
  - `session.encrypt: true` (optional pre-production configuration for sensitive session payloads).

---

## 3. Comprehensive Rate Limiting Architecture

To defend against abuse, rapid transaction replay, and resource exhaustion, endpoints must be protected by category-specific rate limiters.

### Rate Limiting Specification Matrix

| Endpoint Category | Route Pattern | Rate Limit | Window | Identity Key / Fallback | Expected HTTP Status & UX Behavior |
|---|---|---|---|---|---|
| **Login Authentication** | `POST /login` | 5 requests | 1 minute | `email \| ip` | `429 Too Many Requests` (shows clear countdown) |
| **MFA Verification** | `POST /login/mfa` | 5 requests | 1 minute | `user_id \| ip` | `429 Too Many Requests` (account locked temporarily) |
| **Password Reset Request** | `POST /forgot-password` | 3 requests | 10 minutes | `email \| ip` | `429 Too Many Requests` (prevents email flooding) |
| **Password Reset Execution** | `POST /reset-password` | 5 requests | 15 minutes | `ip` | `429 Too Many Requests` |
| **Payment Evidence Upload** | `POST /admin/payments/*`, `POST /salesman/payments/*` | 20 uploads | 1 minute | `user_id` (IP fallback) | `429 Too Many Requests` (graceful retry alert) |
| **Payment Verification** | `POST /admin/payments/{id}/verify` | 30 requests | 1 minute | `user_id` | `429 Too Many Requests` |
| **Order Draft & Submission** | `POST /salesman/orders`, `POST /salesman/orders/drafts` | 30 requests | 1 minute | `user_id` | `429 Too Many Requests` |
| **Order Approval / Rejection** | `POST /admin/orders/{id}/approve` | 30 requests | 1 minute | `user_id` | `429 Too Many Requests` |
| **Inventory Mutation** | `POST /admin/inventory-adjustments` | 20 requests | 1 minute | `user_id` | `429 Too Many Requests` |
| **Delivery Completion / POD** | `POST /delivery/{id}/complete` | 15 requests | 1 minute | `driver_id` | `429 Too Many Requests` |
| **Invoice PDF Generation** | `GET /invoices/{id}/pdf` | 15 requests | 1 minute | `user_id` | `429 Too Many Requests` (prevents Chromium queue DoS) |
| **Report / Analytics Export** | `GET /admin/reports/*` | 30 requests | 1 minute | `user_id` | `429 Too Many Requests` |

---

## 4. Authorization & IDOR Hardening

### 4.1 Route Middleware Alignment
Wrap currently unprotected administrative financial routes in `routes/web.php` with explicit permission middleware:
```php
// Credit Note Sub-Ledger Protection
Route::middleware('permission:credit.view')->group(function () {
    Route::get('/admin/credits', [\App\Http\Controllers\Admin\AdminCreditNoteController::class, 'index'])
        ->name('admin.credits.index');
    Route::get('/admin/credits/{id}', [\App\Http\Controllers\Admin\AdminCreditNoteController::class, 'show'])
        ->whereNumber('id')
        ->name('admin.credits.show');
    Route::get('/admin/returns/{returnRequest}/credit-eligibility', [\App\Http\Controllers\Admin\AdminCreditNoteController::class, 'calculateEligibility'])
        ->whereNumber('returnRequest')
        ->name('admin.returns.credit-eligibility');
});

// Refund Requests Protection
Route::middleware('permission:refund.view')->group(function () {
    Route::get('/admin/refunds', [\App\Http\Controllers\Admin\AdminRefundRequestController::class, 'index'])
        ->name('admin.refunds.index');
    Route::get('/admin/refunds/{id}', [\App\Http\Controllers\Admin\AdminRefundRequestController::class, 'show'])
        ->whereNumber('id')
        ->name('admin.refunds.show');
});
```

### 4.2 Authoritative Resource Scoping Invariant
Every controller receiving a model identifier (`int $id` or implicit route model binding) must enforce the three-tier security boundary:
```text
1. Authentication (User logged in & account active)
2. Permission Check (User has required role/permission)
3. Resource Scope Check (Salesman customer assignment / Driver delivery assignment / Warehouse task assignment)
```

---

## 5. HTTP & Browser Security Headers

### 5.1 Security Headers Middleware Specification
Create `App\Http\Middleware\SecurityHeadersMiddleware` and register in `bootstrap/app.php`:

```php
namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SecurityHeadersMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        /** @var Response $response */
        $response = $next($request);

        $response->headers->set('X-Frame-Options', 'SAMEORIGIN');
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=()');

        if (app()->environment('production', 'staging')) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        // Tailored CSP supporting Inertia, React, Vite HMR (dev), and Google Fonts
        $csp = "default-src 'self'; "
            . "script-src 'self' 'unsafe-inline' 'unsafe-eval'; "
            . "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; "
            . "font-src 'self' https://fonts.gstatic.com data:; "
            . "img-src 'self' data: blob: https://*.amazonaws.com; "
            . "connect-src 'self' https://*.amazonaws.com; "
            . "frame-ancestors 'self'; "
            . "form-action 'self';";

        $response->headers->set('Content-Security-Policy', $csp);

        return $response;
    }
}
```

---

## 6. File Upload & S3 Storage Security

### 6.1 Upload Defense Invariants
1. **Magic Byte Verification:** All uploads must pass authoritative binary inspection (`\xFF\xD8\xFF` for JPEGs, `\x89PNG` for PNGs) via `StorageManagerService`.
2. **SVG Prohibition:** SVGs and XML vectors remain strictly forbidden on all public/catalogue/evidence endpoints to prevent stored XSS attacks.
3. **Collision-Safe Keys:** Object keys must always use UUIDs in format `{domain}/{entity_id}/{subType}/{uuid}.{ext}` without browser-provided filenames.
4. **Temporary Presigned Access:** Direct S3 URLs are never stored in databases; presigned URLs are generated on demand with a 15-minute maximum lifetime.
5. **Compensating Rollbacks:** If a database transaction fails after an upload, `StorageManagerService::compensateDelete()` removes the orphaned object.

---

## 7. Error Handling & Information Leakage Prevention

### 7.1 Production Error Sanitization
In `bootstrap/app.php`:
- Ensure `APP_DEBUG=false` in staging/production environments.
- Verify that standard exception handling renders custom Inertia `Error.tsx` views for 403, 404, 419, 500, and 503 status codes without leaking SQLSTATE, file paths, AWS bucket names, or environment details.
- Internal exception details remain strictly confined to server-side logging (`storage/logs/laravel.log` or cloud log streams).

---

## 8. Secrets & Dependency Hygiene

1. **Zero Committed Secrets:** Continuously ensure `.env` and sensitive credentials remain excluded via `.gitignore`.
2. **OIDC Preferential Policy:** Maintain the transition from long-lived AWS IAM access keys to short-lived OpenID Connect role assumptions once the production hosting platform is selected.
3. **Dependency Maintenance:** Maintain regular security audits via `composer audit` and `npm audit` during continuous integration workflows.
