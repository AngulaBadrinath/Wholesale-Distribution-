import http from 'http';
import fs from 'fs';
import path from 'path';
import { spawn } from 'child_process';
import type { ControllerStatus } from './controller.ts';
import type { UserRole } from '../helpers/auth.ts';

const DEFAULT_PORT = parseInt(process.env.PLAYWRIGHT_QA_PORT || '4444', 10);
const ARTIFACTS_DIR = path.resolve(process.cwd(), 'artifacts', 'browser', 'interactive');
const SESSION_FILE = path.join(ARTIFACTS_DIR, 'session.json');

export interface CommandResponse {
    success: boolean;
    action?: string;
    result?: any;
    status?: ControllerStatus;
    error?: string;
    screenshotPath?: string;
    diagnosticsSummary?: any;
}

export class InteractiveBrowserClient {
    private port: number;

    constructor(port = DEFAULT_PORT) {
        this.port = port;
    }

    /**
     * Ensure the persistent server daemon is running; spawn in background if necessary.
     */
    async ensureServer(headed = true): Promise<ControllerStatus> {
        const running = await this.isServerRunning();
        if (running) {
            return await this.getStatus();
        }

        console.log('[Browser QA Client] Starting persistent headed browser server...');
        const serverScript = path.resolve(process.cwd(), 'tests', 'browser', 'interactive', 'server.ts');

        const args = ['--no-warnings', serverScript];
        if (!headed) {
            args.push('--headless');
        }
        if (this.port !== 4444) {
            args.push(`--port=${this.port}`);
        }

        const child = spawn(process.execPath, args, {
            detached: true,
            stdio: 'ignore',
            cwd: process.cwd(),
            env: process.env,
        });
        child.unref();

        // Wait up to 15 seconds for server to be ready
        const startTime = Date.now();
        while (Date.now() - startTime < 15000) {
            await new Promise((r) => setTimeout(r, 500));
            const ready = await this.isServerRunning();
            if (ready) {
                console.log('[Browser QA Client] Persistent browser is ready.');
                return await this.getStatus();
            }
        }

        throw new Error('[Browser QA Client] Timed out waiting for browser server to start.');
    }

    /**
     * Check if the persistent server is actively listening.
     */
    async isServerRunning(): Promise<boolean> {
        try {
            const resp = await this.httpGet('/api/status');
            return resp.success === true;
        } catch {
            return false;
        }
    }

    /**
     * Execute action on the persistent browser session.
     */
    async sendCommand(action: string, params: any = {}): Promise<CommandResponse> {
        await this.ensureServer();
        return await this.httpPost('/api/command', { action, params });
    }

    async getStatus(): Promise<ControllerStatus> {
        const resp = await this.httpGet('/api/status');
        if (!resp.success) {
            throw new Error(resp.error || 'Failed to fetch status.');
        }
        return resp.data;
    }

    async getDiagnostics(): Promise<any> {
        await this.ensureServer();
        const resp = await this.httpGet('/api/diagnostics');
        return resp.data;
    }

    async stop(): Promise<void> {
        try {
            await this.httpPost('/api/stop', {});
        } catch {}
    }

    // High-Level Helper Commands
    async navigate(urlOrPath: string): Promise<CommandResponse> {
        return this.sendCommand('navigate', { urlOrPath });
    }

    async click(selector: string, timeout?: number): Promise<CommandResponse> {
        return this.sendCommand('click', { selector, timeout });
    }

    async clickText(text: string, exact = false, timeout?: number): Promise<CommandResponse> {
        return this.sendCommand('clickText', { text, exact, timeout });
    }

    async clickRole(role: string, name?: string, timeout?: number): Promise<CommandResponse> {
        return this.sendCommand('clickRole', { role, name, timeout });
    }

    async fill(selector: string, value: string, timeout?: number): Promise<CommandResponse> {
        return this.sendCommand('fill', { selector, value, timeout });
    }

    async fillLabel(label: string, value: string, timeout?: number): Promise<CommandResponse> {
        return this.sendCommand('fillLabel', { label, value, timeout });
    }

    async select(selector: string, value: string, timeout?: number): Promise<CommandResponse> {
        return this.sendCommand('select', { selector, value, timeout });
    }

    async check(selector: string, timeout?: number): Promise<CommandResponse> {
        return this.sendCommand('check', { selector, timeout });
    }

    async uncheck(selector: string, timeout?: number): Promise<CommandResponse> {
        return this.sendCommand('uncheck', { selector, timeout });
    }

    async press(selector: string, key: string, timeout?: number): Promise<CommandResponse> {
        return this.sendCommand('press', { selector, key, timeout });
    }

    async waitFor(selector: string, timeout?: number): Promise<CommandResponse> {
        return this.sendCommand('waitFor', { selector, timeout });
    }

    async wait(ms: number): Promise<CommandResponse> {
        return this.sendCommand('wait', { ms });
    }

    async login(role: UserRole): Promise<CommandResponse> {
        return this.sendCommand('login', { role });
    }

    async screenshot(name?: string, fullPage?: boolean): Promise<CommandResponse> {
        return this.sendCommand('screenshot', { name, fullPage });
    }

    async setViewport(presetOrWidth: string | number, height?: number): Promise<CommandResponse> {
        return this.sendCommand('setViewport', { preset: presetOrWidth, width: presetOrWidth, height });
    }

    async newTab(urlOrPath?: string): Promise<CommandResponse> {
        return this.sendCommand('newTab', { urlOrPath });
    }

    async switchTab(index: number): Promise<CommandResponse> {
        return this.sendCommand('switchTab', { index });
    }

    async closeTab(index?: number): Promise<CommandResponse> {
        return this.sendCommand('closeTab', { index });
    }

    async listTabs(): Promise<CommandResponse> {
        return this.sendCommand('listTabs', {});
    }

    async reload(): Promise<CommandResponse> {
        return this.sendCommand('reload', {});
    }

    async back(): Promise<CommandResponse> {
        return this.sendCommand('back', {});
    }

    async forward(): Promise<CommandResponse> {
        return this.sendCommand('forward', {});
    }

    // HTTP helpers
    private httpGet(path: string): Promise<any> {
        return new Promise((resolve, reject) => {
            const req = http.request(
                {
                    hostname: '127.0.0.1',
                    port: this.port,
                    path,
                    method: 'GET',
                    timeout: 10000,
                },
                (res) => {
                    let data = '';
                    res.on('data', (c) => (data += c));
                    res.on('end', () => {
                        try {
                            resolve(JSON.parse(data || '{}'));
                        } catch {
                            resolve({ success: false, data });
                        }
                    });
                }
            );
            req.on('error', reject);
            req.on('timeout', () => {
                req.destroy();
                reject(new Error('HTTP request timed out.'));
            });
            req.end();
        });
    }

    private httpPost(path: string, body: any): Promise<any> {
        return new Promise((resolve, reject) => {
            const bodyStr = JSON.stringify(body);
            const req = http.request(
                {
                    hostname: '127.0.0.1',
                    port: this.port,
                    path,
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Content-Length': Buffer.byteLength(bodyStr),
                    },
                    timeout: 30000,
                },
                (res) => {
                    let data = '';
                    res.on('data', (c) => (data += c));
                    res.on('end', () => {
                        try {
                            resolve(JSON.parse(data || '{}'));
                        } catch {
                            resolve({ success: false, data });
                        }
                    });
                }
            );
            req.on('error', reject);
            req.on('timeout', () => {
                req.destroy();
                reject(new Error('HTTP command timed out.'));
            });
            req.write(bodyStr);
            req.end();
        });
    }
}
