# Master Defect & Bug Register (BUGLIST.md)

**Document Version:** 1.0  
**Audit Date:** September 8, 2026  
**Auditor:** Antigravity AI Agent (Forensic Read-Only Real-Browser Audit)  
**Status:** ACTIVE AUDIT REGISTER  
**Audit Scope:** Whole Application (All 8 Roles, All Domains, 320px–1920px Viewports)

---

## 1. Summary Defect Metrics

| Priority | Classification | Count | Description |
|---|---|:---:|---|
| **P0** | Blocker / Catastrophic | 0 | Severe security breach, data corruption, financial integrity failure. |
| **P1** | High / Core Workflow | 3 | Core business workflow failure, critical role unable to perform action, or auth leakage. |
| **P2** | Medium / Important | 0 | Important functional defect, responsive breakdown, or scoping issue. |
| **P3** | Low / Polish | 1 | Minor route alias, wording, non-blocking usability issue. |
| **P4** | Enhancement / Deferred | 0 | Approved roadmap enhancement or optional polish. |
| **Total** | | **4** | Confirmed defects discovered during this forensic audit. |

### Domain Breakdown
- **Security & Authorization (IDOR):** 1 (BUG-013)
- **Financial & Accounts Receivable:** 1 (BUG-011)
- **Credits, Refunds & Reverse Logistics:** 1 (BUG-012)
- **Logistics & Navigation:** 1 (BUG-014)
- **Inventory & Allocation:** 0 (Verified sound, zero negative stock or over-allocation)
- **Order Processing & Workflows:** 0 (Adjustments, queue, approvals functional)
- **Payments & Evidence:** 0 (Cash/Cheque/Money Order verification and validation operational)
- **Responsive & Viewports (320px–1920px):** 0 (All 11 breakpoints structurally compliant)
- **Accessibility (WCAG 2.1 AA):** 0 (Form control keyboard Tab cycles validated)
- **Regressions:** 0 (No previously fixed defects regressed; Phase 00 copy clean; 25P02 transaction aborts absent)

---

## 2. Discovered Defect Inventory

---

### [BUG-011] Eloquent Relationship Name Mismatch (`Order::invoices()`) Crashes Customer Master, AR Dashboard, Ledgers, Statements, and Financial Reports with HTTP 500

- **Bug ID:** BUG-011
- **Priority:** P1 (High)
- **Severity:** Major
- **Domain:** Accounts Receivable / Customer Domain / Reporting
- **Role:** Admin, Accountant, Salesman
- **Impacted Routes:**
  - `GET /customers/{id}` (Customer Profile / Financial Summary)
  - `GET /admin/receivables` (AR Dashboard)
  - `GET /admin/receivables/{customer}` (Customer AR Ledger)
  - `GET /admin/receivables/{customer}/statement` (Customer Statement)
  - `GET /admin/reports/customers` (Customer Financial Performance Report)
- **Precondition:** Authenticated user with permission (`customer.view`, `receivable.view`, or `reports.view`) visits any customer financial view or statement.
- **Reproduction Steps:**
  1. Login as Admin (`admin.qa@example.test`) or Accountant (`accountant.qa@example.test`).
  2. Navigate directly to `/admin/receivables`.
  3. Observe immediate HTTP 500 Server Error.
  4. Navigate to `/customers/31` (or click on any customer in `/customers`).
  5. Observe immediate HTTP 500 Server Error.
  6. Navigate to `/admin/reports/customers`.
  7. Observe immediate HTTP 500 Server Error.
- **Observed Behavior:**
  Server terminates execution with an unhandled exception:
  `BadMethodCallException: Call to undefined method App\Models\Order::invoices()`
- **Expected Behavior:**
  - `/admin/receivables` renders AR Dashboard with aging buckets, customer balances, and outstanding metrics.
  - `/customers/{id}` renders customer profile with derived financial overview (total orders, total invoices, operational outstanding).
  - `/admin/reports/customers` renders customer performance metrics.
- **Root Cause Analysis:**
  In `App\Models\Order`, the invoice relationship is singular:
  ```php
  public function invoice(): HasOne
  {
      return $this->hasOne(Invoice::class);
  }
  ```
  However, in:
  1. `app/Services/Receivable/ReceivableLedgerService.php:265`:
     ```php
     Order::where('customer_id', $customerId)
         ->whereIn('status', [OrderStatus::APPROVED->value, OrderStatus::PROCESSING->value, OrderStatus::DISPATCHED->value])
         ->whereDoesntHave('invoices')
         ->sum('grand_total');
     ```
  2. `app/Services/Receivable/ReceivableAgingService.php:126`:
     ```php
     Order::where('customer_id', $customer->id)
         ->whereIn('status', [OrderStatus::APPROVED->value, OrderStatus::PROCESSING->value, OrderStatus::DISPATCHED->value])
         ->whereDoesntHave('invoices')
         ->get();
     ```
  Both services invoke `whereDoesntHave('invoices')` with a plural string instead of the defined singular relationship `invoice` (or an alias `invoices()`).
- **Evidence:**
  - Captured screenshots:
    - [BUG-011-admin-customer-detail-500.png](file:///f:/Wholesale%20Distribution%20Management%20System/artifacts/browser-audit/BUG-011-admin-customer-detail-500.png)
    - [BUG-011-ar-dashboard-500.png](file:///f:/Wholesale%20Distribution%20Management%20System/artifacts/browser-audit/BUG-011-ar-dashboard-500.png)
    - [BUG-011-ar-customer-31-ledger-500.png](file:///f:/Wholesale%20Distribution%20Management%20System/artifacts/browser-audit/BUG-011-ar-customer-31-ledger-500.png)
    - [BUG-011-ar-customer-31-statement-500.png](file:///f:/Wholesale%20Distribution%20Management%20System/artifacts/browser-audit/BUG-011-ar-customer-31-statement-500.png)
    - [BUG-011-admin-reports-customers-500.png](file:///f:/Wholesale%20Distribution%20Management%20System/artifacts/browser-audit/BUG-011-admin-reports-customers-500.png)
  - Stacktrace in `storage/logs/laravel.log`:
    `BadMethodCallException: Call to undefined method App\Models\Order::invoices() at Illuminate/Support/Traits/ForwardsCalls.php:67`
- **Impact:**
  - **Financial Impact:** Blocks access to operational AR and Customer Statements in browser UI.
  - **Security Impact:** None (fails closed via 500 error).
  - **Data Integrity:** No data corruption; purely a query relationship invocation defect.
- **Suggested Fix Direction:**
  In `app/Models/Order.php`, add an alias method:
  ```php
  public function invoices(): HasOne
  {
      return $this->hasOne(Invoice::class);
  }
  ```
  Or change `whereDoesntHave('invoices')` to `whereDoesntHave('invoice')` in `ReceivableLedgerService.php` and `ReceivableAgingService.php`.
- **Status:** CONFIRMED / REPRODUCED

---

### [BUG-012] Undefined Method `App\Enums\RefundStatus::options()` Crashes Admin Refunds Queue with HTTP 500

- **Bug ID:** BUG-012
- **Priority:** P1 (High)
- **Severity:** Major
- **Domain:** Credits & Refunds / Reverse Logistics
- **Role:** Admin, Accountant
- **Impacted Route:** `GET /admin/refunds`
- **Precondition:** Authenticated Admin or Accountant accesses the refund request management queue.
- **Reproduction Steps:**
  1. Login as Admin (`admin.qa@example.test`).
  2. Navigate to `/admin/refunds`.
  3. Observe immediate HTTP 500 Server Error.
- **Observed Behavior:**
  Server throws unhandled fatal error:
  `Error: Call to undefined method App\Enums\RefundStatus::options() at F:/Wholesale Distribution Management System/app/Http/Controllers/Admin/AdminRefundRequestController.php:78`
- **Expected Behavior:**
  The Refunds management workspace (`Admin/Refunds/Index`) should render with list of refund requests, status filter dropdown populated with options, and review modals.
- **Root Cause Analysis:**
  `AdminRefundRequestController::index()` passes:
  ```php
  'statusOptions' => RefundStatus::options(),
  ```
  However, `App\Enums\RefundStatus` (in `app/Enums/RefundStatus.php`) only defines `label()` and `badgeVariant()`, and does not have an `options()` static helper method.
- **Evidence:**
  - Captured screenshot: [BUG-012-admin-refunds-500.png](file:///f:/Wholesale%20Distribution%20Management%20System/artifacts/browser-audit/BUG-012-admin-refunds-500.png)
  - Stacktrace in `storage/logs/laravel.log`:
    `Error(code: 0): Call to undefined method App\Enums\RefundStatus::options() at app/Http/Controllers/Admin/AdminRefundRequestController.php:78`
- **Impact:**
  - **Workflow Impact:** Administrators and accountants cannot review, approve, or reject customer refund requests via the web UI.
  - **Financial Impact:** Blocks processing of customer cash/instrument settlements for approved returns.
- **Suggested Fix Direction:**
  Add an `options()` static method to `App\Enums\RefundStatus`:
  ```php
  public static function options(): array
  {
      return array_map(fn (self $case) => [
          'value' => $case->value,
          'label' => $case->label(),
      ], self::cases());
  }
  ```
- **Status:** CONFIRMED / REPRODUCED

---

### [BUG-013] Security Authorization Leakage: Delivery Partner Bypasses Scoping Guard and Accesses Admin Order Queue (`/admin/orders`)

- **Bug ID:** BUG-013
- **Priority:** P1 (Security / Scoping Violation)
- **Severity:** High
- **Domain:** Security & Access / Role Scoping
- **Role:** Delivery Partner (`UserRole::DELIVERY_PARTNER`)
- **Impacted Route:** `GET /admin/orders`
- **Precondition:** Delivery partner is logged in.
- **Reproduction Steps:**
  1. Login as Delivery Partner (`driver.qa@example.test`).
  2. In browser address bar, navigate directly to `/admin/orders`.
  3. Observe page loads with HTTP 200 OK.
- **Observed Behavior:**
  The Delivery Partner is granted full access to the administrative wholesale order queue (`Admin/Orders/Index`), exposing organization-wide orders, grand totals, customer identities, payment statuses, and review links.
- **Expected Behavior:**
  Access should be rejected with HTTP 403 Forbidden. Delivery partners must strictly be restricted to their logistics dashboard (`/delivery`) and assigned deliveries.
- **Root Cause Analysis:**
  In `routes/web.php` line 168:
  ```php
  Route::middleware('permission:order.view')->group(function () {
      Route::get('/salesman/orders', ...);
      Route::get('/admin/orders', [\App\Http\Controllers\Admin\AdminOrderController::class, 'index']);
  });
  ```
  `UserRole::DELIVERY_PARTNER` is assigned `Permission::ORDER_VIEW` in `PermissionService.php:318` so they can view line items of deliveries.
  In `AdminOrderController::index`:
  ```php
  // Salesmen are strictly restricted to the salesman portal (/salesman/orders)
  if ($actor->role === UserRole::SALESMAN) {
      throw new AuthorizationException('Salesmen must access orders via their salesman portal.');
  }
  ```
  The guard only checks and excludes `UserRole::SALESMAN`, failing to exclude `UserRole::DELIVERY_PARTNER` (and `WAREHOUSE_MANAGER`).
- **Evidence:**
  - Captured screenshot: [BUG-013-delivery-partner-orders-leakage.png](file:///f:/Wholesale%20Distribution%20Management%20System/artifacts/browser-audit/BUG-013-delivery-partner-orders-leakage.png)
  - HTTP Status: 200 OK received instead of 403 Forbidden.
- **Constitutional Violation:**
  - Violates `AGENTS.md` Section 7.3: *"Resource-Level Scope: Delivery partners may only access assigned deliveries."*
  - Violates `AGENTS.md` Section 9: *"administrative controls leaking into field-role workspaces."*
- **Suggested Fix Direction:**
  In `AdminOrderController::index`, update the role restriction:
  ```php
  if (in_array($actor->role, [UserRole::SALESMAN, UserRole::DELIVERY_PARTNER], true)) {
      throw new AuthorizationException('Field roles cannot access the administrative order operations queue.');
  }
  ```
  Or place `/admin/orders` behind an administrative-level permission (e.g. `order.approve` or dedicated `admin.order.view`).
- **Status:** CONFIRMED / REPRODUCED

---

### [BUG-014] Missing `/delivery/today` Route Alias Causes 404 for Drivers Following Canonical Documentation Links

- **Bug ID:** BUG-014
- **Priority:** P3 (Polish / Navigation Alignment)
- **Severity:** Minor
- **Domain:** Delivery & Logistics
- **Role:** Delivery Partner
- **Impacted Route:** `GET /delivery/today`
- **Precondition:** User attempts to navigate to the canonical today delivery route mentioned in the frontend specification and test matrix.
- **Reproduction Steps:**
  1. Login as Delivery Partner.
  2. Navigate to `http://localhost:8000/delivery/today`.
  3. Observe HTTP 404 Not Found.
- **Observed Behavior:**
  Returns 404. The actual active route is `/delivery`.
- **Expected Behavior:**
  `/delivery/today` should either exist or automatically redirect (301/302) to `/delivery` with active tab `today`.
- **Root Cause Analysis:**
  In `routes/web.php:341`, the driver dashboard is registered as `Route::get('/delivery', ...)`. No route or redirect exists for `/delivery/today`.
- **Status:** CONFIRMED / DOCUMENTATION GAP

---

## 3. Root-Cause Clusters

| Cluster ID | Shared Root Cause | Affected Defects | Impacted Subsystems |
|---|---|---|---|
| **RC-01** | **Eloquent Relationship Plurality Mismatch**<br>`Order::invoices()` does not exist; only singular `Order::invoice()` is defined. | **BUG-011** | Customer Profile, AR Dashboard, AR Ledger, Customer Statements, Customer Reports |
| **RC-02** | **Missing Enum Option Serialization Method**<br>`RefundStatus` enum lacks static `options()` method expected by controller. | **BUG-012** | Refund Requests Queue |
| **RC-03** | **Incomplete Role Guard in Shared-Permission Controller**<br>`AdminOrderController::index` blacklists `SALESMAN` but omits `DELIVERY_PARTNER`. | **BUG-013** | Admin Order Queue Security Scoping |
| **RC-04** | **Route Alias Omission**<br>`/delivery/today` referenced in documentation is unmapped in `routes/web.php`. | **BUG-014** | Logistics Dashboard Navigation |

---

## 4. Verification & Validation Status of Previous Fixes

| Backlog Item | Description | Audit Verification Result | Status |
|---|---|---|---|
| **Salesman Dashboard** | Ensure Salesman lands on Overview Dashboard without stale "Phase 00" text. | Verified in Phase 14 (`13_regression_e2e_financial.spec.ts`). Lands on `/dashboard`, renders "Field Sales Dashboard", zero stale phase text. | **VERIFIED CLEAN** |
| **Payment Collection** | Payment recording during New Sales Order creation. | Verified in Phase 5 (`04_salesman_new_order.spec.ts`). Validated method data, evidence requirements, and review calculation stability. | **VERIFIED CLEAN** |
| **Payment Verification** | Admin payment hub and maker-checker validation. | Verified in Phase 7 (`06_payments_verification.spec.ts`). Admin hub displays pending/verified tabs, Salesman POST unauthorized. | **VERIFIED CLEAN** |
| **Adjustments Schema & Concurrency** | Fix `order_items.status` column query error and false 409 status mismatch. | Verified in Phase 6 (`05_admin_orders_adjustments_inventory.spec.ts`). `/admin/adjustments` loads with 200, zero schema errors. | **VERIFIED CLEAN** |
| **PostgreSQL 25P02 Transaction Aborts** | Ensure no queries execute inside aborted transaction blocks. | Verified in Phase 8 & 14. No `SQLSTATE[25P02]` or transaction aborts found in logs during operations. | **VERIFIED CLEAN** |
| **Invoice Image Invariant** | Enforce RULE-DOC-001: Zero product images on formal invoices. | Verified in Phase 11 (`10_invoices_reports_notifications.spec.ts`). Markup inspected; exactly 0 images rendered in invoice table. | **VERIFIED INVARIANT** |
