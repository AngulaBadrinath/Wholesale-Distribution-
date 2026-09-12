# Antigravity-Native Browser MCP Tool Suite

## Wholesale Distribution Management System

**Document Version:** 1.0  
**Effective Date:** September 2026  
**Target Operating Model:** Antigravity AI Agent + Real Persistent Headed Google Chrome

---

## 1. Architectural Overview

The Unique Distributors Browser MCP Tool Suite transforms the existing persistent browser daemon into an agent-native control layer. Antigravity can directly invoke strongly-typed browser tools during conversation to inspect and control a real, visible Google Chrome window running on the developer's desktop or second monitor.

```
┌─────────────────────────────────────────────────────────────┐
│                      ANTIGRAVITY AGENT                      │
│        (Conversational AI / Browser QA Subagent)            │
└──────────────┬───────────────────────────────┬──────────────┘
               │                               │
               ▼                               ▼
┌─────────────────────────────┐ ┌─────────────────────────────┐
│     CHROME DEVTOOLS MCP     │ │ UNIQUE DISTRIBUTORS BROWSER │
│ (chrome-devtools-mcp@1.9.0) │ │   tests/.../mcp-server.ts   │
│ - take_snapshot (a11y tree) │ │ - browser_login (TOTP MFA)  │
│ - click / fill / type_text  │ │ - browser_viewport matrix   │
│ - DevTools console/network  │ │ - confirm_destructive_act   │
└──────────────┬──────────────┘ └──────────────┬──────────────┘
               │                               │
               └───────────────┬───────────────┘
                               ▼
            CHROME DEVTOOLS PROTOCOL (CDP 9222)
                               ▼
┌─────────────────────────────────────────────────────────────┐
│           REAL HEADED LOCAL GOOGLE CHROME WINDOW            │
│      - Persistent BrowserContext                            │
│      - Live cookie, session, and local storage state        │
│      - Visible on desktop / secondary monitor               │
└─────────────────────────────────────────────────────────────┘
```

---

## 2. Workspace MCP Registration

The tool server is registered in [`.agents/mcp_config.json`](file:///.agents/mcp_config.json):

```json
{
  "mcpServers": {
    "chrome-devtools": {
      "command": "npx",
      "args": [
        "-y",
        "chrome-devtools-mcp@1.9.0",
        "--browserUrl",
        "http://127.0.0.1:9222",
        "--workspace",
        "F:\\Wholesale Distribution Management System",
        "--redactNetworkHeaders"
      ]
    },
    "unique-distributors-browser": {
      "command": "node",
      "args": [
        "--no-warnings",
        "tests/browser/interactive/mcp-server.ts"
      ]
    }
  }
}
```

Antigravity automatically discovers and connects to `unique-distributors-browser` when loading the workspace.

---

## 3. Exposed Tool Catalog (22 Tools)

| Tool Name | Parameters | Description |
| :--- | :--- | :--- |
| `browser_status` | *(none)* | Returns running status, URL, title, viewport, environment, active tab, and issues summary. |
| `browser_start` | `headed` (bool), `startUrl` (string) | Ensures the persistent browser daemon is running and opens the initial URL/path. |
| `browser_stop` | *(none)* | Explicitly terminates the browser session and releases system resources. |
| `browser_observe` | `detailLevel` ('summary' \| 'detailed'), `captureScreenshot` (bool) | Extracts semantic DOM elements (headings, inputs, buttons, links, tables, alerts, text snippet). |
| `browser_screenshot` | `name` (string), `fullPage` (bool) | Captures timestamped PNG artifact to `artifacts/browser/interactive/screenshots/`. |
| `browser_navigate` | `path_or_url` (string) | Navigates active tab to relative path or allowed URL. Enforces domain security. |
| `browser_click` | `selector`, `text`, `role`, `name`, `timeout` | Clicks element by CSS selector, visible text, or ARIA role and accessible name. |
| `browser_fill` | `selector`, `label`, `value`, `timeout` | Fills input field or textarea. Passwords/tokens are automatically redacted. |
| `browser_select` | `selector`, `value`, `timeout` | Selects `<select>` dropdown option by value or text label. |
| `browser_check` | `selector`, `timeout` | Checks a checkbox or radio button. |
| `browser_uncheck` | `selector`, `timeout` | Unchecks a checkbox. |
| `browser_press` | `key`, `selector`, `timeout` | Dispatches keyboard key press (e.g. `Enter`, `Escape`, `Tab`, `ArrowDown`). |
| `browser_wait_for` | `selector`, `timeout` | Waits for selector to become visible (max bounded 30s). |
| `browser_tabs` | *(none)* | Lists all open tabs in the persistent session context. |
| `browser_new_tab` | `url_or_path` (string) | Opens new tab in the same BrowserContext (preserving auth/cookies). |
| `browser_switch_tab` | `index` (number) | Switches active tab by zero-based index. |
| `browser_close_tab` | `index` (number) | Closes secondary tab (prevents closing sole active tab). |
| `browser_viewport` | `preset` (string), `width` (num), `height` (num) | Resizes viewport using project matrix preset or explicit dimensions. |
| `browser_diagnostics` | *(none)* | Retrieves live console logs, exceptions, and network errors with redactions. |
| `browser_login` | `role` (enum) | Logs in authoritatively as designated role with automated MFA resolution. |
| `browser_execute_sequence` | `steps` (array) | Executes structured safe action sequence without arbitrary JS eval. |
| `browser_confirm_destructive_action` | `actionDescription`, `confirmed`, `action` | Enforces explicit user confirmation before destructive operations. |

---

## 4. Conversational QA & Natural Language Patterns

| User Natural Intent | MCP Tool Invocations |
| :--- | :--- |
| *"Open the login page"* | `browser_navigate(path_or_url: "/login")` |
| *"Login as Admin"* | `browser_login(role: "ADMIN")` |
| *"Login as Salesman"* | `browser_login(role: "SALESMAN")` |
| *"Open Accounts Receivable"* | `browser_navigate(path_or_url: "/admin/accounts-receivable")` |
| *"Click New Customer"* | `browser_click(text: "New Customer")` or `browser_click(role: "button", name: "New Customer")` |
| *"Type Apex Supermarket into Customer Name"* | `browser_fill(label: "Customer Name", value: "Apex Supermarket")` |
| *"Switch to mobile layout"* | `browser_viewport(preset: "mobile")` (390×844) |
| *"Switch to tablet"* | `browser_viewport(preset: "tablet")` (768×1024) |
| *"Switch to 1080p desktop"* | `browser_viewport(preset: "desktop_fhd")` (1920×1080) |
| *"Open orders in a second tab"* | `browser_new_tab(url_or_path: "/admin/orders")` |
| *"Switch back to first tab"* | `browser_switch_tab(index: 0)` |
| *"Take a screenshot"* | `browser_screenshot(name: "qa_verification")` |
| *"Check for console or network errors"* | `browser_diagnostics()` |
| *"Continue from current page"* | `browser_observe(detailLevel: "summary")` |

---

## 5. Manual User Takeover Protocol

1. **User Interacts Freely:** The user can physically click, scroll, fill forms, or navigate in the visible Google Chrome window.
2. **Agent Re-Observes:** When the user prompts *"Continue"* or asks the next question, the agent calls `browser_observe`.
3. **Live State Inspection:** The agent inspects the current URL, page title, headings, and interactive elements directly from the live DOM without assuming historical state.
4. **Resumed Control:** The agent continues executing the user's intent from the current live state.

---

## 6. Security, Privacy & Safety Guarantees

- **No Remote Dependencies:** Never downloads external Playwright browser binaries; resolves local Google Chrome installation.
- **Domain Allowlist:** Restricts navigation to `localhost`, `127.0.0.1`, and the configured `PLAYWRIGHT_BASE_URL`. External domain navigation attempts are strictly blocked.
- **Credential Redaction:** Passwords, TOTP seeds, Authorization bearer tokens, and S3 presigned signature parameters are automatically redacted from MCP tool returns, logs, and traces.
- **Destructive Action Gate:** Irreversible operations require explicit confirmation via `browser_confirm_destructive_action`.
- **Zero Eval Execution:** `browser_execute_sequence` exclusively accepts safe structured action tokens; arbitrary `eval()` or unsanitized JavaScript execution is prevented.

---

## 7. Verification & Testing

To run the automated verification suite covering all 22 MCP tools:

```bash
npm run test:mcp
```

To run the Playwright runner infrastructure spec:

```bash
npx playwright test tests/browser/interactive/runner.spec.ts
```
