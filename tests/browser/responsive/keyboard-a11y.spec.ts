import { test, expect } from '@playwright/test';
import { safeGoto } from '../helpers/diagnostics';

test.describe('Accessibility & Keyboard Navigation', () => {
    test('Verifies Tab focus sequence and visible interactive elements', async ({ page, context }) => {
        await context.clearCookies();
        await safeGoto(page, '/login');
        await page.waitForSelector('input[name="email"], input#email');

        const emailInput = page.locator('input[name="email"], input#email').first();
        await emailInput.focus();

        // Press Tab to move to password field
        await page.keyboard.press('Tab');
        const activeName = await page.evaluate(() => (document.activeElement as HTMLInputElement)?.name || (document.activeElement as HTMLElement)?.tagName);
        expect(['password', 'INPUT', 'BUTTON', 'A']).toContain(activeName);

        // Escape key should not throw or crash the page
        await page.keyboard.press('Escape');
        expect(page.url()).toContain('/login');
    });
});
