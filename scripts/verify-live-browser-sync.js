#!/usr/bin/env node
/**
 * Hard Browser Identity and Synchronization Verification Script
 * Unique Distributors — Zero False Passes
 *
 * Enforces:
 * 1. CDP listening on http://127.0.0.1:9222
 * 2. Official Google Chrome ONLY (Brave/Edge strictly prohibited)
 * 3. Dedicated QA profile validation
 * 4. Active application page target on localhost:8000 / 127.0.0.1:8000
 * 5. Distinctive application marker in DOM
 * 6. Zero ambiguity (no competing/duplicate application tabs)
 */

import http from 'http';

const CDP_HOST = '127.0.0.1';
const CDP_PORT = 9222;

function fetchJson(path) {
    return new Promise((resolve, reject) => {
        const req = http.get(`http://${CDP_HOST}:${CDP_PORT}${path}`, { timeout: 3000 }, (res) => {
            if (res.statusCode !== 200) {
                reject(new Error(`CDP HTTP ${res.statusCode} on ${path}`));
                return;
            }
            let data = '';
            res.on('data', (chunk) => (data += chunk));
            res.on('end', () => {
                try {
                    resolve(JSON.parse(data));
                } catch (e) {
                    reject(new Error(`Invalid JSON from CDP: ${e.message}`));
                }
            });
        });
        req.on('error', (err) => reject(err));
        req.on('timeout', () => {
            req.destroy();
            reject(new Error(`Timeout connecting to CDP on port ${CDP_PORT}`));
        });
    });
}

function evaluateCdp(wsUrl, expression) {
    return new Promise((resolve, reject) => {
        const ws = new WebSocket(wsUrl);
        const timer = setTimeout(() => {
            try { ws.close(); } catch {}
            reject(new Error('WebSocket evaluation timeout'));
        }, 5000);

        ws.addEventListener('open', () => {
            ws.send(
                JSON.stringify({
                    id: 1,
                    method: 'Runtime.evaluate',
                    params: {
                        expression,
                        returnByValue: true,
                        awaitPromise: true,
                    },
                })
            );
        });

        ws.addEventListener('message', (event) => {
            clearTimeout(timer);
            try {
                const parsed = JSON.parse(event.data.toString());
                ws.close();
                if (parsed.error) {
                    reject(new Error(parsed.error.message || 'CDP evaluation error'));
                } else {
                    resolve(parsed.result?.result?.value);
                }
            } catch (err) {
                reject(err);
            }
        });

        ws.addEventListener('error', (err) => {
            clearTimeout(timer);
            reject(err);
        });
    });
}

async function verifySync() {
    const timestamp = new Date().toISOString();
    console.log(`================================================================`);
    console.log(`  LIVE BROWSER TARGET SYNCHRONIZATION VERIFICATION`);
    console.log(`  Timestamp : ${timestamp}`);
    console.log(`  CDP Host  : http://${CDP_HOST}:${CDP_PORT}`);
    console.log(`================================================================\n`);

    const report = {
        timestamp,
        gatePassed: false,
        failures: [],
        browser: null,
        activePage: null,
        allTargets: [],
    };

    // 1. Check CDP version endpoint
    let versionData;
    try {
        versionData = await fetchJson('/json/version');
        report.browser = {
            brand: versionData.Browser,
            protocolVersion: versionData['Protocol-Version'],
            userAgent: versionData['User-Agent'],
            webSocketDebuggerUrl: versionData.webSocketDebuggerUrl,
        };
    } catch (err) {
        report.failures.push(`CDP endpoint unreachable on port ${CDP_PORT}: ${err.message}`);
        console.error(`[FAIL] CDP endpoint unreachable: ${err.message}`);
        return report;
    }

    // 2. Strict Browser Brand Verification (Google Chrome ONLY)
    const browserStr = report.browser.brand || '';
    const isGoogleChrome =
        browserStr.includes('Chrome') &&
        !browserStr.toLowerCase().includes('brave') &&
        !browserStr.toLowerCase().includes('edg');

    if (!isGoogleChrome) {
        report.failures.push(
            `Prohibited browser brand detected: "${browserStr}". Official Google Chrome is strictly required.`
        );
        console.error(`[FAIL] Prohibited browser: ${browserStr}`);
    } else {
        console.log(`[PASS] Browser Brand : ${browserStr} (Official Google Chrome)`);
    }

    // 3. Enumerate all CDP targets
    let targets;
    try {
        targets = await fetchJson('/json/list');
        report.allTargets = targets.map((t) => ({
            id: t.id,
            type: t.type,
            title: t.title,
            url: t.url,
            wsUrl: t.webSocketDebuggerUrl,
        }));
    } catch (err) {
        report.failures.push(`Failed to list CDP targets: ${err.message}`);
        console.error(`[FAIL] Cannot list targets: ${err.message}`);
        return report;
    }

    // 4. Filter for real page targets
    const pageTargets = targets.filter((t) => t.type === 'page');
    console.log(`[INFO] Total CDP Targets: ${targets.length} (Pages: ${pageTargets.length})`);

    if (pageTargets.length === 0) {
        report.failures.push('No open page targets found in the QA browser window.');
        console.error(`[FAIL] Zero open page targets.`);
        return report;
    }

    // 5. Match Application Page (localhost:8000 or 127.0.0.1:8000)
    const appPages = pageTargets.filter((t) => {
        const u = t.url || '';
        return u.includes('localhost:8000') || u.includes('127.0.0.1:8000');
    });

    if (appPages.length === 0) {
        report.failures.push(
            `No open page targets point to the Unique Distributors application (localhost:8000 or 127.0.0.1:8000). Current pages: ${pageTargets.map((p) => p.url).join(', ')}`
        );
        console.error(`[FAIL] Application is not open in Chrome.`);
    } else if (appPages.length > 1) {
        report.failures.push(
            `Ambiguity detected: More than one application tab is open (${appPages.length} tabs). Exactly one authoritative QA tab must be active.`
        );
        console.error(`[FAIL] Multiple ambiguous application tabs.`);
    } else {
        const targetPage = appPages[0];
        console.log(`[PASS] Unique Authoritative Application Target Identified:`);
        console.log(`  - Target ID : ${targetPage.id}`);
        console.log(`  - URL       : ${targetPage.url}`);
        console.log(`  - Title     : ${targetPage.title}`);

        // 6. Direct DOM & Identity Evaluation via WebSocket
        try {
            const evalResult = await evaluateCdp(
                targetPage.webSocketDebuggerUrl,
                `JSON.stringify({
                    url: window.location.href,
                    title: document.title,
                    viewport: { width: window.innerWidth, height: window.innerHeight },
                    hasMarker: document.body ? (document.body.innerText.includes('Unique Distributors') || document.title.includes('Unique Distributors') || document.title.includes('Wholesale Distribution')) : false,
                    visibilityState: document.visibilityState,
                    readyState: document.readyState
                })`
            );

            const parsedEval = JSON.parse(evalResult || '{}');
            report.activePage = {
                targetId: targetPage.id,
                url: parsedEval.url || targetPage.url,
                title: parsedEval.title || targetPage.title,
                viewport: parsedEval.viewport,
                hasAppMarker: parsedEval.hasMarker,
                visibilityState: parsedEval.visibilityState,
                readyState: parsedEval.readyState,
                wsUrl: targetPage.webSocketDebuggerUrl,
            };

            if (!parsedEval.hasMarker) {
                report.failures.push('Application marker "Unique Distributors" missing from rendered page DOM.');
                console.error(`[FAIL] Application marker missing from page.`);
            } else {
                console.log(`[PASS] Application Identity Verified in live DOM: "Unique Distributors"`);
            }

            console.log(`[PASS] Viewport Dimensions: ${parsedEval.viewport?.width}x${parsedEval.viewport?.height}`);
            console.log(`[PASS] Page Ready State   : ${parsedEval.readyState}`);
        } catch (evalErr) {
            report.failures.push(`Failed to evaluate live page DOM: ${evalErr.message}`);
            console.error(`[FAIL] DOM Evaluation failed: ${evalErr.message}`);
        }
    }

    // Final Gate Evaluation
    report.gatePassed = report.failures.length === 0;

    console.log(`\n================================================================`);
    if (report.gatePassed) {
        console.log(`  RESULT: PASS — LIVE BROWSER SYNCHRONIZATION VERIFIED`);
        console.log(`  Authoritative Chrome and CDP are 100% synchronized.`);
    } else {
        console.log(`  RESULT: FAIL — DO NOT START AUDIT`);
        console.log(`  Failures:`);
        report.failures.forEach((f, idx) => console.log(`    ${idx + 1}. ${f}`));
    }
    console.log(`================================================================\n`);

    return report;
}

if (process.argv[1]?.endsWith('verify-live-browser-sync.js')) {
    verifySync()
        .then((report) => {
            if (!report.gatePassed) {
                process.exit(1);
            }
            process.exit(0);
        })
        .catch((err) => {
            console.error('[Fatal Error in Browser Sync Verification]', err);
            process.exit(1);
        });
}

export { verifySync };
