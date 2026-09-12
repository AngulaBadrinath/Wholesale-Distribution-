import { test, expect } from '@playwright/test';
import { loginAs, logout } from '../helpers/auth';
import { safeGoto } from '../helpers/diagnostics';

test.describe('E2E Security & Anti-IDOR Matrix', () => {
    test('Unauthenticated guest cannot access protected administrative workspaces', async ({ page, context }) => {
        await context.clearCookies();
        const routes = ['/admin/orders', '/admin/payments', '/admin/receivables', '/security/roles'];

        for (const route of routes) {
            await safeGoto(page, route);
            expect(page.url()).toContain('/login');
        }
    });

    test('Specialized role boundary enforcement', async ({ page, context }) => {
        await context.clearCookies();
        // Delivery Partner cannot access /admin/payments
        await loginAs(page, 'DELIVERY_PARTNER');
        const payResp = await safeGoto(page, '/admin/payments');
        expect([403, 404]).toContain(payResp?.status());
        await logout(page);
    });
});
