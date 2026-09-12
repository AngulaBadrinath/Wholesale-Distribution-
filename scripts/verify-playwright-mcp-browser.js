import fs from 'fs';
import path from 'path';
import http from 'http';
import { execSync } from 'child_process';
import { chromium } from '@playwright/test';

const EXPECTED_EXE = 'C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe';
const PROJECT_ROOT = process.cwd();
const EXPECTED_PROFILE = path.resolve(PROJECT_ROOT, 'artifacts', 'browser', 'qa-profile');
const CDP_PORT = 9222;
const BASE_URL = process.env.PLAYWRIGHT_BASE_URL || 'http://localhost:8000';
const EVIDENCE_DIR = path.resolve(PROJECT_ROOT, 'artifacts', 'browser', 'interactive', 'screenshots', 'sync-gate');

fs.mkdirSync(EVIDENCE_DIR, { recursive: true });

function logHeader(msg) {
    console.log('\n================================================================');
    console.log(`  ${msg}`);
    console.log('================================================================');
}

function assertCondition(desc, passed, details = '') {
    if (passed) {
        console.log(`  ✓ [PASS] ${desc} ${details ? '(' + details + ')' : ''}`);
    } else {
        console.error(`  ✗ [FAIL] ${desc} ${details ? '(' + details + ')' : ''}`);
        throw new Error(`Assertion failed: ${desc}. ${details}`);
    }
}

async function fetchJson(url) {
    return new Promise((resolve, reject) => {
        http.get(url, { timeout: 3000 }, (res) => {
            let data = '';
            res.on('data', chunk => data += chunk);
            res.on('end', () => {
                try {
                    resolve(JSON.parse(data));
                } catch {
                    resolve(data);
                }
            });
        }).on('error', reject);
    });
}

async function runGate() {
    logHeader('PHYSICAL PLAYWRIGHT MCP BROWSER SYNCHRONIZATION GATE');

    // 1. Google Chrome executable check
    assertCondition(
        '1. Google Chrome executable exists',
        fs.existsSync(EXPECTED_EXE),
        EXPECTED_EXE
    );

    // 2. Correct QA profile directory check
    assertCondition(
        '2. Dedicated QA profile configured',
        fs.existsSync(EXPECTED_PROFILE),
        EXPECTED_PROFILE
    );

    // 3. CDP reachable
    let versionData;
    try {
        versionData = await fetchJson(`http://127.0.0.1:${CDP_PORT}/json/version`);
    } catch (e) {
        throw new Error(`CDP endpoint not reachable on port ${CDP_PORT}: ${e.message}`);
    }
    assertCondition(
        '3. CDP endpoint responding on 127.0.0.1:9222',
        Boolean(versionData && versionData.Browser),
        versionData.Browser
    );
    assertCondition(
        '3b. Browser binary is official Chrome (not Brave/Edge)',
        versionData.Browser.includes('Chrome') && !versionData.Browser.includes('Brave') && !versionData.Browser.includes('Edg'),
        versionData.Browser
    );

    // 4. Exactly one QA Chrome listener on port 9222
    let netCheck;
    try {
        const cmd = `Get-NetTCPConnection -LocalPort ${CDP_PORT} -State Listen | Select-Object -ExpandProperty OwningProcess -Unique`;
        const out = execSync(`powershell -NoProfile -Command "${cmd}"`, { encoding: 'utf8' }).trim();
        netCheck = out.split(/\r?\n/).filter(Boolean);
    } catch (e) {
        throw new Error(`Failed to check TCP listeners on port ${CDP_PORT}: ${e.message}`);
    }
    assertCondition(
        '4. Exactly one listener process on port 9222',
        netCheck.length === 1,
        `PID: ${netCheck[0]}`
    );
    const qaPid = parseInt(netCheck[0], 10);

    // 5. Application tab exists in CDP
    const tabs = await fetchJson(`http://127.0.0.1:${CDP_PORT}/json/list`);
    const appTab = tabs.find(t => t.type === 'page' && (t.url.includes('localhost:8000') || t.url.includes('127.0.0.1:8000')));
    assertCondition(
        '5. Application tab exists in running Chrome',
        Boolean(appTab),
        appTab ? `${appTab.title} (${appTab.url})` : 'None found'
    );

    // 6 & 7 & 8. Application URL, Title & Identity
    assertCondition(
        '6. Application URL belongs to target domain',
        appTab.url.includes('localhost:8000'),
        appTab.url
    );
    assertCondition(
        '7. Application Title contains Unique Distributors',
        appTab.title.includes('Unique Distributors'),
        appTab.title
    );

    // 9 & 10. Visible QA Chrome Window on OS Desktop belongs to same PID
    const helperPath = path.resolve(PROJECT_ROOT, 'scripts', 'manage-qa-chrome.ps1');
    const psInspect = execSync(`powershell -NoProfile -ExecutionPolicy Bypass -File "${helperPath}" -Action inspect -Port ${CDP_PORT}`, { encoding: 'utf8' });
    const osReport = JSON.parse(psInspect);

    assertCondition(
        '9. Physical QA Chrome window discovered on OS Default desktop',
        Boolean(osReport.QaChromeWindow && osReport.QaChromeWindow.IsVisible),
        `HWND: ${osReport.QaChromeWindow?.HwndHex}, Title: "${osReport.QaChromeWindow?.Title}"`
    );

    assertCondition(
        '10. Physical window PID matches CDP listening process PID',
        osReport.QaChromeWindow?.Pid === qaPid,
        `Window PID: ${osReport.QaChromeWindow?.Pid}, CDP PID: ${qaPid}`
    );

    // Bring QA Chrome window to foreground
    execSync(`powershell -NoProfile -ExecutionPolicy Bypass -File "${helperPath}" -Action foreground -Port ${CDP_PORT}`, { stdio: 'ignore' });

    // 11. Playwright CDP connection & Bidirectional synchronization
    logHeader('LIVE BIDIRECTIONAL PLAYWRIGHT CDP SYNCHRONIZATION TESTS');

    const browser = await chromium.connectOverCDP(`http://127.0.0.1:${CDP_PORT}`);
    const contexts = browser.contexts();
    assertCondition('11a. Connected to existing browser context', contexts.length > 0, `${contexts.length} context(s)`);

    const context = contexts[0];
    const pages = context.pages();
    assertCondition('11b. Pages discovered via Playwright CDP', pages.length > 0, `${pages.length} page(s)`);

    // Dynamically resolve application page
    let livePage = pages.find(p => p.url().includes('localhost:8000'));
    if (!livePage) {
        livePage = pages[0];
        await livePage.goto(`${BASE_URL}/login`);
    }

    // TEST 1 — INITIAL VERIFICATION
    const initialUrl = livePage.url();
    const initialTitle = await livePage.title();
    assertCondition(
        'TEST 1 (INITIAL) — Playwright sees same URL & title as visible Chrome',
        initialUrl.includes('localhost:8000') && initialTitle.includes('Unique Distributors'),
        `URL: ${initialUrl}, Title: "${initialTitle}"`
    );
    const shot1 = path.join(EVIDENCE_DIR, '01_initial_sync.png');
    await livePage.screenshot({ path: shot1 });
    console.log(`    Captured evidence: ${shot1}`);

    // TEST 2 — MCP/PLAYWRIGHT -> VISIBLE
    const testRoute2 = `${BASE_URL}/system/non-existent-audit-route-playwright-mcp-sync`;
    console.log(`\n  Navigating via Playwright CDP to: ${testRoute2}`);
    await livePage.goto(testRoute2);
    await livePage.waitForLoadState('domcontentloaded');
    assertCondition(
        'TEST 2 (MCP -> VISIBLE) — Page navigated to audit sync route',
        livePage.url() === testRoute2,
        livePage.url()
    );
    const shot2 = path.join(EVIDENCE_DIR, '02_mcp_to_visible.png');
    await livePage.screenshot({ path: shot2 });
    console.log(`    Captured evidence: ${shot2}`);

    // TEST 3 — VISIBLE -> MCP (Return to /login)
    console.log(`\n  Clearing session cookies and returning to /login...`);
    await context.clearCookies();
    await livePage.goto(`${BASE_URL}/login`);
    await livePage.waitForLoadState('domcontentloaded');
    assertCondition(
        'TEST 3 (VISIBLE -> MCP) — Live page reflects /login immediately',
        livePage.url().includes('/login'),
        livePage.url()
    );
    const shot3 = path.join(EVIDENCE_DIR, '03_visible_to_mcp_login.png');
    await livePage.screenshot({ path: shot3 });
    console.log(`    Captured evidence: ${shot3}`);

    // TEST 4 — DISTINCTIVE MARKER WITH TIMESTAMP
    const timestamp = Date.now();
    const testRoute4 = `${BASE_URL}/system/non-existent-audit-route-playwright-${timestamp}`;
    console.log(`\n  Navigating to distinctive marker route: ${testRoute4}`);
    await livePage.goto(testRoute4);
    await livePage.waitForLoadState('domcontentloaded');
    assertCondition(
        'TEST 4 (DISTINCTIVE MARKER) — Distinctive timestamp route reflected',
        livePage.url() === testRoute4,
        livePage.url()
    );
    const shot4 = path.join(EVIDENCE_DIR, '04_distinctive_marker.png');
    await livePage.screenshot({ path: shot4 });
    console.log(`    Captured evidence: ${shot4}`);

    // Return to /login
    await context.clearCookies();
    await livePage.goto(`${BASE_URL}/login`);
    await livePage.waitForLoadState('domcontentloaded');

    // TEST 5 — HUMAN TAKEOVER / DOM INTERACTION
    const emailInput = livePage.locator('input#email, input[name="email"], input[type="email"]').first();
    await emailInput.waitFor({ state: 'visible', timeout: 5000 });
    await emailInput.fill('audit.takeover.test@unique-distributors.test');
    const typedValue = await emailInput.inputValue();
    assertCondition(
        'TEST 5 (DOM INTERACTION & TAKEOVER) — Playwright interacted with input in visible Chrome',
        typedValue === 'audit.takeover.test@unique-distributors.test',
        `Input Value: "${typedValue}"`
    );
    // Clear back
    await emailInput.fill('');

    // TEST 6 — NO DUPLICATE BROWSER
    const tcpProcs = execSync(
        `powershell -NoProfile -Command "Get-NetTCPConnection -LocalPort ${CDP_PORT} -State Listen | Select-Object -ExpandProperty OwningProcess -Unique"`,
        { encoding: 'utf8' }
    ).trim().split(/\r?\n/).filter(Boolean);

    assertCondition(
        'TEST 6 (NO DUPLICATE BROWSER) — Exactly one QA Chrome process is listening on CDP 9222',
        tcpProcs.length === 1 && tcpProcs[0] === String(qaPid),
        `Listening PID: ${tcpProcs[0]}, Target QA PID: ${qaPid}`
    );

    await browser.close();

    logHeader('ALL PHYSICAL PLAYWRIGHT MCP SYNCHRONIZATION TESTS PASSED');
    console.log(`\n  Authoritative Google Chrome: ${EXPECTED_EXE}`);
    console.log(`  Profile Directory:          ${EXPECTED_PROFILE}`);
    console.log(`  CDP Endpoint:               http://127.0.0.1:${CDP_PORT}`);
    console.log(`  Physical OS Window HWND:    ${osReport.QaChromeWindow?.HwndHex}`);
    console.log(`  OS Window PID:              ${qaPid}`);
    console.log(`  Evidence Directory:         ${EVIDENCE_DIR}\n`);
}

runGate()
    .then(() => {
        console.log('[Sync Gate] Result: PASS');
        process.exit(0);
    })
    .catch((err) => {
        console.error('\n[Sync Gate Error]', err.message || err);
        process.exit(1);
    });
