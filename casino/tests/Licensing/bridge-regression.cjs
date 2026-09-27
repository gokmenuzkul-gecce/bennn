const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');
const {webcrypto} = require('node:crypto');

const root = path.resolve(__dirname, '../../..');
const helper = fs.readFileSync(path.join(root, 'js/game-session.js'), 'utf8');
const bridge = fs.readFileSync(path.join(root, 'js/promex-legacy-bridge.js'), 'utf8');
const pause = () => new Promise(resolve => setTimeout(resolve, 80));

function browserContext(pathname = '/game/AuditGame', initialize = true) {
    const requests = [], xhrs = [];
    class XHR {
        open(method, url) { this.method = method; this.url = url; this.headers = {}; }
        setRequestHeader(name, value) { this.headers[name] = value; }
        send(body) { this.body = body; xhrs.push(this); }
    }
    const window = {
        crypto: webcrypto, URL, Request, Headers, ArrayBuffer, Uint8Array, Int8Array, DataView,
        TextEncoder, TextDecoder, Promise, Math, Date, JSON, setTimeout, clearTimeout,
        XMLHttpRequest: XHR, sessionStorage: {removeItem() {}, set sessionValue12(_) {}},
        location: {hostname: 'audit.invalid', host: 'audit.invalid', origin: 'https://audit.invalid',
            pathname, href: 'https://audit.invalid' + pathname},
        console: {log() {}, warn() {}, error() {}},
    };
    window.window = window;
    window.parent = window;
    window.fetch = async (input, options) => {
        requests.push({url: input instanceof Request ? input.url : String(input), options});
        return {ok: true, status: 200, text: async () => '{}', json: async () => ({responseHex: '0102ff'})};
    };
    const script = {getAttribute(name) { return ({
        'data-csrf': 'test-session-csrf', 'data-runtime-game': 'AuditGame'
    })[name] || null; }};
    window.document = {
        currentScript: script, cookie: '',
        querySelector() { return null; },
        createElement() { return {}; }, head: {appendChild() {}}, documentElement: {appendChild() {}},
    };
    const context = vm.createContext(window);
    if (initialize) {
        vm.runInContext(helper, context, {filename: 'game-session.js'});
        vm.runInContext(bridge, context, {filename: 'promex-legacy-bridge.js'});
    }
    return {context, requests, xhrs};
}

(async () => {
    const {context, requests, xhrs} = browserContext();
    await context.window.PromexGameSession.ready;
    assert.equal(context.window.PromexGameSession.version, 3);

    await context.window.fetch('/game/AuditGame/server', {method: 'POST', body: '{"action":"init"}'});
    const direct = requests.at(-1);
    assert.equal(direct.options.headers.get('X-CSRF-TOKEN'), 'test-session-csrf');
    assert.match(direct.options.headers.get('X-Promex-Request'), /^[a-f0-9]{32}$/);
    assert.match(direct.options.headers.get('X-Promex-Time'), /^[0-9]{10}$/);
    assert.equal(direct.options.headers.has('X-Promex-Proof'), false);
    assert.equal(direct.options.headers.has('X-Promex-Protocol'), false);

    await context.window.fetch('https://external.invalid/game/AuditGame/server', {method: 'POST', body: '{}'});
    assert.equal(requests.at(-1).options.headers, undefined, 'session headers never leave the operator origin');

    const xhr = new context.window.XMLHttpRequest();
    xhr.open('POST', '/game/AuditGame/server');
    xhr.send('{}');
    assert.equal(xhrs[0].headers['X-CSRF-TOKEN'], 'test-session-csrf');
    assert.match(xhrs[0].headers['X-Promex-Request'], /^[a-f0-9]{32}$/);

    const socket = new context.window.WebSocket('wss://audit.invalid/slots-socket');
    await pause();
    socket.send(JSON.stringify({gameName: 'AuditGame', command: 'login', label: 'é 🎲'}));
    await pause();
    const socketRequest = requests.at(-1);
    assert.equal(JSON.parse(socketRequest.options.body).label, 'é 🎲');
    assert.equal(socketRequest.options.headers.get('X-CSRF-TOKEN'), 'test-session-csrf');

    const framed = browserContext('/games/AuditGame/index.html', false);
    framed.context.window.parent = context.window;
    vm.runInContext(bridge, framed.context, {filename: 'promex-legacy-bridge-child.js'});
    const childSocket = new framed.context.window.WebSocket('wss://audit.invalid/slots-socket');
    await pause();
    childSocket.send(JSON.stringify({gameName: 'AuditGame', command: 'login'}));
    await pause();
    assert.equal(framed.requests.at(-1).options.headers.get('X-CSRF-TOKEN'), 'test-session-csrf',
        'same-origin iframe inherits ordinary parent transport');

    const binaryMessages = [];
    const binary = new context.window.WebSocket('wss://audit.invalid/slots-socket');
    binary.onmessage = event => binaryMessages.push(event.data);
    await pause();
    binary.send(new TextEncoder().encode(':::{"gameName":"AuditGame","sessionId":"binary-session"}'));
    await pause();
    assert.equal(binaryMessages.some(message => message instanceof ArrayBuffer), true,
        'binary handshake returns an ArrayBuffer acknowledgement');

    const requestCount = requests.length;
    binary.send(new Uint8Array([1, 2, 3, 4, 5, 6, 37]));
    await pause();
    assert.equal(requests.length, requestCount + 1, 'binary packet reaches the Laravel game server');
    const binaryRequest = requests.at(-1);
    assert.equal(binaryRequest.options.headers.get('X-CSRF-TOKEN'), 'test-session-csrf');
    assert.match(binaryRequest.options.headers.get('X-Promex-Request'), /^[a-f0-9]{32}$/);
    assert.equal(binaryMessages.at(-1) instanceof ArrayBuffer, true,
        'binary server response remains an ArrayBuffer');

    assert.equal(/WebAssembly|\.wasm|X-Promex-Proof/.test(bridge + helper), false,
        'transport and compatibility adapter contain no licensing WASM path');
    console.log('PASS: ordinary CSRF transport, request identity, fetch/XHR, string and binary socket adapters, iframe inheritance, and no WASM.');
})().catch(error => { console.error(error.stack || error); process.exitCode = 1; });
