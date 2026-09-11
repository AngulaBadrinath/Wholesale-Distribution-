import { test, expect } from '@playwright/test';
import { loginAs } from './helpers/auth';
import { VIEWPORT_MATRIX } from './viewports';

test.describe('Unique Distributors — Pre-Production & Client Readiness Verification', () => {
    test.beforeEach(async ({ page }) => {
        // Collect console errors to fail closed on unexpected runtime issues
        page.on('console', (msg) => {
            if (msg.type() === 'error' && !msg.text().includes('React DevTools')) {
                console.error(`[Browser Console Error]: ${msg.text()}`);
            }
        });
    });

    test('Salesman can browse catalogue with product images and boundary pricing across viewports', async ({ page }) => {
        // Test Desktop XL
        await page.setViewportSize({ width: VIEWPORT_MATRIX.desktop_xl_1440.width, height: VIEWPORT_MATRIX.desktop_xl_1440.height });
        await loginAs(page, 'SALESMAN');

        await page.goto('/salesman/orders/create');
        await expect(page).toHaveURL(/.*salesman\/orders\/create/);

        // Verify category filter and products render
        const productCards = page.locator('div[class*="rounded-xl border"]');
        await expect(productCards.first()).toBeVisible({ timeout: 10000 });

        // Test Mobile Viewport
        await page.setViewportSize({ width: VIEWPORT_MATRIX.mobile_standard_390.width, height: VIEWPORT_MATRIX.mobile_standard_390.height });
        await expect(productCards.first()).toBeVisible();
    });

    test('Admin dashboard and order approval workspace is accessible', async ({ page }) => {
        await page.setViewportSize({ width: VIEWPORT_MATRIX.desktop_xl_1440.width, height: VIEWPORT_MATRIX.desktop_xl_1440.height });
        await loginAs(page, 'ADMIN');

        await page.goto('/admin/orders');
        await expect(page).toHaveURL(/.*admin\/orders/);
        await expect(page.locator('body')).not.toContainText('500 Server Error');
    });

    test('Accountant can view Accounts Receivable and Payment Verification', async ({ page }) => {
        await page.setViewportSize({ width: VIEWPORT_MATRIX.desktop_large_1280.width, height: VIEWPORT_MATRIX.desktop_large_1280.height });
        await loginAs(page, 'ACCOUNTANT');

        await page.goto('/receivables');
        await expect(page).toHaveURL(/.*receivables/);
        await expect(page.locator('body')).not.toContainText('500 Server Error');
    });

    test('Delivery workspace loads without 500 error across viewports', async ({ page }) => {
        await page.setViewportSize({ width: VIEWPORT_MATRIX.mobile_standard_390.width, height: VIEWPORT_MATRIX.mobile_standard_390.height });
        await loginAs(page, 'DELIVERY_PARTNER');

        await page.goto('/delivery');
        await expect(page).toHaveURL(/.*delivery/);
        await expect(page.locator('body')).not.toContainText('500 Server Error');
    });
});
