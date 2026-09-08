import { test, expect } from '@playwright/test';
import { resolveBrowser } from './resolver';
import { VIEWPORT_PRESETS } from './viewports';
import { loginAs, logout } from './helpers/auth';
import { attachDiagnosticsCollector } from './helpers/diagnostics';

test.describe('Permanent Browser Verification Harness Setup', () => {
    test('1. Local browser discovery resolver returns valid local executable', async () => {
        const browserInfo = resolveBrowser();
        expect(browserInfo.executablePath).toBeTruthy();
        expect(browserInfo.browserName).toBeTruthy();
        expect(browserInfo.version).not.toBe('Version unknown');
        console.log(`[Test] Detected: ${browserInfo.browserName} v${browserInfo.version} at ${browserInfo.executablePath}`);
    });

    test('2. Playwright launches real local browser and renders login page with diagnostics', async ({ page }) => {
        const { getDiagnostics, saveDiagnostics } = attachDiagnosticsCollector(page);

        await page.goto('/login');
        await page.waitForLoadState('domcontentloaded');

        // Check page title and DOM elements
        const title = await page.title();
        expect(title).toBeTruthy();

        const emailInput = page.locator('input[type="email"], input[name="email"]');
        await expect(emailInput).toBeVisible();

        const passwordInput = page.locator('input[type="password"], input[name="password"]');
        await expect(passwordInput).toBeVisible();

        // Capture evidence screenshot and diagnostic report
        const diagPath = await saveDiagnostics('setup_login_verification', true, true);
        expect(diagPath).toBeTruthy();

        const diag = getDiagnostics();
        expect(diag.url).toContain('/login');
    });

    test('3. Responsive layout renders properly on mobile, tablet, and desktop viewports', async ({ page }) => {
        // Mobile
        await page.setViewportSize({ width: VIEWPORT_PRESETS.mobile.width, height: VIEWPORT_PRESETS.mobile.height });
        await page.goto('/login');
        await page.waitForLoadState('domcontentloaded');
        const mobileEmail = page.locator('input[type="email"], input[name="email"]');
        await expect(mobileEmail).toBeVisible();

        // Tablet
        await page.setViewportSize({ width: VIEWPORT_PRESETS.tablet.width, height: VIEWPORT_PRESETS.tablet.height });
        await page.goto('/login');
        await page.waitForLoadState('domcontentloaded');
        const tabletEmail = page.locator('input[type="email"], input[name="email"]');
        await expect(tabletEmail).toBeVisible();

        // Desktop
        await page.setViewportSize({ width: VIEWPORT_PRESETS.desktop.width, height: VIEWPORT_PRESETS.desktop.height });
        await page.goto('/login');
        await page.waitForLoadState('domcontentloaded');
        const desktopEmail = page.locator('input[type="email"], input[name="email"]');
        await expect(desktopEmail).toBeVisible();
    });

    test('4. Authenticated workflow succeeds for Admin and Salesman', async ({ page }) => {
        const { saveDiagnostics } = attachDiagnosticsCollector(page);

        // Login as Admin
        await loginAs(page, 'ADMIN');
        await expect(page).toHaveURL(/\/dashboard/);
        await saveDiagnostics('setup_admin_dashboard_auth', true, false);

        // Logout
        await logout(page);
        await expect(page).toHaveURL(/\/login/);

        // Login as Salesman
        await loginAs(page, 'SALESMAN');
        await expect(page).toHaveURL(/\/dashboard/);
        await saveDiagnostics('setup_salesman_dashboard_auth', true, false);
    });
});
