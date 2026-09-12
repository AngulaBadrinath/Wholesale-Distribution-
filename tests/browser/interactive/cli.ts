import readline from 'readline';
import { InteractiveBrowserClient } from './client.ts';
import type { UserRole } from '../helpers/auth.ts';

const client = new InteractiveBrowserClient();

function printBanner(status: any) {
    console.log(`====================================================`);
    console.log(`  UNIQUE DISTRIBUTORS — INTERACTIVE REAL BROWSER QA `);
    console.log(`====================================================`);
    console.log(`[Status]      ACTIVE`);
    console.log(`[Environment] ${status.environment}`);
    console.log(`[Base URL]    ${status.baseUrl}`);
    console.log(`[Browser]     ${status.browserName} (${status.browserVersion})`);
    console.log(`[Current URL] ${status.currentUrl || 'Blank'}`);
    console.log(`[Tabs Open]   ${status.totalTabs} (Active: Tab ${status.activeTabIndex})`);
    console.log(`====================================================`);
    console.log(`Keep this Chrome window on your second display.`);
    console.log(`Type "help" to see available commands or "exit" to quit.\n`);
}

function printHelp() {
    console.log(`
Available Commands:
  navigate <url/path>         Navigate to relative path (e.g. /admin/orders) or full URL
  login <ROLE>                Login as SUPER_ADMIN, ADMIN, ACCOUNTANT, SALESMAN, WAREHOUSE_MANAGER, DELIVERY_PARTNER
  click <selector>            Click element matching CSS selector or text
  clickText <text>            Click element matching visible text
  clickRole <role> [name]     Click accessible element (e.g. clickRole button "Submit Order")
  fill <selector> <value>     Fill input matching selector
  fillLabel <label> <value>   Fill input with associated label or placeholder
  select <selector> <val>     Select dropdown option
  check <selector>            Check a checkbox or radio button
  uncheck <selector>          Uncheck a checkbox
  press <selector> <key>      Press a key on selector (e.g. Enter, Escape, Tab)
  waitFor <selector>          Wait for element to be visible
  wait <ms>                   Wait for specified milliseconds (default 1000)
  viewport <preset/width> [h] Set viewport: mobile (390), tablet (768), desktop (1440), 1920, etc.
  screenshot [name]           Capture timestamped screenshot to artifacts/browser/interactive/
  newTab [url]                Open new browser tab and focus it
  switchTab <index>           Switch active tab (0-indexed)
  closeTab [index]            Close specified or active tab
  tabs / listTabs             List all open tabs
  reload                      Reload current active tab
  back / forward              Navigate history
  status                      Display current session and page status
  diagnostics                 View console errors, warnings, and failed network requests
  stop / exit / quit          Close browser and end interactive session
`);
}

function formatResult(action: string, response: any) {
    if (response.success) {
        console.log(`[SUCCESS] Action: "${action}"`);
        if (response.status) {
            console.log(`  - URL:   ${response.status.currentUrl}`);
            console.log(`  - Title: ${response.status.title}`);
            console.log(`  - Tab:   ${response.status.activeTabIndex + 1} of ${response.status.totalTabs}`);
            if (response.status.diagnosticsSummary) {
                const { consoleErrorsCount, networkErrorsCount } = response.status.diagnosticsSummary;
                if (consoleErrorsCount > 0 || networkErrorsCount > 0) {
                    console.log(`  - Issues: ${consoleErrorsCount} console errors, ${networkErrorsCount} network errors`);
                }
            }
        }
        if (response.result?.filePath || response.result?.relativePath) {
            console.log(`  - Screenshot: ${response.result.relativePath || response.result.filePath}`);
        }
    } else {
        console.error(`[FAILED] Action: "${action}"`);
        console.error(`  - Error:  ${response.error || 'Action failed.'}`);
        if (response.status?.currentUrl) {
            console.error(`  - URL:    ${response.status.currentUrl}`);
        }
        if (response.screenshotPath) {
            console.error(`  - Error Screenshot: ${response.screenshotPath}`);
        }
        if (response.diagnosticsSummary?.consoleErrors?.length > 0) {
            console.error(`  - Console Errors:`, response.diagnosticsSummary.consoleErrors.map((e: any) => e.text).slice(0, 3));
        }
    }
}

async function runCliCommand(args: string[]): Promise<number> {
    if (args.length === 0) {
        console.log('No command provided. Use "npm run browser:interactive" for REPL or pass commands e.g. "navigate /login".');
        return 1;
    }

    const command = args[0].toLowerCase();
    const rawArgs = args.slice(1);

    try {
        switch (command) {
            case 'start':
            case 'headed': {
                const status = await client.ensureServer(true);
                printBanner(status);
                return 0;
            }

            case 'stop':
            case 'exit':
            case 'quit': {
                console.log('[Browser QA] Stopping persistent browser session...');
                await client.stop();
                console.log('[Browser QA] Browser session closed.');
                return 0;
            }

            case 'status': {
                const status = await client.getStatus();
                console.log(JSON.stringify(status, null, 2));
                return 0;
            }

            case 'diagnostics': {
                const diag = await client.getDiagnostics();
                console.log(JSON.stringify(diag, null, 2));
                return 0;
            }

            case 'navigate':
            case 'goto':
            case 'open': {
                const url = rawArgs.join(' ') || '/';
                const resp = await client.navigate(url);
                formatResult('navigate', resp);
                return resp.success ? 0 : 1;
            }

            case 'login': {
                const role = (rawArgs[0] || 'ACCOUNTANT').toUpperCase() as UserRole;
                console.log(`[Browser QA] Logging in as ${role}...`);
                const resp = await client.login(role);
                formatResult(`login (${role})`, resp);
                return resp.success ? 0 : 1;
            }

            case 'click': {
                const selector = rawArgs.join(' ');
                const resp = await client.click(selector);
                formatResult('click', resp);
                return resp.success ? 0 : 1;
            }

            case 'clicktext': {
                const text = rawArgs.join(' ').replace(/^["']|["']$/g, '');
                const resp = await client.clickText(text);
                formatResult(`clickText "${text}"`, resp);
                return resp.success ? 0 : 1;
            }

            case 'clickrole': {
                const role = rawArgs[0];
                const name = rawArgs.slice(1).join(' ').replace(/^["']|["']$/g, '') || undefined;
                const resp = await client.clickRole(role, name);
                formatResult(`clickRole ${role} "${name || ''}"`, resp);
                return resp.success ? 0 : 1;
            }

            case 'fill': {
                const selector = rawArgs[0];
                const value = rawArgs.slice(1).join(' ');
                const resp = await client.fill(selector, value);
                formatResult('fill', resp);
                return resp.success ? 0 : 1;
            }

            case 'filllabel': {
                const label = rawArgs[0].replace(/^["']|["']$/g, '');
                const value = rawArgs.slice(1).join(' ');
                const resp = await client.fillLabel(label, value);
                formatResult(`fillLabel "${label}"`, resp);
                return resp.success ? 0 : 1;
            }

            case 'select': {
                const selector = rawArgs[0];
                const value = rawArgs.slice(1).join(' ');
                const resp = await client.select(selector, value);
                formatResult('select', resp);
                return resp.success ? 0 : 1;
            }

            case 'check': {
                const selector = rawArgs.join(' ');
                const resp = await client.check(selector);
                formatResult('check', resp);
                return resp.success ? 0 : 1;
            }

            case 'uncheck': {
                const selector = rawArgs.join(' ');
                const resp = await client.uncheck(selector);
                formatResult('uncheck', resp);
                return resp.success ? 0 : 1;
            }

            case 'press': {
                const selector = rawArgs[0];
                const key = rawArgs[1] || 'Enter';
                const resp = await client.press(selector, key);
                formatResult(`press ${key}`, resp);
                return resp.success ? 0 : 1;
            }

            case 'waitfor': {
                const selector = rawArgs.join(' ');
                const resp = await client.waitFor(selector);
                formatResult('waitFor', resp);
                return resp.success ? 0 : 1;
            }

            case 'wait': {
                const ms = parseInt(rawArgs[0] || '1000', 10);
                const resp = await client.wait(ms);
                formatResult(`wait ${ms}ms`, resp);
                return resp.success ? 0 : 1;
            }

            case 'viewport': {
                const presetOrW = rawArgs[0] || 'desktop';
                const h = rawArgs[1] ? parseInt(rawArgs[1], 10) : undefined;
                const resp = await client.setViewport(presetOrW, h);
                formatResult(`viewport ${presetOrW}`, resp);
                return resp.success ? 0 : 1;
            }

            case 'mobile': {
                const resp = await client.setViewport('mobile');
                formatResult('viewport mobile (390px)', resp);
                return resp.success ? 0 : 1;
            }

            case 'tablet': {
                const resp = await client.setViewport('tablet');
                formatResult('viewport tablet (768px)', resp);
                return resp.success ? 0 : 1;
            }

            case 'desktop': {
                const resp = await client.setViewport('desktop');
                formatResult('viewport desktop (1440px)', resp);
                return resp.success ? 0 : 1;
            }

            case 'screenshot': {
                const name = rawArgs.join('_') || 'screenshot';
                const fullPage = rawArgs.includes('--full') || rawArgs.includes('--full-page');
                const resp = await client.screenshot(name, fullPage);
                formatResult('screenshot', resp);
                return resp.success ? 0 : 1;
            }

            case 'newtab': {
                const url = rawArgs.join(' ') || undefined;
                const resp = await client.newTab(url);
                formatResult('newTab', resp);
                return resp.success ? 0 : 1;
            }

            case 'switchtab': {
                const idx = parseInt(rawArgs[0] || '0', 10);
                const resp = await client.switchTab(idx);
                formatResult(`switchTab ${idx}`, resp);
                return resp.success ? 0 : 1;
            }

            case 'closetab': {
                const idx = rawArgs[0] ? parseInt(rawArgs[0], 10) : undefined;
                const resp = await client.closeTab(idx);
                formatResult('closeTab', resp);
                return resp.success ? 0 : 1;
            }

            case 'tabs':
            case 'listtabs': {
                const resp = await client.listTabs();
                if (resp.success && Array.isArray(resp.result)) {
                    console.log('Open Tabs:');
                    resp.result.forEach((t: any) => {
                        console.log(`  [Tab ${t.index}] ${t.isActive ? '* ' : '  '}${t.title} (${t.url})`);
                    });
                }
                return resp.success ? 0 : 1;
            }

            case 'reload': {
                const resp = await client.reload();
                formatResult('reload', resp);
                return resp.success ? 0 : 1;
            }

            case 'back': {
                const resp = await client.back();
                formatResult('back', resp);
                return resp.success ? 0 : 1;
            }

            case 'forward': {
                const resp = await client.forward();
                formatResult('forward', resp);
                return resp.success ? 0 : 1;
            }

            default: {
                console.error(`Unknown command: "${command}". Type "help" to see available commands.`);
                return 1;
            }
        }
    } catch (err: any) {
        console.error(`[Error] ${err.message || String(err)}`);
        return 1;
    }
}

async function startRepl() {
    const status = await client.ensureServer(true);
    printBanner(status);

    const rl = readline.createInterface({
        input: process.stdin,
        output: process.stdout,
        prompt: '[Browser QA]> ',
    });

    rl.prompt();

    rl.on('line', async (line) => {
        const trimmed = line.trim();
        if (!trimmed) {
            rl.prompt();
            return;
        }

        if (trimmed.toLowerCase() === 'help') {
            printHelp();
            rl.prompt();
            return;
        }

        if (['exit', 'quit', 'stop'].includes(trimmed.toLowerCase())) {
            console.log('[Browser QA] Closing session...');
            await client.stop();
            rl.close();
            process.exit(0);
        }

        const parts = trimmed.match(/(?:[^\s"']+|"[^"]*"|'[^']*')+/g) || [];
        const cleanParts = parts.map((p) => p.replace(/^["']|["']$/g, ''));

        await runCliCommand(cleanParts);
        console.log('');
        rl.prompt();
    });

    rl.on('close', async () => {
        console.log('\n[Browser QA] Exiting REPL (browser window remains open unless stopped).');
        process.exit(0);
    });
}

// Entrypoint
async function main() {
    const args = process.argv.slice(2);
    if (args.length === 0 || args[0] === 'repl' || args[0] === 'interactive') {
        await startRepl();
    } else {
        const exitCode = await runCliCommand(args);
        process.exit(exitCode);
    }
}

main().catch((err) => {
    console.error('[Browser QA Fatal Error]', err);
    process.exit(1);
});
