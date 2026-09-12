# AI TEST IMPLEMENTATION PLAN — UNIQUE DISTRIBUTORS ERP
**Document Version:** 1.0  
**Effective Date:** 2026-09-12  
**Target Operating Model:** Solo Developer + Antigravity AI Orchestrator  
**Authoritative Source:** `docs/FULL_EXHAUSTIVE_REAL_BROWSER_AUDIT_ZERO_FALSE_PASSES.md`  
**Related Manifest:** `tests/manifest/audit-manifest.json`  
**Related Coverage Matrix:** `docs/reports/AI-AUTOMATION-TEST-COVERAGE-MATRIX-2026-09-12.md`  

---

## 1. Executive Strategy & Architectural Philosophy

The testing architecture enforces the core non-negotiable principle:  
**AI IS NOT THE TEST ORACLE. DETERMINISTIC ASSERTIONS ARE THE TEST ORACLE.**

Rather than treating browser route rendering as authoritative proof of system correctness, tests are partitioned across the cheapest, most authoritative layers:
1. **Domain Tests (`tests/domain`)**: Direct unit testing of mathematical invariants, pricing boundaries, tax profile calculations, line discount calculations, and quantity constraint invariants.
2. **HTTP / API Security Tests (`tests/api`)**: Role-based access control (RBAC), anti-IDOR checks across all specialized roles (Admin, Salesman, Accountant, Warehouse, Delivery), negative validation handling, and state mutation defenses.
3. **Database / Financial Invariant Tests (`tests/database`)**: Authoritative PostgreSQL validations: double-entry bookkeeping (`Debits = Credits`), balance sheet identity (`Assets = Liabilities + Equity`), AR subledger reconciliation, inventory non-negativity, and journal entry immutability.
4. **Playwright E2E (`tests/browser/e2e`)**: High-fidelity browser execution for user workflows, multi-step order submissions, payment verification, and role shells.
5. **Responsive Automation (`tests/browser/responsive`)**: 11-breakpoint deterministic rendering assertions (320px up to 1920px).
6. **Visual Regression (`tests/browser/visual`)**: Screenshot comparison against versioned baselines.
7. **Accessibility (`tests/browser/a11y` / Axe)**: WCAG 2.1 AA keyboard navigation (Tab/Shift+Tab/Enter/Escape), modal focus trapping, and ARIA semantics.
8. **End-to-End Financial Golden Scenario**: Multi-stage executable integration running Customer Creation -> Order -> Approval -> Fulfillment -> Delivery -> Payment -> AR -> Credit -> Refund -> General Ledger Reconciliation.

---

## 2. Test Implementation Plan by Domain

### Domain 1: Pricing & Tax Invariants
- **Checklist Coverage**: CHK-170 to CHK-210 (`PRICING_TAX_CATALOG`)
- **Test Layer**: PHPUnit / Domain Unit Test
- **Test File**: `tests/domain/PricingInvariantsTest.php`, `tests/domain/TaxCalculationTest.php`
- **Expected Assertions**:
  - `minimum_allowed_price <= actual_order_price <= mrp/list_price`
  - Tax calculation is executed per order line item and rounded authoritatively
  - Non-destructive quantities (`ordered_quantity` preserved on adjustment/cancellation)
- **Dependencies**: TaxProfile, Product, PricingRule models
- **Data Fixtures**: Seeded standard product master, wholesale price profiles, tax rate tables
- **Evidence**: PHPUnit assertion results, microsecond execution logs
- **Priority**: P0 (Financial Invariant)

---

### Domain 2: Customer Scoping & Anti-IDOR (API/HTTP)
- **Checklist Coverage**: CHK-145 to CHK-169 (`CUSTOMERS`), CHK-650 to CHK-680 (`SECURITY_AUTH`)
- **Test Layer**: HTTP / API Security Test
- **Test File**: `tests/api/CustomerScopingApiTest.php`, `tests/api/OrderAuthorizationApiTest.php`
- **Expected Assertions**:
  - Salesman A receives HTTP 403 / 404 when querying or mutating Salesman B's customer
  - Salesman cannot create orders for unscoped customers
  - Delivery partner cannot view unscoped orders or customers
  - Tampered client-side payload overrides (e.g. `unit_price`, `tax_amount`, `is_approved`) are rejected
- **Dependencies**: Sanctum auth, Bouncer/Spatie roles, Policy classes
- **Data Fixtures**: `salesman_1`, `salesman_2`, scoped customers, unscoped customers
- **Evidence**: HTTP status code, JSON response assertion, audit log verification
- **Priority**: P0 (Security / IDOR)

---

### Domain 3: General Ledger & Double-Entry Invariants
- **Checklist Coverage**: CHK-530 to CHK-565 (`ACCOUNTING_GL`)
- **Test Layer**: PostgreSQL / Database Invariant Test
- **Test File**: `tests/database/AccountingInvariantsTest.php`
- **Expected Assertions**:
  - For every posted journal entry: `SUM(debit) == SUM(credit)`
  - Trial balance net debit/credit equals zero
  - Balance sheet identity holds: `Total Assets == Total Liabilities + Total Equity`
  - Posted journal lines cannot be updated or deleted (immutability rule)
  - Journal reversals generate compensating entries with inverse polarity
- **Dependencies**: JournalEntry, JournalLine, AccountChart models
- **Data Fixtures**: Posted journal seed records, transaction fixtures
- **Evidence**: Direct DB query results, aggregate equality assertions
- **Priority**: P0 (Financial Invariant)

---

### Domain 4: Accounts Receivable & Payment Allocations
- **Checklist Coverage**: CHK-340 to CHK-380 (`ACCOUNTS_RECEIVABLE`, `PAYMENTS`)
- **Test Layer**: Database & Domain Integration Test
- **Test File**: `tests/database/AccountsReceivableInvariantsTest.php`
- **Expected Assertions**:
  - Customer AR balance equals `SUM(Invoices) - SUM(Payments) - SUM(Credits)`
  - Partial payments allocate correctly without exceeding invoice open balance
  - Cheque / Money Order payments require JPEG evidence upload
  - Payment receipt generation and AR aging buckets (Current, 1-30, 31-60, 61-90, 90+) calculate accurately
- **Dependencies**: Invoice, Payment, ARSubledger models
- **Data Fixtures**: Overdue invoices, partial payment sequences
- **Evidence**: DB line allocations, balance verification
- **Priority**: P0 (Financial Invariant)

---

### Domain 5: Inventory Reservation & Movement Non-Negativity
- **Checklist Coverage**: CHK-410 to CHK-445 (`INVENTORY_WAREHOUSE`)
- **Test Layer**: Database & Domain Invariant Test
- **Test File**: `tests/database/InventoryInvariantsTest.php`
- **Expected Assertions**:
  - Available stock reservations are atomic (`SELECT FOR UPDATE`)
  - No warehouse location stock falls below zero
  - Damaged goods movement transfers stock to quarantine location, never to available stock
- **Dependencies**: InventoryLevel, StockMovement, WarehouseLocation models
- **Data Fixtures**: Seeded inventory records with limited quantities
- **Evidence**: DB inventory balance checks, concurrency lock tests
- **Priority**: P0 (Operational Integrity)

---

### Domain 6: Playwright E2E Workflows & Role Boundaries
- **Checklist Coverage**: CHK-065 to CHK-144 (`SHELL_NAV`, `ORDERS_SALESMAN`, `DELIVERY`)
- **Test Layer**: Playwright Test (Browser E2E)
- **Test File**: `tests/browser/e2e/salesman-order-workflow.spec.ts`, `tests/browser/e2e/payment-verification.spec.ts`, `tests/browser/e2e/delivery-dispatch.spec.ts`
- **Expected Assertions**:
  - Salesman navigates catalog, selects scoped customer, builds multi-item order, reviews calculation, submits successfully
  - Accountant verifies cheque payment, inspects evidence, confirms receipt
  - Delivery driver updates route status to Out for Delivery, records proof of delivery
- **Dependencies**: Real Chrome, local backend server (`http://localhost:8000`), seeded DB
- **Data Fixtures**: Standard test credentials (Admin, Salesman, Accountant, Warehouse, Delivery)
- **Evidence**: Rendered DOM verification, visual screenshots in `artifacts/browser-audit/`
- **Priority**: P1 (Browser Workflow)

---

### Domain 7: Responsive Layout & Accessibility
- **Checklist Coverage**: CHK-204 to CHK-250 (`RESPONSIVE_VISUAL`, `ACCESSIBILITY`)
- **Test Layer**: Playwright Test (Responsive & A11y)
- **Test File**: `tests/browser/responsive/viewport-matrix.spec.ts`, `tests/browser/responsive/keyboard-a11y.spec.ts`
- **Expected Assertions**:
  - Viewport verification across 11 breakpoints: 320, 375, 390, 414, 430, 640, 768, 820, 1024, 1280, 1440, 1920px
  - Zero unintentional horizontal overflow (`scrollWidth <= clientWidth`)
  - Full keyboard operability (Tab order, Enter/Space actuation, Escape modal closure)
  - Zero critical WCAG 2.1 AA violations
- **Dependencies**: Axe-core / Playwright accessibility snapshot
- **Data Fixtures**: Responsive audit views
- **Evidence**: Responsive full-page screenshots, Axe violation reports
- **Priority**: P1 (UX / Compliance)

---

### Domain 8: End-to-End Financial Golden Scenario
- **Checklist Coverage**: CHK-800 to CHK-850 (`FINANCIAL_GOLDEN`)
- **Test Layer**: Composite Multi-Stage Integration (PHPUnit / Database / API)
- **Test File**: `tests/domain/FinancialGoldenScenarioTest.php`
- **Expected Assertions**:
  - Phase A: Customer created with $5,000 credit limit
  - Phase B: Sales order placed with 3 line items ($1,250 subtotal + tax)
  - Phase C: Order approved; inventory allocated atomically
  - Phase D: Warehouse fulfillment and delivery dispatch
  - Phase E: Invoice generated; AR debited, Revenue and Tax credited
  - Phase F: Customer payment applied; Cash debited, AR credited
  - Phase G: Return / credit note issued for 1 damaged item
  - Phase H: Final General Ledger reconciliation: Trial Balance balances, Balance Sheet balances, AR balances, zero orphan transactions
- **Dependencies**: All ERP domain services
- **Data Fixtures**: Clean isolated test database
- **Evidence**: Full audit ledger snapshot, double-entry balance proof
- **Priority**: P0 (Composite Master Invariant)

---

## 3. Execution Sequence & Phase Gates

```text
Step 1: Domain Unit Tests (Pricing, Tax, Quantity Allocations)
        ↓
Step 2: API Security & IDOR Tests (Customer Scope, Order Scope, Payment Access)
        ↓
Step 3: Database Invariant Tests (GL Balance, AR Reconciliation, Stock Non-Negativity)
        ↓
Step 4: Composite Financial Golden Scenario (Phases A through H)
        ↓
Step 5: Playwright E2E & Responsive/A11y Tests
        ↓
Step 6: Machine-Readable Aggregation (artifacts/test-results/final-results.json)
        ↓
Step 7: Verification & Checklist Coverage Reconciliation Report
```
