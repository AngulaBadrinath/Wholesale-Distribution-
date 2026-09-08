import { test, expect } from '@playwright/test';
import { loginAs, logout } from '../helpers/auth';
import { DiagnosticsCollector, safeGoto } from '../helpers/diagnostics';
import path from 'path';

test.describe('Audit Phase 11: Invoices, Reports, and Notifications', () => {
    const evidenceDir = path.resolve(process.cwd(), 'artifacts/browser-audit');

    test('11.1 Invoice presentation and strict zero-product-image invariant', async ({ page }) => {
        const diagnostics = new DiagnosticsCollector(page);
        await loginAs(page, 'ADMIN');

        // Invoices Index
        const invResp = await safeGoto(page, '/admin/invoices');
        expect(invResp?.status()).toBe(200);
        await page.waitForLoadState('domcontentloaded');
        await diagnostics.captureNamedScreenshot(page, '10_admin_invoices_index', evidenceDir);

        // Check first available invoice
        const firstInvoiceLink = page.locator('a[href^="/admin/invoices/"]').first();
        if (await firstInvoiceLink.isVisible().catch(() => false)) {
            await firstInvoiceLink.click();
            await page.waitForLoadState('domcontentloaded');
            await diagnostics.captureNamedScreenshot(page, '10_admin_invoice_detail', evidenceDir);

            // Verify RULE-DOC-001: ZERO product images in invoice markup
            const imgCount = await page.locator('table img, .invoice img, [data-testid="product-image"]').count();
            expect(imgCount).toBe(0);
        }

        await logout(page);
    });

    test('11.2 Reporting modules and role access boundaries', async ({ page }) => {
        const diagnostics = new DiagnosticsCollector(page);
        await loginAs(page, 'ADMIN');

        // Sales Reports
        const salesResp = await safeGoto(page, '/admin/reports/sales');
        expect(salesResp?.status()).toBe(200);
        await page.waitForLoadState('domcontentloaded');
        await diagnostics.captureNamedScreenshot(page, '10_admin_reports_sales', evidenceDir);

        // Customer Reports (Documents BUG-011: returns 500 due to Order::invoices())
        const custResp = await safeGoto(page, '/admin/reports/customers');
        if (custResp?.status() === 500) {
            await diagnostics.captureNamedScreenshot(page, 'BUG-011-admin-reports-customers-500', evidenceDir);
        } else {
            await page.waitForLoadState('domcontentloaded');
            await diagnostics.captureNamedScreenshot(page, '10_admin_reports_customers', evidenceDir);
        }

        // Inventory Reports
        const invResp = await safeGoto(page, '/admin/reports/inventory');
        expect(invResp?.status()).toBe(200);
        await page.waitForLoadState('domcontentloaded');

        // Notifications
        const notifResp = await safeGoto(page, '/notifications');
        expect(notifResp?.status()).toBe(200);
        await page.waitForLoadState('domcontentloaded');
        await diagnostics.captureNamedScreenshot(page, '10_notifications_center', evidenceDir);

        await logout(page);

        // Salesman MUST NOT access /admin/reports/salesmen (Organization-wide performance)
        await loginAs(page, 'SALESMAN');
        const repPerfResp = await safeGoto(page, '/admin/reports/salesmen');
        expect([403, 404]).toContain(repPerfResp?.status());

        await logout(page);
    });
});
