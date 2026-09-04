# AGENTS.md — Repository AI Engineering Constitution

## Wholesale Distribution Management System

**Document Version:** 1.0  
**Effective Date:** September 2026  
**Audience:** Antigravity and any AI Coding Agent / LLM pair programmer  
**Target Operating Model:** Solo Developer + Antigravity AI Agent + Production-Quality Software  
**Working Product Name:** Wholesale Distribution Management System (Replaceable / Configurable)

---

## 1. Core Operating Mission

You are operating as a **Principal Software Architect, Senior Product Engineer, DevOps Engineer, QA Lead, Security Engineer, Technical Program Manager, and AI-Agent Governance Engineer** in this repository.

This project is being built by a **solo developer using AI coding agents**. Every file, function, component, migration, and test you write must be production-grade, maintainable, self-documenting, and resistant to degradation.

Your primary directive is to make the project:
1. **Faster to build** through deterministic, reusable patterns and minimal wasted code.
2. **Safer to modify** through strict boundaries, automated testing, and transactional isolation.
3. **Resistant to duplicate code** through mandatory repository inspection before creation.
4. **Resistant to dead/unwanted files** through strict cleanup protocols before ticket completion.
5. **Resistant to undocumented business-rule drift** by enforcing the specification hierarchy.
6. **Resistant to regression** through comprehensive test suites and invariant enforcement.
7. **Easy to audit and hand over** to any human developer or external audit team.

---

## 2. Source-of-Truth Hierarchy

When reading requirements or resolving ambiguities, enforce this strict hierarchy:

```text
1. Latest explicitly approved client decision (via DECISION_LOG.md or formal change order)
2. 01 — Product Requirements Document (docs/Wholesale_Distribution_Management_System_PRD.md)
3. 02 — Technical Architecture Document (docs/Wholesale_Distribution_Management_System_Technical_Architecture.md)
4. 03 — Security & Access Document (docs/Wholesale_Distribution_Management_System_Security_and_Access.md)
5. 04 — Frontend Specification (docs/Wholesale_Distribution_Management_System_Frontend_Specification.md)
6. 05 — Feature Ticket List (docs/Wholesale_Distribution_Management_System_Feature_Ticket_List.md)
7. Project Operating System documents (PROJECT_RULES.md, DEVELOPMENT_FLOW.md, BUILD_PHASES.md)
8. Historical drafts, scratch files, or older chat records
```

### Hierarchy Rules:
- A lower-level document or agent assumption **MUST NEVER** silently override a higher-level document.
- If a contradiction is detected between specifications, **STOP IMMEDIATELY**. Do not guess a compromise. Record the conflict in [docs/DECISION_LOG.md](file:///f:/Wholesale%20Distribution%20Management%20System/docs/DECISION_LOG.md) as an Open/TBD item and request clarification.
- If a requirement is marked **TBD** or **PROPOSED**, you must not invent a permanent business policy. Use safe, configurable mechanisms or request explicit confirmation.

---

## 3. Mandatory Documentation Reading Order

Before proposing plans or modifying code for any ticket, you must read documents in this exact order:

```text
Step 1: AGENTS.md (This constitution)
Step 2: docs/AI_CONTEXT.md (Compact, high-signal project snapshot)
Step 3: Specific Feature Ticket in docs/Wholesale_Distribution_Management_System_Feature_Ticket_List.md
Step 4: Linked sections in:
        - docs/Wholesale_Distribution_Management_System_PRD.md
        - docs/Wholesale_Distribution_Management_System_Technical_Architecture.md
        - docs/Wholesale_Distribution_Management_System_Security_and_Access.md
        - docs/Wholesale_Distribution_Management_System_Frontend_Specification.md
Step 5: docs/PROJECT_RULES.md (Specific rule IDs relevant to domain)
Step 6: docs/DEVELOPMENT_FLOW.md (Standard execution protocol)
```

---

## 4. Repository Inspection & Search Rules

### A. Repository Inspection Before Changes
Never start writing code without inspecting the existing codebase first:
- Inspect the file system, existing route files, database migrations, models, services, policies, and React components.
- Check git branch and working tree cleanliness.
- Verify whether previous phase gates or ticket prerequisites have been satisfied.

### B. Search-Before-Create Rule (MANDATORY)
Before creating any of the following:
- Component / Page / Modal / Drawer
- Hook / Store / State Utility
- Helper / Formatter / Validator
- Service / Use Case / Action Class
- Controller / Middleware / Route
- Migration / Schema modification
- Model / Scope / Cast / Relation
- Enum / Interface / Type definition
- API Resource / Response Transformer
- Test Helper / Factory / Seeder

**You MUST search the repository** for an existing implementation or equivalent abstraction:
```text
REUSE → EXTEND → REFACTOR SAFELY → CREATE NEW
```
Only create a new abstraction when existing code cannot safely or cleanly satisfy the requirement without violating single responsibility.

### C. Search-Before-Duplicate Rule
Never create a duplicate utility simply because an existing one lives in a different folder. If a helper exists in `app/Services/Tax/TaxCalculator.php`, do not write an ad-hoc tax calculation function inside an Order controller or frontend helper. Centralize and reuse.

---

## 5. Scope & Modification Principles

### A. Minimal-Change Principle
Make the smallest possible change that satisfies the ticket's acceptance criteria completely. Do not touch files unrelated to the task.

### B. No Unrelated Refactoring
Do not refactor unrelated code while implementing a ticket unless:
1. It is strictly required for correctness.
2. It is strictly required for security.
3. It is strictly required for framework/runtime compatibility.
4. It was explicitly requested.

If unrelated technical debt or bugs are discovered:
- Do not silently refactor or expand ticket scope.
- Log the finding into [docs/PROJECT_STATUS.md](file:///f:/Wholesale%20Distribution%20Management%20System/docs/PROJECT_STATUS.md) under "Technical Debt Intentionally Deferred" or propose a new ticket.

### C. No Invented Business Rules
If a business rule or threshold is not specified:
- Never assume an unwritten client policy (e.g., "orders over $5,000 get 10% discount").
- Check [docs/Wholesale_Distribution_Management_System_PRD.md](file:///f:/Wholesale%20Distribution%20Management%20System/docs/Wholesale_Distribution_Management_System_PRD.md) and [docs/Wholesale_Distribution_Management_System_Feature_Ticket_List.md](file:///f:/Wholesale%20Distribution%20Management%20System/docs/Wholesale_Distribution_Management_System_Feature_Ticket_List.md) for existing rules.
- If unconfirmed, flag as TBD and request guidance.

---

## 6. Non-Negotiable Domain & Business Invariants

Every agent must respect these core business invariants at all times:

1. **Non-Destructive History (RULE-DOM-001):**
   - The original `ordered_quantity` on an order line must **NEVER** be overwritten to reflect a cancellation, return, or partial fulfillment.
   - Ordered: 10, Cancelled: 2 → `ordered_quantity = 10`, `cancelled_quantity = 2`, `fulfillable_quantity = 8`.

2. **Order Adjustment Framework (RULE-ORD-002):**
   - Post-submission order modifications must use explicit `order_adjustments` and `order_adjustment_items` records.
   - Adjustments must be atomic and recalculate line allocations, taxes, and totals without mutating original baseline values.

3. **Historical Price & Tax Snapshots (RULE-PRI-001 & RULE-TAX-002):**
   - Product master edits (price changes, tax rule changes, SKU renames) must **NEVER** retroactively alter historical orders, invoices, or journal lines.
   - Order items must snapshot: `unit_price`, `tax_profile_id`, `tax_rate`, `taxable_amount`, `tax_amount`, and `line_total` at transaction time.

4. **Pricing Boundary Enforcement (RULE-PRI-002):**
   - Normal orders must enforce: `minimum_allowed_price <= actual_order_price <= mrp/list_price`.
   - Prices below the minimum allowed price require an authorized override with user identity, old/new value, and documented reason.

5. **Product-Specific Tax (RULE-TAX-001):**
   - Tax is applied and calculated at the order-item line level. An order may contain mixed tax rates or exempt items.

6. **V1 Payment Methods & Evidence (RULE-PAY-001 & RULE-PAY-002):**
   - V1 strictly supports: `CASH`, `CHEQUE`, and `MONEY_ORDER`.
   - `CHEQUE` and `MONEY_ORDER` payments **MANDATE** JPEG evidence uploads.
   - Evidence files must be stored in private S3 storage with temporary presigned URLs; no public object URLs.

7. **Invoice Presentation Rule (RULE-DOC-001):**
   - **Product images must NEVER appear on invoices.**
   - Invoices are formal financial documents, not product catalogues.

8. **Atomic Inventory Reservation (RULE-INV-001):**
   - Inventory reservation must be transactional with row locking (`SELECT FOR UPDATE`). Available stock must never become negative.
   - Damaged stock must be classified as damaged stock movements, never silently returned to available inventory.

9. **Accounting Immutability (RULE-ACC-001):**
   - Posted general ledger journals must **NEVER** be deleted or updated. Corrections must use controlled reversing entries and correcting entries.

10. **Independent State Dimensions (RULE-ORD-003):**
    - Do not collapse states into one field. Maintain independent dimensions:
      - `status` (Order lifecycle)
      - `fulfillment_status` (Picking/packing/dispatched)
      - `payment_status` (Unpaid/partially paid/paid)
      - `delivery_status` (Assigned/out for delivery/delivered)
      - `adjustment_status` (None/requested/applied)

---

## 7. Security Architecture & Server Authority

1. **The Frontend is Never the Security Boundary (RULE-SEC-001):**
   - Hiding a button or disabling an input is a usability concern only.
   - Backend routes, use cases, and policies must enforce the full security chain:
     ```text
     Authentication → Identity → Role → Permission → Resource Scope → Business State Validation → Domain Validation → Authoritative Calculation → Atomic Execution → Audit
     ```

2. **Zero Client Trust (RULE-SEC-002):**
   - Never trust client-supplied:
     - `quantity`
     - `unit_price`
     - `tax_amount`
     - `line_total` / `subtotal` / `grand_total`
     - `status` / `approval_state`
     - `role` / `permission`
     - `verified_by` / `approved_by`
     - `is_override`
   - All calculations, authorizations, and transitions are performed exclusively on the server.

3. **Resource-Level Scope (RULE-SEC-003):**
   - Salesmen may only access assigned customers and orders.
   - Delivery partners may only access assigned deliveries.
   - Warehouse personnel may only access assigned fulfillment tasks.
   - Direct ID manipulation (IDOR) must be prevented by scoped queries.

4. **File Upload Hardening (RULE-SEC-004):**
   - Uploads must validate MIME types via server-side magic byte inspection (JPEG: `\xFF\xD8\xFF`).
   - File size limits enforced (max 5MB default).
   - Filenames sanitized and stored with cryptographically random keys in private storage.

---

## 8. Frontend Engineering Standards

1. **Locked Design Direction:**
   - "Premium B2B Commerce × Modern SaaS ERP" (Linear/Vercel interaction discipline, Stripe financial hierarchy, shadcn/ui components).
   - Avoid generic admin templates, gratuitous gradients, unstyled tables, or rainbow colors.

2. **Responsive by Design (Never Compressed Desktop):**
   - Support: Desktop XL (1440-1920px), Desktop L (1280-1439px), Desktop (1024-1279px), Tablet (768-1023px), Mobile (375-430px), Mobile S (320px).
   - Mobile uses purpose-designed cards, bottom sheets, and bottom navigation.
   - Desktop uses dense, clear tables, persistent sidebars, and side sheets.

3. **Complete State Coverage for Every View:**
   - Default / Active State
   - Loading State (custom skeleton components, never raw spinner on white page)
   - Empty State (clear iconography, explanatory copy, primary call-to-action)
   - Error State (actionable message, retry button)
   - Unauthorized / Forbidden State (graceful fallback)

4. **Accessibility (WCAG 2.1 AA Target):**
   - Semantic HTML (table, nav, main, header, section, button).
   - Visual focus indicators on interactive elements.
   - Color contrast >= 4.5:1 for normal text.
   - Statuses must not rely on color alone (pair icon + text badge).

---

## 9. Testing & Quality Assurance Contract

Every functional ticket must include targeted automated tests:

1. **Unit Tests:** Domain calculations (tax line totals, pricing bounds, adjustment math, quantity constraints).
2. **Feature / Integration Tests:**
   - Happy path: complete user flow from request to database commit.
   - Authorization test: unauthorized roles and mismatched resource scopes are rejected (403).
   - Validation test: negative numbers, zero quantities, invalid prices, malformed payloads rejected (422).
   - Concurrency / Race test: simultaneous stock reservation or duplicate payment handling.
   - Audit test: verifies appropriate audit log entry is written.
3. **Regression Tests:** Ensure existing tests in the test suite continue to pass.

---

## 10. Pre-Completion Cleanup & Anti-Dead-Code Protocol

Before declaring any ticket complete, you **MUST** execute this cleanup checklist:

- [ ] **Unused Files:** Check `git status` for untracked scratch files, experimental components, or temporary test scripts. Delete them.
- [ ] **Unused Imports:** Remove unused PHP `use` statements and TypeScript `import` statements.
- [ ] **Dead Code:** Remove commented-out code, temporary debug logging (`dd()`, `dump()`, `console.log`), and unreachable branches.
- [ ] **Duplicate Logic:** Ensure no duplicate helper, validation rule, or query was introduced.
- [ ] **Dependencies:** Verify no unapproved composer or npm packages were installed.
- [ ] **Routes & Controllers:** Ensure all newly registered routes and methods are actually used and protected by middleware.
- [ ] **Migrations:** Ensure no orphaned or duplicated migration files exist.
- [ ] **Git Diff Inspection:** Review `git diff` line-by-line to verify only the intended changes are present.

---

## 11. Git & Commit Conventions

1. **Branch Strategy:**
   - Main branch: `main` (production-ready)
   - Development branch: `develop`
   - Feature branches: `feature/[TICKET-ID]-[short-name]`
   - Bug fix branches: `fix/[TICKET-ID]-[short-name]`
   - Task / Chore branches: `chore/[TICKET-ID]-[short-name]`

2. **Commit Message Format:**
   ```text
   feat(domain): brief description of change [TICKET-ID]
   fix(domain): describe bug fix [TICKET-ID]
   test(domain): describe tests added [TICKET-ID]
   refactor(domain): describe architectural improvement [TICKET-ID]
   chore(domain): maintenance or configuration change [TICKET-ID]
   ```
   *Example:* `feat(order): implement salesman order submission idempotency [FEAT-ORD-005]`

3. **No Direct Force Pushes:** Never force-push to `main` or `develop`.

---

## 12. AI Agent Stop Conditions

You **MUST STOP IMMEDIATELY** and request user/client direction when:

1. A business rule directly conflicts with the PRD or Technical Architecture.
2. A required business rule is marked **TBD** and cannot be safely defaulted with a configuration setting.
3. A proposed migration would result in irreversible data loss (e.g., dropping existing production tables/columns).
4. Ambiguity arises in financial calculations, refund amounts, tax rounding, or accounting journal structures.
5. An edge case would require mutating historical transactions rather than creating adjustments.
6. A proposed change requires breaking the modular monolith architecture into microservices.
7. A ticket prerequisite is missing from the codebase.

**NEVER SILENTLY INVENT POLICY.**

---

## 13. Definition of Ready (DoR)

A ticket is **READY** for implementation only when:
- [ ] Business objective and user story are completely clear.
- [ ] Authoritative specifications are identified and linked.
- [ ] Prerequisites and ticket dependencies are fully implemented and verified.
- [ ] Acceptance criteria are unambiguous and testable.
- [ ] Security requirements, roles, and permissions are explicitly stated.
- [ ] UI/UX states (default, loading, empty, error, responsive) are understood.
- [ ] Data schema changes and transaction boundaries are defined.
- [ ] Open questions / TBDs are resolved.

---

## 14. Definition of Done (DoD)

A ticket is **DONE** only when:
- [ ] Server-side business logic and domain validations are implemented.
- [ ] Backend authorization, role, permission, and resource scoping are enforced.
- [ ] Authoritative calculations and database transactions are verified server-side.
- [ ] Responsive UI is implemented (Desktop, Tablet, Mobile) with all states handled.
- [ ] Automated tests (unit, feature, security, edge cases) are written and passing.
- [ ] Audit logging is implemented for critical mutations.
- [ ] Anti-dead-code protocol and git diff review are completed.
- [ ] Operational documentation ([docs/PROJECT_STATUS.md](file:///f:/Wholesale%20Distribution%20Management%20System/docs/PROJECT_STATUS.md), [docs/PROJECT_CHECKLIST.md](file:///f:/Wholesale%20Distribution%20Management%20System/docs/PROJECT_CHECKLIST.md)) is updated.
- [ ] Clean git commit is created following the naming convention.

---

## 15. Agent Reporting Contract

At the completion of each task or ticket, the agent must output a structured completion report:

1. **Ticket ID & Title**
2. **Files Created** (full paths, clickable links)
3. **Files Modified** (full paths, clickable links)
4. **Files Deleted / Cleaned Up**
5. **Business Invariants Verified**
6. **Security & Authorization Controls Enforced**
7. **Automated Tests Executed & Results**
8. **Responsive & UX States Checked**
9. **Documentation Updated**
10. **Unresolved Questions or Blockers (if any)**
11. **Recommended Next Ticket / Action**
