import { chromium } from '@playwright/test';
import { resolveBrowser } from '../tests/browser/resolver.ts';
import { VIEWPORT_MATRIX } from '../tests/browser/viewports.ts';
import fs from 'fs';
import path from 'path';

const baseURL = process.env.PLAYWRIGHT_BASE_URL || 'http://localhost:8000';
const outputDir = path.resolve(process.cwd(), 'artifacts', 'browser', 'visual');

async function main() {
    fs.mkdirSync(outputDir, { recursive: true });

    const resolved = resolveBrowser();
    console.log(`[Browser Visual] Launching ${resolved.browserName} (${resolved.version})...`);

    const browser = await chromium.launch({
        executablePath: resolved.executablePath,
        headless: true,
    });

    const targetRoutes = [
        { name: 'login_page', path: '/login' },
        { name: 'welcome_page', path: '/' },
    ];

    const targetViewports = [
        VIEWPORT_MATRIX['mobile_standard_390'],
        VIEWPORT_MATRIX['tablet_portrait_768'],
        VIEWPORT_MATRIX['desktop_xl_1440'],
    ];

    for (const route of targetRoutes) {
        for (const vp of targetViewports) {
            const context = await browser.newContext({
                viewport: { width: vp.width, height: vp.height },
                isMobile: vp.isMobile,
                hasTouch: vp.hasTouch,
            });

            const page = await context.newPage();
            const url = `${baseURL}${route.path}`;
            console.log(`[Browser Visual] Capturing ${route.name} at ${vp.width}x${vp.height} (${url})...`);

            try {
                await page.goto(url, { waitUntil: 'domcontentloaded', timeout: 20000 });
                await page.waitForTimeout(500); // Allow layout/CSS to settle

                const filename = `${route.name}_${vp.width}w.png`;
                const filepath = path.join(outputDir, filename);
                await page.screenshot({ path: filepath, fullPage: true });
                console.log(`[Browser Visual] Saved: ${filepath}`);
            } catch (err) {
                console.error(`[Browser Visual] Error capturing ${route.name} at ${vp.width}w:`, err.message);
            } finally {
                await context.close();
            }
        }
    }

    await browser.close();
    console.log(`[Browser Visual] All screenshots saved to ${outputDir}`);
}

main().catch((err) => {
    console.error('[Browser Visual Error]', err);
    process.exit(1);
});
