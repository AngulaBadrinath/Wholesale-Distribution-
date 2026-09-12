#!/usr/bin/env node
import fs from 'fs';
import path from 'path';
import http from 'http';
import { spawn, execSync } from 'child_process';
import { resolveBrowser } from '../resolver.ts';

export interface LaunchChromeConfig {
    port?: number;
    targetUrl?: string;
    profileDir?: string;
    windowX?: number;
    windowY?: number;
    windowWidth?: number;
    windowHeight?: number;
}

export interface MonitorPlacement {
    x: number;
    y: number;
    width: number;
    height: number;
    monitorDescription: string;
}

/**
 * Check if CDP endpoint is responding on the specified port.
 */
export async function checkCdpEndpoint(port: number): Promise<{ isRunning: boolean; versionData?: any; error?: string }> {
    return new Promise((resolve) => {
        const req = http.get(`http://127.0.0.1:${port}/json/version`, { timeout: 1500 }, (res) => {
            if (res.statusCode !== 200) {
                resolve({ isRunning: false, error: `CDP returned HTTP ${res.statusCode}` });
                return;
            }
            let raw = '';
            res.on('data', (chunk) => (raw += chunk));
            res.on('end', () => {
                try {
                    const parsed = JSON.parse(raw);
                    resolve({ isRunning: true, versionData: parsed });
                } catch {
                    resolve({ isRunning: true, versionData: raw });
                }
            });
        });

        req.on('error', (err) => {
            resolve({ isRunning: false, error: err.message });
        });

        req.on('timeout', () => {
            req.destroy();
            resolve({ isRunning: false, error: 'Connection timed out' });
        });
    });
}

/**
 * Detect secondary monitor bounds on Windows or fallback to sensible defaults.
 */
export function detectMonitorPlacement(): MonitorPlacement {
    if (process.platform === 'win32') {
        const psScriptPath = path.resolve(process.cwd(), 'tests', 'browser', 'interactive', 'detect-monitors.ps1');
        if (fs.existsSync(psScriptPath)) {
            try {
                const rawJson = execSync(
                    `powershell -NoProfile -ExecutionPolicy Bypass -File "${psScriptPath}"`,
                    { stdio: ['pipe', 'pipe', 'ignore'], timeout: 6000 }
                ).toString().trim();

                const parsed = JSON.parse(rawJson);
                if (parsed && parsed.recommendedX !== undefined) {
                    const isSecondary = parsed.totalScreens > 1 && parsed.targetScreen && !parsed.targetScreen.Primary;
                    return {
                        x: parsed.recommendedX,
                        y: parsed.recommendedY,
                        width: parsed.recommendedWidth || 1440,
                        height: parsed.recommendedHeight || 900,
                        monitorDescription: isSecondary
                            ? `Secondary Monitor (${parsed.targetScreen.DeviceName}, ${parsed.targetScreen.Width}x${parsed.targetScreen.Height} at X:${parsed.targetScreen.X}, Y:${parsed.targetScreen.Y})`
                            : `Primary Monitor (${parsed.targetScreen?.DeviceName || 'Screen 1'}, offset for visibility)`,
                    };
                }
            } catch {
                // Fallback below
            }
        }
    }

    // Default safe fallback
    return {
        x: 80,
        y: 80,
        width: 1440,
        height: 900,
        monitorDescription: 'Default Display Layout (Single Monitor Fallback)',
    };
}

/**
 * Launch the dedicated headed Google Chrome QA window with remote debugging on port 9222.
 */
export async function launchDedicatedChrome(config: LaunchChromeConfig = {}): Promise<{
    port: number;
    profileDir: string;
    executablePath: string;
    cdpUrl: string;
    targetUrl: string;
    monitor: MonitorPlacement;
}> {
    const port = config.port || parseInt(process.env.CHROME_DEBUG_PORT || '9222', 10);
    const baseUrl = process.env.PLAYWRIGHT_BASE_URL || 'http://localhost:8000';
    const targetUrl = config.targetUrl || `${baseUrl.replace(/\/$/, '')}/login`;

    const projectRoot = process.cwd();
    const profileDir = config.profileDir || path.resolve(projectRoot, 'artifacts', 'browser', 'qa-profile');
    fs.mkdirSync(profileDir, { recursive: true });

    // 1. Health check existing CDP endpoint
    const existing = await checkCdpEndpoint(port);
    if (existing.isRunning) {
        console.log(`\n================================================================`);
        console.log(`  [QA Chrome] Already Running & Healthy`);
        console.log(`  CDP Endpoint : http://127.0.0.1:${port}`);
        console.log(`  Browser      : ${existing.versionData?.Browser || 'Chrome/Chromium'}`);
        console.log(`  Profile      : ${profileDir}`);
        console.log(`  Target URL   : ${targetUrl}`);
        console.log(`================================================================\n`);
        return {
            port,
            profileDir,
            executablePath: 'Attached to active instance',
            cdpUrl: `http://127.0.0.1:${port}`,
            targetUrl,
            monitor: { x: 0, y: 0, width: 1440, height: 900, monitorDescription: 'Existing Window' },
        };
    }

    // 2. Resolve local Chrome binary
    const resolved = resolveBrowser();
    console.log(`[QA Chrome] Resolved Browser Binary: ${resolved.executablePath} (${resolved.browserName} v${resolved.version})`);

    // 3. Detect monitor placement
    const detectedMonitor = detectMonitorPlacement();
    const posX = config.windowX !== undefined ? config.windowX : (process.env.BROWSER_WINDOW_X ? parseInt(process.env.BROWSER_WINDOW_X, 10) : detectedMonitor.x);
    const posY = config.windowY !== undefined ? config.windowY : (process.env.BROWSER_WINDOW_Y ? parseInt(process.env.BROWSER_WINDOW_Y, 10) : detectedMonitor.y);
    const posW = config.windowWidth !== undefined ? config.windowWidth : (process.env.BROWSER_WINDOW_WIDTH ? parseInt(process.env.BROWSER_WINDOW_WIDTH, 10) : detectedMonitor.width);
    const posH = config.windowHeight !== undefined ? config.windowHeight : (process.env.BROWSER_WINDOW_HEIGHT ? parseInt(process.env.BROWSER_WINDOW_HEIGHT, 10) : detectedMonitor.height);

    const placement: MonitorPlacement = {
        x: posX,
        y: posY,
        width: posW,
        height: posH,
        monitorDescription: detectedMonitor.monitorDescription,
    };

    // 4. Construct clean Chrome launch flags (no anti-detection/stealth flags)
    const chromeArgs = [
        `--remote-debugging-port=${port}`,
        `--remote-debugging-address=127.0.0.1`,
        `--user-data-dir=${profileDir}`,
        '--no-first-run',
        '--no-default-browser-check',
        `--window-position=${placement.x},${placement.y}`,
        `--window-size=${placement.width},${placement.height}`,
        targetUrl,
    ];

    console.log(`[QA Chrome] Spawning Chrome OS Window...`);
    console.log(`  Target Display : ${placement.monitorDescription}`);
    console.log(`  Coordinates    : X: ${placement.x}, Y: ${placement.y} (${placement.width}x${placement.height})`);
    console.log(`  CDP Binding    : 127.0.0.1:${port}`);
    console.log(`  QA Profile     : ${profileDir}`);

    const child = spawn(resolved.executablePath, chromeArgs, {
        detached: true,
        stdio: 'ignore',
    });
    child.unref();

    // 5. Poll CDP endpoint until ready
    let isReady = false;
    let attempts = 0;
    const maxAttempts = 20;

    while (attempts < maxAttempts) {
        await new Promise((r) => setTimeout(r, 500));
        attempts++;
        const check = await checkCdpEndpoint(port);
        if (check.isRunning) {
            isReady = true;
            console.log(`\n================================================================`);
            console.log(`  [QA Chrome] Visible Chrome Window Successfully Launched`);
            console.log(`  CDP Endpoint : http://127.0.0.1:${port}`);
            console.log(`  Target URL   : ${targetUrl}`);
            console.log(`  Profile      : ${profileDir}`);
            console.log(`  Ready in     : ${attempts * 500}ms`);
            console.log(`================================================================\n`);
            break;
        }
    }

    if (!isReady) {
        throw new Error(
            `[QA Chrome] Chrome process was spawned, but CDP port ${port} did not respond within 10 seconds.`
        );
    }

    return {
        port,
        profileDir,
        executablePath: resolved.executablePath,
        cdpUrl: `http://127.0.0.1:${port}`,
        targetUrl,
        monitor: placement,
    };
}

if (process.argv[1]?.endsWith('launch-chrome.ts') || process.argv[1]?.endsWith('launch-chrome.js')) {
    launchDedicatedChrome()
        .then(() => process.exit(0))
        .catch((err) => {
            console.error('[QA Chrome Launch Error]', err.message || err);
            process.exit(1);
        });
}
