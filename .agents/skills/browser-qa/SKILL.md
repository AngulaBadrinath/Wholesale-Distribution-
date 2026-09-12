---
name: browser-qa
description: >-
  Use this skill whenever the user asks to open, inspect, click, test, verify, or interact with the application in the real persistent browser, take screenshots, test responsive viewports, diagnose console/network errors, or resume after manual browser takeover.
---

# Persistent Real-Browser QA Skill

This skill guides conversational interaction with the real, persistent, headed Google Chrome window running on the local desktop / second monitor via the `chrome-devtools` and `unique-distributors-browser` MCP servers over CDP port `9222`.

---

## 1. Operating Rules

1. **One Persistent Browser Window:** The visible Chrome instance runs on the secondary monitor (or primary screen with an offset) via `npm run browser:open`. Never restart or close the browser after individual actions unless explicitly instructed with `browser_stop`.
2. **Observe Before Acting:** When resuming or continuing after manual user takeover, call `take_snapshot` (or `browser_observe`) first to inspect the live page state.
3. **Dual-Layer Tool Mapping:**
   - **Page Observation:** `take_snapshot` (semantic accessibility tree) or `browser_observe`
   - **Granular UI Actions:** `click`, `fill`, `fill_form`, `type_text`, `press_key`, `upload_file`
   - **Automated MFA Authentication:** `browser_login("ADMIN")` (auto-computes TOTP)
   - **Responsive Viewports:** `browser_viewport("mobile")` or `resize_page`
   - **Visual Evidence:** `take_screenshot` or `browser_screenshot`
   - **DevTools Diagnostics:** `list_console_messages`, `list_network_requests`, `browser_diagnostics`
   - **Destructive Gates:** `browser_confirm_destructive_action`
4. **Natural Intent Translation:**
   - *"Open the login page"* $\rightarrow$ `navigate_page({ pageId, url: "http://localhost:8000/login" })` or `browser_navigate("/login")`
   - *"Login as Admin"* $\rightarrow$ `browser_login("ADMIN")`
   - *"Open Accounts Receivable"* $\rightarrow$ `browser_navigate("/admin/accounting/receivables")`
   - *"Switch to mobile"* $\rightarrow$ `browser_viewport("mobile")`
   - *"Take a screenshot"* $\rightarrow$ `take_screenshot()` or `browser_screenshot("qa_verification")`
   - *"Check diagnostics"* $\rightarrow$ `list_console_messages()` / `list_network_requests()`
   - *"Continue from current page"* $\rightarrow$ `take_snapshot()`
5. **Security & Redaction:** Do not log or output plain-text passwords, auth tokens, or TOTP secret seeds.
6. **No Code Edits in QA Mode:** Report defects with structured evidence without modifying application source code.
