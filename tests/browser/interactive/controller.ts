import { chromium } from '@playwright/test';
import type { Browser, BrowserContext, Page } from '@playwright/test';
import fs from 'fs';
import path from 'path';
import { resolveBrowser, type ResolvedBrowser } from '../resolver.ts';
import { VIEWPORT_MATRIX, type ViewportConfig } from '../viewports.ts';
import { loginAs, type UserRole, QA_USER_CREDENTIALS } from '../helpers/auth.ts';
import {
    attachDiagnosticsCollector,
    type BrowserDiagnostics,
    type ConsoleLogEntry,
    type NetworkErrorEntry,
    safeGoto,
} from '../helpers/diagnostics.ts';

export interface TabInfo {
    index: number;
    title: string;
    url: string;
    isActive: boolean;
}

export interface ControllerStatus {
    isRunning: boolean;
    browserName: string;
    browserVersion: string;
    executablePath: string;
    baseUrl: string;
    environment: 'LOCAL' | 'PRE-PRODUCTION' | 'PRODUCTION' | 'CUSTOM';
    activeTabIndex: number;
    totalTabs: number;
    tabs: TabInfo[];
    currentUrl: string;
    title: string;
    viewport: { width: number; height: number } | null;
    diagnosticsSummary: {
        consoleErrorsCount: number;
        networkErrorsCount: number;
    };
    lastScreenshotPath?: string;
}

export interface LaunchOptions {
    headed?: boolean;
    baseUrl?: string;
    executablePath?: string;
    startUrl?: string;
    recordVideo?: boolean;
    trace?: boolean;
    viewport?: { width: number; height: number };
}

export class InteractiveBrowserController {
    private browser: Browser | null = null;
    private context: BrowserContext | null = null;
    private pages: Page[] = [];
    private pageCollectors: Map<Page, ReturnType<typeof attachDiagnosticsCollector>> = new Map();
    private activePageIndex = 0;
    private resolvedBrowser: ResolvedBrowser | null = null;
    private baseUrl: string;
    private isRunning = false;
    private currentViewport: { width: number; height: number } | null = null;
    private lastScreenshotPath?: string;
    private artifactsDir: string;
    private screenshotsDir: string;
    private diagnosticsDir: string;

    constructor() {
        this.baseUrl = process.env.PLAYWRIGHT_BASE_URL || 'http://localhost:8000';
        this.artifactsDir = path.resolve(process.cwd(), 'artifacts', 'browser', 'interactive');
        this.screenshotsDir = path.join(this.artifactsDir, 'screenshots');
        this.diagnosticsDir = path.join(this.artifactsDir, 'diagnostics');

        fs.mkdirSync(this.screenshotsDir, { recursive: true });
        fs.mkdirSync(this.diagnosticsDir, { recursive: true });
    }

    /**
     * Start the persistent real headed browser session.
     */
    async start(options: LaunchOptions = {}): Promise<ControllerStatus> {
        if (this.isRunning && this.browser && this.context) {
            return this.getStatus();
        }

        if (options.baseUrl) {
            this.baseUrl = options.baseUrl;
        }

        // 1. Resolve local Chrome/Chromium without CDN dependencies
        if (options.executablePath) {
            this.resolvedBrowser = {
                executablePath: path.resolve(options.executablePath),
                browserName: 'Custom Specified Browser',
                version: 'Custom',
                source: 'configured',
            };
        } else {
            this.resolvedBrowser = resolveBrowser();
        }

        const isHeaded = options.headed !== undefined ? options.headed : true;
        const shouldRecordVideo = options.recordVideo || process.env.BROWSER_RECORD_VIDEO === 'true';
        const shouldTrace = options.trace || process.env.BROWSER_TRACE === 'true';

        const launchArgs = [
            '--start-maximized',
            '--no-default-browser-check',
            '--no-first-run',
            '--disable-blink-features=AutomationControlled',
        ];

        // 2. Launch real browser instance
        this.browser = await chromium.launch({
            executablePath: this.resolvedBrowser.executablePath,
            headless: !isHeaded,
            args: launchArgs,
        });

        const contextOptions: Parameters<Browser['newContext']>[0] = {
            viewport: options.viewport || null,
            ignoreHTTPSErrors: true,
        };

        if (shouldRecordVideo) {
            const videoDir = path.join(this.artifactsDir, 'videos');
            fs.mkdirSync(videoDir, { recursive: true });
            contextOptions.recordVideo = { dir: videoDir };
        }

        this.context = await this.browser.newContext(contextOptions);

        if (shouldTrace) {
            await this.context.tracing.start({ screenshots: true, snapshots: true });
        }

        // 3. Listen to new tabs created by links/scripts
        this.context.on('page', (newPage) => {
            this.trackNewPage(newPage);
        });

        // 4. Initialize first tab
        const firstPage = await this.context.newPage();
        this.trackNewPage(firstPage);
        this.activePageIndex = 0;

        if (options.viewport) {
            this.currentViewport = options.viewport;
            await firstPage.setViewportSize(options.viewport);
        }

        const initialUrl = options.startUrl || this.baseUrl;
        await safeGoto(firstPage, initialUrl);

        this.isRunning = true;
        return this.getStatus();
    }

    /**
     * Attach tracking, close listeners, and diagnostics to a page.
     */
    private trackNewPage(page: Page): void {
        if (!this.pages.includes(page)) {
            this.pages.push(page);
            const collector = attachDiagnosticsCollector(page);
            this.pageCollectors.set(page, collector);

            page.on('close', () => {
                const idx = this.pages.indexOf(page);
                if (idx !== -1) {
                    this.pages.splice(idx, 1);
                    this.pageCollectors.delete(page);
                    if (this.activePageIndex >= this.pages.length) {
                        this.activePageIndex = Math.max(0, this.pages.length - 1);
                    }
                }
            });
        }
    }

    /**
     * Get the active Page instance.
     */
    getActivePage(): Page {
        this.ensureRunning();
        if (this.pages.length === 0) {
            throw new Error('[InteractiveBrowser] No open tabs found in the active session.');
        }
        if (this.activePageIndex < 0 || this.activePageIndex >= this.pages.length) {
            this.activePageIndex = 0;
        }
        return this.pages[this.activePageIndex];
    }

    /**
     * Navigate active tab to relative path or absolute URL.
     */
    async navigate(urlOrPath: string): Promise<ControllerStatus> {
        const page = this.getActivePage();
        let targetUrl = urlOrPath;

        if (!urlOrPath.startsWith('http://') && !urlOrPath.startsWith('https://')) {
            const cleanPath = urlOrPath.startsWith('/') ? urlOrPath : `/${urlOrPath}`;
            targetUrl = `${this.baseUrl}${cleanPath}`;
        }

        await safeGoto(page, targetUrl);
        return this.getStatus();
    }

    /**
     * Click element by selector, accessible role, or text.
     */
    async click(selector: string, timeout = 10000): Promise<ControllerStatus> {
        const page = this.getActivePage();

        // 1. Try standard locator
        try {
            await page.locator(selector).first().click({ timeout });
            return this.getStatus();
        } catch {
            // 2. Fallback to text match
            try {
                await page.getByText(selector, { exact: false }).first().click({ timeout: 3000 });
                return this.getStatus();
            } catch {
                // 3. Fallback to label/button role
                await page.getByRole('button', { name: selector, exact: false }).first().click({ timeout: 3000 });
                return this.getStatus();
            }
        }
    }

    /**
     * Click element with visible text content.
     */
    async clickText(text: string, exact = false, timeout = 10000): Promise<ControllerStatus> {
        const page = this.getActivePage();
        await page.getByText(text, { exact }).first().click({ timeout });
        return this.getStatus();
    }

    /**
     * Click element by accessible ARIA role and optional name.
     */
    async clickRole(role: string, name?: string, timeout = 10000): Promise<ControllerStatus> {
        const page = this.getActivePage();
        const roleLocator = page.getByRole(role as any, name ? { name, exact: false } : undefined).first();
        await roleLocator.click({ timeout });
        return this.getStatus();
    }

    /**
     * Fill text into input field by selector.
     */
    async fill(selector: string, value: string, timeout = 10000): Promise<ControllerStatus> {
        const page = this.getActivePage();
        await page.locator(selector).first().fill(value, { timeout });
        return this.getStatus();
    }

    /**
     * Fill text into input field associated with a label or placeholder.
     */
    async fillLabel(label: string, value: string, timeout = 10000): Promise<ControllerStatus> {
        const page = this.getActivePage();
        try {
            await page.getByLabel(label, { exact: false }).first().fill(value, { timeout });
        } catch {
            await page.getByPlaceholder(label, { exact: false }).first().fill(value, { timeout });
        }
        return this.getStatus();
    }

    /**
     * Select dropdown option by value or label.
     */
    async select(selector: string, valueOrLabel: string, timeout = 10000): Promise<ControllerStatus> {
        const page = this.getActivePage();
        try {
            await page.locator(selector).first().selectOption({ value: valueOrLabel }, { timeout });
        } catch {
            await page.locator(selector).first().selectOption({ label: valueOrLabel }, { timeout });
        }
        return this.getStatus();
    }

    /**
     * Check a checkbox or radio button.
     */
    async check(selector: string, timeout = 10000): Promise<ControllerStatus> {
        const page = this.getActivePage();
        await page.locator(selector).first().check({ timeout });
        return this.getStatus();
    }

    /**
     * Uncheck a checkbox.
     */
    async uncheck(selector: string, timeout = 10000): Promise<ControllerStatus> {
        const page = this.getActivePage();
        await page.locator(selector).first().uncheck({ timeout });
        return this.getStatus();
    }

    /**
     * Press a keyboard key on target selector or active page.
     */
    async press(selector: string, key: string, timeout = 10000): Promise<ControllerStatus> {
        const page = this.getActivePage();
        if (selector && selector !== 'body') {
            await page.locator(selector).first().press(key, { timeout });
        } else {
            await page.keyboard.press(key);
        }
        return this.getStatus();
    }

    /**
     * Wait for a selector to become visible or present.
     */
    async waitFor(selector: string, timeoutMs = 10000): Promise<ControllerStatus> {
        const page = this.getActivePage();
        await page.waitForSelector(selector, { state: 'visible', timeout: timeoutMs });
        return this.getStatus();
    }

    /**
     * Pause execution for milliseconds.
     */
    async wait(ms: number): Promise<ControllerStatus> {
        const page = this.getActivePage();
        await page.waitForTimeout(ms);
        return this.getStatus();
    }

    /**
     * Authenticate authoritatively as a QA role with automated MFA handling.
     */
    async login(role: UserRole): Promise<ControllerStatus> {
        const page = this.getActivePage();
        await loginAs(page, role);
        return this.getStatus();
    }

    /**
     * Adjust viewport size using matrix preset or custom dimensions.
     */
    async setViewport(presetOrWidth: string | number, height?: number): Promise<ControllerStatus> {
        const page = this.getActivePage();
        let targetWidth = 1440;
        let targetHeight = 900;

        if (typeof presetOrWidth === 'number') {
            targetWidth = presetOrWidth;
            targetHeight = height || (presetOrWidth === 320 ? 568 : presetOrWidth === 375 ? 667 : presetOrWidth === 390 ? 844 : presetOrWidth === 768 ? 1024 : 900);
        } else {
            const key = presetOrWidth.toLowerCase();
            if (key === 'mobile' || key === 'phone') {
                targetWidth = 390;
                targetHeight = 844;
            } else if (key === 'tablet' || key === 'ipad') {
                targetWidth = 768;
                targetHeight = 1024;
            } else if (key === 'desktop' || key === 'laptop') {
                targetWidth = 1440;
                targetHeight = 900;
            } else if (VIEWPORT_MATRIX[key]) {
                targetWidth = VIEWPORT_MATRIX[key].width;
                targetHeight = VIEWPORT_MATRIX[key].height;
            } else {
                const num = parseInt(presetOrWidth, 10);
                if (!isNaN(num)) {
                    targetWidth = num;
                    targetHeight = height || 900;
                }
            }
        }

        this.currentViewport = { width: targetWidth, height: targetHeight };
        await page.setViewportSize(this.currentViewport);
        return this.getStatus();
    }

    /**
     * Capture screenshot and save to artifacts/browser/interactive/screenshots.
     */
    async screenshot(options: { name?: string; fullPage?: boolean } = {}): Promise<{ filePath: string; relativePath: string; status: ControllerStatus }> {
        const page = this.getActivePage();
        const timestamp = new Date().toISOString().replace(/[:.]/g, '-');
        const customName = options.name ? options.name.replace(/[^a-zA-Z0-9_-]/g, '_') : 'interactive_qa';
        const filename = `${customName}_${timestamp}.png`;
        const filePath = path.join(this.screenshotsDir, filename);

        await page.screenshot({ path: filePath, fullPage: options.fullPage || false });
        this.lastScreenshotPath = filePath;

        const relativePath = path.relative(process.cwd(), filePath);
        const status = await this.getStatus();
        return { filePath, relativePath, status };
    }

    /**
     * Open a new browser tab and focus it.
     */
    async newTab(urlOrPath?: string): Promise<ControllerStatus> {
        this.ensureRunning();
        const newPage = await this.context!.newPage();
        this.trackNewPage(newPage);
        this.activePageIndex = this.pages.indexOf(newPage);

        if (this.currentViewport) {
            await newPage.setViewportSize(this.currentViewport);
        }

        if (urlOrPath) {
            let targetUrl = urlOrPath;
            if (!urlOrPath.startsWith('http://') && !urlOrPath.startsWith('https://')) {
                const cleanPath = urlOrPath.startsWith('/') ? urlOrPath : `/${urlOrPath}`;
                targetUrl = `${this.baseUrl}${cleanPath}`;
            }
            await safeGoto(newPage, targetUrl);
        }

        return this.getStatus();
    }

    /**
     * Switch active tab by index.
     */
    async switchTab(index: number): Promise<ControllerStatus> {
        this.ensureRunning();
        if (index < 0 || index >= this.pages.length) {
            throw new Error(`[InteractiveBrowser] Tab index ${index} out of range (Total tabs: ${this.pages.length}).`);
        }
        this.activePageIndex = index;
        const page = this.pages[index];
        await page.bringToFront();
        return this.getStatus();
    }

    /**
     * Close a tab by index (or current tab).
     */
    async closeTab(index?: number): Promise<ControllerStatus> {
        this.ensureRunning();
        const targetIndex = index !== undefined ? index : this.activePageIndex;
        if (targetIndex < 0 || targetIndex >= this.pages.length) {
            throw new Error(`[InteractiveBrowser] Tab index ${targetIndex} out of range.`);
        }

        const pageToClose = this.pages[targetIndex];
        await pageToClose.close();

        return this.getStatus();
    }

    /**
     * List all open tabs.
     */
    async listTabs(): Promise<TabInfo[]> {
        const tabs: TabInfo[] = [];
        for (let i = 0; i < this.pages.length; i++) {
            const p = this.pages[i];
            let pageTitle = 'Unknown';
            try {
                pageTitle = await p.title();
            } catch {
                pageTitle = 'Closed';
            }
            tabs.push({
                index: i,
                title: pageTitle,
                url: p.url(),
                isActive: i === this.activePageIndex,
            });
        }
        return tabs;
    }

    /**
     * Reload current page.
     */
    async reload(): Promise<ControllerStatus> {
        const page = this.getActivePage();
        await page.reload({ waitUntil: 'domcontentloaded' });
        return this.getStatus();
    }

    /**
     * Navigate back in history.
     */
    async back(): Promise<ControllerStatus> {
        const page = this.getActivePage();
        await page.goBack({ waitUntil: 'domcontentloaded' });
        return this.getStatus();
    }

    /**
     * Navigate forward in history.
     */
    async forward(): Promise<ControllerStatus> {
        const page = this.getActivePage();
        await page.goForward({ waitUntil: 'domcontentloaded' });
        return this.getStatus();
    }

    /**
     * Get live diagnostics for active page.
     */
    getDiagnostics(): BrowserDiagnostics {
        const page = this.getActivePage();
        const collector = this.pageCollectors.get(page);
        if (collector) {
            return collector.getDiagnostics();
        }
        return {
            url: page.url(),
            title: '',
            timestamp: new Date().toISOString(),
            consoleLogs: [],
            consoleErrors: [],
            networkErrors: [],
        };
    }

    /**
     * Get current controller status.
     */
    async getStatus(): Promise<ControllerStatus> {
        const browser = this.resolvedBrowser || resolveBrowser();
        let currentUrl = '';
        let currentTitle = '';
        let tabs: TabInfo[] = [];

        if (this.isRunning && this.pages.length > 0) {
            try {
                const page = this.getActivePage();
                currentUrl = page.url();
                currentTitle = await page.title();
                tabs = await this.listTabs();
            } catch {
                // Page may be transitioning
            }
        }

        const diagnostics = this.isRunning && this.pages.length > 0 ? this.getDiagnostics() : null;

        let environment: ControllerStatus['environment'] = 'LOCAL';
        if (this.baseUrl.includes('preprod') || this.baseUrl.includes('staging')) {
            environment = 'PRE-PRODUCTION';
        } else if (this.baseUrl.includes('unique-distributors.com') || this.baseUrl.includes('production')) {
            environment = 'PRODUCTION';
        } else if (!this.baseUrl.includes('localhost') && !this.baseUrl.includes('127.0.0.1')) {
            environment = 'CUSTOM';
        }

        return {
            isRunning: this.isRunning,
            browserName: browser.browserName,
            browserVersion: browser.version,
            executablePath: browser.executablePath,
            baseUrl: this.baseUrl,
            environment,
            activeTabIndex: this.activePageIndex,
            totalTabs: this.pages.length,
            tabs,
            currentUrl,
            title: currentTitle,
            viewport: this.currentViewport,
            diagnosticsSummary: {
                consoleErrorsCount: diagnostics ? diagnostics.consoleErrors.length : 0,
                networkErrorsCount: diagnostics ? diagnostics.networkErrors.length : 0,
            },
            lastScreenshotPath: this.lastScreenshotPath,
        };
    }

    /**
     * Stop the browser session cleanly.
     */
    async stop(): Promise<void> {
        if (!this.isRunning) {
            return;
        }

        if (this.context) {
            if (process.env.BROWSER_TRACE === 'true') {
                const tracesDir = path.join(this.artifactsDir, 'traces');
                fs.mkdirSync(tracesDir, { recursive: true });
                const tracePath = path.join(tracesDir, `trace_${new Date().toISOString().replace(/[:.]/g, '-')}.zip`);
                try {
                    await this.context.tracing.stop({ path: tracePath });
                } catch {
                    // Ignore trace close error
                }
            }
            try {
                await this.context.close();
            } catch {}
        }

        if (this.browser) {
            try {
                await this.browser.close();
            } catch {}
        }

        this.pages = [];
        this.pageCollectors.clear();
        this.browser = null;
        this.context = null;
        this.isRunning = false;
    }

    private ensureRunning(): void {
        if (!this.isRunning || !this.context) {
            throw new Error('[InteractiveBrowser] Browser session is not active. Call start() first.');
        }
    }
}
