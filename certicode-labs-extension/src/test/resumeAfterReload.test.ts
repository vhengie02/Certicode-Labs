import { fakeSecrets, memento } from './vscodeStub';
import { SidebarProvider } from '../sidebarProvider';
// eslint-disable-next-line @typescript-eslint/no-var-requires
const { test } = require('node:test');
// eslint-disable-next-line @typescript-eslint/no-var-requires
const assert = require('node:assert');

const BACKEND = 'https://labs.example.com';

/**
 * A provider whose connect only records its arguments, sharing state and secrets with the
 * window before the reload.
 */
function provider(state: any, secrets: any) {
    const p: any = new SidebarProvider({ fsPath: '/ext' } as any, state, secrets);
    p.connects = [];
    p.connectToSession = async (...args: any[]) => { p.connects.push(args); return true; };
    p.makeRequest = async () => ({});
    return p;
}

/** A window connected to session 7 that VS Code then shut down. */
async function shutDownWindow(state: any, secrets: any) {
    await state.update('certicode.trustedBackends', [BACKEND]);
    const before: any = new SidebarProvider({ fsPath: '/ext' } as any, state, secrets);
    before._backendUrl = BACKEND;
    before._sessionId = 7;
    before._apiToken = 'tok';
    before.makeRequest = async () => ({});
    await before.rememberSession();
    await before.notifyVsCodeClosed();
}

test('a reload reconnects to the same session with its token', async () => {
    const state = memento();
    const secrets = fakeSecrets();
    await shutDownWindow(state, secrets);

    const after = provider(state, secrets);
    await after.resumeAfterReload();

    assert.deepStrictEqual(after.connects, [[BACKEND, 7, 'tok']]);
});

test('starting long after a shutdown does not reconnect, and forgets the session', async () => {
    const state = memento();
    const secrets = fakeSecrets();
    await shutDownWindow(state, secrets);
    const saved = state.get('certicode.resumeSession');
    await state.update('certicode.resumeSession', { ...saved, closedAt: Date.now() - 60000 });

    const after = provider(state, secrets);
    await after.resumeAfterReload();

    assert.deepStrictEqual(after.connects, []);
    assert.strictEqual(state.get('certicode.resumeSession'), undefined);
    assert.strictEqual(await secrets.get('certicode.resumeSessionToken'), undefined);
});

test('a window that never shut down with a session open does not reconnect', async () => {
    const state = memento();
    const secrets = fakeSecrets();
    await state.update('certicode.trustedBackends', [BACKEND]);
    await state.update('certicode.resumeSession', { backendUrl: BACKEND, sessionId: 7 });

    const after = provider(state, secrets);
    await after.resumeAfterReload();

    assert.deepStrictEqual(after.connects, []);
});

test('a server the student no longer trusts is not reconnected to', async () => {
    const state = memento();
    const secrets = fakeSecrets();
    await shutDownWindow(state, secrets);
    await state.update('certicode.trustedBackends', []);

    const after = provider(state, secrets);
    await after.resumeAfterReload();

    assert.deepStrictEqual(after.connects, []);
});
