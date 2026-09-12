# Codex Remediation — Phase 1: Critical Regressions

**Date:** September 12, 2026  
**Status:** Complete  
**Branch:** `codex/remediation-production-audit-20260912`

## Invoice print failure

**Reproduction:** an invoice with at least one `VERIFIED` order payment failed while rendering `/invoices/{invoice}/print` at `resources/views/documents/invoice.blade.php:536`.

**Root cause:** the print template accessed `$payment->transaction_date->format(...)`. `transaction_date` is not a `Payment` attribute, database column, or Eloquent cast. The canonical payment field is the non-null, date-cast `payment_date` defined by the payments migration and payment model. The controller and PDF service both render the same template, so the defect affected HTML printing and PDF generation.

**Resolution:** the template now renders the authoritative `payment_date`. A narrowly scoped `Payment date unavailable` label makes the renderer safe for a legacy malformed in-memory record without inventing a date or changing financial values. New feature coverage exercises no payments, pending-payment exclusion, multiple verified payments, and this legacy rendering edge case.

## AUTH-001 user-interface leak

**History:** `AUTH-001` was introduced in the original login UI commit `f43ff55`, then removed from the TypeScript source in `a5322c3`. Current React source remains clean and the existing browser regression spec asserts that normal login UI does not display the identifier.

**Regression source:** `public/build` is ignored, and its locally retained pre-removal `Login-BlDqjaIO.js` bundle still contained `AUTH-001`. A non-HMR Laravel instance could therefore serve stale frontend assets even though the source was correct.

**Resolution:** a fresh `npm run build` regenerated the ignored production assets. The current generated login bundle contains no `AUTH-001`. Generated build output remains uncommitted by repository policy; production deployment must run the normal asset build rather than reuse stale local artifacts.

## Validation

- `php artisan test --filter=InvoicePrintTest --testdox` — 10 tests, 46 assertions passed.
- `php artisan test --filter=InvoicePdfTest --testdox` — 8 tests, 20 assertions passed.
- `php artisan test --filter=AuthenticationTest --testdox` — 59 tests, 287 assertions passed.
- `npm run type-check` — passed.
- `npm run build` — passed; regenerated the login asset.
- Source and regenerated asset search for `AUTH-001` — no user-facing match.

## Invariants retained

- Payment verification, maker-checker controls, and payment values were not changed.
- Invoice authorization and salesman resource scoping remain enforced by the existing print and PDF controllers.
- Invoice documents continue to exclude product images and payment-evidence images.
