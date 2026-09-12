import { Client } from '@modelcontextprotocol/sdk/client/index.js';
import { StdioClientTransport } from '@modelcontextprotocol/sdk/client/stdio.js';
import fs from 'fs';
import path from 'path';

const EXPECTED_TOOLS = [
    'browser_status',
    'browser_start',
    'browser_stop',
    'browser_observe',
    'browser_screenshot',
    'browser_navigate',
    'browser_click',
    'browser_fill',
    'browser_select',
    'browser_check',
    'browser_uncheck',
    'browser_press',
    'browser_wait_for',
    'browser_tabs',
    'browser_new_tab',
    'browser_switch_tab',
    'browser_close_tab',
    'browser_viewport',
    'browser_diagnostics',
    'browser_login',
    'browser_execute_sequence',
    'browser_confirm_destructive_action',
];

async function runTests() {
    console.log(`====================================================`);
    console.log(`  ANTIGRAVITY MCP BROWSER TOOL VERIFICATION SUITE   `);
    console.log(`====================================================\n`);

    const mcpServerScript = path.resolve(process.cwd(), 'tests', 'browser', 'interactive', 'mcp-server.ts');

    const transport = new StdioClientTransport({
        command: process.execPath,
        args: ['--no-warnings', mcpServerScript],
    });

    const client = new Client(
        {
            name: 'mcp-test-client',
            version: '1.0.0',
        },
        {
            capabilities: {},
        }
    );

    let passedTests = 0;
    let totalTests = 0;

    const assertTest = (name: string, condition: boolean, details?: string) => {
        totalTests++;
        if (condition) {
            console.log(`  ✓ [PASS] ${name}`);
            passedTests++;
        } else {
            console.error(`  ✗ [FAIL] ${name} ${details ? `(${details})` : ''}`);
            throw new Error(`Test failed: ${name}`);
        }
    };

    try {
        console.log(`[Step 1] Connecting to MCP Server via stdio...`);
        await client.connect(transport);
        assertTest('MCP Client connected to MCP Server', true);

        console.log(`\n[Step 2] Listing exposed MCP Tools...`);
        const toolsResult = await client.listTools();
        const availableToolNames = toolsResult.tools.map((t) => t.name);
        console.log(`  Discovered ${availableToolNames.length} MCP tools.`);

        assertTest('Discovered exactly 22 MCP tools', availableToolNames.length === 22);

        for (const expected of EXPECTED_TOOLS) {
            const found = availableToolNames.includes(expected);
            assertTest(`Tool "${expected}" is registered in MCP tool catalog`, found);
        }

        console.log(`\n[Step 3] Invoking browser_status (initial state)...`);
        const statusRes1 = await client.callTool({ name: 'browser_status', arguments: {} });
        const statusData1 = JSON.parse((statusRes1.content[0] as any).text);
        assertTest('browser_status returned structured JSON', statusData1.success === true);

        console.log(`\n[Step 4] Starting headed browser via browser_start...`);
        const startRes = await client.callTool({
            name: 'browser_start',
            arguments: { headed: true, startUrl: '/login' },
        });
        const startData = JSON.parse((startRes.content[0] as any).text);
        assertTest('browser_start initialized persistent browser', startData.success === true);

        console.log(`\n[Step 5] Invoking browser_observe on /login...`);
        const observeRes = await client.callTool({
            name: 'browser_observe',
            arguments: { detailLevel: 'detailed', captureScreenshot: true },
        });
        const observeData = JSON.parse((observeRes.content[0] as any).text);
        assertTest('browser_observe returned page observation', observeData.success === true);
        assertTest('Observation includes current URL (/login)', observeData.observation?.url?.includes('/login'));
        assertTest('Observation includes interactive inputs', Array.isArray(observeData.observation?.inputs));
        assertTest('Observation includes action buttons', Array.isArray(observeData.observation?.buttons));
        assertTest('Observation includes headings', Array.isArray(observeData.observation?.headings));

        console.log(`\n[Step 6] Testing browser_fill (with sensitive password redaction)...`);
        const fillEmailRes = await client.callTool({
            name: 'browser_fill',
            arguments: { selector: 'input[type="email"]', value: 'admin.qa@example.test' },
        });
        assertTest('Filled email input', JSON.parse((fillEmailRes.content[0] as any).text).success === true);

        const fillPassRes = await client.callTool({
            name: 'browser_fill',
            arguments: { selector: 'input[type="password"]', value: 'Password123!' },
        });
        const fillPassData = JSON.parse((fillPassRes.content[0] as any).text);
        assertTest('Filled password input', fillPassData.success === true);
        assertTest(
            'Password value is never leaked in tool response text',
            !JSON.stringify(fillPassData).includes('Password123!')
        );

        console.log(`\n[Step 7] Capturing screenshot via browser_screenshot...`);
        const shotRes = await client.callTool({
            name: 'browser_screenshot',
            arguments: { name: 'mcp_test_verification', fullPage: false },
        });
        const shotData = JSON.parse((shotRes.content[0] as any).text);
        assertTest('browser_screenshot captured artifact', shotData.success === true);
        assertTest('Screenshot file exists on disk', shotData.filePath && fs.existsSync(shotData.filePath));

        console.log(`\n[Step 8] Testing Viewport switching via browser_viewport...`);
        const vpMobileRes = await client.callTool({
            name: 'browser_viewport',
            arguments: { preset: 'mobile' },
        });
        const vpMobileData = JSON.parse((vpMobileRes.content[0] as any).text);
        assertTest('Switched viewport to mobile (390x844)', vpMobileData.viewport?.width === 390);

        const vpDesktopRes = await client.callTool({
            name: 'browser_viewport',
            arguments: { preset: 'desktop' },
        });
        const vpDesktopData = JSON.parse((vpDesktopRes.content[0] as any).text);
        assertTest('Switched viewport back to desktop (1440x900)', vpDesktopData.viewport?.width === 1440);

        console.log(`\n[Step 9] Testing Multi-Tab Management...`);
        const newTabRes = await client.callTool({
            name: 'browser_new_tab',
            arguments: { url_or_path: '/login' },
        });
        const newTabData = JSON.parse((newTabRes.content[0] as any).text);
        assertTest('Opened new tab in same context', newTabData.totalTabs >= 2);

        const tabsRes = await client.callTool({ name: 'browser_tabs', arguments: {} });
        const tabsData = JSON.parse((tabsRes.content[0] as any).text);
        assertTest('Listed open tabs', Array.isArray(tabsData.tabs) && tabsData.tabs.length >= 2);

        const switchTabRes = await client.callTool({
            name: 'browser_switch_tab',
            arguments: { index: 0 },
        });
        const switchTabData = JSON.parse((switchTabRes.content[0] as any).text);
        assertTest('Switched back to tab 0', switchTabData.activeTabIndex === 0);

        const closeTabRes = await client.callTool({
            name: 'browser_close_tab',
            arguments: { index: 1 },
        });
        const closeTabData = JSON.parse((closeTabRes.content[0] as any).text);
        assertTest('Closed secondary tab', closeTabData.success === true);

        console.log(`\n[Step 10] Testing browser_diagnostics & Redaction...`);
        const diagRes = await client.callTool({ name: 'browser_diagnostics', arguments: {} });
        const diagData = JSON.parse((diagRes.content[0] as any).text);
        assertTest('browser_diagnostics returned log data', diagData.success === true);

        console.log(`\n[Step 11] Testing Security: Domain Allowlist Enforcement...`);
        const extNavRes = await client.callTool({
            name: 'browser_navigate',
            arguments: { path_or_url: 'https://malicious-external-domain.invalid/hack' },
        });
        const extNavData = JSON.parse((extNavRes.content[0] as any).text);
        assertTest(
            'Blocked navigation to unauthorized external domain',
            extNavRes.isError === true || extNavData.success === false
        );

        console.log(`\n[Step 12] Testing browser_execute_sequence...`);
        const seqRes = await client.callTool({
            name: 'browser_execute_sequence',
            arguments: {
                steps: [
                    { action: 'navigate', path: '/login' },
                    { action: 'fill', selector: 'input[type="email"]', value: 'admin.qa@example.test' },
                ],
            },
        });
        const seqData = JSON.parse((seqRes.content[0] as any).text);
        assertTest('Executed structured sequence', seqData.success === true && seqData.stepResults?.length === 2);

        console.log(`\n[Step 13] Testing browser_confirm_destructive_action...`);
        const unconfirmedRes = await client.callTool({
            name: 'browser_confirm_destructive_action',
            arguments: {
                actionDescription: 'Reset QA Database State',
                confirmed: false,
            },
        });
        const unconfirmedData = JSON.parse((unconfirmedRes.content[0] as any).text);
        assertTest('Unconfirmed destructive action was blocked', unconfirmedData.confirmed === false);

        const confirmedRes = await client.callTool({
            name: 'browser_confirm_destructive_action',
            arguments: {
                actionDescription: 'Navigate to login test',
                confirmed: true,
                action: { action: 'navigate', path: '/login' },
            },
        });
        const confirmedData = JSON.parse((confirmedRes.content[0] as any).text);
        assertTest('Confirmed action was permitted and executed', confirmedData.confirmed === true);

        console.log(`\n====================================================`);
        console.log(`  ALL ${passedTests}/${totalTests} MCP VERIFICATION TESTS PASSED SUCCESSFULLY!`);
        console.log(`====================================================\n`);

        await client.close();
        process.exit(0);
    } catch (err) {
        console.error('\n[FATAL TEST RUNNER ERROR]', err);
        try {
            await client.close();
        } catch {}
        process.exit(1);
    }
}

runTests();
