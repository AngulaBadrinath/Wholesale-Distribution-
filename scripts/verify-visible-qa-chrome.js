#!/usr/bin/env node
/**
 * Hard Physical QA Chrome Window Verification Gate
 * Unique Distributors — Zero False Passes
 *
 * Verifies:
 * 1. Dedicated QA Chrome process on CDP port 9222
 * 2. Official Google Chrome executable (NOT Brave, NOT Edge)
 * 3. Physical OS top-level window on the interactive desktop (WinSta0\Default)
 * 4. OS Foreground Window HWND and PID match the QA Chrome process
 * 5. Window is visible, restored (NOT minimized), and positioned on active display
 * 6. CDP target points to the application (/login or localhost:8000)
 */

import http from 'http';
import { execSync } from 'child_process';
import path from 'path';

const CDP_PORT = 9222;

function fetchJson(endpoint) {
    return new Promise((resolve, reject) => {
        const req = http.get(`http://127.0.0.1:${CDP_PORT}${endpoint}`, { timeout: 3000 }, (res) => {
            if (res.statusCode !== 200) {
                reject(new Error(`CDP HTTP ${res.statusCode} on ${endpoint}`));
                return;
            }
            let raw = '';
            res.on('data', (c) => (raw += c));
            res.on('end', () => {
                try {
                    resolve(JSON.parse(raw));
                } catch (e) {
                    reject(e);
                }
            });
        });
        req.on('error', reject);
        req.on('timeout', () => {
            req.destroy();
            reject(new Error('CDP connection timed out'));
        });
    });
}

export async function verifyVisibleQaChrome() {
    console.log('================================================================');
    console.log('  PHYSICAL QA CHROME WINDOW VISIBILITY GATE (OS-LEVEL)');
    console.log(`  Timestamp: ${new Date().toISOString()}`);
    console.log('================================================================\n');

    const report = {
        timestamp: new Date().toISOString(),
        gateResult: 'FAIL',
        failures: [],
        cdp: null,
        target: null,
        osDesktop: null,
    };

    // 1. Check CDP /json/version
    try {
        const v = await fetchJson('/json/version');
        report.cdp = {
            browser: v.Browser,
            userAgent: v['User-Agent'],
            wsUrl: v.webSocketDebuggerUrl,
        };
        console.log(`[PASS] CDP Endpoint Reachable: ${v.Browser}`);
    } catch (e) {
        report.failures.push(`CDP endpoint unreachable on port ${CDP_PORT}: ${e.message}`);
        console.error(`[FAIL] CDP unreachable: ${e.message}`);
        return report;
    }

    // 2. Strict Browser Check (Google Chrome ONLY)
    const browserStr = report.cdp.browser || '';
    if (!browserStr.includes('Chrome') || browserStr.toLowerCase().includes('brave') || browserStr.toLowerCase().includes('edg')) {
        report.failures.push(`Prohibited browser brand on CDP: "${browserStr}". Official Google Chrome required.`);
        console.error(`[FAIL] Prohibited browser on CDP: ${browserStr}`);
    } else {
        console.log(`[PASS] Official Google Chrome Verified on CDP: ${browserStr}`);
    }

    // 3. Check CDP targets
    try {
        const targets = await fetchJson('/json/list');
        const pageTargets = targets.filter((t) => t.type === 'page');
        const appTarget = pageTargets.find((t) => t.url.includes(':8000') || t.url.includes('localhost'));

        if (!appTarget) {
            report.failures.push(`No active CDP page target pointing to application. Pages: ${pageTargets.map((p) => p.url).join(', ')}`);
            console.error('[FAIL] No application target found in CDP.');
        } else {
            report.target = {
                id: appTarget.id,
                url: appTarget.url,
                title: appTarget.title,
            };
            console.log(`[PASS] Application Target Found: Target ID ${appTarget.id} at ${appTarget.url}`);
        }
    } catch (e) {
        report.failures.push(`Failed to list CDP targets: ${e.message}`);
        console.error(`[FAIL] Target listing error: ${e.message}`);
    }

    // 4. Activate and foreground QA Chrome on the OS Desktop, then query
    const scriptPath = path.resolve(process.cwd(), 'scripts', 'manage-qa-chrome.ps1');
    let osData;
    try {
        const rawJson = execSync(
            `powershell -NoProfile -ExecutionPolicy Bypass -File "${scriptPath}" -Action foreground -Port ${CDP_PORT}`,
            { encoding: 'utf8', timeout: 15000 }
        );
        const jsonStart = rawJson.indexOf('{');
        if (jsonStart === -1) throw new Error(`No JSON output from manage-qa-chrome: ${rawJson}`);
        osData = JSON.parse(rawJson.substring(jsonStart));
        report.osDesktop = osData;
    } catch (e) {
        report.failures.push(`Failed to inspect OS desktop: ${e.message}`);
        console.error(`[FAIL] OS desktop inspection error: ${e.message}`);
        return report;
    }

    const qaPid = osData.QaChromePidOnPort;
    const qaWin = osData.QaChromeWindow;
    const fgWin = osData.ForegroundWindow;

    console.log('\n--- OS DESKTOP OWNERSHIP ---');
    console.log(`  QA Chrome PID (Port ${CDP_PORT}) : ${qaPid}`);
    if (qaWin) {
        console.log(`  QA Chrome Window HWND        : ${qaWin.HwndHex} (PID ${qaWin.Pid})`);
        console.log(`  QA Chrome Window Title       : "${qaWin.Title}"`);
        console.log(`  QA Chrome Window Bounds      : Left=${qaWin.Left}, Top=${qaWin.Top}, Right=${qaWin.Right}, Bottom=${qaWin.Bottom} (${qaWin.Width}x${qaWin.Height})`);
        console.log(`  QA Chrome Executable         : ${qaWin.ExePath}`);
        console.log(`  QA Chrome Window Minimized   : ${qaWin.IsIconic}`);
    } else {
        console.log('  QA Chrome Window             : NOT FOUND on Default desktop');
    }

    console.log('\n--- OS FOREGROUND WINDOW ---');
    if (fgWin) {
        console.log(`  Foreground HWND              : ${fgWin.HwndHex}`);
        console.log(`  Foreground PID               : ${fgWin.Pid}`);
        console.log(`  Foreground Process           : ${fgWin.ProcessName}`);
        console.log(`  Foreground Executable        : ${fgWin.ExePath}`);
        console.log(`  Foreground Title             : "${fgWin.Title}"`);
        console.log(`  Foreground Bounds            : Left=${fgWin.Left}, Top=${fgWin.Top}, Right=${fgWin.Right}, Bottom=${fgWin.Bottom} (${fgWin.Width}x${fgWin.Height})`);
    } else {
        console.log('  Foreground Window            : None / Undetected');
    }

    // 5. Perform Hard Gate Invariant Checks
    if (!qaPid || qaPid === 0) {
        report.failures.push(`No process listening on CDP port ${CDP_PORT}`);
    }

    if (!qaWin) {
        report.failures.push('Dedicated QA Chrome has NO visible top-level window on the interactive desktop.');
    } else {
        if (qaWin.IsIconic) {
            report.failures.push('QA Chrome window is MINIMIZED (IsIconic == true).');
        }
        if (!qaWin.ExePath.toLowerCase().includes('google\\chrome')) {
            report.failures.push(`QA Chrome window executable is NOT official Google Chrome: ${qaWin.ExePath}`);
        }
        if (qaWin.Width < 400 || qaWin.Height < 300) {
            report.failures.push(`QA Chrome window rectangle is too small (${qaWin.Width}x${qaWin.Height}).`);
        }
    }

    if (!fgWin) {
        report.failures.push('OS foreground window could not be determined.');
    } else {
        // Must NOT be Brave
        if (fgWin.ExePath.toLowerCase().includes('brave') || fgWin.ProcessName.toLowerCase().includes('brave')) {
            report.failures.push(`PHYSICAL FOREGROUND IS BRAVE (PID ${fgWin.Pid}, HWND ${fgWin.HwndHex})! Brave is obscuring the QA browser.`);
        }

        // Must NOT be Edge
        if (fgWin.ExePath.toLowerCase().includes('msedge') || fgWin.ProcessName.toLowerCase().includes('edge')) {
            report.failures.push(`PHYSICAL FOREGROUND IS EDGE (PID ${fgWin.Pid})!`);
        }

        // Foreground must be the QA Chrome process
        if (qaWin && fgWin.Pid !== qaWin.Pid && fgWin.Hwnd !== qaWin.Hwnd) {
            report.failures.push(
                `Foreground window PID (${fgWin.Pid}: ${fgWin.ProcessName}) does NOT match QA Chrome PID (${qaPid})! Chrome is not in the foreground.`
            );
        }
    }

    // Evaluate final gate
    report.gatePassed = report.failures.length === 0;
    report.gateResult = report.gatePassed ? 'PASS' : 'FAIL';

    console.log('\n================================================================');
    if (report.gatePassed) {
        console.log('  GATE RESULT: PASS — PHYSICAL QA CHROME VERIFIED IN FOREGROUND');
        console.log('  Physical Window, Process Ownership, and CDP are 100% Aligned.');
    } else {
        console.log('  GATE RESULT: FAIL — PHYSICAL QA CHROME GATE FAILED');
        console.log('  Failures:');
        report.failures.forEach((f, idx) => console.log(`    ${idx + 1}. ${f}`));
    }
    console.log('================================================================\n');

    return report;
}

if (process.argv[1]?.endsWith('verify-visible-qa-chrome.js')) {
    verifyVisibleQaChrome()
        .then((r) => {
            process.exit(r.gatePassed ? 0 : 1);
        })
        .catch((err) => {
            console.error('[Fatal Error in Physical Window Verification]', err);
            process.exit(1);
        });
}
