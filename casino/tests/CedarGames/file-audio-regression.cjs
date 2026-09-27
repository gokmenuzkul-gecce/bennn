const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');

const root = path.resolve(__dirname, '../../..');
const nodes = new Map();
const createNode = id => ({
    id,
    children: [],
    dataset: {},
    listeners: {},
    classList: {add() {}, remove() {}, toggle() {}},
    style: {setProperty() {}},
    addEventListener(type, listener) { this.listeners[type] = listener; },
    appendChild(child) { this.children.push(child); },
    setAttribute(name, value) { this[name] = String(value); },
    getAttribute(name) { return name === 'href' ? this.href || '' : null; },
    set textContent(value) { this._textContent = value; },
    get textContent() { return this._textContent || ''; },
});
const node = id => {
    if (!nodes.has(id)) nodes.set(id, createNode(id));
    return nodes.get(id);
};

const controls = node('controls');
const findChild = (parent, id) => {
    for (const child of parent.children || []) {
        if (child.id === id) return child;
        const nested = findChild(child, id);
        if (nested) return nested;
    }
    return null;
};
const bootstrapScript = {getAttribute(name) { return name === 'data-game' ? 'Cedarcules' : null; }};
const document = {
    hidden: false,
    head: {appendChild() {}},
    getElementById(id) { return findChild(controls, id) || node(id); },
    querySelector(selector) {
        if (selector === '.cedar-controls') return controls;
        return selector.includes('promex-html-game.js') ? bootstrapScript : null;
    },
    createElement(tag) { return Object.assign(createNode('created-' + tag + '-' + nodes.size), {tagName: tag}); },
    addEventListener() {},
};

const audioInstances = [];
class AudioMock {
    constructor(src) { this.src = src; this.currentTime = 0; this.paused = true; this.playCount = 0; this.pauseCount = 0; audioInstances.push(this); }
    play() { this.paused = false; this.playCount++; return Promise.resolve(); }
    pause() { this.paused = true; this.pauseCount++; }
}

const storage = new Map();
const window = new EventTarget();
window.window = window;
window.document = document;
window.Audio = AudioMock;
window.localStorage = {getItem: key => storage.has(key) ? storage.get(key) : null, setItem: (key, value) => storage.set(key, String(value))};
const files = Object.fromEntries([
    'reel_start', 'reel_loop', 'reel_stop_1', 'reel_stop_2', 'reel_stop_3', 'result_no_win',
    'win_small', 'win_medium', 'win_large', 'wild_land', 'button_click', 'fast_toggle', 'signature_cue', 'ambient_loop',
].map(name => [name, `/CedarGames/Cedarcules/audio/${name.replaceAll('_', '-')}.ogg`]));
const config = {
    title: 'Cedarcules', balance: '100.00', min_bet: 1, max_bet: 1, bet_steps: [1], client_seed: 'client',
    layout: {columns: 5, rows: 1}, math: {rows: 1, reel_weights: [{1: 1}, {1: 1}, {1: 1}, {1: 1}, {1: 1}], symbols: {1: 'Wild'}, wild: 1, published_rtp: 92},
    brand: {name: 'CEDAR', loader_css: '', theme_css: '', game_theme_css: ''}, assets: {},
    audio: {mode: 'files', files, win_tiers: {medium_multiplier: 5, large_multiplier: 20}},
};
let nextWin = '0.00';
window.PromexHtmlGame = {
    game: 'Cedarcules',
    request(action) {
        if (action === 'init') return Promise.resolve(config);
        return Promise.resolve({grid: [1, 1, 1, 1, 1], line_wins: [], win_amount: nextWin, balance: '99.00', next_server_seed_hash: 'next', client_seed: 'client', nonce: 1});
    },
};

const context = vm.createContext({
    window, document, Audio: AudioMock, Event, EventTarget, console, Promise,
    setTimeout(callback) { return setImmediate(callback); }, clearTimeout: clearImmediate,
    setInterval() { return 1; }, clearInterval() {},
});
new vm.Script(fs.readFileSync(path.join(root, 'CedarGames/_runtime/cedar-slot.js'), 'utf8'), {filename: 'cedar-slot.js'}).runInContext(context);

setTimeout(async () => {
    try {
        assert.equal(audioInstances.length, 14, 'all mapped files should be prepared');
        assert.equal(controls.children.length, 1, 'audio controls should be added');
        assert.equal(controls.children[0].children[0].textContent, 'SFX ON');
        assert.equal(controls.children[0].children[1].textContent, 'MUSIC OFF', 'ambient music should be opt-in initially');
        node('cedar-wager').value = 1;
        await node('cedar-spin').listeners.click();
        await new Promise(resolve => setImmediate(resolve));
        const byEvent = event => audioInstances.find(audio => audio.src === files[event]);
        assert.equal(byEvent('reel_start').playCount, 1);
        assert.equal(byEvent('reel_loop').playCount, 1);
        assert.ok(byEvent('reel_loop').pauseCount >= 1, 'reel loop should stop after the final landing');
        assert.equal(byEvent('reel_stop_1').playCount, 2);
        assert.equal(byEvent('reel_stop_2').playCount, 2);
        assert.equal(byEvent('reel_stop_3').playCount, 1);
        assert.equal(byEvent('wild_land').playCount, 1, 'Wild cue should play once when one or more Wilds land');
        assert.equal(byEvent('result_no_win').playCount, 1, 'exactly one neutral result cue should play');
        assert.equal(byEvent('ambient_loop').playCount, 0, 'ambient should not autoplay');
        for (const value of ['1.00', '5.00', '20.00']) {
            nextWin = value;
            await node('cedar-spin').listeners.click();
        }
        assert.equal(byEvent('win_small').playCount, 1);
        assert.equal(byEvent('win_medium').playCount, 1);
        assert.equal(byEvent('win_large').playCount, 1);
        controls.children[0].children[1].listeners.click();
        assert.equal(byEvent('ambient_loop').playCount, 1, 'music control should start ambient audio');
        assert.equal(storage.get('cedar-music-muted'), '0');
        controls.children[0].children[0].listeners.click();
        assert.equal(storage.get('cedar-effects-muted'), '1');
        console.log('PASS: file audio sequencing, win tiers, reel-stop rotation, and persistent controls.');
    } catch (error) {
        console.error(error.stack || error);
        process.exitCode = 1;
    }
}, 10);
