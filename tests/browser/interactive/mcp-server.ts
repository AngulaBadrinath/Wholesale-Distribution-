#!/usr/bin/env node
import { McpServer } from '@modelcontextprotocol/sdk/server/mcp.js';
import { StdioServerTransport } from '@modelcontextprotocol/sdk/server/stdio.js';
import { z } from 'zod';
import { InteractiveBrowserClient } from './client.ts';
import { redactSensitiveData } from './controller.ts';

const client = new InteractiveBrowserClient();

const server = new McpServer({
    name: 'unique-distributors-browser',
    version: '1.0.0',
});

function formatResponse(data: any, isError = false) {
    const cleaned = redactSensitiveData(data);
    return {
        content: [
            {
                type: 'text' as const,
                text: typeof cleaned === 'string' ? cleaned : JSON.stringify(cleaned, null, 2),
            },
        ],
        isError,
    };
}

// 1. browser_status
server.tool(
    'browser_status',
    'Get current status of the persistent QA browser session, active page URL, title, viewport, environment, active tab, and diagnostics summary.',
    {},
    async () => {
        try {
            const isRunning = await client.isServerRunning();
            if (!isRunning) {
                return formatResponse({
                    success: true,
                    isRunning: false,
                    message: 'Browser daemon is not running. Use browser_start or navigate to initiate.',
                });
            }
            const status = await client.getStatus();
            return formatResponse({
                success: true,
                ...status,
            });
        } catch (err: any) {
            return formatResponse({ success: false, error: err.message || String(err) }, true);
        }
    }
);

// 2. browser_start
server.tool(
    'browser_start',
    'Start the persistent headed Chrome QA browser daemon if not already running, or return active session status.',
    {
        headed: z.boolean().optional().describe('Launch headed browser window on screen (default: true).'),
        startUrl: z.string().optional().describe('Optional initial URL or relative path to open.'),
    },
    async ({ headed = true, startUrl }) => {
        try {
            const status = await client.ensureServer(headed);
            if (startUrl) {
                const navResp = await client.navigate(startUrl);
                return formatResponse({
                    success: true,
                    message: 'Browser daemon started and navigated to initial URL.',
                    status: navResp.status,
                });
            }
            return formatResponse({
                success: true,
                message: 'Browser daemon is active and ready.',
                status,
            });
        } catch (err: any) {
            return formatResponse({ success: false, error: err.message || String(err) }, true);
        }
    }
);

// 3. browser_stop
server.tool(
    'browser_stop',
    'Explicitly stop the persistent QA browser session and close Chrome.',
    {},
    async () => {
        try {
            await client.stop();
            return formatResponse({
                success: true,
                message: 'Persistent browser session stopped and closed successfully.',
            });
        } catch (err: any) {
            return formatResponse({ success: false, error: err.message || String(err) }, true);
        }
    }
);

// 4. browser_observe
server.tool(
    'browser_observe',
    'Inspect and observe current visible browser page state (headings, forms, inputs, buttons, links, tables, alerts, errors, and visible text excerpt) in a compact, structured format.',
    {
        detailLevel: z
            .enum(['summary', 'detailed'])
            .optional()
            .describe('Level of observation detail: "summary" (default) or "detailed".'),
        captureScreenshot: z
            .boolean()
            .optional()
            .describe('Whether to also capture and return a screenshot artifact (default: false).'),
    },
    async ({ detailLevel = 'summary', captureScreenshot = false }) => {
        try {
            const resp = await client.observe(detailLevel, captureScreenshot);
            if (!resp.success) {
                return formatResponse(resp, true);
            }
            return formatResponse({
                success: true,
                observation: resp.result,
                status: resp.status,
            });
        } catch (err: any) {
            return formatResponse({ success: false, error: err.message || String(err) }, true);
        }
    }
);

// 5. browser_screenshot
server.tool(
    'browser_screenshot',
    'Capture a screenshot of the active browser page and save to artifacts/browser/interactive/screenshots/.',
    {
        name: z.string().optional().describe('Custom name prefix for the screenshot file.'),
        fullPage: z.boolean().optional().describe('Capture full scrollable page (default: false).'),
    },
    async ({ name, fullPage = false }) => {
        try {
            const resp = await client.screenshot(name, fullPage);
            if (!resp.success) {
                return formatResponse(resp, true);
            }
            return formatResponse({
                success: true,
                filePath: resp.result?.filePath,
                relativePath: resp.result?.relativePath,
                status: resp.status,
            });
        } catch (err: any) {
            return formatResponse({ success: false, error: err.message || String(err) }, true);
        }
    }
);

// 6. browser_navigate
server.tool(
    'browser_navigate',
    'Navigate active browser tab to a relative path (e.g. /admin/orders) or allowed base URL.',
    {
        path_or_url: z.string().describe('Relative path (e.g., /admin/orders) or full allowed URL.'),
    },
    async ({ path_or_url }) => {
        try {
            const resp = await client.navigate(path_or_url);
            if (!resp.success) {
                return formatResponse(resp, true);
            }
            return formatResponse({
                success: true,
                currentUrl: resp.status?.currentUrl,
                title: resp.status?.title,
                status: resp.status,
            });
        } catch (err: any) {
            return formatResponse({ success: false, error: err.message || String(err) }, true);
        }
    }
);

// 7. browser_click
server.tool(
    'browser_click',
    'Click an interactive element by CSS selector, visible text, or accessible role and name.',
    {
        selector: z.string().optional().describe('CSS selector or text fallback.'),
        text: z.string().optional().describe('Exact or partial visible text to click.'),
        role: z.string().optional().describe('ARIA role (e.g., button, link, tab, menuitem).'),
        name: z.string().optional().describe('Accessible name when targeting by role.'),
        timeout: z.number().optional().describe('Timeout in milliseconds (default: 10000).'),
    },
    async ({ selector, text, role, name, timeout }) => {
        try {
            let resp;
            if (role) {
                resp = await client.clickRole(role, name, timeout);
            } else if (text) {
                resp = await client.clickText(text, false, timeout);
            } else if (selector) {
                resp = await client.click(selector, timeout);
            } else {
                return formatResponse(
                    { success: false, error: 'Must provide at least one of selector, text, or role.' },
                    true
                );
            }

            if (!resp.success) {
                return formatResponse(resp, true);
            }
            return formatResponse({
                success: true,
                currentUrl: resp.status?.currentUrl,
                title: resp.status?.title,
                status: resp.status,
            });
        } catch (err: any) {
            return formatResponse({ success: false, error: err.message || String(err) }, true);
        }
    }
);

// 8. browser_fill
server.tool(
    'browser_fill',
    'Fill text into an input field or textarea by selector, label, or placeholder. Sensitive inputs are redacted.',
    {
        selector: z.string().optional().describe('CSS selector for input field.'),
        label: z.string().optional().describe('Associated label or placeholder text.'),
        value: z.string().describe('Text value to enter into input.'),
        timeout: z.number().optional().describe('Timeout in milliseconds (default: 10000).'),
    },
    async ({ selector, label, value, timeout }) => {
        try {
            let resp;
            if (label) {
                resp = await client.fillLabel(label, value, timeout);
            } else if (selector) {
                resp = await client.fill(selector, value, timeout);
            } else {
                return formatResponse({ success: false, error: 'Must provide selector or label.' }, true);
            }

            if (!resp.success) {
                return formatResponse(resp, true);
            }
            return formatResponse({
                success: true,
                currentUrl: resp.status?.currentUrl,
                status: resp.status,
            });
        } catch (err: any) {
            return formatResponse({ success: false, error: err.message || String(err) }, true);
        }
    }
);

// 9. browser_select
server.tool(
    'browser_select',
    'Select an option in a dropdown <select> element by value or visible label.',
    {
        selector: z.string().describe('CSS selector for select element.'),
        value: z.string().describe('Option value or visible text to select.'),
        timeout: z.number().optional().describe('Timeout in milliseconds (default: 10000).'),
    },
    async ({ selector, value, timeout }) => {
        try {
            const resp = await client.select(selector, value, timeout);
            if (!resp.success) {
                return formatResponse(resp, true);
            }
            return formatResponse({
                success: true,
                currentUrl: resp.status?.currentUrl,
                status: resp.status,
            });
        } catch (err: any) {
            return formatResponse({ success: false, error: err.message || String(err) }, true);
        }
    }
);

// 10. browser_check
server.tool(
    'browser_check',
    'Check a checkbox or radio button.',
    {
        selector: z.string().describe('CSS selector for checkbox or radio element.'),
        timeout: z.number().optional().describe('Timeout in milliseconds (default: 10000).'),
    },
    async ({ selector, timeout }) => {
        try {
            const resp = await client.check(selector, timeout);
            if (!resp.success) {
                return formatResponse(resp, true);
            }
            return formatResponse({
                success: true,
                status: resp.status,
            });
        } catch (err: any) {
            return formatResponse({ success: false, error: err.message || String(err) }, true);
        }
    }
);

// 11. browser_uncheck
server.tool(
    'browser_uncheck',
    'Uncheck a checkbox.',
    {
        selector: z.string().describe('CSS selector for checkbox element.'),
        timeout: z.number().optional().describe('Timeout in milliseconds (default: 10000).'),
    },
    async ({ selector, timeout }) => {
        try {
            const resp = await client.uncheck(selector, timeout);
            if (!resp.success) {
                return formatResponse(resp, true);
            }
            return formatResponse({
                success: true,
                status: resp.status,
            });
        } catch (err: any) {
            return formatResponse({ success: false, error: err.message || String(err) }, true);
        }
    }
);

// 12. browser_press
server.tool(
    'browser_press',
    'Press a keyboard key on the active page or target element (e.g., Enter, Escape, Tab, ArrowDown, Backspace).',
    {
        key: z.string().describe('Keyboard key name (e.g., Enter, Escape, Tab, ArrowDown).'),
        selector: z
            .string()
            .optional()
            .describe('Optional CSS selector to focus before pressing key (default: body).'),
        timeout: z.number().optional().describe('Timeout in milliseconds (default: 10000).'),
    },
    async ({ key, selector = 'body', timeout }) => {
        try {
            const resp = await client.press(selector, key, timeout);
            if (!resp.success) {
                return formatResponse(resp, true);
            }
            return formatResponse({
                success: true,
                status: resp.status,
            });
        } catch (err: any) {
            return formatResponse({ success: false, error: err.message || String(err) }, true);
        }
    }
);

// 13. browser_wait_for
server.tool(
    'browser_wait_for',
    'Wait for an element matching selector or text to appear on the page.',
    {
        selector: z.string().describe('CSS selector to wait for.'),
        timeout: z
            .number()
            .optional()
            .describe('Maximum wait time in milliseconds (default: 10000, max: 30000).'),
    },
    async ({ selector, timeout = 10000 }) => {
        try {
            const boundedTimeout = Math.min(timeout, 30000);
            const resp = await client.waitFor(selector, boundedTimeout);
            if (!resp.success) {
                return formatResponse(resp, true);
            }
            return formatResponse({
                success: true,
                status: resp.status,
            });
        } catch (err: any) {
            return formatResponse({ success: false, error: err.message || String(err) }, true);
        }
    }
);

// 14. browser_tabs
server.tool(
    'browser_tabs',
    'List all open tabs in the persistent browser context.',
    {},
    async () => {
        try {
            const resp = await client.listTabs();
            if (!resp.success) {
                return formatResponse(resp, true);
            }
            return formatResponse({
                success: true,
                activeTabIndex: resp.status?.activeTabIndex,
                totalTabs: resp.status?.totalTabs,
                tabs: resp.result,
            });
        } catch (err: any) {
            return formatResponse({ success: false, error: err.message || String(err) }, true);
        }
    }
);

// 15. browser_new_tab
server.tool(
    'browser_new_tab',
    'Open a new browser tab within the same BrowserContext (session and cookies preserved) and switch to it.',
    {
        url_or_path: z.string().optional().describe('Optional relative path or URL to open in the new tab.'),
    },
    async ({ url_or_path }) => {
        try {
            const resp = await client.newTab(url_or_path);
            if (!resp.success) {
                return formatResponse(resp, true);
            }
            return formatResponse({
                success: true,
                activeTabIndex: resp.status?.activeTabIndex,
                totalTabs: resp.status?.totalTabs,
                currentUrl: resp.status?.currentUrl,
                status: resp.status,
            });
        } catch (err: any) {
            return formatResponse({ success: false, error: err.message || String(err) }, true);
        }
    }
);

// 16. browser_switch_tab
server.tool(
    'browser_switch_tab',
    'Switch the active browser tab by tab index (0-indexed).',
    {
        index: z.number().describe('Zero-based tab index to switch to.'),
    },
    async ({ index }) => {
        try {
            const resp = await client.switchTab(index);
            if (!resp.success) {
                return formatResponse(resp, true);
            }
            return formatResponse({
                success: true,
                activeTabIndex: resp.status?.activeTabIndex,
                totalTabs: resp.status?.totalTabs,
                currentUrl: resp.status?.currentUrl,
                status: resp.status,
            });
        } catch (err: any) {
            return formatResponse({ success: false, error: err.message || String(err) }, true);
        }
    }
);

// 17. browser_close_tab
server.tool(
    'browser_close_tab',
    'Close a browser tab by index (or current active tab). Cannot close the only remaining tab.',
    {
        index: z.number().optional().describe('Optional zero-based tab index to close. Defaults to current active tab.'),
    },
    async ({ index }) => {
        try {
            const resp = await client.closeTab(index);
            if (!resp.success) {
                return formatResponse(resp, true);
            }
            return formatResponse({
                success: true,
                message: 'Tab closed successfully.',
                activeTabIndex: resp.status?.activeTabIndex,
                totalTabs: resp.status?.totalTabs,
                status: resp.status,
            });
        } catch (err: any) {
            return formatResponse({ success: false, error: err.message || String(err) }, true);
        }
    }
);

// 18. browser_viewport
server.tool(
    'browser_viewport',
    'Resize the browser viewport using a standard matrix preset or explicit width and height.',
    {
        preset: z
            .string()
            .optional()
            .describe(
                'Viewport preset: mobile_s (320), mobile_m (375), mobile (390), mobile_l (430), small_tablet (640), tablet (768), tablet_l (820), desktop_sm (1024), desktop_md (1280), desktop (1440), desktop_fhd (1920).'
            ),
        width: z.number().optional().describe('Explicit width in pixels.'),
        height: z.number().optional().describe('Explicit height in pixels.'),
    },
    async ({ preset, width, height }) => {
        try {
            const target = preset || width || 1440;
            const resp = await client.setViewport(target, height);
            if (!resp.success) {
                return formatResponse(resp, true);
            }
            return formatResponse({
                success: true,
                viewport: resp.status?.viewport,
                status: resp.status,
            });
        } catch (err: any) {
            return formatResponse({ success: false, error: err.message || String(err) }, true);
        }
    }
);

// 19. browser_diagnostics
server.tool(
    'browser_diagnostics',
    'Retrieve console logs, console errors, uncaught exceptions, and failed network requests from the active page (with sensitive credentials redacted).',
    {},
    async () => {
        try {
            const diagnostics = await client.getDiagnostics();
            return formatResponse({
                success: true,
                diagnostics,
            });
        } catch (err: any) {
            return formatResponse({ success: false, error: err.message || String(err) }, true);
        }
    }
);

// 20. browser_login
server.tool(
    'browser_login',
    'Authoritatively log in as a designated QA user role with automatic MFA resolution via the real login flow.',
    {
        role: z
            .enum([
                'SUPER_ADMIN',
                'ADMIN',
                'ACCOUNTANT',
                'SALESMAN',
                'SALESMAN_B',
                'WAREHOUSE_MANAGER',
                'DELIVERY_PARTNER',
            ])
            .describe('QA user role to log in as.'),
    },
    async ({ role }) => {
        try {
            const resp = await client.login(role as any);
            if (!resp.success) {
                return formatResponse(resp, true);
            }
            return formatResponse({
                success: true,
                message: `Successfully authenticated as ${role}.`,
                currentUrl: resp.status?.currentUrl,
                title: resp.status?.title,
                status: resp.status,
            });
        } catch (err: any) {
            return formatResponse({ success: false, error: err.message || String(err) }, true);
        }
    }
);

// 21. browser_execute_sequence
server.tool(
    'browser_execute_sequence',
    'Execute a sequence of small, structured safe browser actions in order (no eval or arbitrary JS allowed).',
    {
        steps: z
            .array(z.record(z.any()))
            .describe(
                'List of structured step objects, e.g. [{"action": "navigate", "path": "/admin/orders"}, {"action": "click", "text": "New Order"}].'
            ),
    },
    async ({ steps }) => {
        try {
            const resp = await client.executeSequence(steps);
            if (!resp.success) {
                return formatResponse(resp, true);
            }
            return formatResponse({
                success: true,
                stepResults: resp.result?.results,
                finalStatus: resp.result?.finalStatus || resp.status,
            });
        } catch (err: any) {
            return formatResponse({ success: false, error: err.message || String(err) }, true);
        }
    }
);

// 22. browser_confirm_destructive_action
server.tool(
    'browser_confirm_destructive_action',
    'Enforce user confirmation before executing potentially destructive actions (delete, refund, destructive reset, bulk update).',
    {
        actionDescription: z.string().describe('Clear human-readable description of the destructive action.'),
        confirmed: z.boolean().describe('Must be explicitly true after getting user consent.'),
        action: z
            .record(z.any())
            .optional()
            .describe('Optional structured action object to execute once confirmed.'),
    },
    async ({ actionDescription, confirmed, action }) => {
        try {
            const resp = await client.confirmDestructiveAction(actionDescription, confirmed, action);
            if (!resp.success) {
                return formatResponse(resp, true);
            }
            return formatResponse(resp.result || resp);
        } catch (err: any) {
            return formatResponse({ success: false, error: err.message || String(err) }, true);
        }
    }
);

async function main() {
    const transport = new StdioServerTransport();
    await server.connect(transport);
    console.error('[Unique Distributors Browser MCP] Server running on stdio.');
}

main().catch((err) => {
    console.error('[Unique Distributors Browser MCP Fatal Error]', err);
    process.exit(1);
});
