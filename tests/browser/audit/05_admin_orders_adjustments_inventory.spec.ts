import { test, expect } from '@playwright/test';
import { loginAs, logout } from '../helpers/auth';
import { DiagnosticsCollector, safeGoto } from '../helpers/diagnostics';
import path from 'path';

test.describe('Audit Phase 6: Admin Order Operations, Adjustments, and Inventory', () => {
    const evidenceDir = path.resolve(process.cwd(), 'artifacts/browser-audit');

    test('6.1 Admin Order Queue and Review Workspace', async ({ page }) => {
        const diagnostics = new DiagnosticsCollector(page);
        await loginAs(page, 'ADMIN');

        // Admin Order Operations
        const ordersResp = await safeGoto(page, '/admin/orders');
        expect(ordersResp?.status()).toBe(200);
        await page.waitForLoadState('domcontentloaded');
        await diagnostics.captureNamedScreenshot(page, '05_admin_orders_index', evidenceDir);

        // Review order 28
        const reviewResp = await safeGoto(page, '/admin/orders/28/review');
        if (reviewResp?.status() === 200) {
            await page.waitForLoadState('domcontentloaded');
            await diagnostics.captureNamedScreenshot(page, '05_admin_order_28_review', evidenceDir);
        }

        // Adjustments Queue
        const adjResp = await safeGoto(page, '/admin/adjustments');
        expect(adjResp?.status()).toBe(200);
        await page.waitForLoadState('domcontentloaded');
        await diagnostics.captureNamedScreenshot(page, '05_admin_adjustments_queue', evidenceDir);

        // Inventory Stock Balances
        const invResp = await safeGoto(page, '/admin/inventory');
        expect(invResp?.status()).toBe(200);
        await page.waitForLoadState('domcontentloaded');
        await diagnostics.captureNamedScreenshot(page, '05_admin_inventory_balances', evidenceDir);

        // Inventory Exceptions
        const excResp = await safeGoto(page, '/admin/inventory-exceptions');
        expect(excResp?.status()).toBe(200);
        await page.waitForLoadState('domcontentloaded');
        await diagnostics.captureNamedScreenshot(page, '05_admin_inventory_exceptions', evidenceDir);

        await logout(page);
    });
});
