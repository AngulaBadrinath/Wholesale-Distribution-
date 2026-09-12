import { test, expect } from '@playwright/test';
import { loginAs, logout } from '../helpers/auth';
import { safeGoto } from '../helpers/diagnostics';

test.describe('Runtime Console and Network Health Check', () => {
    test('Verifies no uncaught errors or 500 status on critical admin & salesman routes', async ({ page, context }) => {
        await context.clearCookies();

        const consoleErrors: string[] = [];
        page.on('console', msg => {
            if (msg.type() === 'error') {
                consoleErrors.push(msg.text());
            }
        });

        const failedResponses: string[] = [];
        page.on('response', response => {
            if (response.status() >= 500) {
                failedResponses.push(`${response.status()} on ${response.url()}`);
            }
        });

        await loginAs(page, 'ADMIN');

        const coreRoutes = [
            '/dashboard',
            '/admin/customers',
            '/admin/products',
            '/admin/orders',
            '/admin/payments/verification',
            '/admin/ar/dashboard',
            '/admin/accounting/chart-of-accounts'
        ];

        for (const route of coreRoutes) {
            await safeGoto(page, route);
            await page.waitForLoadState('domcontentloaded');
            expect(failedResponses).toEqual([]);
        }

        await logout(page);
    });
});
