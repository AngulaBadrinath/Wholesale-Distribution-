# CHANGE_LOG.md — Controlled Change Management & Audit

## Wholesale Distribution Management System

**Document Version:** 1.0  
**Effective Date:** September 2026  
**Status:** Authoritative Change Registry  
**Protocol:** No client-requested business or architectural change may be implemented directly in code without passing through the formal **Change Impact Assessment Procedure** documented below.

---

## 1. Change Impact Assessment Procedure

When a new business requirement, client change request, or technical modification arrives, the developer/agent must execute this protocol:

```text
1. Record the Request (Assign CHANGE-XXX)
       ↓
2. Identify Affected Specifications (PRD, Technical Architecture, Security, Frontend)
       ↓
3. Assess Domain Impacts:
   - Order & Allocation Impact
   - Inventory & Warehouse Impact
   - Pricing & Tax Impact
   - Payment & Evidence Impact
   - Accounting & General Ledger Impact
       ↓
4. Assess Non-Functional Impacts:
   - Security, Authorization & Audit Impact
   - Database Migration & Schema Impact
   - Performance & Concurrency Impact
   - Automated Testing & QA Impact
       ↓
5. Classify Scope & Versioning:
   - Is this within V1 scope, a new build phase, or deferred to future releases?
       ↓
6. Formal Approval & Document Synchronization:
   - Update PRD, Architecture, Security, or Frontend docs first
   - Create or update tickets in Feature Ticket List
       ↓
7. Implementation & Verification
```

---

## 2. Change Register

### CHANGE-001: Baseline Specification Freeze & Project Operating System Initialization
- **Change ID:** `CHANGE-001`
- **Date:** September 4, 2026
- **Requested By:** Project Architect & Solo Developer
- **Request:** Establish the foundational five authoritative specifications (PRD, Architecture, Security & Access, Frontend Specification, Feature Tickets) and institute the comprehensive Project Operating System governance layer (`AGENTS.md`, `CLAUDE.md`, `GEMINI.md`, and all `docs/*` control files).
- **Reason:** Prevent architectural drift, eliminate unverified AI assumptions, guarantee non-destructive historical data models, and provide a deterministic, auditable engineering process for long-term development.
- **Status:** `APPROVED & COMPLETED`
- **Priority:** `P0` (Critical Prerequisite)
- **Affected PRD Requirements:** Documents 01 through 05 established as baseline v1.0.
- **Affected Architecture:** All sections of Technical Architecture established.
- **Affected Security:** Strict server-side zero-trust security architecture established.
- **Affected Frontend:** "Premium B2B Commerce × Modern SaaS ERP" design direction locked.
- **Affected Tickets:** All tickets (`TECH-FOUND-001` through `DEPLOY-005`).
- **Inventory Impact:** Formalized 4-tier stock representation (`on_hand`, `reserved`, `available`, `damaged`) and pessimistic locking.
- **Order Impact:** Locked non-destructive quantity allocation and explicit order adjustment framework.
- **Payment Impact:** Locked V1 payment methods (`CASH`, `CHEQUE`, `MONEY_ORDER`) with mandatory JPEG evidence.
- **Tax Impact:** Locked product-specific line-level tax calculation and transaction snapshots.
- **Accounting Impact:** Locked immutable double-entry general ledger journal posting.
- **Data Migration Impact:** Clean slate; zero legacy migrations.
- **Testing Impact:** Established 11 QA test suites and cross-feature edge case register (`EDGE-001` through `EDGE-025`).
- **Deployment Impact:** Baseline AWS architecture (EC2, RDS PostgreSQL, ElastiCache, S3, Route 53) specified.
- **Approved By:** Lead Architect / Project Governance
- **Implementation Status:** Verified complete; control layer active.
- **Release/Commit Reference:** Initial commit baseline.

---

### CHANGE-002: Authoritative Price Boundary Constraint Enforcement & Decimal Hardening
- **Change ID:** `CHANGE-002`
- **Date:** September 5, 2026
- **Requested By:** Principal Software Architect
- **Request:** Establish server-side price boundary enforcement (`0 <= cost_price`, `0 < min <= default <= mrp`), eliminate float casting in favor of exact 2-decimal BCMath arithmetic strings via `PriceBoundaryService`, add PostgreSQL `products_pricing_hierarchy_check` constraint, enforce resulting-state validation on updates, and create reusable domain method `validateOrderUnitPrice()` for future Phase 05 Orders.
- **Reason:** Eliminate float-precision rounding anomalies in financial data, enforce defense-in-depth database constraints, and provide reusable domain authority for order lines.
- **Status:** `APPROVED & IMPLEMENTED`
- **Priority:** `P1` (Commerce Spine Prerequisite)
- **Affected PRD Requirements:** PRD §9.1, §9.2, §10.1.
- **Affected Architecture:** Technical Architecture §18, §19, §23.
- **Affected Security:** Permission `product.price.update` strictly enforced on price mutations.
- **Affected Frontend:** Product Create/Edit forms with step="0.01" and real-time visual hierarchy feedback.
- **Affected Tickets:** `FEAT-PRICE-001`.
- **Inventory Impact:** None.
- **Order Impact:** Reusable order line unit price boundary validator (`validateOrderUnitPrice`) established for Phase 05.
- **Payment Impact:** None.
- **Tax Impact:** None.
- **Accounting Impact:** Preserves accurate, non-floating-point financial base prices.
- **Data Migration Impact:** Added PostgreSQL CHECK constraint `products_pricing_hierarchy_check` via migration `2026_09_05_000005_add_pricing_hierarchy_check_to_products_table.php`.
- **Testing Impact:** Added `PriceBoundaryServiceTest` (24 unit tests) and `ProductPriceBoundaryTest` (17 feature tests). Total suite at 492 tests passing.
- **Deployment Impact:** Verified BCMath extension availability in PHP runtime.
- **Approved By:** Principal Software Architect
- **Implementation Status:** Complete and verified.
- **Release/Commit Reference:** `FEAT-PRICE-001`.

---

## 3. Template for Future Change Requests

```markdown
### CHANGE-XXX: [Descriptive Title]
- **Change ID:** `CHANGE-XXX`
- **Date:** YYYY-MM-DD
- **Requested By:** [Client / Product Owner / Technical Lead]
- **Request:** [Concise description of the requested modification]
- **Reason:** [Business or technical justification]
- **Status:** [`REQUESTED` | `UNDER_REVIEW` | `APPROVED` | `REJECTED` | `IMPLEMENTED`]
- **Priority:** [`P0` | `P1` | `P2` | `P3`]
- **Affected PRD Requirements:** [Sections in Document 01 affected]
- **Affected Architecture:** [Sections in Document 02 affected]
- **Affected Security:** [Sections in Document 03 affected]
- **Affected Frontend:** [Screens / components in Document 04 affected]
- **Affected Tickets:** [Existing tickets modified or new tickets created]
- **Inventory Impact:** [Impact on reservations, stock states, or movements]
- **Order Impact:** [Impact on order lifecycle, item allocations, or adjustments]
- **Payment Impact:** [Impact on payment methods, evidence, or verification]
- **Tax Impact:** [Impact on line-item tax calculation or snapshots]
- **Accounting Impact:** [Impact on chart of accounts or journal entries]
- **Data Migration Impact:** [Database schema changes, data backfill requirements]
- **Testing Impact:** [New tests or regression suites required]
- **Deployment Impact:** [Infrastructure or CI/CD adjustments required]
- **Approved By:** [Client / Architect Name]
- **Implementation Status:** [Pending / In-Progress / Complete]
- **Release/Commit Reference:** [Git commit hash or release tag]
```
