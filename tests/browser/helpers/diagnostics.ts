import { Page } from '@playwright/test';
import fs from 'fs';
import path from 'path';

export interface ConsoleLogEntry {
    type: string;
    text: string;
    location?: string;
    timestamp: string;
}

export interface NetworkErrorEntry {
    url: string;
    method: string;
    status?: number;
    statusText?: string;
    failureReason?: string;
    timestamp: string;
}

export interface BrowserDiagnostics {
    url: string;
    title: string;
    timestamp: string;
    consoleLogs: ConsoleLogEntry[];
    consoleErrors: ConsoleLogEntry[];
    networkErrors: NetworkErrorEntry[];
    screenshotPath?: string;
}

const ARTIFACTS_DIR = path.resolve(process.cwd(), 'artifacts', 'browser');
const SCREENSHOTS_DIR = path.join(ARTIFACTS_DIR, 'screenshots');
const DIAGNOSTICS_DIR = path.join(ARTIFACTS_DIR, 'diagnostics');

/**
 * Attach listeners to page for collecting console and network diagnostics.
 */
export function attachDiagnosticsCollector(page: Page): {
    getDiagnostics: () => BrowserDiagnostics;
    saveDiagnostics: (testName: string, captureScreenshot?: boolean, fullPage?: boolean) => Promise<string>;
} {
    // Ensure output directories exist
    fs.mkdirSync(SCREENSHOTS_DIR, { recursive: true });
    fs.mkdirSync(DIAGNOSTICS_DIR, { recursive: true });

    const consoleLogs: ConsoleLogEntry[] = [];
    const consoleErrors: ConsoleLogEntry[] = [];
    const networkErrors: NetworkErrorEntry[] = [];

    // 1. Console listener
    page.on('console', (msg) => {
        const type = msg.type();
        const text = msg.text();
        const location = msg.location() ? `${msg.location().url}:${msg.location().lineNumber}` : undefined;
        const entry: ConsoleLogEntry = {
            type,
            text,
            location,
            timestamp: new Date().toISOString(),
        };

        consoleLogs.push(entry);
        if (type === 'error') {
            consoleErrors.push(entry);
        }
    });

    // 2. Uncaught page error listener
    page.on('pageerror', (err) => {
        consoleErrors.push({
            type: 'pageerror',
            text: err.message,
            location: err.stack,
            timestamp: new Date().toISOString(),
        });
    });

    // 3. Network request failure listener
    page.on('requestfailed', (req) => {
        networkErrors.push({
            url: req.url(),
            method: req.method(),
            failureReason: req.failure()?.errorText || 'Request failed',
            timestamp: new Date().toISOString(),
        });
    });

    // 4. HTTP response 4xx / 5xx listener
    page.on('response', (res) => {
        const status = res.status();
        if (status >= 400) {
            networkErrors.push({
                url: res.url(),
                method: res.request().method(),
                status,
                statusText: res.statusText(),
                timestamp: new Date().toISOString(),
            });
        }
    });

    const getDiagnostics = (): BrowserDiagnostics => {
        return {
            url: page.url(),
            title: '',
            timestamp: new Date().toISOString(),
            consoleLogs,
            consoleErrors,
            networkErrors,
        };
    };

    const saveDiagnostics = async (
        testName: string,
        captureScreenshot: boolean = true,
        fullPage: boolean = false
    ): Promise<string> => {
        const sanitizedName = testName.replace(/[^a-zA-Z0-9_-]/g, '_');
        const timestamp = new Date().toISOString().replace(/[:.]/g, '-');
        const baseFilename = `${sanitizedName}_${timestamp}`;

        let screenshotPath: string | undefined;
        if (captureScreenshot) {
            screenshotPath = path.join(SCREENSHOTS_DIR, `${baseFilename}.png`);
            try {
                await page.screenshot({ path: screenshotPath, fullPage });
            } catch {
                // Ignore if page is already closed
            }
        }

        let title = '';
        try {
            title = await page.title();
        } catch {
            title = 'Unavailable';
        }

        const report: BrowserDiagnostics = {
            url: page.url(),
            title,
            timestamp: new Date().toISOString(),
            consoleLogs,
            consoleErrors,
            networkErrors,
            screenshotPath,
        };

        const jsonPath = path.join(DIAGNOSTICS_DIR, `${baseFilename}.json`);
        fs.writeFileSync(jsonPath, JSON.stringify(report, null, 2), 'utf8');

        return jsonPath;
    };

    return {
        getDiagnostics,
        saveDiagnostics,
    };
}

export class DiagnosticsCollector {
    private collector: ReturnType<typeof attachDiagnosticsCollector>;

    constructor(private page: Page) {
        this.collector = attachDiagnosticsCollector(page);
    }

    getDiagnostics(): BrowserDiagnostics {
        return this.collector.getDiagnostics();
    }

    getErrors(): string[] {
        return this.collector.getDiagnostics().consoleErrors.map((e) => e.text);
    }

    async saveDiagnostics(testName: string, captureScreenshot = true, fullPage = false): Promise<string> {
        return this.collector.saveDiagnostics(testName, captureScreenshot, fullPage);
    }

    async captureNamedScreenshot(page: Page, name: string, dir: string): Promise<string> {
        fs.mkdirSync(dir, { recursive: true });
        const filePath = path.join(dir, `${name}.png`);
        try {
            await page.screenshot({ path: filePath, fullPage: true });
        } catch {
            // In case page was redirected or closed
        }
        return filePath;
    }
}

export async function safeGoto(page: Page, url: string, maxAttempts = 3) {
    for (let attempt = 1; attempt <= maxAttempts; attempt++) {
        try {
            const resp = await page.goto(url, { waitUntil: 'domcontentloaded', timeout: 20000 });
            return resp;
        } catch (err: any) {
            if (attempt === maxAttempts) throw err;
            await page.waitForTimeout(800 * attempt);
        }
    }
    return null;
}
