const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');

const root = path.resolve(__dirname, '../../..');
const requests = [];
const nodes = new Map();
const node = id => {
    if (!nodes.has(id)) nodes.set(id, {
        id,
        children: [],
        classList: {add() {}, toggle() {}},
        style: {setProperty() {}},
        addEventListener() {},
        appendChild(child) { this.children.push(child); },
        setAttribute(name, value) { this[name] = String(value); },
        getAttribute(name) { return name === 'href' ? this.href || '' : null; },
        set textContent(value) { this._textContent = value; },
        get textContent() { return this._textContent || ''; },
    });
    return nodes.get(id);
};

const window = new EventTarget();
window.window = window;
window.parent = window;
window.location = {search: '', hash: '', href: 'https://operator.example/game/Cedarcules'};
window.CustomEvent = class CustomEvent extends Event {
    constructor(type, options) { super(type); this.detail = options && options.detail; }
};
window.PromexGameSession = {runtimeGame: 'Cedarcules', ready: Promise.resolve()};
window.CedarFairness = {verify() { return true; }};
window.fetch = async (url, options) => {
    requests.push({url, options});
    return new Promise(() => {});
};

const bootstrapScript = {
    src: '/js/promex-html-game.js?v=8',
    getAttribute(name) { return name === 'data-game' ? 'Cedarcules' : null; },
};
const document = {
    currentScript: null,
    head: {appendChild() {}},
    documentElement: {appendChild() {}},
    getElementById: node,
    querySelector(selector) { return selector.includes('promex-html-game.js') ? bootstrapScript : null; },
    createElement(tag) { return Object.assign(node('created-' + tag + '-' + nodes.size), {tagName: tag}); },
};
window.document = document;

const context = vm.createContext({
    window,
    document,
    CustomEvent: window.CustomEvent,
    Event,
    EventTarget,
    console,
    setTimeout(callback, delay, ...args) {
        const timer = setTimeout(callback, delay, ...args);
        timer.unref();
        return timer;
    },
    clearTimeout,
    setInterval,
    clearInterval,
    Promise,
    URL,
    URLSearchParams,
});

const runtime = fs.readFileSync(path.join(root, 'CedarGames/_runtime/cedar-slot.js'), 'utf8');
const loader = fs.readFileSync(path.join(root, 'js/promex-html-game.js'), 'utf8');

// Exercise the race explicitly: the slot consumer starts before the transport producer.
new vm.Script(runtime, {filename: 'cedar-slot.js'}).runInContext(context);
document.currentScript = bootstrapScript;
new vm.Script(loader, {filename: 'promex-html-game.js'}).runInContext(context);

setTimeout(() => {
    try {
        assert.equal(window.PromexHtmlGame.version, 2, 'runtime version should be bumped');
        assert.equal(requests.length, 1, 'boot should dispatch one initialization request');
        assert.equal(requests[0].url, '/game/Cedarcules/server');
        assert.equal(requests[0].options.method, 'POST');
        assert.deepEqual(JSON.parse(requests[0].options.body), {action: 'init'});
        console.log('PASS: Cedarcules readiness dispatches the HTTP init request.');
    } catch (error) {
        console.error(error.stack || error);
        process.exitCode = 1;
    }
}, 25);
