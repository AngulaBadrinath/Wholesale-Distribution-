import { chromium } from '@playwright/test';
import type { Browser, BrowserContext, Page } from '@playwright/test';
import fs from 'fs';
import path from 'path';
import { resolveBrowser, type ResolvedBrowser } from '../resolver.ts';
import { VIEWPORT_MATRIX, type ViewportConfig } from '../viewports.ts';
import { loginAs, type UserRole, QA_USER_CREDENTIALS } from '../helpers/auth.ts';
import { checkCdpEndpoint, launchDedicatedChrome } from './launch-chrome.ts';
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

export interface InteractiveElementInfo {
    tagName: string;
    type?: string;
    name?: string;
    id?: string;
    selector?: string;
    text?: string;
    label?: string;
    placeholder?: string;
    value?: string;
    role?: string;
    ariaLabel?: string;
    checked?: boolean;
    disabled?: boolean;
    href?: string;
}

export interface PageObservation {
    url: string;
    title: string;
    viewport: { width: number; height: number } | null;
    activeTabIndex: number;
    totalTabs: number;
    headings: string[];
    buttons: InteractiveElementInfo[];
    inputs: InteractiveElementInfo[];
    selects: InteractiveElementInfo[];
    links: InteractiveElementInfo[];
    alerts: string[];
    tables: Array<{ headers: string[]; rowCount: number; sampleRows?: string[][] }>;
    visibleTextExcerpt: string;
    diagnosticsSummary: {
        consoleErrorsCount: number;
        networkErrorsCount: number;
        recentErrors: string[];
    };
    screenshotPath?: string;
    screenshotRelativePath?: string;
}

/**
 * Redact sensitive fields, passwords, auth tokens, and secrets.
 */
export function redactSensitiveData<T>(data: T): T {
    if (!data) return data;
    if (typeof data === 'string') {
        return data
            .replace(/(password|token|secret|totp|pin|cvv)=([^& \s]+)/gi, '$1=[REDACTED]')
            .replace(/(Bearer\s+)[A-Za-z0-9\-_.]+/gi, '$1[REDACTED]')
            .replace(/(X-Amz-Signature=[A-Za-z0-9]+)/gi, 'X-Amz-Signature=[REDACTED]') as unknown as T;
    }
    if (Array.isArray(data)) {
        return data.map((item) => redactSensitiveData(item)) as unknown as T;
    }
    if (typeof data === 'object') {
        const copy: any = {};
        for (const [key, value] of Object.entries(data)) {
            const lowerKey = key.toLowerCase();
            if (
                lowerKey.includes('password') ||
                lowerKey.includes('secret') ||
                lowerKey.includes('token') ||
                lowerKey.includes('totp') ||
                lowerKey.includes('cookie') ||
                lowerKey === 'authorization'
            ) {
                copy[key] = '[REDACTED]';
            } else {
                copy[key] = redactSensitiveData(value);
            }
        }
        return copy as T;
    }
    return data;
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
     * Start the persistent real headed browser session by attaching to the visible Chrome via CDP.
     * Guarantees ONE single visible Chrome instance across all tools.
     */
    async start(options: LaunchOptions = {}): Promise<ControllerStatus> {
        if (this.isRunning && this.browser && this.context) {
            this.syncPages();
            return this.getStatus();
        }

        if (options.baseUrl) {
            this.baseUrl = options.baseUrl;
        }

        const port = parseInt(process.env.CHROME_DEBUG_PORT || '9222', 10);
        const cdpUrl = `http://127.0.0.1:${port}`;

        // 1. Ensure the dedicated visible Chrome instance is running on port 9222
        const cdpCheck = await checkCdpEndpoint(port);
        if (!cdpCheck.isRunning) {
            await launchDedicatedChrome({ port });
        }

        this.resolvedBrowser = resolveBrowser();

        // 2. Connect Playwright over CDP to the SAME visible Chrome window
        this.browser = await chromium.connectOverCDP(cdpUrl);
        const contexts = this.browser.contexts();
        this.context = contexts[0] || (await this.browser.newContext({ ignoreHTTPSErrors: true }));

        // 3. Track all existing pages in the visible Chrome instance
        this.pages = [];
        this.pageCollectors.clear();
        this.syncPages();

        if (this.pages.length === 0) {
            const firstPage = await this.context.newPage();
            this.trackNewPage(firstPage);
            this.activePageIndex = 0;
        }

        // Listen for new tabs opened by user or scripts
        this.context.on('page', (newPage) => {
            this.trackNewPage(newPage);
        });

        this.isRunning = true;
        const activePage = this.getActivePage();

        if (options.viewport) {
            this.currentViewport = options.viewport;
            await activePage.setViewportSize(options.viewport);
        }

        if (options.startUrl) {
            this.checkDomainAllowed(options.startUrl);
            await safeGoto(activePage, options.startUrl);
        }

        return this.getStatus();
    }

    /**
     * Re-synchronize tracked pages with live browser context tabs.
     */
    private syncPages(): void {
        if (!this.context) return;
        const currentPages = this.context.pages();
        for (const p of currentPages) {
            if (!this.pages.includes(p)) {
                this.trackNewPage(p);
            }
        }
        // Remove closed or stale pages
        this.pages = this.pages.filter((p) => {
            try {
                return !p.isClosed() && currentPages.includes(p);
            } catch {
                return false;
            }
        });
        if (this.activePageIndex >= this.pages.length) {
            this.activePageIndex = Math.max(0, this.pages.length - 1);
        }
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
        this.syncPages();
        if (this.pages.length === 0) {
            throw new Error('[InteractiveBrowser] No open tabs found in the active session.');
        }
        if (this.activePageIndex < 0 || this.activePageIndex >= this.pages.length) {
            this.activePageIndex = 0;
        }
        return this.pages[this.activePageIndex];
    }

    /**
     * Verify URL against allowed domain policy.
     */
    checkDomainAllowed(urlOrPath: string): void {
        if (process.env.ALLOW_EXTERNAL_NAVIGATION === 'true') {
            return;
        }
        if (!urlOrPath.startsWith('http://') && !urlOrPath.startsWith('https://')) {
            return; // Relative path is local by definition
        }
        try {
            const parsed = new URL(urlOrPath);
            const host = parsed.hostname.toLowerCase();
            let baseHost = 'localhost';
            try {
                baseHost = new URL(this.baseUrl).hostname.toLowerCase();
            } catch {}

            const allowedHosts = ['localhost', '127.0.0.1', '0.0.0.0', baseHost];
            const isAllowed = allowedHosts.some((allowed) => host === allowed || host.endsWith('.' + allowed));
            if (!isAllowed) {
                throw new Error(
                    `[Security Policy] Navigation to external domain "${host}" is blocked. Allowed domains: ${allowedHosts.join(', ')}.`
                );
            }
        } catch (err: any) {
            if (err.message.includes('[Security Policy]')) throw err;
        }
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

        this.checkDomainAllowed(targetUrl);
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
            targetHeight =
                height ||
                (presetOrWidth === 320
                    ? 568
                    : presetOrWidth === 375
                    ? 667
                    : presetOrWidth === 390
                    ? 844
                    : presetOrWidth === 430
                    ? 932
                    : presetOrWidth === 640
                    ? 800
                    : presetOrWidth === 768
                    ? 1024
                    : presetOrWidth === 820
                    ? 1180
                    : presetOrWidth === 1024
                    ? 768
                    : presetOrWidth === 1280
                    ? 800
                    : presetOrWidth === 1440
                    ? 900
                    : 1080);
        } else {
            const key = presetOrWidth.toLowerCase().trim();
            const aliasMap: Record<string, string> = {
                mobile_s: 'mobile_s_320',
                mobile_320: 'mobile_s_320',
                mobile_m: 'mobile_m_375',
                mobile_375: 'mobile_m_375',
                mobile: 'mobile_standard_390',
                mobile_390: 'mobile_standard_390',
                mobile_standard: 'mobile_standard_390',
                phone: 'mobile_standard_390',
                mobile_l: 'mobile_max_430',
                mobile_430: 'mobile_max_430',
                mobile_max: 'mobile_max_430',
                small_tablet: 'small_tablet_640',
                tablet_640: 'small_tablet_640',
                tablet: 'tablet_portrait_768',
                tablet_768: 'tablet_portrait_768',
                tablet_portrait: 'tablet_portrait_768',
                ipad: 'tablet_portrait_768',
                tablet_l: 'tablet_air_820',
                tablet_820: 'tablet_air_820',
                tablet_air: 'tablet_air_820',
                desktop_sm: 'desktop_standard_1024',
                desktop_1024: 'desktop_standard_1024',
                desktop_standard: 'desktop_standard_1024',
                desktop_md: 'desktop_large_1280',
                desktop_1280: 'desktop_large_1280',
                desktop_large: 'desktop_large_1280',
                laptop: 'desktop_large_1280',
                desktop: 'desktop_xl_1440',
                desktop_1440: 'desktop_xl_1440',
                desktop_xl: 'desktop_xl_1440',
                desktop_fhd: 'desktop_fhd_1920',
                desktop_1920: 'desktop_fhd_1920',
                fhd: 'desktop_fhd_1920',
                '1920': 'desktop_fhd_1920',
            };

            const mappedKey = aliasMap[key] || key;
            if (VIEWPORT_MATRIX[mappedKey]) {
                targetWidth = VIEWPORT_MATRIX[mappedKey].width;
                targetHeight = VIEWPORT_MATRIX[mappedKey].height;
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
    async screenshot(
        options: { name?: string; fullPage?: boolean } = {}
    ): Promise<{ filePath: string; relativePath: string; status: ControllerStatus }> {
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
     * Inspect and observe the current page in a compact, structured semantic form.
     */
    async observe(
        options: { detailLevel?: 'summary' | 'detailed'; captureScreenshot?: boolean } = {}
    ): Promise<PageObservation> {
        this.ensureRunning();
        const page = this.getActivePage();
        const url = page.url();
        const title = await page.title().catch(() => '');

        // Extract semantic page details directly from the live DOM
        const domDetails = await page.evaluate(() => {
            const isVisible = (el: Element): boolean => {
                const style = window.getComputedStyle(el);
                if (style.display === 'none' || style.visibility === 'hidden' || style.opacity === '0') {
                    return false;
                }
                const rect = el.getBoundingClientRect();
                return rect.width > 0 && rect.height > 0;
            };

            // Headings
            const headingEls = Array.from(document.querySelectorAll('h1, h2, h3, h4')).filter(isVisible);
            const headings = headingEls.map((h) => `${h.tagName}: ${(h.textContent || '').trim()}`).filter(Boolean);

            // Buttons
            const buttonEls = Array.from(
                document.querySelectorAll('button, input[type="button"], input[type="submit"], [role="button"]')
            ).filter(isVisible);
            const buttons = buttonEls.slice(0, 30).map((b) => {
                const el = b as HTMLElement;
                const text = (el.innerText || el.getAttribute('value') || el.getAttribute('aria-label') || '').trim();
                const disabled = (el as HTMLButtonElement).disabled || el.getAttribute('aria-disabled') === 'true';
                const id = el.id ? `#${el.id}` : '';
                const role = el.getAttribute('role') || el.tagName.toLowerCase();
                return {
                    tagName: el.tagName.toLowerCase(),
                    id: el.id || undefined,
                    selector: id || undefined,
                    text: text.slice(0, 80),
                    role,
                    disabled,
                };
            });

            // Inputs
            const inputEls = Array.from(
                document.querySelectorAll('input:not([type="button"]):not([type="submit"]):not([type="hidden"]), textarea')
            ).filter(isVisible);
            const inputs = inputEls.slice(0, 30).map((inp) => {
                const el = inp as HTMLInputElement;
                const type = el.type || 'text';
                const isSecret =
                    type === 'password' ||
                    /password|secret|token|totp|pin|cvv/i.test(el.name || '') ||
                    /password|secret|token|totp|pin|cvv/i.test(el.id || '');

                let label = '';
                if (el.id) {
                    const labelEl = document.querySelector(`label[for="${el.id}"]`);
                    if (labelEl) label = (labelEl.textContent || '').trim();
                }
                if (!label && el.closest('label')) {
                    label = (el.closest('label')?.textContent || '').trim();
                }

                return {
                    tagName: el.tagName.toLowerCase(),
                    type,
                    name: el.name || undefined,
                    id: el.id || undefined,
                    label: label || undefined,
                    placeholder: el.placeholder || undefined,
                    value: isSecret ? '[REDACTED]' : el.value ? el.value.slice(0, 100) : undefined,
                    disabled: el.disabled,
                    checked: el.type === 'checkbox' || el.type === 'radio' ? el.checked : undefined,
                };
            });

            // Selects
            const selectEls = Array.from(document.querySelectorAll('select')).filter(isVisible);
            const selects = selectEls.slice(0, 15).map((sel) => {
                const el = sel as HTMLSelectElement;
                let label = '';
                if (el.id) {
                    const labelEl = document.querySelector(`label[for="${el.id}"]`);
                    if (labelEl) label = (labelEl.textContent || '').trim();
                }
                const selectedOption = el.options[el.selectedIndex];
                return {
                    tagName: 'select',
                    name: el.name || undefined,
                    id: el.id || undefined,
                    label: label || undefined,
                    value: selectedOption ? selectedOption.text : el.value,
                    disabled: el.disabled,
                };
            });

            // Navigation Links
            const linkEls = Array.from(document.querySelectorAll('a[href]')).filter(isVisible);
            const links = linkEls.slice(0, 30).map((a) => {
                const el = a as HTMLAnchorElement;
                return {
                    tagName: 'a',
                    text: (el.innerText || el.getAttribute('aria-label') || '').trim().slice(0, 60),
                    href: el.getAttribute('href') || '',
                };
            });

            // Visible Alerts & Validation Errors
            const alertEls = Array.from(
                document.querySelectorAll(
                    '[role="alert"], .alert, .text-destructive, [aria-invalid="true"], .error-message, .validation-error'
                )
            ).filter(isVisible);
            const alerts = alertEls
                .map((a) => (a.textContent || '').trim())
                .filter((txt) => txt.length > 0 && txt.length < 300)
                .slice(0, 10);

            // Tables summary
            const tableEls = Array.from(document.querySelectorAll('table')).filter(isVisible);
            const tables = tableEls.slice(0, 5).map((tbl) => {
                const ths = Array.from(tbl.querySelectorAll('th')).map((th) => (th.textContent || '').trim());
                const rows = Array.from(tbl.querySelectorAll('tbody tr'));
                const sampleRows = rows.slice(0, 3).map((r) =>
                    Array.from(r.querySelectorAll('td'))
                        .map((td) => (td.textContent || '').trim().slice(0, 40))
                        .slice(0, 6)
                );
                return {
                    headers: ths.slice(0, 10),
                    rowCount: rows.length,
                    sampleRows: sampleRows.length > 0 ? sampleRows : undefined,
                };
            });

            // Visible Text Excerpt
            const rawBody = (document.body.innerText || '').replace(/\s+/g, ' ').trim();
            const visibleTextExcerpt = rawBody.slice(0, 1000);

            return {
                headings,
                buttons,
                inputs,
                selects,
                links,
                alerts,
                tables,
                visibleTextExcerpt,
            };
        });

        const diagnostics = this.getDiagnostics();
        const recentErrors = diagnostics.consoleErrors.map((e) => e.text).slice(-5);

        let screenshotPath: string | undefined;
        let screenshotRelativePath: string | undefined;

        if (options.captureScreenshot) {
            const shot = await this.screenshot({ name: 'observe_snapshot' });
            screenshotPath = shot.filePath;
            screenshotRelativePath = shot.relativePath;
        }

        return {
            url,
            title,
            viewport: this.currentViewport,
            activeTabIndex: this.activePageIndex,
            totalTabs: this.pages.length,
            headings: domDetails.headings,
            buttons: options.detailLevel === 'summary' ? domDetails.buttons.slice(0, 15) : domDetails.buttons,
            inputs: options.detailLevel === 'summary' ? domDetails.inputs.slice(0, 15) : domDetails.inputs,
            selects: domDetails.selects,
            links: options.detailLevel === 'summary' ? domDetails.links.slice(0, 15) : domDetails.links,
            alerts: domDetails.alerts,
            tables: domDetails.tables,
            visibleTextExcerpt: domDetails.visibleTextExcerpt,
            diagnosticsSummary: {
                consoleErrorsCount: diagnostics.consoleErrors.length,
                networkErrorsCount: diagnostics.networkErrors.length,
                recentErrors,
            },
            screenshotPath,
            screenshotRelativePath,
        };
    }

    /**
     * Execute a sequence of structured actions safely without eval.
     */
    async executeSequence(
        steps: Array<{ action: string; [key: string]: any }>
    ): Promise<{ results: any[]; finalStatus: ControllerStatus }> {
        const results = [];
        for (let i = 0; i < steps.length; i++) {
            const step = steps[i];
            const action = step.action;
            const params = { ...step };
            delete params.action;

            let stepResult: any;
            switch (action) {
                case 'navigate':
                case 'goto':
                    stepResult = await this.navigate(params.url || params.path || params.urlOrPath);
                    break;
                case 'click':
                    stepResult = await this.click(params.selector, params.timeout);
                    break;
                case 'clickText':
                    stepResult = await this.clickText(params.text, params.exact, params.timeout);
                    break;
                case 'clickRole':
                    stepResult = await this.clickRole(params.role, params.name, params.timeout);
                    break;
                case 'fill':
                    stepResult = await this.fill(params.selector, params.value, params.timeout);
                    break;
                case 'fillLabel':
                    stepResult = await this.fillLabel(params.label, params.value, params.timeout);
                    break;
                case 'select':
                    stepResult = await this.select(params.selector, params.value || params.valueOrLabel, params.timeout);
                    break;
                case 'check':
                    stepResult = await this.check(params.selector, params.timeout);
                    break;
                case 'uncheck':
                    stepResult = await this.uncheck(params.selector, params.timeout);
                    break;
                case 'press':
                    stepResult = await this.press(params.selector || 'body', params.key, params.timeout);
                    break;
                case 'waitFor':
                    stepResult = await this.waitFor(params.selector, params.timeout);
                    break;
                case 'wait':
                    stepResult = await this.wait(params.ms || 1000);
                    break;
                case 'setViewport':
                case 'viewport':
                    stepResult = await this.setViewport(params.preset || params.width, params.height);
                    break;
                case 'screenshot':
                    stepResult = await this.screenshot({ name: params.name, fullPage: params.fullPage });
                    break;
                case 'reload':
                    stepResult = await this.reload();
                    break;
                case 'back':
                    stepResult = await this.back();
                    break;
                case 'forward':
                    stepResult = await this.forward();
                    break;
                default:
                    throw new Error(`Unsupported sequence action: "${action}" at step index ${i}.`);
            }
            results.push({ step: i, action, success: true, result: stepResult });
        }
        const finalStatus = await this.getStatus();
        return { results, finalStatus };
    }

    /**
     * Execute a potentially destructive action only after explicit confirmation.
     */
    async confirmDestructiveAction(options: {
        actionDescription: string;
        confirmed: boolean;
        action?: { action: string; [key: string]: any };
    }): Promise<{ confirmed: boolean; executed: boolean; message: string; result?: any }> {
        if (!options.confirmed) {
            return {
                confirmed: false,
                executed: false,
                message: `Action "${options.actionDescription}" requires explicit confirmation. Please confirm with the user before executing.`,
            };
        }

        if (options.action) {
            const seq = await this.executeSequence([options.action]);
            return {
                confirmed: true,
                executed: true,
                message: `Action "${options.actionDescription}" was confirmed and executed successfully.`,
                result: seq.results[0]?.result,
            };
        }

        return {
            confirmed: true,
            executed: false,
            message: `Action "${options.actionDescription}" is confirmed.`,
        };
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
            this.checkDomainAllowed(targetUrl);
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

        if (this.pages.length <= 1) {
            throw new Error(`[InteractiveBrowser] Cannot close the only remaining active tab. Use browser_stop to close browser.`);
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
