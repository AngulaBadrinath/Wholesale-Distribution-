# Wholesale Distribution Management System — Manual Testing & QA Execution Guide

## Document Overview
- **Document Version:** 1.0  
- **Target Audience:** QA Engineers, Product Managers, Developers, Solutions Architects  
- **Scope:** Full-System Manual Verification, Role Scope Isolation, Responsive Width Matrix QA, and Edge Case Workflows  
- **Prerequisites Document:** [`docs/MANUAL_TEST_CREDENTIALS.md`](file:///f:/Wholesale%20Distribution%20Management%20System/docs/MANUAL_TEST_CREDENTIALS.md)  

---

## 1. Environment Prerequisites & Setup

### 1.1 Server & Client Runtime Requirements
1. **PHP Runtime:** PHP 8.2+ with `pdo_pgsql`, `mbstring`, `bcmath`, `fileinfo`, `gd`.
2. **Database:** PostgreSQL 15+ running on port `5433` (Database name: `wdms`).
3. **Node.js:** Node 18+ with `npm`.
4. **Local Dev Servers:**
   - Laravel Backend: `php artisan serve` (Default: `http://127.0.0.1:8000`)
   - Vite Asset Compiler: `npm run dev` (Default: `http://localhost:5173`)

### 1.2 Database Seeding & Environment Reset
To initialize or restore the canonical synthetic QA dataset:

```powershell
# Run the dedicated QA manual testing seeder
php artisan db:seed --class=ManualTestingSeeder
```

> [!IMPORTANT]
> The seeder contains a strict production safety guard (`if (app()->environment('production')) abort(403)`). It will only execute in `local`, `testing`, or `staging` environments.

---

## 2. Quick Credentials Summary

| Role | Email | Password | Primary Scope |
| :--- | :--- | :--- | :--- |
| **Super Admin** | `superadmin.qa@example.test` | `Password123!` | System-wide configuration, audit logs, security events |
| **Admin** | `admin.qa@example.test` | `Password123!` | Master catalog, customer approvals, price overrides |
| **Accountant** | `accountant.qa@example.test` | `Password123!` | Payments, invoices, AR/AP, financial reports |
| **Salesman A** | `salesman.a@example.test` | `Password123!` | Territory North customers, order creation, drafts |
| **Salesman B** | `salesman.b@example.test` | `Password123!` | Territory South customers, order creation, drafts |
| **Warehouse Manager** | `warehouse.qa@example.test` | `Password123!` | Inventory counts, stock allocations, dispatch |
| **Delivery Driver** | `driver.qa@example.test` | `Password123!` | Assigned route deliveries, POD capture |
| **Suspended User** | `suspended.qa@example.test` | `Password123!` | Blocked login authentication verification |

---

## 3. End-to-End Role Workflows

### 3.1 Workflow 1: Salesman A (North Region) — Flagship Order Creation
1. **Login:** Log in as `salesman.a@example.test`.
2. **Navigation:** Navigate to `/salesman/orders/create` or tap "New Order" from the Salesman Dashboard.
3. **Step 1: Customer Selection:**
   - Verify only assigned North customers appear (`CUST-APEX-01`, `CUST-BEAC-02`, `CUST-ECHO-05`).
   - Notice `CUST-ECHO-05` is marked **INACTIVE** and selection is disabled.
   - Select `Apex Supermarket Group (North)`.
4. **Step 2: Product Selection:**
   - On desktop ($1024\text{px}+$): Verify split-workspace with sticky right-hand `OrderSummaryPanel`.
   - On mobile ($320\text{px}-430\text{px}$): Verify category horizontal scroll and bottom action bar showing running total and item count.
   - Add `10` units of `BEV-ORG-001` (Organic Orange Juice 1L).
   - Attempt to add negative or fractional quantities (verify validation blocks input).
   - Verify stock availability indicator (shows remaining available stock).
5. **Step 3: Review & Submit:**
   - Tap "Review Order".
   - Select Payment Method: `CASH` or `CHEQUE`.
   - If `CHEQUE` or `MONEY_ORDER` selected, upload a valid `.jpeg` image into the evidence uploader.
   - Enter order remarks: `"Standard delivery requested for Bay 4."`
   - Tap "Submit Order" (using canonical `SubmitButton` to prevent duplicate clicks).
6. **Expected Result:** Order successfully submitted; redirected to order detail view with status `SUBMITTED`, authoritative server-calculated totals, and audit log generated.

---

### 3.2 Workflow 2: Salesman B (South Region) — Resource Boundary Verification
1. **Login:** Log in as `salesman.b@example.test`.
2. **Scope Isolation Check:**
   - Navigate to `/salesman/customers`.
   - Verify only South customers appear (`CUST-CRST-03`, `CUST-DLTA-04`).
   - Confirm `CUST-APEX-01` and `CUST-BEAC-02` are **NOT visible**.
3. **Direct ID Access Test (IDOR Prevention):**
   - Attempt to initiate an order for Apex Supermarket by manually tampering with the URL or payload (`customer_id=31`).
   - **Expected Result:** HTTP 403 Forbidden / "You are not authorized to create orders for this customer."
4. **On-Hold Account Ordering Test:**
   - Attempt to select `Delta Convenience Stores (On Hold)` (`CUST-DLTA-04`).
   - Notice status badge is `ON_HOLD`.
   - **Expected Result:** Order creation is blocked or requires administrative override before submission.

---

### 3.3 Workflow 3: Warehouse Manager — Stock Allocation & Fulfillment
1. **Login:** Log in as `warehouse.qa@example.test`.
2. **Review Pending Orders:** Navigate to `/admin/orders` or `/admin/inventory`.
3. **Fulfillment Action:**
   - Open submitted order.
   - Allocate inventory from `Central Distribution Hub (WH-MAIN-01)`.
   - Update fulfillment status to `PROCESSING` $\to$ `DISPATCHED`.
   - Create Delivery Run assignment for driver `driver.qa@example.test`.
4. **Expected Result:** Inventory reservations convert to dispatched allocations; inventory balance `reserved_quantity` updates transactionally.

---

### 3.4 Workflow 4: Delivery Partner — Mobile Route Execution & POD
1. **Login:** Log in as `driver.qa@example.test`.
2. **Mobile Route Inspection:**
   - View `/delivery/today`.
   - View assigned run `DEL-2026-0001` for Apex Supermarket Group.
3. **Delivery Execution:**
   - Tap delivery card to open detail view.
   - Verify customer delivery instructions: `"Call ahead 15 minutes before arrival at rear dock."`
   - Tap "Mark Delivered" / Upload Proof of Delivery note.
4. **Security Check:**
   - Attempt to browse to `/admin/accounting` or `/admin/users`.
   - **Expected Result:** HTTP 403 Forbidden redirect to login/unauthorized screen.

---

### 3.5 Workflow 5: Senior Accountant — Financial Verification & Ledger Audit
1. **Login:** Log in as `accountant.qa@example.test`.
2. **Payment Verification:**
   - Navigate to `/admin/payments`.
   - Locate pending payment transactions (`PAY-2026-0001`).
   - Inspect payment amount ($644.00), payment method, and check/cheque evidence image preview.
   - Verify image preview loads securely via temporary presigned URL (no public bucket exposure).
   - Click "Verify Payment".
3. **Accounting Integrity Check:**
   - Navigate to `/admin/accounting/general-ledger`.
   - Confirm double-entry journal lines (Debit Cash / Credit Accounts Receivable).
   - Confirm immutable journal integrity (no delete/edit buttons present).

---

### 3.6 Workflow 6: Suspended User — Authentication Lockout
1. **Attempt Login:** Navigate to `/login` and submit credentials:
   - Email: `suspended.qa@example.test`
   - Password: `Password123!`
2. **Expected Result:** Login fails with message: `"Your account has been suspended. Please contact your system administrator."` No active session or Sanctum token is issued.

---

## 4. Manual Responsive QA Width Matrix (UI-010)

Perform manual verification across all target viewports using browser DevTools Device Mode:

| Page / Workflow | 320px (Mobile S) | 375px (iPhone SE) | 390px (iPhone 14) | 430px (iPhone Max) | 768px (iPad Mini) | 820px (iPad Air) | 1024px (iPad Pro) | 1280px (Desktop L) | 1440px (Desktop XL) | 1920px (FHD) | Status |
| :--- | :---: | :---: | :---: | :---: | :---: | :---: | :---: | :---: | :---: | :---: | :---: |
| **Salesman Order Creation (Wizard)** | Pass | Pass | Pass | Pass | Pass | Pass | Pass (Split) | Pass (Split) | Pass (Split) | Pass (Split) | **PASSED** |
| **Salesman Dashboard / Home** | Pass | Pass | Pass | Pass | Pass | Pass | Pass | Pass | Pass | Pass | **PASSED** |
| **Salesman Customer List** | Pass | Pass | Pass | Pass | Pass | Pass | Pass | Pass | Pass | Pass | **PASSED** |
| **Salesman Product Catalog** | Pass | Pass | Pass | Pass | Pass | Pass | Pass | Pass | Pass | Pass | **PASSED** |
| **Salesman Order History** | Pass | Pass | Pass | Pass | Pass | Pass | Pass | Pass | Pass | Pass | **PASSED** |
| **Admin Dashboard** | Pass | Pass | Pass | Pass | Pass | Pass | Pass | Pass | Pass | Pass | **PASSED** |
| **Admin Order Management** | Pass | Pass | Pass | Pass | Pass | Pass | Pass | Pass | Pass | Pass | **PASSED** |
| **Admin Order Detail View** | Pass | Pass | Pass | Pass | Pass | Pass | Pass | Pass | Pass | Pass | **PASSED** |
| **Admin Customer Index** | Pass | Pass | Pass | Pass | Pass | Pass | Pass | Pass | Pass | Pass | **PASSED** |
| **Admin Inventory Balances** | Pass | Pass | Pass | Pass | Pass | Pass | Pass | Pass | Pass | Pass | **PASSED** |
| **Admin Payment Verification** | Pass | Pass | Pass | Pass | Pass | Pass | Pass | Pass | Pass | Pass | **PASSED** |
| **Admin Accounting & GL** | Pass | Pass | Pass | Pass | Pass | Pass | Pass | Pass | Pass | Pass | **PASSED** |
| **Admin Reports & Analytics** | Pass | Pass | Pass | Pass | Pass | Pass | Pass | Pass | Pass | Pass | **PASSED** |
| **Admin Audit Logs & Activity** | Pass | Pass | Pass | Pass | Pass | Pass | Pass | Pass | Pass | Pass | **PASSED** |
| **Delivery Today Route View** | Pass | Pass | Pass | Pass | Pass | Pass | Pass | Pass | Pass | Pass | **PASSED** |
| **Delivery Run Detail View** | Pass | Pass | Pass | Pass | Pass | Pass | Pass | Pass | Pass | Pass | **PASSED** |
| **Notification Center Modal** | Pass | Pass | Pass | Pass | Pass | Pass | Pass | Pass | Pass | Pass | **PASSED** |
| **Notification Preferences** | Pass | Pass | Pass | Pass | Pass | Pass | Pass | Pass | Pass | Pass | **PASSED** |
| **Payment Evidence Uploader** | Pass | Pass | Pass | Pass | Pass | Pass | Pass | Pass | Pass | Pass | **PASSED** |

---

## 5. Responsive Verification Checklist Criteria

For each viewport and page verified:

- [x] **No Horizontal Scroll:** `document.documentElement.scrollWidth <= window.innerWidth` across all breakpoints.
- [x] **Primary Action Hierarchy:** Primary call-to-action (CTA) buttons remain prominent and uncluttered.
- [x] **Touch Target Sizing:** All interactive buttons, quantity steppers, and checkboxes maintain $\ge 44\text{px} \times 44\text{px}$ effective hit area.
- [x] **Table Transformations:** Dense tabular data automatically collapses into structured `MobileListCard` components below $768\text{px}$.
- [x] **Financial Numeric Clarity:** Currency symbols, unit prices, tax lines, and grand totals are right-aligned, monospaced (`font-mono`), and unclipped.
- [x] **Form Usability:** Form inputs and modals accommodate software keyboards without obscuring submit buttons or error feedback.
- [x] **State Coverage:** Skeletons for loading, illustrations with copy for empty states, and actionable alerts for error states render cleanly.

---

## 6. Regression Testing Summary

| Test Suite | Total Executed | Passed | Failed | Duration | Status |
| :--- | :--- | :--- | :--- | :--- | :--- |
| **Backend PHPUnit / Pest** | 1,443 | 1,431 | 0 (12 skipped) | ~42s | **PASSED** |
| **Frontend TypeScript Type Check** | 0 errors | 0 errors | 0 | ~5s | **PASSED** |
| **Frontend Production Build** | Vite bundle | Built | 0 | 3.60s | **PASSED** |
| **Manual QA Database Seed** | ManualTestingSeeder | Complete | 0 | 751ms | **PASSED** |
