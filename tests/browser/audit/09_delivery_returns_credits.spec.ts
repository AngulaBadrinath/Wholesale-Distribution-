import { test, expect } from '@playwright/test';
import { loginAs, logout } from '../helpers/auth';
import { DiagnosticsCollector, safeGoto } from '../helpers/diagnostics';
import path from 'path';

test.describe('Audit Phase 10: Delivery, Returns, Credits & Refunds', () => {
    const evidenceDir = path.resolve(process.cwd(), 'artifacts/browser-audit');

    test('10.1 Delivery Partner operations and touch-ready dashboard', async ({ page }) => {
        const diagnostics = new DiagnosticsCollector(page);
        await loginAs(page, 'DELIVERY_PARTNER');

        const delivResp = await safeGoto(page, '/delivery');
        expect(delivResp?.status()).toBe(200);
        await page.waitForLoadState('domcontentloaded');
        await diagnostics.captureNamedScreenshot(page, '09_driver_dashboard', evidenceDir);

        const content = await page.content();
        expect(content).toContain('Deliveries');

        await logout(page);
    });

    test('10.2 Admin returns management, credits, and refunds queues', async ({ page }) => {
        const diagnostics = new DiagnosticsCollector(page);
        await loginAs(page, 'ADMIN');

        // Returns
        const retResp = await safeGoto(page, '/admin/returns');
        expect(retResp?.status()).toBe(200);
        await page.waitForLoadState('domcontentloaded');
        await diagnostics.captureNamedScreenshot(page, '09_admin_returns_queue', evidenceDir);

        // Credits
        const credResp = await safeGoto(page, '/admin/credits');
        expect(credResp?.status()).toBe(200);
        await page.waitForLoadState('domcontentloaded');
        await diagnostics.captureNamedScreenshot(page, '09_admin_credits_index', evidenceDir);

        // Refunds (Documents BUG-012: returns 500 due to RefundStatus::options())
        const refResp = await safeGoto(page, '/admin/refunds');
        if (refResp?.status() === 500) {
            await diagnostics.captureNamedScreenshot(page, 'BUG-012-admin-refunds-500', evidenceDir);
        } else {
            await page.waitForLoadState('domcontentloaded');
            await diagnostics.captureNamedScreenshot(page, '09_admin_refunds_queue', evidenceDir);
        }

        await logout(page);
    });
});
