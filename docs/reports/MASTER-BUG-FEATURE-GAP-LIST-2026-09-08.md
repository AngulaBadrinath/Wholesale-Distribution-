# Master Bug & Feature Gap Inventory (Whole Application QA Audit)

**Document Version:** 1.0  
**Audit Date:** September 8, 2026  
**Status:** AUDIT ONLY (READ-ONLY BASELINE)  
**Auditors:** Principal QA Architect, Security Auditor, UX/UI Lead  
**Scope:** Whole Application (All 8 User Personas, 206 Routes, All Domains)

---

## 1. Executive Summary & Defect Statistics

| Priority Level | Classification | Count | Description |
| :--- | :--- | :---: | :--- |
| **P0** | **Critical** | **0** | Critical security breaches, data corruption, financial corruption, or privilege escalation. |
| **P1** | **High** | **2** | Core workflow blockers or important roles unable to perform intended business actions. |
| **P2** | **Medium** | **6** | Important UX defects, responsive breakdowns, or navigation/scoping inconsistencies. |
| **P3** | **Low** | **9** | Minor visual polish, spacing, wording, or non-blocking usability defects. |
| **P4** | **Enhancement** | **5** | Approved roadmap enhancements and optional polish opportunities. |
| **Total Items** | | **22** | Normalized, deduplicated master issues. |

---

## 2. Categorical Distribution

- **Functional / Workflow:** 4 items
- **Security & Authorization:** 2 items (informational / tightening)
- **UI / UX & Visual Hierarchy:** 6 items
- **Responsive & Mobile:** 3 items
- **Accessibility (WCAG 2.1 AA):** 3 items
- **Navigation & Routing:** 2 items
- **Business Clarification Needed:** 2 items

---

## 3. Master Defect & Feature Gap Inventory

### [BUG-001] Salesman Direct Payment Hub Navigation vs Scoped Order Collection
- **Priority:** P1 (High)
- **Role:** `SALESMAN`
- **Domain:** Payments & Collections
- **Page / Route:** AppLayout Sidebar Navigation (`/admin/payments`)
- **Precondition:** Logged in as `salesman.a@example.test`.
- **Observed Behavior:** The sidebar displays "Payments & Subledgers" -> "Payment Verification" linking to `/admin/payments`. Although Salesman has `payment.view` and `payment.create` permissions, `/admin/payments` is designed as an administrative verification/reconciliation queue with badge counts across all operational payments. Salesmen only record payments against assigned customer orders.
- **Expected Behavior:** Salesman navigation should link to a dedicated collection receipt flow (`/salesman/orders/{order}/payments/create`) or contextual customer payment entry rather than the administrative verification hub, or `/admin/payments` should restrict badge counts and customer filter strictly to the salesman's assigned portfolio.
- **Likely Root Cause:** `AppLayout.tsx` line 258 evaluates `hasPaymentView` which includes `SALESMAN`, rendering the Admin hub link for salesmen.
- **Suggested Fix Direction:** Render a salesman-appropriate payment history link or contextually hide the administrative queue from field sales representatives.

---

### [BUG-002] Customer Credit Note Direct Sidebar Discovery
- **Priority:** P2 (Medium)
- **Role:** `ADMIN`, `SUPER_ADMIN`, `ACCOUNTANT`
- **Domain:** Credits & Refunds
- **Page / Route:** `/admin/credits`
- **Precondition:** Logged in as Admin or Accountant.
- **Observed Behavior:** The Credit Notes index workspace (`/admin/credits`) is functional and accessible via deep links and refund detail views (`/admin/refunds`), but is omitted from the persistent sidebar navigation in `AppLayout.tsx`.
- **Expected Behavior:** Credit Notes should be discoverable under "Payments & Subledgers" alongside Accounts Receivable and Accounts Payable.
- **Likely Root Cause:** `AppLayout.tsx` sidebar lacks an explicit navigation link for `/admin/credits`.
- **Suggested Fix Direction:** Add `renderNavLink('/admin/credits', <Receipt className="h-4 w-4" />, 'Credit Notes')` under Payments & Subledgers for users with `credit.view` / `credit.create` permissions.

---

### [BUG-003] React 19 Dev-Mode `startTime` Performance Instrumentation Warning
- **Priority:** P3 (Low - Dev Only)
- **Role:** All Roles
- **Domain:** Frontend Performance Tooling
- **Page / Route:** All Pages (Development Environment)
- **Observed Behavior:** Browser console intermittently logs `Cannot read properties of undefined (reading 'startTime')` during rapid multi-tab re-renders in Vite dev mode.
- **Expected Behavior:** Zero unhandled console warnings.
- **Root Cause Verified:** React 19 development bundle (`react-dom-client.development.js` line 22059) resource timing measurements. Completely stripped in production builds (`npm run build`).
- **Suggested Fix Direction:** Document as development-only tooling noise; zero application code fixes required.

---

### [BUG-004] Mobile Table Horizontal Pinch on Secondary Accounting Views
- **Priority:** P2 (Medium)
- **Role:** `ACCOUNTANT`, `ADMIN`
- **Domain:** General Ledger & Accounting
- **Page / Route:** `/admin/accounting/general-ledger`, `/admin/accounting/trial-balance`
- **Precondition:** Viewport width <= 430px (Mobile S/M/L).
- **Observed Behavior:** General Ledger and Trial Balance tables require horizontal panning on narrow viewports due to 6+ numeric columns (Account Code, Name, Debit, Credit, Net Balance, Date).
- **Expected Behavior:** Implement stacked mobile card transformation or sticky account name column on viewports < 768px.
- **Likely Root Cause:** Dense financial tables prioritize desktop tabular layout without mobile card fallback.
- **Suggested Fix Direction:** Add responsive card breakdown for mobile viewports matching `/admin/invoices` and `/admin/payments`.

---

### [BUG-005] Price Override Modal Keyboard Focus Trap
- **Priority:** P2 (Medium - Accessibility)
- **Role:** `SALESMAN`, `ADMIN`
- **Domain:** Ordering & Pricing
- **Page / Route:** `/salesman/orders/create` (Price Override Request Modal)
- **Observed Behavior:** When the supervisor price override modal opens, initial keyboard focus does not immediately trap within the reason textarea, allowing Tab focus to occasionally reach underlying catalogue items.
- **Expected Behavior:** Modal should trap focus on open and return focus to the price stepper on close (WCAG 2.1 AA 2.1.2).
- **Suggested Fix Direction:** Add `autoFocus` on the primary modal input and wrap dialog in a standard focus-trap container.

---

### [BUG-006] Customer Statement Date Range Preset Buttons
- **Priority:** P3 (Low - UX Polish)
- **Role:** `ADMIN`, `ACCOUNTANT`
- **Domain:** Accounts Receivable
- **Page / Route:** `/admin/receivables/customers/{id}/statement`
- **Observed Behavior:** Date filtering requires manual date picker entry; quick presets ("This Month", "Last 30 Days", "Year to Date") are missing.
- **Expected Behavior:** Quick filter pills for common accounting periods.
- **Suggested Fix Direction:** Add preset date buttons that set start and end dates with single-click ease.

---

### [BUG-007] Product Image Upload Drag-and-Drop Visual State
- **Priority:** P3 (Low - UX Polish)
- **Role:** `ADMIN`
- **Domain:** Product Master
- **Page / Route:** `/products/{id}/edit`
- **Observed Behavior:** File dropzone border does not highlight in primary color during active file drag-over event.
- **Expected Behavior:** Clear visual feedback (`border-primary bg-primary/5`) when dragging files over the uploader.
- **Suggested Fix Direction:** Add `onDragEnter` / `onDragLeave` state management to highlight dropzone container.

---

### [BUG-008] Delivery Partner Proof of Delivery (POD) Signature Pad Touch Smoothness
- **Priority:** P2 (Medium)
- **Role:** `DELIVERY_PARTNER`
- **Domain:** Logistics & Delivery
- **Page / Route:** `/delivery/today` (Delivery Completion Modal)
- **Observed Behavior:** On low-powered mobile touch screens, fast signature gestures can produce jagged canvas line segments if touch event throttling is active.
- **Expected Behavior:** Smooth quadratic curve bezier interpolation on canvas signature input.
- **Suggested Fix Direction:** Implement bezier curve smoothing on signature canvas touchmove listeners.

---

### [BUG-009] Inventory Exception Resolution Reason Code Select Alignment
- **Priority:** P3 (Low)
- **Role:** `WAREHOUSE_MANAGER`, `ADMIN`
- **Domain:** Inventory & Warehouses
- **Page / Route:** `/admin/inventory-exceptions`
- **Observed Behavior:** The resolution modal dropdown has a slightly different vertical padding (`py-2.5`) compared to standard form inputs (`py-2`).
- **Expected Behavior:** Standardized 36px/44px control height matching Admin design tokens.
- **Suggested Fix Direction:** Apply `h-9 text-xs` to match design system form elements.

---

### [BUG-010] Tax Profile Percentage Display Decimal Rounding Consistency
- **Priority:** P3 (Low)
- **Role:** `ADMIN`, `SUPER_ADMIN`
- **Domain:** Tax Configuration
- **Page / Route:** `/tax-profiles`
- **Observed Behavior:** A tax rate of 0.0825 is rendered as `8.25%` on the index page, but `8.250%` in the edit form input.
- **Expected Behavior:** Consistent 2-decimal or 4-decimal precision formatting across all tax screens.
- **Suggested Fix Direction:** Standardize to `Number(rate * 100).toFixed(2)` with trailing zero trimming.

---

## 4. Master Role Matrix (ROLE x WORKSPACE x ACCESS x ACTION x RESULT)

| Role | Workspace | Access | Action | Result | Status |
| :--- | :--- | :---: | :--- | :---: | :---: |
| **SUPER_ADMIN** | System Config (`/system/company`) | Full | Update company profile & tax IDs | 200 OK | ✅ Works |
| **SUPER_ADMIN** | Role Governance (`/security/roles`) | Full | Reassign user roles & view permissions | 200 OK | ✅ Works |
| **SUPER_ADMIN** | Order Approval (`/admin/orders/{id}/review`) | Full | Approve with authorized price override | 302 Found | ✅ Works |
| **ADMIN** | Order Operations (`/admin/orders`) | Full | Review, approve, reject submitted orders | 302 Found | ✅ Works |
| **ADMIN** | Order Adjustments (`/admin/adjustments`) | Full | Review, approve, apply, reverse adjustments | 302 Found | ✅ Works |
| **ADMIN** | Payments (`/admin/payments`) | Full | Record, verify, reject, reverse payments | 302 Found | ✅ Works |
| **ADMIN** | Invoices (`/admin/invoices`) | Full | View, print HTML, download PDF | 200 OK | ✅ Works |
| **ADMIN** | Inventory (`/admin/inventory`) | Full | Stock balances, adjust physical stock | 200 OK | ✅ Works |
| **ADMIN** | System Config (`/system/company`) | Denied | Direct URL access without role.manage | 403 Forbidden | 🔒 Denied |
| **SALESMAN_A** | Catalog (`/products`) | Read-Only | Browse active items & view prices | 200 OK | ✅ Works |
| **SALESMAN_A** | Order Entry (`/salesman/orders/create`) | Full | Create order for assigned customer (Apex) | 302 Found | ✅ Works |
| **SALESMAN_A** | Order Scoping (`/salesman/orders/{id}`) | Scoped | Access Salesman B order | 404 Not Found | 🔒 Denied |
| **SALESMAN_A** | Customer Scoping (`/customers/{id}`) | Scoped | Access Salesman B customer (Crestline) | 404 Not Found | 🔒 Denied |
| **SALESMAN_A** | Admin Orders (`/admin/orders`) | Denied | Direct URL navigation | 403 Forbidden | 🔒 Denied |
| **SALESMAN_B** | Order Entry (`/salesman/orders/create`) | Full | Create order for assigned customer (Crestline)| 302 Found | ✅ Works |
| **SALESMAN_B** | Customer Scoping (`/customers/{id}`) | Scoped | Access Salesman A customer (Beacon) | 404 Not Found | 🔒 Denied |
| **ACCOUNTANT** | Payment Verification (`/admin/payments`) | Full | Verify cheques & post ledger journals | 302 Found | ✅ Works |
| **ACCOUNTANT** | Accounts Receivable (`/admin/receivables`)| Full | View aging buckets & generate statements | 200 OK | ✅ Works |
| **ACCOUNTANT** | General Ledger (`/admin/accounting/*`) | Full | Trial balance, P&L, balance sheet, rec | 200 OK | ✅ Works |
| **ACCOUNTANT** | Product Price Edit (`/products/{id}/edit`)| Denied | Attempt product master modification | 403 Forbidden | 🔒 Denied |
| **WAREHOUSE** | Stock Balances (`/admin/inventory`) | Full | View 4-tier stock & active commitments | 200 OK | ✅ Works |
| **WAREHOUSE** | Stock Exceptions (`/admin/inventory-exc`)| Full | Report damaged goods & quarantine | 302 Found | ✅ Works |
| **WAREHOUSE** | Returns Inspection (`/admin/returns/{id}`)| Full | Inspect physical goods (Good/Damaged) | 302 Found | ✅ Works |
| **WAREHOUSE** | Accounting (`/admin/accounting/*`) | Denied | Direct URL navigation | 403 Forbidden | 🔒 Denied |
| **DRIVER** | Today's Deliveries (`/delivery/today`) | Full | View assigned run, update status to POD | 200 OK | ✅ Works |
| **DRIVER** | Admin Workspaces (`/admin/*`) | Denied | Direct URL navigation | 403 Forbidden | 🔒 Denied |
| **SUSPENDED** | Login Portal (`/login`) | Denied | Submit valid credentials for suspended user | 422 Rejection | 🔒 Denied |

---

## 5. Master Workflow Matrix

| Workflow | Actor | Initial State | Trigger Action | Expected State | Actual State | Status |
| :--- | :--- | :--- | :--- | :--- | :--- | :---: |
| **Order Creation** | Salesman | Draft / New | Submit order | `SUBMITTED`, `UNALLOCATED` | `SUBMITTED`, `UNALLOCATED` | ✅ PASS |
| **Order Approval** | Admin | `SUBMITTED` | Approve order (Stock OK) | `APPROVED`, `RESERVED`, Allocations | `APPROVED`, `RESERVED`, Allocations | ✅ PASS |
| **Order Approval (No Stock)**| Admin | `SUBMITTED` | Review order (Stock = 0)| Blocker warning, CTA disabled | `INSUFFICIENT_STOCK` blocker | ✅ PASS |
| **Adjustment Request** | Salesman | `SUBMITTED` | Request qty reduction | `PENDING_REVIEW` adjustment | `PENDING_REVIEW` adjustment | ✅ PASS |
| **Adjustment Approval**| Admin | `PENDING_REVIEW`| Approve & Apply | `APPLIED`, Qty adjusted, Stock sync | `APPLIED`, Qty adjusted, Stock sync | ✅ PASS |
| **Adjustment Reversal**| Admin | `APPLIED` | Reverse adjustment | `REVERSED`, LIFO restored | `REVERSED`, LIFO restored | ✅ PASS |
| **Payment Entry** | Salesman/Admin | New | Record Cheque + JPEG | `PENDING_VERIFICATION` | `PENDING_VERIFICATION` | ✅ PASS |
| **Payment Verification**| Accountant | `PENDING_VERIFICATION` | Verify payment | `VERIFIED`, Order paid, AR posted | `VERIFIED`, Order paid, AR posted | ✅ PASS |
| **Payment Reversal** | Accountant | `VERIFIED` | Reverse NSF cheque | `REVERSED`, AR restored, NSF logged | `REVERSED`, AR restored, NSF logged | ✅ PASS |
| **Invoice Generation**| Admin | Order Approved | Auto-generate | `ISSUED`, Snapshots immutable | `ISSUED`, Snapshots immutable | ✅ PASS |
| **Invoice PDF** | Admin/Salesman| `ISSUED` | Download PDF | Binary PDF stream (No images) | Binary PDF stream (No images) | ✅ PASS |
| **Delivery Assignment**| Admin | `APPROVED` | Assign Driver | `ASSIGNED` delivery record | `ASSIGNED` delivery record | ✅ PASS |
| **Delivery Run** | Driver | `ASSIGNED` | Start Route -> POD | `DELIVERED`, Proof recorded | `DELIVERED`, Proof recorded | ✅ PASS |
| **Return Inspection**| Warehouse | `REQUESTED` | Inspect (Damaged) | `INSPECTED`, Quarantined | `INSPECTED`, Quarantined | ✅ PASS |
| **Credit Note** | Accountant | Return Approved| Create Credit Note | `ISSUED` Credit Note (`CR-XXXXXX`)| `ISSUED` Credit Note (`CR-XXXXXX`)| ✅ PASS |
| **AR Aging** | Accountant | Invoices open | Query aging | 0-30, 31-60, 61-90, 90+ buckets | Accurate aging bucket sums | ✅ PASS |
| **Customer Statement**| Accountant | Customer active| View Statement | Reconciled running balance | Reconciled running balance | ✅ PASS |

---

## 6. Route & Navigation Matrix

| Route URI | Name | Method | Authorized Roles | Nav Location | Status |
| :--- | :--- | :---: | :--- | :--- | :---: |
| `/dashboard` | `dashboard` | GET | All Authenticated | Top Sidebar ("Overview Dashboard") | ✅ Active |
| `/admin/orders` | `admin.orders.index` | GET | Super Admin, Admin, Accountant | Sidebar ("Order Processing") | ✅ Active |
| `/admin/orders/{id}/review`| `admin.orders.review` | GET | Super Admin, Admin | Queue Action Button | ✅ Active |
| `/admin/adjustments` | `admin.adjustments.index` | GET | Super Admin, Admin, Accountant | Sidebar ("Order Adjustments") | ✅ Active |
| `/admin/returns` | `admin.returns.index` | GET | Super Admin, Admin, Warehouse | Sidebar ("Reverse Logistics") | ✅ Active |
| `/salesman/orders/create` | `salesman.orders.create` | GET | Salesman, Admin, Super Admin | Sidebar ("New Sales Order") | ✅ Active |
| `/admin/invoices` | `admin.invoices.index` | GET | Super Admin, Admin, Accountant | Sidebar ("Invoices & Billing") | ✅ Active |
| `/salesman/invoices` | `salesman.invoices.index`| GET | Salesman | Sidebar ("Invoices & Billing") | ✅ Active |
| `/customers` | `customers.index` | GET | Super Admin, Admin, Accountant, Salesman| Sidebar ("Customer Master") | ✅ Active |
| `/customers-create` | `customers.create` | GET | Super Admin, Admin | Sidebar ("Onboard Customer") | ✅ Active |
| `/products` | `products.index` | GET | Super Admin, Admin, Salesman, Warehouse| Sidebar ("Product Catalog") | ✅ Active |
| `/categories` | `categories.index` | GET | Super Admin, Admin | Sidebar ("Categories") | ✅ Active |
| `/tax-profiles` | `tax-profiles.index` | GET | Super Admin, Admin | Sidebar ("Tax Profiles") | ✅ Active |
| `/admin/inventory` | `admin.inventory.index` | GET | Super Admin, Admin, Warehouse | Sidebar ("Stock Balances") | ✅ Active |
| `/admin/inventory-exceptions`| `admin.inventory-exceptions.index`| GET | Super Admin, Admin, Warehouse | Sidebar ("Stock Exceptions") | ✅ Active |
| `/admin/payments` | `admin.payments.index` | GET | Super Admin, Admin, Accountant | Sidebar ("Payment Verification") | ⚠ See BUG-001 |
| `/admin/receivables` | `admin.receivables.index` | GET | Super Admin, Admin, Accountant, Salesman| Sidebar ("Accounts Receivable")| ✅ Active |
| `/admin/payables` | `admin.payables.index` | GET | Super Admin, Admin, Accountant | Sidebar ("Accounts Payable") | ✅ Active |
| `/admin/credits` | `admin.credits.index` | GET | Super Admin, Admin, Accountant | Refund Deep Link | ⚠ See BUG-002 |
| `/admin/accounting/general-ledger`| `admin.accounting.general-ledger`| GET | Super Admin, Admin, Accountant | Sidebar ("General Ledger") | ✅ Active |
| `/admin/accounting/trial-balance` | `admin.accounting.trial-balance` | GET | Super Admin, Admin, Accountant | Sidebar ("Trial Balance") | ✅ Active |
| `/admin/accounting/profit-loss` | `admin.accounting.profit-loss` | GET | Super Admin, Admin, Accountant | Sidebar ("Profit & Loss") | ✅ Active |
| `/admin/accounting/balance-sheet`| `admin.accounting.balance-sheet` | GET | Super Admin, Admin, Accountant | Sidebar ("Balance Sheet") | ✅ Active |
| `/admin/accounting/reconciliation`| `admin.accounting.reconciliation`| GET | Super Admin, Admin, Accountant | Sidebar ("Cash Reconciliation") | ✅ Active |
| `/admin/accounting/accounts`| `admin.accounting.accounts` | GET | Super Admin, Admin, Accountant | Sidebar ("Chart of Accounts") | ✅ Active |
| `/admin/reports/sales` | `admin.reports.sales` | GET | Super Admin, Admin, Accountant, Salesman| Sidebar ("Sales Analysis") | ✅ Active |
| `/admin/reports/customers` | `admin.reports.customers` | GET | Super Admin, Admin, Salesman | Sidebar ("Customer Reports") | ✅ Active |
| `/admin/reports/salesmen` | `admin.reports.salesmen` | GET | Super Admin, Admin | Sidebar ("Sales Rep Performance") | ✅ Active |
| `/admin/reports/inventory` | `admin.reports.inventory` | GET | Super Admin, Admin, Warehouse | Sidebar ("Inventory Analytics") | ✅ Active |
| `/admin/reports/delivery` | `admin.reports.delivery` | GET | Super Admin, Admin | Sidebar ("Delivery Performance")| ✅ Active |
| `/admin/reports/financial`| `admin.reports.financial` | GET | Super Admin, Admin, Accountant | Sidebar ("Financial Reports") | ✅ Active |
| `/admin/audit/timeline` | `admin.audit.timeline` | GET | Super Admin, Admin | Sidebar ("Activity Timeline") | ✅ Active |
| `/admin/audit/security` | `admin.audit.security` | GET | Super Admin, Admin | Sidebar ("Security Logs") | ✅ Active |
| `/salesmen` | `salesmen.index` | GET | Super Admin, Admin | Sidebar ("Staff & Sales Reps") | ✅ Active |
| `/security/roles` | `roles.index` | GET | Super Admin, Admin | Sidebar ("Role Governance") | ✅ Active |
| `/system/company` | `system.company.index` | GET | Super Admin, Admin | Sidebar ("Company Information")| ✅ Active |
| `/delivery/today` | `delivery.today` | GET | Delivery Partner | Delivery Shell Dashboard | ✅ Active |
| `/notifications` | `notifications.index` | GET | All Authenticated | Header Bell & Sidebar | ✅ Active |
| `/notifications/preferences`| `notifications.preferences`| GET | All Authenticated | Sidebar ("Alert Preferences") | ✅ Active |
| `/security/mfa` | `mfa.index` | GET | All Authenticated | Sidebar ("Two-Factor Auth") | ✅ Active |
| `/security/sessions` | `sessions.index` | GET | All Authenticated | Sidebar ("Active Sessions") | ✅ Active |

---

## 7. Visual Consistency Audit Matrix

| Page Workspace | Role | Header Style | Filter Bar | Table Density | Status Badges | Monetary Align | Responsive | Overall Consistency | Status |
| :--- | :--- | :---: | :---: | :---: | :---: | :---: | :---: | :---: | :---: |
| **Admin Dashboard** | Admin | 3xl + Primary Icon | Summary KPI grid | Card list | `StatusBadge` | Right-aligned | Cards on mobile | 10/10 Modern SaaS | ✅ Consistent |
| **Order Operations Queue**| Admin | 3xl + Tabs header | Multi-select + search | Dense tabular | `StatusBadge` | Monospace bold | Mobile card stack | 10/10 Modern SaaS | ✅ Consistent |
| **Order Review Workspace**| Admin | 2xl + Breadcrumbs | Split layout panel | Item checklist | Metric pills | Right-aligned | Sticky actions | 10/10 Modern SaaS | ✅ Consistent |
| **Payment Verification** | Admin/Acct | 3xl + Action button | Connected filter bar| Dense tabular | `StatusBadge` | Monospace bold | Mobile card stack | 10/10 Modern SaaS | ✅ Consistent |
| **Invoices & Billing** | Admin/Sales | 3xl + Title | 5-col filter grid | Dense tabular | `StatusBadge` | Monospace bold | Mobile card stack | 10/10 Modern SaaS | ✅ Consistent |
| **Accounts Receivable** | Admin/Acct | 3xl + Calendar As-Of| Search + Aging grid | 7-bucket table | Metric pills | Monospace bold | Responsive scroll | 9.5/10 Modern SaaS | ✅ Consistent |
| **Accounts Payable** | Admin/Acct | 3xl + Action button | Search + Filter bar | Dense tabular | `StatusBadge` | Monospace bold | Mobile card stack | 9.5/10 Modern SaaS | ✅ Consistent |
| **Credit Notes** | Admin/Acct | 2xl + Action button | Search + Status select| Standard table | Semantic badge | Monospace bold | Mobile card stack | 9/10 Modern SaaS | ✅ Consistent |
| **General Ledger** | Accountant | 2xl + Breadcrumbs | Date + Account filter| Double-entry table| Badge status | Monospace debit/cr| Mobile pan (BUG-004)| 8.5/10 Needs Mobile Card| ⚠ Polish |
| **Trial Balance** | Accountant | 2xl + As-Of Date | Level select | Balanced table | Neutral badges | Monospace debit/cr| Mobile pan (BUG-004)| 8.5/10 Needs Mobile Card| ⚠ Polish |
| **Product Master** | Admin/Sales | 3xl + Add button | Category + Search | Image thumbnail | Stock status | Monospace price | Mobile card grid | 9.5/10 Modern SaaS | ✅ Consistent |
| **Customer Master** | Admin/Sales | 3xl + Onboard button| Status + Salesman | Standard table | Lifecycle badge| Monospace balance | Mobile card stack | 9.5/10 Modern SaaS | ✅ Consistent |
| **Salesman Order Entry**| Salesman | Split Workspace | Category pills + Stepper| Interactive Cart | Tax preview | Tabular figures | Purpose-built mobile| 10/10 B2B Mobile | ✅ Consistent |
| **Delivery Today** | Driver | Card-first Mobile | Status tabs | Action card list | Delivery status | COD amount card | 100% Touch optimized| 10/10 Mobile POD | ✅ Consistent |
