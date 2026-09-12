import { chromium } from '@playwright/test';
import fs from 'fs';
import path from 'path';
import http from 'http';
import { execSync } from 'child_process';

const SYNC_EVIDENCE_DIR = path.resolve(process.cwd(), 'artifacts', 'browser', 'interactive', 'screenshots', 'browser-sync');
fs.mkdirSync(SYNC_EVIDENCE_DIR, { recursive: true });

function fetchJson(endpoint) {
    return new Promise((resolve, reject) => {
        const req = http.get(`http://127.0.0.1:9222${endpoint}`, { timeout: 3000 }, (res) => {
            let data = '';
            res.on('data', (c) => (data += c));
            res.on('end', () => resolve(JSON.parse(data)));
        });
        req.on('error', reject);
    });
}

async function runSyncGate() {
    console.log('================================================================');
    console.log('  STARTING BIDIRECTIONAL SYNCHRONIZATION GATE SUITE');
    console.log('  Evidence Directory: ' + SYNC_EVIDENCE_DIR);
    console.log('================================================================\n');

    const results = {};

    // 1. Connect to Chrome over CDP :9222
    const versionData = await fetchJson('/json/version');
    const browser = await chromium.connectOverCDP('http://127.0.0.1:9222');
    const contexts = browser.contexts();
    if (contexts.length === 0) throw new Error('No browser context found on CDP :9222');
    const context = contexts[0];
    const pages = context.pages();
    if (pages.length === 0) throw new Error('No active page found');
    const page = pages[0];

    // =========================================================================
    // TEST 1 — INITIAL IDENTITY
    // =========================================================================
    console.log('\n--- EXECUTING TEST 1: INITIAL IDENTITY ---');
    await page.goto('http://127.0.0.1:8000/login', { waitUntil: 'domcontentloaded' });
    const t1Url = page.url();
    const t1Title = await page.title();
    const t1Targets = await fetchJson('/json/list');
    const t1AppTarget = t1Targets.find((t) => t.type === 'page' && (t.url.includes(':8000') || t.url.includes('localhost')));
    const t1ScreenshotPath = path.join(SYNC_EVIDENCE_DIR, 'test1_initial_identity_login.png');
    await page.screenshot({ path: t1ScreenshotPath });

    const t1Pass = t1Url.includes('/login') && t1Title.includes('Unique Distributors') && !!t1AppTarget;
    results.test1 = {
        name: 'TEST 1 — INITIAL IDENTITY',
        status: t1Pass ? 'PASS' : 'FAIL',
        browser: versionData.Browser,
        targetId: t1AppTarget?.id,
        url: t1Url,
        title: t1Title,
        screenshot: 'artifacts/browser/interactive/screenshots/browser-sync/test1_initial_identity_login.png',
        timestamp: new Date().toISOString(),
    };
    console.log(`  Result: ${results.test1.status}`);
    console.log(`  Target ID: ${results.test1.targetId}, URL: ${results.test1.url}, Title: "${results.test1.title}"`);

    // =========================================================================
    // TEST 2 — MCP -> VISIBLE PAGE CHANGE (404)
    // =========================================================================
    console.log('\n--- EXECUTING TEST 2: MCP -> VISIBLE (404 ROUTE) ---');
    const route404 = 'http://127.0.0.1:8000/system/non-existent-audit-route-404';
    await page.goto(route404, { waitUntil: 'domcontentloaded' }).catch(() => {});
    await page.waitForTimeout(500);
    const t2Url = page.url();
    const t2Title = await page.title();
    const t2Targets = await fetchJson('/json/list');
    const t2AppTarget = t2Targets.find((t) => t.type === 'page');
    const t2ScreenshotPath = path.join(SYNC_EVIDENCE_DIR, 'test2_mcp_to_visible_404.png');
    await page.screenshot({ path: t2ScreenshotPath });

    const t2Pass = t2Url.includes('non-existent-audit-route-404') && t2AppTarget?.id === t1AppTarget?.id;
    results.test2 = {
        name: 'TEST 2 — MCP -> VISIBLE',
        status: t2Pass ? 'PASS' : 'FAIL',
        targetId: t2AppTarget?.id,
        url: t2Url,
        title: t2Title,
        screenshot: 'artifacts/browser/interactive/screenshots/browser-sync/test2_mcp_to_visible_404.png',
        timestamp: new Date().toISOString(),
    };
    console.log(`  Result: ${results.test2.status}`);
    console.log(`  Target ID: ${results.test2.targetId}, URL: ${results.test2.url}`);

    // =========================================================================
    // TEST 3 — VISIBLE -> MCP OBSERVATION
    // =========================================================================
    console.log('\n--- EXECUTING TEST 3: VISIBLE -> MCP OBSERVATION ---');
    await page.evaluate(() => {
        window.location.href = 'http://127.0.0.1:8000/login';
    });
    await page.waitForLoadState('domcontentloaded');
    await page.waitForTimeout(500);

    const t3Targets = await fetchJson('/json/list');
    const t3AppTarget = t3Targets.find((t) => t.type === 'page');
    const t3Url = page.url();
    const t3Title = await page.title();
    const t3ScreenshotPath = path.join(SYNC_EVIDENCE_DIR, 'test3_visible_to_mcp_login.png');
    await page.screenshot({ path: t3ScreenshotPath });

    const t3Pass = t3Url.includes('/login') && t3AppTarget?.url.includes('/login');
    results.test3 = {
        name: 'TEST 3 — VISIBLE -> MCP',
        status: t3Pass ? 'PASS' : 'FAIL',
        targetId: t3AppTarget?.id,
        url: t3Url,
        title: t3Title,
        screenshot: 'artifacts/browser/interactive/screenshots/browser-sync/test3_visible_to_mcp_login.png',
        timestamp: new Date().toISOString(),
    };
    console.log(`  Result: ${results.test3.status}`);
    console.log(`  Target ID: ${results.test3.targetId}, URL: ${results.test3.url}`);

    // =========================================================================
    // TEST 4 — DISTINCTIVE STATE MARKER
    // =========================================================================
    console.log('\n--- EXECUTING TEST 4: DISTINCTIVE STATE MARKER ---');
    const markerTimestamp = Date.now();
    const distinctiveRoute = `http://127.0.0.1:8000/system/non-existent-audit-route-sync-${markerTimestamp}`;
    await page.goto(distinctiveRoute, { waitUntil: 'domcontentloaded' }).catch(() => {});
    await page.waitForTimeout(500);

    const t4Targets = await fetchJson('/json/list');
    const t4AppTarget = t4Targets.find((t) => t.type === 'page');
    const t4Url = page.url();
    const t4ScreenshotPath = path.join(SYNC_EVIDENCE_DIR, `test4_distinctive_marker_${markerTimestamp}.png`);
    await page.screenshot({ path: t4ScreenshotPath });

    const t4PassMarker = t4Url.includes(`sync-${markerTimestamp}`) && t4AppTarget?.url.includes(`sync-${markerTimestamp}`);

    await page.goto('http://127.0.0.1:8000/login', { waitUntil: 'domcontentloaded' });
    const t4FinalUrl = page.url();
    const t4FinalScreenshot = path.join(SYNC_EVIDENCE_DIR, 'test4_final_login_reobservation.png');
    await page.screenshot({ path: t4FinalScreenshot });

    const t4Pass = t4PassMarker && t4FinalUrl.includes('/login');
    results.test4 = {
        name: 'TEST 4 — DISTINCTIVE STATE MARKER',
        status: t4Pass ? 'PASS' : 'FAIL',
        markerRoute: distinctiveRoute,
        targetId: t4AppTarget?.id,
        observedUrl: t4Url,
        finalUrl: t4FinalUrl,
        screenshotMarker: `artifacts/browser/interactive/screenshots/browser-sync/test4_distinctive_marker_${markerTimestamp}.png`,
        screenshotFinal: 'artifacts/browser/interactive/screenshots/browser-sync/test4_final_login_reobservation.png',
        timestamp: new Date().toISOString(),
    };
    console.log(`  Result: ${results.test4.status}`);
    console.log(`  Distinctive Marker: ${distinctiveRoute}`);
    console.log(`  Observed URL: ${results.test4.observedUrl} -> Final URL: ${results.test4.finalUrl}`);

    // =========================================================================
    // TEST 5 — PROCESS EXCLUSIVITY
    // =========================================================================
    console.log('\n--- EXECUTING TEST 5: PROCESS EXCLUSIVITY ---');
    let portProcs = [];
    try {
        const netstatOutput = execSync('netstat -ano | findstr ":9222.*LISTENING"', { timeout: 5000, encoding: 'utf8' }).trim();
        const lines = netstatOutput.split('\n').map((l) => l.trim()).filter(Boolean);
        for (const line of lines) {
            const parts = line.split(/\s+/);
            const pid = parts[parts.length - 1];
            if (pid && !isNaN(parseInt(pid, 10))) {
                const psCmd = `powershell -NoProfile -Command "(Get-CimInstance Win32_Process -Filter 'ProcessId = ${pid}').CommandLine"`;
                const cmdLine = execSync(psCmd, { timeout: 5000, encoding: 'utf8' }).trim();
                portProcs.push({
                    pid: parseInt(pid, 10),
                    name: 'chrome.exe',
                    commandLine: cmdLine,
                    hasQaProfile: cmdLine.includes('qa-profile'),
                    isGoogleChrome: cmdLine.includes('Google\\Chrome\\Application\\chrome.exe'),
                });
            }
        }
    } catch (e) {
        console.warn('Could not inspect via netstat/powershell:', e.message);
    }

    const hasQaProfile = portProcs.length > 0 && portProcs.every((p) => p.hasQaProfile && p.isGoogleChrome);
    const isSinglePort9222 = portProcs.length === 1;

    results.test5 = {
        name: 'TEST 5 — PROCESS EXCLUSIVITY',
        status: hasQaProfile && isSinglePort9222 ? 'PASS' : 'FAIL',
        listeningProcessesCount: portProcs.length,
        processes: portProcs,
        timestamp: new Date().toISOString(),
    };
    console.log(`  Result: ${results.test5.status}`);
    console.log(`  Port 9222 Listeners: ${portProcs.length}, Profile Verified: ${hasQaProfile}`);
    if (portProcs.length > 0) {
        console.log(`  PID: ${portProcs[0].pid}`);
        console.log(`  Command Line: ${portProcs[0].commandLine}`);
    }

    // =========================================================================
    // HUMAN TAKEOVER VERIFICATION
    // =========================================================================
    console.log('\n--- EXECUTING HUMAN TAKEOVER VERIFICATION ---');
    await page.fill('input[type="email"], input[name="email"]', 'admin@wdms.local');
    const emailVal = await page.inputValue('input[type="email"], input[name="email"]');
    const takeoverPass = emailVal === 'admin@wdms.local';
    const takeoverScreenshot = path.join(SYNC_EVIDENCE_DIR, 'human_takeover_interactive_input.png');
    await page.screenshot({ path: takeoverScreenshot });

    await page.fill('input[type="email"], input[name="email"]', '');

    results.humanTakeover = {
        name: 'HUMAN TAKEOVER TEST',
        status: takeoverPass ? 'PASS' : 'FAIL',
        testedInteraction: 'Fill and readback email input field on login form',
        screenshot: 'artifacts/browser/interactive/screenshots/browser-sync/human_takeover_interactive_input.png',
        timestamp: new Date().toISOString(),
    };
    console.log(`  Result: ${results.humanTakeover.status}`);

    // Output JSON Summary
    const summaryFile = path.join(SYNC_EVIDENCE_DIR, 'sync_gate_summary.json');
    fs.writeFileSync(summaryFile, JSON.stringify(results, null, 2), 'utf8');

    console.log('\n================================================================');
    console.log('  SYNCHRONIZATION GATE EXECUTION COMPLETE');
    console.log('  Summary written to: ' + summaryFile);
    console.log('================================================================\n');

    return results;
}

runSyncGate()
    .then((r) => {
        const allPass = Object.values(r).every((t) => t.status === 'PASS');
        if (!allPass) {
            console.error('[FAIL] Not all synchronization gate tests passed.');
            process.exit(1);
        }
        console.log('[SUCCESS] ALL 5 SYNCHRONIZATION TESTS + HUMAN TAKEOVER PASSED!');
        process.exit(0);
    })
    .catch((err) => {
        console.error('[FATAL ERROR IN SYNC GATE]', err);
        process.exit(1);
    });
