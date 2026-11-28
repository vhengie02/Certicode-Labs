import { fakeSecrets } from './vscodeStub';
import { SidebarProvider } from '../sidebarProvider';
// eslint-disable-next-line @typescript-eslint/no-var-requires
const { test } = require('node:test');
// eslint-disable-next-line @typescript-eslint/no-var-requires
const assert = require('node:assert');

/** Stands in for any DOM value the chat code does not read: every property and call gives itself back. */
const anything: any = new Proxy(function () { /* no-op */ }, {
    get: (_t, prop) => {
        if (prop === Symbol.iterator) { return function* () { /* empty */ }; }
        if (prop === Symbol.toPrimitive) { return () => ''; }
        return anything;
    },
    apply: () => anything,
});

type FakeElement = {
    value: string;
    hidden: boolean;
    fire: (type: string, event?: object) => void;
};

function fakeElement(): FakeElement {
    const listeners: Record<string, ((event: object) => void)[]> = {};
    const own: any = {
        value: '',
        hidden: false,
        addEventListener: (type: string, fn: (event: object) => void) => { (listeners[type] ??= []).push(fn); },
        fire: (type: string, event: object = {}) => { for (const fn of listeners[type] ?? []) { fn(event); } },
        querySelectorAll: () => [],
    };
    return new Proxy(own, {
        get: (target, prop) => (prop in target ? target[prop] : anything),
        set: (target, prop, value) => { target[prop] = value; return true; },
    });
}

/** Runs the sidebar's own script against a fake DOM and returns its elements and the messages it posts. */
function loadSidebar() {
    const provider: any = new SidebarProvider({ fsPath: '/ext' } as any, { get: (_k: string, d: any) => d, update: async () => undefined } as any, fakeSecrets());
    const html: string = provider._getHtmlForWebview({ cspSource: 'test', asWebviewUri: (uri: unknown) => uri });
    const script = [...html.matchAll(/<script[^>]*>([\s\S]*?)<\/script>/g)]
        .map(match => match[1])
        .filter(body => body.trim() !== '')
        .join('\n');

    const elements = new Map<string, FakeElement>();
    const el = (id: string) => {
        if (!elements.has(id)) { elements.set(id, fakeElement()); }
        return elements.get(id)!;
    };
    const document = { getElementById: el, createElement: () => fakeElement(), querySelectorAll: () => [], addEventListener: () => undefined };
    const window = { addEventListener: () => undefined };
    const posted: any[] = [];
    const acquireVsCodeApi = () => ({ postMessage: (message: any) => { posted.push(message); }, getState: () => undefined, setState: () => undefined });

    new Function('acquireVsCodeApi', 'document', 'window', script)(acquireVsCodeApi, document, window);

    return { el, chats: () => posted.filter(m => m.type === 'sendChat') };
}

function keydown(key: string, extra: object = {}) {
    const event = { key, shiftKey: false, isComposing: false, defaultPrevented: false, preventDefault() { this.defaultPrevented = true; }, ...extra };
    return event;
}

test('the snippet box has its own send button', () => {
    const provider: any = new SidebarProvider({ fsPath: '/ext' } as any, { get: (_k: string, d: any) => d, update: async () => undefined } as any, fakeSecrets());
    const html: string = provider._getHtmlForWebview({ cspSource: 'test', asWebviewUri: (uri: unknown) => uri });
    const snippetRow = html.match(/<div id="snippet-container"[\s\S]*?<\/div>/)?.[0] ?? '';

    assert.ok(snippetRow.includes('id="chat-snippet-input"'));
    assert.ok(snippetRow.includes('id="snippet-send-btn"'));
});

test('Enter in the snippet box sends the snippet and closes the box', () => {
    const { el, chats } = loadSidebar();
    el('chat-snippet-input').value = 'public class Main {\n    int x;\n}';
    el('snippet-container').hidden = false;

    const event = keydown('Enter');
    el('chat-snippet-input').fire('keydown', event);

    assert.strictEqual(event.defaultPrevented, true);
    assert.deepStrictEqual(chats(), [{ type: 'sendChat', message: 'Code snippet shared:', codeSnippet: 'public class Main {\n    int x;\n}' }]);
    assert.strictEqual(el('chat-snippet-input').value, '');
    assert.strictEqual(el('snippet-container').hidden, true);
});

test('Shift+Enter in the snippet box is a new line, not a send', () => {
    const { el, chats } = loadSidebar();
    el('chat-snippet-input').value = 'int x;';

    const event = keydown('Enter', { shiftKey: true });
    el('chat-snippet-input').fire('keydown', event);

    assert.strictEqual(event.defaultPrevented, false);
    assert.strictEqual(chats().length, 0);
    assert.strictEqual(el('chat-snippet-input').value, 'int x;');
});

test('Enter that confirms an IME composition does not send', () => {
    const { el, chats } = loadSidebar();
    el('chat-snippet-input').value = 'int x;';

    const event = keydown('Enter', { isComposing: true });
    el('chat-snippet-input').fire('keydown', event);

    assert.strictEqual(event.defaultPrevented, false);
    assert.strictEqual(chats().length, 0);
});

test('the snippet send button sends the message and the snippet together', () => {
    const { el, chats } = loadSidebar();
    el('chat-input').value = 'look at this';
    el('chat-snippet-input').value = 'int x;';

    el('snippet-send-btn').fire('click');

    assert.deepStrictEqual(chats(), [{ type: 'sendChat', message: 'look at this', codeSnippet: 'int x;' }]);
    assert.strictEqual(el('chat-input').value, '');
});

test('Enter in the message box still sends', () => {
    const { el, chats } = loadSidebar();
    el('chat-input').value = 'hello';

    el('chat-input').fire('keydown', keydown('Enter'));

    assert.deepStrictEqual(chats(), [{ type: 'sendChat', message: 'hello', codeSnippet: null }]);
});

test('Enter in an empty snippet box sends nothing', () => {
    const { el, chats } = loadSidebar();

    el('chat-snippet-input').fire('keydown', keydown('Enter'));

    assert.strictEqual(chats().length, 0);
});
