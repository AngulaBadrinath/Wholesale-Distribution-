# AI AUTOMATED TESTING ARCHITECTURE REPORT
**Date:** 2026-09-12  
**System:** Unique Distributors Wholesale ERP  
**Target Operating Model:** Solo Developer + Antigravity AI Orchestrator  
**Document Status:** COMPLETE & VERIFIED  

---

## 1. Core Operating Mission & Non-Negotiable Philosophy

The Unique Distributors automated testing and AI orchestration system enforces the foundational invariant:  
**AI IS NOT THE TEST ORACLE. DETERMINISTIC ASSERTIONS ARE THE TEST ORACLE.**

Antigravity / Gemini 3.8 serves as the planner, orchestrator, triage analyst, and test generator. Deterministic test runners (PHPUnit, Playwright Test, PostgreSQL constraints, and Axe accessibility audits) determine PASS / FAIL status.

---

## 2. Multi-Layer Testing Stack

| Layer | Runner / Tool | Target Scope | Authority Boundary |
|---|---|---|---|
| **Domain Layer** | PHPUnit (`tests/domain`) | Pricing bounds, line tax math, non-destructive quantity math, multi-stage financial golden scenario | In-memory domain invariants, arbitrary-precision string math (`bcadd`, `bccomp`) |
| **HTTP / API Layer** | PHPUnit HTTP (`tests/api`) | Role authentication, RBAC authorization, cross-role IDOR rejection, payload tampering protection | Server-side routing, middleware chains (`permission`, `throttle`), policy gates |
| **Database Layer** | PHPUnit DB (`tests/database`) | Double-entry balance (`Debits = Credits`), AR ledger consistency, inventory non-negativity, ledger record immutability | PostgreSQL authoritative tables, database constraints |
| **Browser / E2E Layer** | Playwright Test (`tests/browser/e2e`, `audit`) | End-to-end user workflows across all 6 specialized roles (Admin, Salesman, Accountant, Warehouse Manager, Delivery Partner, Super Admin) | Real Google Chrome (v152) over CDP `:9222`, rendered DOM state, network responses |
| **Responsive Matrix** | Playwright (`tests/browser/responsive`) | Systematic verification of 11 viewports (320px up to 1920px), no horizontal overflow (`scrollWidth <= clientWidth`) | Physical browser viewport resizing, layout rendering |
| **Accessibility Layer** | Playwright (`tests/browser/responsive/keyboard-a11y.spec.ts`) | Tab focus sequencing, visible focus rings, Escape dismiss | DOM activeElement inspection, WCAG 2.1 AA keyboard navigation |
| **Visual Regression** | Playwright (`tests/browser/visual`) | Pixel-accurate visual baseline comparison (`artifacts/visual/`) | PNG full-page snapshots against versioned baselines |
| **Interactive QA MCP** | Playwright MCP (`@playwright/mcp`) | Dedicated interactive human/AI debugging over loopback CDP (`http://127.0.0.1:9222`) | Attached to installed official Google Chrome (`artifacts/browser/qa-profile`) |
| **Continuous Integration** | GitHub Actions (`.github/workflows/ci.yml`) | Automated execution of Domain, API, Database, Unit, and Feature test suites | Ubuntu runner with PostgreSQL 18 and Redis services |

---

## 3. Directory Layout & Standards

```text
tests/
├── domain/                      # Domain-level unit & financial invariant tests
│   ├── PricingInvariantsTest.php
│   ├── TaxCalculationTest.php
│   ├── AllocationMathTest.php
│   └── FinancialGoldenScenarioTest.php
├── api/                         # HTTP & security API tests
│   ├── CustomerScopingApiTest.php
│   ├── OrderAuthorizationApiTest.php
│   └── PaymentVerificationApiTest.php
├── database/                    # Authoritative database invariant tests
│   ├── AccountingInvariantsTest.php
│   ├── AccountsReceivableInvariantsTest.php
│   └── InventoryInvariantsTest.php
├── browser/                     # Playwright automated browser test suite
│   ├── audit/                   # 13 audit specifications (28 test suites)
│   ├── e2e/                     # End-to-end user workflow specifications
│   ├── responsive/              # 11-breakpoint responsive & keyboard a11y tests
│   ├── security/                # Anti-IDOR & role boundary browser tests
│   ├── visual/                  # Visual baseline snapshot capture tests
│   ├── helpers/                 # Authentication & diagnostics collectors
│   ├── interactive/             # Chrome launcher & physical sync controllers
│   ├── resolver.ts              # Local Google Chrome executable locator
│   └── viewports.ts             # 11 standard viewport breakpoint definitions
├── support/                     # Common base test classes
│   ├── DomainTestCase.php
│   ├── ApiTestCase.php
│   └── DatabaseTestCase.php
└── manifest/                    # Machine-readable test manifests
    └── audit-manifest.json      # 999 enumerated items mapped to test layers
```

---

## 4. Single Machine-Readable Result Pipeline

All test execution layers output deterministic results aggregated by `scripts/aggregate-test-results.js` into:  
`artifacts/test-results/final-results.json`

Every checklist item record contains:
- `checklistId` (e.g. `CHK-001` through `CHK-999`)
- `domain`
- `testType`
- `testFile`
- `testCase`
- `expected`
- `actual`
- `evidence`
- `status` (`PASS`, `PARTIAL`, `NOT_TESTED`, `NOT_APPLICABLE`, `FAIL`, `BLOCKED`)
