# Second-Monitor Real-Chrome Agent Testing Environment

## Unique Distributors — Wholesale Distribution Management System

**Document Version:** 1.0  
**Effective Date:** September 2026  
**Target Environment:** Local Windows Workstation + Multi-Monitor Setup + Google Chrome + Antigravity AI Agent

---

## 1. Executive Summary

This architecture establishes a **dedicated, visible, user-accessible Google Chrome testing window on a second monitor** that Antigravity directly controls and inspects via **Chrome DevTools MCP** and **Unique Distributors Browser MCP** over a shared Chrome DevTools Protocol (CDP) session on `127.0.0.1:9222`.

### Core Capabilities:
- **One Visible Chrome Window:** A normal OS desktop Chrome window runs on your display (or Monitor 2).
- **Conversational Control:** Tell Antigravity *"Open the login page"*, *"Login as Admin"*, *"Open Accounts Receivable"*, *"Switch to mobile"*, *"Take a screenshot"*.
- **Seamless Manual Takeover:** You can freely click, type, scroll, or test in Chrome manually at any time. When you say *"Continue from current page"*, Antigravity inspects the live page and resumes without losing state.
- **Isolated Profile:** Uses `<project-root>/artifacts/browser/qa-profile/` to completely isolate QA sessions from personal browsing history and passwords.
- **Dual-Layer Coexistence:** Official Google `chrome-devtools-mcp` provides granular DOM snapshots (`take_snapshot`) and deep DevTools diagnostics, while `unique-distributors-browser` handles high-level project tasks (automated MFA login, viewport matrix, destructive action gates) over the exact same browser instance.
- **Zero CDN Dependencies:** Standalone Playwright test suites continue to resolve local Chrome via `tests/browser/resolver.ts` with zero external driver downloads.

---

## 2. System Architecture

```text
┌──────────────────────────────────────────────────────────────────────────────────────────────────┐
│                                   USER CONVERSATIONAL LAYER                                      │
│  "Open Sales Order" ──> [Antigravity Agent] <── "I manually selected Apex Supermarket. Continue."│
└─────────────────────────────────────────┬────────────────────────────────────────────────────────┘
                                          │
                  ┌───────────────────────┴───────────────────────┐
                  ▼                                               ▼
   ┌─────────────────────────────┐                 ┌─────────────────────────────┐
   │    Primary Interactive:     │                 │   Project Domain Actions:   │
   │      Chrome DevTools        │                 │ Unique Distributors Browser │
   │      MCP (Port 9222)        │                 │    MCP (High-Level Tools)   │
   │  • take_snapshot (a11y)     │                 │  • browser_login (TOTP auto)│
   │  • click / fill / type_text │                 │  • browser_viewport matrix  │
   │  • list_console_messages    │                 │  • browser_diagnostics      │
   │  • list_network_requests    │                 │  • confirm_destructive_act  │
   │  • lighthouse / heap trace  │                 │  • connectOverCDP (9222)    │
   └──────────────┬──────────────┘                 └──────────────┬──────────────┘
                  │                                               │
                  └───────────────────────┬───────────────────────┘
                                          ▼
                      ┌───────────────────────────────────────┐
                      │    CHROME DEVTOOLS PROTOCOL (CDP)     │
                      │       ws://127.0.0.1:9222/devtools    │
                      └───────────────────┬───────────────────┘
                                          ▼
   ┌──────────────────────────────────────────────────────────────────────────────────────────────┐
   │                          DEDICATED QA CHROME INSTANCE (OS WINDOW)                            │
   │  • Executable: C:\Program Files\Google\Chrome\Application\chrome.exe                         │
   │  • Profile: artifacts/browser/qa-profile/ (Isolated, persistent, non-personal)               │
   │  • Positioning: Monitor 2 (Auto-detected secondary screen bounds or primary offset)          │
   │  • Security: Bound strictly to 127.0.0.1 (Loopback only)                                     │
   │  • Target: http://localhost:8000 (Local Laravel Backend + React Inertia Frontend)            │
   └──────────────────────────────────────────────────────────────────────────────────────────────┘
```

---

## 3. Quick Start Guide

### Step 1: Launch the Visible Chrome Window
Run the dedicated launcher from your terminal:
```powershell
npm run browser:open
```
- Chrome automatically opens on your secondary monitor (or primary screen with an offset).
- The remote debugging endpoint becomes available at `http://127.0.0.1:9222`.
- The isolated profile is initialized under `artifacts/browser/qa-profile/`.

### Step 2: Interact with Antigravity Naturally
You do NOT need to type manual CLI commands. Simply converse with Antigravity:

- **Navigate:** *"Open the login page."*
- **Authenticate:** *"Login as Admin."* or *"Login as Salesman."*
- **Explore:** *"Navigate to Accounts Receivable."*
- **Responsive Viewport:** *"Switch to mobile."* (or *"Switch to tablet."* / *"Switch to 1440px desktop."*)
- **Capture Evidence:** *"Take a screenshot of this view."*
- **Check Diagnostics:** *"Check if there are any console or network errors."*
- **Manual Takeover:** *(Interact manually with the Chrome window)* $\rightarrow$ *"I manually approved order #1042. Continue from here."*

---

## 4. Second-Monitor Topology Detection

The launcher detects your display topology via PowerShell (`tests/browser/interactive/detect-monitors.ps1`):
1. **Multi-Monitor Setup:** Automatically discovers secondary display coordinates (`Bounds.X`, `Bounds.Y`) and places the Chrome window with a sensible 40px margin.
2. **Single-Monitor Setup:** Uses safe default positioning (`X: 40`, `Y: 40`, `1440x800`) without crashing.
3. **Custom Coordinate Overrides:** You can pin exact coordinates via environment variables:
   - `BROWSER_WINDOW_X=1920`
   - `BROWSER_WINDOW_Y=0`
   - `BROWSER_WINDOW_WIDTH=1440`
   - `BROWSER_WINDOW_HEIGHT=900`
   - `CHROME_DEBUG_PORT=9222`

---

## 5. Manual User Takeover Workflow

The system is designed for frictionless human-in-the-loop pair testing:

```text
1. Antigravity executes actions in visible Chrome (e.g. types an order).
2. Developer manually clicks a custom button, opens devtools, or fills edge-case inputs.
3. Developer tells Antigravity: "I navigated to /admin/payments. Continue from here."
4. Antigravity invokes `take_snapshot` (or `browser_observe`).
5. Antigravity reads the live accessibility tree from the current page and proceeds.
```

---

## 6. MCP Configuration

The workspace [`.agents/mcp_config.json`](file:///.agents/mcp_config.json) registers both servers:

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

---

## 7. Security & Guardrails

1. **Loopback Only:** CDP port `9222` binds exclusively to `127.0.0.1`. Never exposed to external networks or LAN.
2. **Profile Isolation:** QA sessions run in `artifacts/browser/qa-profile/`, completely segregated from personal accounts, saved passwords, and browsing history.
3. **Credential Redaction:** Passwords, TOTP seeds, auth cookies, `Authorization: Bearer` tokens, and S3 signatures are automatically redacted from agent responses.
4. **Domain Allowlist:** Navigation is restricted to `localhost`, `127.0.0.1`, and explicit staging environments.

---

## 8. Verification & Testing

| Command | Purpose |
| :--- | :--- |
| `npm run browser:open` | Launches dedicated visible Chrome window on port 9222 |
| `npm run test:mcp` | Runs 47-point integration test suite for `unique-distributors-browser` |
| `npx playwright test tests/browser/interactive/second-monitor.spec.ts` | Validates second-monitor topology, CDP health, shared state, and security |
