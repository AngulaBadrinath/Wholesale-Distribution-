import fs from 'fs';
import path from 'path';
import os from 'os';
import { execSync } from 'child_process';

export interface ResolvedBrowser {
    executablePath: string;
    browserName: string;
    version: string;
    source: 'env' | 'discovered' | 'configured';
}

/**
 * Common Chrome/Chromium installation paths by operating system.
 */
const WINDOWS_SEARCH_PATHS = [
    'C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe',
    'C:\\Program Files (x86)\\Google\\Chrome\\Application\\chrome.exe',
    path.join(process.env.LOCALAPPDATA || 'C:\\Users\\' + (process.env.USERNAME || 'default') + '\\AppData\\Local', 'Google\\Chrome\\Application\\chrome.exe'),
];

const MACOS_SEARCH_PATHS = [
    '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome',
    '/Applications/Chromium.app/Contents/MacOS/Chromium',
    path.join(os.homedir(), 'Applications/Google Chrome.app/Contents/MacOS/Google Chrome'),
    path.join(os.homedir(), 'Applications/Chromium.app/Contents/MacOS/Chromium'),
];

const LINUX_SEARCH_PATHS = [
    '/usr/bin/google-chrome',
    '/usr/bin/google-chrome-stable',
    '/usr/bin/chromium',
    '/usr/bin/chromium-browser',
    '/snap/bin/chromium',
];

/**
 * Extract version string from an executable path safely.
 */
function getBrowserVersion(execPath: string): string {
    try {
        if (process.platform === 'win32') {
            const escapedPath = execPath.replace(/'/g, "''");
            const cmd = `powershell -NoProfile -Command "(Get-Item '${escapedPath}').VersionInfo.ProductVersion"`;
            const out = execSync(cmd, { stdio: ['pipe', 'pipe', 'ignore'], timeout: 5000 }).toString().trim();
            if (out) return out;
        }
        const out = execSync(`"${execPath}" --version`, { stdio: ['pipe', 'pipe', 'ignore'], timeout: 5000 }).toString().trim();
        return out || 'Version unknown';
    } catch {
        return 'Version unknown';
    }
}

/**
 * Determine human-readable browser brand from executable path.
 */
function getBrowserName(execPath: string): string {
    const lower = execPath.toLowerCase();
    if (lower.includes('chrome')) return 'Google Chrome';
    if (lower.includes('msedge') || lower.includes('edge')) return 'Microsoft Edge';
    if (lower.includes('brave')) return 'Brave Browser';
    if (lower.includes('chromium')) return 'Chromium';
    return 'Custom Chromium Browser';
}

/**
 * Resolve local Chrome/Chromium executable with zero external CDN dependency.
 */
export function resolveBrowser(): ResolvedBrowser {
    // 1. Explicit Environment Variable Override
    const envPath = process.env.PLAYWRIGHT_BROWSER_PATH;
    if (envPath) {
        if (fs.existsSync(envPath)) {
            return {
                executablePath: path.resolve(envPath),
                browserName: getBrowserName(envPath),
                version: getBrowserVersion(envPath),
                source: 'env',
            };
        } else {
            throw new Error(
                `[BrowserResolver] PLAYWRIGHT_BROWSER_PATH is set to "${envPath}", but the file does not exist on disk.`
            );
        }
    }

    // 2. Platform-Specific Search List
    let candidatePaths: string[] = [];
    const platform = process.platform;

    if (platform === 'win32') {
        candidatePaths = WINDOWS_SEARCH_PATHS;
    } else if (platform === 'darwin') {
        candidatePaths = MACOS_SEARCH_PATHS;
    } else {
        candidatePaths = LINUX_SEARCH_PATHS;
    }

    for (const candidate of candidatePaths) {
        try {
            if (fs.existsSync(candidate)) {
                return {
                    executablePath: candidate,
                    browserName: getBrowserName(candidate),
                    version: getBrowserVersion(candidate),
                    source: 'discovered',
                };
            }
        } catch {
            // Ignore filesystem access exceptions and continue checking candidates
        }
    }

    // 3. Fallback which / where command resolution on Unix/Linux systems
    if (platform !== 'win32') {
        const binNames = ['google-chrome', 'google-chrome-stable', 'chromium', 'chromium-browser'];
        for (const bin of binNames) {
            try {
                const found = execSync(`which ${bin}`, { stdio: ['pipe', 'pipe', 'ignore'] }).toString().trim();
                if (found && fs.existsSync(found)) {
                    return {
                        executablePath: found,
                        browserName: getBrowserName(found),
                        version: getBrowserVersion(found),
                        source: 'discovered',
                    };
                }
            } catch {
                // Not in PATH
            }
        }
    }

    // 4. Actionable Failure Message (Zero CDN Downloads)
    const checkedList = candidatePaths.map((p) => `  - ${p}`).join('\n');
    throw new Error(
        `[BrowserResolver] No installed Chrome or Chromium executable found on this system.\n` +
        `Checked locations:\n${checkedList}\n\n` +
        `To fix this:\n` +
        `1. Ensure Google Chrome, Microsoft Edge, or Chromium is installed.\n` +
        `2. Or set the PLAYWRIGHT_BROWSER_PATH environment variable to your browser executable path:\n` +
        `   e.g. set PLAYWRIGHT_BROWSER_PATH=C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe`
    );
}

/**
 * Strictly resolve official Google Chrome for QA and Interactive Audit mode.
 * Throws an explicit error if official Google Chrome is not installed.
 */
export function resolveChromeOnly(): ResolvedBrowser {
    const candidatePaths: string[] = [];
    if (process.platform === 'win32') {
        candidatePaths.push(
            'C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe',
            'C:\\Program Files (x86)\\Google\\Chrome\\Application\\chrome.exe',
            path.join(process.env.LOCALAPPDATA || 'C:\\Users\\' + (process.env.USERNAME || 'default') + '\\AppData\\Local', 'Google\\Chrome\\Application\\chrome.exe')
        );
    } else if (process.platform === 'darwin') {
        candidatePaths.push(
            '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome',
            path.join(os.homedir(), 'Applications/Google Chrome.app/Contents/MacOS/Google Chrome')
        );
    } else {
        candidatePaths.push('/usr/bin/google-chrome', '/usr/bin/google-chrome-stable');
    }

    for (const candidate of candidatePaths) {
        if (fs.existsSync(candidate)) {
            return {
                executablePath: candidate,
                browserName: 'Google Chrome',
                version: getBrowserVersion(candidate),
                source: 'discovered',
            };
        }
    }

    throw new Error(
        `[BrowserResolver] Official Google Chrome is required for Interactive QA Audit, but was not found.\n` +
        `Checked paths:\n${candidatePaths.map(p => `  - ${p}`).join('\n')}\n` +
        `Please install Google Chrome at "C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe".`
    );
}
