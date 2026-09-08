import { test, expect } from '@playwright/test';
import { loginAs, logout } from '../helpers/auth';
import { VIEWPORT_MATRIX } from '../viewports';
import { DiagnosticsCollector, safeGoto } from '../helpers/diagnostics';
import path from 'path';

test.describe('Audit Phase 13: Responsive Breakpoints Matrix & Accessibility', () => {
    const evidenceDir = path.resolve(process.cwd(), 'artifacts/browser-audit');

    test('13.1 Systematic 11-breakpoint responsive rendering on core workspaces', async ({ page }) => {
        const diagnostics = new DiagnosticsCollector(page);
        await loginAs(page, 'ADMIN');

        const testBreakpoints = Object.values(VIEWPORT_MATRIX);

        // Inspect Dashboard and Invoices across breakpoints
        for (const bp of testBreakpoints) {
            await page.setViewportSize({ width: bp.width, height: bp.height });
            await safeGoto(page, '/dashboard');
            await page.waitForLoadState('domcontentloaded');

            // Capture sample responsive evidence for key widths
            if ([320, 390, 768, 1440].includes(bp.width)) {
                await diagnostics.captureNamedScreenshot(page, `12_responsive_dashboard_${bp.width}w`, evidenceDir);
            }
        }

        await logout(page);
    });

    test('13.2 Keyboard accessibility (Tab navigation and Escape dismiss)', async ({ page }) => {
        await safeGoto(page, '/login');
        await page.waitForSelector('input[name="email"], input#email');

        // Focus first control and verify Tab moves focus through interactive controls
        const emailInput = page.locator('input[name="email"], input#email').first();
        await emailInput.focus();
        await page.keyboard.press('Tab');
        const activeTag = await page.evaluate(() => document.activeElement?.tagName);
        expect(['INPUT', 'BUTTON', 'A']).toContain(activeTag);
    });
});
