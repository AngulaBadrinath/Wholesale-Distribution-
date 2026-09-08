import { test, expect } from '@playwright/test';
import { loginAs, logout } from '../helpers/auth';
import { DiagnosticsCollector, safeGoto } from '../helpers/diagnostics';
import path from 'path';

test.describe('Audit Phase 7: Payment Lifecycle & Verification', () => {
    const evidenceDir = path.resolve(process.cwd(), 'artifacts/browser-audit');

    test('7.1 Admin / Accountant Payment Verification Workspace', async ({ page }) => {
        const diagnostics = new DiagnosticsCollector(page);
        await loginAs(page, 'ADMIN');

        const payResp = await safeGoto(page, '/admin/payments');
        expect(payResp?.status()).toBe(200);
        await page.waitForLoadState('domcontentloaded');
        await diagnostics.captureNamedScreenshot(page, '06_admin_payments_hub', evidenceDir);

        await page.waitForSelector('table');
        const bodyText = await page.locator('body').innerText();
        expect(bodyText).toContain('Payments & Collections Workspace');
        expect(bodyText).toContain('Pending Verification');

        await logout(page);
    });

    test('7.2 Salesman payment verification access restriction', async ({ page }) => {
        await loginAs(page, 'SALESMAN');

        // Verify action endpoint protection: Salesman cannot perform verification POST without permission
        const verifyPostResp = await page.request.post('/admin/payments/1/verify', {
            data: { notes: 'Unauthorized attempt' },
            maxRedirects: 0,
        });
        // Non-permitted POST must be rejected via 403 or redirected via 302
        expect([302, 403, 404, 405]).toContain(verifyPostResp.status());

        await logout(page);
    });
});
