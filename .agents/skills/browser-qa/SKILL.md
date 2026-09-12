---
name: browser-qa
description: >-
  Use this skill whenever the user asks to open, inspect, click, test, verify, or interact with the application in the real persistent browser, take screenshots, test responsive viewports, diagnose console/network errors, or resume after manual browser takeover.
---

# Persistent Real-Browser QA Skill

This skill guides conversational interaction with the real, persistent, headed Google Chrome window running on the local desktop / second monitor via the `unique-distributors-browser` MCP server.

---

## 1. Operating Rules

1. **One Persistent Browser:** Never restart or close the browser after individual actions unless explicitly instructed with `browser_stop`.
2. **Observe Before Acting:** When resuming or continuing after manual user takeover, call `browser_observe` first to inspect the live page state.
3. **Natural Intent Translation:**
   - *"Open the login page"* $\rightarrow$ `browser_navigate("/login")`
   - *"Login as Admin"* $\rightarrow$ `browser_login("ADMIN")`
   - *"Open Accounts Receivable"* $\rightarrow$ `browser_navigate("/admin/accounts-receivable")`
   - *"Switch to mobile"* $\rightarrow$ `browser_viewport("mobile")`
   - *"Take a screenshot"* $\rightarrow$ `browser_screenshot("qa_verification")`
   - *"Check diagnostics"* $\rightarrow$ `browser_diagnostics()`
   - *"Continue from current page"* $\rightarrow$ `browser_observe()`
4. **Security & Redaction:** Do not log or output plain-text passwords, auth tokens, or TOTP secret seeds.
5. **No Code Edits in QA Mode:** Report defects with structured evidence without modifying application source code.
