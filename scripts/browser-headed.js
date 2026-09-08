import { chromium } from '@playwright/test';
import { resolveBrowser } from '../tests/browser/resolver.ts';

const baseURL = process.env.PLAYWRIGHT_BASE_URL || 'http://localhost:8000';

async function main() {
    console.log('[Browser Headed] Resolving local browser...');
    const resolved = resolveBrowser();
    console.log(`[Browser Headed] Launching ${resolved.browserName} (${resolved.version})...`);

    const browser = await chromium.launch({
        executablePath: resolved.executablePath,
        headless: false,
        args: ['--start-maximized'],
    });

    const context = await browser.newContext({
        viewport: null, // Let window size dictate viewport
    });

    const page = await context.newPage();

    // Listen to console and network errors
    page.on('console', (msg) => {
        if (msg.type() === 'error') {
            console.error(`[Browser Console Error] ${msg.text()}`);
        }
    });

    page.on('pageerror', (err) => {
        console.error(`[Browser Page Error] ${err.message}`);
    });

    console.log(`[Browser Headed] Navigating to ${baseURL}...`);
    await page.goto(baseURL);
    console.log('[Browser Headed] Browser window open. Press Ctrl+C in terminal when finished.');

    // Keep process open until window is closed
    await new Promise((resolve) => {
        page.on('close', () => {
            console.log('[Browser Headed] Browser window closed.');
            resolve();
        });
    });

    await browser.close();
}

main().catch((err) => {
    console.error('[Browser Headed Error]', err);
    process.exit(1);
});
