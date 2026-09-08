import { test, expect } from '@playwright/test';
import { loginAs, logout } from '../helpers/auth';
import { DiagnosticsCollector, safeGoto } from '../helpers/diagnostics';
import path from 'path';

test.describe('Audit Phase 9: Accounts Payable and General Ledger Accounting', () => {
    const evidenceDir = path.resolve(process.cwd(), 'artifacts/browser-audit');

    test('9.1 Accounts Payable workspace and supplier liabilities', async ({ page }) => {
        const diagnostics = new DiagnosticsCollector(page);
        await loginAs(page, 'ACCOUNTANT');

        // Accounts Payable
        const apResp = await safeGoto(page, '/admin/payables');
        expect(apResp?.status()).toBe(200);
        await page.waitForLoadState('domcontentloaded');
        await diagnostics.captureNamedScreenshot(page, '08_ap_workspace', evidenceDir);

        const content = await page.content();
        expect(content).toContain('Payables');

        await logout(page);
    });

    test('9.2 General Ledger, Trial Balance, Chart of Accounts, and Financial Statements', async ({ page }) => {
        const diagnostics = new DiagnosticsCollector(page);
        await loginAs(page, 'ACCOUNTANT');

        // General Ledger
        const glResp = await safeGoto(page, '/admin/accounting/general-ledger');
        expect(glResp?.status()).toBe(200);
        await page.waitForLoadState('domcontentloaded');
        await diagnostics.captureNamedScreenshot(page, '08_gl_general_ledger', evidenceDir);

        // Trial Balance
        const tbResp = await safeGoto(page, '/admin/accounting/trial-balance');
        expect(tbResp?.status()).toBe(200);
        await page.waitForLoadState('domcontentloaded');
        await diagnostics.captureNamedScreenshot(page, '08_gl_trial_balance', evidenceDir);

        // Chart of Accounts
        const coaResp = await safeGoto(page, '/admin/accounting/accounts');
        expect(coaResp?.status()).toBe(200);
        await page.waitForLoadState('domcontentloaded');
        await diagnostics.captureNamedScreenshot(page, '08_gl_chart_of_accounts', evidenceDir);

        // Profit & Loss
        const plResp = await safeGoto(page, '/admin/accounting/profit-loss');
        expect(plResp?.status()).toBe(200);
        await page.waitForLoadState('domcontentloaded');
        await diagnostics.captureNamedScreenshot(page, '08_gl_profit_loss', evidenceDir);

        // Balance Sheet
        const bsResp = await safeGoto(page, '/admin/accounting/balance-sheet');
        expect(bsResp?.status()).toBe(200);
        await page.waitForLoadState('domcontentloaded');
        await diagnostics.captureNamedScreenshot(page, '08_gl_balance_sheet', evidenceDir);

        // Cash Reconciliation
        const recResp = await safeGoto(page, '/admin/accounting/reconciliation');
        expect(recResp?.status()).toBe(200);
        await page.waitForLoadState('domcontentloaded');
        await diagnostics.captureNamedScreenshot(page, '08_gl_cash_reconciliation', evidenceDir);

        await logout(page);
    });
});
