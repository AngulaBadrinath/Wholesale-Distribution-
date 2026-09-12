# Browser Verification Guide

**Document Version:** 1.0  
**Effective Date:** September 2026  
**Audience:** Developers, QA Engineers, CI/CD Automations, Antigravity AI Agent  
**Scope:** Reusable local Playwright verification using real installed Chrome / Chromium browsers without CDN downloads.

---

## 1. Architecture & Strategy

The browser verification infrastructure in this repository is designed around **zero CDN browser binary dependencies**:
1. It **NEVER** calls or requires `npx playwright install` or Playwright CDN browser binary downloads.
2. It detects and attaches directly to an existing, real locally installed **Google Chrome**, **Chromium**, or **Microsoft Edge** browser executable.
3. It provides deterministic viewport presets, authenticated role fixtures, network/console error monitoring, and failure artifacts.

---

## 2. Browser Discovery Priority & Contract

The harness resolves the browser executable in the following strict order:

```text
Priority 1: PLAYWRIGHT_BROWSER_PATH environment variable (Manual override / CI)
    ↓
Priority 2: Auto-discovery in OS-standard installation directories:
    - Windows: Chrome, Edge, Chromium (Program Files, LocalAppData)
    - macOS: Google Chrome.app, Chromium.app, Microsoft Edge.app (/Applications)
    - Linux: /usr/bin/google-chrome, /usr/bin/chromium-browser, etc.
    ↓
Priority 3: Actionable Error (Clear instructions on installing Chrome or setting PLAYWRIGHT_BROWSER_PATH)
```

### Environment Variables

| Variable | Required | Default | Description |
|---|---|---|---|
| `PLAYWRIGHT_BROWSER_PATH` | Optional | *Auto-detected* | Absolute file path to a Chrome/Chromium/Edge executable. |
| `PLAYWRIGHT_BASE_URL` | Optional | `http://localhost:8000` | Target URL of the application server. |
| `PLAYWRIGHT_HEADED` | Optional | `false` | Set to `true` or `1` to run Playwright in headed GUI mode. |
| `PLAYWRIGHT_SLOWMO` | Optional | `0` | Milliseconds to delay each step (useful for visual inspection). |

---

## 3. Available npm Commands

| Command | Description |
|---|---|
| `npm run browser:check` | Inspects system, verifies browser resolution, prints executable path & version. |
| `npm run browser:open` | Launches dedicated visible Chrome QA window with remote debugging on port 9222. |
| `npm run browser:verify` | Executes the complete Playwright browser test suite (`tests/browser/setup.spec.ts`). |
| `npm run browser:headed` | Launches a live Chromium browser window connected to `PLAYWRIGHT_BASE_URL`. |
| `npm run browser:visual` | Captures high-fidelity rendered screenshots across responsive breakpoints into `artifacts/browser/visual/`. |
| `npm run browser:interactive` | Starts the persistent, interactive headed QA browser session with REPL and daemon. |
| `npm run browser:qa -- <cmd>` | Executes a single interactive QA action (e.g. `navigate`, `login`, `screenshot`, `diagnostics`). |

For full second-monitor agent testing documentation, see [SECOND_MONITOR_BROWSER_SETUP.md](file:///f:/Wholesale%20Distribution%20Management%20System/docs/SECOND_MONITOR_BROWSER_SETUP.md) and [ANTIGRAVITY_BROWSER_MCP.md](file:///f:/Wholesale%20Distribution%20Management%20System/docs/ANTIGRAVITY_BROWSER_MCP.md).

---

## 4. Responsive Viewport Matrix

The harness supports standard B2B/ERP viewport breakpoints configured in `tests/browser/viewports.ts`:

| Preset Key | Width x Height | Category / Target Device |
|---|---|---|
| `mobile_s` | `320 x 568` | Small Mobile (iPhone SE 1st gen) |
| `mobile_m` | `375 x 667` | Standard Mobile (iPhone 8) |
| `mobile` | `390 x 844` | Modern Mobile (iPhone 13/14) |
| `mobile_l` | `430 x 932` | Large Mobile (iPhone 14 Pro Max) |
| `mobile_wide`| `640 x 900` | Foldable / Large Mobile Landscape |
| `tablet` | `768 x 1024` | Tablet Portrait (iPad Mini/Air) |
| `tablet_l` | `820 x 1180` | Large Tablet Portrait (iPad Pro 11") |
| `desktop_sm` | `1024 x 768` | Small Desktop / Tablet Landscape |
| `desktop_md` | `1280 x 800` | Medium Desktop / Standard Laptop |
| `desktop` | `1440 x 900` | High-Res Desktop / MacBook Pro |
| `desktop_fhd`| `1920 x 1080`| Full HD Large Display |

---

## 5. Authenticated Role Helpers

The authentication helper (`tests/browser/helpers/auth.ts`) logs in through the real login interface and handles multi-factor authentication (MFA) seamlessly for privileged roles:

```typescript
import { test } from '@playwright/test';
import { loginAs, logout } from './helpers/auth';

test('Admin can access receivables', async ({ page }) => {
    // Authenticate as Admin (handles MFA automatically if prompted)
    await loginAs(page, 'ADMIN');
    
    await page.goto('/admin/receivables');
    // ... test expectations
});
```

### Supported QA Roles

- `SUPER_ADMIN` (`superadmin.qa@example.test`)
- `ADMIN` (`admin.qa@example.test`)
- `ACCOUNTANT` (`accountant.qa@example.test`)
- `SALESMAN` (`salesman.a@example.test`)
- `WAREHOUSE_MANAGER` (`warehouse.qa@example.test`)
- `DELIVERY_PARTNER` (`driver.qa@example.test`)

---

## 6. Diagnostics & Failure Artifacts

Every test execution automatically collects diagnostics:
- **Console Errors & Warnings:** Captured and accessible via `DiagnosticsCollector`.
- **Network Failures:** HTTP 4xx/5xx responses and request failures captured.
- **Screenshots on Failure:** Saved to `artifacts/browser/test-results/`.
- **Traces:** Zip traces recorded for inspection via `npx playwright show-trace <path-to-trace.zip>`.
- **Dedicated Artifacts Directory:** All generated test outputs reside in `artifacts/browser/` (configured in `.gitignore`).

---

## 7. CI / GitHub Actions Configuration

In CI environments, ensure Chrome or Chromium is installed on the runner and specify `PLAYWRIGHT_BROWSER_PATH`:

```yaml
# Example GitHub Actions step:
- name: Run Real-Browser Verification
  env:
    PLAYWRIGHT_BROWSER_PATH: /usr/bin/google-chrome
    PLAYWRIGHT_BASE_URL: http://127.0.0.1:8000
  run: |
    npm run browser:check
    npm run browser:verify
```

---

## 8. Troubleshooting

### "No supported browser found on system"
1. Verify Google Chrome, Chromium, or Microsoft Edge is installed.
2. If installed in a non-standard location, set the environment variable:
   - **Windows (PowerShell):** `$env:PLAYWRIGHT_BROWSER_PATH="C:\CustomPath\chrome.exe"`
   - **macOS / Linux:** `export PLAYWRIGHT_BROWSER_PATH="/custom/path/to/chrome"`
3. Run `npm run browser:check` to confirm detection.
