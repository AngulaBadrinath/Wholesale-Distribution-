# DEVELOPMENT_FLOW.md — Canonical Execution Workflow

## Wholesale Distribution Management System

**Document Version:** 1.0  
**Effective Date:** September 2026  
**Audience:** Solo Developer & AI Coding Agents (Antigravity / Claude / Gemini)  
**Standard:** Production-Grade Execution Protocol

---

## 1. Master Ticket Lifecycle Workflow

Every single ticket in this repository—regardless of size or domain—must pass through this 19-step execution lifecycle in exact sequence:

```text
 1. TICKET SELECTION
       ↓
 2. DOCUMENT REVIEW
       ↓
 3. REPOSITORY INSPECTION
       ↓
 4. PREREQUISITE & DEPENDENCY CHECK
       ↓
 5. IMPLEMENTATION PLAN
       ↓
 6. IMPLEMENTATION
       ↓
 7. TARGETED AUTOMATED TESTING
       ↓
 8. REGRESSION TESTING
       ↓
 9. SECURITY & AUTHORIZATION CHECK
       ↓
10. RESPONSIVE LAYOUT VERIFICATION
       ↓
11. ACCESSIBILITY VERIFICATION
       ↓
12. DEAD CODE & DUPLICATE CHECK
       ↓
13. DEPENDENCY & PACKAGE HYGIENE CHECK
       ↓
14. GIT DIFF LINE-BY-LINE REVIEW
       ↓
15. DOCUMENTATION & TRACEABILITY UPDATE
       ↓
16. STATUS & CHECKLIST UPDATE
       ↓
17. GIT COMMIT
       ↓
18. GIT PUSH / CI VERIFICATION
       ↓
19. NEXT TICKET SELECTION
```

---

## 2. Step-by-Step Execution Protocol

### Step 1: Ticket Selection
- **What to inspect:** [docs/BUILD_PHASES.md](file:///f:/Wholesale%20Distribution%20Management%20System/docs/BUILD_PHASES.md), [docs/PROJECT_STATUS.md](file:///f:/Wholesale%20Distribution%20Management%20System/docs/PROJECT_STATUS.md), and [docs/Wholesale_Distribution_Management_System_Feature_Ticket_List.md](file:///f:/Wholesale%20Distribution%20Management%20System/docs/Wholesale_Distribution_Management_System_Feature_Ticket_List.md).
- **What to verify:** Ensure the selected ticket belongs to the current active Phase and satisfies the **Definition of Ready (DoR)**.
- **Failure cause:** Picking a ticket whose dependencies have not been implemented.
- **When to stop:** If a prior prerequisite ticket is incomplete, stop and implement the prerequisite first.
- **Evidence:** Active Ticket ID logged in `docs/PROJECT_STATUS.md`.

### Step 2: Document Review
- **What to inspect:** The ticket's linked PRD sections, Technical Architecture sections, Security & Access sections, and Frontend Specifications.
- **What to verify:** Ensure complete understanding of business invariants, data models, state machines, and UX expectations.
- **Failure cause:** Implementing based on assumptions rather than specifications.
- **When to stop:** If a requirement is contradictory or marked TBD, record it in [docs/DECISION_LOG.md](file:///f:/Wholesale%20Distribution%20Management%20System/docs/DECISION_LOG.md) and stop.
- **Evidence:** Specification citations listed in the ticket plan.

### Step 3: Repository Inspection
- **What to inspect:** Existing migrations, models, services, policies, controllers, TypeScript types, and React components.
- **What to verify:** Current state of the relevant domain module. Check if related utilities or shared components already exist.
- **Failure cause:** Re-implementing existing helpers, inventing duplicate enums, or ignoring established patterns.
- **When to stop:** If unexpected legacy modifications or broken working tree states are detected.
- **Evidence:** File search queries and inspection logs.

### Step 4: Prerequisite & Dependency Check
- **What to inspect:** Database schema, migrations status, foreign key relationships, route registrations, and package dependencies.
- **What to verify:** Database tables required by this ticket exist and pass migration checks.
- **Failure cause:** Writing code against nonexistent tables or missing foreign keys.
- **When to stop:** If a database migration or parent domain entity is absent.
- **Evidence:** `php artisan migrate:status` verification.

### Step 5: Implementation Plan
- **What to inspect:** Scope boundaries. Formulate the exact list of files to create, modify, or delete.
- **What to verify:** Adherence to the **Minimal-Change Principle**. Confirm no unrelated files will be touched.
- **Failure cause:** Unbounded scope, unplanned schema changes, or accidental architecture mutations.
- **When to stop:** If implementation requires altering existing historical records or breaking the modular monolith.
- **Evidence:** Written implementation plan outlining models, migrations, use cases, UI components, and tests.

### Step 6: Implementation
- **What to inspect:** Follow strict coding standards: `declare(strict_types=1);` in PHP, strict TypeScript typing (no `any`), server-side domain validations, and transactional safety.
- **What to verify:** Code accurately reflects the approved design and domain invariants.
- **Failure cause:** Relying on frontend validation alone, writing raw SQL queries without parameterization, or omitting audit logs.
- **When to stop:** If an edge case invalidates the planned architecture.
- **Evidence:** Clean source code adhering to repository standards.

### Step 7: Targeted Automated Testing
- **What to inspect:** Newly created test files in `tests/Unit` and `tests/Feature`.
- **What to verify:** Test coverage includes happy paths, input validation errors (422), permission rejections (403), resource scoping (IDOR), and domain boundary tests.
- **Failure cause:** Inadequate assertions, mock-heavy tests that do not test database transactions, or failing tests.
- **When to stop:** If any targeted test fails.
- **Evidence:** Terminal output showing green/passing targeted tests.

### Step 8: Regression Testing
- **What to inspect:** The entire test suite (`php artisan test` and `npm run test` if configured).
- **What to verify:** No existing functionality in prior domains was broken by the new implementation.
- **Failure cause:** Accidental side-effects, schema breaks, or altered shared helpers.
- **When to stop:** If any existing test fails. Fix the regression before proceeding.
- **Evidence:** Full test suite execution log with zero failures.

### Step 9: Security & Authorization Check
- **What to inspect:** Controller endpoints, route middlewares, Form Requests, policies, and queries.
- **What to verify:** Full enforcement of the security chain:
  `Authentication → Identity → Role → Permission → Resource Scope → Business State Validation → Authoritative Calculation → Atomic Execution → Audit`.
  Ensure zero client trust for prices, taxes, totals, or statuses.
- **Failure cause:** Missing policy checks, missing resource ownership scoping, or accepting client-provided status fields.
- **When to stop:** If an IDOR vulnerability or permission bypass is detected.
- **Evidence:** Security test assertions in the feature test suite.

### Step 10: Responsive Layout Verification
- **What to inspect:** Frontend components across standard breakpoints:
  Desktop XL (1440-1920px), Desktop (1024-1279px), Tablet (768-1023px), Mobile (375-430px), Mobile S (320px).
- **What to verify:** Mobile layouts use cards/sheets/bottom navigation; desktop layouts use dense tables and sidebars. No horizontal overflow or truncated financial figures.
- **Failure cause:** Shrinking desktop tables on phones or unreadable typography.
- **When to stop:** If mobile view is broken, unnavigable, or touch targets are smaller than 44x44px.
- **Evidence:** Visual/component inspection across breakpoints.

### Step 11: Accessibility Verification
- **What to inspect:** Interactive elements, forms, table headers, and badges.
- **What to verify:** WCAG 2.1 AA compliance: semantic HTML elements, visible focus indicators, valid label associations, and status indicators that do not rely on color alone.
- **Failure cause:** Using unadorned `div` tags for buttons, missing `aria` labels, or poor color contrast.
- **When to stop:** If keyboard navigation is impossible or forms lack input labels.
- **Evidence:** Accessibility checklist verification.

### Step 12: Dead Code & Duplicate Check (Anti-Dead-Code Protocol)
- **What to inspect:** `git status` and git working tree.
- **What to verify:**
  - No temporary or scratch files left behind.
  - No unused imports (`use` / `import`).
  - No commented-out blocks or leftover debugging statements (`dd()`, `dump()`, `console.log`).
  - No duplicate helpers or components created.
- **Failure cause:** Accumulating dead files and abandoned code snippets.
- **When to stop:** If any dead code or unreferenced file is discovered, delete it immediately.
- **Evidence:** Clean working directory with zero unreferenced files.

### Step 13: Dependency & Package Hygiene Check
- **What to inspect:** `composer.json`, `composer.lock`, `package.json`, and `package-lock.json`.
- **What to verify:** No unapproved third-party dependencies were installed during experimentation.
- **Failure cause:** Introducing unvetted or redundant npm/composer libraries.
- **When to stop:** If an unapproved library was added, remove it and refactor using native or existing tools.
- **Evidence:** Clean package manifest diffs.

### Step 14: Git Diff Line-by-Line Review
- **What to inspect:** `git diff` output for all staged and unstaged changes.
- **What to verify:** Every single line modified directly corresponds to the ticket's acceptance criteria.
- **Failure cause:** Accidental formatting changes, whitespace churn, or unintended modifications to unrelated files.
- **When to stop:** If unrelated modifications are spotted, revert them before committing.
- **Evidence:** Verified clean git diff.

### Step 15: Documentation & Traceability Update
- **What to inspect:** [docs/PROJECT_RULES.md](file:///f:/Wholesale%20Distribution%20Management%20System/docs/PROJECT_RULES.md), [docs/DECISION_LOG.md](file:///f:/Wholesale%20Distribution%20Management%20System/docs/DECISION_LOG.md), and [docs/TEST_MATRIX.md](file:///f:/Wholesale%20Distribution%20Management%20System/docs/TEST_MATRIX.md).
- **What to verify:** If a decision was made or a test added, update the respective documentation.
- **Failure cause:** Out-of-date documentation and lost test traceability.
- **When to stop:** If documentation does not reflect the current codebase state.
- **Evidence:** Updated documentation files.

### Step 16: Status & Checklist Update
- **What to inspect:** [docs/PROJECT_STATUS.md](file:///f:/Wholesale%20Distribution%20Management%20System/docs/PROJECT_STATUS.md) and [docs/PROJECT_CHECKLIST.md](file:///f:/Wholesale%20Distribution%20Management%20System/docs/PROJECT_CHECKLIST.md).
- **What to verify:** Mark the ticket item as `[x] Verified complete` in the master checklist. Update completed ticket counts and current progress percentages.
- **Failure cause:** Desynchronized project status tracking.
- **When to stop:** If status metrics do not reflect actual verified completion.
- **Evidence:** Committed updates to `PROJECT_STATUS.md` and `PROJECT_CHECKLIST.md`.

### Step 17: Git Commit
- **What to inspect:** Staged files.
- **What to verify:** Follow standard conventional commit format:
  `feat(scope): short description [TICKET-ID]`
  `fix(scope): short description [TICKET-ID]`
- **Failure cause:** Vague commit messages or bundling multiple tickets into one commit.
- **When to stop:** If multiple unrelated tickets are bundled in the staging area.
- **Evidence:** Commit hash recorded in git history.

### Step 18: Git Push / CI Verification
- **What to inspect:** Remote tracking branch and GitHub Actions CI workflow runs.
- **What to verify:** Branch pushes cleanly; automated CI checks (linters, tests, builds) pass without error.
- **Failure cause:** CI pipeline failure or broken remote build.
- **When to stop:** If remote CI fails, immediately investigate and fix.
- **Evidence:** Passing GitHub Actions pipeline run.

### Step 19: Next Ticket Selection
- **What to inspect:** [docs/BUILD_PHASES.md](file:///f:/Wholesale%20Distribution%20Management%20System/docs/BUILD_PHASES.md) and current Phase exit criteria.
- **What to verify:** If all tickets in the current phase are complete, trigger the **Phase Completion Protocol** and generate a Phase Report. Otherwise, select the next sequential ticket.
- **Evidence:** Next ticket selected according to phase sequence.

---

## 3. DO NOT MARK TICKET COMPLETE IF...

An agent or developer **MUST NOT** mark a ticket complete if **ANY** of the following conditions are true:

1. **Failing Tests:** Any unit, feature, integration, or regression test is failing or skipped.
2. **Missing Tests:** The new logic has zero automated tests covering authorization, validation, or happy path.
3. **Client-Trusted Calculations:** The frontend calculates taxes, prices, totals, or inventory availability and the backend blindly accepts them.
4. **Mutated Historical Data:** An order quantity was directly updated to represent a cancellation or return instead of creating an adjustment record.
5. **Missing Resource Scope:** A salesman can view another salesman's customers, or a delivery partner can view unassigned deliveries.
6. **Public Payment Evidence:** Cheque or money-order images are accessible via public, unauthenticated URLs.
7. **Product Images on Invoices:** Product images appear anywhere in the invoice HTML or PDF document.
8. **Incomplete Responsive States:** The page breaks, overflows horizontally, or is unusable on a mobile phone (375px) or tablet (768px).
9. **Missing UI States:** Loading states, empty states, or error handling are missing or replaced with blank white screens.
10. **Dead Code / Leftover Artifacts:** Unused imports, temporary debug logging (`dd()`, `console.log`), or experimental files remain in the repository.
11. **Unreviewed Git Diff:** The staged changes have not been inspected line-by-line.
12. **Outdated Operational Docs:** [docs/PROJECT_STATUS.md](file:///f:/Wholesale%20Distribution%20Management%20System/docs/PROJECT_STATUS.md) or [docs/PROJECT_CHECKLIST.md](file:///f:/Wholesale%20Distribution%20Management%20System/docs/PROJECT_CHECKLIST.md) has not been updated.

---

## 4. Specialized Domain Workflows

### A. Normal Master Data / CRUD Ticket
1. Check existing models, traits, and policies for reuse.
2. Create/update migration with foreign keys and database constraints.
3. Define Eloquent model with explicit `$fillable`, property casts, and relationships.
4. Create Form Request classes validating inputs (unique constraints, string lengths, formats).
5. Enforce Policy check in Controller/Action (`authorize('update', $model)`).
6. Implement Inertia React pages (Index, Show, Form) using shadcn/ui components.
7. Implement Desktop table with server-side pagination, debounced search, and mobile card view.
8. Add automated tests: validation failure, unauthorized access, successful CRUD, and audit log generation.

### B. Order Ticket
1. Review `RULE-ORD-*` and `RULE-ALLOC-*`.
2. Ensure order creation is wrapped in a database transaction (`DB::transaction`).
3. Authoritatively resolve product pricing using `PricingService` (`RULE-PRI-002`).
4. Authoritatively calculate line-level taxes using `TaxCalculationService` (`RULE-TAX-001`).
5. Snapshot product name, SKU, price, tax rate, and amounts on each `order_items` record.
6. Enforce submission idempotency via client idempotency keys (`FEAT-ORD-005`).
7. Update customer outstanding balance and credit limit checks.
8. Verify independent lifecycle status columns (`status`, `fulfillment_status`, `payment_status`, `delivery_status`).
9. Add tests: duplicate submission prevention, unauthorized price override rejection, price boundary checks, and line-item tax snapshot immutability.

### C. Inventory Ticket
1. Review `RULE-INV-*`.
2. Always execute stock balance changes within a database transaction.
3. Use pessimistic locking (`SELECT ... FOR UPDATE`) on `inventory_balances` to prevent race conditions.
4. Verify non-negative constraint: $\text{on\_hand} - \text{reserved} \ge \text{requested}$.
5. Write corresponding audit entry to `inventory_movements` with explicit `from_state` and `to_state`.
6. Add concurrent concurrency test simulating simultaneous order reservations for scarce stock.

### D. Order Adjustment Ticket
1. Review `RULE-ORD-002`, `RULE-ALLOC-*`, and `RULE-DOM-001`.
2. Never update `order_items.ordered_quantity`.
3. Create `order_adjustments` and `order_adjustment_items` records capturing requested changes, reason, and requester.
4. If adjustment cancels or reduces stock, release corresponding `reserved_quantity` and record movement. If damaged, move to `DAMAGED`.
5. Recalculate net order total and tax adjustments proportionally.
6. Provide visual adjustment preview showing before/after impact on quantities, taxes, totals, and customer balance.
7. Add tests: partial quantity cancellation math, multi-adjustment cumulative validation, inventory release verification, and non-destructive quantity assertions.

### E. Payment Ticket
1. Review `RULE-PAY-*` and `RULE-SEC-004`.
2. Capture payment method (`CASH`, `CHEQUE`, `MONEY_ORDER`).
3. For `CHEQUE` and `MONEY_ORDER`, validate mandatory JPEG upload (check binary magic bytes `\xFF\xD8\xFF`).
4. Store files in private S3 bucket using UUID storage keys. Store metadata in `payment_evidence`.
5. Implement verification workflow: `RECORDED` → `PENDING_VERIFICATION` → `VERIFIED`.
6. Reversals or bounced cheques must create offsetting customer account entries and accounting journals.
7. Add tests: rejection of non-JPEG uploads, missing evidence validation, unauthorized payment verification rejection, and duplicate payment recording prevention.

### F. Financial & Accounting Ticket
1. Review `RULE-ACC-*` and `RULE-CR-*`.
2. Ensure double-entry journal balance: $\sum \text{Debits} = \sum \text{Credits}$.
3. Never allow `UPDATE` or `DELETE` on posted `journal_entries` or `journal_lines`.
4. Implement controlled reversal journal mechanism for corrections.
5. Ensure receivable ledger records customer invoices, payments, credits, and refunds accurately.
6. Add tests: un-balanced journal rejection, journal immutability assertion, customer aging calculations, and credit-note balance reconciliations.

### G. Security-Sensitive Ticket
1. Review [docs/Wholesale_Distribution_Management_System_Security_and_Access.md](file:///f:/Wholesale%20Distribution%20Management%20System/docs/Wholesale_Distribution_Management_System_Security_and_Access.md).
2. Validate role, permission, and resource scope server-side.
3. Validate session timeout, rate limiting, and step-up authentication if applicable.
4. Sanitize all inputs to prevent XSS and SQL injection.
5. Log security events to dedicated security log channel.
6. Add security test suite asserting 401 Unauthenticated, 403 Forbidden, and 429 Too Many Requests.

### H. UI & Responsive Ticket
1. Review [docs/Wholesale_Distribution_Management_System_Frontend_Specification.md](file:///f:/Wholesale%20Distribution%20Management%20System/docs/Wholesale_Distribution_Management_System_Frontend_Specification.md).
2. Align typography, colors, borders, and radius with defined Design Tokens.
3. Verify all states: default, skeleton loading, empty state with CTA, error state with retry.
4. Verify responsive layout on mobile (375px), tablet (768px), and desktop (1280px+).
5. Verify touch targets ($\ge 44\text{px}$) on mobile views.
6. Run accessibility checks (contrast, focus rings, semantic tags).

### I. Bug Fix Ticket
1. Reproduce the bug with a failing automated test first.
2. Inspect git history to identify the root cause.
3. Apply the minimal fix required to address the root cause.
4. Verify the new test passes and no regressions exist in the full test suite.
5. Complete anti-dead-code cleanup, update documentation, and commit with `fix(scope): ... [TICKET-ID]`.
