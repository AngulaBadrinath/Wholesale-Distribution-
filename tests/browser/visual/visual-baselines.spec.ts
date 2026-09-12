import { test, expect } from '@playwright/test';
import { loginAs, logout } from '../helpers/auth';
import { safeGoto } from '../helpers/diagnostics';
import path from 'path';
import fs from 'fs';

test.describe('Visual Regression Baseline Capture', () => {
    const visualDir = path.resolve(process.cwd(), 'artifacts/visual');

    test.beforeAll(() => {
        fs.mkdirSync(visualDir, { recursive: true });
    });

    test('Captures login page visual snapshot', async ({ page, context }) => {
        await context.clearCookies();
        await safeGoto(page, '/login');
        await page.waitForSelector('input[name="email"], input#email');

        const screenshotPath = path.join(visualDir, 'login-page-baseline.png');
        await page.screenshot({ path: screenshotPath, fullPage: true });

        expect(fs.existsSync(screenshotPath)).toBeTruthy();
        expect(fs.statSync(screenshotPath).size).toBeGreaterThan(1000);
    });

    test('Captures admin dashboard visual snapshot', async ({ page, context }) => {
        await context.clearCookies();
        await loginAs(page, 'ADMIN');
        await page.waitForLoadState('networkidle');

        const screenshotPath = path.join(visualDir, 'admin-dashboard-baseline.png');
        await page.screenshot({ path: screenshotPath, fullPage: true });

        expect(fs.existsSync(screenshotPath)).toBeTruthy();
        expect(fs.statSync(screenshotPath).size).toBeGreaterThan(1000);

        await logout(page);
    });
});
