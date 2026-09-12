# Interactive Persistent Real-Browser QA Runner Guide
## Unique Distributors — Wholesale Distribution Management System

**Document Version:** 1.0  
**Effective Date:** September 2026  
**Audience:** Developers, QA Engineers, CI/CD Automations, Antigravity AI Agent  
**Scope:** Interactive, stateful, headed real-browser QA automation and continuous manual-agent pair testing without CDN browser dependencies.

---

## 1. Overview & Purpose

The **Interactive Persistent Real-Browser QA Runner** enables Antigravity and developers to control a live, headed Google Chrome window running on a local or secondary monitor. Unlike traditional one-shot automated tests that launch and tear down the browser after every command, this system:
1. **Keeps the Browser Open:** Launches a single headed Chrome instance that stays alive across multiple sequential commands.
2. **Preserves Stateful Sessions:** Maintains logged-in sessions, cookies, page history, form states, and tabs across commands.
3. **Supports Manual Hand-off:** Allows the developer to inspect, click, or test manually at any point; subsequent agent commands seamlessly continue from the browser's current page state.
4. **Eliminates Playwright CDN Dependencies:** Completely bypasses Playwright CDN browser binaries (avoiding HTTP 404 installation failures) and uses the locally installed Google Chrome (`C:\Program Files\Google\Chrome\Application\chrome.exe` on Windows).
5. **Provides Real-time Diagnostics & Screenshots:** Continuously records console errors, warnings, failed HTTP requests, and full-page screenshots into timestamped artifacts.

---

## 2. Architecture & Communication Flow

```text
+-----------------------------------------------------------------------------------+
|                            ANTIGRAVITY AI AGENT / DEVELOPER                       |
+-----------------------------------------------------------------------------------+
                                          |
                         HTTP JSON API / REPL CLI Commands
                                          v
+-----------------------------------------------------------------------------------+
|                 INTERACTIVE BROWSER CONTROLLER & DAEMON (Port 4444)               |
|                                                                                   |
|  - InteractiveBrowserServer (HTTP Daemon on 127.0.0.1:4444)                      |
|  - InteractiveBrowserController (Stateful context, page, tabs, and diagnostics)   |
|  - Session Lock (artifacts/browser/interactive/session.json)                      |
+-----------------------------------------------------------------------------------+
                                          |
                               Playwright Automation
                                          v
+-----------------------------------------------------------------------------------+
|               REAL LOCAL GOOGLE CHROME BROWSER (Headed GUI Window)                |
|                                                                                   |
|  - Dedicated isolated QA user profile (no personal profile corruption)            |
|  - Placed on primary or secondary monitor                                         |
|  - Connected to Target Application (e.g. http://localhost:8000)                   |
+-----------------------------------------------------------------------------------+
```

---

## 3. Quick Start & CLI Usage

### Starting the Interactive REPL
To start the interactive browser QA session in your terminal:
```bash
npm run browser:interactive
```
This automatically launches the headed Chrome window, opens the base URL, and enters an interactive prompt (`[Browser QA]> `).

### Running Individual Commands via CLI
Antigravity or automated scripts can invoke single actions directly without entering the REPL:
```bash
npm run browser:qa -- navigate /login
npm run browser:qa -- login ADMIN
npm run browser:qa -- navigate /admin/accounting/profit-loss
npm run browser:qa -- screenshot pnl_september
npm run browser:qa -- diagnostics
npm run browser:qa -- status
npm run browser:qa -- stop
```

### Dedicated Daemon Server
To run the browser daemon as a standalone background service:
```bash
npm run browser:interactive:headed
```

---

## 4. Supported Command Reference

| Command | Syntax | Description |
|---|---|---|
| **`navigate`** | `navigate <path/url>` | Navigates the active tab to a relative path (e.g. `/admin/orders`) or absolute URL. |
| **`login`** | `login <ROLE>` | Authenticates using standard test credentials for `SUPER_ADMIN`, `ADMIN`, `ACCOUNTANT`, `SALESMAN`, `WAREHOUSE_MANAGER`, `DELIVERY_PARTNER`. Handles MFA automatically. |
| **`click`** | `click <selector>` | Clicks an element matching CSS selector or visible text. |
| **`clickText`** | `clickText <text>` | Clicks an element matching exact or substring visible text. |
| **`clickRole`** | `clickRole <role> [name]` | Clicks accessible element by role and name (e.g., `clickRole button "Submit Order"`). |
| **`fill`** | `fill <selector> <value>` | Fills an input or textarea matching selector. |
| **`fillLabel`** | `fillLabel <label> <value>` | Fills an input with matching label or placeholder. |
| **`select`** | `select <selector> <value>` | Selects option in dropdown element. |
| **`check`** | `check <selector>` | Checks a checkbox or radio button. |
| **`uncheck`** | `uncheck <selector>` | Unchecks a checkbox. |
| **`press`** | `press <selector> <key>` | Presses a key (e.g., `Enter`, `Escape`, `Tab`). |
| **`waitFor`** | `waitFor <selector>` | Waits for element visibility (default 10s timeout). |
| **`wait`** | `wait [ms]` | Pauses execution for specified milliseconds (default 1000ms). |
| **`viewport`** | `viewport <preset/width> [height]` | Resizes viewport to `mobile` (390px), `tablet` (768px), `desktop` (1440px), `1920`, etc. |
| **`screenshot`** | `screenshot [name]` | Captures viewport screenshot to `artifacts/browser/interactive/screenshots/`. |
| **`newTab`** | `newTab [url]` | Opens a new tab within the same browser context and switches to it. |
| **`switchTab`** | `switchTab <index>` | Switches active tab focus by 0-indexed position. |
| **`closeTab`** | `closeTab [index]` | Closes specified tab index or the currently active tab. |
| **`tabs` / `listTabs`** | `tabs` | Lists all open tabs, titles, URLs, and active status. |
| **`reload`** | `reload` | Reloads current page and waits for DOM ready. |
| **`back` / `forward`**| `back` / `forward` | Navigates browser history. |
| **`status`** | `status` | Displays running status, active URL, title, environment, and open tabs. |
| **`diagnostics`** | `diagnostics` | Prints captured console errors, console logs, and failed network requests. |
| **`stop`** | `stop` | Closes the headed Chrome browser window and terminates server daemon. |

---

## 5. Viewport Control Matrix

The runner natively supports the project standard responsive viewports defined in `tests/browser/viewports.ts`:

- **Mobile Presets:**
  - `mobile_s` (320 x 568)
  - `mobile_m` (375 x 667)
  - `mobile` / `mobile_standard` (390 x 844)
  - `mobile_l` (430 x 932)
- **Tablet Presets:**
  - `small_tablet` (640 x 800)
  - `tablet` (768 x 1024)
  - `tablet_l` (820 x 1180)
- **Desktop Presets:**
  - `desktop_sm` (1024 x 768)
  - `desktop_md` (1280 x 800)
  - `desktop` (1440 x 900)
  - `desktop_fhd` (1920 x 1080)

Example usage:
```bash
npm run browser:qa -- viewport mobile
npm run browser:qa -- screenshot mobile_login_layout
npm run browser:qa -- viewport desktop
```

---

## 6. Authentication & Roles

Authentication leverages `tests/browser/helpers/auth.ts` and operates exclusively through the real UI login flow:
- Validates CSRF tokens.
- Fills email and password.
- If privileged accounts require Multi-Factor Authentication (MFA), it generates TOTP codes via `tests/browser/helpers/totp.ts` and submits the challenge.
- Maintains session cookies across all tabs in the browser context.

Supported QA Roles:
1. `SUPER_ADMIN`
2. `ADMIN`
3. `ACCOUNTANT`
4. `SALESMAN`
5. `WAREHOUSE_MANAGER`
6. `DELIVERY_PARTNER`

---

## 7. Diagnostics & Failure Capture

Every tab automatically attaches event listeners to capture:
- **Console Errors & Warnings:** Filtered and tracked continuously.
- **Uncaught Page Exceptions:** Recorded with error stack traces.
- **Failed HTTP Requests:** 4xx and 5xx responses with request method and status code.

To view live diagnostics at any time:
```bash
npm run browser:interactive:diagnostics
```

All failure screenshots and trace files are saved to `artifacts/browser/interactive/` which is strictly git-ignored.

---

## 8. Configuration & Environment Overrides

| Variable | Default | Purpose |
|---|---|---|
| `PLAYWRIGHT_BASE_URL` | `http://localhost:8000` | Application root URL. Set to pre-production or staging as needed. |
| `PLAYWRIGHT_BROWSER_PATH` | *Auto-detected* | Explicit path to a local Chrome or Chromium binary. |
| `PLAYWRIGHT_QA_PORT` | `4444` | Port for the local background daemon server. |
| `BROWSER_RECORD_VIDEO` | `false` | When set to `true`, records video to artifacts directory. |
| `BROWSER_TRACE` | `false` | When set to `true`, saves Playwright trace files. |

---

## 9. Security & Safety Guidelines

1. **Zero Destructive Automation:** The interactive QA runner will never automatically truncate tables, drop databases, delete users, or reverse financial journals without explicit instructions.
2. **Credential Redaction:** Passwords, TOTP seeds, authorization headers, and AWS credentials are never logged or stored in diagnostic files.
3. **Dedicated Profile Safety:** Chrome runs with an isolated temporary user directory; your personal Chrome cookies, extensions, and bookmarks are untouched.
4. **Environment Display:** The runner displays the target environment (`LOCAL`, `PRE-PRODUCTION`, `CUSTOM`) prominently in the banner to prevent unintended actions on live systems.
