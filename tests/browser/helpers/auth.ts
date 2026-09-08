import { Page } from '@playwright/test';
import { execSync } from 'child_process';
import { generateTOTP } from './totp';

export type UserRole =
    | 'SUPER_ADMIN'
    | 'ADMIN'
    | 'ACCOUNTANT'
    | 'SALESMAN'
    | 'SALESMAN_B'
    | 'WAREHOUSE_MANAGER'
    | 'DELIVERY_PARTNER'
    | 'SUSPENDED';

export interface UserCredential {
    email: string;
    password: string;
    expectedDashboardRoute: string;
    label: string;
}

export const QA_USER_CREDENTIALS: Record<UserRole, UserCredential> = {
    SUPER_ADMIN: {
        email: 'superadmin.qa@example.test',
        password: 'Password123!',
        expectedDashboardRoute: '/dashboard',
        label: 'Super Administrator',
    },
    ADMIN: {
        email: 'admin.qa@example.test',
        password: 'Password123!',
        expectedDashboardRoute: '/dashboard',
        label: 'Operations Admin',
    },
    ACCOUNTANT: {
        email: 'accountant.qa@example.test',
        password: 'Password123!',
        expectedDashboardRoute: '/dashboard',
        label: 'Finance Accountant',
    },
    SALESMAN: {
        email: 'salesman.a@example.test',
        password: 'Password123!',
        expectedDashboardRoute: '/dashboard',
        label: 'Sales Representative North',
    },
    SALESMAN_B: {
        email: 'salesman.b@example.test',
        password: 'Password123!',
        expectedDashboardRoute: '/dashboard',
        label: 'Sales Representative South',
    },
    WAREHOUSE_MANAGER: {
        email: 'warehouse.qa@example.test',
        password: 'Password123!',
        expectedDashboardRoute: '/admin/inventory',
        label: 'Warehouse Supervisor',
    },
    DELIVERY_PARTNER: {
        email: 'driver.qa@example.test',
        password: 'Password123!',
        expectedDashboardRoute: '/delivery',
        label: 'Logistics Driver',
    },
    SUSPENDED: {
        email: 'suspended.qa@example.test',
        password: 'Password123!',
        expectedDashboardRoute: '/login',
        label: 'Suspended Staff',
    },
};

/**
 * Retrieve the TOTP secret for a user if already enrolled in the local database.
 */
function getStoredUserMfaSecret(email: string): string {
    try {
        const cmd = `php artisan tinker --execute="echo \\App\\Models\\User::where('email', '${email}')->value('two_factor_secret');"`;
        const output = execSync(cmd, { encoding: 'utf-8', timeout: 5000 });
        return output.trim().replace(/[^A-Za-z0-9]/g, '');
    } catch {
        return '';
    }
}

/**
 * Authenticate as a specific project role via the real web login form.
 * Automatically completes MFA challenge if required for privileged roles.
 */
export async function loginAs(page: Page, role: UserRole): Promise<void> {
    const creds = QA_USER_CREDENTIALS[role];
    if (!creds) {
        throw new Error(`[AuthHelper] Unknown user role: "${role}"`);
    }

    if (!page.url().includes('/login')) {
        let gotoAttempts = 0;
        while (gotoAttempts < 4) {
            try {
                await page.goto('/login', { waitUntil: 'domcontentloaded', timeout: 20000 });
                break;
            } catch (err: any) {
                gotoAttempts++;
                if (gotoAttempts >= 4) throw err;
                await page.waitForTimeout(1000);
            }
        }
    }

    await page.locator('input[type="email"]').waitFor({ state: 'visible' });
    await page.waitForTimeout(300);

    // Fill credentials
    const emailInput = page.locator('input[type="email"], input[name="email"]');
    const passwordInput = page.locator('input[type="password"], input[name="password"]');

    await emailInput.fill(creds.email);
    await passwordInput.fill(creds.password);
    await page.waitForTimeout(100);

    // Click Sign In
    const submitBtn = page.locator('button[type="submit"]');
    await submitBtn.click();

    // Wait until login form navigates away from /login or throws validation error
    try {
        await page.waitForURL((url) => url.pathname !== '/login', { timeout: 8000 });
    } catch (e) {
        const alertText = await page.locator('[role="alert"], .text-destructive').first().textContent().catch(() => '');
        throw new Error(`[AuthHelper] Login failed for ${role} (${creds.email}). Still on ${page.url()}. Page alert: "${alertText?.trim()}"`);
    }

    const currentUrl = page.url();

    if (currentUrl.includes('/login/mfa') || currentUrl.includes('/mfa')) {
        // Step 1: Check for manual key in Inertia props (initial enrollment)
        let secretKey = await page.evaluate(() => {
            try {
                const el = document.getElementById('app') || document.querySelector('[data-page]');
                if (el && el.getAttribute('data-page')) {
                    const data = JSON.parse(el.getAttribute('data-page') || '{}');
                    return (data?.props?.manual_key || '').replace(/[^A-Za-z0-9]/g, '');
                }
            } catch {}
            return '';
        });

        // Step 2: Fallback to DOM span if visible
        if (!secretKey) {
            const manualKeySpan = page.locator('.font-mono').first();
            if (await manualKeySpan.isVisible().catch(() => false)) {
                const text = await manualKeySpan.innerText();
                if (text && text.length >= 16) {
                    secretKey = text.replace(/[^A-Za-z0-9]/g, '');
                }
            }
        }

        // Step 3: If challenge on already enrolled account, fetch from local DB
        if (!secretKey) {
            secretKey = getStoredUserMfaSecret(creds.email);
        }

        if (secretKey) {
            const totpCode = generateTOTP(secretKey);
            const mfaInput = page.locator('input#code, input[name="code"], input[type="text"]').first();
            await mfaInput.fill(totpCode);

            const mfaSubmit = page.locator('button[type="submit"]');
            await mfaSubmit.click();

            // Wait for Inertia URL to navigate away from /login/mfa
            await page.waitForURL((url) => !url.pathname.includes('/login'), { timeout: 15000 });
        }
    }

    // Verify successful authentication
    const finalUrl = page.url();
    if (finalUrl.includes('/login')) {
        throw new Error(`[AuthHelper] Login failed for ${role} (${creds.email}). Still on ${finalUrl}`);
    }
}

/**
 * Log out the current session.
 */
export async function logout(page: Page): Promise<void> {
    try {
        await page.context().clearCookies();
        await page.waitForTimeout(400);
        await page.goto('/login', { waitUntil: 'domcontentloaded', timeout: 15000 });
    } catch {
        // Fallback
    }
}
