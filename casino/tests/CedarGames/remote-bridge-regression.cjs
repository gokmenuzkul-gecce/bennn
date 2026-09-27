const assert = require('node:assert/strict');
const crypto = require('node:crypto').webcrypto;
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');

const root = path.resolve(__dirname, '../../..');
const posted = [];
const parent = {postMessage(message, origin) { posted.push({message, origin}); }};
const window = new EventTarget();
window.window = window;
window.parent = parent;
window.location = {
    search: '?promex_remote=1&operator_origin=https%3A%2F%2Foperator.example',
    hash: '#promex_launch=' + 't'.repeat(43),
};
window.crypto = crypto;
window.CedarFairness = {verify() { return true; }};
window.CustomEvent = class CustomEvent extends Event {
    constructor(type, options) { super(type); this.detail = options && options.detail; }
};
const script = {getAttribute(name) { return name === 'data-game' ? 'Cedarcules' : null; }};
const document = {
    currentScript: script,
    referrer: 'https://operator.example/game/Cedarcules',
    head: {appendChild() {}}, documentElement: {appendChild() {}},
    createElement() { return {}; },
};
window.document = document;

const context = vm.createContext({
    window, document, crypto, URL, URLSearchParams, Event, EventTarget,
    CustomEvent: window.CustomEvent, console, Promise, setTimeout, clearTimeout,
});
new vm.Script(fs.readFileSync(path.join(root, 'js/promex-html-game.js'), 'utf8'), {filename: 'promex-html-game.js'}).runInContext(context);

function reply(index, data) {
    const event = new Event('message');
    Object.defineProperties(event, {
        origin: {value: 'https://operator.example'},
        source: {value: parent},
        data: {value: {
            type: 'promex:game-response', request_id: posted[index].message.request_id,
            ok: true, status: 200, data,
        }},
    });
    window.dispatchEvent(event);
}

(async () => {
    await window.PromexHtmlGame.ready;
    assert.equal(window.PromexHtmlGame.delivery, 'PROMEX_REMOTE');
    const initPromise = window.PromexHtmlGame.request('init');
    await new Promise(resolve => setTimeout(resolve, 0));
    assert.equal(posted[0].origin, 'https://operator.example');
    assert.equal(posted[0].message.launch_token, 't'.repeat(43));
    reply(0, {status: 'success', server_seed_hash: 'a'.repeat(64), client_seed: 'client', nonce: 1});
    await initPromise;

    const spinPromise = window.PromexHtmlGame.request('spin', {wager: 2, client_seed: 'client'});
    await new Promise(resolve => setTimeout(resolve, 0));
    assert.match(posted[1].message.payload.request_id, /^[a-f0-9]{32}$/);
    assert.equal(posted[1].message.payload.server_seed_hash, 'a'.repeat(64));
    reply(1, {status: 'success', next_server_seed_hash: 'b'.repeat(64)});
    await spinPromise;
    console.log('PASS: Promex Remote bridge binds operator origin, launch token, commitment, and logical request ID.');
})().catch(error => {
    console.error(error.stack || error);
    process.exitCode = 1;
});
