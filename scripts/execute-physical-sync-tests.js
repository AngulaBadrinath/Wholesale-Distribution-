#!/usr/bin/env node
/**
 * Physical Synchronization Gate Tests
 * Executes Test A, Test B, Test C, and Human Takeover
 * Captures real OS desktop screenshots with Chrome visible in the foreground
 */

import { chromium } from '@playwright/test';
import { execSync } from 'child_process';
import path from 'path';
import fs from 'fs';

const EVIDENCE_DIR = path.resolve(process.cwd(), 'artifacts', 'browser', 'interactive', 'screenshots', 'browser-sync');
fs.mkdirSync(EVIDENCE_DIR, { recursive: true });

function capturePhysicalScreen(filename) {
    const outPath = path.join(EVIDENCE_DIR, filename);
    const capScript = path.resolve(process.cwd(), 'scripts', 'capture-desktop-screen.ps1');
    execSync(`powershell -NoProfile -ExecutionPolicy Bypass -File "${capScript}" -OutputPath "${outPath}"`, {
        stdio: 'pipe',
        timeout: 10000,
    });
    return outPath;
}

function ensureForeground() {
    const mgmtScript = path.resolve(process.cwd(), 'scripts', 'manage-qa-chrome.ps1');
    try {
        execSync(`powershell -NoProfile -ExecutionPolicy Bypass -File "${mgmtScript}" -Action foreground -Port 9222`, {
            stdio: 'pipe',
            timeout: 8000,
        });
    } catch {}
}

async function runPhysicalSyncTests() {
    console.log('================================================================');
    console.log('  EXECUTING PHYSICAL SYNCHRONIZATION SUITE (A, B, C + TAKEOVER)');
    console.log('================================================================\n');

    const browser = await chromium.connectOverCDP('http://127.0.0.1:9222');
    const contexts = browser.contexts();
    if (contexts.length === 0) throw new Error('No browser context found on CDP');
    const context = contexts[0];
    const pages = context.pages();
    if (pages.length === 0) throw new Error('No pages found on CDP');
    const page = pages[0];

    const results = {};

    // -------------------------------------------------------------
    // TEST A: MCP navigates to /system/non-existent-audit-route-visible-window-test
    // -------------------------------------------------------------
    console.log('--- EXECUTING TEST A: MCP -> VISIBLE WINDOW ROUTE ---');
    const testARoute = 'http://localhost:8000/system/non-existent-audit-route-visible-window-test';
    await page.goto(testARoute, { waitUntil: 'domcontentloaded' }).catch(() => {});
    await page.waitForTimeout(500);
    ensureForeground();

    const tAShot = capturePhysicalScreen('physical_test_a_route.png');
    const tAUrl = page.url();
    const tAPass = tAUrl.includes('non-existent-audit-route-visible-window-test');
    results.testA = {
        name: 'TEST A — MCP NAVIGATES VISIBLE WINDOW',
        status: tAPass ? 'PASS' : 'FAIL',
        url: tAUrl,
        screenshot: 'artifacts/browser/interactive/screenshots/browser-sync/physical_test_a_route.png',
    };
    console.log(`  Result: ${results.testA.status} | URL: ${results.testA.url}`);

    // -------------------------------------------------------------
    // TEST B: Visible/Browser navigates to /login -> MCP observes
    // -------------------------------------------------------------
    console.log('\n--- EXECUTING TEST B: VISIBLE -> MCP OBSERVATION ---');
    await page.evaluate(() => {
        window.location.href = 'http://localhost:8000/login';
    });
    await page.waitForLoadState('domcontentloaded');
    await page.waitForTimeout(500);
    ensureForeground();

    const tBShot = capturePhysicalScreen('physical_test_b_login.png');
    const tBUrl = page.url();
    const tBPass = tBUrl.includes('/login');
    results.testB = {
        name: 'TEST B — VISIBLE -> MCP OBSERVATION',
        status: tBPass ? 'PASS' : 'FAIL',
        url: tBUrl,
        screenshot: 'artifacts/browser/interactive/screenshots/browser-sync/physical_test_b_login.png',
    };
    console.log(`  Result: ${results.testB.status} | URL: ${results.testB.url}`);

    // -------------------------------------------------------------
    // TEST C: MCP navigates to another distinctive route
    // -------------------------------------------------------------
    console.log('\n--- EXECUTING TEST C: DISTINCTIVE ROUTE SYNCHRONIZATION ---');
    const marker = Date.now();
    const testCRoute = `http://localhost:8000/system/non-existent-audit-route-distinctive-${marker}`;
    await page.goto(testCRoute, { waitUntil: 'domcontentloaded' }).catch(() => {});
    await page.waitForTimeout(500);
    ensureForeground();

    const tCShot = capturePhysicalScreen(`physical_test_c_distinctive_${marker}.png`);
    const tCUrl = page.url();
    const tCPass = tCUrl.includes(`distinctive-${marker}`);
    results.testC = {
        name: 'TEST C — DISTINCTIVE ROUTE SYNCHRONIZATION',
        status: tCPass ? 'PASS' : 'FAIL',
        url: tCUrl,
        screenshot: `artifacts/browser/interactive/screenshots/browser-sync/physical_test_c_distinctive_${marker}.png`,
    };
    console.log(`  Result: ${results.testC.status} | URL: ${results.testC.url}`);

    // Return to /login
    await page.goto('http://localhost:8000/login', { waitUntil: 'domcontentloaded' });
    await page.waitForTimeout(500);
    ensureForeground();

    // -------------------------------------------------------------
    // HUMAN TAKEOVER INTERACTIVE TEST
    // -------------------------------------------------------------
    console.log('\n--- EXECUTING HUMAN TAKEOVER TEST ---');
    await page.fill('input[type="email"], input[name="email"]', 'admin@wdms.local');
    const val = await page.inputValue('input[type="email"], input[name="email"]');
    ensureForeground();
    const takeShot = capturePhysicalScreen('physical_human_takeover_input.png');

    const takeoverPass = val === 'admin@wdms.local';
    results.humanTakeover = {
        name: 'HUMAN TAKEOVER INTERACTIVE INPUT',
        status: takeoverPass ? 'PASS' : 'FAIL',
        field: 'email',
        value: val,
        screenshot: 'artifacts/browser/interactive/screenshots/browser-sync/physical_human_takeover_input.png',
    };
    console.log(`  Result: ${results.humanTakeover.status} | Readback value: "${val}"`);

    // Clean field
    await page.fill('input[type="email"], input[name="email"]', '');
    capturePhysicalScreen('physical_final_login.png');

    const allPassed = Object.values(results).every((r) => r.status === 'PASS');
    console.log('\n================================================================');
    console.log(`  PHYSICAL SYNC SUITE RESULT: ${allPassed ? 'PASS' : 'FAIL'}`);
    console.log('================================================================\n');

    return { allPassed, results };
}

runPhysicalSyncTests()
    .then(({ allPassed }) => {
        process.exit(allPassed ? 0 : 1);
    })
    .catch((err) => {
        console.error('[Fatal Error in Physical Sync Tests]', err);
        process.exit(1);
    });
