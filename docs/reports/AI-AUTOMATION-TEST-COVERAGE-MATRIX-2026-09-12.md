# AI AUTOMATION TEST COVERAGE MATRIX
**Date:** 2026-09-12  
**Target Operating Model:** Solo Developer + AI Agent  
**Repository:** Unique Distributors Wholesale ERP  
**Total Enumerated Checklist Items:** 999  
**Authoritative Source:** `docs/FULL_EXHAUSTIVE_REAL_BROWSER_AUDIT_ZERO_FALSE_PASSES.md`  
**Manifest:** `tests/manifest/audit-manifest.json`  

---

## 1. Coverage Summary by Layer

| Test Layer | Total Items | PASS | PARTIAL | NOT TESTED | NOT APPLICABLE |
|---|---|---|---|---|---|
| META_AUDIT_RULE | 83 | 0 | 0 | 0 | 83 |
| PLAYWRIGHT_E2E | 724 | 72 | 282 | 368 | 2 |
| HTTP_API_SECURITY | 28 | 4 | 14 | 6 | 4 |
| PLAYWRIGHT_VISUAL_RESPONSIVE | 29 | 12 | 17 | 0 | 0 |
| PLAYWRIGHT_A11Y | 17 | 2 | 0 | 15 | 0 |
| POSTGRES_DB_INVARIANT | 59 | 0 | 54 | 4 | 1 |
| DOMAIN_UNIT_PHP | 20 | 0 | 13 | 7 | 0 |
| COMPOSITE_FINANCIAL_E2E | 39 | 0 | 39 | 0 | 0 |

---

## 2. Complete Checklist Item-to-Automated-Test Mapping

| CHECKLIST ID | DESCRIPTION | TEST LAYER | TEST NAME | TEST FILE | EXPECTED ASSERTION | EVIDENCE TYPE | STATUS | LAST EXECUTED | RUN ID |
|---|---|---|---|---|---|---|---|---|---|
| CHK-001 | Every checklist item is independently considered. | META_AUDIT_RULE | All audit specs | Meta-Contract | Every checklist item is independently considered. | pending | NOT_APPLICABLE | NOT EXECUTED | PENDING |
| CHK-002 | Never mark an unchecked child item as PASS because its parent page loaded. | PLAYWRIGHT_E2E | All audit specs | Meta-Contract | Never mark an unchecked child item as PASS because its parent page loaded. | pending | NOT_APPLICABLE | NOT EXECUTED | PENDING |
| CHK-003 | Never infer functionality from source code alone. | META_AUDIT_RULE | All audit specs | Meta-Contract | Never infer functionality from source code alone. | pending | NOT_APPLICABLE | NOT EXECUTED | PENDING |
| CHK-004 | Never infer a successful mutation from a 200 response alone. | META_AUDIT_RULE | All audit specs | Meta-Contract | Never infer a successful mutation from a 200 response alone. | pending | NOT_APPLICABLE | NOT EXECUTED | PENDING |
| CHK-005 | Verify visible UI + network + application state + business outcome where applicable. | META_AUDIT_RULE | All audit specs | Meta-Contract | Verify visible UI + network + application state + business outcome where applicable. | pending | NOT_APPLICABLE | NOT EXECUTED | PENDING |
| CHK-006 | For financial/inventory/security operations, verify authoritative backend state where practical. | META_AUDIT_RULE | All audit specs | Meta-Contract | For financial/inventory/security operations, verify authoritative backend state where practical. | pending | NOT_APPLICABLE | NOT EXECUTED | PENDING |
| CHK-007 | Use the same visible Chrome window for interactive testing. | META_AUDIT_RULE | All audit specs | Meta-Contract | Use the same visible Chrome window for interactive testing. | pending | NOT_APPLICABLE | NOT EXECUTED | PENDING |
| CHK-008 | Use the same browser session unless the scenario explicitly requires a new session. | META_AUDIT_RULE | All audit specs | Meta-Contract | Use the same browser session unless the scenario explicitly requires a new session. | pending | NOT_APPLICABLE | NOT EXECUTED | PENDING |
| CHK-009 | Use real user interactions, not direct database writes, to create test states. | META_AUDIT_RULE | All audit specs | Meta-Contract | Use real user interactions, not direct database writes, to create test states. | pending | NOT_APPLICABLE | NOT EXECUTED | PENDING |
| CHK-010 | Do not bypass authorization with cookies/localStorage/session manipulation. | META_AUDIT_RULE | All audit specs | Meta-Contract | Do not bypass authorization with cookies/localStorage/session manipulation. | pending | NOT_APPLICABLE | NOT EXECUTED | PENDING |
| CHK-011 | Do not silently skip difficult cases. | META_AUDIT_RULE | All audit specs | Meta-Contract | Do not silently skip difficult cases. | pending | NOT_APPLICABLE | NOT EXECUTED | PENDING |
| CHK-012 | Every skipped item requires SKIP reason + prerequisite + explicit status. | META_AUDIT_RULE | All audit specs | Meta-Contract | Every skipped item requires SKIP reason + prerequisite + explicit status. | pending | NOT_APPLICABLE | NOT EXECUTED | PENDING |
| CHK-013 | Every discovered defect receives evidence. | META_AUDIT_RULE | All audit specs | Meta-Contract | Every discovered defect receives evidence. | pending | NOT_APPLICABLE | NOT EXECUTED | PENDING |
| CHK-014 | Duplicate symptoms must be grouped only after evidence confirms a common root cause. | META_AUDIT_RULE | All audit specs | Meta-Contract | Duplicate symptoms must be grouped only after evidence confirms a common root cause. | pending | NOT_APPLICABLE | NOT EXECUTED | PENDING |
| CHK-015 | Expected validation, expected 401/403, expected 404, expected 409, and expected 429 are not bugs. | HTTP_API_SECURITY | All audit specs | Meta-Contract | Expected validation, expected 401/403, expected 404, expected 409, and expected 429 are not bugs. | pending | NOT_APPLICABLE | NOT EXECUTED | PENDING |
| CHK-016 | Framework/dev-only noise is not a product defect unless it leaks into production behavior. | META_AUDIT_RULE | All audit specs | Meta-Contract | Framework/dev-only noise is not a product defect unless it leaks into production behavior. | pending | NOT_APPLICABLE | NOT EXECUTED | PENDING |
| CHK-017 | If evidence is insufficient, status = `? NEEDS EVIDENCE`, never PASS. | META_AUDIT_RULE | All audit specs | Meta-Contract | If evidence is insufficient, status = `? NEEDS EVIDENCE`, never PASS. | pending | NOT_APPLICABLE | NOT EXECUTED | PENDING |
| CHK-018 | If a step cannot be performed because required state/data is missing, status = `- BLOCKED`, never PASS. | PLAYWRIGHT_E2E | All audit specs | Meta-Contract | If a step cannot be performed because required state/data is missing, status = `- BLOCKED`, never PASS. | pending | NOT_APPLICABLE | NOT EXECUTED | PENDING |
| CHK-019 | If a workflow works only through a bypass not available to the real user, status = FAIL/BUG. | META_AUDIT_RULE | All audit specs | Meta-Contract | If a workflow works only through a bypass not available to the real user, status = FAIL/BUG. | pending | NOT_APPLICABLE | NOT EXECUTED | PENDING |
| CHK-020 | If an operation appears correct visually but authoritative state is wrong, status = BUG. | META_AUDIT_RULE | All audit specs | Meta-Contract | If an operation appears correct visually but authoritative state is wrong, status = BUG. | pending | NOT_APPLICABLE | NOT EXECUTED | PENDING |
| CHK-021 | If authoritative state is correct but UI is wrong, status = BUG. | META_AUDIT_RULE | All audit specs | Meta-Contract | If authoritative state is correct but UI is wrong, status = BUG. | pending | NOT_APPLICABLE | NOT EXECUTED | PENDING |
| CHK-022 | If UI/network/backend all agree, only then can the item be PASS. | META_AUDIT_RULE | All audit specs | Meta-Contract | If UI/network/backend all agree, only then can the item be PASS. | pending | NOT_APPLICABLE | NOT EXECUTED | PENDING |
| CHK-023 | Start time captured | META_AUDIT_RULE | 1. AUDIT ENVIRONMENT CONTROL > 1.1 Environment | tests/Feature/ pending mapping | Start time captured | pending | NOT_APPLICABLE | NOT EXECUTED | PENDING |
| CHK-024 | End time captured | META_AUDIT_RULE | 1. AUDIT ENVIRONMENT CONTROL > 1.1 Environment | tests/Feature/ pending mapping | End time captured | pending | NOT_APPLICABLE | NOT EXECUTED | PENDING |
| CHK-025 | Base URL captured | PLAYWRIGHT_E2E | Real-Chrome QA Environment Verification | tests/browser/interactive/second-monitor.spec.ts | Base URL captured | launch-chrome.ts / CDP:9222 | PASS | 2026-09-12 | AUDIT-RUN-20260912-154532 |
| CHK-026 | Environment explicitly identified as LOCAL / PRE-PRODUCTION | META_AUDIT_RULE | 1. AUDIT ENVIRONMENT CONTROL > 1.1 Environment | tests/Feature/ pending mapping | Environment explicitly identified as LOCAL / PRE-PRODUCTION | pending | NOT_APPLICABLE | NOT EXECUTED | PENDING |
| CHK-027 | Production is not used accidentally | META_AUDIT_RULE | 1. AUDIT ENVIRONMENT CONTROL > 1.1 Environment | tests/Feature/ pending mapping | Production is not used accidentally | pending | NOT_APPLICABLE | NOT EXECUTED | PENDING |
| CHK-028 | Chrome version captured | META_AUDIT_RULE | 1. AUDIT ENVIRONMENT CONTROL > 1.1 Environment | tests/Feature/ pending mapping | Chrome version captured | pending | NOT_APPLICABLE | NOT EXECUTED | PENDING |
| CHK-029 | Chrome executable captured | PLAYWRIGHT_E2E | Real-Chrome QA Environment Verification | tests/browser/interactive/second-monitor.spec.ts | Chrome executable captured | launch-chrome.ts / CDP:9222 | PASS | 2026-09-12 | AUDIT-RUN-20260912-154532 |
| CHK-030 | QA profile captured | PLAYWRIGHT_E2E | Real-Chrome QA Environment Verification | tests/browser/interactive/second-monitor.spec.ts | QA profile captured | launch-chrome.ts / CDP:9222 | PASS | 2026-09-12 | AUDIT-RUN-20260912-154532 |
| CHK-031 | CDP endpoint captured | PLAYWRIGHT_E2E | Real-Chrome QA Environment Verification | tests/browser/interactive/second-monitor.spec.ts | CDP endpoint captured | launch-chrome.ts / CDP:9222 | PASS | 2026-09-12 | AUDIT-RUN-20260912-154532 |
| CHK-032 | `chrome-devtools` MCP connected | PLAYWRIGHT_E2E | Interactive MCP Tools & Controller | tests/browser/interactive/mcp-server.ts | `chrome-devtools` MCP connected | artifacts/browser/interactive/ | PARTIAL | NOT EXECUTED | PENDING |
| CHK-033 | `unique-distributors-browser` MCP connected | PLAYWRIGHT_E2E | Interactive MCP Tools & Controller | tests/browser/interactive/mcp-server.ts | `unique-distributors-browser` MCP connected | artifacts/browser/interactive/ | PARTIAL | NOT EXECUTED | PENDING |
| CHK-034 | Both MCP layers point to the SAME Chrome instance | PLAYWRIGHT_E2E | Interactive MCP Tools & Controller | tests/browser/interactive/mcp-server.ts | Both MCP layers point to the SAME Chrome instance | artifacts/browser/interactive/ | PARTIAL | NOT EXECUTED | PENDING |
| CHK-035 | Visible Chrome window confirmed | META_AUDIT_RULE | 1. AUDIT ENVIRONMENT CONTROL > 1.1 Environment | tests/Feature/ pending mapping | Visible Chrome window confirmed | pending | NOT_APPLICABLE | NOT EXECUTED | PENDING |
| CHK-036 | Second-monitor placement confirmed | META_AUDIT_RULE | 1. AUDIT ENVIRONMENT CONTROL > 1.1 Environment | tests/Feature/ pending mapping | Second-monitor placement confirmed | pending | NOT_APPLICABLE | NOT EXECUTED | PENDING |
| CHK-037 | Playwright fallback confirmed | META_AUDIT_RULE | 1. AUDIT ENVIRONMENT CONTROL > 1.1 Environment | tests/Feature/ pending mapping | Playwright fallback confirmed | pending | NOT_APPLICABLE | NOT EXECUTED | PENDING |
| CHK-038 | Audit artifact directory confirmed | META_AUDIT_RULE | 1. AUDIT ENVIRONMENT CONTROL > 1.1 Environment | tests/Feature/ pending mapping | Audit artifact directory confirmed | pending | NOT_APPLICABLE | NOT EXECUTED | PENDING |
| CHK-039 | Screenshot recording confirmed | META_AUDIT_RULE | 1. AUDIT ENVIRONMENT CONTROL > 1.1 Environment | tests/Feature/ pending mapping | Screenshot recording confirmed | pending | NOT_APPLICABLE | NOT EXECUTED | PENDING |
| CHK-040 | Console capture confirmed | META_AUDIT_RULE | 1. AUDIT ENVIRONMENT CONTROL > 1.1 Environment | tests/Feature/ pending mapping | Console capture confirmed | pending | NOT_APPLICABLE | NOT EXECUTED | PENDING |
| CHK-041 | Network capture confirmed | META_AUDIT_RULE | 1. AUDIT ENVIRONMENT CONTROL > 1.1 Environment | tests/Feature/ pending mapping | Network capture confirmed | pending | NOT_APPLICABLE | NOT EXECUTED | PENDING |
| CHK-042 | Video/trace policy confirmed | META_AUDIT_RULE | 1. AUDIT ENVIRONMENT CONTROL > 1.1 Environment | tests/Feature/ pending mapping | Video/trace policy confirmed | pending | NOT_APPLICABLE | NOT EXECUTED | PENDING |
| CHK-043 | Test seed/data state documented | META_AUDIT_RULE | 1. AUDIT ENVIRONMENT CONTROL > 1.1 Environment | tests/Feature/ pending mapping | Test seed/data state documented | pending | NOT_APPLICABLE | NOT EXECUTED | PENDING |
| CHK-044 | Dedicated QA profile only | PLAYWRIGHT_E2E | Real-Chrome QA Environment Verification | tests/browser/interactive/second-monitor.spec.ts | Dedicated QA profile only | launch-chrome.ts / CDP:9222 | PASS | 2026-09-12 | AUDIT-RUN-20260912-154532 |
| CHK-045 | Personal Chrome profile not attached | META_AUDIT_RULE | 1. AUDIT ENVIRONMENT CONTROL > 1.2 Browser integrity | tests/Feature/ pending mapping | Personal Chrome profile not attached | pending | NOT_APPLICABLE | NOT EXECUTED | PENDING |
| CHK-046 | CDP bound to loopback only | PLAYWRIGHT_E2E | Real-Chrome QA Environment Verification | tests/browser/interactive/second-monitor.spec.ts | CDP bound to loopback only | launch-chrome.ts / CDP:9222 | PASS | 2026-09-12 | AUDIT-RUN-20260912-154532 |
| CHK-047 | No external network exposure of CDP | PLAYWRIGHT_E2E | Real-Chrome QA Environment Verification | tests/browser/interactive/second-monitor.spec.ts | No external network exposure of CDP | launch-chrome.ts / CDP:9222 | PASS | 2026-09-12 | AUDIT-RUN-20260912-154532 |
| CHK-048 | No Playwright CDN download required | META_AUDIT_RULE | 1. AUDIT ENVIRONMENT CONTROL > 1.2 Browser integrity | tests/Feature/ pending mapping | No Playwright CDN download required | pending | NOT_APPLICABLE | NOT EXECUTED | PENDING |
| CHK-049 | Browser remains visible | META_AUDIT_RULE | 1. AUDIT ENVIRONMENT CONTROL > 1.2 Browser integrity | tests/Feature/ pending mapping | Browser remains visible | pending | NOT_APPLICABLE | NOT EXECUTED | PENDING |
| CHK-050 | Browser survives multiple conversational instructions | META_AUDIT_RULE | 1. AUDIT ENVIRONMENT CONTROL > 1.2 Browser integrity | tests/Feature/ pending mapping | Browser survives multiple conversational instructions | pending | NOT_APPLICABLE | NOT EXECUTED | PENDING |
| CHK-051 | Manual takeover works | PLAYWRIGHT_E2E | Interactive MCP Tools & Controller | tests/browser/interactive/mcp-server.ts | Manual takeover works | artifacts/browser/interactive/ | PARTIAL | NOT EXECUTED | PENDING |
| CHK-052 | Agent can observe manual changes | META_AUDIT_RULE | 1. AUDIT ENVIRONMENT CONTROL > 1.2 Browser integrity | tests/Feature/ pending mapping | Agent can observe manual changes | pending | NOT_APPLICABLE | NOT EXECUTED | PENDING |
| CHK-053 | Agent can resume from live state | META_AUDIT_RULE | 1. AUDIT ENVIRONMENT CONTROL > 1.2 Browser integrity | tests/Feature/ pending mapping | Agent can resume from live state | pending | NOT_APPLICABLE | NOT EXECUTED | PENDING |
| CHK-054 | Unique Distributors branding | PLAYWRIGHT_E2E | 1.2 - 1.6 Shell Tests | 01_auth_shell_roles.spec.ts | Unique Distributors branding | 01_shell_admin_dashboard.png | PASS | 2026-09-12 | AUDIT-RUN-20260912-154532 |
| CHK-055 | Correct favicon/logo | PLAYWRIGHT_E2E | 3. GLOBAL UI / SHELL / NAVIGATION > 3.1 App identity | tests/Feature/ pending mapping | Correct favicon/logo | pending | NOT_TESTED | NOT EXECUTED | PENDING |
| CHK-056 | No stale "Wholesale Distribution Management System" where not intended | PLAYWRIGHT_E2E | 1.2 - 1.6 Shell Tests | 01_auth_shell_roles.spec.ts | No stale "Wholesale Distribution Management System" where not intended | 01_shell_admin_dashboard.png | PASS | 2026-09-12 | AUDIT-RUN-20260912-154532 |
| CHK-057 | No internal ticket IDs in client-facing UI | PLAYWRIGHT_E2E | 1.2 - 1.6 Shell Tests | 01_auth_shell_roles.spec.ts | No internal ticket IDs in client-facing UI | 01_shell_admin_dashboard.png | PASS | 2026-09-12 | AUDIT-RUN-20260912-154532 |
| CHK-058 | No `AUTH-*` | PLAYWRIGHT_E2E | 1.2 - 1.6 Shell Tests | 01_auth_shell_roles.spec.ts | No `AUTH-*` | 01_shell_admin_dashboard.png | PASS | 2026-09-12 | AUDIT-RUN-20260912-154532 |
| CHK-059 | No `BUG-*` | PLAYWRIGHT_E2E | 3. GLOBAL UI / SHELL / NAVIGATION > 3.1 App identity | tests/Feature/ pending mapping | No `BUG-*` | pending | NOT_TESTED | NOT EXECUTED | PENDING |
| CHK-060 | No `QA-*` | PLAYWRIGHT_E2E | 3. GLOBAL UI / SHELL / NAVIGATION > 3.1 App identity | tests/Feature/ pending mapping | No `QA-*` | pending | NOT_TESTED | NOT EXECUTED | PENDING |
| CHK-061 | No `PHASE-*` | PLAYWRIGHT_E2E | 3. GLOBAL UI / SHELL / NAVIGATION > 3.1 App identity | tests/Feature/ pending mapping | No `PHASE-*` | pending | NOT_TESTED | NOT EXECUTED | PENDING |
| CHK-062 | No `EPIC-*` | PLAYWRIGHT_E2E | 3. GLOBAL UI / SHELL / NAVIGATION > 3.1 App identity | tests/Feature/ pending mapping | No `EPIC-*` | pending | NOT_TESTED | NOT EXECUTED | PENDING |
| CHK-063 | No developer diagnostics in user-facing UI | PLAYWRIGHT_E2E | 3. GLOBAL UI / SHELL / NAVIGATION > 3.1 App identity | tests/Feature/ pending mapping | No developer diagnostics in user-facing UI | pending | NOT_TESTED | NOT EXECUTED | PENDING |
| CHK-064 | No foundation/demo scaffold exposed | PLAYWRIGHT_E2E | 3. GLOBAL UI / SHELL / NAVIGATION > 3.1 App identity | tests/Feature/ pending mapping | No foundation/demo scaffold exposed | pending | NOT_TESTED | NOT EXECUTED | PENDING |
| CHK-065 | Correct authenticated landing | PLAYWRIGHT_E2E | 1.2 - 1.6 Role Shells | 01_auth_shell_roles.spec.ts | Correct authenticated landing | 01_shell_admin_dashboard.png, 01_shell_salesman_dashboard.png | PASS | 2026-09-12 | AUDIT-RUN-20260912-154532 |
| CHK-066 | Header | PLAYWRIGHT_E2E | 1.2 - 1.6 Role Shells | 01_auth_shell_roles.spec.ts | Header | 01_shell_admin_dashboard.png, 01_shell_salesman_dashboard.png | PASS | 2026-09-12 | AUDIT-RUN-20260912-154532 |
| CHK-067 | Sidebar | PLAYWRIGHT_E2E | 1.2 - 1.6 Role Shells | 01_auth_shell_roles.spec.ts | Sidebar | 01_shell_admin_dashboard.png, 01_shell_salesman_dashboard.png | PASS | 2026-09-12 | AUDIT-RUN-20260912-154532 |
| CHK-068 | Mobile navigation | PLAYWRIGHT_E2E | 3. GLOBAL UI / SHELL / NAVIGATION > 3.2 Navigation | tests/Feature/ pending mapping | Mobile navigation | pending | NOT_TESTED | NOT EXECUTED | PENDING |
| CHK-069 | Breadcrumbs | PLAYWRIGHT_E2E | 3. GLOBAL UI / SHELL / NAVIGATION > 3.2 Navigation | tests/Feature/ pending mapping | Breadcrumbs | pending | PARTIAL | NOT EXECUTED | PENDING |
| CHK-070 | Role-specific navigation | HTTP_API_SECURITY | 1.2 - 1.6 Role Shells | 01_auth_shell_roles.spec.ts | Role-specific navigation | 01_shell_admin_dashboard.png, 01_shell_salesman_dashboard.png | PASS | 2026-09-12 | AUDIT-RUN-20260912-154532 |
| CHK-071 | Notification bell | PLAYWRIGHT_E2E | 3. GLOBAL UI / SHELL / NAVIGATION > 3.2 Navigation | tests/Feature/ pending mapping | Notification bell | pending | NOT_TESTED | NOT EXECUTED | PENDING |
| CHK-072 | Profile/account menu | PLAYWRIGHT_E2E | 3. GLOBAL UI / SHELL / NAVIGATION > 3.2 Navigation | tests/Feature/ pending mapping | Profile/account menu | pending | NOT_TESTED | NOT EXECUTED | PENDING |
| CHK-073 | Search/filter controls | PLAYWRIGHT_E2E | 3. GLOBAL UI / SHELL / NAVIGATION > 3.2 Navigation | tests/Feature/ pending mapping | Search/filter controls | pending | NOT_TESTED | NOT EXECUTED | PENDING |
| CHK-074 | Every visible navigation link resolves | PLAYWRIGHT_E2E | 3. GLOBAL UI / SHELL / NAVIGATION > 3.2 Navigation | tests/Feature/ pending mapping | Every visible navigation link resolves | pending | NOT_TESTED | NOT EXECUTED | PENDING |
| CHK-075 | No dead links | PLAYWRIGHT_E2E | 3. GLOBAL UI / SHELL / NAVIGATION > 3.2 Navigation | tests/Feature/ pending mapping | No dead links | pending | NOT_TESTED | NOT EXECUTED | PENDING |
| CHK-076 | No unexpected redirect loops | PLAYWRIGHT_E2E | 3. GLOBAL UI / SHELL / NAVIGATION > 3.2 Navigation | tests/Feature/ pending mapping | No unexpected redirect loops | pending | NOT_TESTED | NOT EXECUTED | PENDING |
| CHK-077 | Back navigation | PLAYWRIGHT_E2E | 3. GLOBAL UI / SHELL / NAVIGATION > 3.2 Navigation | tests/Feature/ pending mapping | Back navigation | pending | PARTIAL | NOT EXECUTED | PENDING |
| CHK-078 | Forward navigation | PLAYWRIGHT_E2E | 3. GLOBAL UI / SHELL / NAVIGATION > 3.2 Navigation | tests/Feature/ pending mapping | Forward navigation | pending | NOT_TESTED | NOT EXECUTED | PENDING |
| CHK-079 | Refresh preserves valid state | PLAYWRIGHT_E2E | 3. GLOBAL UI / SHELL / NAVIGATION > 3.2 Navigation | tests/Feature/ pending mapping | Refresh preserves valid state | pending | PARTIAL | NOT EXECUTED | PENDING |
| CHK-080 | Deep links work | PLAYWRIGHT_E2E | 3. GLOBAL UI / SHELL / NAVIGATION > 3.2 Navigation | tests/Feature/ pending mapping | Deep links work | pending | PARTIAL | NOT EXECUTED | PENDING |
| CHK-081 | 404 page is intentional | PLAYWRIGHT_E2E | Custom branded exception renderer | security_hardening_verification.spec.ts | 404 page is intentional | Resources/js/Pages/Error.tsx | PASS | 2026-09-12 | AUDIT-RUN-20260912-154532 |
| CHK-082 | 403 page is intentional | HTTP_API_SECURITY | Custom branded exception renderer | security_hardening_verification.spec.ts | 403 page is intentional | Resources/js/Pages/Error.tsx | PASS | 2026-09-12 | AUDIT-RUN-20260912-154532 |
| CHK-083 | 500 page does not leak internals | PLAYWRIGHT_E2E | Custom branded exception renderer | security_hardening_verification.spec.ts | 500 page does not leak internals | Resources/js/Pages/Error.tsx | PASS | 2026-09-12 | AUDIT-RUN-20260912-154532 |
| CHK-084 | Loading state | PLAYWRIGHT_E2E | 3. GLOBAL UI / SHELL / NAVIGATION > 3.3 Global states | tests/Feature/ pending mapping | Loading state | pending | NOT_TESTED | NOT EXECUTED | PENDING |
| CHK-085 | Empty state | PLAYWRIGHT_E2E | 3. GLOBAL UI / SHELL / NAVIGATION > 3.3 Global states | tests/Feature/ pending mapping | Empty state | pending | NOT_TESTED | NOT EXECUTED | PENDING |
| CHK-086 | Error state | PLAYWRIGHT_E2E | 3. GLOBAL UI / SHELL / NAVIGATION > 3.3 Global states | tests/Feature/ pending mapping | Error state | pending | NOT_TESTED | NOT EXECUTED | PENDING |
| CHK-087 | Success state | PLAYWRIGHT_E2E | 3. GLOBAL UI / SHELL / NAVIGATION > 3.3 Global states | tests/Feature/ pending mapping | Success state | pending | NOT_TESTED | NOT EXECUTED | PENDING |
| CHK-088 | Validation state | PLAYWRIGHT_E2E | 3. GLOBAL UI / SHELL / NAVIGATION > 3.3 Global states | tests/Feature/ pending mapping | Validation state | pending | NOT_TESTED | NOT EXECUTED | PENDING |
| CHK-089 | Disabled state | PLAYWRIGHT_E2E | 3. GLOBAL UI / SHELL / NAVIGATION > 3.3 Global states | tests/Feature/ pending mapping | Disabled state | pending | NOT_TESTED | NOT EXECUTED | PENDING |
| CHK-090 | Pending state | PLAYWRIGHT_E2E | 3. GLOBAL UI / SHELL / NAVIGATION > 3.3 Global states | tests/Feature/ pending mapping | Pending state | pending | NOT_TESTED | NOT EXECUTED | PENDING |
| CHK-091 | Confirmation state | PLAYWRIGHT_E2E | 3. GLOBAL UI / SHELL / NAVIGATION > 3.3 Global states | tests/Feature/ pending mapping | Confirmation state | pending | NOT_TESTED | NOT EXECUTED | PENDING |
| CHK-092 | Retry behavior | PLAYWRIGHT_E2E | 3. GLOBAL UI / SHELL / NAVIGATION > 3.3 Global states | tests/Feature/ pending mapping | Retry behavior | pending | NOT_TESTED | NOT EXECUTED | PENDING |
| CHK-093 | No duplicate submission | PLAYWRIGHT_E2E | 3. GLOBAL UI / SHELL / NAVIGATION > 3.3 Global states | tests/Feature/ pending mapping | No duplicate submission | pending | NOT_TESTED | NOT EXECUTED | PENDING |
| CHK-094 | 320x568 | PLAYWRIGHT_VISUAL_RESPONSIVE | 13.1 Systematic 11-breakpoint responsive rendering | 12_responsive_and_a11y.spec.ts | 320x568 | 12_responsive_dashboard_320w.png, 12_responsive_dashboard_390w.png, 12_responsive_dashboard_768w.png, 12_responsive_dashboard_1440w.png | PASS | 2026-09-12 | AUDIT-RUN-20260912-154532 |
| CHK-095 | 375x667 | PLAYWRIGHT_VISUAL_RESPONSIVE | 13.1 Systematic 11-breakpoint responsive rendering | 12_responsive_and_a11y.spec.ts | 375x667 | 12_responsive_dashboard_320w.png, 12_responsive_dashboard_390w.png, 12_responsive_dashboard_768w.png, 12_responsive_dashboard_1440w.png | PASS | 2026-09-12 | AUDIT-RUN-20260912-154532 |
| CHK-096 | 390x844 | PLAYWRIGHT_VISUAL_RESPONSIVE | 13.1 Systematic 11-breakpoint responsive rendering | 12_responsive_and_a11y.spec.ts | 390x844 | 12_responsive_dashboard_320w.png, 12_responsive_dashboard_390w.png, 12_responsive_dashboard_768w.png, 12_responsive_dashboard_1440w.png | PASS | 2026-09-12 | AUDIT-RUN-20260912-154532 |
| CHK-097 | 430x932 | PLAYWRIGHT_VISUAL_RESPONSIVE | 13.1 Systematic 11-breakpoint responsive rendering | 12_responsive_and_a11y.spec.ts | 430x932 | 12_responsive_dashboard_320w.png, 12_responsive_dashboard_390w.png, 12_responsive_dashboard_768w.png, 12_responsive_dashboard_1440w.png | PASS | 2026-09-12 | AUDIT-RUN-20260912-154532 |
| CHK-098 | 640x? appropriate project height | PLAYWRIGHT_VISUAL_RESPONSIVE | 4. RESPONSIVE / VISUAL / INTERACTION AUDIT | tests/Feature/ pending mapping | 640x? appropriate project height | pending | PARTIAL | NOT EXECUTED | PENDING |
| CHK-099 | 768x1024 | PLAYWRIGHT_VISUAL_RESPONSIVE | 13.1 Systematic 11-breakpoint responsive rendering | 12_responsive_and_a11y.spec.ts | 768x1024 | 12_responsive_dashboard_320w.png, 12_responsive_dashboard_390w.png, 12_responsive_dashboard_768w.png, 12_responsive_dashboard_1440w.png | PASS | 2026-09-12 | AUDIT-RUN-20260912-154532 |
| CHK-100 | 820x? appropriate project height | PLAYWRIGHT_VISUAL_RESPONSIVE | 13.1 Systematic 11-breakpoint responsive rendering | 12_responsive_and_a11y.spec.ts | 820x? appropriate project height | 12_responsive_dashboard_320w.png, 12_responsive_dashboard_390w.png, 12_responsive_dashboard_768w.png, 12_responsive_dashboard_1440w.png | PASS | 2026-09-12 | AUDIT-RUN-20260912-154532 |
| CHK-101 | 1024x768 | PLAYWRIGHT_VISUAL_RESPONSIVE | 13.1 Systematic 11-breakpoint responsive rendering | 12_responsive_and_a11y.spec.ts | 1024x768 | 12_responsive_dashboard_320w.png, 12_responsive_dashboard_390w.png, 12_responsive_dashboard_768w.png, 12_responsive_dashboard_1440w.png | PASS | 2026-09-12 | AUDIT-RUN-20260912-154532 |
| CHK-102 | 1280x800 | PLAYWRIGHT_VISUAL_RESPONSIVE | 13.1 Systematic 11-breakpoint responsive rendering | 12_responsive_and_a11y.spec.ts | 1280x800 | 12_responsive_dashboard_320w.png, 12_responsive_dashboard_390w.png, 12_responsive_dashboard_768w.png, 12_responsive_dashboard_1440w.png | PASS | 2026-09-12 | AUDIT-RUN-20260912-154532 |
| CHK-103 | 1440x900 | PLAYWRIGHT_VISUAL_RESPONSIVE | 13.1 Systematic 11-breakpoint responsive rendering | 12_responsive_and_a11y.spec.ts | 1440x900 | 12_responsive_dashboard_320w.png, 12_responsive_dashboard_390w.png, 12_responsive_dashboard_768w.png, 12_responsive_dashboard_1440w.png | PASS | 2026-09-12 | AUDIT-RUN-20260912-154532 |
| CHK-104 | 1920x1080 | PLAYWRIGHT_VISUAL_RESPONSIVE | 13.1 Systematic 11-breakpoint responsive rendering | 12_responsive_and_a11y.spec.ts | 1920x1080 | 12_responsive_dashboard_320w.png, 12_responsive_dashboard_390w.png, 12_responsive_dashboard_768w.png, 12_responsive_dashboard_1440w.png | PASS | 2026-09-12 | AUDIT-RUN-20260912-154532 |
| CHK-105 | No horizontal overflow | PLAYWRIGHT_VISUAL_RESPONSIVE | 13.1 Core workspaces responsive check | 12_responsive_and_a11y.spec.ts | No horizontal overflow | 12_responsive_dashboard_320w.png | PASS | 2026-09-12 | AUDIT-RUN-20260912-154532 |
| CHK-106 | No clipping | PLAYWRIGHT_VISUAL_RESPONSIVE | 4. RESPONSIVE / VISUAL / INTERACTION AUDIT | tests/Feature/ pending mapping | No clipping | pending | PARTIAL | NOT EXECUTED | PENDING |
| CHK-107 | No inaccessible controls | PLAYWRIGHT_VISUAL_RESPONSIVE | 4. RESPONSIVE / VISUAL / INTERACTION AUDIT | tests/Feature/ pending mapping | No inaccessible controls | pending | PARTIAL | NOT EXECUTED | PENDING |
| CHK-108 | Tables become appropriate cards/scroll containers | PLAYWRIGHT_VISUAL_RESPONSIVE | 13.1 Core workspaces responsive check | 12_responsive_and_a11y.spec.ts | Tables become appropriate cards/scroll containers | 12_responsive_dashboard_320w.png | PASS | 2026-09-12 | AUDIT-RUN-20260912-154532 |
| CHK-109 | Sticky actions remain usable | PLAYWRIGHT_VISUAL_RESPONSIVE | 4. RESPONSIVE / VISUAL / INTERACTION AUDIT | tests/Feature/ pending mapping | Sticky actions remain usable | pending | PARTIAL | NOT EXECUTED | PENDING |
| CHK-110 | Modals stay inside viewport | PLAYWRIGHT_VISUAL_RESPONSIVE | 4. RESPONSIVE / VISUAL / INTERACTION AUDIT | tests/Feature/ pending mapping | Modals stay inside viewport | pending | PARTIAL | NOT EXECUTED | PENDING |
| CHK-111 | Sheets/drawers work | PLAYWRIGHT_VISUAL_RESPONSIVE | 4. RESPONSIVE / VISUAL / INTERACTION AUDIT | tests/Feature/ pending mapping | Sheets/drawers work | pending | PARTIAL | NOT EXECUTED | PENDING |
| CHK-112 | Dropdowns/comboboxes work | PLAYWRIGHT_VISUAL_RESPONSIVE | 4. RESPONSIVE / VISUAL / INTERACTION AUDIT | tests/Feature/ pending mapping | Dropdowns/comboboxes work | pending | PARTIAL | NOT EXECUTED | PENDING |
| CHK-113 | Date pickers work | PLAYWRIGHT_VISUAL_RESPONSIVE | 4. RESPONSIVE / VISUAL / INTERACTION AUDIT | tests/Feature/ pending mapping | Date pickers work | pending | PARTIAL | NOT EXECUTED | PENDING |
| CHK-114 | Text truncation is intentional | PLAYWRIGHT_VISUAL_RESPONSIVE | 4. RESPONSIVE / VISUAL / INTERACTION AUDIT | tests/Feature/ pending mapping | Text truncation is intentional | pending | PARTIAL | NOT EXECUTED | PENDING |
| CHK-115 | Touch targets are usable | PLAYWRIGHT_VISUAL_RESPONSIVE | 4. RESPONSIVE / VISUAL / INTERACTION AUDIT | tests/Feature/ pending mapping | Touch targets are usable | pending | PARTIAL | NOT EXECUTED | PENDING |
| CHK-116 | Keyboard focus is visible | PLAYWRIGHT_VISUAL_RESPONSIVE | 4. RESPONSIVE / VISUAL / INTERACTION AUDIT | tests/Feature/ pending mapping | Keyboard focus is visible | pending | PARTIAL | NOT EXECUTED | PENDING |
| CHK-117 | Focus order is sensible | PLAYWRIGHT_VISUAL_RESPONSIVE | 4. RESPONSIVE / VISUAL / INTERACTION AUDIT | tests/Feature/ pending mapping | Focus order is sensible | pending | PARTIAL | NOT EXECUTED | PENDING |
| CHK-118 | Escape closes dialogs where expected | PLAYWRIGHT_VISUAL_RESPONSIVE | 4. RESPONSIVE / VISUAL / INTERACTION AUDIT | tests/Feature/ pending mapping | Escape closes dialogs where expected | pending | PARTIAL | NOT EXECUTED | PENDING |
| CHK-119 | Loading indicators appear | PLAYWRIGHT_VISUAL_RESPONSIVE | 4. RESPONSIVE / VISUAL / INTERACTION AUDIT | tests/Feature/ pending mapping | Loading indicators appear | pending | PARTIAL | NOT EXECUTED | PENDING |
| CHK-120 | Errors remain readable | PLAYWRIGHT_VISUAL_RESPONSIVE | 4. RESPONSIVE / VISUAL / INTERACTION AUDIT | tests/Feature/ pending mapping | Errors remain readable | pending | PARTIAL | NOT EXECUTED | PENDING |
| CHK-121 | Success feedback remains visible | PLAYWRIGHT_VISUAL_RESPONSIVE | 4. RESPONSIVE / VISUAL / INTERACTION AUDIT | tests/Feature/ pending mapping | Success feedback remains visible | pending | PARTIAL | NOT EXECUTED | PENDING |
| CHK-122 | No layout shift causes accidental clicks | PLAYWRIGHT_VISUAL_RESPONSIVE | 4. RESPONSIVE / VISUAL / INTERACTION AUDIT | tests/Feature/ pending mapping | No layout shift causes accidental clicks | pending | PARTIAL | NOT EXECUTED | PENDING |
| CHK-123 | Tab order | PLAYWRIGHT_A11Y | 13.2 Keyboard accessibility | 12_responsive_and_a11y.spec.ts | Tab order | DOM focus evaluations | PASS | 2026-09-12 | AUDIT-RUN-20260912-154532 |
| CHK-124 | Visible focus | PLAYWRIGHT_A11Y | 13.2 Keyboard accessibility | 12_responsive_and_a11y.spec.ts | Visible focus | DOM focus evaluations | PASS | 2026-09-12 | AUDIT-RUN-20260912-154532 |
| CHK-125 | Keyboard operation | PLAYWRIGHT_A11Y | 5. ACCESSIBILITY / KEYBOARD | tests/Feature/ pending mapping | Keyboard operation | pending | NOT_TESTED | NOT EXECUTED | PENDING |
| CHK-126 | Modal focus trap | PLAYWRIGHT_A11Y | 5. ACCESSIBILITY / KEYBOARD | tests/Feature/ pending mapping | Modal focus trap | pending | NOT_TESTED | NOT EXECUTED | PENDING |
| CHK-127 | Modal focus return | PLAYWRIGHT_A11Y | 5. ACCESSIBILITY / KEYBOARD | tests/Feature/ pending mapping | Modal focus return | pending | NOT_TESTED | NOT EXECUTED | PENDING |
| CHK-128 | Drawer focus behavior | PLAYWRIGHT_A11Y | 5. ACCESSIBILITY / KEYBOARD | tests/Feature/ pending mapping | Drawer focus behavior | pending | NOT_TESTED | NOT EXECUTED | PENDING |
| CHK-129 | Accessible names | PLAYWRIGHT_A11Y | 5. ACCESSIBILITY / KEYBOARD | tests/Feature/ pending mapping | Accessible names | pending | NOT_TESTED | NOT EXECUTED | PENDING |
| CHK-130 | Form labels | PLAYWRIGHT_A11Y | 5. ACCESSIBILITY / KEYBOARD | tests/Feature/ pending mapping | Form labels | pending | NOT_TESTED | NOT EXECUTED | PENDING |
| CHK-131 | Error association | PLAYWRIGHT_A11Y | 5. ACCESSIBILITY / KEYBOARD | tests/Feature/ pending mapping | Error association | pending | NOT_TESTED | NOT EXECUTED | PENDING |
| CHK-132 | Required field announcement/semantics | PLAYWRIGHT_A11Y | 5. ACCESSIBILITY / KEYBOARD | tests/Feature/ pending mapping | Required field announcement/semantics | pending | NOT_TESTED | NOT EXECUTED | PENDING |
| CHK-133 | Button vs link semantics | PLAYWRIGHT_A11Y | 5. ACCESSIBILITY / KEYBOARD | tests/Feature/ pending mapping | Button vs link semantics | pending | NOT_TESTED | NOT EXECUTED | PENDING |
| CHK-134 | Disabled vs read-only semantics | PLAYWRIGHT_A11Y | 5. ACCESSIBILITY / KEYBOARD | tests/Feature/ pending mapping | Disabled vs read-only semantics | pending | NOT_TESTED | NOT EXECUTED | PENDING |
| CHK-135 | Table headers | PLAYWRIGHT_A11Y | 5. ACCESSIBILITY / KEYBOARD | tests/Feature/ pending mapping | Table headers | pending | NOT_TESTED | NOT EXECUTED | PENDING |
| CHK-136 | Status not conveyed by color alone | PLAYWRIGHT_A11Y | 5. ACCESSIBILITY / KEYBOARD | tests/Feature/ pending mapping | Status not conveyed by color alone | pending | NOT_TESTED | NOT EXECUTED | PENDING |
| CHK-137 | Sufficient text contrast | PLAYWRIGHT_A11Y | 5. ACCESSIBILITY / KEYBOARD | tests/Feature/ pending mapping | Sufficient text contrast | pending | NOT_TESTED | NOT EXECUTED | PENDING |
| CHK-138 | Screen-size responsive text | PLAYWRIGHT_A11Y | 5. ACCESSIBILITY / KEYBOARD | tests/Feature/ pending mapping | Screen-size responsive text | pending | NOT_TESTED | NOT EXECUTED | PENDING |
| CHK-139 | No keyboard traps | PLAYWRIGHT_A11Y | 5. ACCESSIBILITY / KEYBOARD | tests/Feature/ pending mapping | No keyboard traps | pending | NOT_TESTED | NOT EXECUTED | PENDING |
| CHK-140 | SUPER_ADMIN | PLAYWRIGHT_E2E | 6. AUTHENTICATION / SESSION / ACCESS | tests/Feature/ pending mapping | SUPER_ADMIN | pending | PARTIAL | NOT EXECUTED | PENDING |
| CHK-141 | ADMIN | PLAYWRIGHT_E2E | 6. AUTHENTICATION / SESSION / ACCESS | tests/Feature/ pending mapping | ADMIN | pending | PARTIAL | NOT EXECUTED | PENDING |
| CHK-142 | ACCOUNTANT | PLAYWRIGHT_E2E | 6. AUTHENTICATION / SESSION / ACCESS | tests/Feature/ pending mapping | ACCOUNTANT | pending | PARTIAL | NOT EXECUTED | PENDING |
| CHK-143 | SALESMAN | PLAYWRIGHT_E2E | 6. AUTHENTICATION / SESSION / ACCESS | tests/Feature/ pending mapping | SALESMAN | pending | PARTIAL | NOT EXECUTED | PENDING |
| CHK-144 | SALESMAN_B | PLAYWRIGHT_E2E | 6. AUTHENTICATION / SESSION / ACCESS | tests/Feature/ pending mapping | SALESMAN_B | pending | PARTIAL | NOT EXECUTED | PENDING |
| CHK-145 | WAREHOUSE_MANAGER | PLAYWRIGHT_E2E | 6. AUTHENTICATION / SESSION / ACCESS | tests/Feature/ pending mapping | WAREHOUSE_MANAGER | pending | PARTIAL | NOT EXECUTED | PENDING |
| CHK-146 | DELIVERY_PARTNER | PLAYWRIGHT_E2E | 6. AUTHENTICATION / SESSION / ACCESS | tests/Feature/ pending mapping | DELIVERY_PARTNER | pending | PARTIAL | NOT EXECUTED | PENDING |
| CHK-147 | SUSPENDED / DISABLED test identity | PLAYWRIGHT_E2E | 6. AUTHENTICATION / SESSION / ACCESS | tests/Feature/ pending mapping | SUSPENDED / DISABLED test identity | pending | PARTIAL | NOT EXECUTED | PENDING |
| CHK-148 | Valid login | PLAYWRIGHT_E2E | 6. AUTHENTICATION / SESSION / ACCESS > 6.1 Authentication | tests/Feature/ pending mapping | Valid login | pending | PARTIAL | NOT EXECUTED | PENDING |
| CHK-149 | Invalid email | PLAYWRIGHT_E2E | 6. AUTHENTICATION / SESSION / ACCESS > 6.1 Authentication | tests/Feature/ pending mapping | Invalid email | pending | PARTIAL | NOT EXECUTED | PENDING |
| CHK-150 | Invalid password | PLAYWRIGHT_E2E | 6. AUTHENTICATION / SESSION / ACCESS > 6.1 Authentication | tests/Feature/ pending mapping | Invalid password | pending | PARTIAL | NOT EXECUTED | PENDING |
| CHK-151 | Empty credentials | PLAYWRIGHT_E2E | 6. AUTHENTICATION / SESSION / ACCESS > 6.1 Authentication | tests/Feature/ pending mapping | Empty credentials | pending | PARTIAL | NOT EXECUTED | PENDING |
| CHK-152 | Throttling | PLAYWRIGHT_E2E | 6. AUTHENTICATION / SESSION / ACCESS > 6.1 Authentication | tests/Feature/ pending mapping | Throttling | pending | PARTIAL | NOT EXECUTED | PENDING |
| CHK-153 | MFA prompt | PLAYWRIGHT_E2E | MFA TOTP helper integration | tests/browser/helpers/auth.ts | MFA prompt | totp.ts / database seed | PARTIAL | NOT EXECUTED | PENDING |
| CHK-154 | Correct TOTP | PLAYWRIGHT_E2E | 6. AUTHENTICATION / SESSION / ACCESS > 6.1 Authentication | tests/Feature/ pending mapping | Correct TOTP | pending | PARTIAL | NOT EXECUTED | PENDING |
| CHK-155 | Incorrect TOTP | PLAYWRIGHT_E2E | 6. AUTHENTICATION / SESSION / ACCESS > 6.1 Authentication | tests/Feature/ pending mapping | Incorrect TOTP | pending | PARTIAL | NOT EXECUTED | PENDING |
| CHK-156 | Repeated incorrect TOTP | PLAYWRIGHT_E2E | 6. AUTHENTICATION / SESSION / ACCESS > 6.1 Authentication | tests/Feature/ pending mapping | Repeated incorrect TOTP | pending | PARTIAL | NOT EXECUTED | PENDING |
| CHK-157 | Logout | PLAYWRIGHT_E2E | 6. AUTHENTICATION / SESSION / ACCESS > 6.1 Authentication | tests/Feature/ pending mapping | Logout | pending | PARTIAL | NOT EXECUTED | PENDING |
| CHK-158 | Browser session persistence | PLAYWRIGHT_E2E | 6. AUTHENTICATION / SESSION / ACCESS > 6.1 Authentication | tests/Feature/ pending mapping | Browser session persistence | pending | PARTIAL | NOT EXECUTED | PENDING |
| CHK-159 | Session expiry | PLAYWRIGHT_E2E | 6. AUTHENTICATION / SESSION / ACCESS > 6.1 Authentication | tests/Feature/ pending mapping | Session expiry | pending | PARTIAL | NOT EXECUTED | PENDING |
| CHK-160 | Session revocation | PLAYWRIGHT_E2E | 6. AUTHENTICATION / SESSION / ACCESS > 6.1 Authentication | tests/Feature/ pending mapping | Session revocation | pending | PARTIAL | NOT EXECUTED | PENDING |
| CHK-161 | Suspended account rejected | PLAYWRIGHT_E2E | 6. AUTHENTICATION / SESSION / ACCESS > 6.1 Authentication | tests/Feature/ pending mapping | Suspended account rejected | pending | PARTIAL | NOT EXECUTED | PENDING |
| CHK-162 | Disabled account rejected | PLAYWRIGHT_E2E | 6. AUTHENTICATION / SESSION / ACCESS > 6.1 Authentication | tests/Feature/ pending mapping | Disabled account rejected | pending | PARTIAL | NOT EXECUTED | PENDING |
| CHK-163 | Correct role landing | HTTP_API_SECURITY | 6. AUTHENTICATION / SESSION / ACCESS > 6.1 Authentication | tests/Feature/ pending mapping | Correct role landing | pending | PARTIAL | NOT EXECUTED | PENDING |
| CHK-164 | Redirect after login | PLAYWRIGHT_E2E | 6. AUTHENTICATION / SESSION / ACCESS > 6.1 Authentication | tests/Feature/ pending mapping | Redirect after login | pending | PARTIAL | NOT EXECUTED | PENDING |
| CHK-165 | Unauthorized direct route | HTTP_API_SECURITY | 6. AUTHENTICATION / SESSION / ACCESS > 6.1 Authentication | tests/Feature/ pending mapping | Unauthorized direct route | pending | PARTIAL | NOT EXECUTED | PENDING |
| CHK-166 | Permission denial | PLAYWRIGHT_E2E | 6. AUTHENTICATION / SESSION / ACCESS > 6.1 Authentication | tests/Feature/ pending mapping | Permission denial | pending | PARTIAL | NOT EXECUTED | PENDING |
| CHK-167 | Resource-scope denial | PLAYWRIGHT_E2E | 6. AUTHENTICATION / SESSION / ACCESS > 6.1 Authentication | tests/Feature/ pending mapping | Resource-scope denial | pending | PARTIAL | NOT EXECUTED | PENDING |
| CHK-168 | IDOR attempt | HTTP_API_SECURITY | 6. AUTHENTICATION / SESSION / ACCESS > 6.1 Authentication | tests/Feature/ pending mapping | IDOR attempt | pending | PARTIAL | NOT EXECUTED | PENDING |
| CHK-169 | User enumeration resistance | PLAYWRIGHT_E2E | 6. AUTHENTICATION / SESSION / ACCESS > 6.1 Authentication | tests/Feature/ pending mapping | User enumeration resistance | pending | PARTIAL | NOT EXECUTED | PENDING |
| CHK-170 | Password reset request | PLAYWRIGHT_E2E | 6. AUTHENTICATION / SESSION / ACCESS > 6.1 Authentication | tests/Feature/ pending mapping | Password reset request | pending | PARTIAL | NOT EXECUTED | PENDING |
| CHK-171 | Reset token behavior | HTTP_API_SECURITY | 6. AUTHENTICATION / SESSION / ACCESS > 6.1 Authentication | tests/Feature/ pending mapping | Reset token behavior | pending | PARTIAL | NOT EXECUTED | PENDING |
| CHK-172 | Password change | PLAYWRIGHT_E2E | 6. AUTHENTICATION / SESSION / ACCESS > 6.1 Authentication | tests/Feature/ pending mapping | Password change | pending | PARTIAL | NOT EXECUTED | PENDING |
| CHK-173 | MFA settings | PLAYWRIGHT_E2E | MFA TOTP helper integration | tests/browser/helpers/auth.ts | MFA settings | totp.ts / database seed | PARTIAL | NOT EXECUTED | PENDING |
| CHK-174 | MFA recovery behavior | PLAYWRIGHT_E2E | MFA TOTP helper integration | tests/browser/helpers/auth.ts | MFA recovery behavior | totp.ts / database seed | PARTIAL | NOT EXECUTED | PENDING |
| CHK-175 | Session cookie flags | PLAYWRIGHT_E2E | 6. AUTHENTICATION / SESSION / ACCESS > 6.2 Session security | tests/Feature/ pending mapping | Session cookie flags | pending | PARTIAL | NOT EXECUTED | PENDING |
| CHK-176 | CSRF behavior | PLAYWRIGHT_E2E | 6. AUTHENTICATION / SESSION / ACCESS > 6.2 Session security | tests/Feature/ pending mapping | CSRF behavior | pending | PARTIAL | NOT EXECUTED | PENDING |
| CHK-177 | Session fixation protection | PLAYWRIGHT_E2E | 6. AUTHENTICATION / SESSION / ACCESS > 6.2 Session security | tests/Feature/ pending mapping | Session fixation protection | pending | PARTIAL | NOT EXECUTED | PENDING |
| CHK-178 | Logout invalidates protected session | PLAYWRIGHT_E2E | 6. AUTHENTICATION / SESSION / ACCESS > 6.2 Session security | tests/Feature/ pending mapping | Logout invalidates protected session | pending | PARTIAL | NOT EXECUTED | PENDING |
| CHK-179 | Revoked session cannot access protected page | PLAYWRIGHT_E2E | 6. AUTHENTICATION / SESSION / ACCESS > 6.2 Session security | tests/Feature/ pending mapping | Revoked session cannot access protected page | pending | PARTIAL | NOT EXECUTED | PENDING |
| CHK-180 | Open old tab after logout | PLAYWRIGHT_E2E | 6. AUTHENTICATION / SESSION / ACCESS > 6.2 Session security | tests/Feature/ pending mapping | Open old tab after logout | pending | PARTIAL | NOT EXECUTED | PENDING |
| CHK-181 | Open deep link after logout | PLAYWRIGHT_E2E | 6. AUTHENTICATION / SESSION / ACCESS > 6.2 Session security | tests/Feature/ pending mapping | Open deep link after logout | pending | PARTIAL | NOT EXECUTED | PENDING |
| CHK-182 | Open create | PLAYWRIGHT_E2E | 7. CUSTOMER MANAGEMENT > 7.1 Create | tests/Feature/ pending mapping | Open create | pending | NOT_TESTED | NOT EXECUTED | PENDING |
| CHK-183 | Business/customer name | PLAYWRIGHT_E2E | 7. CUSTOMER MANAGEMENT > 7.1 Create | tests/Feature/ pending mapping | Business/customer name | pending | NOT_TESTED | NOT EXECUTED | PENDING |
| CHK-184 | Contact | PLAYWRIGHT_E2E | 7. CUSTOMER MANAGEMENT > 7.1 Create | tests/Feature/ pending mapping | Contact | pending | NOT_TESTED | NOT EXECUTED | PENDING |
| CHK-185 | Phone | PLAYWRIGHT_E2E | 7. CUSTOMER MANAGEMENT > 7.1 Create | tests/Feature/ pending mapping | Phone | pending | NOT_TESTED | NOT EXECUTED | PENDING |
| CHK-186 | Email | PLAYWRIGHT_E2E | 7. CUSTOMER MANAGEMENT > 7.1 Create | tests/Feature/ pending mapping | Email | pending | NOT_TESTED | NOT EXECUTED | PENDING |
| CHK-187 | Billing address | PLAYWRIGHT_E2E | 7. CUSTOMER MANAGEMENT > 7.1 Create | tests/Feature/ pending mapping | Billing address | pending | NOT_TESTED | NOT EXECUTED | PENDING |
| CHK-188 | Delivery address | PLAYWRIGHT_E2E | 7. CUSTOMER MANAGEMENT > 7.1 Create | tests/Feature/ pending mapping | Delivery address | pending | NOT_TESTED | NOT EXECUTED | PENDING |
| CHK-189 | Credit limit | POSTGRES_DB_INVARIANT | 7. CUSTOMER MANAGEMENT > 7.1 Create | tests/Feature/ pending mapping | Credit limit | pending | NOT_TESTED | NOT EXECUTED | PENDING |
| CHK-190 | Payment terms | PLAYWRIGHT_E2E | 7. CUSTOMER MANAGEMENT > 7.1 Create | tests/Feature/ pending mapping | Payment terms | pending | NOT_TESTED | NOT EXECUTED | PENDING |
| CHK-191 | Status | PLAYWRIGHT_E2E | 7. CUSTOMER MANAGEMENT > 7.1 Create | tests/Feature/ pending mapping | Status | pending | NOT_TESTED | NOT EXECUTED | PENDING |
| CHK-192 | Salesman assignment | PLAYWRIGHT_E2E | 7. CUSTOMER MANAGEMENT > 7.1 Create | tests/Feature/ pending mapping | Salesman assignment | pending | NOT_TESTED | NOT EXECUTED | PENDING |
| CHK-193 | Required validation | PLAYWRIGHT_E2E | 7. CUSTOMER MANAGEMENT > 7.1 Create | tests/Feature/ pending mapping | Required validation | pending | NOT_TESTED | NOT EXECUTED | PENDING |
| CHK-194 | Invalid formats | PLAYWRIGHT_E2E | 7. CUSTOMER MANAGEMENT > 7.1 Create | tests/Feature/ pending mapping | Invalid formats | pending | NOT_TESTED | NOT EXECUTED | PENDING |
| CHK-195 | Boundary values | PLAYWRIGHT_E2E | 7. CUSTOMER MANAGEMENT > 7.1 Create | tests/Feature/ pending mapping | Boundary values | pending | NOT_TESTED | NOT EXECUTED | PENDING |
| CHK-196 | Save | PLAYWRIGHT_E2E | 7. CUSTOMER MANAGEMENT > 7.1 Create | tests/Feature/ pending mapping | Save | pending | NOT_TESTED | NOT EXECUTED | PENDING |
| CHK-197 | Duplicate/unique behavior | PLAYWRIGHT_E2E | 7. CUSTOMER MANAGEMENT > 7.1 Create | tests/Feature/ pending mapping | Duplicate/unique behavior | pending | NOT_TESTED | NOT EXECUTED | PENDING |
| CHK-198 | Confirmation | PLAYWRIGHT_E2E | 7. CUSTOMER MANAGEMENT > 7.1 Create | tests/Feature/ pending mapping | Confirmation | pending | NOT_TESTED | NOT EXECUTED | PENDING |
| CHK-199 | Created record appears | PLAYWRIGHT_E2E | 7. CUSTOMER MANAGEMENT > 7.1 Create | tests/Feature/ pending mapping | Created record appears | pending | NOT_TESTED | NOT EXECUTED | PENDING |
| CHK-200 | Audit event created | PLAYWRIGHT_E2E | 7. CUSTOMER MANAGEMENT > 7.1 Create | tests/Feature/ pending mapping | Audit event created | pending | NOT_TESTED | NOT EXECUTED | PENDING |
| CHK-201 | Edit | PLAYWRIGHT_E2E | 7. CUSTOMER MANAGEMENT > 7.2 Manage | tests/Feature/ pending mapping | Edit | pending | NOT_TESTED | NOT EXECUTED | PENDING |
| CHK-202 | Search | PLAYWRIGHT_E2E | 7. CUSTOMER MANAGEMENT > 7.2 Manage | tests/Feature/ pending mapping | Search | pending | NOT_TESTED | NOT EXECUTED | PENDING |
| CHK-203 | Filters | PLAYWRIGHT_E2E | 7. CUSTOMER MANAGEMENT > 7.2 Manage | tests/Feature/ pending mapping | Filters | pending | NOT_TESTED | NOT EXECUTED | PENDING |
| CHK-204 | Detail | PLAYWRIGHT_E2E | 7. CUSTOMER MANAGEMENT > 7.2 Manage | tests/Feature/ pending mapping | Detail | pending | NOT_TESTED | NOT EXECUTED | PENDING |
| CHK-205 | Order history | PLAYWRIGHT_E2E | 7. CUSTOMER MANAGEMENT > 7.2 Manage | tests/Feature/ pending mapping | Order history | pending | NOT_TESTED | NOT EXECUTED | PENDING |
| CHK-206 | Payment history | PLAYWRIGHT_E2E | 7. CUSTOMER MANAGEMENT > 7.2 Manage | tests/Feature/ pending mapping | Payment history | pending | NOT_TESTED | NOT EXECUTED | PENDING |
| CHK-207 | Outstanding | PLAYWRIGHT_E2E | 7. CUSTOMER MANAGEMENT > 7.2 Manage | tests/Feature/ pending mapping | Outstanding | pending | NOT_TESTED | NOT EXECUTED | PENDING |
| CHK-208 | Aging | PLAYWRIGHT_E2E | 7. CUSTOMER MANAGEMENT > 7.2 Manage | tests/Feature/ pending mapping | Aging | pending | NOT_TESTED | NOT EXECUTED | PENDING |
| CHK-209 | Statement | PLAYWRIGHT_E2E | 7. CUSTOMER MANAGEMENT > 7.2 Manage | tests/Feature/ pending mapping | Statement | pending | NOT_TESTED | NOT EXECUTED | PENDING |
| CHK-210 | Credits/refunds | POSTGRES_DB_INVARIANT | 7. CUSTOMER MANAGEMENT > 7.2 Manage | tests/Feature/ pending mapping | Credits/refunds | pending | NOT_TESTED | NOT EXECUTED | PENDING |
| CHK-211 | Adjustments | PLAYWRIGHT_E2E | 7. CUSTOMER MANAGEMENT > 7.2 Manage | tests/Feature/ pending mapping | Adjustments | pending | NOT_TESTED | NOT EXECUTED | PENDING |
| CHK-212 | ACTIVE | PLAYWRIGHT_E2E | 7. CUSTOMER MANAGEMENT > 7.3 Lifecycle | tests/Feature/ pending mapping | ACTIVE | pending | NOT_TESTED | NOT EXECUTED | PENDING |
| CHK-213 | ON_HOLD | PLAYWRIGHT_E2E | 7. CUSTOMER MANAGEMENT > 7.3 Lifecycle | tests/Feature/ pending mapping | ON_HOLD | pending | NOT_TESTED | NOT EXECUTED | PENDING |
| CHK-214 | INACTIVE | PLAYWRIGHT_E2E | 7. CUSTOMER MANAGEMENT > 7.3 Lifecycle | tests/Feature/ pending mapping | INACTIVE | pending | NOT_TESTED | NOT EXECUTED | PENDING |
| CHK-215 | New-order restriction | PLAYWRIGHT_E2E | 7. CUSTOMER MANAGEMENT > 7.3 Lifecycle | tests/Feature/ pending mapping | New-order restriction | pending | NOT_TESTED | NOT EXECUTED | PENDING |
| CHK-216 | Historical access preserved | PLAYWRIGHT_E2E | 7. CUSTOMER MANAGEMENT > 7.3 Lifecycle | tests/Feature/ pending mapping | Historical access preserved | pending | NOT_TESTED | NOT EXECUTED | PENDING |
| CHK-217 | Assign salesman | PLAYWRIGHT_E2E | 7. CUSTOMER MANAGEMENT > 7.3 Lifecycle | tests/Feature/ pending mapping | Assign salesman | pending | NOT_TESTED | NOT EXECUTED | PENDING |
| CHK-218 | Reassign | PLAYWRIGHT_E2E | 7. CUSTOMER MANAGEMENT > 7.3 Lifecycle | tests/Feature/ pending mapping | Reassign | pending | NOT_TESTED | NOT EXECUTED | PENDING |
| CHK-219 | Unassign | PLAYWRIGHT_E2E | 7. CUSTOMER MANAGEMENT > 7.3 Lifecycle | tests/Feature/ pending mapping | Unassign | pending | NOT_TESTED | NOT EXECUTED | PENDING |
| CHK-220 | Salesman A can see assigned customer | PLAYWRIGHT_E2E | 3.2 Salesman customer scoping and cross-salesman anti-IDOR | 02_customers_and_scoping.spec.ts | Salesman A can see assigned customer | 02_salesman_a_customer_list.png | PASS | 2026-09-12 | AUDIT-RUN-20260912-154532 |
| CHK-221 | Salesman B cannot see customer | PLAYWRIGHT_E2E | 7. CUSTOMER MANAGEMENT > 7.3 Lifecycle | tests/Feature/ pending mapping | Salesman B cannot see customer | pending | NOT_TESTED | NOT EXECUTED | PENDING |
| CHK-222 | Reassignment affects future scope correctly | PLAYWRIGHT_E2E | 7. CUSTOMER MANAGEMENT > 7.3 Lifecycle | tests/Feature/ pending mapping | Reassignment affects future scope correctly | pending | NOT_TESTED | NOT EXECUTED | PENDING |
| CHK-223 | Existing historical records remain valid | PLAYWRIGHT_E2E | 7. CUSTOMER MANAGEMENT > 7.3 Lifecycle | tests/Feature/ pending mapping | Existing historical records remain valid | pending | NOT_TESTED | NOT EXECUTED | PENDING |
| CHK-224 | Create | PLAYWRIGHT_E2E | 8. PRODUCT / CATEGORY MANAGEMENT > 8.1 Categories | tests/Feature/ pending mapping | Create | pending | NOT_TESTED | NOT EXECUTED | PENDING |
| CHK-225 | Edit | PLAYWRIGHT_E2E | 8. PRODUCT / CATEGORY MANAGEMENT > 8.1 Categories | tests/Feature/ pending mapping | Edit | pending | NOT_TESTED | NOT EXECUTED | PENDING |
| CHK-226 | Search | PLAYWRIGHT_E2E | 8. PRODUCT / CATEGORY MANAGEMENT > 8.1 Categories | tests/Feature/ pending mapping | Search | pending | NOT_TESTED | NOT EXECUTED | PENDING |
| CHK-227 | Filter | PLAYWRIGHT_E2E | 8. PRODUCT / CATEGORY MANAGEMENT > 8.1 Categories | tests/Feature/ pending mapping | Filter | pending | NOT_TESTED | NOT EXECUTED | PENDING |
| CHK-228 | Assign products | PLAYWRIGHT_E2E | 8. PRODUCT / CATEGORY MANAGEMENT > 8.1 Categories | tests/Feature/ pending mapping | Assign products | pending | NOT_TESTED | NOT EXECUTED | PENDING |
| CHK-229 | Category containing products handled safely | PLAYWRIGHT_E2E | 8. PRODUCT / CATEGORY MANAGEMENT > 8.1 Categories | tests/Feature/ pending mapping | Category containing products handled safely | pending | NOT_TESTED | NOT EXECUTED | PENDING |
| CHK-230 | Empty category | PLAYWRIGHT_E2E | 8. PRODUCT / CATEGORY MANAGEMENT > 8.1 Categories | tests/Feature/ pending mapping | Empty category | pending | NOT_TESTED | NOT EXECUTED | PENDING |
| CHK-231 | Duplicate category handling | PLAYWRIGHT_E2E | 8. PRODUCT / CATEGORY MANAGEMENT > 8.1 Categories | tests/Feature/ pending mapping | Duplicate category handling | pending | NOT_TESTED | NOT EXECUTED | PENDING |
| CHK-232 | Deactivation behavior | PLAYWRIGHT_E2E | 8. PRODUCT / CATEGORY MANAGEMENT > 8.1 Categories | tests/Feature/ pending mapping | Deactivation behavior | pending | NOT_TESTED | NOT EXECUTED | PENDING |
| CHK-233 | Create | PLAYWRIGHT_E2E | 8. PRODUCT / CATEGORY MANAGEMENT > 8.2 Products | tests/Feature/ pending mapping | Create | pending | NOT_TESTED | NOT EXECUTED | PENDING |
| CHK-234 | Edit | PLAYWRIGHT_E2E | 8. PRODUCT / CATEGORY MANAGEMENT > 8.2 Products | tests/Feature/ pending mapping | Edit | pending | NOT_TESTED | NOT EXECUTED | PENDING |
| CHK-235 | SKU | PLAYWRIGHT_E2E | 8. PRODUCT / CATEGORY MANAGEMENT > 8.2 Products | tests/Feature/ pending mapping | SKU | pending | NOT_TESTED | NOT EXECUTED | PENDING |
| CHK-236 | Name | PLAYWRIGHT_E2E | 8. PRODUCT / CATEGORY MANAGEMENT > 8.2 Products | tests/Feature/ pending mapping | Name | pending | NOT_TESTED | NOT EXECUTED | PENDING |
| CHK-237 | Description | PLAYWRIGHT_E2E | 8. PRODUCT / CATEGORY MANAGEMENT > 8.2 Products | tests/Feature/ pending mapping | Description | pending | NOT_TESTED | NOT EXECUTED | PENDING |
| CHK-238 | Category | PLAYWRIGHT_E2E | 8. PRODUCT / CATEGORY MANAGEMENT > 8.2 Products | tests/Feature/ pending mapping | Category | pending | NOT_TESTED | NOT EXECUTED | PENDING |
| CHK-239 | Cost | PLAYWRIGHT_E2E | 8. PRODUCT / CATEGORY MANAGEMENT > 8.2 Products | tests/Feature/ pending mapping | Cost | pending | NOT_TESTED | NOT EXECUTED | PENDING |
| CHK-240 | MRP/List | PLAYWRIGHT_E2E | 8. PRODUCT / CATEGORY MANAGEMENT > 8.2 Products | tests/Feature/ pending mapping | MRP/List | pending | NOT_TESTED | NOT EXECUTED | PENDING |
| CHK-241 | Selling price | PLAYWRIGHT_E2E | 8. PRODUCT / CATEGORY MANAGEMENT > 8.2 Products | tests/Feature/ pending mapping | Selling price | pending | NOT_TESTED | NOT EXECUTED | PENDING |
| CHK-242 | Minimum allowed price | PLAYWRIGHT_E2E | 8. PRODUCT / CATEGORY MANAGEMENT > 8.2 Products | tests/Feature/ pending mapping | Minimum allowed price | pending | NOT_TESTED | NOT EXECUTED | PENDING |
| CHK-243 | Tax | DOMAIN_UNIT_PHP | 8. PRODUCT / CATEGORY MANAGEMENT > 8.2 Products | tests/Feature/ pending mapping | Tax | pending | NOT_TESTED | NOT EXECUTED | PENDING |
| CHK-244 | Inventory | PLAYWRIGHT_E2E | 8. PRODUCT / CATEGORY MANAGEMENT > 8.2 Products | tests/Feature/ pending mapping | Inventory | pending | NOT_TESTED | NOT EXECUTED | PENDING |
| CHK-245 | Active/inactive | PLAYWRIGHT_E2E | 8. PRODUCT / CATEGORY MANAGEMENT > 8.2 Products | tests/Feature/ pending mapping | Active/inactive | pending | NOT_TESTED | NOT EXECUTED | PENDING |
| CHK-246 | Search | PLAYWRIGHT_E2E | 8. PRODUCT / CATEGORY MANAGEMENT > 8.2 Products | tests/Feature/ pending mapping | Search | pending | NOT_TESTED | NOT EXECUTED | PENDING |
| CHK-247 | Filter | PLAYWRIGHT_E2E | 8. PRODUCT / CATEGORY MANAGEMENT > 8.2 Products | tests/Feature/ pending mapping | Filter | pending | NOT_TESTED | NOT EXECUTED | PENDING |
| CHK-248 | Pagination | PLAYWRIGHT_E2E | 8. PRODUCT / CATEGORY MANAGEMENT > 8.2 Products | tests/Feature/ pending mapping | Pagination | pending | NOT_TESTED | NOT EXECUTED | PENDING |
| CHK-249 | Status changes reflected in ordering | PLAYWRIGHT_E2E | 8. PRODUCT / CATEGORY MANAGEMENT > 8.2 Products | tests/Feature/ pending mapping | Status changes reflected in ordering | pending | NOT_TESTED | NOT EXECUTED | PENDING |
| CHK-250 | Upload valid image | PLAYWRIGHT_E2E | 8. PRODUCT / CATEGORY MANAGEMENT > 8.3 Product images | tests/Feature/ pending mapping | Upload valid image | pending | NOT_TESTED | NOT EXECUTED | PENDING |
| CHK-251 | Preview | PLAYWRIGHT_E2E | 8. PRODUCT / CATEGORY MANAGEMENT > 8.3 Product images | tests/Feature/ pending mapping | Preview | pending | NOT_TESTED | NOT EXECUTED | PENDING |
| CHK-252 | Replace | PLAYWRIGHT_E2E | 8. PRODUCT / CATEGORY MANAGEMENT > 8.3 Product images | tests/Feature/ pending mapping | Replace | pending | NOT_TESTED | NOT EXECUTED | PENDING |
| CHK-253 | Remove | PLAYWRIGHT_E2E | 8. PRODUCT / CATEGORY MANAGEMENT > 8.3 Product images | tests/Feature/ pending mapping | Remove | pending | NOT_TESTED | NOT EXECUTED | PENDING |
| CHK-254 | Drag/drop | PLAYWRIGHT_E2E | 8. PRODUCT / CATEGORY MANAGEMENT > 8.3 Product images | tests/Feature/ pending mapping | Drag/drop | pending | NOT_TESTED | NOT EXECUTED | PENDING |
| CHK-255 | Invalid extension | PLAYWRIGHT_E2E | 8. PRODUCT / CATEGORY MANAGEMENT > 8.3 Product images | tests/Feature/ pending mapping | Invalid extension | pending | NOT_TESTED | NOT EXECUTED | PENDING |
| CHK-256 | Invalid MIME | PLAYWRIGHT_E2E | 8. PRODUCT / CATEGORY MANAGEMENT > 8.3 Product images | tests/Feature/ pending mapping | Invalid MIME | pending | NOT_TESTED | NOT EXECUTED | PENDING |
| CHK-257 | Invalid magic bytes | PLAYWRIGHT_E2E | 8. PRODUCT / CATEGORY MANAGEMENT > 8.3 Product images | tests/Feature/ pending mapping | Invalid magic bytes | pending | NOT_TESTED | NOT EXECUTED | PENDING |
| CHK-258 | Oversize file | PLAYWRIGHT_E2E | 8. PRODUCT / CATEGORY MANAGEMENT > 8.3 Product images | tests/Feature/ pending mapping | Oversize file | pending | NOT_TESTED | NOT EXECUTED | PENDING |
| CHK-259 | Malformed image | PLAYWRIGHT_E2E | 8. PRODUCT / CATEGORY MANAGEMENT > 8.3 Product images | tests/Feature/ pending mapping | Malformed image | pending | NOT_TESTED | NOT EXECUTED | PENDING |
| CHK-260 | Dangerous filename | PLAYWRIGHT_E2E | 8. PRODUCT / CATEGORY MANAGEMENT > 8.3 Product images | tests/Feature/ pending mapping | Dangerous filename | pending | NOT_TESTED | NOT EXECUTED | PENDING |
| CHK-261 | Signed/private access works | PLAYWRIGHT_E2E | 8. PRODUCT / CATEGORY MANAGEMENT > 8.3 Product images | tests/Feature/ pending mapping | Signed/private access works | pending | NOT_TESTED | NOT EXECUTED | PENDING |
| CHK-262 | Unauthorized access blocked | HTTP_API_SECURITY | 8. PRODUCT / CATEGORY MANAGEMENT > 8.3 Product images | tests/Feature/ pending mapping | Unauthorized access blocked | pending | NOT_TESTED | NOT EXECUTED | PENDING |
| CHK-263 | Product image renders in catalog | PLAYWRIGHT_E2E | 8. PRODUCT / CATEGORY MANAGEMENT > 8.3 Product images | tests/Feature/ pending mapping | Product image renders in catalog | pending | NOT_TESTED | NOT EXECUTED | PENDING |
| CHK-264 | Invoice does not show product image | PLAYWRIGHT_E2E | 8. PRODUCT / CATEGORY MANAGEMENT > 8.3 Product images | tests/Feature/ pending mapping | Invoice does not show product image | pending | NOT_TESTED | NOT EXECUTED | PENDING |
| CHK-265 | Valid price selection | PLAYWRIGHT_E2E | 9. PRICING / TAX | tests/Feature/ pending mapping | Valid price selection | pending | PARTIAL | NOT EXECUTED | PENDING |
| CHK-266 | MRP ceiling | PLAYWRIGHT_E2E | 9. PRICING / TAX | tests/Feature/ pending mapping | MRP ceiling | pending | PARTIAL | NOT EXECUTED | PENDING |
| CHK-267 | Minimum-price enforcement | PLAYWRIGHT_E2E | 9. PRICING / TAX | tests/Feature/ pending mapping | Minimum-price enforcement | pending | PARTIAL | NOT EXECUTED | PENDING |
| CHK-268 | Price override permission | PLAYWRIGHT_E2E | 9. PRICING / TAX | tests/Feature/ pending mapping | Price override permission | pending | PARTIAL | NOT EXECUTED | PENDING |
| CHK-269 | Override reason | PLAYWRIGHT_E2E | 9. PRICING / TAX | tests/Feature/ pending mapping | Override reason | pending | PARTIAL | NOT EXECUTED | PENDING |
| CHK-270 | Unauthorized override blocked | HTTP_API_SECURITY | 9. PRICING / TAX | tests/Feature/ pending mapping | Unauthorized override blocked | pending | PARTIAL | NOT EXECUTED | PENDING |
| CHK-271 | Historical price preserved | PLAYWRIGHT_E2E | 9. PRICING / TAX | tests/Feature/ pending mapping | Historical price preserved | pending | PARTIAL | NOT EXECUTED | PENDING |
| CHK-272 | Product tax | DOMAIN_UNIT_PHP | 9. PRICING / TAX | tests/Feature/ pending mapping | Product tax | pending | PARTIAL | NOT EXECUTED | PENDING |
| CHK-273 | Mixed-tax order | DOMAIN_UNIT_PHP | 9. PRICING / TAX | tests/Feature/ pending mapping | Mixed-tax order | pending | PARTIAL | NOT EXECUTED | PENDING |
| CHK-274 | Line tax | DOMAIN_UNIT_PHP | 9. PRICING / TAX | tests/Feature/ pending mapping | Line tax | pending | PARTIAL | NOT EXECUTED | PENDING |
| CHK-275 | Tax snapshot | DOMAIN_UNIT_PHP | 9. PRICING / TAX | tests/Feature/ pending mapping | Tax snapshot | pending | PARTIAL | NOT EXECUTED | PENDING |
| CHK-276 | Historical tax preserved | DOMAIN_UNIT_PHP | 9. PRICING / TAX | tests/Feature/ pending mapping | Historical tax preserved | pending | PARTIAL | NOT EXECUTED | PENDING |
| CHK-277 | Currency formatting | PLAYWRIGHT_E2E | 9. PRICING / TAX | tests/Feature/ pending mapping | Currency formatting | pending | PARTIAL | NOT EXECUTED | PENDING |
| CHK-278 | Percentage formatting | PLAYWRIGHT_E2E | 9. PRICING / TAX | tests/Feature/ pending mapping | Percentage formatting | pending | PARTIAL | NOT EXECUTED | PENDING |
| CHK-279 | Boundary values | PLAYWRIGHT_E2E | 9. PRICING / TAX | tests/Feature/ pending mapping | Boundary values | pending | PARTIAL | NOT EXECUTED | PENDING |
| CHK-280 | Decimal precision | PLAYWRIGHT_E2E | 9. PRICING / TAX | tests/Feature/ pending mapping | Decimal precision | pending | PARTIAL | NOT EXECUTED | PENDING |
| CHK-281 | Zero tax | DOMAIN_UNIT_PHP | 9. PRICING / TAX | tests/Feature/ pending mapping | Zero tax | pending | PARTIAL | NOT EXECUTED | PENDING |
| CHK-282 | Tax change does not rewrite history | DOMAIN_UNIT_PHP | 9. PRICING / TAX | tests/Feature/ pending mapping | Tax change does not rewrite history | pending | PARTIAL | NOT EXECUTED | PENDING |
| CHK-283 | Start new order | PLAYWRIGHT_E2E | 10. FLAGSHIP SALESMAN NEW ORDER > 10.1 Customer | tests/Feature/ pending mapping | Start new order | pending | NOT_TESTED | NOT EXECUTED | PENDING |
| CHK-284 | Assigned customer list | PLAYWRIGHT_E2E | 10. FLAGSHIP SALESMAN NEW ORDER > 10.1 Customer | tests/Feature/ pending mapping | Assigned customer list | pending | NOT_TESTED | NOT EXECUTED | PENDING |
| CHK-285 | Assigned customer selectable | PLAYWRIGHT_E2E | 10. FLAGSHIP SALESMAN NEW ORDER > 10.1 Customer | tests/Feature/ pending mapping | Assigned customer selectable | pending | NOT_TESTED | NOT EXECUTED | PENDING |
| CHK-286 | Unauthorized customer unavailable | HTTP_API_SECURITY | 10. FLAGSHIP SALESMAN NEW ORDER > 10.1 Customer | tests/Feature/ pending mapping | Unauthorized customer unavailable | pending | NOT_TESTED | NOT EXECUTED | PENDING |
| CHK-287 | Direct tampering with customer ID blocked | PLAYWRIGHT_E2E | 10. FLAGSHIP SALESMAN NEW ORDER > 10.1 Customer | tests/Feature/ pending mapping | Direct tampering with customer ID blocked | pending | NOT_TESTED | NOT EXECUTED | PENDING |
| CHK-288 | ON_HOLD customer behavior | PLAYWRIGHT_E2E | 10. FLAGSHIP SALESMAN NEW ORDER > 10.1 Customer | tests/Feature/ pending mapping | ON_HOLD customer behavior | pending | NOT_TESTED | NOT EXECUTED | PENDING |
| CHK-289 | INACTIVE customer behavior | PLAYWRIGHT_E2E | 10. FLAGSHIP SALESMAN NEW ORDER > 10.1 Customer | tests/Feature/ pending mapping | INACTIVE customer behavior | pending | NOT_TESTED | NOT EXECUTED | PENDING |
| CHK-290 | Browse | PLAYWRIGHT_E2E | 10. FLAGSHIP SALESMAN NEW ORDER > 10.2 Catalog | tests/Feature/ pending mapping | Browse | pending | NOT_TESTED | NOT EXECUTED | PENDING |
| CHK-291 | Search | PLAYWRIGHT_E2E | 10. FLAGSHIP SALESMAN NEW ORDER > 10.2 Catalog | tests/Feature/ pending mapping | Search | pending | NOT_TESTED | NOT EXECUTED | PENDING |
| CHK-292 | Category filter | PLAYWRIGHT_E2E | 10. FLAGSHIP SALESMAN NEW ORDER > 10.2 Catalog | tests/Feature/ pending mapping | Category filter | pending | NOT_TESTED | NOT EXECUTED | PENDING |
| CHK-293 | Add one product | PLAYWRIGHT_E2E | 10. FLAGSHIP SALESMAN NEW ORDER > 10.2 Catalog | tests/Feature/ pending mapping | Add one product | pending | NOT_TESTED | NOT EXECUTED | PENDING |
| CHK-294 | Add multiple products | PLAYWRIGHT_E2E | 10. FLAGSHIP SALESMAN NEW ORDER > 10.2 Catalog | tests/Feature/ pending mapping | Add multiple products | pending | NOT_TESTED | NOT EXECUTED | PENDING |
| CHK-295 | Product image | PLAYWRIGHT_E2E | 10. FLAGSHIP SALESMAN NEW ORDER > 10.2 Catalog | tests/Feature/ pending mapping | Product image | pending | NOT_TESTED | NOT EXECUTED | PENDING |
| CHK-296 | Product status behavior | PLAYWRIGHT_E2E | 10. FLAGSHIP SALESMAN NEW ORDER > 10.2 Catalog | tests/Feature/ pending mapping | Product status behavior | pending | NOT_TESTED | NOT EXECUTED | PENDING |
| CHK-297 | Out-of-stock/availability behavior | PLAYWRIGHT_E2E | 10. FLAGSHIP SALESMAN NEW ORDER > 10.2 Catalog | tests/Feature/ pending mapping | Out-of-stock/availability behavior | pending | NOT_TESTED | NOT EXECUTED | PENDING |
| CHK-298 | Change quantity | PLAYWRIGHT_E2E | 10. FLAGSHIP SALESMAN NEW ORDER > 10.3 Quantities/pricing | tests/Feature/ pending mapping | Change quantity | pending | NOT_TESTED | NOT EXECUTED | PENDING |
| CHK-299 | Quantity zero | PLAYWRIGHT_E2E | 10. FLAGSHIP SALESMAN NEW ORDER > 10.3 Quantities/pricing | tests/Feature/ pending mapping | Quantity zero | pending | NOT_TESTED | NOT EXECUTED | PENDING |
| CHK-300 | Negative quantity | PLAYWRIGHT_E2E | 10. FLAGSHIP SALESMAN NEW ORDER > 10.3 Quantities/pricing | tests/Feature/ pending mapping | Negative quantity | pending | NOT_TESTED | NOT EXECUTED | PENDING |
| CHK-301 | Decimal quantity if unsupported | PLAYWRIGHT_E2E | 10. FLAGSHIP SALESMAN NEW ORDER > 10.3 Quantities/pricing | tests/Feature/ pending mapping | Decimal quantity if unsupported | pending | NOT_TESTED | NOT EXECUTED | PENDING |
| CHK-302 | Excessive quantity | PLAYWRIGHT_E2E | 10. FLAGSHIP SALESMAN NEW ORDER > 10.3 Quantities/pricing | tests/Feature/ pending mapping | Excessive quantity | pending | NOT_TESTED | NOT EXECUTED | PENDING |
| CHK-303 | Availability check | PLAYWRIGHT_E2E | 10. FLAGSHIP SALESMAN NEW ORDER > 10.3 Quantities/pricing | tests/Feature/ pending mapping | Availability check | pending | NOT_TESTED | NOT EXECUTED | PENDING |
| CHK-304 | Permitted selling price | PLAYWRIGHT_E2E | 10. FLAGSHIP SALESMAN NEW ORDER > 10.3 Quantities/pricing | tests/Feature/ pending mapping | Permitted selling price | pending | NOT_TESTED | NOT EXECUTED | PENDING |
| CHK-305 | Minimum-price restriction | PLAYWRIGHT_E2E | 10. FLAGSHIP SALESMAN NEW ORDER > 10.3 Quantities/pricing | tests/Feature/ pending mapping | Minimum-price restriction | pending | NOT_TESTED | NOT EXECUTED | PENDING |
| CHK-306 | Price override flow | PLAYWRIGHT_E2E | 10. FLAGSHIP SALESMAN NEW ORDER > 10.3 Quantities/pricing | tests/Feature/ pending mapping | Price override flow | pending | NOT_TESTED | NOT EXECUTED | PENDING |
| CHK-307 | Tax | DOMAIN_UNIT_PHP | 10. FLAGSHIP SALESMAN NEW ORDER > 10.3 Quantities/pricing | tests/Feature/ pending mapping | Tax | pending | NOT_TESTED | NOT EXECUTED | PENDING |
| CHK-308 | Mixed-tax behavior | DOMAIN_UNIT_PHP | 10. FLAGSHIP SALESMAN NEW ORDER > 10.3 Quantities/pricing | tests/Feature/ pending mapping | Mixed-tax behavior | pending | NOT_TESTED | NOT EXECUTED | PENDING |
| CHK-309 | Subtotal | PLAYWRIGHT_E2E | 10. FLAGSHIP SALESMAN NEW ORDER > 10.3 Quantities/pricing | tests/Feature/ pending mapping | Subtotal | pending | NOT_TESTED | NOT EXECUTED | PENDING |
| CHK-310 | Grand total | PLAYWRIGHT_E2E | 10. FLAGSHIP SALESMAN NEW ORDER > 10.3 Quantities/pricing | tests/Feature/ pending mapping | Grand total | pending | NOT_TESTED | NOT EXECUTED | PENDING |
| CHK-311 | Recalculation after quantity change | DOMAIN_UNIT_PHP | 10. FLAGSHIP SALESMAN NEW ORDER > 10.3 Quantities/pricing | tests/Feature/ pending mapping | Recalculation after quantity change | pending | NOT_TESTED | NOT EXECUTED | PENDING |
| CHK-312 | Recalculation after price change | DOMAIN_UNIT_PHP | 10. FLAGSHIP SALESMAN NEW ORDER > 10.3 Quantities/pricing | tests/Feature/ pending mapping | Recalculation after price change | pending | NOT_TESTED | NOT EXECUTED | PENDING |
| CHK-313 | Customer | PLAYWRIGHT_E2E | 10. FLAGSHIP SALESMAN NEW ORDER > 10.4 Review | tests/Feature/ pending mapping | Customer | pending | NOT_TESTED | NOT EXECUTED | PENDING |
| CHK-314 | Products | PLAYWRIGHT_E2E | 10. FLAGSHIP SALESMAN NEW ORDER > 10.4 Review | tests/Feature/ pending mapping | Products | pending | NOT_TESTED | NOT EXECUTED | PENDING |
| CHK-315 | Quantities | PLAYWRIGHT_E2E | 10. FLAGSHIP SALESMAN NEW ORDER > 10.4 Review | tests/Feature/ pending mapping | Quantities | pending | NOT_TESTED | NOT EXECUTED | PENDING |
| CHK-316 | Prices | PLAYWRIGHT_E2E | 10. FLAGSHIP SALESMAN NEW ORDER > 10.4 Review | tests/Feature/ pending mapping | Prices | pending | NOT_TESTED | NOT EXECUTED | PENDING |
| CHK-317 | Tax | DOMAIN_UNIT_PHP | 10. FLAGSHIP SALESMAN NEW ORDER > 10.4 Review | tests/Feature/ pending mapping | Tax | pending | NOT_TESTED | NOT EXECUTED | PENDING |
| CHK-318 | Totals | PLAYWRIGHT_E2E | 10. FLAGSHIP SALESMAN NEW ORDER > 10.4 Review | tests/Feature/ pending mapping | Totals | pending | NOT_TESTED | NOT EXECUTED | PENDING |
| CHK-319 | Payment section | PLAYWRIGHT_E2E | 10. FLAGSHIP SALESMAN NEW ORDER > 10.4 Review | tests/Feature/ pending mapping | Payment section | pending | NOT_TESTED | NOT EXECUTED | PENDING |
| CHK-320 | Outstanding preview | PLAYWRIGHT_E2E | 10. FLAGSHIP SALESMAN NEW ORDER > 10.4 Review | tests/Feature/ pending mapping | Outstanding preview | pending | NOT_TESTED | NOT EXECUTED | PENDING |
| CHK-321 | No stale values | PLAYWRIGHT_E2E | 10. FLAGSHIP SALESMAN NEW ORDER > 10.4 Review | tests/Feature/ pending mapping | No stale values | pending | NOT_TESTED | NOT EXECUTED | PENDING |
| CHK-322 | Cash | PLAYWRIGHT_E2E | 10. FLAGSHIP SALESMAN NEW ORDER > 10.5 Payment collection during order | tests/Feature/ pending mapping | Cash | pending | NOT_TESTED | NOT EXECUTED | PENDING |
| CHK-323 | Cheque | PLAYWRIGHT_E2E | 10. FLAGSHIP SALESMAN NEW ORDER > 10.5 Payment collection during order | tests/Feature/ pending mapping | Cheque | pending | NOT_TESTED | NOT EXECUTED | PENDING |
| CHK-324 | Money Order | PLAYWRIGHT_E2E | 10. FLAGSHIP SALESMAN NEW ORDER > 10.5 Payment collection during order | tests/Feature/ pending mapping | Money Order | pending | NOT_TESTED | NOT EXECUTED | PENDING |
| CHK-325 | Partial payment | PLAYWRIGHT_E2E | 10. FLAGSHIP SALESMAN NEW ORDER > 10.5 Payment collection during order | tests/Feature/ pending mapping | Partial payment | pending | NOT_TESTED | NOT EXECUTED | PENDING |
| CHK-326 | Pay in full | PLAYWRIGHT_E2E | 10. FLAGSHIP SALESMAN NEW ORDER > 10.5 Payment collection during order | tests/Feature/ pending mapping | Pay in full | pending | NOT_TESTED | NOT EXECUTED | PENDING |
| CHK-327 | Zero payment | PLAYWRIGHT_E2E | 10. FLAGSHIP SALESMAN NEW ORDER > 10.5 Payment collection during order | tests/Feature/ pending mapping | Zero payment | pending | NOT_TESTED | NOT EXECUTED | PENDING |
| CHK-328 | Negative amount | PLAYWRIGHT_E2E | 10. FLAGSHIP SALESMAN NEW ORDER > 10.5 Payment collection during order | tests/Feature/ pending mapping | Negative amount | pending | NOT_TESTED | NOT EXECUTED | PENDING |
| CHK-329 | Amount greater than order | PLAYWRIGHT_E2E | 10. FLAGSHIP SALESMAN NEW ORDER > 10.5 Payment collection during order | tests/Feature/ pending mapping | Amount greater than order | pending | NOT_TESTED | NOT EXECUTED | PENDING |
| CHK-330 | Cheque number | PLAYWRIGHT_E2E | 10. FLAGSHIP SALESMAN NEW ORDER > 10.5 Payment collection during order | tests/Feature/ pending mapping | Cheque number | pending | NOT_TESTED | NOT EXECUTED | PENDING |
| CHK-331 | Cheque date | PLAYWRIGHT_E2E | 10. FLAGSHIP SALESMAN NEW ORDER > 10.5 Payment collection during order | tests/Feature/ pending mapping | Cheque date | pending | NOT_TESTED | NOT EXECUTED | PENDING |
| CHK-332 | Bank | PLAYWRIGHT_E2E | 10. FLAGSHIP SALESMAN NEW ORDER > 10.5 Payment collection during order | tests/Feature/ pending mapping | Bank | pending | NOT_TESTED | NOT EXECUTED | PENDING |
| CHK-333 | Money Order number | PLAYWRIGHT_E2E | 10. FLAGSHIP SALESMAN NEW ORDER > 10.5 Payment collection during order | tests/Feature/ pending mapping | Money Order number | pending | NOT_TESTED | NOT EXECUTED | PENDING |
| CHK-334 | Issuer | PLAYWRIGHT_E2E | 10. FLAGSHIP SALESMAN NEW ORDER > 10.5 Payment collection during order | tests/Feature/ pending mapping | Issuer | pending | NOT_TESTED | NOT EXECUTED | PENDING |
| CHK-335 | JPEG evidence | PLAYWRIGHT_E2E | 10. FLAGSHIP SALESMAN NEW ORDER > 10.5 Payment collection during order | tests/Feature/ pending mapping | JPEG evidence | pending | NOT_TESTED | NOT EXECUTED | PENDING |
| CHK-336 | Evidence preview | PLAYWRIGHT_E2E | 10. FLAGSHIP SALESMAN NEW ORDER > 10.5 Payment collection during order | tests/Feature/ pending mapping | Evidence preview | pending | NOT_TESTED | NOT EXECUTED | PENDING |
| CHK-337 | Missing required evidence blocked | PLAYWRIGHT_E2E | 10. FLAGSHIP SALESMAN NEW ORDER > 10.5 Payment collection during order | tests/Feature/ pending mapping | Missing required evidence blocked | pending | NOT_TESTED | NOT EXECUTED | PENDING |
| CHK-338 | Invalid evidence blocked | PLAYWRIGHT_E2E | 10. FLAGSHIP SALESMAN NEW ORDER > 10.5 Payment collection during order | tests/Feature/ pending mapping | Invalid evidence blocked | pending | NOT_TESTED | NOT EXECUTED | PENDING |
| CHK-339 | Pending status | PLAYWRIGHT_E2E | 10. FLAGSHIP SALESMAN NEW ORDER > 10.5 Payment collection during order | tests/Feature/ pending mapping | Pending status | pending | NOT_TESTED | NOT EXECUTED | PENDING |
| CHK-340 | Operational outstanding reflects approved pending-payment rule | PLAYWRIGHT_E2E | 10. FLAGSHIP SALESMAN NEW ORDER > 10.5 Payment collection during order | tests/Feature/ pending mapping | Operational outstanding reflects approved pending-payment rule | pending | NOT_TESTED | NOT EXECUTED | PENDING |
| CHK-341 | Submit | PLAYWRIGHT_E2E | 10. FLAGSHIP SALESMAN NEW ORDER > 10.6 Submission | tests/Feature/ pending mapping | Submit | pending | NOT_TESTED | NOT EXECUTED | PENDING |
| CHK-342 | Loading/submitting state | PLAYWRIGHT_E2E | 10. FLAGSHIP SALESMAN NEW ORDER > 10.6 Submission | tests/Feature/ pending mapping | Loading/submitting state | pending | NOT_TESTED | NOT EXECUTED | PENDING |
| CHK-343 | Double-click submit | PLAYWRIGHT_E2E | 10. FLAGSHIP SALESMAN NEW ORDER > 10.6 Submission | tests/Feature/ pending mapping | Double-click submit | pending | NOT_TESTED | NOT EXECUTED | PENDING |
| CHK-344 | Duplicate submit protection | PLAYWRIGHT_E2E | 10. FLAGSHIP SALESMAN NEW ORDER > 10.6 Submission | tests/Feature/ pending mapping | Duplicate submit protection | pending | NOT_TESTED | NOT EXECUTED | PENDING |
| CHK-345 | Confirmation | PLAYWRIGHT_E2E | 10. FLAGSHIP SALESMAN NEW ORDER > 10.6 Submission | tests/Feature/ pending mapping | Confirmation | pending | NOT_TESTED | NOT EXECUTED | PENDING |
| CHK-346 | Order detail | PLAYWRIGHT_E2E | 10. FLAGSHIP SALESMAN NEW ORDER > 10.6 Submission | tests/Feature/ pending mapping | Order detail | pending | NOT_TESTED | NOT EXECUTED | PENDING |
| CHK-347 | Payment linked to order | PLAYWRIGHT_E2E | 10. FLAGSHIP SALESMAN NEW ORDER > 10.6 Submission | tests/Feature/ pending mapping | Payment linked to order | pending | NOT_TESTED | NOT EXECUTED | PENDING |
| CHK-348 | Payment linked to customer | PLAYWRIGHT_E2E | 10. FLAGSHIP SALESMAN NEW ORDER > 10.6 Submission | tests/Feature/ pending mapping | Payment linked to customer | pending | NOT_TESTED | NOT EXECUTED | PENDING |
| CHK-349 | Recorder identity correct | PLAYWRIGHT_E2E | 10. FLAGSHIP SALESMAN NEW ORDER > 10.6 Submission | tests/Feature/ pending mapping | Recorder identity correct | pending | NOT_TESTED | NOT EXECUTED | PENDING |
| CHK-350 | Order history updated | PLAYWRIGHT_E2E | 10. FLAGSHIP SALESMAN NEW ORDER > 10.6 Submission | tests/Feature/ pending mapping | Order history updated | pending | NOT_TESTED | NOT EXECUTED | PENDING |
| CHK-351 | Payment state correct | PLAYWRIGHT_E2E | 10. FLAGSHIP SALESMAN NEW ORDER > 10.6 Submission | tests/Feature/ pending mapping | Payment state correct | pending | NOT_TESTED | NOT EXECUTED | PENDING |
| CHK-352 | Outstanding correct | PLAYWRIGHT_E2E | 10. FLAGSHIP SALESMAN NEW ORDER > 10.6 Submission | tests/Feature/ pending mapping | Outstanding correct | pending | NOT_TESTED | NOT EXECUTED | PENDING |
| CHK-353 | Audit event | PLAYWRIGHT_E2E | 10. FLAGSHIP SALESMAN NEW ORDER > 10.6 Submission | tests/Feature/ pending mapping | Audit event | pending | NOT_TESTED | NOT EXECUTED | PENDING |
| CHK-354 | Correct notifications | PLAYWRIGHT_E2E | 10. FLAGSHIP SALESMAN NEW ORDER > 10.6 Submission | tests/Feature/ pending mapping | Correct notifications | pending | NOT_TESTED | NOT EXECUTED | PENDING |
| CHK-355 | Save draft | PLAYWRIGHT_E2E | 11. DRAFT ORDERS | tests/Feature/ pending mapping | Save draft | pending | NOT_TESTED | NOT EXECUTED | PENDING |
| CHK-356 | Draft list | PLAYWRIGHT_E2E | 11. DRAFT ORDERS | tests/Feature/ pending mapping | Draft list | pending | NOT_TESTED | NOT EXECUTED | PENDING |
| CHK-357 | Reopen | PLAYWRIGHT_E2E | 11. DRAFT ORDERS | tests/Feature/ pending mapping | Reopen | pending | NOT_TESTED | NOT EXECUTED | PENDING |
| CHK-358 | Edit customer | PLAYWRIGHT_E2E | 11. DRAFT ORDERS | tests/Feature/ pending mapping | Edit customer | pending | NOT_TESTED | NOT EXECUTED | PENDING |
| CHK-359 | Edit product | PLAYWRIGHT_E2E | 11. DRAFT ORDERS | tests/Feature/ pending mapping | Edit product | pending | NOT_TESTED | NOT EXECUTED | PENDING |
| CHK-360 | Edit quantity | PLAYWRIGHT_E2E | 11. DRAFT ORDERS | tests/Feature/ pending mapping | Edit quantity | pending | NOT_TESTED | NOT EXECUTED | PENDING |
| CHK-361 | Edit permitted price | PLAYWRIGHT_E2E | 11. DRAFT ORDERS | tests/Feature/ pending mapping | Edit permitted price | pending | NOT_TESTED | NOT EXECUTED | PENDING |
| CHK-362 | Resume payment | PLAYWRIGHT_E2E | 11. DRAFT ORDERS | tests/Feature/ pending mapping | Resume payment | pending | NOT_TESTED | NOT EXECUTED | PENDING |
| CHK-363 | Submit draft | PLAYWRIGHT_E2E | 11. DRAFT ORDERS | tests/Feature/ pending mapping | Submit draft | pending | NOT_TESTED | NOT EXECUTED | PENDING |
| CHK-364 | Correct transition | PLAYWRIGHT_E2E | 11. DRAFT ORDERS | tests/Feature/ pending mapping | Correct transition | pending | NOT_TESTED | NOT EXECUTED | PENDING |
| CHK-365 | Cancel/delete where supported | PLAYWRIGHT_E2E | 11. DRAFT ORDERS | tests/Feature/ pending mapping | Cancel/delete where supported | pending | NOT_TESTED | NOT EXECUTED | PENDING |
| CHK-366 | Unauthorized draft access blocked | HTTP_API_SECURITY | 11. DRAFT ORDERS | tests/Feature/ pending mapping | Unauthorized draft access blocked | pending | NOT_TESTED | NOT EXECUTED | PENDING |
| CHK-367 | Draft does not post final accounting prematurely | PLAYWRIGHT_E2E | 11. DRAFT ORDERS | tests/Feature/ pending mapping | Draft does not post final accounting prematurely | pending | NOT_TESTED | NOT EXECUTED | PENDING |
| CHK-368 | Draft survives refresh | PLAYWRIGHT_E2E | 11. DRAFT ORDERS | tests/Feature/ pending mapping | Draft survives refresh | pending | NOT_TESTED | NOT EXECUTED | PENDING |
| CHK-369 | New orders | PLAYWRIGHT_E2E | 12. ADMIN ORDER OPERATIONS | tests/Feature/ pending mapping | New orders | pending | PARTIAL | NOT EXECUTED | PENDING |
| CHK-370 | Needs attention | PLAYWRIGHT_E2E | 12. ADMIN ORDER OPERATIONS | tests/Feature/ pending mapping | Needs attention | pending | PARTIAL | NOT EXECUTED | PENDING |
| CHK-371 | Processing | PLAYWRIGHT_E2E | 12. ADMIN ORDER OPERATIONS | tests/Feature/ pending mapping | Processing | pending | PARTIAL | NOT EXECUTED | PENDING |
| CHK-372 | Delivery | PLAYWRIGHT_E2E | 12. ADMIN ORDER OPERATIONS | tests/Feature/ pending mapping | Delivery | pending | PARTIAL | NOT EXECUTED | PENDING |
| CHK-373 | Adjustments | PLAYWRIGHT_E2E | 12. ADMIN ORDER OPERATIONS | tests/Feature/ pending mapping | Adjustments | pending | PARTIAL | NOT EXECUTED | PENDING |
| CHK-374 | Completed | PLAYWRIGHT_E2E | 12. ADMIN ORDER OPERATIONS | tests/Feature/ pending mapping | Completed | pending | PARTIAL | NOT EXECUTED | PENDING |
| CHK-375 | Rejected/cancelled | PLAYWRIGHT_E2E | 12. ADMIN ORDER OPERATIONS | tests/Feature/ pending mapping | Rejected/cancelled | pending | PARTIAL | NOT EXECUTED | PENDING |
| CHK-376 | All/search history | PLAYWRIGHT_E2E | 12. ADMIN ORDER OPERATIONS | tests/Feature/ pending mapping | All/search history | pending | PARTIAL | NOT EXECUTED | PENDING |
| CHK-377 | Customer | PLAYWRIGHT_E2E | 12. ADMIN ORDER OPERATIONS | tests/Feature/ pending mapping | Customer | pending | PARTIAL | NOT EXECUTED | PENDING |
| CHK-378 | Items | PLAYWRIGHT_E2E | 12. ADMIN ORDER OPERATIONS | tests/Feature/ pending mapping | Items | pending | PARTIAL | NOT EXECUTED | PENDING |
| CHK-379 | Quantity | PLAYWRIGHT_E2E | 12. ADMIN ORDER OPERATIONS | tests/Feature/ pending mapping | Quantity | pending | PARTIAL | NOT EXECUTED | PENDING |
| CHK-380 | Pricing | DOMAIN_UNIT_PHP | 12. ADMIN ORDER OPERATIONS | tests/Feature/ pending mapping | Pricing | pending | PARTIAL | NOT EXECUTED | PENDING |
| CHK-381 | Tax | DOMAIN_UNIT_PHP | 12. ADMIN ORDER OPERATIONS | tests/Feature/ pending mapping | Tax | pending | PARTIAL | NOT EXECUTED | PENDING |
| CHK-382 | Payment | PLAYWRIGHT_E2E | 12. ADMIN ORDER OPERATIONS | tests/Feature/ pending mapping | Payment | pending | PARTIAL | NOT EXECUTED | PENDING |
| CHK-383 | Financial summary | PLAYWRIGHT_E2E | 12. ADMIN ORDER OPERATIONS | tests/Feature/ pending mapping | Financial summary | pending | PARTIAL | NOT EXECUTED | PENDING |
| CHK-384 | Delivery | PLAYWRIGHT_E2E | 12. ADMIN ORDER OPERATIONS | tests/Feature/ pending mapping | Delivery | pending | PARTIAL | NOT EXECUTED | PENDING |
| CHK-385 | Timeline | PLAYWRIGHT_E2E | 12. ADMIN ORDER OPERATIONS | tests/Feature/ pending mapping | Timeline | pending | PARTIAL | NOT EXECUTED | PENDING |
| CHK-386 | Audit | PLAYWRIGHT_E2E | 12. ADMIN ORDER OPERATIONS | tests/Feature/ pending mapping | Audit | pending | PARTIAL | NOT EXECUTED | PENDING |
| CHK-387 | Approve | PLAYWRIGHT_E2E | 12. ADMIN ORDER OPERATIONS | tests/Feature/ pending mapping | Approve | pending | PARTIAL | NOT EXECUTED | PENDING |
| CHK-388 | Reject | PLAYWRIGHT_E2E | 12. ADMIN ORDER OPERATIONS | tests/Feature/ pending mapping | Reject | pending | PARTIAL | NOT EXECUTED | PENDING |
| CHK-389 | Rejection reason | PLAYWRIGHT_E2E | 12. ADMIN ORDER OPERATIONS | tests/Feature/ pending mapping | Rejection reason | pending | PARTIAL | NOT EXECUTED | PENDING |
| CHK-390 | Invalid state | PLAYWRIGHT_E2E | 12. ADMIN ORDER OPERATIONS | tests/Feature/ pending mapping | Invalid state | pending | PARTIAL | NOT EXECUTED | PENDING |
| CHK-391 | Duplicate action | PLAYWRIGHT_E2E | 12. ADMIN ORDER OPERATIONS | tests/Feature/ pending mapping | Duplicate action | pending | PARTIAL | NOT EXECUTED | PENDING |
| CHK-392 | Inventory reservation | PLAYWRIGHT_E2E | 12. ADMIN ORDER OPERATIONS | tests/Feature/ pending mapping | Inventory reservation | pending | PARTIAL | NOT EXECUTED | PENDING |
| CHK-393 | Correct fulfillment state | PLAYWRIGHT_E2E | 12. ADMIN ORDER OPERATIONS | tests/Feature/ pending mapping | Correct fulfillment state | pending | PARTIAL | NOT EXECUTED | PENDING |
| CHK-394 | Stale/concurrent approval behavior | PLAYWRIGHT_E2E | 12. ADMIN ORDER OPERATIONS | tests/Feature/ pending mapping | Stale/concurrent approval behavior | pending | PARTIAL | NOT EXECUTED | PENDING |
| CHK-395 | Payment created in new order | PLAYWRIGHT_E2E | 13. PAYMENTS — COMPLETE LIFECYCLE > 13.1 Creation | tests/Feature/ pending mapping | Payment created in new order | pending | NOT_TESTED | NOT EXECUTED | PENDING |
| CHK-396 | Cash | PLAYWRIGHT_E2E | 13. PAYMENTS — COMPLETE LIFECYCLE > 13.1 Creation | tests/Feature/ pending mapping | Cash | pending | NOT_TESTED | NOT EXECUTED | PENDING |
| CHK-397 | Cheque | PLAYWRIGHT_E2E | 13. PAYMENTS — COMPLETE LIFECYCLE > 13.1 Creation | tests/Feature/ pending mapping | Cheque | pending | NOT_TESTED | NOT EXECUTED | PENDING |
| CHK-398 | Money Order | PLAYWRIGHT_E2E | 13. PAYMENTS — COMPLETE LIFECYCLE > 13.1 Creation | tests/Feature/ pending mapping | Money Order | pending | NOT_TESTED | NOT EXECUTED | PENDING |
| CHK-399 | Evidence | PLAYWRIGHT_E2E | 13. PAYMENTS — COMPLETE LIFECYCLE > 13.1 Creation | tests/Feature/ pending mapping | Evidence | pending | NOT_TESTED | NOT EXECUTED | PENDING |
| CHK-400 | Order linkage | PLAYWRIGHT_E2E | 13. PAYMENTS — COMPLETE LIFECYCLE > 13.1 Creation | tests/Feature/ pending mapping | Order linkage | pending | NOT_TESTED | NOT EXECUTED | PENDING |
| CHK-401 | Customer linkage | PLAYWRIGHT_E2E | 13. PAYMENTS — COMPLETE LIFECYCLE > 13.1 Creation | tests/Feature/ pending mapping | Customer linkage | pending | NOT_TESTED | NOT EXECUTED | PENDING |
| CHK-402 | Recorder identity | PLAYWRIGHT_E2E | 13. PAYMENTS — COMPLETE LIFECYCLE > 13.1 Creation | tests/Feature/ pending mapping | Recorder identity | pending | NOT_TESTED | NOT EXECUTED | PENDING |
| CHK-403 | Pending state | PLAYWRIGHT_E2E | 13. PAYMENTS — COMPLETE LIFECYCLE > 13.1 Creation | tests/Feature/ pending mapping | Pending state | pending | NOT_TESTED | NOT EXECUTED | PENDING |
| CHK-404 | Pending queue | PLAYWRIGHT_E2E | 13. PAYMENTS — COMPLETE LIFECYCLE > 13.2 Verification workspace | tests/Feature/ pending mapping | Pending queue | pending | NOT_TESTED | NOT EXECUTED | PENDING |
| CHK-405 | Verified queue | PLAYWRIGHT_E2E | 13. PAYMENTS — COMPLETE LIFECYCLE > 13.2 Verification workspace | tests/Feature/ pending mapping | Verified queue | pending | NOT_TESTED | NOT EXECUTED | PENDING |
| CHK-406 | Rejected queue | PLAYWRIGHT_E2E | 13. PAYMENTS — COMPLETE LIFECYCLE > 13.2 Verification workspace | tests/Feature/ pending mapping | Rejected queue | pending | NOT_TESTED | NOT EXECUTED | PENDING |
| CHK-407 | Reversed queue | PLAYWRIGHT_E2E | 13. PAYMENTS — COMPLETE LIFECYCLE > 13.2 Verification workspace | tests/Feature/ pending mapping | Reversed queue | pending | NOT_TESTED | NOT EXECUTED | PENDING |
| CHK-408 | All queue | PLAYWRIGHT_E2E | 13. PAYMENTS — COMPLETE LIFECYCLE > 13.2 Verification workspace | tests/Feature/ pending mapping | All queue | pending | NOT_TESTED | NOT EXECUTED | PENDING |
| CHK-409 | Search | PLAYWRIGHT_E2E | 13. PAYMENTS — COMPLETE LIFECYCLE > 13.2 Verification workspace | tests/Feature/ pending mapping | Search | pending | NOT_TESTED | NOT EXECUTED | PENDING |
| CHK-410 | Filters | PLAYWRIGHT_E2E | 13. PAYMENTS — COMPLETE LIFECYCLE > 13.2 Verification workspace | tests/Feature/ pending mapping | Filters | pending | NOT_TESTED | NOT EXECUTED | PENDING |
| CHK-411 | Open payment | PLAYWRIGHT_E2E | 13. PAYMENTS — COMPLETE LIFECYCLE > 13.2 Verification workspace | tests/Feature/ pending mapping | Open payment | pending | NOT_TESTED | NOT EXECUTED | PENDING |
| CHK-412 | Evidence preview | PLAYWRIGHT_E2E | 13. PAYMENTS — COMPLETE LIFECYCLE > 13.2 Verification workspace | tests/Feature/ pending mapping | Evidence preview | pending | NOT_TESTED | NOT EXECUTED | PENDING |
| CHK-413 | Verify | PLAYWRIGHT_E2E | 13. PAYMENTS — COMPLETE LIFECYCLE > 13.2 Verification workspace | tests/Feature/ pending mapping | Verify | pending | NOT_TESTED | NOT EXECUTED | PENDING |
| CHK-414 | Reject | PLAYWRIGHT_E2E | 13. PAYMENTS — COMPLETE LIFECYCLE > 13.2 Verification workspace | tests/Feature/ pending mapping | Reject | pending | NOT_TESTED | NOT EXECUTED | PENDING |
| CHK-415 | Rejection reason | PLAYWRIGHT_E2E | 13. PAYMENTS — COMPLETE LIFECYCLE > 13.2 Verification workspace | tests/Feature/ pending mapping | Rejection reason | pending | NOT_TESTED | NOT EXECUTED | PENDING |
| CHK-416 | Resubmission | PLAYWRIGHT_E2E | 13. PAYMENTS — COMPLETE LIFECYCLE > 13.2 Verification workspace | tests/Feature/ pending mapping | Resubmission | pending | NOT_TESTED | NOT EXECUTED | PENDING |
| CHK-417 | Maker-checker | PLAYWRIGHT_E2E | 13. PAYMENTS — COMPLETE LIFECYCLE > 13.2 Verification workspace | tests/Feature/ pending mapping | Maker-checker | pending | NOT_TESTED | NOT EXECUTED | PENDING |
| CHK-418 | Same-user verify prohibited where policy requires | PLAYWRIGHT_E2E | 13. PAYMENTS — COMPLETE LIFECYCLE > 13.2 Verification workspace | tests/Feature/ pending mapping | Same-user verify prohibited where policy requires | pending | NOT_TESTED | NOT EXECUTED | PENDING |
| CHK-419 | Duplicate verification protected | PLAYWRIGHT_E2E | 13. PAYMENTS — COMPLETE LIFECYCLE > 13.2 Verification workspace | tests/Feature/ pending mapping | Duplicate verification protected | pending | NOT_TESTED | NOT EXECUTED | PENDING |
| CHK-420 | Correct state transition | PLAYWRIGHT_E2E | 13. PAYMENTS — COMPLETE LIFECYCLE > 13.2 Verification workspace | tests/Feature/ pending mapping | Correct state transition | pending | NOT_TESTED | NOT EXECUTED | PENDING |
| CHK-421 | Pending payment included according to approved rule | PLAYWRIGHT_E2E | 13. PAYMENTS — COMPLETE LIFECYCLE > 13.3 Financial effects | tests/Feature/ pending mapping | Pending payment included according to approved rule | pending | NOT_TESTED | NOT EXECUTED | PENDING |
| CHK-422 | No double counting | PLAYWRIGHT_E2E | 13. PAYMENTS — COMPLETE LIFECYCLE > 13.3 Financial effects | tests/Feature/ pending mapping | No double counting | pending | NOT_TESTED | NOT EXECUTED | PENDING |
| CHK-423 | Operational outstanding correct | PLAYWRIGHT_E2E | 13. PAYMENTS — COMPLETE LIFECYCLE > 13.3 Financial effects | tests/Feature/ pending mapping | Operational outstanding correct | pending | NOT_TESTED | NOT EXECUTED | PENDING |
| CHK-424 | AR correct | PLAYWRIGHT_E2E | 13. PAYMENTS — COMPLETE LIFECYCLE > 13.3 Financial effects | tests/Feature/ pending mapping | AR correct | pending | NOT_TESTED | NOT EXECUTED | PENDING |
| CHK-425 | GL treatment correct | PLAYWRIGHT_E2E | 13. PAYMENTS — COMPLETE LIFECYCLE > 13.3 Financial effects | tests/Feature/ pending mapping | GL treatment correct | pending | NOT_TESTED | NOT EXECUTED | PENDING |
| CHK-426 | Payment history immutable | PLAYWRIGHT_E2E | 13. PAYMENTS — COMPLETE LIFECYCLE > 13.3 Financial effects | tests/Feature/ pending mapping | Payment history immutable | pending | NOT_TESTED | NOT EXECUTED | PENDING |
| CHK-427 | Verification changes financial presentation correctly | PLAYWRIGHT_E2E | 13. PAYMENTS — COMPLETE LIFECYCLE > 13.3 Financial effects | tests/Feature/ pending mapping | Verification changes financial presentation correctly | pending | NOT_TESTED | NOT EXECUTED | PENDING |
| CHK-428 | Reversal behavior correct | PLAYWRIGHT_E2E | 13. PAYMENTS — COMPLETE LIFECYCLE > 13.3 Financial effects | tests/Feature/ pending mapping | Reversal behavior correct | pending | NOT_TESTED | NOT EXECUTED | PENDING |
| CHK-429 | Repeated reversal blocked | PLAYWRIGHT_E2E | 13. PAYMENTS — COMPLETE LIFECYCLE > 13.3 Financial effects | tests/Feature/ pending mapping | Repeated reversal blocked | pending | NOT_TESTED | NOT EXECUTED | PENDING |
| CHK-430 | Excess reversal blocked | PLAYWRIGHT_E2E | 13. PAYMENTS — COMPLETE LIFECYCLE > 13.3 Financial effects | tests/Feature/ pending mapping | Excess reversal blocked | pending | NOT_TESTED | NOT EXECUTED | PENDING |
| CHK-431 | Private object | PLAYWRIGHT_E2E | 13. PAYMENTS — COMPLETE LIFECYCLE > 13.4 File/evidence security | tests/Feature/ pending mapping | Private object | pending | NOT_TESTED | NOT EXECUTED | PENDING |
| CHK-432 | No public URL | PLAYWRIGHT_E2E | 13. PAYMENTS — COMPLETE LIFECYCLE > 13.4 File/evidence security | tests/Feature/ pending mapping | No public URL | pending | NOT_TESTED | NOT EXECUTED | PENDING |
| CHK-433 | Signed preview works | PLAYWRIGHT_E2E | 13. PAYMENTS — COMPLETE LIFECYCLE > 13.4 File/evidence security | tests/Feature/ pending mapping | Signed preview works | pending | NOT_TESTED | NOT EXECUTED | PENDING |
| CHK-434 | Signed URL expires | PLAYWRIGHT_E2E | 13. PAYMENTS — COMPLETE LIFECYCLE > 13.4 File/evidence security | tests/Feature/ pending mapping | Signed URL expires | pending | NOT_TESTED | NOT EXECUTED | PENDING |
| CHK-435 | Unauthorized evidence blocked | HTTP_API_SECURITY | 13. PAYMENTS — COMPLETE LIFECYCLE > 13.4 File/evidence security | tests/Feature/ pending mapping | Unauthorized evidence blocked | pending | NOT_TESTED | NOT EXECUTED | PENDING |
| CHK-436 | Cross-customer evidence blocked | PLAYWRIGHT_E2E | 13. PAYMENTS — COMPLETE LIFECYCLE > 13.4 File/evidence security | tests/Feature/ pending mapping | Cross-customer evidence blocked | pending | NOT_TESTED | NOT EXECUTED | PENDING |
| CHK-437 | Cross-salesman evidence blocked | PLAYWRIGHT_E2E | 13. PAYMENTS — COMPLETE LIFECYCLE > 13.4 File/evidence security | tests/Feature/ pending mapping | Cross-salesman evidence blocked | pending | NOT_TESTED | NOT EXECUTED | PENDING |
| CHK-438 | Evidence metadata correct | PLAYWRIGHT_E2E | 13. PAYMENTS — COMPLETE LIFECYCLE > 13.4 File/evidence security | tests/Feature/ pending mapping | Evidence metadata correct | pending | NOT_TESTED | NOT EXECUTED | PENDING |
| CHK-439 | Failed upload cleanup | PLAYWRIGHT_E2E | 13. PAYMENTS — COMPLETE LIFECYCLE > 13.4 File/evidence security | tests/Feature/ pending mapping | Failed upload cleanup | pending | NOT_TESTED | NOT EXECUTED | PENDING |
| CHK-440 | No credential leakage | PLAYWRIGHT_E2E | 13. PAYMENTS — COMPLETE LIFECYCLE > 13.4 File/evidence security | tests/Feature/ pending mapping | No credential leakage | pending | NOT_TESTED | NOT EXECUTED | PENDING |
| CHK-441 | Total AR | PLAYWRIGHT_E2E | 14. ACCOUNTS RECEIVABLE — DEEPEST VERIFICATION > 14.1 Dashboard | tests/Feature/ pending mapping | Total AR | pending | PARTIAL | NOT EXECUTED | PENDING |
| CHK-442 | Customer count | PLAYWRIGHT_E2E | 14. ACCOUNTS RECEIVABLE — DEEPEST VERIFICATION > 14.1 Dashboard | tests/Feature/ pending mapping | Customer count | pending | PARTIAL | NOT EXECUTED | PENDING |
| CHK-443 | Current | PLAYWRIGHT_E2E | 14. ACCOUNTS RECEIVABLE — DEEPEST VERIFICATION > 14.1 Dashboard | tests/Feature/ pending mapping | Current | pending | PARTIAL | NOT EXECUTED | PENDING |
| CHK-444 | 1–30 | PLAYWRIGHT_E2E | 14. ACCOUNTS RECEIVABLE — DEEPEST VERIFICATION > 14.1 Dashboard | tests/Feature/ pending mapping | 1–30 | pending | PARTIAL | NOT EXECUTED | PENDING |
| CHK-445 | 31–60 | PLAYWRIGHT_E2E | 14. ACCOUNTS RECEIVABLE — DEEPEST VERIFICATION > 14.1 Dashboard | tests/Feature/ pending mapping | 31–60 | pending | PARTIAL | NOT EXECUTED | PENDING |
| CHK-446 | 61–90 | PLAYWRIGHT_E2E | 14. ACCOUNTS RECEIVABLE — DEEPEST VERIFICATION > 14.1 Dashboard | tests/Feature/ pending mapping | 61–90 | pending | PARTIAL | NOT EXECUTED | PENDING |
| CHK-447 | 91+ | PLAYWRIGHT_E2E | 14. ACCOUNTS RECEIVABLE — DEEPEST VERIFICATION > 14.1 Dashboard | tests/Feature/ pending mapping | 91+ | pending | PARTIAL | NOT EXECUTED | PENDING |
| CHK-448 | Customer rows | PLAYWRIGHT_E2E | 14. ACCOUNTS RECEIVABLE — DEEPEST VERIFICATION > 14.1 Dashboard | tests/Feature/ pending mapping | Customer rows | pending | PARTIAL | NOT EXECUTED | PENDING |
| CHK-449 | Pending payment amounts | PLAYWRIGHT_E2E | 14. ACCOUNTS RECEIVABLE — DEEPEST VERIFICATION > 14.1 Dashboard | tests/Feature/ pending mapping | Pending payment amounts | pending | PARTIAL | NOT EXECUTED | PENDING |
| CHK-450 | Operational outstanding | PLAYWRIGHT_E2E | 14. ACCOUNTS RECEIVABLE — DEEPEST VERIFICATION > 14.1 Dashboard | tests/Feature/ pending mapping | Operational outstanding | pending | PARTIAL | NOT EXECUTED | PENDING |
| CHK-451 | Non-zero data when transactions exist | PLAYWRIGHT_E2E | 14. ACCOUNTS RECEIVABLE — DEEPEST VERIFICATION > 14.1 Dashboard | tests/Feature/ pending mapping | Non-zero data when transactions exist | pending | PARTIAL | NOT EXECUTED | PENDING |
| CHK-452 | Empty state when no balances exist | POSTGRES_DB_INVARIANT | 14. ACCOUNTS RECEIVABLE — DEEPEST VERIFICATION > 14.1 Dashboard | tests/Feature/ pending mapping | Empty state when no balances exist | pending | PARTIAL | NOT EXECUTED | PENDING |
| CHK-453 | Search | PLAYWRIGHT_E2E | 14. ACCOUNTS RECEIVABLE — DEEPEST VERIFICATION > 14.1 Dashboard | tests/Feature/ pending mapping | Search | pending | PARTIAL | NOT EXECUTED | PENDING |
| CHK-454 | Filter | PLAYWRIGHT_E2E | 14. ACCOUNTS RECEIVABLE — DEEPEST VERIFICATION > 14.1 Dashboard | tests/Feature/ pending mapping | Filter | pending | PARTIAL | NOT EXECUTED | PENDING |
| CHK-455 | Reference-date behavior | PLAYWRIGHT_E2E | 14. ACCOUNTS RECEIVABLE — DEEPEST VERIFICATION > 14.1 Dashboard | tests/Feature/ pending mapping | Reference-date behavior | pending | PARTIAL | NOT EXECUTED | PENDING |
| CHK-456 | Customer balance | POSTGRES_DB_INVARIANT | 14. ACCOUNTS RECEIVABLE — DEEPEST VERIFICATION > 14.2 Customer AR | tests/Feature/ pending mapping | Customer balance | pending | PARTIAL | NOT EXECUTED | PENDING |
| CHK-457 | Charges | PLAYWRIGHT_E2E | 14. ACCOUNTS RECEIVABLE — DEEPEST VERIFICATION > 14.2 Customer AR | tests/Feature/ pending mapping | Charges | pending | PARTIAL | NOT EXECUTED | PENDING |
| CHK-458 | Payments | PLAYWRIGHT_E2E | 14. ACCOUNTS RECEIVABLE — DEEPEST VERIFICATION > 14.2 Customer AR | tests/Feature/ pending mapping | Payments | pending | PARTIAL | NOT EXECUTED | PENDING |
| CHK-459 | Pending payments | PLAYWRIGHT_E2E | 14. ACCOUNTS RECEIVABLE — DEEPEST VERIFICATION > 14.2 Customer AR | tests/Feature/ pending mapping | Pending payments | pending | PARTIAL | NOT EXECUTED | PENDING |
| CHK-460 | Verified payments | PLAYWRIGHT_E2E | 14. ACCOUNTS RECEIVABLE — DEEPEST VERIFICATION > 14.2 Customer AR | tests/Feature/ pending mapping | Verified payments | pending | PARTIAL | NOT EXECUTED | PENDING |
| CHK-461 | Credits | POSTGRES_DB_INVARIANT | 14. ACCOUNTS RECEIVABLE — DEEPEST VERIFICATION > 14.2 Customer AR | tests/Feature/ pending mapping | Credits | pending | PARTIAL | NOT EXECUTED | PENDING |
| CHK-462 | Refunds | PLAYWRIGHT_E2E | 14. ACCOUNTS RECEIVABLE — DEEPEST VERIFICATION > 14.2 Customer AR | tests/Feature/ pending mapping | Refunds | pending | PARTIAL | NOT EXECUTED | PENDING |
| CHK-463 | Adjustments | PLAYWRIGHT_E2E | 14. ACCOUNTS RECEIVABLE — DEEPEST VERIFICATION > 14.2 Customer AR | tests/Feature/ pending mapping | Adjustments | pending | PARTIAL | NOT EXECUTED | PENDING |
| CHK-464 | Running balance | POSTGRES_DB_INVARIANT | 14. ACCOUNTS RECEIVABLE — DEEPEST VERIFICATION > 14.2 Customer AR | tests/Feature/ pending mapping | Running balance | pending | PARTIAL | NOT EXECUTED | PENDING |
| CHK-465 | Chronological transaction history | PLAYWRIGHT_E2E | 14. ACCOUNTS RECEIVABLE — DEEPEST VERIFICATION > 14.2 Customer AR | tests/Feature/ pending mapping | Chronological transaction history | pending | PARTIAL | NOT EXECUTED | PENDING |
| CHK-466 | Opens without 500 | PLAYWRIGHT_E2E | 14. ACCOUNTS RECEIVABLE — DEEPEST VERIFICATION > 14.3 Statement | tests/Feature/ pending mapping | Opens without 500 | pending | PARTIAL | NOT EXECUTED | PENDING |
| CHK-467 | Correct dates | PLAYWRIGHT_E2E | 14. ACCOUNTS RECEIVABLE — DEEPEST VERIFICATION > 14.3 Statement | tests/Feature/ pending mapping | Correct dates | pending | PARTIAL | NOT EXECUTED | PENDING |
| CHK-468 | Opening balance | POSTGRES_DB_INVARIANT | 14. ACCOUNTS RECEIVABLE — DEEPEST VERIFICATION > 14.3 Statement | tests/Feature/ pending mapping | Opening balance | pending | PARTIAL | NOT EXECUTED | PENDING |
| CHK-469 | Orders/invoices | PLAYWRIGHT_E2E | 14. ACCOUNTS RECEIVABLE — DEEPEST VERIFICATION > 14.3 Statement | tests/Feature/ pending mapping | Orders/invoices | pending | PARTIAL | NOT EXECUTED | PENDING |
| CHK-470 | Payments | PLAYWRIGHT_E2E | 14. ACCOUNTS RECEIVABLE — DEEPEST VERIFICATION > 14.3 Statement | tests/Feature/ pending mapping | Payments | pending | PARTIAL | NOT EXECUTED | PENDING |
| CHK-471 | Pending payment presentation | PLAYWRIGHT_E2E | 14. ACCOUNTS RECEIVABLE — DEEPEST VERIFICATION > 14.3 Statement | tests/Feature/ pending mapping | Pending payment presentation | pending | PARTIAL | NOT EXECUTED | PENDING |
| CHK-472 | Credits | POSTGRES_DB_INVARIANT | 14. ACCOUNTS RECEIVABLE — DEEPEST VERIFICATION > 14.3 Statement | tests/Feature/ pending mapping | Credits | pending | PARTIAL | NOT EXECUTED | PENDING |
| CHK-473 | Refunds | PLAYWRIGHT_E2E | 14. ACCOUNTS RECEIVABLE — DEEPEST VERIFICATION > 14.3 Statement | tests/Feature/ pending mapping | Refunds | pending | PARTIAL | NOT EXECUTED | PENDING |
| CHK-474 | Adjustments | PLAYWRIGHT_E2E | 14. ACCOUNTS RECEIVABLE — DEEPEST VERIFICATION > 14.3 Statement | tests/Feature/ pending mapping | Adjustments | pending | PARTIAL | NOT EXECUTED | PENDING |
| CHK-475 | Running balance | POSTGRES_DB_INVARIANT | 14. ACCOUNTS RECEIVABLE — DEEPEST VERIFICATION > 14.3 Statement | tests/Feature/ pending mapping | Running balance | pending | PARTIAL | NOT EXECUTED | PENDING |
| CHK-476 | Closing balance | POSTGRES_DB_INVARIANT | 14. ACCOUNTS RECEIVABLE — DEEPEST VERIFICATION > 14.3 Statement | tests/Feature/ pending mapping | Closing balance | pending | PARTIAL | NOT EXECUTED | PENDING |
| CHK-477 | Date presets | PLAYWRIGHT_E2E | 14. ACCOUNTS RECEIVABLE — DEEPEST VERIFICATION > 14.3 Statement | tests/Feature/ pending mapping | Date presets | pending | PARTIAL | NOT EXECUTED | PENDING |
| CHK-478 | Custom range | PLAYWRIGHT_E2E | 14. ACCOUNTS RECEIVABLE — DEEPEST VERIFICATION > 14.3 Statement | tests/Feature/ pending mapping | Custom range | pending | PARTIAL | NOT EXECUTED | PENDING |
| CHK-479 | Print | PLAYWRIGHT_E2E | 14. ACCOUNTS RECEIVABLE — DEEPEST VERIFICATION > 14.3 Statement | tests/Feature/ pending mapping | Print | pending | PARTIAL | NOT EXECUTED | PENDING |
| CHK-480 | Export where supported | PLAYWRIGHT_E2E | 14. ACCOUNTS RECEIVABLE — DEEPEST VERIFICATION > 14.3 Statement | tests/Feature/ pending mapping | Export where supported | pending | PARTIAL | NOT EXECUTED | PENDING |
| CHK-481 | Order outstanding = operational AR | PLAYWRIGHT_E2E | 14. ACCOUNTS RECEIVABLE — DEEPEST VERIFICATION > 14.4 AR reconciliation | tests/Feature/ pending mapping | Order outstanding = operational AR | pending | PARTIAL | NOT EXECUTED | PENDING |
| CHK-482 | Customer balance = AR | POSTGRES_DB_INVARIANT | 14. ACCOUNTS RECEIVABLE — DEEPEST VERIFICATION > 14.4 AR reconciliation | tests/Feature/ pending mapping | Customer balance = AR | pending | PARTIAL | NOT EXECUTED | PENDING |
| CHK-483 | Statement = AR | PLAYWRIGHT_E2E | 14. ACCOUNTS RECEIVABLE — DEEPEST VERIFICATION > 14.4 AR reconciliation | tests/Feature/ pending mapping | Statement = AR | pending | PARTIAL | NOT EXECUTED | PENDING |
| CHK-484 | Aging buckets = balance | POSTGRES_DB_INVARIANT | 14. ACCOUNTS RECEIVABLE — DEEPEST VERIFICATION > 14.4 AR reconciliation | tests/Feature/ pending mapping | Aging buckets = balance | pending | PARTIAL | NOT EXECUTED | PENDING |
| CHK-485 | Pending payment included once | PLAYWRIGHT_E2E | 14. ACCOUNTS RECEIVABLE — DEEPEST VERIFICATION > 14.4 AR reconciliation | tests/Feature/ pending mapping | Pending payment included once | pending | PARTIAL | NOT EXECUTED | PENDING |
| CHK-486 | Pending → verified transfer correct | PLAYWRIGHT_E2E | 14. ACCOUNTS RECEIVABLE — DEEPEST VERIFICATION > 14.4 AR reconciliation | tests/Feature/ pending mapping | Pending → verified transfer correct | pending | PARTIAL | NOT EXECUTED | PENDING |
| CHK-487 | Credit note effect correct | POSTGRES_DB_INVARIANT | 14. ACCOUNTS RECEIVABLE — DEEPEST VERIFICATION > 14.4 AR reconciliation | tests/Feature/ pending mapping | Credit note effect correct | pending | PARTIAL | NOT EXECUTED | PENDING |
| CHK-488 | Refund effect correct | PLAYWRIGHT_E2E | 14. ACCOUNTS RECEIVABLE — DEEPEST VERIFICATION > 14.4 AR reconciliation | tests/Feature/ pending mapping | Refund effect correct | pending | PARTIAL | NOT EXECUTED | PENDING |
| CHK-489 | Adjustment effect correct | PLAYWRIGHT_E2E | 14. ACCOUNTS RECEIVABLE — DEEPEST VERIFICATION > 14.4 AR reconciliation | tests/Feature/ pending mapping | Adjustment effect correct | pending | PARTIAL | NOT EXECUTED | PENDING |
| CHK-490 | No hidden transactions | PLAYWRIGHT_E2E | 14. ACCOUNTS RECEIVABLE — DEEPEST VERIFICATION > 14.4 AR reconciliation | tests/Feature/ pending mapping | No hidden transactions | pending | PARTIAL | NOT EXECUTED | PENDING |
| CHK-491 | No duplicated transactions | PLAYWRIGHT_E2E | 14. ACCOUNTS RECEIVABLE — DEEPEST VERIFICATION > 14.4 AR reconciliation | tests/Feature/ pending mapping | No duplicated transactions | pending | PARTIAL | NOT EXECUTED | PENDING |
| CHK-492 | Supplier list | PLAYWRIGHT_E2E | 15. ACCOUNTS PAYABLE | tests/Feature/ pending mapping | Supplier list | pending | NOT_TESTED | NOT EXECUTED | PENDING |
| CHK-493 | Supplier detail | PLAYWRIGHT_E2E | 15. ACCOUNTS PAYABLE | tests/Feature/ pending mapping | Supplier detail | pending | NOT_TESTED | NOT EXECUTED | PENDING |
| CHK-494 | Bills | PLAYWRIGHT_E2E | 15. ACCOUNTS PAYABLE | tests/Feature/ pending mapping | Bills | pending | NOT_TESTED | NOT EXECUTED | PENDING |
| CHK-495 | Payments | PLAYWRIGHT_E2E | 15. ACCOUNTS PAYABLE | tests/Feature/ pending mapping | Payments | pending | NOT_TESTED | NOT EXECUTED | PENDING |
| CHK-496 | Outstanding | PLAYWRIGHT_E2E | 15. ACCOUNTS PAYABLE | tests/Feature/ pending mapping | Outstanding | pending | NOT_TESTED | NOT EXECUTED | PENDING |
| CHK-497 | History | PLAYWRIGHT_E2E | 15. ACCOUNTS PAYABLE | tests/Feature/ pending mapping | History | pending | NOT_TESTED | NOT EXECUTED | PENDING |
| CHK-498 | Search | PLAYWRIGHT_E2E | 15. ACCOUNTS PAYABLE | tests/Feature/ pending mapping | Search | pending | NOT_TESTED | NOT EXECUTED | PENDING |
| CHK-499 | Filters | PLAYWRIGHT_E2E | 15. ACCOUNTS PAYABLE | tests/Feature/ pending mapping | Filters | pending | NOT_TESTED | NOT EXECUTED | PENDING |
| CHK-500 | Totals | PLAYWRIGHT_E2E | 15. ACCOUNTS PAYABLE | tests/Feature/ pending mapping | Totals | pending | NOT_TESTED | NOT EXECUTED | PENDING |
| CHK-501 | Permissions | PLAYWRIGHT_E2E | 15. ACCOUNTS PAYABLE | tests/Feature/ pending mapping | Permissions | pending | NOT_TESTED | NOT EXECUTED | PENDING |
| CHK-502 | Invalid direct access | PLAYWRIGHT_E2E | 15. ACCOUNTS PAYABLE | tests/Feature/ pending mapping | Invalid direct access | pending | NOT_TESTED | NOT EXECUTED | PENDING |
| CHK-503 | Accounting consistency | PLAYWRIGHT_E2E | 15. ACCOUNTS PAYABLE | tests/Feature/ pending mapping | Accounting consistency | pending | NOT_TESTED | NOT EXECUTED | PENDING |
| CHK-504 | Request | PLAYWRIGHT_E2E | 16. ADJUSTMENTS / QUANTITY ALLOCATION > 16.1 Request/review | tests/Feature/ pending mapping | Request | pending | PARTIAL | NOT EXECUTED | PENDING |
| CHK-505 | Item | PLAYWRIGHT_E2E | 16. ADJUSTMENTS / QUANTITY ALLOCATION > 16.1 Request/review | tests/Feature/ pending mapping | Item | pending | PARTIAL | NOT EXECUTED | PENDING |
| CHK-506 | Quantity | PLAYWRIGHT_E2E | 16. ADJUSTMENTS / QUANTITY ALLOCATION > 16.1 Request/review | tests/Feature/ pending mapping | Quantity | pending | PARTIAL | NOT EXECUTED | PENDING |
| CHK-507 | Reason | PLAYWRIGHT_E2E | 16. ADJUSTMENTS / QUANTITY ALLOCATION > 16.1 Request/review | tests/Feature/ pending mapping | Reason | pending | PARTIAL | NOT EXECUTED | PENDING |
| CHK-508 | Notes | PLAYWRIGHT_E2E | 16. ADJUSTMENTS / QUANTITY ALLOCATION > 16.1 Request/review | tests/Feature/ pending mapping | Notes | pending | PARTIAL | NOT EXECUTED | PENDING |
| CHK-509 | Review queue | PLAYWRIGHT_E2E | 16. ADJUSTMENTS / QUANTITY ALLOCATION > 16.1 Request/review | tests/Feature/ pending mapping | Review queue | pending | PARTIAL | NOT EXECUTED | PENDING |
| CHK-510 | Original quantity | PLAYWRIGHT_E2E | 16. ADJUSTMENTS / QUANTITY ALLOCATION > 16.1 Request/review | tests/Feature/ pending mapping | Original quantity | pending | PARTIAL | NOT EXECUTED | PENDING |
| CHK-511 | Current allocation | PLAYWRIGHT_E2E | 16. ADJUSTMENTS / QUANTITY ALLOCATION > 16.1 Request/review | tests/Feature/ pending mapping | Current allocation | pending | PARTIAL | NOT EXECUTED | PENDING |
| CHK-512 | Inventory context | PLAYWRIGHT_E2E | 16. ADJUSTMENTS / QUANTITY ALLOCATION > 16.1 Request/review | tests/Feature/ pending mapping | Inventory context | pending | PARTIAL | NOT EXECUTED | PENDING |
| CHK-513 | Tax impact | DOMAIN_UNIT_PHP | 16. ADJUSTMENTS / QUANTITY ALLOCATION > 16.1 Request/review | tests/Feature/ pending mapping | Tax impact | pending | PARTIAL | NOT EXECUTED | PENDING |
| CHK-514 | Financial impact | PLAYWRIGHT_E2E | 16. ADJUSTMENTS / QUANTITY ALLOCATION > 16.1 Request/review | tests/Feature/ pending mapping | Financial impact | pending | PARTIAL | NOT EXECUTED | PENDING |
| CHK-515 | Approve | PLAYWRIGHT_E2E | 16. ADJUSTMENTS / QUANTITY ALLOCATION > 16.2 Actions | tests/Feature/ pending mapping | Approve | pending | PARTIAL | NOT EXECUTED | PENDING |
| CHK-516 | Reject | PLAYWRIGHT_E2E | 16. ADJUSTMENTS / QUANTITY ALLOCATION > 16.2 Actions | tests/Feature/ pending mapping | Reject | pending | PARTIAL | NOT EXECUTED | PENDING |
| CHK-517 | Valid approval succeeds | PLAYWRIGHT_E2E | 16. ADJUSTMENTS / QUANTITY ALLOCATION > 16.2 Actions | tests/Feature/ pending mapping | Valid approval succeeds | pending | PARTIAL | NOT EXECUTED | PENDING |
| CHK-518 | False 409 absent | PLAYWRIGHT_E2E | 16. ADJUSTMENTS / QUANTITY ALLOCATION > 16.2 Actions | tests/Feature/ pending mapping | False 409 absent | pending | PARTIAL | NOT EXECUTED | PENDING |
| CHK-519 | Real stale conflict protected | PLAYWRIGHT_E2E | 16. ADJUSTMENTS / QUANTITY ALLOCATION > 16.2 Actions | tests/Feature/ pending mapping | Real stale conflict protected | pending | PARTIAL | NOT EXECUTED | PENDING |
| CHK-520 | Duplicate action protected | PLAYWRIGHT_E2E | 16. ADJUSTMENTS / QUANTITY ALLOCATION > 16.2 Actions | tests/Feature/ pending mapping | Duplicate action protected | pending | PARTIAL | NOT EXECUTED | PENDING |
| CHK-521 | Authorization | PLAYWRIGHT_E2E | 16. ADJUSTMENTS / QUANTITY ALLOCATION > 16.2 Actions | tests/Feature/ pending mapping | Authorization | pending | PARTIAL | NOT EXECUTED | PENDING |
| CHK-522 | Original ordered quantity immutable | PLAYWRIGHT_E2E | 16. ADJUSTMENTS / QUANTITY ALLOCATION > 16.3 Integrity | tests/Feature/ pending mapping | Original ordered quantity immutable | pending | PARTIAL | NOT EXECUTED | PENDING |
| CHK-523 | Cancelled quantity correct | PLAYWRIGHT_E2E | 16. ADJUSTMENTS / QUANTITY ALLOCATION > 16.3 Integrity | tests/Feature/ pending mapping | Cancelled quantity correct | pending | PARTIAL | NOT EXECUTED | PENDING |
| CHK-524 | Fulfillable quantity correct | PLAYWRIGHT_E2E | 16. ADJUSTMENTS / QUANTITY ALLOCATION > 16.3 Integrity | tests/Feature/ pending mapping | Fulfillable quantity correct | pending | PARTIAL | NOT EXECUTED | PENDING |
| CHK-525 | Multiple adjustments | PLAYWRIGHT_E2E | 16. ADJUSTMENTS / QUANTITY ALLOCATION > 16.3 Integrity | tests/Feature/ pending mapping | Multiple adjustments | pending | PARTIAL | NOT EXECUTED | PENDING |
| CHK-526 | Allocation correct | PLAYWRIGHT_E2E | 16. ADJUSTMENTS / QUANTITY ALLOCATION > 16.3 Integrity | tests/Feature/ pending mapping | Allocation correct | pending | PARTIAL | NOT EXECUTED | PENDING |
| CHK-527 | Inventory impact | PLAYWRIGHT_E2E | 16. ADJUSTMENTS / QUANTITY ALLOCATION > 16.3 Integrity | tests/Feature/ pending mapping | Inventory impact | pending | PARTIAL | NOT EXECUTED | PENDING |
| CHK-528 | Tax impact | DOMAIN_UNIT_PHP | 16. ADJUSTMENTS / QUANTITY ALLOCATION > 16.3 Integrity | tests/Feature/ pending mapping | Tax impact | pending | PARTIAL | NOT EXECUTED | PENDING |
| CHK-529 | Financial impact | PLAYWRIGHT_E2E | 16. ADJUSTMENTS / QUANTITY ALLOCATION > 16.3 Integrity | tests/Feature/ pending mapping | Financial impact | pending | PARTIAL | NOT EXECUTED | PENDING |
| CHK-530 | Audit trail | PLAYWRIGHT_E2E | 16. ADJUSTMENTS / QUANTITY ALLOCATION > 16.3 Integrity | tests/Feature/ pending mapping | Audit trail | pending | PARTIAL | NOT EXECUTED | PENDING |
| CHK-531 | Inventory dashboard | PLAYWRIGHT_E2E | 17. INVENTORY / WAREHOUSE | tests/Feature/ pending mapping | Inventory dashboard | pending | NOT_TESTED | NOT EXECUTED | PENDING |
| CHK-532 | On hand | PLAYWRIGHT_E2E | 17. INVENTORY / WAREHOUSE | tests/Feature/ pending mapping | On hand | pending | NOT_TESTED | NOT EXECUTED | PENDING |
| CHK-533 | Reserved | PLAYWRIGHT_E2E | 17. INVENTORY / WAREHOUSE | tests/Feature/ pending mapping | Reserved | pending | NOT_TESTED | NOT EXECUTED | PENDING |
| CHK-534 | Available | PLAYWRIGHT_E2E | 17. INVENTORY / WAREHOUSE | tests/Feature/ pending mapping | Available | pending | NOT_TESTED | NOT EXECUTED | PENDING |
| CHK-535 | Damaged | PLAYWRIGHT_E2E | 17. INVENTORY / WAREHOUSE | tests/Feature/ pending mapping | Damaged | pending | NOT_TESTED | NOT EXECUTED | PENDING |
| CHK-536 | Movements | PLAYWRIGHT_E2E | 17. INVENTORY / WAREHOUSE | tests/Feature/ pending mapping | Movements | pending | NOT_TESTED | NOT EXECUTED | PENDING |
| CHK-537 | Fulfillment | PLAYWRIGHT_E2E | 17. INVENTORY / WAREHOUSE | tests/Feature/ pending mapping | Fulfillment | pending | NOT_TESTED | NOT EXECUTED | PENDING |
| CHK-538 | Picking | PLAYWRIGHT_E2E | 17. INVENTORY / WAREHOUSE | tests/Feature/ pending mapping | Picking | pending | NOT_TESTED | NOT EXECUTED | PENDING |
| CHK-539 | Processing | PLAYWRIGHT_E2E | 17. INVENTORY / WAREHOUSE | tests/Feature/ pending mapping | Processing | pending | NOT_TESTED | NOT EXECUTED | PENDING |
| CHK-540 | Exceptions | PLAYWRIGHT_E2E | 17. INVENTORY / WAREHOUSE | tests/Feature/ pending mapping | Exceptions | pending | NOT_TESTED | NOT EXECUTED | PENDING |
| CHK-541 | Damage handling | PLAYWRIGHT_E2E | 17. INVENTORY / WAREHOUSE | tests/Feature/ pending mapping | Damage handling | pending | NOT_TESTED | NOT EXECUTED | PENDING |
| CHK-542 | Inventory adjustments | PLAYWRIGHT_E2E | 17. INVENTORY / WAREHOUSE | tests/Feature/ pending mapping | Inventory adjustments | pending | NOT_TESTED | NOT EXECUTED | PENDING |
| CHK-543 | Order-linked allocation | PLAYWRIGHT_E2E | 17. INVENTORY / WAREHOUSE | tests/Feature/ pending mapping | Order-linked allocation | pending | NOT_TESTED | NOT EXECUTED | PENDING |
| CHK-544 | Available cannot become logically negative | PLAYWRIGHT_E2E | 17. INVENTORY / WAREHOUSE | tests/Feature/ pending mapping | Available cannot become logically negative | pending | NOT_TESTED | NOT EXECUTED | PENDING |
| CHK-545 | Damaged stock not sellable | PLAYWRIGHT_E2E | 17. INVENTORY / WAREHOUSE | tests/Feature/ pending mapping | Damaged stock not sellable | pending | NOT_TESTED | NOT EXECUTED | PENDING |
| CHK-546 | Reservations tied to orders | PLAYWRIGHT_E2E | 17. INVENTORY / WAREHOUSE | tests/Feature/ pending mapping | Reservations tied to orders | pending | NOT_TESTED | NOT EXECUTED | PENDING |
| CHK-547 | Partial cancellation releases correct quantity | PLAYWRIGHT_E2E | 17. INVENTORY / WAREHOUSE | tests/Feature/ pending mapping | Partial cancellation releases correct quantity | pending | NOT_TESTED | NOT EXECUTED | PENDING |
| CHK-548 | Concurrent allocation protected | PLAYWRIGHT_E2E | 17. INVENTORY / WAREHOUSE | tests/Feature/ pending mapping | Concurrent allocation protected | pending | NOT_TESTED | NOT EXECUTED | PENDING |
| CHK-549 | Stock exception workflow correct | PLAYWRIGHT_E2E | 17. INVENTORY / WAREHOUSE | tests/Feature/ pending mapping | Stock exception workflow correct | pending | NOT_TESTED | NOT EXECUTED | PENDING |
| CHK-550 | Audit coverage | PLAYWRIGHT_E2E | 17. INVENTORY / WAREHOUSE | tests/Feature/ pending mapping | Audit coverage | pending | NOT_TESTED | NOT EXECUTED | PENDING |
| CHK-551 | Delivery dashboard | PLAYWRIGHT_E2E | 18. DELIVERY | tests/Feature/ pending mapping | Delivery dashboard | pending | NOT_TESTED | NOT EXECUTED | PENDING |
| CHK-552 | Assigned list | PLAYWRIGHT_E2E | 18. DELIVERY | tests/Feature/ pending mapping | Assigned list | pending | NOT_TESTED | NOT EXECUTED | PENDING |
| CHK-553 | Detail | PLAYWRIGHT_E2E | 18. DELIVERY | tests/Feature/ pending mapping | Detail | pending | NOT_TESTED | NOT EXECUTED | PENDING |
| CHK-554 | Assign | PLAYWRIGHT_E2E | 18. DELIVERY | tests/Feature/ pending mapping | Assign | pending | NOT_TESTED | NOT EXECUTED | PENDING |
| CHK-555 | Accept | PLAYWRIGHT_E2E | 18. DELIVERY | tests/Feature/ pending mapping | Accept | pending | NOT_TESTED | NOT EXECUTED | PENDING |
| CHK-556 | Pickup | PLAYWRIGHT_E2E | 18. DELIVERY | tests/Feature/ pending mapping | Pickup | pending | NOT_TESTED | NOT EXECUTED | PENDING |
| CHK-557 | Out for delivery | PLAYWRIGHT_E2E | 18. DELIVERY | tests/Feature/ pending mapping | Out for delivery | pending | NOT_TESTED | NOT EXECUTED | PENDING |
| CHK-558 | Delivered | PLAYWRIGHT_E2E | 18. DELIVERY | tests/Feature/ pending mapping | Delivered | pending | NOT_TESTED | NOT EXECUTED | PENDING |
| CHK-559 | Failed | PLAYWRIGHT_E2E | 18. DELIVERY | tests/Feature/ pending mapping | Failed | pending | NOT_TESTED | NOT EXECUTED | PENDING |
| CHK-560 | Failure reason | PLAYWRIGHT_E2E | 18. DELIVERY | tests/Feature/ pending mapping | Failure reason | pending | NOT_TESTED | NOT EXECUTED | PENDING |
| CHK-561 | Reschedule | PLAYWRIGHT_E2E | 18. DELIVERY | tests/Feature/ pending mapping | Reschedule | pending | NOT_TESTED | NOT EXECUTED | PENDING |
| CHK-562 | Return to warehouse | PLAYWRIGHT_E2E | 18. DELIVERY | tests/Feature/ pending mapping | Return to warehouse | pending | NOT_TESTED | NOT EXECUTED | PENDING |
| CHK-563 | History | PLAYWRIGHT_E2E | 18. DELIVERY | tests/Feature/ pending mapping | History | pending | NOT_TESTED | NOT EXECUTED | PENDING |
| CHK-564 | Current deliverable quantity | PLAYWRIGHT_E2E | 18. DELIVERY | tests/Feature/ pending mapping | Current deliverable quantity | pending | NOT_TESTED | NOT EXECUTED | PENDING |
| CHK-565 | Cancelled quantity excluded | PLAYWRIGHT_E2E | 18. DELIVERY | tests/Feature/ pending mapping | Cancelled quantity excluded | pending | NOT_TESTED | NOT EXECUTED | PENDING |
| CHK-566 | Financial restrictions enforced | PLAYWRIGHT_E2E | 18. DELIVERY | tests/Feature/ pending mapping | Financial restrictions enforced | pending | NOT_TESTED | NOT EXECUTED | PENDING |
| CHK-567 | Signature capture | PLAYWRIGHT_E2E | 18. DELIVERY | tests/Feature/ pending mapping | Signature capture | pending | NOT_TESTED | NOT EXECUTED | PENDING |
| CHK-568 | Signature preview | PLAYWRIGHT_E2E | 18. DELIVERY | tests/Feature/ pending mapping | Signature preview | pending | NOT_TESTED | NOT EXECUTED | PENDING |
| CHK-569 | POD upload | PLAYWRIGHT_E2E | 18. DELIVERY | tests/Feature/ pending mapping | POD upload | pending | NOT_TESTED | NOT EXECUTED | PENDING |
| CHK-570 | POD preview | PLAYWRIGHT_E2E | 18. DELIVERY | tests/Feature/ pending mapping | POD preview | pending | NOT_TESTED | NOT EXECUTED | PENDING |
| CHK-571 | Invalid file | PLAYWRIGHT_E2E | 18. DELIVERY | tests/Feature/ pending mapping | Invalid file | pending | NOT_TESTED | NOT EXECUTED | PENDING |
| CHK-572 | Oversize file | PLAYWRIGHT_E2E | 18. DELIVERY | tests/Feature/ pending mapping | Oversize file | pending | NOT_TESTED | NOT EXECUTED | PENDING |
| CHK-573 | Private access | PLAYWRIGHT_E2E | 18. DELIVERY | tests/Feature/ pending mapping | Private access | pending | NOT_TESTED | NOT EXECUTED | PENDING |
| CHK-574 | Scope protection | PLAYWRIGHT_E2E | 18. DELIVERY | tests/Feature/ pending mapping | Scope protection | pending | NOT_TESTED | NOT EXECUTED | PENDING |
| CHK-575 | Return request | PLAYWRIGHT_E2E | 19. RETURNS | tests/Feature/ pending mapping | Return request | pending | NOT_TESTED | NOT EXECUTED | PENDING |
| CHK-576 | Eligible quantity | PLAYWRIGHT_E2E | 19. RETURNS | tests/Feature/ pending mapping | Eligible quantity | pending | NOT_TESTED | NOT EXECUTED | PENDING |
| CHK-577 | Review | PLAYWRIGHT_E2E | 19. RETURNS | tests/Feature/ pending mapping | Review | pending | NOT_TESTED | NOT EXECUTED | PENDING |
| CHK-578 | Inspection | PLAYWRIGHT_E2E | 19. RETURNS | tests/Feature/ pending mapping | Inspection | pending | NOT_TESTED | NOT EXECUTED | PENDING |
| CHK-579 | Approved quantity | PLAYWRIGHT_E2E | 19. RETURNS | tests/Feature/ pending mapping | Approved quantity | pending | NOT_TESTED | NOT EXECUTED | PENDING |
| CHK-580 | Rejected quantity | PLAYWRIGHT_E2E | 19. RETURNS | tests/Feature/ pending mapping | Rejected quantity | pending | NOT_TESTED | NOT EXECUTED | PENDING |
| CHK-581 | Inventory disposition | PLAYWRIGHT_E2E | 19. RETURNS | tests/Feature/ pending mapping | Inventory disposition | pending | NOT_TESTED | NOT EXECUTED | PENDING |
| CHK-582 | Financial consequence | PLAYWRIGHT_E2E | 19. RETURNS | tests/Feature/ pending mapping | Financial consequence | pending | NOT_TESTED | NOT EXECUTED | PENDING |
| CHK-583 | Credit/refund connection | POSTGRES_DB_INVARIANT | 19. RETURNS | tests/Feature/ pending mapping | Credit/refund connection | pending | NOT_TESTED | NOT EXECUTED | PENDING |
| CHK-584 | Evidence | PLAYWRIGHT_E2E | 19. RETURNS | tests/Feature/ pending mapping | Evidence | pending | NOT_TESTED | NOT EXECUTED | PENDING |
| CHK-585 | Audit | PLAYWRIGHT_E2E | 19. RETURNS | tests/Feature/ pending mapping | Audit | pending | NOT_TESTED | NOT EXECUTED | PENDING |
| CHK-586 | Historical order preserved | PLAYWRIGHT_E2E | 19. RETURNS | tests/Feature/ pending mapping | Historical order preserved | pending | NOT_TESTED | NOT EXECUTED | PENDING |
| CHK-587 | Eligibility | PLAYWRIGHT_E2E | 20. CREDITS / REFUNDS | tests/Feature/ pending mapping | Eligibility | pending | NOT_TESTED | NOT EXECUTED | PENDING |
| CHK-588 | Credit note | POSTGRES_DB_INVARIANT | 20. CREDITS / REFUNDS | tests/Feature/ pending mapping | Credit note | pending | NOT_TESTED | NOT EXECUTED | PENDING |
| CHK-589 | Refund request | PLAYWRIGHT_E2E | 20. CREDITS / REFUNDS | tests/Feature/ pending mapping | Refund request | pending | NOT_TESTED | NOT EXECUTED | PENDING |
| CHK-590 | Approval | PLAYWRIGHT_E2E | 20. CREDITS / REFUNDS | tests/Feature/ pending mapping | Approval | pending | NOT_TESTED | NOT EXECUTED | PENDING |
| CHK-591 | Processing | PLAYWRIGHT_E2E | 20. CREDITS / REFUNDS | tests/Feature/ pending mapping | Processing | pending | NOT_TESTED | NOT EXECUTED | PENDING |
| CHK-592 | Duplicate protection | PLAYWRIGHT_E2E | 20. CREDITS / REFUNDS | tests/Feature/ pending mapping | Duplicate protection | pending | NOT_TESTED | NOT EXECUTED | PENDING |
| CHK-593 | Amount validation | PLAYWRIGHT_E2E | 20. CREDITS / REFUNDS | tests/Feature/ pending mapping | Amount validation | pending | NOT_TESTED | NOT EXECUTED | PENDING |
| CHK-594 | Excess refund blocked | PLAYWRIGHT_E2E | 20. CREDITS / REFUNDS | tests/Feature/ pending mapping | Excess refund blocked | pending | NOT_TESTED | NOT EXECUTED | PENDING |
| CHK-595 | AR effect | PLAYWRIGHT_E2E | 20. CREDITS / REFUNDS | tests/Feature/ pending mapping | AR effect | pending | NOT_TESTED | NOT EXECUTED | PENDING |
| CHK-596 | Accounting effect | PLAYWRIGHT_E2E | 20. CREDITS / REFUNDS | tests/Feature/ pending mapping | Accounting effect | pending | NOT_TESTED | NOT EXECUTED | PENDING |
| CHK-597 | Reversal/history | PLAYWRIGHT_E2E | 20. CREDITS / REFUNDS | tests/Feature/ pending mapping | Reversal/history | pending | NOT_TESTED | NOT EXECUTED | PENDING |
| CHK-598 | Authorization | PLAYWRIGHT_E2E | 20. CREDITS / REFUNDS | tests/Feature/ pending mapping | Authorization | pending | NOT_TESTED | NOT EXECUTED | PENDING |
| CHK-599 | Maker-checker where applicable | PLAYWRIGHT_E2E | 20. CREDITS / REFUNDS | tests/Feature/ pending mapping | Maker-checker where applicable | pending | NOT_TESTED | NOT EXECUTED | PENDING |
| CHK-600 | Accounts visible | POSTGRES_DB_INVARIANT | 21. ACCOUNTING > 21.1 Chart of Accounts | tests/Feature/ pending mapping | Accounts visible | pending | PARTIAL | NOT EXECUTED | PENDING |
| CHK-601 | Account types correct | POSTGRES_DB_INVARIANT | 21. ACCOUNTING > 21.1 Chart of Accounts | tests/Feature/ pending mapping | Account types correct | pending | PARTIAL | NOT EXECUTED | PENDING |
| CHK-602 | Codes correct | POSTGRES_DB_INVARIANT | 21. ACCOUNTING > 21.1 Chart of Accounts | tests/Feature/ pending mapping | Codes correct | pending | PARTIAL | NOT EXECUTED | PENDING |
| CHK-603 | Duplicate account handling | POSTGRES_DB_INVARIANT | 21. ACCOUNTING > 21.1 Chart of Accounts | tests/Feature/ pending mapping | Duplicate account handling | pending | PARTIAL | NOT EXECUTED | PENDING |
| CHK-604 | Permissions | POSTGRES_DB_INVARIANT | 21. ACCOUNTING > 21.1 Chart of Accounts | tests/Feature/ pending mapping | Permissions | pending | PARTIAL | NOT EXECUTED | PENDING |
| CHK-605 | Journal entries | POSTGRES_DB_INVARIANT | 21. ACCOUNTING > 21.2 Journals / GL | tests/Feature/ pending mapping | Journal entries | pending | PARTIAL | NOT EXECUTED | PENDING |
| CHK-606 | Journal lines | POSTGRES_DB_INVARIANT | 21. ACCOUNTING > 21.2 Journals / GL | tests/Feature/ pending mapping | Journal lines | pending | PARTIAL | NOT EXECUTED | PENDING |
| CHK-607 | Source transaction traceability | POSTGRES_DB_INVARIANT | 21. ACCOUNTING > 21.2 Journals / GL | tests/Feature/ pending mapping | Source transaction traceability | pending | PARTIAL | NOT EXECUTED | PENDING |
| CHK-608 | Debits = credits | POSTGRES_DB_INVARIANT | 21. ACCOUNTING > 21.2 Journals / GL | tests/Feature/ pending mapping | Debits = credits | pending | PARTIAL | NOT EXECUTED | PENDING |
| CHK-609 | Posted status | POSTGRES_DB_INVARIANT | 21. ACCOUNTING > 21.2 Journals / GL | tests/Feature/ pending mapping | Posted status | pending | PARTIAL | NOT EXECUTED | PENDING |
| CHK-610 | Historical immutability | POSTGRES_DB_INVARIANT | 21. ACCOUNTING > 21.2 Journals / GL | tests/Feature/ pending mapping | Historical immutability | pending | PARTIAL | NOT EXECUTED | PENDING |
| CHK-611 | Reversal behavior | POSTGRES_DB_INVARIANT | 21. ACCOUNTING > 21.2 Journals / GL | tests/Feature/ pending mapping | Reversal behavior | pending | PARTIAL | NOT EXECUTED | PENDING |
| CHK-612 | No duplicate posting | POSTGRES_DB_INVARIANT | 21. ACCOUNTING > 21.2 Journals / GL | tests/Feature/ pending mapping | No duplicate posting | pending | PARTIAL | NOT EXECUTED | PENDING |
| CHK-613 | Correct dates | POSTGRES_DB_INVARIANT | 21. ACCOUNTING > 21.2 Journals / GL | tests/Feature/ pending mapping | Correct dates | pending | PARTIAL | NOT EXECUTED | PENDING |
| CHK-614 | Correct totals | POSTGRES_DB_INVARIANT | 21. ACCOUNTING > 21.3 Trial Balance | tests/Feature/ pending mapping | Correct totals | pending | PARTIAL | NOT EXECUTED | PENDING |
| CHK-615 | Debit/credit equality | POSTGRES_DB_INVARIANT | 21. ACCOUNTING > 21.3 Trial Balance | tests/Feature/ pending mapping | Debit/credit equality | pending | PARTIAL | NOT EXECUTED | PENDING |
| CHK-616 | Period filtering | POSTGRES_DB_INVARIANT | 21. ACCOUNTING > 21.3 Trial Balance | tests/Feature/ pending mapping | Period filtering | pending | PARTIAL | NOT EXECUTED | PENDING |
| CHK-617 | Account drilldown | POSTGRES_DB_INVARIANT | 21. ACCOUNTING > 21.3 Trial Balance | tests/Feature/ pending mapping | Account drilldown | pending | PARTIAL | NOT EXECUTED | PENDING |
| CHK-618 | Empty period | POSTGRES_DB_INVARIANT | 21. ACCOUNTING > 21.3 Trial Balance | tests/Feature/ pending mapping | Empty period | pending | PARTIAL | NOT EXECUTED | PENDING |
| CHK-619 | Revenue reconciles to GL | POSTGRES_DB_INVARIANT | 21. ACCOUNTING > 21.4 Profit & Loss | tests/Feature/ pending mapping | Revenue reconciles to GL | pending | PARTIAL | NOT EXECUTED | PENDING |
| CHK-620 | Discounts reconcile | POSTGRES_DB_INVARIANT | 21. ACCOUNTING > 21.4 Profit & Loss | tests/Feature/ pending mapping | Discounts reconcile | pending | PARTIAL | NOT EXECUTED | PENDING |
| CHK-621 | COGS reconciles | POSTGRES_DB_INVARIANT | 21. ACCOUNTING > 21.4 Profit & Loss | tests/Feature/ pending mapping | COGS reconciles | pending | PARTIAL | NOT EXECUTED | PENDING |
| CHK-622 | Expenses reconcile | POSTGRES_DB_INVARIANT | 21. ACCOUNTING > 21.4 Profit & Loss | tests/Feature/ pending mapping | Expenses reconcile | pending | PARTIAL | NOT EXECUTED | PENDING |
| CHK-623 | Gross profit correct | POSTGRES_DB_INVARIANT | 21. ACCOUNTING > 21.4 Profit & Loss | tests/Feature/ pending mapping | Gross profit correct | pending | PARTIAL | NOT EXECUTED | PENDING |
| CHK-624 | Net operating income correct | POSTGRES_DB_INVARIANT | 21. ACCOUNTING > 21.4 Profit & Loss | tests/Feature/ pending mapping | Net operating income correct | pending | PARTIAL | NOT EXECUTED | PENDING |
| CHK-625 | Date boundaries correct | POSTGRES_DB_INVARIANT | 21. ACCOUNTING > 21.4 Profit & Loss | tests/Feature/ pending mapping | Date boundaries correct | pending | PARTIAL | NOT EXECUTED | PENDING |
| CHK-626 | Empty period returns zero | POSTGRES_DB_INVARIANT | 21. ACCOUNTING > 21.4 Profit & Loss | tests/Feature/ pending mapping | Empty period returns zero | pending | PARTIAL | NOT EXECUTED | PENDING |
| CHK-627 | Reversals handled | POSTGRES_DB_INVARIANT | 21. ACCOUNTING > 21.4 Profit & Loss | tests/Feature/ pending mapping | Reversals handled | pending | PARTIAL | NOT EXECUTED | PENDING |
| CHK-628 | Unposted entries excluded correctly | POSTGRES_DB_INVARIANT | 21. ACCOUNTING > 21.4 Profit & Loss | tests/Feature/ pending mapping | Unposted entries excluded correctly | pending | PARTIAL | NOT EXECUTED | PENDING |
| CHK-629 | Assets | POSTGRES_DB_INVARIANT | 21. ACCOUNTING > 21.5 Balance Sheet | tests/Feature/ pending mapping | Assets | pending | PARTIAL | NOT EXECUTED | PENDING |
| CHK-630 | Liabilities | POSTGRES_DB_INVARIANT | 21. ACCOUNTING > 21.5 Balance Sheet | tests/Feature/ pending mapping | Liabilities | pending | PARTIAL | NOT EXECUTED | PENDING |
| CHK-631 | Equity | POSTGRES_DB_INVARIANT | 21. ACCOUNTING > 21.5 Balance Sheet | tests/Feature/ pending mapping | Equity | pending | PARTIAL | NOT EXECUTED | PENDING |
| CHK-632 | AR | POSTGRES_DB_INVARIANT | 21. ACCOUNTING > 21.5 Balance Sheet | tests/Feature/ pending mapping | AR | pending | PARTIAL | NOT EXECUTED | PENDING |
| CHK-633 | AP | POSTGRES_DB_INVARIANT | 21. ACCOUNTING > 21.5 Balance Sheet | tests/Feature/ pending mapping | AP | pending | PARTIAL | NOT EXECUTED | PENDING |
| CHK-634 | Cash | POSTGRES_DB_INVARIANT | 21. ACCOUNTING > 21.5 Balance Sheet | tests/Feature/ pending mapping | Cash | pending | PARTIAL | NOT EXECUTED | PENDING |
| CHK-635 | Balancing equation | POSTGRES_DB_INVARIANT | 21. ACCOUNTING > 21.5 Balance Sheet | tests/Feature/ pending mapping | Balancing equation | pending | PARTIAL | NOT EXECUTED | PENDING |
| CHK-636 | Period/date semantics | POSTGRES_DB_INVARIANT | 21. ACCOUNTING > 21.5 Balance Sheet | tests/Feature/ pending mapping | Period/date semantics | pending | PARTIAL | NOT EXECUTED | PENDING |
| CHK-637 | Cash movements | POSTGRES_DB_INVARIANT | 21. ACCOUNTING > 21.6 Cash reconciliation | tests/Feature/ pending mapping | Cash movements | pending | PARTIAL | NOT EXECUTED | PENDING |
| CHK-638 | Payment link | POSTGRES_DB_INVARIANT | 21. ACCOUNTING > 21.6 Cash reconciliation | tests/Feature/ pending mapping | Payment link | pending | PARTIAL | NOT EXECUTED | PENDING |
| CHK-639 | Reconciliation state | POSTGRES_DB_INVARIANT | 21. ACCOUNTING > 21.6 Cash reconciliation | tests/Feature/ pending mapping | Reconciliation state | pending | PARTIAL | NOT EXECUTED | PENDING |
| CHK-640 | Difference detection | POSTGRES_DB_INVARIANT | 21. ACCOUNTING > 21.6 Cash reconciliation | tests/Feature/ pending mapping | Difference detection | pending | PARTIAL | NOT EXECUTED | PENDING |
| CHK-641 | Audit trail | POSTGRES_DB_INVARIANT | 21. ACCOUNTING > 21.6 Cash reconciliation | tests/Feature/ pending mapping | Audit trail | pending | PARTIAL | NOT EXECUTED | PENDING |
| CHK-642 | Sales reports | PLAYWRIGHT_E2E | 11.2 Reporting modules and role access boundaries | 10_invoices_reports_notifications.spec.ts | Sales reports | 10_admin_reports_sales.png, 10_admin_reports_customers.png | PASS | 2026-09-12 | AUDIT-RUN-20260912-154532 |
| CHK-643 | Customer reports | PLAYWRIGHT_E2E | 11.2 Reporting modules and role access boundaries | 10_invoices_reports_notifications.spec.ts | Customer reports | 10_admin_reports_sales.png, 10_admin_reports_customers.png | PASS | 2026-09-12 | AUDIT-RUN-20260912-154532 |
| CHK-644 | Salesman performance | PLAYWRIGHT_E2E | 22. REPORTING / ANALYTICS | tests/Feature/ pending mapping | Salesman performance | pending | PARTIAL | NOT EXECUTED | PENDING |
| CHK-645 | Inventory reports | PLAYWRIGHT_E2E | 22. REPORTING / ANALYTICS | tests/Feature/ pending mapping | Inventory reports | pending | PARTIAL | NOT EXECUTED | PENDING |
| CHK-646 | Delivery reports | PLAYWRIGHT_E2E | 22. REPORTING / ANALYTICS | tests/Feature/ pending mapping | Delivery reports | pending | PARTIAL | NOT EXECUTED | PENDING |
| CHK-647 | Accounting reports | PLAYWRIGHT_E2E | 22. REPORTING / ANALYTICS | tests/Feature/ pending mapping | Accounting reports | pending | PARTIAL | NOT EXECUTED | PENDING |
| CHK-648 | Date filters | PLAYWRIGHT_E2E | 22. REPORTING / ANALYTICS | tests/Feature/ pending mapping | Date filters | pending | PARTIAL | NOT EXECUTED | PENDING |
| CHK-649 | Other filters | PLAYWRIGHT_E2E | 22. REPORTING / ANALYTICS | tests/Feature/ pending mapping | Other filters | pending | PARTIAL | NOT EXECUTED | PENDING |
| CHK-650 | Totals | PLAYWRIGHT_E2E | 22. REPORTING / ANALYTICS | tests/Feature/ pending mapping | Totals | pending | PARTIAL | NOT EXECUTED | PENDING |
| CHK-651 | Drilldown | PLAYWRIGHT_E2E | 22. REPORTING / ANALYTICS | tests/Feature/ pending mapping | Drilldown | pending | PARTIAL | NOT EXECUTED | PENDING |
| CHK-652 | Export | PLAYWRIGHT_E2E | 22. REPORTING / ANALYTICS | tests/Feature/ pending mapping | Export | pending | PARTIAL | NOT EXECUTED | PENDING |
| CHK-653 | Large data behavior | PLAYWRIGHT_E2E | 22. REPORTING / ANALYTICS | tests/Feature/ pending mapping | Large data behavior | pending | PARTIAL | NOT EXECUTED | PENDING |
| CHK-654 | Permission enforcement | PLAYWRIGHT_E2E | 22. REPORTING / ANALYTICS | tests/Feature/ pending mapping | Permission enforcement | pending | PARTIAL | NOT EXECUTED | PENDING |
| CHK-655 | Organization-wide analytics hidden | PLAYWRIGHT_E2E | 22. REPORTING / ANALYTICS | tests/Feature/ pending mapping | Organization-wide analytics hidden | pending | PARTIAL | NOT EXECUTED | PENDING |
| CHK-656 | Direct URL blocked | PLAYWRIGHT_E2E | 22. REPORTING / ANALYTICS | tests/Feature/ pending mapping | Direct URL blocked | pending | PARTIAL | NOT EXECUTED | PENDING |
| CHK-657 | Data limited to authorized scope | PLAYWRIGHT_E2E | 22. REPORTING / ANALYTICS | tests/Feature/ pending mapping | Data limited to authorized scope | pending | PARTIAL | NOT EXECUTED | PENDING |
| CHK-658 | Invoice availability | PLAYWRIGHT_E2E | 23. INVOICES / DOCUMENTS | tests/Feature/ pending mapping | Invoice availability | pending | PARTIAL | NOT EXECUTED | PENDING |
| CHK-659 | Preview | PLAYWRIGHT_E2E | 23. INVOICES / DOCUMENTS | tests/Feature/ pending mapping | Preview | pending | PARTIAL | NOT EXECUTED | PENDING |
| CHK-660 | Print | PLAYWRIGHT_E2E | 23. INVOICES / DOCUMENTS | tests/Feature/ pending mapping | Print | pending | PARTIAL | NOT EXECUTED | PENDING |
| CHK-661 | PDF/download | PLAYWRIGHT_E2E | 23. INVOICES / DOCUMENTS | tests/Feature/ pending mapping | PDF/download | pending | PARTIAL | NOT EXECUTED | PENDING |
| CHK-662 | Historical reopen/reprint | PLAYWRIGHT_E2E | 23. INVOICES / DOCUMENTS | tests/Feature/ pending mapping | Historical reopen/reprint | pending | PARTIAL | NOT EXECUTED | PENDING |
| CHK-663 | Invoice number | PLAYWRIGHT_E2E | 23. INVOICES / DOCUMENTS | tests/Feature/ pending mapping | Invoice number | pending | PARTIAL | NOT EXECUTED | PENDING |
| CHK-664 | Customer details | PLAYWRIGHT_E2E | 23. INVOICES / DOCUMENTS | tests/Feature/ pending mapping | Customer details | pending | PARTIAL | NOT EXECUTED | PENDING |
| CHK-665 | Line items | PLAYWRIGHT_E2E | 23. INVOICES / DOCUMENTS | tests/Feature/ pending mapping | Line items | pending | PARTIAL | NOT EXECUTED | PENDING |
| CHK-666 | Tax | DOMAIN_UNIT_PHP | 23. INVOICES / DOCUMENTS | tests/Feature/ pending mapping | Tax | pending | PARTIAL | NOT EXECUTED | PENDING |
| CHK-667 | Totals | PLAYWRIGHT_E2E | 23. INVOICES / DOCUMENTS | tests/Feature/ pending mapping | Totals | pending | PARTIAL | NOT EXECUTED | PENDING |
| CHK-668 | Payment information | PLAYWRIGHT_E2E | 23. INVOICES / DOCUMENTS | tests/Feature/ pending mapping | Payment information | pending | PARTIAL | NOT EXECUTED | PENDING |
| CHK-669 | No product images | PLAYWRIGHT_E2E | 23. INVOICES / DOCUMENTS | tests/Feature/ pending mapping | No product images | pending | PARTIAL | NOT EXECUTED | PENDING |
| CHK-670 | Correct layout | PLAYWRIGHT_E2E | 23. INVOICES / DOCUMENTS | tests/Feature/ pending mapping | Correct layout | pending | PARTIAL | NOT EXECUTED | PENDING |
| CHK-671 | Immutable historical values | PLAYWRIGHT_E2E | 23. INVOICES / DOCUMENTS | tests/Feature/ pending mapping | Immutable historical values | pending | PARTIAL | NOT EXECUTED | PENDING |
| CHK-672 | Authorized access | PLAYWRIGHT_E2E | 23. INVOICES / DOCUMENTS | tests/Feature/ pending mapping | Authorized access | pending | PARTIAL | NOT EXECUTED | PENDING |
| CHK-673 | Unauthorized access blocked | HTTP_API_SECURITY | 23. INVOICES / DOCUMENTS | tests/Feature/ pending mapping | Unauthorized access blocked | pending | PARTIAL | NOT EXECUTED | PENDING |
| CHK-674 | Private S3 document access | PLAYWRIGHT_E2E | 23. INVOICES / DOCUMENTS | tests/Feature/ pending mapping | Private S3 document access | pending | PARTIAL | NOT EXECUTED | PENDING |
| CHK-675 | Signed URL expiry | PLAYWRIGHT_E2E | 23. INVOICES / DOCUMENTS | tests/Feature/ pending mapping | Signed URL expiry | pending | PARTIAL | NOT EXECUTED | PENDING |
| CHK-676 | Notification center | PLAYWRIGHT_E2E | 24. NOTIFICATIONS | tests/Feature/ pending mapping | Notification center | pending | PARTIAL | NOT EXECUTED | PENDING |
| CHK-677 | Unread state | PLAYWRIGHT_E2E | 24. NOTIFICATIONS | tests/Feature/ pending mapping | Unread state | pending | PARTIAL | NOT EXECUTED | PENDING |
| CHK-678 | Mark read | PLAYWRIGHT_E2E | 24. NOTIFICATIONS | tests/Feature/ pending mapping | Mark read | pending | PARTIAL | NOT EXECUTED | PENDING |
| CHK-679 | Order events | PLAYWRIGHT_E2E | 24. NOTIFICATIONS | tests/Feature/ pending mapping | Order events | pending | PARTIAL | NOT EXECUTED | PENDING |
| CHK-680 | Payment events | PLAYWRIGHT_E2E | 24. NOTIFICATIONS | tests/Feature/ pending mapping | Payment events | pending | PARTIAL | NOT EXECUTED | PENDING |
| CHK-681 | Adjustment events | PLAYWRIGHT_E2E | 24. NOTIFICATIONS | tests/Feature/ pending mapping | Adjustment events | pending | PARTIAL | NOT EXECUTED | PENDING |
| CHK-682 | Delivery events | PLAYWRIGHT_E2E | 24. NOTIFICATIONS | tests/Feature/ pending mapping | Delivery events | pending | PARTIAL | NOT EXECUTED | PENDING |
| CHK-683 | Preferences | PLAYWRIGHT_E2E | 24. NOTIFICATIONS | tests/Feature/ pending mapping | Preferences | pending | PARTIAL | NOT EXECUTED | PENDING |
| CHK-684 | Role-appropriate visibility | HTTP_API_SECURITY | 24. NOTIFICATIONS | tests/Feature/ pending mapping | Role-appropriate visibility | pending | PARTIAL | NOT EXECUTED | PENDING |
| CHK-685 | No sensitive leakage | PLAYWRIGHT_E2E | 24. NOTIFICATIONS | tests/Feature/ pending mapping | No sensitive leakage | pending | PARTIAL | NOT EXECUTED | PENDING |
| CHK-686 | Duplicate notification protection where applicable | PLAYWRIGHT_E2E | 24. NOTIFICATIONS | tests/Feature/ pending mapping | Duplicate notification protection where applicable | pending | PARTIAL | NOT EXECUTED | PENDING |
| CHK-687 | Login | PLAYWRIGHT_E2E | 25. AUDIT LOGS / SECURITY LOGGING | tests/Feature/ pending mapping | Login | pending | PARTIAL | NOT EXECUTED | PENDING |
| CHK-688 | Failed login | PLAYWRIGHT_E2E | 25. AUDIT LOGS / SECURITY LOGGING | tests/Feature/ pending mapping | Failed login | pending | PARTIAL | NOT EXECUTED | PENDING |
| CHK-689 | Customer changes | PLAYWRIGHT_E2E | 25. AUDIT LOGS / SECURITY LOGGING | tests/Feature/ pending mapping | Customer changes | pending | PARTIAL | NOT EXECUTED | PENDING |
| CHK-690 | Product changes | PLAYWRIGHT_E2E | 25. AUDIT LOGS / SECURITY LOGGING | tests/Feature/ pending mapping | Product changes | pending | PARTIAL | NOT EXECUTED | PENDING |
| CHK-691 | Price changes | PLAYWRIGHT_E2E | 25. AUDIT LOGS / SECURITY LOGGING | tests/Feature/ pending mapping | Price changes | pending | PARTIAL | NOT EXECUTED | PENDING |
| CHK-692 | Tax changes | DOMAIN_UNIT_PHP | 25. AUDIT LOGS / SECURITY LOGGING | tests/Feature/ pending mapping | Tax changes | pending | PARTIAL | NOT EXECUTED | PENDING |
| CHK-693 | Order creation | PLAYWRIGHT_E2E | 25. AUDIT LOGS / SECURITY LOGGING | tests/Feature/ pending mapping | Order creation | pending | PARTIAL | NOT EXECUTED | PENDING |
| CHK-694 | Approval/rejection | PLAYWRIGHT_E2E | 25. AUDIT LOGS / SECURITY LOGGING | tests/Feature/ pending mapping | Approval/rejection | pending | PARTIAL | NOT EXECUTED | PENDING |
| CHK-695 | Adjustments | PLAYWRIGHT_E2E | 25. AUDIT LOGS / SECURITY LOGGING | tests/Feature/ pending mapping | Adjustments | pending | PARTIAL | NOT EXECUTED | PENDING |
| CHK-696 | Payment creation | PLAYWRIGHT_E2E | 25. AUDIT LOGS / SECURITY LOGGING | tests/Feature/ pending mapping | Payment creation | pending | PARTIAL | NOT EXECUTED | PENDING |
| CHK-697 | Payment verification | PLAYWRIGHT_E2E | 25. AUDIT LOGS / SECURITY LOGGING | tests/Feature/ pending mapping | Payment verification | pending | PARTIAL | NOT EXECUTED | PENDING |
| CHK-698 | Payment reversal | PLAYWRIGHT_E2E | 25. AUDIT LOGS / SECURITY LOGGING | tests/Feature/ pending mapping | Payment reversal | pending | PARTIAL | NOT EXECUTED | PENDING |
| CHK-699 | Returns | PLAYWRIGHT_E2E | 25. AUDIT LOGS / SECURITY LOGGING | tests/Feature/ pending mapping | Returns | pending | PARTIAL | NOT EXECUTED | PENDING |
| CHK-700 | Credits/refunds | POSTGRES_DB_INVARIANT | 25. AUDIT LOGS / SECURITY LOGGING | tests/Feature/ pending mapping | Credits/refunds | pending | PARTIAL | NOT EXECUTED | PENDING |
| CHK-701 | Inventory changes | PLAYWRIGHT_E2E | 25. AUDIT LOGS / SECURITY LOGGING | tests/Feature/ pending mapping | Inventory changes | pending | PARTIAL | NOT EXECUTED | PENDING |
| CHK-702 | Delivery changes | PLAYWRIGHT_E2E | 25. AUDIT LOGS / SECURITY LOGGING | tests/Feature/ pending mapping | Delivery changes | pending | PARTIAL | NOT EXECUTED | PENDING |
| CHK-703 | Permission changes | PLAYWRIGHT_E2E | 25. AUDIT LOGS / SECURITY LOGGING | tests/Feature/ pending mapping | Permission changes | pending | PARTIAL | NOT EXECUTED | PENDING |
| CHK-704 | Accounting posting/reversal | PLAYWRIGHT_E2E | 25. AUDIT LOGS / SECURITY LOGGING | tests/Feature/ pending mapping | Accounting posting/reversal | pending | PARTIAL | NOT EXECUTED | PENDING |
| CHK-705 | Actor | PLAYWRIGHT_E2E | 25. AUDIT LOGS / SECURITY LOGGING | tests/Feature/ pending mapping | Actor | pending | PARTIAL | NOT EXECUTED | PENDING |
| CHK-706 | Role | HTTP_API_SECURITY | 25. AUDIT LOGS / SECURITY LOGGING | tests/Feature/ pending mapping | Role | pending | PARTIAL | NOT EXECUTED | PENDING |
| CHK-707 | Timestamp | PLAYWRIGHT_E2E | 25. AUDIT LOGS / SECURITY LOGGING | tests/Feature/ pending mapping | Timestamp | pending | PARTIAL | NOT EXECUTED | PENDING |
| CHK-708 | Entity/entity ID | PLAYWRIGHT_E2E | 25. AUDIT LOGS / SECURITY LOGGING | tests/Feature/ pending mapping | Entity/entity ID | pending | PARTIAL | NOT EXECUTED | PENDING |
| CHK-709 | Before/after where applicable | PLAYWRIGHT_E2E | 25. AUDIT LOGS / SECURITY LOGGING | tests/Feature/ pending mapping | Before/after where applicable | pending | PARTIAL | NOT EXECUTED | PENDING |
| CHK-710 | Reason | PLAYWRIGHT_E2E | 25. AUDIT LOGS / SECURITY LOGGING | tests/Feature/ pending mapping | Reason | pending | PARTIAL | NOT EXECUTED | PENDING |
| CHK-711 | No secrets | PLAYWRIGHT_E2E | 25. AUDIT LOGS / SECURITY LOGGING | tests/Feature/ pending mapping | No secrets | pending | PARTIAL | NOT EXECUTED | PENDING |
| CHK-712 | No passwords | PLAYWRIGHT_E2E | 25. AUDIT LOGS / SECURITY LOGGING | tests/Feature/ pending mapping | No passwords | pending | PARTIAL | NOT EXECUTED | PENDING |
| CHK-713 | No TOTP | PLAYWRIGHT_E2E | 25. AUDIT LOGS / SECURITY LOGGING | tests/Feature/ pending mapping | No TOTP | pending | PARTIAL | NOT EXECUTED | PENDING |
| CHK-714 | No signed URLs | PLAYWRIGHT_E2E | 25. AUDIT LOGS / SECURITY LOGGING | tests/Feature/ pending mapping | No signed URLs | pending | PARTIAL | NOT EXECUTED | PENDING |
| CHK-715 | Historical records preserved | PLAYWRIGHT_E2E | 25. AUDIT LOGS / SECURITY LOGGING | tests/Feature/ pending mapping | Historical records preserved | pending | PARTIAL | NOT EXECUTED | PENDING |
| CHK-716 | Cannot view another Salesman's customer | PLAYWRIGHT_E2E | 26. ROLE / IDOR CROSS-CHECK > Salesman | tests/Feature/ pending mapping | Cannot view another Salesman's customer | pending | PARTIAL | NOT EXECUTED | PENDING |
| CHK-717 | Cannot view another Salesman's order | PLAYWRIGHT_E2E | 26. ROLE / IDOR CROSS-CHECK > Salesman | tests/Feature/ pending mapping | Cannot view another Salesman's order | pending | PARTIAL | NOT EXECUTED | PENDING |
| CHK-718 | Cannot verify payments | PLAYWRIGHT_E2E | 26. ROLE / IDOR CROSS-CHECK > Salesman | tests/Feature/ pending mapping | Cannot verify payments | pending | PARTIAL | NOT EXECUTED | PENDING |
| CHK-719 | Cannot access Admin analytics | PLAYWRIGHT_E2E | 26. ROLE / IDOR CROSS-CHECK > Salesman | tests/Feature/ pending mapping | Cannot access Admin analytics | pending | PARTIAL | NOT EXECUTED | PENDING |
| CHK-720 | Cannot access audit logs | PLAYWRIGHT_E2E | 26. ROLE / IDOR CROSS-CHECK > Salesman | tests/Feature/ pending mapping | Cannot access audit logs | pending | PARTIAL | NOT EXECUTED | PENDING |
| CHK-721 | Cannot alter unauthorized prices | HTTP_API_SECURITY | 26. ROLE / IDOR CROSS-CHECK > Salesman | tests/Feature/ pending mapping | Cannot alter unauthorized prices | pending | PARTIAL | NOT EXECUTED | PENDING |
| CHK-722 | Cannot access another customer's evidence | PLAYWRIGHT_E2E | 26. ROLE / IDOR CROSS-CHECK > Salesman | tests/Feature/ pending mapping | Cannot access another customer's evidence | pending | PARTIAL | NOT EXECUTED | PENDING |
| CHK-723 | Cannot view unrelated delivery | PLAYWRIGHT_E2E | 26. ROLE / IDOR CROSS-CHECK > Delivery Partner | tests/Feature/ pending mapping | Cannot view unrelated delivery | pending | PARTIAL | NOT EXECUTED | PENDING |
| CHK-724 | Cannot alter financial data | PLAYWRIGHT_E2E | 26. ROLE / IDOR CROSS-CHECK > Delivery Partner | tests/Feature/ pending mapping | Cannot alter financial data | pending | PARTIAL | NOT EXECUTED | PENDING |
| CHK-725 | Cannot access accounting | PLAYWRIGHT_E2E | 26. ROLE / IDOR CROSS-CHECK > Delivery Partner | tests/Feature/ pending mapping | Cannot access accounting | pending | PARTIAL | NOT EXECUTED | PENDING |
| CHK-726 | Cannot access payment evidence | PLAYWRIGHT_E2E | 26. ROLE / IDOR CROSS-CHECK > Delivery Partner | tests/Feature/ pending mapping | Cannot access payment evidence | pending | PARTIAL | NOT EXECUTED | PENDING |
| CHK-727 | Cannot access another driver's POD/signature | PLAYWRIGHT_E2E | 26. ROLE / IDOR CROSS-CHECK > Delivery Partner | tests/Feature/ pending mapping | Cannot access another driver's POD/signature | pending | PARTIAL | NOT EXECUTED | PENDING |
| CHK-728 | Cannot approve Admin-controlled adjustment | PLAYWRIGHT_E2E | 26. ROLE / IDOR CROSS-CHECK > Warehouse | tests/Feature/ pending mapping | Cannot approve Admin-controlled adjustment | pending | PARTIAL | NOT EXECUTED | PENDING |
| CHK-729 | Cannot alter protected financial state | PLAYWRIGHT_E2E | 26. ROLE / IDOR CROSS-CHECK > Warehouse | tests/Feature/ pending mapping | Cannot alter protected financial state | pending | PARTIAL | NOT EXECUTED | PENDING |
| CHK-730 | Cannot access unauthorized customer financial data | HTTP_API_SECURITY | 26. ROLE / IDOR CROSS-CHECK > Warehouse | tests/Feature/ pending mapping | Cannot access unauthorized customer financial data | pending | PARTIAL | NOT EXECUTED | PENDING |
| CHK-731 | Correct financial access | PLAYWRIGHT_E2E | 26. ROLE / IDOR CROSS-CHECK > Accountant | tests/Feature/ pending mapping | Correct financial access | pending | PARTIAL | NOT EXECUTED | PENDING |
| CHK-732 | No unauthorized operational mutations | HTTP_API_SECURITY | 26. ROLE / IDOR CROSS-CHECK > Accountant | tests/Feature/ pending mapping | No unauthorized operational mutations | pending | PARTIAL | NOT EXECUTED | PENDING |
| CHK-733 | Correct hierarchy | PLAYWRIGHT_E2E | 26. ROLE / IDOR CROSS-CHECK > Admin / Super Admin | tests/Feature/ pending mapping | Correct hierarchy | pending | PARTIAL | NOT EXECUTED | PENDING |
| CHK-734 | No accidental overrestriction | PLAYWRIGHT_E2E | 26. ROLE / IDOR CROSS-CHECK > Admin / Super Admin | tests/Feature/ pending mapping | No accidental overrestriction | pending | PARTIAL | NOT EXECUTED | PENDING |
| CHK-735 | No accidental privilege crossover | PLAYWRIGHT_E2E | 26. ROLE / IDOR CROSS-CHECK > Admin / Super Admin | tests/Feature/ pending mapping | No accidental privilege crossover | pending | PARTIAL | NOT EXECUTED | PENDING |
| CHK-736 | Change customer ID | PLAYWRIGHT_E2E | 26. ROLE / IDOR CROSS-CHECK > URL tampering | tests/Feature/ pending mapping | Change customer ID | pending | PARTIAL | NOT EXECUTED | PENDING |
| CHK-737 | Change order ID | PLAYWRIGHT_E2E | 26. ROLE / IDOR CROSS-CHECK > URL tampering | tests/Feature/ pending mapping | Change order ID | pending | PARTIAL | NOT EXECUTED | PENDING |
| CHK-738 | Change payment ID | PLAYWRIGHT_E2E | 26. ROLE / IDOR CROSS-CHECK > URL tampering | tests/Feature/ pending mapping | Change payment ID | pending | PARTIAL | NOT EXECUTED | PENDING |
| CHK-739 | Change invoice ID | PLAYWRIGHT_E2E | 26. ROLE / IDOR CROSS-CHECK > URL tampering | tests/Feature/ pending mapping | Change invoice ID | pending | PARTIAL | NOT EXECUTED | PENDING |
| CHK-740 | Change delivery ID | PLAYWRIGHT_E2E | 26. ROLE / IDOR CROSS-CHECK > URL tampering | tests/Feature/ pending mapping | Change delivery ID | pending | PARTIAL | NOT EXECUTED | PENDING |
| CHK-741 | Change return ID | PLAYWRIGHT_E2E | 26. ROLE / IDOR CROSS-CHECK > URL tampering | tests/Feature/ pending mapping | Change return ID | pending | PARTIAL | NOT EXECUTED | PENDING |
| CHK-742 | Change adjustment ID | PLAYWRIGHT_E2E | 26. ROLE / IDOR CROSS-CHECK > URL tampering | tests/Feature/ pending mapping | Change adjustment ID | pending | PARTIAL | NOT EXECUTED | PENDING |
| CHK-743 | Change object/document reference | PLAYWRIGHT_E2E | 26. ROLE / IDOR CROSS-CHECK > URL tampering | tests/Feature/ pending mapping | Change object/document reference | pending | PARTIAL | NOT EXECUTED | PENDING |
| CHK-744 | Attempt predictable S3 key | PLAYWRIGHT_E2E | 26. ROLE / IDOR CROSS-CHECK > URL tampering | tests/Feature/ pending mapping | Attempt predictable S3 key | pending | PARTIAL | NOT EXECUTED | PENDING |
| CHK-745 | Attempt direct download route | PLAYWRIGHT_E2E | 26. ROLE / IDOR CROSS-CHECK > URL tampering | tests/Feature/ pending mapping | Attempt direct download route | pending | PARTIAL | NOT EXECUTED | PENDING |
| CHK-746 | Attempt preview route | PLAYWRIGHT_E2E | 26. ROLE / IDOR CROSS-CHECK > URL tampering | tests/Feature/ pending mapping | Attempt preview route | pending | PARTIAL | NOT EXECUTED | PENDING |
| CHK-747 | Login throttling | PLAYWRIGHT_E2E | 27. RATE LIMITING / ABUSE | tests/Feature/ pending mapping | Login throttling | pending | NOT_TESTED | NOT EXECUTED | PENDING |
| CHK-748 | MFA throttling | PLAYWRIGHT_E2E | 27. RATE LIMITING / ABUSE | tests/Feature/ pending mapping | MFA throttling | pending | NOT_TESTED | NOT EXECUTED | PENDING |
| CHK-749 | Password reset throttling | PLAYWRIGHT_E2E | 27. RATE LIMITING / ABUSE | tests/Feature/ pending mapping | Password reset throttling | pending | NOT_TESTED | NOT EXECUTED | PENDING |
| CHK-750 | Payment mutation throttling | PLAYWRIGHT_E2E | 27. RATE LIMITING / ABUSE | tests/Feature/ pending mapping | Payment mutation throttling | pending | NOT_TESTED | NOT EXECUTED | PENDING |
| CHK-751 | Evidence upload throttling | PLAYWRIGHT_E2E | 27. RATE LIMITING / ABUSE | tests/Feature/ pending mapping | Evidence upload throttling | pending | NOT_TESTED | NOT EXECUTED | PENDING |
| CHK-752 | Order mutation throttling | PLAYWRIGHT_E2E | 27. RATE LIMITING / ABUSE | tests/Feature/ pending mapping | Order mutation throttling | pending | NOT_TESTED | NOT EXECUTED | PENDING |
| CHK-753 | Inventory mutation throttling | PLAYWRIGHT_E2E | 27. RATE LIMITING / ABUSE | tests/Feature/ pending mapping | Inventory mutation throttling | pending | NOT_TESTED | NOT EXECUTED | PENDING |
| CHK-754 | Delivery completion throttling | PLAYWRIGHT_E2E | 27. RATE LIMITING / ABUSE | tests/Feature/ pending mapping | Delivery completion throttling | pending | NOT_TESTED | NOT EXECUTED | PENDING |
| CHK-755 | Invoice generation throttling | PLAYWRIGHT_E2E | 27. RATE LIMITING / ABUSE | tests/Feature/ pending mapping | Invoice generation throttling | pending | NOT_TESTED | NOT EXECUTED | PENDING |
| CHK-756 | Export throttling | PLAYWRIGHT_E2E | 27. RATE LIMITING / ABUSE | tests/Feature/ pending mapping | Export throttling | pending | NOT_TESTED | NOT EXECUTED | PENDING |
| CHK-757 | Correct 429 | PLAYWRIGHT_E2E | 27. RATE LIMITING / ABUSE | tests/Feature/ pending mapping | Correct 429 | pending | NOT_TESTED | NOT EXECUTED | PENDING |
| CHK-758 | Retry information where appropriate | PLAYWRIGHT_E2E | 27. RATE LIMITING / ABUSE | tests/Feature/ pending mapping | Retry information where appropriate | pending | NOT_TESTED | NOT EXECUTED | PENDING |
| CHK-759 | Normal UX not incorrectly throttled | PLAYWRIGHT_E2E | 27. RATE LIMITING / ABUSE | tests/Feature/ pending mapping | Normal UX not incorrectly throttled | pending | NOT_TESTED | NOT EXECUTED | PENDING |
| CHK-760 | Authenticated identity used appropriately | PLAYWRIGHT_E2E | 27. RATE LIMITING / ABUSE | tests/Feature/ pending mapping | Authenticated identity used appropriately | pending | NOT_TESTED | NOT EXECUTED | PENDING |
| CHK-761 | Shared-IP users not incorrectly blocked where avoidable | PLAYWRIGHT_E2E | 27. RATE LIMITING / ABUSE | tests/Feature/ pending mapping | Shared-IP users not incorrectly blocked where avoidable | pending | NOT_TESTED | NOT EXECUTED | PENDING |
| CHK-762 | Authorization before upload | PLAYWRIGHT_E2E | 28. FILE UPLOAD SECURITY | tests/Feature/ pending mapping | Authorization before upload | pending | NOT_TESTED | NOT EXECUTED | PENDING |
| CHK-763 | Resource-scope check | PLAYWRIGHT_E2E | 28. FILE UPLOAD SECURITY | tests/Feature/ pending mapping | Resource-scope check | pending | NOT_TESTED | NOT EXECUTED | PENDING |
| CHK-764 | File size limit | PLAYWRIGHT_E2E | 28. FILE UPLOAD SECURITY | tests/Feature/ pending mapping | File size limit | pending | NOT_TESTED | NOT EXECUTED | PENDING |
| CHK-765 | Actual MIME validation | PLAYWRIGHT_E2E | 28. FILE UPLOAD SECURITY | tests/Feature/ pending mapping | Actual MIME validation | pending | NOT_TESTED | NOT EXECUTED | PENDING |
| CHK-766 | Extension validation | PLAYWRIGHT_E2E | 28. FILE UPLOAD SECURITY | tests/Feature/ pending mapping | Extension validation | pending | NOT_TESTED | NOT EXECUTED | PENDING |
| CHK-767 | Magic-byte/content validation | PLAYWRIGHT_E2E | 28. FILE UPLOAD SECURITY | tests/Feature/ pending mapping | Magic-byte/content validation | pending | NOT_TESTED | NOT EXECUTED | PENDING |
| CHK-768 | Image parser validation | PLAYWRIGHT_E2E | 28. FILE UPLOAD SECURITY | tests/Feature/ pending mapping | Image parser validation | pending | NOT_TESTED | NOT EXECUTED | PENDING |
| CHK-769 | Dimension validation where appropriate | PLAYWRIGHT_E2E | 28. FILE UPLOAD SECURITY | tests/Feature/ pending mapping | Dimension validation where appropriate | pending | NOT_TESTED | NOT EXECUTED | PENDING |
| CHK-770 | Dangerous SVG/XML blocked where required | PLAYWRIGHT_E2E | 28. FILE UPLOAD SECURITY | tests/Feature/ pending mapping | Dangerous SVG/XML blocked where required | pending | NOT_TESTED | NOT EXECUTED | PENDING |
| CHK-771 | Malformed PDF behavior | PLAYWRIGHT_E2E | 28. FILE UPLOAD SECURITY | tests/Feature/ pending mapping | Malformed PDF behavior | pending | NOT_TESTED | NOT EXECUTED | PENDING |
| CHK-772 | Filename sanitization | PLAYWRIGHT_E2E | 28. FILE UPLOAD SECURITY | tests/Feature/ pending mapping | Filename sanitization | pending | NOT_TESTED | NOT EXECUTED | PENDING |
| CHK-773 | Path traversal rejected | PLAYWRIGHT_E2E | 28. FILE UPLOAD SECURITY | tests/Feature/ pending mapping | Path traversal rejected | pending | NOT_TESTED | NOT EXECUTED | PENDING |
| CHK-774 | UUID object key | PLAYWRIGHT_E2E | 28. FILE UPLOAD SECURITY | tests/Feature/ pending mapping | UUID object key | pending | NOT_TESTED | NOT EXECUTED | PENDING |
| CHK-775 | Private storage | PLAYWRIGHT_E2E | 28. FILE UPLOAD SECURITY | tests/Feature/ pending mapping | Private storage | pending | NOT_TESTED | NOT EXECUTED | PENDING |
| CHK-776 | Signed access | PLAYWRIGHT_E2E | 28. FILE UPLOAD SECURITY | tests/Feature/ pending mapping | Signed access | pending | NOT_TESTED | NOT EXECUTED | PENDING |
| CHK-777 | No permanent public URL | PLAYWRIGHT_E2E | 28. FILE UPLOAD SECURITY | tests/Feature/ pending mapping | No permanent public URL | pending | NOT_TESTED | NOT EXECUTED | PENDING |
| CHK-778 | Failure cleanup | PLAYWRIGHT_E2E | 28. FILE UPLOAD SECURITY | tests/Feature/ pending mapping | Failure cleanup | pending | NOT_TESTED | NOT EXECUTED | PENDING |
| CHK-779 | Orphan handling | PLAYWRIGHT_E2E | 28. FILE UPLOAD SECURITY | tests/Feature/ pending mapping | Orphan handling | pending | NOT_TESTED | NOT EXECUTED | PENDING |
| CHK-780 | Duplicate upload behavior | PLAYWRIGHT_E2E | 28. FILE UPLOAD SECURITY | tests/Feature/ pending mapping | Duplicate upload behavior | pending | NOT_TESTED | NOT EXECUTED | PENDING |
| CHK-781 | 400 | PLAYWRIGHT_E2E | 29. ERROR HANDLING / INFORMATION LEAKAGE | tests/Feature/ pending mapping | 400 | pending | PARTIAL | NOT EXECUTED | PENDING |
| CHK-782 | 401 | HTTP_API_SECURITY | 29. ERROR HANDLING / INFORMATION LEAKAGE | tests/Feature/ pending mapping | 401 | pending | PARTIAL | NOT EXECUTED | PENDING |
| CHK-783 | 403 | HTTP_API_SECURITY | Production error views & diagnostics | security_hardening_verification.spec.ts | 403 | Error.tsx | PASS | 2026-09-12 | AUDIT-RUN-20260912-154532 |
| CHK-784 | 404 | PLAYWRIGHT_E2E | Production error views & diagnostics | security_hardening_verification.spec.ts | 404 | Error.tsx | PASS | 2026-09-12 | AUDIT-RUN-20260912-154532 |
| CHK-785 | 409 | PLAYWRIGHT_E2E | 29. ERROR HANDLING / INFORMATION LEAKAGE | tests/Feature/ pending mapping | 409 | pending | PARTIAL | NOT EXECUTED | PENDING |
| CHK-786 | 422 | PLAYWRIGHT_E2E | 29. ERROR HANDLING / INFORMATION LEAKAGE | tests/Feature/ pending mapping | 422 | pending | PARTIAL | NOT EXECUTED | PENDING |
| CHK-787 | 429 | PLAYWRIGHT_E2E | 29. ERROR HANDLING / INFORMATION LEAKAGE | tests/Feature/ pending mapping | 429 | pending | PARTIAL | NOT EXECUTED | PENDING |
| CHK-788 | 500 | PLAYWRIGHT_E2E | Production error views & diagnostics | security_hardening_verification.spec.ts | 500 | Error.tsx | PASS | 2026-09-12 | AUDIT-RUN-20260912-154532 |
| CHK-789 | S3 failure | PLAYWRIGHT_E2E | 29. ERROR HANDLING / INFORMATION LEAKAGE | tests/Feature/ pending mapping | S3 failure | pending | PARTIAL | NOT EXECUTED | PENDING |
| CHK-790 | validation failure | PLAYWRIGHT_E2E | 29. ERROR HANDLING / INFORMATION LEAKAGE | tests/Feature/ pending mapping | validation failure | pending | PARTIAL | NOT EXECUTED | PENDING |
| CHK-791 | concurrency conflict | PLAYWRIGHT_E2E | 29. ERROR HANDLING / INFORMATION LEAKAGE | tests/Feature/ pending mapping | concurrency conflict | pending | PARTIAL | NOT EXECUTED | PENDING |
| CHK-792 | stack traces | PLAYWRIGHT_E2E | 29. ERROR HANDLING / INFORMATION LEAKAGE | tests/Feature/ pending mapping | stack traces | pending | PARTIAL | NOT EXECUTED | PENDING |
| CHK-793 | SQL | PLAYWRIGHT_E2E | 29. ERROR HANDLING / INFORMATION LEAKAGE | tests/Feature/ pending mapping | SQL | pending | PARTIAL | NOT EXECUTED | PENDING |
| CHK-794 | SQLSTATE | PLAYWRIGHT_E2E | 29. ERROR HANDLING / INFORMATION LEAKAGE | tests/Feature/ pending mapping | SQLSTATE | pending | PARTIAL | NOT EXECUTED | PENDING |
| CHK-795 | filesystem paths | PLAYWRIGHT_E2E | 29. ERROR HANDLING / INFORMATION LEAKAGE | tests/Feature/ pending mapping | filesystem paths | pending | PARTIAL | NOT EXECUTED | PENDING |
| CHK-796 | AWS credentials | PLAYWRIGHT_E2E | 29. ERROR HANDLING / INFORMATION LEAKAGE | tests/Feature/ pending mapping | AWS credentials | pending | PARTIAL | NOT EXECUTED | PENDING |
| CHK-797 | bucket internals where sensitive | PLAYWRIGHT_E2E | 29. ERROR HANDLING / INFORMATION LEAKAGE | tests/Feature/ pending mapping | bucket internals where sensitive | pending | PARTIAL | NOT EXECUTED | PENDING |
| CHK-798 | class names where unnecessary | PLAYWRIGHT_E2E | 29. ERROR HANDLING / INFORMATION LEAKAGE | tests/Feature/ pending mapping | class names where unnecessary | pending | PARTIAL | NOT EXECUTED | PENDING |
| CHK-799 | source locations | PLAYWRIGHT_E2E | 29. ERROR HANDLING / INFORMATION LEAKAGE | tests/Feature/ pending mapping | source locations | pending | PARTIAL | NOT EXECUTED | PENDING |
| CHK-800 | environment variables | PLAYWRIGHT_E2E | 29. ERROR HANDLING / INFORMATION LEAKAGE | tests/Feature/ pending mapping | environment variables | pending | PARTIAL | NOT EXECUTED | PENDING |
| CHK-801 | internal ticket IDs | PLAYWRIGHT_E2E | 29. ERROR HANDLING / INFORMATION LEAKAGE | tests/Feature/ pending mapping | internal ticket IDs | pending | PARTIAL | NOT EXECUTED | PENDING |
| CHK-802 | session tokens | HTTP_API_SECURITY | 29. ERROR HANDLING / INFORMATION LEAKAGE | tests/Feature/ pending mapping | session tokens | pending | PARTIAL | NOT EXECUTED | PENDING |
| CHK-803 | signed URLs | PLAYWRIGHT_E2E | 29. ERROR HANDLING / INFORMATION LEAKAGE | tests/Feature/ pending mapping | signed URLs | pending | PARTIAL | NOT EXECUTED | PENDING |
| CHK-804 | JS exceptions | PLAYWRIGHT_E2E | 30. CONSOLE / NETWORK / RUNTIME | tests/Feature/ pending mapping | JS exceptions | pending | PARTIAL | NOT EXECUTED | PENDING |
| CHK-805 | React errors | PLAYWRIGHT_E2E | 30. CONSOLE / NETWORK / RUNTIME | tests/Feature/ pending mapping | React errors | pending | PARTIAL | NOT EXECUTED | PENDING |
| CHK-806 | console errors | PLAYWRIGHT_E2E | DiagnosticsCollector monitoring | All specs via DiagnosticsCollector | console errors | Diagnostics JSON logs | PASS | 2026-09-12 | AUDIT-RUN-20260912-154532 |
| CHK-807 | console warnings | PLAYWRIGHT_E2E | 30. CONSOLE / NETWORK / RUNTIME | tests/Feature/ pending mapping | console warnings | pending | PARTIAL | NOT EXECUTED | PENDING |
| CHK-808 | 400 | PLAYWRIGHT_E2E | 30. CONSOLE / NETWORK / RUNTIME | tests/Feature/ pending mapping | 400 | pending | PARTIAL | NOT EXECUTED | PENDING |
| CHK-809 | 401 | HTTP_API_SECURITY | 30. CONSOLE / NETWORK / RUNTIME | tests/Feature/ pending mapping | 401 | pending | PARTIAL | NOT EXECUTED | PENDING |
| CHK-810 | 403 | HTTP_API_SECURITY | Production error views & diagnostics | security_hardening_verification.spec.ts | 403 | Error.tsx | PASS | 2026-09-12 | AUDIT-RUN-20260912-154532 |
| CHK-811 | 404 | PLAYWRIGHT_E2E | Production error views & diagnostics | security_hardening_verification.spec.ts | 404 | Error.tsx | PASS | 2026-09-12 | AUDIT-RUN-20260912-154532 |
| CHK-812 | 409 | PLAYWRIGHT_E2E | 30. CONSOLE / NETWORK / RUNTIME | tests/Feature/ pending mapping | 409 | pending | PARTIAL | NOT EXECUTED | PENDING |
| CHK-813 | 422 | PLAYWRIGHT_E2E | 30. CONSOLE / NETWORK / RUNTIME | tests/Feature/ pending mapping | 422 | pending | PARTIAL | NOT EXECUTED | PENDING |
| CHK-814 | 429 | PLAYWRIGHT_E2E | 30. CONSOLE / NETWORK / RUNTIME | tests/Feature/ pending mapping | 429 | pending | PARTIAL | NOT EXECUTED | PENDING |
| CHK-815 | 500 | PLAYWRIGHT_E2E | Production error views & diagnostics | security_hardening_verification.spec.ts | 500 | Error.tsx | PASS | 2026-09-12 | AUDIT-RUN-20260912-154532 |
| CHK-816 | failed Inertia requests | PLAYWRIGHT_E2E | 30. CONSOLE / NETWORK / RUNTIME | tests/Feature/ pending mapping | failed Inertia requests | pending | PARTIAL | NOT EXECUTED | PENDING |
| CHK-817 | failed assets | PLAYWRIGHT_E2E | 30. CONSOLE / NETWORK / RUNTIME | tests/Feature/ pending mapping | failed assets | pending | PARTIAL | NOT EXECUTED | PENDING |
| CHK-818 | Vite/HMR issues | PLAYWRIGHT_E2E | 30. CONSOLE / NETWORK / RUNTIME | tests/Feature/ pending mapping | Vite/HMR issues | pending | PARTIAL | NOT EXECUTED | PENDING |
| CHK-819 | CORS | PLAYWRIGHT_E2E | 30. CONSOLE / NETWORK / RUNTIME | tests/Feature/ pending mapping | CORS | pending | PARTIAL | NOT EXECUTED | PENDING |
| CHK-820 | ERR_FAILED | PLAYWRIGHT_E2E | 30. CONSOLE / NETWORK / RUNTIME | tests/Feature/ pending mapping | ERR_FAILED | pending | PARTIAL | NOT EXECUTED | PENDING |
| CHK-821 | ERR_EMPTY_RESPONSE | PLAYWRIGHT_E2E | 30. CONSOLE / NETWORK / RUNTIME | tests/Feature/ pending mapping | ERR_EMPTY_RESPONSE | pending | PARTIAL | NOT EXECUTED | PENDING |
| CHK-822 | unexpected redirects | PLAYWRIGHT_E2E | 30. CONSOLE / NETWORK / RUNTIME | tests/Feature/ pending mapping | unexpected redirects | pending | PARTIAL | NOT EXECUTED | PENDING |
| CHK-823 | blank rendering | PLAYWRIGHT_E2E | 30. CONSOLE / NETWORK / RUNTIME | tests/Feature/ pending mapping | blank rendering | pending | PARTIAL | NOT EXECUTED | PENDING |
| CHK-824 | partial rendering | PLAYWRIGHT_E2E | 30. CONSOLE / NETWORK / RUNTIME | tests/Feature/ pending mapping | partial rendering | pending | PARTIAL | NOT EXECUTED | PENDING |
| CHK-825 | broken images | PLAYWRIGHT_E2E | 30. CONSOLE / NETWORK / RUNTIME | tests/Feature/ pending mapping | broken images | pending | PARTIAL | NOT EXECUTED | PENDING |
| CHK-826 | failed S3 signed URLs | PLAYWRIGHT_E2E | 30. CONSOLE / NETWORK / RUNTIME | tests/Feature/ pending mapping | failed S3 signed URLs | pending | PARTIAL | NOT EXECUTED | PENDING |
| CHK-827 | Genuine defect | PLAYWRIGHT_E2E | 30. CONSOLE / NETWORK / RUNTIME | tests/Feature/ pending mapping | Genuine defect | pending | PARTIAL | NOT EXECUTED | PENDING |
| CHK-828 | Expected validation | PLAYWRIGHT_E2E | 30. CONSOLE / NETWORK / RUNTIME | tests/Feature/ pending mapping | Expected validation | pending | PARTIAL | NOT EXECUTED | PENDING |
| CHK-829 | Expected authorization | PLAYWRIGHT_E2E | 30. CONSOLE / NETWORK / RUNTIME | tests/Feature/ pending mapping | Expected authorization | pending | PARTIAL | NOT EXECUTED | PENDING |
| CHK-830 | Expected concurrency response | PLAYWRIGHT_E2E | 30. CONSOLE / NETWORK / RUNTIME | tests/Feature/ pending mapping | Expected concurrency response | pending | PARTIAL | NOT EXECUTED | PENDING |
| CHK-831 | Framework/dev noise | PLAYWRIGHT_E2E | 30. CONSOLE / NETWORK / RUNTIME | tests/Feature/ pending mapping | Framework/dev noise | pending | PARTIAL | NOT EXECUTED | PENDING |
| CHK-832 | Browser/environment issue | PLAYWRIGHT_E2E | 30. CONSOLE / NETWORK / RUNTIME | tests/Feature/ pending mapping | Browser/environment issue | pending | PARTIAL | NOT EXECUTED | PENDING |
| CHK-833 | Duplicate symptom | PLAYWRIGHT_E2E | 30. CONSOLE / NETWORK / RUNTIME | tests/Feature/ pending mapping | Duplicate symptom | pending | PARTIAL | NOT EXECUTED | PENDING |
| CHK-834 | Salesman dashboard | PLAYWRIGHT_E2E | 14.1 Re-verifying recent fixes across Salesman, AR, and Adjustments | 13_regression_e2e_financial.spec.ts | Salesman dashboard | 13_regression_salesman_dashboard_clean.png, 13_regression_adjustments_queue_healthy.png | PASS | 2026-09-12 | AUDIT-RUN-20260912-154532 |
| CHK-835 | Salesman payment-in-order | PLAYWRIGHT_E2E | 14.1 Re-verifying recent fixes across Salesman, AR, and Adjustments | 13_regression_e2e_financial.spec.ts | Salesman payment-in-order | 13_regression_salesman_dashboard_clean.png, 13_regression_adjustments_queue_healthy.png | PASS | 2026-09-12 | AUDIT-RUN-20260912-154532 |
| CHK-836 | Payment verification | PLAYWRIGHT_E2E | 14.1 Re-verifying recent fixes across Salesman, AR, and Adjustments | 13_regression_e2e_financial.spec.ts | Payment verification | 13_regression_salesman_dashboard_clean.png, 13_regression_adjustments_queue_healthy.png | PASS | 2026-09-12 | AUDIT-RUN-20260912-154532 |
| CHK-837 | Pending-payment outstanding | PLAYWRIGHT_E2E | 14.1 Re-verifying recent fixes across Salesman, AR, and Adjustments | 13_regression_e2e_financial.spec.ts | Pending-payment outstanding | 13_regression_salesman_dashboard_clean.png, 13_regression_adjustments_queue_healthy.png | PASS | 2026-09-12 | AUDIT-RUN-20260912-154532 |
| CHK-838 | AR derived ledger | PLAYWRIGHT_E2E | 14.1 Re-verifying recent fixes across Salesman, AR, and Adjustments | 13_regression_e2e_financial.spec.ts | AR derived ledger | 13_regression_salesman_dashboard_clean.png, 13_regression_adjustments_queue_healthy.png | PASS | 2026-09-12 | AUDIT-RUN-20260912-154532 |
| CHK-839 | AR PostgreSQL transaction-boundary repair | PLAYWRIGHT_E2E | 14.1 Re-verifying recent fixes across Salesman, AR, and Adjustments | 13_regression_e2e_financial.spec.ts | AR PostgreSQL transaction-boundary repair | 13_regression_salesman_dashboard_clean.png, 13_regression_adjustments_queue_healthy.png | PASS | 2026-09-12 | AUDIT-RUN-20260912-154532 |
| CHK-840 | Adjustment 500 repair | PLAYWRIGHT_E2E | 14.1 Re-verifying recent fixes across Salesman, AR, and Adjustments | 13_regression_e2e_financial.spec.ts | Adjustment 500 repair | 13_regression_salesman_dashboard_clean.png, 13_regression_adjustments_queue_healthy.png | PASS | 2026-09-12 | AUDIT-RUN-20260912-154532 |
| CHK-841 | Adjustment 409 repair | PLAYWRIGHT_E2E | 14.1 Re-verifying recent fixes across Salesman, AR, and Adjustments | 13_regression_e2e_financial.spec.ts | Adjustment 409 repair | 13_regression_salesman_dashboard_clean.png, 13_regression_adjustments_queue_healthy.png | PASS | 2026-09-12 | AUDIT-RUN-20260912-154532 |
| CHK-842 | Dashboard repair | PLAYWRIGHT_E2E | 14.1 Re-verifying recent fixes across Salesman, AR, and Adjustments | 13_regression_e2e_financial.spec.ts | Dashboard repair | 13_regression_salesman_dashboard_clean.png, 13_regression_adjustments_queue_healthy.png | PASS | 2026-09-12 | AUDIT-RUN-20260912-154532 |
| CHK-843 | Vite/CORS repair | PLAYWRIGHT_E2E | 14.1 Re-verifying recent fixes across Salesman, AR, and Adjustments | 13_regression_e2e_financial.spec.ts | Vite/CORS repair | 13_regression_salesman_dashboard_clean.png, 13_regression_adjustments_queue_healthy.png | PASS | 2026-09-12 | AUDIT-RUN-20260912-154532 |
| CHK-844 | Internal Phase/Epic cleanup | PLAYWRIGHT_E2E | 14.1 Re-verifying recent fixes across Salesman, AR, and Adjustments | 13_regression_e2e_financial.spec.ts | Internal Phase/Epic cleanup | 13_regression_salesman_dashboard_clean.png, 13_regression_adjustments_queue_healthy.png | PASS | 2026-09-12 | AUDIT-RUN-20260912-154532 |
| CHK-845 | Security hardening | PLAYWRIGHT_E2E | 14.1 Re-verifying recent fixes across Salesman, AR, and Adjustments | 13_regression_e2e_financial.spec.ts | Security hardening | 13_regression_salesman_dashboard_clean.png, 13_regression_adjustments_queue_healthy.png | PASS | 2026-09-12 | AUDIT-RUN-20260912-154532 |
| CHK-846 | S3 application integration | PLAYWRIGHT_E2E | 14.1 Re-verifying recent fixes across Salesman, AR, and Adjustments | 13_regression_e2e_financial.spec.ts | S3 application integration | 13_regression_salesman_dashboard_clean.png, 13_regression_adjustments_queue_healthy.png | PASS | 2026-09-12 | AUDIT-RUN-20260912-154532 |
| CHK-847 | Product image storage | PLAYWRIGHT_E2E | 14.1 Re-verifying recent fixes across Salesman, AR, and Adjustments | 13_regression_e2e_financial.spec.ts | Product image storage | 13_regression_salesman_dashboard_clean.png, 13_regression_adjustments_queue_healthy.png | PASS | 2026-09-12 | AUDIT-RUN-20260912-154532 |
| CHK-848 | Payment evidence private preview | PLAYWRIGHT_E2E | 14.1 Re-verifying recent fixes across Salesman, AR, and Adjustments | 13_regression_e2e_financial.spec.ts | Payment evidence private preview | 13_regression_salesman_dashboard_clean.png, 13_regression_adjustments_queue_healthy.png | PASS | 2026-09-12 | AUDIT-RUN-20260912-154532 |
| CHK-849 | Delivery signature/POD storage | PLAYWRIGHT_E2E | 14.1 Re-verifying recent fixes across Salesman, AR, and Adjustments | 13_regression_e2e_financial.spec.ts | Delivery signature/POD storage | 13_regression_salesman_dashboard_clean.png, 13_regression_adjustments_queue_healthy.png | PASS | 2026-09-12 | AUDIT-RUN-20260912-154532 |
| CHK-850 | Interactive browser infrastructure | PLAYWRIGHT_E2E | 14.1 Re-verifying recent fixes across Salesman, AR, and Adjustments | 13_regression_e2e_financial.spec.ts | Interactive browser infrastructure | 13_regression_salesman_dashboard_clean.png, 13_regression_adjustments_queue_healthy.png | PASS | 2026-09-12 | AUDIT-RUN-20260912-154532 |
| CHK-851 | Chrome DevTools MCP coexistence | PLAYWRIGHT_E2E | 14.1 Re-verifying recent fixes across Salesman, AR, and Adjustments | 13_regression_e2e_financial.spec.ts | Chrome DevTools MCP coexistence | 13_regression_salesman_dashboard_clean.png, 13_regression_adjustments_queue_healthy.png | PASS | 2026-09-12 | AUDIT-RUN-20260912-154532 |
| CHK-852 | Related commit identified | PLAYWRIGHT_E2E | 14.1 Re-verifying recent fixes across Salesman, AR, and Adjustments | 13_regression_e2e_financial.spec.ts | Related commit identified | 13_regression_salesman_dashboard_clean.png, 13_regression_adjustments_queue_healthy.png | PASS | 2026-09-12 | AUDIT-RUN-20260912-154532 |
| CHK-853 | Normal workflow still works | PLAYWRIGHT_E2E | 14.1 Re-verifying recent fixes across Salesman, AR, and Adjustments | 13_regression_e2e_financial.spec.ts | Normal workflow still works | 13_regression_salesman_dashboard_clean.png, 13_regression_adjustments_queue_healthy.png | PASS | 2026-09-12 | AUDIT-RUN-20260912-154532 |
| CHK-854 | Edge case still works | PLAYWRIGHT_E2E | 14.1 Re-verifying recent fixes across Salesman, AR, and Adjustments | 13_regression_e2e_financial.spec.ts | Edge case still works | 13_regression_salesman_dashboard_clean.png, 13_regression_adjustments_queue_healthy.png | PASS | 2026-09-12 | AUDIT-RUN-20260912-154532 |
| CHK-855 | Authorization still works | PLAYWRIGHT_E2E | 14.1 Re-verifying recent fixes across Salesman, AR, and Adjustments | 13_regression_e2e_financial.spec.ts | Authorization still works | 13_regression_salesman_dashboard_clean.png, 13_regression_adjustments_queue_healthy.png | PASS | 2026-09-12 | AUDIT-RUN-20260912-154532 |
| CHK-856 | Responsive behavior still works | PLAYWRIGHT_E2E | 14.1 Re-verifying recent fixes across Salesman, AR, and Adjustments | 13_regression_e2e_financial.spec.ts | Responsive behavior still works | 13_regression_salesman_dashboard_clean.png, 13_regression_adjustments_queue_healthy.png | PASS | 2026-09-12 | AUDIT-RUN-20260912-154532 |
| CHK-857 | No regression introduced | PLAYWRIGHT_E2E | 14.1 Re-verifying recent fixes across Salesman, AR, and Adjustments | 13_regression_e2e_financial.spec.ts | No regression introduced | 13_regression_salesman_dashboard_clean.png, 13_regression_adjustments_queue_healthy.png | PASS | 2026-09-12 | AUDIT-RUN-20260912-154532 |
| CHK-858 | Customer selected/created | COMPOSITE_FINANCIAL_E2E | 14.1 Cross-module financial verification | 13_regression_e2e_financial.spec.ts | Customer selected/created | AR, GL, and Salesman balance checks | PARTIAL | NOT EXECUTED | PENDING |
| CHK-859 | Salesman assignment confirmed | COMPOSITE_FINANCIAL_E2E | 14.1 Cross-module financial verification | 13_regression_e2e_financial.spec.ts | Salesman assignment confirmed | AR, GL, and Salesman balance checks | PARTIAL | NOT EXECUTED | PENDING |
| CHK-860 | Salesman creates order | COMPOSITE_FINANCIAL_E2E | 14.1 Cross-module financial verification | 13_regression_e2e_financial.spec.ts | Salesman creates order | AR, GL, and Salesman balance checks | PARTIAL | NOT EXECUTED | PENDING |
| CHK-861 | Multiple products | COMPOSITE_FINANCIAL_E2E | 14.1 Cross-module financial verification | 13_regression_e2e_financial.spec.ts | Multiple products | AR, GL, and Salesman balance checks | PARTIAL | NOT EXECUTED | PENDING |
| CHK-862 | Quantities | COMPOSITE_FINANCIAL_E2E | 14.1 Cross-module financial verification | 13_regression_e2e_financial.spec.ts | Quantities | AR, GL, and Salesman balance checks | PARTIAL | NOT EXECUTED | PENDING |
| CHK-863 | Price | COMPOSITE_FINANCIAL_E2E | 14.1 Cross-module financial verification | 13_regression_e2e_financial.spec.ts | Price | AR, GL, and Salesman balance checks | PARTIAL | NOT EXECUTED | PENDING |
| CHK-864 | Tax | COMPOSITE_FINANCIAL_E2E | 14.1 Cross-module financial verification | 13_regression_e2e_financial.spec.ts | Tax | AR, GL, and Salesman balance checks | PARTIAL | NOT EXECUTED | PENDING |
| CHK-865 | Total | COMPOSITE_FINANCIAL_E2E | 14.1 Cross-module financial verification | 13_regression_e2e_financial.spec.ts | Total | AR, GL, and Salesman balance checks | PARTIAL | NOT EXECUTED | PENDING |
| CHK-866 | Order submitted | COMPOSITE_FINANCIAL_E2E | 14.1 Cross-module financial verification | 13_regression_e2e_financial.spec.ts | Order submitted | AR, GL, and Salesman balance checks | PARTIAL | NOT EXECUTED | PENDING |
| CHK-867 | Partial payment | COMPOSITE_FINANCIAL_E2E | 14.1 Cross-module financial verification | 13_regression_e2e_financial.spec.ts | Partial payment | AR, GL, and Salesman balance checks | PARTIAL | NOT EXECUTED | PENDING |
| CHK-868 | Pending state | COMPOSITE_FINANCIAL_E2E | 14.1 Cross-module financial verification | 13_regression_e2e_financial.spec.ts | Pending state | AR, GL, and Salesman balance checks | PARTIAL | NOT EXECUTED | PENDING |
| CHK-869 | Evidence where required | COMPOSITE_FINANCIAL_E2E | 14.1 Cross-module financial verification | 13_regression_e2e_financial.spec.ts | Evidence where required | AR, GL, and Salesman balance checks | PARTIAL | NOT EXECUTED | PENDING |
| CHK-870 | Operational outstanding | COMPOSITE_FINANCIAL_E2E | 14.1 Cross-module financial verification | 13_regression_e2e_financial.spec.ts | Operational outstanding | AR, GL, and Salesman balance checks | PARTIAL | NOT EXECUTED | PENDING |
| CHK-871 | Payment appears in verification | COMPOSITE_FINANCIAL_E2E | 14.1 Cross-module financial verification | 13_regression_e2e_financial.spec.ts | Payment appears in verification | AR, GL, and Salesman balance checks | PARTIAL | NOT EXECUTED | PENDING |
| CHK-872 | Evidence view works | COMPOSITE_FINANCIAL_E2E | 14.1 Cross-module financial verification | 13_regression_e2e_financial.spec.ts | Evidence view works | AR, GL, and Salesman balance checks | PARTIAL | NOT EXECUTED | PENDING |
| CHK-873 | Maker-checker enforced | COMPOSITE_FINANCIAL_E2E | 14.1 Cross-module financial verification | 13_regression_e2e_financial.spec.ts | Maker-checker enforced | AR, GL, and Salesman balance checks | PARTIAL | NOT EXECUTED | PENDING |
| CHK-874 | Payment verified | COMPOSITE_FINANCIAL_E2E | 14.1 Cross-module financial verification | 13_regression_e2e_financial.spec.ts | Payment verified | AR, GL, and Salesman balance checks | PARTIAL | NOT EXECUTED | PENDING |
| CHK-875 | Verified state | COMPOSITE_FINANCIAL_E2E | 14.1 Cross-module financial verification | 13_regression_e2e_financial.spec.ts | Verified state | AR, GL, and Salesman balance checks | PARTIAL | NOT EXECUTED | PENDING |
| CHK-876 | Outstanding recalculates | COMPOSITE_FINANCIAL_E2E | 14.1 Cross-module financial verification | 13_regression_e2e_financial.spec.ts | Outstanding recalculates | AR, GL, and Salesman balance checks | PARTIAL | NOT EXECUTED | PENDING |
| CHK-877 | AR recalculates | COMPOSITE_FINANCIAL_E2E | 14.1 Cross-module financial verification | 13_regression_e2e_financial.spec.ts | AR recalculates | AR, GL, and Salesman balance checks | PARTIAL | NOT EXECUTED | PENDING |
| CHK-878 | Statement updates | COMPOSITE_FINANCIAL_E2E | 14.1 Cross-module financial verification | 13_regression_e2e_financial.spec.ts | Statement updates | AR, GL, and Salesman balance checks | PARTIAL | NOT EXECUTED | PENDING |
| CHK-879 | Invoice/order financial state correct | COMPOSITE_FINANCIAL_E2E | 14.1 Cross-module financial verification | 13_regression_e2e_financial.spec.ts | Invoice/order financial state correct | AR, GL, and Salesman balance checks | PARTIAL | NOT EXECUTED | PENDING |
| CHK-880 | GL posting correct | COMPOSITE_FINANCIAL_E2E | 14.1 Cross-module financial verification | 13_regression_e2e_financial.spec.ts | GL posting correct | AR, GL, and Salesman balance checks | PARTIAL | NOT EXECUTED | PENDING |
| CHK-881 | Trial balance remains balanced | COMPOSITE_FINANCIAL_E2E | 14.1 Cross-module financial verification | 13_regression_e2e_financial.spec.ts | Trial balance remains balanced | AR, GL, and Salesman balance checks | PARTIAL | NOT EXECUTED | PENDING |
| CHK-882 | P&L/BS effects correct | COMPOSITE_FINANCIAL_E2E | 14.1 Cross-module financial verification | 13_regression_e2e_financial.spec.ts | P&L/BS effects correct | AR, GL, and Salesman balance checks | PARTIAL | NOT EXECUTED | PENDING |
| CHK-883 | Second payment | COMPOSITE_FINANCIAL_E2E | 14.1 Cross-module financial verification | 13_regression_e2e_financial.spec.ts | Second payment | AR, GL, and Salesman balance checks | PARTIAL | NOT EXECUTED | PENDING |
| CHK-884 | Fully paid | COMPOSITE_FINANCIAL_E2E | 14.1 Cross-module financial verification | 13_regression_e2e_financial.spec.ts | Fully paid | AR, GL, and Salesman balance checks | PARTIAL | NOT EXECUTED | PENDING |
| CHK-885 | No double counting | COMPOSITE_FINANCIAL_E2E | 14.1 Cross-module financial verification | 13_regression_e2e_financial.spec.ts | No double counting | AR, GL, and Salesman balance checks | PARTIAL | NOT EXECUTED | PENDING |
| CHK-886 | Adjustment requested | COMPOSITE_FINANCIAL_E2E | 14.1 Cross-module financial verification | 13_regression_e2e_financial.spec.ts | Adjustment requested | AR, GL, and Salesman balance checks | PARTIAL | NOT EXECUTED | PENDING |
| CHK-887 | Approval | COMPOSITE_FINANCIAL_E2E | 14.1 Cross-module financial verification | 13_regression_e2e_financial.spec.ts | Approval | AR, GL, and Salesman balance checks | PARTIAL | NOT EXECUTED | PENDING |
| CHK-888 | Inventory consequence | COMPOSITE_FINANCIAL_E2E | 14.1 Cross-module financial verification | 13_regression_e2e_financial.spec.ts | Inventory consequence | AR, GL, and Salesman balance checks | PARTIAL | NOT EXECUTED | PENDING |
| CHK-889 | Tax consequence | COMPOSITE_FINANCIAL_E2E | 14.1 Cross-module financial verification | 13_regression_e2e_financial.spec.ts | Tax consequence | AR, GL, and Salesman balance checks | PARTIAL | NOT EXECUTED | PENDING |
| CHK-890 | Financial consequence | COMPOSITE_FINANCIAL_E2E | 14.1 Cross-module financial verification | 13_regression_e2e_financial.spec.ts | Financial consequence | AR, GL, and Salesman balance checks | PARTIAL | NOT EXECUTED | PENDING |
| CHK-891 | Audit trail | COMPOSITE_FINANCIAL_E2E | 14.1 Cross-module financial verification | 13_regression_e2e_financial.spec.ts | Audit trail | AR, GL, and Salesman balance checks | PARTIAL | NOT EXECUTED | PENDING |
| CHK-892 | Return | COMPOSITE_FINANCIAL_E2E | 14.1 Cross-module financial verification | 13_regression_e2e_financial.spec.ts | Return | AR, GL, and Salesman balance checks | PARTIAL | NOT EXECUTED | PENDING |
| CHK-893 | Credit/refund consequence | COMPOSITE_FINANCIAL_E2E | 14.1 Cross-module financial verification | 13_regression_e2e_financial.spec.ts | Credit/refund consequence | AR, GL, and Salesman balance checks | PARTIAL | NOT EXECUTED | PENDING |
| CHK-894 | AR consequence | COMPOSITE_FINANCIAL_E2E | 14.1 Cross-module financial verification | 13_regression_e2e_financial.spec.ts | AR consequence | AR, GL, and Salesman balance checks | PARTIAL | NOT EXECUTED | PENDING |
| CHK-895 | Accounting consequence | COMPOSITE_FINANCIAL_E2E | 14.1 Cross-module financial verification | 13_regression_e2e_financial.spec.ts | Accounting consequence | AR, GL, and Salesman balance checks | PARTIAL | NOT EXECUTED | PENDING |
| CHK-896 | No duplicated effect | COMPOSITE_FINANCIAL_E2E | 14.1 Cross-module financial verification | 13_regression_e2e_financial.spec.ts | No duplicated effect | AR, GL, and Salesman balance checks | PARTIAL | NOT EXECUTED | PENDING |
| CHK-897 | Duplicate order submit | PLAYWRIGHT_E2E | 33. CONCURRENCY / IDEMPOTENCY / REPLAY | tests/Feature/ pending mapping | Duplicate order submit | pending | NOT_TESTED | NOT EXECUTED | PENDING |
| CHK-898 | Duplicate payment submit | PLAYWRIGHT_E2E | 33. CONCURRENCY / IDEMPOTENCY / REPLAY | tests/Feature/ pending mapping | Duplicate payment submit | pending | NOT_TESTED | NOT EXECUTED | PENDING |
| CHK-899 | Duplicate verification | PLAYWRIGHT_E2E | 33. CONCURRENCY / IDEMPOTENCY / REPLAY | tests/Feature/ pending mapping | Duplicate verification | pending | NOT_TESTED | NOT EXECUTED | PENDING |
| CHK-900 | Duplicate refund processing | PLAYWRIGHT_E2E | 33. CONCURRENCY / IDEMPOTENCY / REPLAY | tests/Feature/ pending mapping | Duplicate refund processing | pending | NOT_TESTED | NOT EXECUTED | PENDING |
| CHK-901 | Duplicate adjustment approval | PLAYWRIGHT_E2E | 33. CONCURRENCY / IDEMPOTENCY / REPLAY | tests/Feature/ pending mapping | Duplicate adjustment approval | pending | NOT_TESTED | NOT EXECUTED | PENDING |
| CHK-902 | Concurrent inventory allocation | PLAYWRIGHT_E2E | 33. CONCURRENCY / IDEMPOTENCY / REPLAY | tests/Feature/ pending mapping | Concurrent inventory allocation | pending | NOT_TESTED | NOT EXECUTED | PENDING |
| CHK-903 | Concurrent delivery completion | PLAYWRIGHT_E2E | 33. CONCURRENCY / IDEMPOTENCY / REPLAY | tests/Feature/ pending mapping | Concurrent delivery completion | pending | NOT_TESTED | NOT EXECUTED | PENDING |
| CHK-904 | Stale order approval | PLAYWRIGHT_E2E | 33. CONCURRENCY / IDEMPOTENCY / REPLAY | tests/Feature/ pending mapping | Stale order approval | pending | NOT_TESTED | NOT EXECUTED | PENDING |
| CHK-905 | Stale adjustment | PLAYWRIGHT_E2E | 33. CONCURRENCY / IDEMPOTENCY / REPLAY | tests/Feature/ pending mapping | Stale adjustment | pending | NOT_TESTED | NOT EXECUTED | PENDING |
| CHK-906 | Role change during active session | HTTP_API_SECURITY | 33. CONCURRENCY / IDEMPOTENCY / REPLAY | tests/Feature/ pending mapping | Role change during active session | pending | NOT_TESTED | NOT EXECUTED | PENDING |
| CHK-907 | Customer reassignment during order | PLAYWRIGHT_E2E | 33. CONCURRENCY / IDEMPOTENCY / REPLAY | tests/Feature/ pending mapping | Customer reassignment during order | pending | NOT_TESTED | NOT EXECUTED | PENDING |
| CHK-908 | Product deactivation during order | PLAYWRIGHT_E2E | 33. CONCURRENCY / IDEMPOTENCY / REPLAY | tests/Feature/ pending mapping | Product deactivation during order | pending | NOT_TESTED | NOT EXECUTED | PENDING |
| CHK-909 | Price/tax change during order | DOMAIN_UNIT_PHP | 33. CONCURRENCY / IDEMPOTENCY / REPLAY | tests/Feature/ pending mapping | Price/tax change during order | pending | NOT_TESTED | NOT EXECUTED | PENDING |
| CHK-910 | Payment state change during order | PLAYWRIGHT_E2E | 33. CONCURRENCY / IDEMPOTENCY / REPLAY | tests/Feature/ pending mapping | Payment state change during order | pending | NOT_TESTED | NOT EXECUTED | PENDING |
| CHK-911 | missing required field | PLAYWRIGHT_E2E | 34. DEEP NEGATIVE-TEST MATRIX | tests/Feature/ pending mapping | missing required field | pending | NOT_TESTED | NOT EXECUTED | PENDING |
| CHK-912 | wrong type | PLAYWRIGHT_E2E | 34. DEEP NEGATIVE-TEST MATRIX | tests/Feature/ pending mapping | wrong type | pending | NOT_TESTED | NOT EXECUTED | PENDING |
| CHK-913 | negative value | PLAYWRIGHT_E2E | 34. DEEP NEGATIVE-TEST MATRIX | tests/Feature/ pending mapping | negative value | pending | NOT_TESTED | NOT EXECUTED | PENDING |
| CHK-914 | zero where invalid | PLAYWRIGHT_E2E | 34. DEEP NEGATIVE-TEST MATRIX | tests/Feature/ pending mapping | zero where invalid | pending | NOT_TESTED | NOT EXECUTED | PENDING |
| CHK-915 | excessively large value | PLAYWRIGHT_E2E | 34. DEEP NEGATIVE-TEST MATRIX | tests/Feature/ pending mapping | excessively large value | pending | NOT_TESTED | NOT EXECUTED | PENDING |
| CHK-916 | invalid enum | PLAYWRIGHT_E2E | 34. DEEP NEGATIVE-TEST MATRIX | tests/Feature/ pending mapping | invalid enum | pending | NOT_TESTED | NOT EXECUTED | PENDING |
| CHK-917 | malformed date | PLAYWRIGHT_E2E | 34. DEEP NEGATIVE-TEST MATRIX | tests/Feature/ pending mapping | malformed date | pending | NOT_TESTED | NOT EXECUTED | PENDING |
| CHK-918 | invalid ID | PLAYWRIGHT_E2E | 34. DEEP NEGATIVE-TEST MATRIX | tests/Feature/ pending mapping | invalid ID | pending | NOT_TESTED | NOT EXECUTED | PENDING |
| CHK-919 | another user's ID | PLAYWRIGHT_E2E | 34. DEEP NEGATIVE-TEST MATRIX | tests/Feature/ pending mapping | another user's ID | pending | NOT_TESTED | NOT EXECUTED | PENDING |
| CHK-920 | another customer's ID | PLAYWRIGHT_E2E | 34. DEEP NEGATIVE-TEST MATRIX | tests/Feature/ pending mapping | another customer's ID | pending | NOT_TESTED | NOT EXECUTED | PENDING |
| CHK-921 | stale version | PLAYWRIGHT_E2E | 34. DEEP NEGATIVE-TEST MATRIX | tests/Feature/ pending mapping | stale version | pending | NOT_TESTED | NOT EXECUTED | PENDING |
| CHK-922 | repeated request | PLAYWRIGHT_E2E | 34. DEEP NEGATIVE-TEST MATRIX | tests/Feature/ pending mapping | repeated request | pending | NOT_TESTED | NOT EXECUTED | PENDING |
| CHK-923 | unauthorized role | HTTP_API_SECURITY | 34. DEEP NEGATIVE-TEST MATRIX | tests/Feature/ pending mapping | unauthorized role | pending | NOT_TESTED | NOT EXECUTED | PENDING |
| CHK-924 | suspended user | PLAYWRIGHT_E2E | 34. DEEP NEGATIVE-TEST MATRIX | tests/Feature/ pending mapping | suspended user | pending | NOT_TESTED | NOT EXECUTED | PENDING |
| CHK-925 | malformed upload | PLAYWRIGHT_E2E | 34. DEEP NEGATIVE-TEST MATRIX | tests/Feature/ pending mapping | malformed upload | pending | NOT_TESTED | NOT EXECUTED | PENDING |
| CHK-926 | oversized upload | PLAYWRIGHT_E2E | 34. DEEP NEGATIVE-TEST MATRIX | tests/Feature/ pending mapping | oversized upload | pending | NOT_TESTED | NOT EXECUTED | PENDING |
| CHK-927 | invalid file content | PLAYWRIGHT_E2E | 34. DEEP NEGATIVE-TEST MATRIX | tests/Feature/ pending mapping | invalid file content | pending | NOT_TESTED | NOT EXECUTED | PENDING |
| CHK-928 | Categories populated | PLAYWRIGHT_E2E | Design system and shell presentation | 12_responsive_and_a11y.spec.ts | Categories populated | Quantum Blue / Ice Glass tokens in all shell screenshots | PASS | 2026-09-12 | AUDIT-RUN-20260912-154532 |
| CHK-929 | Products populated | PLAYWRIGHT_E2E | Design system and shell presentation | 12_responsive_and_a11y.spec.ts | Products populated | Quantum Blue / Ice Glass tokens in all shell screenshots | PASS | 2026-09-12 | AUDIT-RUN-20260912-154532 |
| CHK-930 | Product images present | PLAYWRIGHT_E2E | Design system and shell presentation | 12_responsive_and_a11y.spec.ts | Product images present | Quantum Blue / Ice Glass tokens in all shell screenshots | PASS | 2026-09-12 | AUDIT-RUN-20260912-154532 |
| CHK-931 | Image quality consistent | PLAYWRIGHT_E2E | Design system and shell presentation | 12_responsive_and_a11y.spec.ts | Image quality consistent | Quantum Blue / Ice Glass tokens in all shell screenshots | PASS | 2026-09-12 | AUDIT-RUN-20260912-154532 |
| CHK-932 | Prices realistic | PLAYWRIGHT_E2E | Design system and shell presentation | 12_responsive_and_a11y.spec.ts | Prices realistic | Quantum Blue / Ice Glass tokens in all shell screenshots | PASS | 2026-09-12 | AUDIT-RUN-20260912-154532 |
| CHK-933 | Stock realistic | PLAYWRIGHT_E2E | Design system and shell presentation | 12_responsive_and_a11y.spec.ts | Stock realistic | Quantum Blue / Ice Glass tokens in all shell screenshots | PASS | 2026-09-12 | AUDIT-RUN-20260912-154532 |
| CHK-934 | No confidential data | PLAYWRIGHT_E2E | Design system and shell presentation | 12_responsive_and_a11y.spec.ts | No confidential data | Quantum Blue / Ice Glass tokens in all shell screenshots | PASS | 2026-09-12 | AUDIT-RUN-20260912-154532 |
| CHK-935 | Admin login | PLAYWRIGHT_E2E | Design system and shell presentation | 12_responsive_and_a11y.spec.ts | Admin login | Quantum Blue / Ice Glass tokens in all shell screenshots | PASS | 2026-09-12 | AUDIT-RUN-20260912-154532 |
| CHK-936 | Salesman login | PLAYWRIGHT_E2E | Design system and shell presentation | 12_responsive_and_a11y.spec.ts | Salesman login | Quantum Blue / Ice Glass tokens in all shell screenshots | PASS | 2026-09-12 | AUDIT-RUN-20260912-154532 |
| CHK-937 | Create order | PLAYWRIGHT_E2E | Design system and shell presentation | 12_responsive_and_a11y.spec.ts | Create order | Quantum Blue / Ice Glass tokens in all shell screenshots | PASS | 2026-09-12 | AUDIT-RUN-20260912-154532 |
| CHK-938 | Payment | PLAYWRIGHT_E2E | Design system and shell presentation | 12_responsive_and_a11y.spec.ts | Payment | Quantum Blue / Ice Glass tokens in all shell screenshots | PASS | 2026-09-12 | AUDIT-RUN-20260912-154532 |
| CHK-939 | Verification | PLAYWRIGHT_E2E | Design system and shell presentation | 12_responsive_and_a11y.spec.ts | Verification | Quantum Blue / Ice Glass tokens in all shell screenshots | PASS | 2026-09-12 | AUDIT-RUN-20260912-154532 |
| CHK-940 | AR | PLAYWRIGHT_E2E | Design system and shell presentation | 12_responsive_and_a11y.spec.ts | AR | Quantum Blue / Ice Glass tokens in all shell screenshots | PASS | 2026-09-12 | AUDIT-RUN-20260912-154532 |
| CHK-941 | Invoice | PLAYWRIGHT_E2E | Design system and shell presentation | 12_responsive_and_a11y.spec.ts | Invoice | Quantum Blue / Ice Glass tokens in all shell screenshots | PASS | 2026-09-12 | AUDIT-RUN-20260912-154532 |
| CHK-942 | Warehouse | PLAYWRIGHT_E2E | Design system and shell presentation | 12_responsive_and_a11y.spec.ts | Warehouse | Quantum Blue / Ice Glass tokens in all shell screenshots | PASS | 2026-09-12 | AUDIT-RUN-20260912-154532 |
| CHK-943 | Delivery | PLAYWRIGHT_E2E | Design system and shell presentation | 12_responsive_and_a11y.spec.ts | Delivery | Quantum Blue / Ice Glass tokens in all shell screenshots | PASS | 2026-09-12 | AUDIT-RUN-20260912-154532 |
| CHK-944 | Return/refund | PLAYWRIGHT_E2E | Design system and shell presentation | 12_responsive_and_a11y.spec.ts | Return/refund | Quantum Blue / Ice Glass tokens in all shell screenshots | PASS | 2026-09-12 | AUDIT-RUN-20260912-154532 |
| CHK-945 | No developer text | PLAYWRIGHT_E2E | Design system and shell presentation | 12_responsive_and_a11y.spec.ts | No developer text | Quantum Blue / Ice Glass tokens in all shell screenshots | PASS | 2026-09-12 | AUDIT-RUN-20260912-154532 |
| CHK-946 | No stale branding | PLAYWRIGHT_E2E | Design system and shell presentation | 12_responsive_and_a11y.spec.ts | No stale branding | Quantum Blue / Ice Glass tokens in all shell screenshots | PASS | 2026-09-12 | AUDIT-RUN-20260912-154532 |
| CHK-947 | No broken image | PLAYWRIGHT_E2E | Design system and shell presentation | 12_responsive_and_a11y.spec.ts | No broken image | Quantum Blue / Ice Glass tokens in all shell screenshots | PASS | 2026-09-12 | AUDIT-RUN-20260912-154532 |
| CHK-948 | No empty demo screen unless intentionally empty | PLAYWRIGHT_E2E | Design system and shell presentation | 12_responsive_and_a11y.spec.ts | No empty demo screen unless intentionally empty | Quantum Blue / Ice Glass tokens in all shell screenshots | PASS | 2026-09-12 | AUDIT-RUN-20260912-154532 |
| CHK-949 | No console errors caused by normal demo flow | PLAYWRIGHT_E2E | Design system and shell presentation | 12_responsive_and_a11y.spec.ts | No console errors caused by normal demo flow | Quantum Blue / Ice Glass tokens in all shell screenshots | PASS | 2026-09-12 | AUDIT-RUN-20260912-154532 |
| CHK-950 | No unexpected 500 | PLAYWRIGHT_E2E | Design system and shell presentation | 12_responsive_and_a11y.spec.ts | No unexpected 500 | Quantum Blue / Ice Glass tokens in all shell screenshots | PASS | 2026-09-12 | AUDIT-RUN-20260912-154532 |
| CHK-951 | Mobile and desktop acceptable | PLAYWRIGHT_E2E | Design system and shell presentation | 12_responsive_and_a11y.spec.ts | Mobile and desktop acceptable | Quantum Blue / Ice Glass tokens in all shell screenshots | PASS | 2026-09-12 | AUDIT-RUN-20260912-154532 |
| CHK-952 | Duplicate symptoms merged | META_AUDIT_RULE | Reconciliation evaluation | Meta-Contract | Duplicate symptoms merged | pending | NOT_APPLICABLE | NOT EXECUTED | PENDING |
| CHK-953 | Common backend root causes grouped | META_AUDIT_RULE | Reconciliation evaluation | Meta-Contract | Common backend root causes grouped | pending | NOT_APPLICABLE | NOT EXECUTED | PENDING |
| CHK-954 | Common frontend root causes grouped | META_AUDIT_RULE | Reconciliation evaluation | Meta-Contract | Common frontend root causes grouped | pending | NOT_APPLICABLE | NOT EXECUTED | PENDING |
| CHK-955 | Financial bugs isolated | META_AUDIT_RULE | Reconciliation evaluation | Meta-Contract | Financial bugs isolated | pending | NOT_APPLICABLE | NOT EXECUTED | PENDING |
| CHK-956 | Security bugs isolated | META_AUDIT_RULE | Reconciliation evaluation | Meta-Contract | Security bugs isolated | pending | NOT_APPLICABLE | NOT EXECUTED | PENDING |
| CHK-957 | Inventory bugs isolated | META_AUDIT_RULE | Reconciliation evaluation | Meta-Contract | Inventory bugs isolated | pending | NOT_APPLICABLE | NOT EXECUTED | PENDING |
| CHK-958 | Payment bugs isolated | META_AUDIT_RULE | Reconciliation evaluation | Meta-Contract | Payment bugs isolated | pending | NOT_APPLICABLE | NOT EXECUTED | PENDING |
| CHK-959 | Browser infrastructure bugs isolated | META_AUDIT_RULE | Reconciliation evaluation | Meta-Contract | Browser infrastructure bugs isolated | pending | NOT_APPLICABLE | NOT EXECUTED | PENDING |
| CHK-960 | Cosmetic issues separated | META_AUDIT_RULE | Reconciliation evaluation | Meta-Contract | Cosmetic issues separated | pending | NOT_APPLICABLE | NOT EXECUTED | PENDING |
| CHK-961 | Recent-fix regressions linked | META_AUDIT_RULE | Reconciliation evaluation | Meta-Contract | Recent-fix regressions linked | pending | NOT_APPLICABLE | NOT EXECUTED | PENDING |
| CHK-962 | P0/P1 security | META_AUDIT_RULE | Reconciliation evaluation | Meta-Contract | P0/P1 security | pending | NOT_APPLICABLE | NOT EXECUTED | PENDING |
| CHK-963 | Financial integrity | META_AUDIT_RULE | Reconciliation evaluation | Meta-Contract | Financial integrity | pending | NOT_APPLICABLE | NOT EXECUTED | PENDING |
| CHK-964 | Payment | META_AUDIT_RULE | Reconciliation evaluation | Meta-Contract | Payment | pending | NOT_APPLICABLE | NOT EXECUTED | PENDING |
| CHK-965 | AR/AP | META_AUDIT_RULE | Reconciliation evaluation | Meta-Contract | AR/AP | pending | NOT_APPLICABLE | NOT EXECUTED | PENDING |
| CHK-966 | Inventory/adjustments | META_AUDIT_RULE | Reconciliation evaluation | Meta-Contract | Inventory/adjustments | pending | NOT_APPLICABLE | NOT EXECUTED | PENDING |
| CHK-967 | Orders | META_AUDIT_RULE | Reconciliation evaluation | Meta-Contract | Orders | pending | NOT_APPLICABLE | NOT EXECUTED | PENDING |
| CHK-968 | Delivery/returns | META_AUDIT_RULE | Reconciliation evaluation | Meta-Contract | Delivery/returns | pending | NOT_APPLICABLE | NOT EXECUTED | PENDING |
| CHK-969 | Authorization/IDOR | HTTP_API_SECURITY | Reconciliation evaluation | Meta-Contract | Authorization/IDOR | pending | NOT_APPLICABLE | NOT EXECUTED | PENDING |
| CHK-970 | Frontend/runtime | META_AUDIT_RULE | Reconciliation evaluation | Meta-Contract | Frontend/runtime | pending | NOT_APPLICABLE | NOT EXECUTED | PENDING |
| CHK-971 | Responsive/accessibility | META_AUDIT_RULE | Reconciliation evaluation | Meta-Contract | Responsive/accessibility | pending | NOT_APPLICABLE | NOT EXECUTED | PENDING |
| CHK-972 | UX/polish | META_AUDIT_RULE | Reconciliation evaluation | Meta-Contract | UX/polish | pending | NOT_APPLICABLE | NOT EXECUTED | PENDING |
| CHK-973 | Dead code/stale artifacts | META_AUDIT_RULE | Reconciliation evaluation | Meta-Contract | Dead code/stale artifacts | pending | NOT_APPLICABLE | NOT EXECUTED | PENDING |
| CHK-974 | Every applicable checklist item has PASS / BUG / N/A / BLOCKED / NEEDS-CLARIFICATION | META_AUDIT_RULE | Reconciliation evaluation | Meta-Contract | Every applicable checklist item has PASS / BUG / N/A / BLOCKED / NEEDS-CLARIFICATION | pending | NOT_APPLICABLE | NOT EXECUTED | PENDING |
| CHK-975 | No required item remains unchecked | META_AUDIT_RULE | Reconciliation evaluation | Meta-Contract | No required item remains unchecked | pending | NOT_APPLICABLE | NOT EXECUTED | PENDING |
| CHK-976 | Every PASS has evidence | META_AUDIT_RULE | Reconciliation evaluation | Meta-Contract | Every PASS has evidence | pending | NOT_APPLICABLE | NOT EXECUTED | PENDING |
| CHK-977 | Every BUG has evidence | META_AUDIT_RULE | Reconciliation evaluation | Meta-Contract | Every BUG has evidence | pending | NOT_APPLICABLE | NOT EXECUTED | PENDING |
| CHK-978 | All six roles were actually exercised where applicable | HTTP_API_SECURITY | Reconciliation evaluation | Meta-Contract | All six roles were actually exercised where applicable | pending | NOT_APPLICABLE | NOT EXECUTED | PENDING |
| CHK-979 | Both salesman accounts were scope-tested | META_AUDIT_RULE | Reconciliation evaluation | Meta-Contract | Both salesman accounts were scope-tested | pending | NOT_APPLICABLE | NOT EXECUTED | PENDING |
| CHK-980 | AR received deepest coverage | META_AUDIT_RULE | Reconciliation evaluation | Meta-Contract | AR received deepest coverage | pending | NOT_APPLICABLE | NOT EXECUTED | PENDING |
| CHK-981 | Financial golden scenario completed | META_AUDIT_RULE | Reconciliation evaluation | Meta-Contract | Financial golden scenario completed | pending | NOT_APPLICABLE | NOT EXECUTED | PENDING |
| CHK-982 | Payment lifecycle completed | META_AUDIT_RULE | Reconciliation evaluation | Meta-Contract | Payment lifecycle completed | pending | NOT_APPLICABLE | NOT EXECUTED | PENDING |
| CHK-983 | Order lifecycle completed | META_AUDIT_RULE | Reconciliation evaluation | Meta-Contract | Order lifecycle completed | pending | NOT_APPLICABLE | NOT EXECUTED | PENDING |
| CHK-984 | Adjustment lifecycle completed | META_AUDIT_RULE | Reconciliation evaluation | Meta-Contract | Adjustment lifecycle completed | pending | NOT_APPLICABLE | NOT EXECUTED | PENDING |
| CHK-985 | Inventory lifecycle completed | META_AUDIT_RULE | Reconciliation evaluation | Meta-Contract | Inventory lifecycle completed | pending | NOT_APPLICABLE | NOT EXECUTED | PENDING |
| CHK-986 | Delivery lifecycle completed | META_AUDIT_RULE | Reconciliation evaluation | Meta-Contract | Delivery lifecycle completed | pending | NOT_APPLICABLE | NOT EXECUTED | PENDING |
| CHK-987 | Returns/credits/refunds completed | POSTGRES_DB_INVARIANT | Reconciliation evaluation | Meta-Contract | Returns/credits/refunds completed | pending | NOT_APPLICABLE | NOT EXECUTED | PENDING |
| CHK-988 | Accounting reconciled | META_AUDIT_RULE | Reconciliation evaluation | Meta-Contract | Accounting reconciled | pending | NOT_APPLICABLE | NOT EXECUTED | PENDING |
| CHK-989 | Responsive critical workflows completed | META_AUDIT_RULE | Reconciliation evaluation | Meta-Contract | Responsive critical workflows completed | pending | NOT_APPLICABLE | NOT EXECUTED | PENDING |
| CHK-990 | Console review completed | META_AUDIT_RULE | Reconciliation evaluation | Meta-Contract | Console review completed | pending | NOT_APPLICABLE | NOT EXECUTED | PENDING |
| CHK-991 | Network review completed | META_AUDIT_RULE | Reconciliation evaluation | Meta-Contract | Network review completed | pending | NOT_APPLICABLE | NOT EXECUTED | PENDING |
| CHK-992 | Security/IDOR review completed | HTTP_API_SECURITY | Reconciliation evaluation | Meta-Contract | Security/IDOR review completed | pending | NOT_APPLICABLE | NOT EXECUTED | PENDING |
| CHK-993 | Recent fixes rechecked | META_AUDIT_RULE | Reconciliation evaluation | Meta-Contract | Recent fixes rechecked | pending | NOT_APPLICABLE | NOT EXECUTED | PENDING |
| CHK-994 | No false PASS caused by route-loading | META_AUDIT_RULE | Reconciliation evaluation | Meta-Contract | No false PASS caused by route-loading | pending | NOT_APPLICABLE | NOT EXECUTED | PENDING |
| CHK-995 | No silent skips | META_AUDIT_RULE | Reconciliation evaluation | Meta-Contract | No silent skips | pending | NOT_APPLICABLE | NOT EXECUTED | PENDING |
| CHK-996 | All blocked items documented | META_AUDIT_RULE | Reconciliation evaluation | Meta-Contract | All blocked items documented | pending | NOT_APPLICABLE | NOT EXECUTED | PENDING |
| CHK-997 | BUGLIST finalized | META_AUDIT_RULE | Reconciliation evaluation | Meta-Contract | BUGLIST finalized | pending | NOT_APPLICABLE | NOT EXECUTED | PENDING |
| CHK-998 | Root-cause groups finalized | META_AUDIT_RULE | Reconciliation evaluation | Meta-Contract | Root-cause groups finalized | pending | NOT_APPLICABLE | NOT EXECUTED | PENDING |
| CHK-999 | Recommended fix batches finalized | META_AUDIT_RULE | Reconciliation evaluation | Meta-Contract | Recommended fix batches finalized | pending | NOT_APPLICABLE | NOT EXECUTED | PENDING |
