import { test, expect } from '@playwright/test';
import fs from 'fs';
import path from 'path';
import { resolveBrowser } from '../resolver.ts';
import { InteractiveBrowserController } from './controller.ts';
import { VIEWPORT_PRESETS } from '../viewports.ts';

test.describe('Interactive Browser QA Runner Infrastructure Suite', () => {
    let controller: InteractiveBrowserController;

    test.afterEach(async () => {
        if (controller) {
            await controller.stop().catch(() => {});
        }
    });

    test('1. Local Chrome/Chromium discovery resolves without CDN download', async () => {
        const resolved = resolveBrowser();
        expect(resolved.executablePath).toBeTruthy();
        expect(fs.existsSync(resolved.executablePath)).toBe(true);
        expect(resolved.browserName).toBeTruthy();
        expect(resolved.version).not.toBe('Version unknown');
        // Must NOT point to Playwright cache/CDN download paths
        expect(resolved.executablePath.toLowerCase()).not.toContain('ms-playwright');
        console.log(`[Discovery Test] Resolved local browser: ${resolved.browserName} (${resolved.version}) at ${resolved.executablePath}`);
    });

    test('2. PLAYWRIGHT_BROWSER_PATH override is respected', async () => {
        const currentPath = resolveBrowser().executablePath;
        const previousEnv = process.env.PLAYWRIGHT_BROWSER_PATH;
        try {
            process.env.PLAYWRIGHT_BROWSER_PATH = currentPath;
            const resolved = resolveBrowser();
            expect(resolved.executablePath).toBe(path.resolve(currentPath));
            expect(resolved.source).toBe('env');
        } finally {
            if (previousEnv !== undefined) {
                process.env.PLAYWRIGHT_BROWSER_PATH = previousEnv;
            } else {
                delete process.env.PLAYWRIGHT_BROWSER_PATH;
            }
        }
    });

    test('3. PLAYWRIGHT_BASE_URL override is respected', async () => {
        const customBaseUrl = 'http://127.0.0.1:8000';
        controller = new InteractiveBrowserController();
        const status = await controller.start({
            baseUrl: customBaseUrl,
            headed: false, // Run headless for automated CI / fast verification
        });
        expect(status.baseUrl).toBe(customBaseUrl);
        expect(status.environment).toBe('LOCAL');
    });

    test('4. Browser launches and navigates to base URL /login', async () => {
        controller = new InteractiveBrowserController();
        await controller.start({ headed: false });

        const navResult = await controller.navigate('/login');
        expect(navResult.currentUrl).toContain('/login');

        const status = await controller.getStatus();
        expect(status.isRunning).toBe(true);
        expect(status.currentUrl).toContain('/login');
        expect(status.title).toBeTruthy();
    });

    test('5. Persistent session: single context survives multiple sequential actions', async () => {
        controller = new InteractiveBrowserController();
        await controller.start({ headed: false });

        // Action 1: Navigate to login
        await controller.navigate('/login');
        let status = await controller.getStatus();
        expect(status.currentUrl).toContain('/login');

        // Action 2: Check form input visibility
        await controller.waitFor('input[name="email"], input[type="email"]');

        // Action 3: Fill email
        await controller.fill('input[name="email"], input[type="email"]', 'admin@uniquedistributors.com');

        // Action 4: Fill password
        await controller.fill('input[name="password"], input[type="password"]', 'TestAdmin123!');

        // Action 5: Get status — browser context and page remain identical
        status = await controller.getStatus();
        expect(status.totalTabs).toBe(1);
        expect(status.currentUrl).toContain('/login');
    });

    test('6. Multi-tab support: new tab, switch tab, close tab, list tabs', async () => {
        controller = new InteractiveBrowserController();
        await controller.start({ headed: false });

        // Tab 0
        await controller.navigate('/login');

        // Tab 1 (newTab)
        const tab1 = await controller.newTab('/login');
        expect(tab1.activeTabIndex).toBe(1);
        expect(tab1.totalTabs).toBe(2);

        // List tabs
        const tabs = await controller.listTabs();
        expect(tabs.length).toBe(2);
        expect(tabs[1].isActive).toBe(true);

        // Switch to Tab 0
        const switchResult = await controller.switchTab(0);
        expect(switchResult.activeTabIndex).toBe(0);

        // Close Tab 1
        const closeResult = await controller.closeTab(1);
        expect(closeResult.totalTabs).toBe(1);
        expect(closeResult.activeTabIndex).toBe(0);
    });

    test('7. Viewport switching with project presets (mobile, tablet, desktop)', async () => {
        controller = new InteractiveBrowserController();
        await controller.start({ headed: false });
        await controller.navigate('/login');

        // Mobile preset (390px)
        const mobileResult = await controller.setViewport('mobile');
        expect(mobileResult.viewport?.width).toBe(VIEWPORT_PRESETS.mobile.width);
        expect(mobileResult.viewport?.height).toBe(VIEWPORT_PRESETS.mobile.height);

        // Tablet preset (768px)
        const tabletResult = await controller.setViewport('tablet');
        expect(tabletResult.viewport?.width).toBe(VIEWPORT_PRESETS.tablet.width);
        expect(tabletResult.viewport?.height).toBe(VIEWPORT_PRESETS.tablet.height);

        // Desktop preset (1440px)
        const desktopResult = await controller.setViewport('desktop');
        expect(desktopResult.viewport?.width).toBe(VIEWPORT_PRESETS.desktop.width);
        expect(desktopResult.viewport?.height).toBe(VIEWPORT_PRESETS.desktop.height);

        // Custom numeric viewport
        const customResult = await controller.setViewport(1280, 800);
        expect(customResult.viewport?.width).toBe(1280);
        expect(customResult.viewport?.height).toBe(800);
    });

    test('8. Screenshot capture saves to artifacts/browser/interactive/ directory', async () => {
        controller = new InteractiveBrowserController();
        await controller.start({ headed: false });
        await controller.navigate('/login');

        const screenshotResult = await controller.screenshot('test_runner_login');
        expect(screenshotResult.filePath).toBeTruthy();
        expect(fs.existsSync(screenshotResult.filePath)).toBe(true);
        expect(screenshotResult.relativePath).toContain('artifacts');
        expect(screenshotResult.relativePath).toContain('screenshots');
    });

    test('9. Continuous diagnostics capture console errors and network failures', async () => {
        controller = new InteractiveBrowserController();
        await controller.start({ headed: false });
        await controller.navigate('/login');

        const diagnostics = controller.getDiagnostics();
        expect(diagnostics).toHaveProperty('url');
        expect(diagnostics).toHaveProperty('consoleErrors');
        expect(diagnostics).toHaveProperty('consoleLogs');
        expect(diagnostics).toHaveProperty('networkErrors');
        expect(Array.isArray(diagnostics.consoleErrors)).toBe(true);
        expect(Array.isArray(diagnostics.networkErrors)).toBe(true);
    });

    test('10. Clean shutdown closes browser context and releases resources', async () => {
        controller = new InteractiveBrowserController();
        await controller.start({ headed: false });
        expect(controller.getStatus().then((s) => s.isRunning)).resolves.toBe(true);

        await controller.stop();
        const status = await controller.getStatus();
        expect(status.isRunning).toBe(false);
    });
});
