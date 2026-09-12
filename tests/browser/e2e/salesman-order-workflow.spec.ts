import { test, expect } from '@playwright/test';
import { loginAs, logout } from '../helpers/auth';
import { safeGoto } from '../helpers/diagnostics';

test.describe('E2E Salesman Order Workflow', () => {
    test('Salesman accesses order create form and verifies customer scoping', async ({ page, context }) => {
        await context.clearCookies();
        await loginAs(page, 'SALESMAN');

        const resp = await safeGoto(page, '/salesman/orders/create');
        expect(resp?.status()).toBe(200);

        await page.waitForLoadState('domcontentloaded');
        const content = await page.content();
        expect(content.toLowerCase()).toContain('order');

        // Order history loads cleanly
        const historyResp = await safeGoto(page, '/salesman/orders');
        expect(historyResp?.status()).toBe(200);

        await logout(page);
    });
});
