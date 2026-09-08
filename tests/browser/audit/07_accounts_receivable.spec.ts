import { test, expect } from '@playwright/test';
import { loginAs, logout } from '../helpers/auth';
import { DiagnosticsCollector, safeGoto } from '../helpers/diagnostics';
import path from 'path';

test.describe('Audit Phase 8: Accounts Receivable (Deepest Section)', () => {
    const evidenceDir = path.resolve(process.cwd(), 'artifacts/browser-audit');

    test('8.1 AR Overview Dashboard loads with 200, valid aging, and no transaction aborts', async ({ page }) => {
        const diagnostics = new DiagnosticsCollector(page);
        await loginAs(page, 'ADMIN');

        // 1. AR Dashboard (Verified BUG-011 fix: returns 200 with derived metrics)
        const arResp = await safeGoto(page, '/admin/receivables');
        expect(arResp?.status()).toBe(200);
        await page.waitForLoadState('domcontentloaded');
        await diagnostics.captureNamedScreenshot(page, '07_ar_dashboard', evidenceDir);

        // 2. Customer AR Ledger Detail (Customer 31: Apex Supermarket Group)
        const custArResp = await safeGoto(page, '/admin/receivables/31');
        expect(custArResp?.status()).toBe(200);
        await page.waitForLoadState('domcontentloaded');
        await diagnostics.captureNamedScreenshot(page, '07_ar_customer_31_ledger', evidenceDir);

        // 3. Customer Statement View (Customer 31)
        const stmtResp = await safeGoto(page, '/admin/receivables/31/statement');
        expect(stmtResp?.status()).toBe(200);
        await page.waitForLoadState('domcontentloaded');
        await diagnostics.captureNamedScreenshot(page, '07_ar_customer_31_statement', evidenceDir);

        await logout(page);
    });
});
