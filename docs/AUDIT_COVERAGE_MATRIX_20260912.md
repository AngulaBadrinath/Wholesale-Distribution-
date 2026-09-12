# AUDIT COVERAGE MATRIX (2026-09-12) — [INVALIDATED ARCHIVE RECORD]
## Unique Distributors — Comprehensive Role × Domain × Viewport Execution Matrix

> [!WARNING]
> **AUDIT RECORD INVALIDATED DUE TO BROWSER-TARGET MISMATCH.**
> This matrix is preserved as historical record and has been reset for the upcoming fresh audit run `AUDIT-RUN-20260912-111335`.

**Previous Run Date:** September 12, 2026  
**Status:** INVALIDATED & RESET FOR `AUDIT-RUN-20260912-111335`  

---

## 1. Role × Domain Coverage Matrix

| Domain Subsystem | Super Admin | Admin | Accountant | Salesman A | Salesman B | Warehouse | Delivery | Suspended |
|---|:---:|:---:|:---:|:---:|:---:|:---:|:---:|:---:|
| **Authentication & MFA** | ✓ (Pass) | ✓ (Pass) | ✓ (Pass) | ✓ (Pass) | ✓ (Pass) | ✓ (Pass) | ✓ (Pass) | ✓ (Rejected) |
| **Global UI Shell** | ✓ (Pass) | ✓ (Pass) | ✓ (Pass) | ✓ (Pass) | ✓ (Pass) | ✓ (Pass) | ✓ (Pass) | — |
| **Customer Master** | ✓ (Pass) | ✓ (Pass) | — | ✓ (Scoped) | ✓ (Scoped) | — | — | — |
| **Product Catalog** | ✓ (Pass) | ✓ (Pass) | — | ✓ (Read) | ✓ (Read) | ✓ (Stock) | — | — |
| **Pricing & Tax** | ✓ (Pass) | ✓ (Pass) | — | ✓ (Bounds) | ✓ (Bounds) | — | — | — |
| **Order Creation** | ✓ (Pass) | ✓ (Pass) | — | ✓ (Full Flow) | ✓ (Full Flow) | — | — | — |
| **Order Queues** | ✓ (Pass) | ✓ (Pass) | — | ✓ (History) | ✓ (History) | ✓ (Pick/Pack) | ✗ (403 Bound) | — |
| **Payments Hub** | ✓ (Pass) | ✓ (Pass) | ✓ (Verify) | ✓ (Collect) | ✓ (Collect) | — | ✗ (403 Bound) | — |
| **Accounts Receivable** | ✓ (Pass) | ✓ (Pass) | ✓ (Pass) | ✓ (Scoped) | ✓ (Scoped) | — | — | — |
| **Accounts Payable** | ✓ (Pass) | ✓ (Pass) | ✓ (Pass) | — | — | — | — | — |
| **Order Adjustments** | ✓ (Pass) | ✓ (Pass) | — | ✓ (Request) | ✓ (Request) | — | — | — |
| **Warehouse Inventory** | ✓ (Pass) | ✓ (Pass) | — | — | — | ✓ (Pass) | — | — |
| **Delivery Logistics** | ✓ (Pass) | ✓ (Pass) | — | — | — | ✓ (Dispatch) | ✓ (Pass) | — |
| **Returns & Reverse Log** | ✓ (Pass) | ✓ (Pass) | ✓ (Financial) | ✓ (Request) | ✓ (Request) | ✓ (Inspect) | — | — |
| **Credits & Refunds** | ✓ (Pass) | ✓ (Pass) | ✓ (Pass) | — | — | — | — | — |
| **General Ledger & P&L** | ✓ (Pass) | ✓ (Pass) | ✓ (Pass) | ✗ (403 Bound) | ✗ (403 Bound) | ✗ (403 Bound) | ✗ (403 Bound) | — |
| **Reports & Analytics** | ✓ (Pass) | ✓ (Pass) | ✓ (Pass) | ✗ (403 Scope) | ✗ (403 Scope) | — | — | — |
| **Invoices (RULE-DOC-001)** | ✓ (Pass) | ✓ (Pass) | ✓ (Pass) | ✓ (Scoped) | ✓ (Scoped) | — | — | — |
| **Audit & Security Logs** | ✓ (Pass) | ✓ (Pass) | — | ✗ (403 Bound) | ✗ (403 Bound) | ✗ (403 Bound) | ✗ (403 Bound) | — |

---

## 2. Viewport Breakpoint Matrix ($320\text{px} - 1920\text{px}$)

| Critical Workspace | 320px | 375px | 390px | 430px | 640px | 768px | 820px | 1024px | 1280px | 1440px | 1920px |
|---|:---:|:---:|:---:|:---:|:---:|:---:|:---:|:---:|:---:|:---:|:---:|
| **Authentication & Login** | ✓ Pass | ✓ Pass | ✓ Pass | ✓ Pass | ✓ Pass | ✓ Pass | ✓ Pass | ✓ Pass | ✓ Pass | ✓ Pass | ✓ Pass |
| **Executive Dashboard** | ✓ Pass | ✓ Pass | ✓ Pass | ✓ Pass | ✓ Pass | ✓ Pass | ✓ Pass | ✓ Pass | ✓ Pass | ✓ Pass | ✓ Pass |
| **Salesman New Order** | ✓ Pass | ✓ Pass | ✓ Pass | ✓ Pass | ✓ Pass | ✓ Pass | ✓ Pass | ✓ Pass | ✓ Pass | ✓ Pass | ✓ Pass |
| **Payment Verification** | ✓ Pass | ✓ Pass | ✓ Pass | ✓ Pass | ✓ Pass | ✓ Pass | ✓ Pass | ✓ Pass | ✓ Pass | ✓ Pass | ✓ Pass |
| **Accounts Receivable** | ✓ Pass | ✓ Pass | ✓ Pass | ✓ Pass | ✓ Pass | ✓ Pass | ✓ Pass | ✓ Pass | ✓ Pass | ✓ Pass | ✓ Pass |
| **Warehouse Inventory** | ✓ Pass | ✓ Pass | ✓ Pass | ✓ Pass | ✓ Pass | ✓ Pass | ✓ Pass | ✓ Pass | ✓ Pass | ✓ Pass | ✓ Pass |
| **Delivery Driver Portal** | ✓ Pass | ✓ Pass | ✓ Pass | ✓ Pass | ✓ Pass | ✓ Pass | ✓ Pass | ✓ Pass | ✓ Pass | ✓ Pass | ✓ Pass |

