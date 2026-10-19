import './vscodeStub';
import { SidebarProvider } from '../sidebarProvider';
// eslint-disable-next-line @typescript-eslint/no-var-requires
const { test, mock, afterEach } = require('node:test');
// eslint-disable-next-line @typescript-eslint/no-var-requires
const assert = require('node:assert');

afterEach(() => mock.timers.reset());

/**
 * A provider monitoring session 7 with timers under test control. Every request is logged
 * as "METHOD path"; the session read answers with `session` (or `sessionStatus`).
 */
function monitoredProvider(session: object, sessionStatus = 200) {
    mock.timers.enable({ apis: ['setInterval'] });

    const provider: any = new SidebarProvider({ fsPath: '/ext' } as any, { get: (_k: string, d: any) => d, update: async () => undefined } as any);
    const requests: string[] = [];

    provider._sessionId = 7;
    provider._view = { visible: true, webview: { postMessage: () => undefined } };
    provider.setupActivityListeners = () => undefined;
    provider.setupFocusTracking = () => undefined;
    provider.checkIdleState = () => undefined;
    provider.provisionStarterFiles = async () => undefined;
    provider.verifyWorkspaceFileIntegrity = async () => undefined;
    provider.makeRequest = async (method: string, path: string) => {
        requests.push(`${method} ${path}`);
        if (path === '/api/v1/sessions/7') {
            return { status: sessionStatus, body: JSON.stringify(session) };
        }
        return { status: 200, body: JSON.stringify({ chats: [], leaderboard: [] }) };
    };

    provider.startMonitoring();

    const advance = async (ms: number) => {
        for (let t = 0; t < ms; t += 1000) {
            mock.timers.tick(1000);
            await new Promise(r => setImmediate(r));
        }
    };

    return { provider, requests, advance };
}

test('one heartbeat request every 15 seconds: the session read, no separate ping', async () => {
    const { requests, advance } = monitoredProvider({ is_group_lab: false });
    await advance(1000);
    requests.length = 0;

    await advance(15000);

    assert.deepStrictEqual(requests.filter(r => !r.includes('/leaderboard')), ['GET /api/v1/sessions/7']);
});

test('a solo lab never polls the chat', async () => {
    const { requests, advance } = monitoredProvider({ is_group_lab: false });

    await advance(60000);

    assert.strictEqual(requests.filter(r => r.includes('/chat')).length, 0);
});

test('a team lab still polls the chat', async () => {
    const { requests, advance } = monitoredProvider({ is_group_lab: true });

    await advance(16000);

    assert.ok(requests.filter(r => r.includes('/chat')).length >= 3);
});

test('syncs back off while the server keeps failing', async () => {
    const { provider, requests, advance } = monitoredProvider({}, 500);
    await advance(1000);

    await advance(120000);

    const syncs = requests.filter(r => r === 'GET /api/v1/sessions/7').length;
    // 1 at start, then 30 s, 60 s and 120 s gaps instead of one every 15 s (9)
    assert.ok(syncs <= 4, `expected at most 4 syncs in 2 minutes, got ${syncs}`);
    assert.strictEqual(requests.filter(r => r.includes('/leaderboard') || r.includes('/chat')).length, 1, 'only the first leaderboard load');
    provider.stopSessionTimers?.();
});
