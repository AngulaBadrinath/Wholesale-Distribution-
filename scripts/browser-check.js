import { resolveBrowser } from '../tests/browser/resolver.ts';

console.log('====================================================');
console.log('  PERMANENT LOCAL REAL-BROWSER DIAGNOSTICS CHECK    ');
console.log('====================================================');

try {
    const browser = resolveBrowser();
    console.log(`[Status]       SUCCESS`);
    console.log(`[Browser Name] ${browser.browserName}`);
    console.log(`[Version]      ${browser.version}`);
    console.log(`[Path]         ${browser.executablePath}`);
    console.log(`[Source]       ${browser.source === 'env' ? 'PLAYWRIGHT_BROWSER_PATH environment variable' : 'Auto-discovered local installation'}`);
    console.log(`[Platform]     ${process.platform} (${process.arch})`);
    console.log(`[Base URL]     ${process.env.PLAYWRIGHT_BASE_URL || 'http://localhost:8000'}`);
    console.log('====================================================');
    console.log('Local real browser is ready. Zero CDN downloads required.');
    process.exit(0);
} catch (err) {
    console.error(`[Status]       FAILED`);
    console.error(`[Error]        ${err.message}`);
    console.log('====================================================');
    process.exit(1);
}
