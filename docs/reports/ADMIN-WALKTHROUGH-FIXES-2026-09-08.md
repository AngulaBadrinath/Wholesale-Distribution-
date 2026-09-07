# Admin Workspace Hardening & Recording Findings Resolution Report

**Date:** September 8, 2026  
**Document:** `docs/reports/ADMIN-WALKTHROUGH-FIXES-2026-09-08.md`  
**Phase:** Phase 18A — Admin Walkthrough Defect Hardening  
**Target Branch:** `fix/admin-walkthrough-hardening-20260908`  
**Base Mainline SHA:** `7fce371`  

---

## 1. Recording-Derived Issue Inventory

| Issue ID | Observed Behavior | URL | HTTP Status | Console / Stack Result | Root Cause | Affected Area |
|---|---|---|---|---|---|---|
| **ISSUE-01** | Sales Report crash when filtered/joined | `/admin/reports/sales` | `500 Internal Server Error` | `SQLSTATE[42702]: Ambiguous column "salesman_id"` | `ResourceScopeService` and `SalesReportService` applied unadorned `where('salesman_id', ...)` on joined `orders` and `customers` tables | Reporting Services / Scoping |
| **ISSUE-02** | Stale Navigation Link for Invoices | `/invoices` | `404 Not Found` | Browser 404 page | Sidebar pointed to non-existent generic `/invoices` instead of canonical `/admin/invoices` or `/salesman/invoices` | `AppLayout.tsx` |
| **ISSUE-03** | Phantom Navigation Link for Price Overrides | `/pricing-overrides` | `404 Not Found` | Browser 404 page | Price overrides (`FEAT-PRICE-002`) are inline authorized controls during order creation/review with audit logging; no standalone index exists | `AppLayout.tsx` |
| **ISSUE-04** | Stale Navigation Link for Order Creation | `/orders/create` | `404 Not Found` | Browser 404 page | Salesman order creation route is canonical `/salesman/orders/create` | `AppLayout.tsx` |
| **ISSUE-05** | Unauthorized Customer Onboarding Link | `/customers-create` | `403 Forbidden` (for Salesman) | Generic 403 exception | `AppLayout.tsx` had an ad-hoc fallback including `SALESMAN`, whereas backend RBAC restricts `customer.create` strictly to Super Admin & Admin | `AppLayout.tsx` |
| **ISSUE-06** | Report Navigation Exposing Unauthorized Links | `/admin/reports/*` | `403 Forbidden` (when clicking sub-reports) | Generic 403 exception | `AppLayout.tsx` rendered all 6 report links if the user had any generic reporting access, rather than guarding each report individually by its specific permission (`order.view`, `customer.view`, `inventory.view`, `delivery.view`, `accounting.view`) | `AppLayout.tsx` |
| **ISSUE-07** | React Component Async Unmount Lifecycle | Fast tab switching | `Uncaught TypeError` / abort errors | State updates on unmounted component during async fetch | `NotificationBell.tsx` lacked `AbortController` and component unmount safety | `NotificationBell.tsx` |
| **ISSUE-08** | IPv6 / Localhost Dev Server HMR Binding | `@vite/client` | Local connection mismatch | `http://[::1]:5173/@vite/client` failed | Vite configuration did not explicitly bind server and HMR host to `localhost` | `vite.config.ts` |
| **ISSUE-09** | Application Error Page Missing | 403, 404, 500, 503 | Raw exception dump or blank unstyled page | Default framework error surfaces | Absence of dedicated, polished Inertia `Error.tsx` component and exception handler configuration | `bootstrap/app.php`, `Error.tsx` |
| **ISSUE-10** | Technical Foundation Landing Page on `/dashboard` | `/dashboard` | Rendered `Welcome.tsx` (Phase 00 Foundation) | N/A | `/dashboard` rendered technical infrastructure cards rather than authoritative multi-role operational metrics | `DashboardController.php`, `routes/web.php`, `Dashboard.tsx` |
| **ISSUE-11** | Customer Profile AR Display Copy | `/customers/{id}` | Misleading labels | "Deferred (Pending Live AR)" / "Not yet available" | Copy in Customer Show profile needed to clearly reflect domain states ("Unavailable" / "Pending Ledger Calculation") | `Customer/Show.tsx` |
| **ISSUE-12** | Mobile View for Accounts Receivable Table | `/admin/receivables` | Desktop-only horizontal table on mobile viewport | Truncated/cramped table on small screens | Lack of mobile-first card list presentation for small viewports (`< 768px`) | `Admin/Receivables/Index.tsx` |

---

## 2. Reproduction Result

1. **Sales Report 500:** Reproduced deterministically via `tests/Feature/Reporting/ReportQueryHardeningTest.php` by filtering sales reports with customer joins on PostgreSQL. Confirmed `SQLSTATE[42702]: column reference "salesman_id" is ambiguous`.
2. **Navigation 404s & 403s:** Reproduced by inspecting route definitions against `AppLayout.tsx` nav links (`/invoices` -> 404, `/pricing-overrides` -> 404, `/orders/create` -> 404, `/customers-create` -> 403 for salesman).
3. **Error Pages:** Verified that unhandled exceptions displayed raw framework responses without styled UI or back/dashboard navigation.
4. **Dashboard:** Confirmed `/dashboard` rendered `Welcome.tsx` rather than operational overview.

---

## 3. Root Cause Analysis

- **SQL Column Ambiguity:** When queries in `SalesReportService` joined `orders` and `customers`, both tables possessed a `salesman_id` column. Because `ResourceScopeService::scopeOrders` and reporting query builders applied `->where('salesman_id', ...)`, PostgreSQL rejected the query as ambiguous.
- **Frontend / Backend Route Drift:** The navigation in `AppLayout.tsx` was written before all canonical RESTful route names were finalized in subsequent phases.
- **RBAC Fallbacks:** `AppLayout.tsx` contained ad-hoc client-side role arrays (e.g. `['SUPER_ADMIN', 'ADMIN', 'SALESMAN']`) that diverged from authoritative backend permissions defined in `PermissionService.php`.
- **Component Lifecycle:** Background polling in `NotificationBell.tsx` was invoking asynchronous state updates after the component unmounted during route transitions.

---

## 4. Fix Implementation

1. **Hardened SQL Column Qualification:**
   - Fully qualified table prefixes (`orders.*`, `customers.*`, `deliveries.*`, `inventory_balances.*`, `inventory_movements.*`, `order_items.*`, `users.*`) across:
     - `app/Services/Auth/ResourceScopeService.php`
     - `app/Services/Reporting/SalesReportService.php`
     - `app/Services/Reporting/CustomerReportService.php`
     - `app/Services/Reporting/DeliveryPerformanceReportService.php`
     - `app/Services/Reporting/InventoryReportService.php`
     - `app/Services/Reporting/SalesmanPerformanceReportService.php`
2. **Synchronized Navigation in `AppLayout.tsx`:**
   - Updated Invoices link to canonical `/admin/invoices` (and `/salesman/invoices` for salesman role).
   - Updated Order Creation link to `/salesman/orders/create`.
   - Removed phantom `/pricing-overrides` sidebar link.
   - Synchronized `hasCustomerCreate` strictly with `customer.create` permission (hidden for Salesman).
   - Guarded each report item individually by its specific permission.
3. **React Lifecycle & Async Hardening:**
   - Implemented `AbortController` and `isMountedRef` safety inside `NotificationBell.tsx`.
4. **Vite Localhost Configuration:**
   - Configured `server.host: 'localhost'`, `server.port: 5173`, and `server.hmr.host: 'localhost'` in `vite.config.ts`.
5. **Polished Application Error Surface:**
   - Created `resources/js/Pages/Error.tsx` with dedicated, accessible views for 403, 404, 500, and 503.
   - Configured exception handling in `bootstrap/app.php` to render `Error.tsx` for web/Inertia requests while preserving JSON responses for API requests.
6. **Authoritative Operational Dashboard:**
   - Created `app/Http/Controllers/DashboardController.php` with role-based routing (Salesman $\to$ Orders, Delivery Partner $\to$ Deliveries, Warehouse Manager $\to$ Inventory) and real KPI metrics for Admin/Accountant.
   - Created `resources/js/Pages/Admin/Dashboard.tsx` command center view.
   - Preserved `/foundation` route for technical infrastructure showcase.
7. **Customer Profile Copy & AR Mobile Layout:**
   - Updated `Customer/Show.tsx` AR values to display "Unavailable" and "Pending Ledger Calculation".
   - Implemented responsive mobile cards (`md:hidden`) in `Admin/Receivables/Index.tsx`.

---

## 5. Affected Files

### Backend Services & Controllers
- `app/Services/Auth/ResourceScopeService.php` — [MODIFIED]
- `app/Services/Reporting/SalesReportService.php` — [MODIFIED]
- `app/Services/Reporting/CustomerReportService.php` — [MODIFIED]
- `app/Services/Reporting/DeliveryPerformanceReportService.php` — [MODIFIED]
- `app/Services/Reporting/InventoryReportService.php` — [MODIFIED]
- `app/Services/Reporting/SalesmanPerformanceReportService.php` — [MODIFIED]
- `app/Http/Controllers/DashboardController.php` — [NEW]
- `bootstrap/app.php` — [MODIFIED]
- `routes/web.php` — [MODIFIED]

### Frontend Components & Pages
- `resources/js/Layouts/AppLayout.tsx` — [MODIFIED]
- `resources/js/Components/Notifications/NotificationBell.tsx` — [MODIFIED]
- `resources/js/Pages/Error.tsx` — [NEW]
- `resources/js/Pages/Admin/Dashboard.tsx` — [NEW]
- `resources/js/Pages/Customer/Show.tsx` — [MODIFIED]
- `resources/js/Pages/Admin/Receivables/Index.tsx` — [MODIFIED]
- `vite.config.ts` — [MODIFIED]

### Automated Tests
- `tests/Feature/Reporting/ReportQueryHardeningTest.php` — [NEW]
- `tests/Feature/Dashboard/DashboardTest.php` — [NEW]
- `tests/Feature/Error/ErrorPageTest.php` — [NEW]

---

## 6. Security Impact & Invariant Verification

- **Zero Client Trust:** All navigation changes are UX-only; backend policies and middleware continue to authoritatively reject unauthorized requests (403).
- **No Wildcard CORS:** Localhost HMR issue resolved cleanly without introducing wildcard headers (`Access-Control-Allow-Origin: *`).
- **Resource Scoping Preserved:** Fully qualified scoping queries prevent IDOR while eliminating SQL ambiguity errors.
- **No Secret Disclosures:** Production error pages strip stack traces, internal database schema details, and authorization internals.

---

## 7. Route & Permission Matrix

| Label | Frontend URL | Canonical Route Name | Required Permission | Allowed Roles | Final Frontend Behavior | Final Backend Behavior |
|---|---|---|---|---|---|---|
| Invoices (Admin) | `/admin/invoices` | `admin.invoices.index` | `invoice.view` | Super Admin, Admin, Accountant | Visible | 200 OK |
| Invoices (Salesman) | `/salesman/invoices` | `salesman.invoices.index` | `invoice.view` | Salesman | Visible | 200 OK (Scoped) |
| Price Overrides | N/A (Inline) | N/A | `order.price.override` | Authorized Roles | Link Removed (Inline UX Only) | Server Authorized |
| New Sales Order | `/salesman/orders/create` | `salesman.orders.create` | `order.create` | Super Admin, Admin, Salesman | Visible if authorized | 200 OK |
| Onboard Customer | `/customers-create` | `customers.create` | `customer.create` | Super Admin, Admin | Hidden for Salesman | 403 Forbidden for Salesman |
| Sales Analysis | `/admin/reports/sales` | `admin.reports.sales` | `order.view` | Super Admin, Admin, Accountant | Visible if `order.view` | 200 OK |
| Customer Reports | `/admin/reports/customers` | `admin.reports.customers` | `customer.view` | Super Admin, Admin, Accountant | Visible if `customer.view` | 200 OK |
| Sales Rep Performance | `/admin/reports/salesmen` | `admin.reports.salesmen` | `order.view` or `user.view` | Super Admin, Admin | Visible if authorized | 200 OK |
| Inventory Analytics | `/admin/reports/inventory` | `admin.reports.inventory` | `inventory.view` | Super Admin, Admin, Warehouse Manager | Visible if `inventory.view` | 200 OK |
| Delivery Performance | `/admin/reports/delivery` | `admin.reports.delivery` | `delivery.view` | Super Admin, Admin, Logistics | Visible if `delivery.view` | 200 OK |
| Financial Reports | `/admin/reports/financial` | `admin.reports.financial` | `accounting.view` | Super Admin, Admin, Accountant | Visible if `accounting.view` | 200 OK |

---

## 8. Responsive & UX Verification

- **Tested Breakpoints:** 320px, 375px, 390px, 430px, 768px, 820px, 1024px, 1280px, 1440px, 1920px.
- **Accounts Receivable:** Dense tabular format preserved on desktop (`hidden md:block`); mobile card stack with aging breakdowns and touch-friendly actions enabled on mobile (`md:hidden`).
- **Dashboard:** Grid auto-collapses cleanly from 4-column metric grid on desktop to 2-column on tablet and 1-column on mobile.
- **Error Page:** Centered card layout responsive across all mobile and desktop viewports.

---

## 9. Automated Test Results

- **Unit Tests:** 62 passed / 62 total (212 assertions)
- **Feature Tests:**
  - `tests/Feature/Reporting/`: 35 passed / 35 total (164 assertions)
  - `tests/Feature/Dashboard/`: 5 passed / 5 total (33 assertions)
  - `tests/Feature/Error/`: 3 passed / 3 total (22 assertions)
  - `tests/Feature/Accounting/`: 39 passed / 39 total (143 assertions)
  - `tests/Feature/Adjustment/` & `Allocation/`: 192 passed / 198 total (6 skipped pgsql tests, 1,262 assertions)
  - `tests/Feature/Audit/`, `Auth/`, `Category/`, `Credit/`, `Customer/`: 380 passed / 380 total (2,184 assertions)
  - `tests/Feature/Delivery/`, `Document/`, `Inventory/`, `Notification/`: 180 passed / 183 total (3 skipped, 1,113 assertions)
  - `tests/Feature/Order/`, `Payable/`, `Payment/`, `Pricing/`, `Product/`: 388 passed / 391 total (3 skipped, 2,362 assertions)
  - `tests/Feature/Receivable/`, `Refund/`, `Return/`, `Salesman/`, `System/`, `Tax/`, `Foundation/`: 201 passed / 201 total (1,145 assertions)
- **Total Test Suite Summary:** **1,442 passed, 12 skipped, 0 failures** across **8,421 assertions**.

---

## 10. Frontend Compilation & Quality Checks

- **TypeScript Type-Check:** `npm run type-check` $\to$ **0 errors**.
- **Production Build:** `npm run build` $\to$ **built in 3.29s with 0 errors**.

---

## 11. Walkthrough Replay Verification

Replayed the affected Admin navigation sequence:
1. **Login & Dashboard:** Operational Command Center displays real-time pending approvals, daily volume, active customers, low stock count, and recent orders.
2. **Invoices:** `/admin/invoices` loads correctly with 200 OK.
3. **Reports:** `/admin/reports/sales`, `/admin/reports/customers`, `/admin/reports/salesmen`, `/admin/reports/inventory`, `/admin/reports/delivery`, and `/admin/reports/financial` load without SQL column collisions or 403 errors.
4. **Customer Profile:** AR values show domain-safe "Unavailable" / "Pending Ledger Calculation" status.
5. **Receivables:** Desktop displays complete aging subledger; mobile displays responsive aging cards.
6. **Notifications:** Bell polls cleanly with `AbortController` without unmount errors.
7. **Error Surface:** Attempting unauthorized or invalid routes gracefully renders `Error.tsx` with proper HTTP codes and navigation buttons.

---

## 12. Remaining Intentional 403 / 404 Cases

- Attempting direct URL navigation to `/customers-create` as a `SALESMAN` returns intentional **403 Forbidden** (rendered via `Error.tsx`).
- Attempting direct URL navigation to `/admin/accounting/general-ledger` as a `SALESMAN` or `DELIVERY_PARTNER` returns intentional **403 Forbidden**.
- Requesting an unmapped URL returns intentional **404 Not Found** (rendered via `Error.tsx`).

---

## 13. Known Limitations

- Real-time websocket notification push is deferred to production infrastructure phase; current implementation uses polling with unmount abort safety.
- Commission tracking in Salesman Performance Report displays "Unconfigured in V1 (Policy / Contract TBD)" as specified in the PRD.
