import { fakeSecrets, shown } from './vscodeStub';
import { SidebarProvider } from '../sidebarProvider';
// eslint-disable-next-line @typescript-eslint/no-var-requires
const { test } = require('node:test');
// eslint-disable-next-line @typescript-eslint/no-var-requires
const assert = require('node:assert');

type Team = { leader_id: number; leader_name: string; is_leader: boolean; can_submit: boolean; votes: object[] } | null;

/**
 * A provider connected to session 7 on a group lab, with the workspace and server calls
 * replaced. Each request stays open until the test answers it.
 */
function teamProvider(team: Team) {
    const provider: any = new SidebarProvider({ fsPath: '/ext' } as any, { get: (_k: string, d: any) => d, update: async () => undefined } as any, fakeSecrets());
    const requests: { path: string; body: any; answer: (status: number, body: object) => void }[] = [];
    const posted: any[] = [];

    provider._sessionId = 7;
    provider._lastSessionData = { is_group_lab: true, team };
    provider._view = { webview: { postMessage: (message: any) => { posted.push(message); } } };
    provider.recordActivity = () => undefined;
    provider.syncSessionState = async () => undefined;
    provider.syncActivityBeforeSubmit = async () => undefined;
    provider.stopSessionTimers = () => { posted.push({ type: 'timersStopped' }); };
    provider.collectSubmissionFiles = async () => ({
        primaryCode: 'class Main {}',
        primaryFileName: 'Main.java',
        filesPayload: [{ name: 'Main.java', content: 'class Main {}', is_primary: true }],
        fileUris: [],
    });
    provider.collectDiagnostics = () => [];
    provider.makeRequest = (_method: string, path: string, body: any) => new Promise(resolve => {
        requests.push({ path, body, answer: (status, answer) => resolve({ status, body: JSON.stringify(answer) }) });
    });

    return { provider, requests, posted };
}

const tick = () => new Promise(r => setImmediate(r));
const alicesTeam = (mine: boolean): Team => ({ leader_id: 2, leader_name: 'Alice', is_leader: mine, can_submit: mine, votes: [] });

test('a teammate who is not the leader cannot submit', async () => {
    const { provider, requests } = teamProvider(alicesTeam(false));
    shown.length = 0;

    await provider.handleSubmit();

    assert.strictEqual(requests.length, 0);
    assert.ok(shown.some(s => s.level === 'error' && s.message === 'Only your team leader, Alice, can submit this lab.'));
});

test('the leader submits as before', async () => {
    const { provider, requests } = teamProvider(alicesTeam(true));
    const { stubState } = require('./vscodeStub');
    stubState.warningAnswer = 'Yes, Submit';

    const submit = provider.handleSubmit();
    await tick();
    await tick();

    assert.strictEqual(requests.length, 1);
    assert.ok(requests[0].path.endsWith('/sessions/7/submit'));
    requests[0].answer(200, { performance_score: 80, completed_tasks: [1], evaluation: {}, team: { submitted_by: 2, graded_user_ids: [2, 3] } });
    await submit;
});

test('a session without a team can submit', async () => {
    const { provider, requests } = teamProvider(null);
    const { stubState } = require('./vscodeStub');
    stubState.warningAnswer = 'Yes, Submit';

    const submit = provider.handleSubmit();
    await tick();
    await tick();

    assert.strictEqual(requests.length, 1);
    requests[0].answer(200, { performance_score: 80, completed_tasks: [1], evaluation: {} });
    await submit;
});

test('a submit the server refuses shows its message and keeps the session running', async () => {
    // The extension still thinks this student leads; the team changed leader a moment ago
    const { provider, requests, posted } = teamProvider(alicesTeam(true));
    const { stubState } = require('./vscodeStub');
    stubState.warningAnswer = 'Yes, Submit';
    shown.length = 0;
    let synced = 0;
    provider.syncSessionState = async () => { synced++; };

    const submit = provider.handleSubmit();
    await tick();
    await tick();
    requests[0].answer(403, { error: 'not_team_leader', message: 'Only your team leader, Bob, can submit this lab.' });
    await submit;

    assert.ok(shown.some(s => s.level === 'error' && s.message.includes('Only your team leader, Bob, can submit this lab.')));
    assert.ok(!posted.some(m => m.type === 'timersStopped'));
    assert.strictEqual(synced, 1);
});

test('choosing a leader posts the candidate and relays the result', async () => {
    const { provider, requests, posted } = teamProvider(alicesTeam(false));

    const choose = provider.handleChooseLeader(3);
    await tick();

    assert.strictEqual(requests.length, 1);
    assert.ok(requests[0].path.endsWith('/sessions/7/leader'));
    assert.deepStrictEqual(requests[0].body, { candidate_id: 3 });

    requests[0].answer(200, { status: 'success', changed: true, leader_id: 3, votes: [] });
    await choose;

    assert.deepStrictEqual(posted.find(m => m.type === 'leaderResult'), { type: 'leaderResult', changed: true, leader_id: 3, votes: [] });
});

test('a refused leader choice shows the server message', async () => {
    const { provider, requests, posted } = teamProvider(alicesTeam(false));
    shown.length = 0;

    const choose = provider.handleChooseLeader(99);
    await tick();
    requests[0].answer(422, { message: 'Dave is not in Team A.' });
    await choose;

    assert.ok(shown.some(s => s.level === 'error' && s.message.includes('Dave is not in Team A.')));
    assert.ok(!posted.some(m => m.type === 'leaderResult'));
});

test('the sidebar script is valid JavaScript and wires the leader controls', () => {
    const { provider } = teamProvider(null);
    const html: string = provider._getHtmlForWebview({ cspSource: 'test', asWebviewUri: (uri: unknown) => uri });
    const scripts = [...html.matchAll(/<script[^>]*>([\s\S]*?)<\/script>/g)].map(match => match[1]).filter(body => body.trim() !== '');

    assert.ok(scripts.length > 0);
    for (const body of scripts) {
        // Throws a SyntaxError if the script would not load in the sidebar
        new Function(body);
    }
    const script = scripts.join('\n');
    assert.ok(script.includes("type: 'chooseLeader'"));
    assert.ok(script.includes("case 'leaderResult'"));
    assert.ok(script.includes('c.is_system'));
});

test('choosing a leader does nothing outside a team', async () => {
    const { provider, requests } = teamProvider(null);

    await provider.handleChooseLeader(3);

    assert.strictEqual(requests.length, 0);
});
