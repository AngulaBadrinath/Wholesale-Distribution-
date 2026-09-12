import http from 'http';
import fs from 'fs';
import path from 'path';
import { InteractiveBrowserController } from './controller.ts';

const DEFAULT_PORT = parseInt(process.env.PLAYWRIGHT_QA_PORT || '4444', 10);
const ARTIFACTS_DIR = path.resolve(process.cwd(), 'artifacts', 'browser', 'interactive');
const SESSION_FILE = path.join(ARTIFACTS_DIR, 'session.json');

export class InteractiveBrowserServer {
    private controller: InteractiveBrowserController;
    private server: http.Server | null = null;
    private port: number;

    constructor(port = DEFAULT_PORT) {
        this.port = port;
        this.controller = new InteractiveBrowserController();
    }

    async start(options: { headed?: boolean; baseUrl?: string; port?: number } = {}): Promise<void> {
        if (options.port) {
            this.port = options.port;
        }

        fs.mkdirSync(ARTIFACTS_DIR, { recursive: true });

        // 1. Launch the headed browser session
        console.log(`[Interactive QA Server] Launching persistent browser...`);
        const status = await this.controller.start({
            headed: options.headed !== undefined ? options.headed : true,
            baseUrl: options.baseUrl,
        });

        console.log(`====================================================`);
        console.log(`  UNIQUE DISTRIBUTORS — PERSISTENT BROWSER QA SERVER `);
        console.log(`====================================================`);
        console.log(`[Status]      ACTIVE`);
        console.log(`[Environment] ${status.environment}`);
        console.log(`[Base URL]    ${status.baseUrl}`);
        console.log(`[Browser]     ${status.browserName} (${status.browserVersion})`);
        console.log(`[Executable]  ${status.executablePath}`);
        console.log(`[Port]        http://127.0.0.1:${this.port}`);
        console.log(`[Session Lock] ${SESSION_FILE}`);
        console.log(`====================================================`);
        console.log(`Browser window is open on screen. Ready for instructions.`);

        // 2. Start HTTP Server for Agent / CLI commands
        this.server = http.createServer(async (req, res) => {
            // Enable JSON and CORS
            res.setHeader('Content-Type', 'application/json');
            res.setHeader('Access-Control-Allow-Origin', '*');
            res.setHeader('Access-Control-Allow-Methods', 'GET, POST, OPTIONS');
            res.setHeader('Access-Control-Allow-Headers', 'Content-Type');

            if (req.method === 'OPTIONS') {
                res.writeHead(204);
                res.end();
                return;
            }

            const url = new URL(req.url || '/', `http://127.0.0.1:${this.port}`);

            try {
                if (req.method === 'GET' && url.pathname === '/api/status') {
                    const currentStatus = await this.controller.getStatus();
                    res.writeHead(200);
                    res.end(JSON.stringify({ success: true, data: currentStatus }));
                    return;
                }

                if (req.method === 'GET' && url.pathname === '/api/diagnostics') {
                    const diagnostics = this.controller.getDiagnostics();
                    res.writeHead(200);
                    res.end(JSON.stringify({ success: true, data: diagnostics }));
                    return;
                }

                if (req.method === 'POST' && url.pathname === '/api/stop') {
                    console.log(`[Interactive QA Server] Received stop command.`);
                    await this.stop();
                    res.writeHead(200);
                    res.end(JSON.stringify({ success: true, message: 'Browser session stopped.' }));
                    setTimeout(() => process.exit(0), 500);
                    return;
                }

                if (req.method === 'POST' && url.pathname === '/api/command') {
                    let body = '';
                    req.on('data', (chunk) => {
                        body += chunk;
                    });
                    req.on('end', async () => {
                        try {
                            const payload = JSON.parse(body || '{}');
                            const action = payload.action;
                            const params = payload.params || {};

                            if (!action) {
                                res.writeHead(400);
                                res.end(JSON.stringify({ success: false, error: 'Missing "action" parameter.' }));
                                return;
                            }

                            const result = await this.executeAction(action, params);
                            const currentStatus = await this.controller.getStatus();
                            res.writeHead(200);
                            res.end(JSON.stringify({
                                success: true,
                                action,
                                result,
                                status: currentStatus,
                            }));
                        } catch (err: any) {
                            let screenshotPath: string | undefined;
                            try {
                                const shot = await this.controller.screenshot({ name: `error_${Date.now()}` });
                                screenshotPath = shot.filePath;
                            } catch {}

                            const currentStatus = await this.controller.getStatus().catch(() => null);
                            const diagnostics = this.controller.getDiagnostics();

                            res.writeHead(500);
                            res.end(JSON.stringify({
                                success: false,
                                error: err.message || String(err),
                                action: payload?.action,
                                screenshotPath,
                                status: currentStatus,
                                diagnosticsSummary: {
                                    consoleErrors: diagnostics.consoleErrors,
                                    networkErrors: diagnostics.networkErrors,
                                },
                            }));
                        }
                    });
                    return;
                }

                // 404 handler
                res.writeHead(404);
                res.end(JSON.stringify({ success: false, error: 'Endpoint not found.' }));
            } catch (err: any) {
                res.writeHead(500);
                res.end(JSON.stringify({ success: false, error: err.message || String(err) }));
            }
        });

        await new Promise<void>((resolve, reject) => {
            this.server!.listen(this.port, '127.0.0.1', () => {
                this.saveSessionLock();
                resolve();
            });
            this.server!.on('error', (err) => {
                reject(err);
            });
        });

        // Clean up handlers
        const cleanup = async () => {
            await this.stop();
            process.exit(0);
        };
        process.on('SIGINT', cleanup);
        process.on('SIGTERM', cleanup);
    }

    private async executeAction(action: string, params: any): Promise<any> {
        switch (action) {
            case 'navigate':
            case 'goto':
                return await this.controller.navigate(params.url || params.path || params.urlOrPath);

            case 'click':
                return await this.controller.click(params.selector, params.timeout);

            case 'clickText':
                return await this.controller.clickText(params.text, params.exact, params.timeout);

            case 'clickRole':
                return await this.controller.clickRole(params.role, params.name, params.timeout);

            case 'fill':
                return await this.controller.fill(params.selector, params.value, params.timeout);

            case 'fillLabel':
                return await this.controller.fillLabel(params.label, params.value, params.timeout);

            case 'select':
                return await this.controller.select(params.selector, params.value || params.valueOrLabel, params.timeout);

            case 'check':
                return await this.controller.check(params.selector, params.timeout);

            case 'uncheck':
                return await this.controller.uncheck(params.selector, params.timeout);

            case 'press':
                return await this.controller.press(params.selector || 'body', params.key, params.timeout);

            case 'waitFor':
                return await this.controller.waitFor(params.selector, params.timeout);

            case 'wait':
                return await this.controller.wait(params.ms || 1000);

            case 'login':
                return await this.controller.login(params.role);

            case 'screenshot':
                return await this.controller.screenshot({ name: params.name, fullPage: params.fullPage });

            case 'setViewport':
            case 'viewport':
                return await this.controller.setViewport(params.preset || params.width, params.height);

            case 'newTab':
                return await this.controller.newTab(params.url || params.urlOrPath);

            case 'switchTab':
                return await this.controller.switchTab(params.index);

            case 'closeTab':
                return await this.controller.closeTab(params.index);

            case 'listTabs':
                return await this.controller.listTabs();

            case 'reload':
                return await this.controller.reload();

            case 'back':
                return await this.controller.back();

            case 'forward':
                return await this.controller.forward();

            case 'diagnostics':
                return this.controller.getDiagnostics();

            case 'status':
                return await this.controller.getStatus();

            default:
                throw new Error(`Unknown action: "${action}".`);
        }
    }

    private saveSessionLock(): void {
        const sessionData = {
            pid: process.pid,
            port: this.port,
            baseUrl: process.env.PLAYWRIGHT_BASE_URL || 'http://localhost:8000',
            startedAt: new Date().toISOString(),
        };
        fs.writeFileSync(SESSION_FILE, JSON.stringify(sessionData, null, 2), 'utf8');
    }

    private removeSessionLock(): void {
        try {
            if (fs.existsSync(SESSION_FILE)) {
                fs.unlinkSync(SESSION_FILE);
            }
        } catch {}
    }

    async stop(): Promise<void> {
        this.removeSessionLock();
        await this.controller.stop();
        if (this.server) {
            await new Promise<void>((resolve) => {
                this.server!.close(() => resolve());
            });
            this.server = null;
        }
    }
}

// Direct execution entrypoint
if (process.argv[1] && process.argv[1].endsWith('server.ts') || process.argv[1]?.endsWith('server.js')) {
    const portArg = process.argv.find((a) => a.startsWith('--port='));
    const port = portArg ? parseInt(portArg.split('=')[1], 10) : DEFAULT_PORT;
    const headless = process.argv.includes('--headless');

    const server = new InteractiveBrowserServer(port);
    server.start({ headed: !headless }).catch((err) => {
        console.error('[Interactive QA Server Fatal Error]', err);
        process.exit(1);
    });
}
