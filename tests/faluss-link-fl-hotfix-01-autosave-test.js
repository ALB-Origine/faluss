'use strict';

const fs = require('fs');
const vm = require('vm');
const path = require('path');

function assert(condition, message) {
    if (!condition) { throw new Error(message); }
}

const root = path.resolve(__dirname, '..');
let source = fs.readFileSync(path.join(root, 'plugins/faluss-link/assets/js/faluss-link-editor.js'), 'utf8');
source = source.replace(/}\(jQuery\)\);\s*$/, "window.__falussHotfixTest = { enqueueStudioMutation: enqueueStudioMutation, hydrateCanonicalBlocks: hydrateCanonicalBlocks };}(jQuery));");

function makeChain(length) {
    const values = Object.create(null);
    let proxy;
    const target = { length: length || 0 };
    proxy = new Proxy(target, {
        get(object, property) {
            if (property in object) { return object[property]; }
            if (property === 'val' || property === 'text' || property === 'html') {
                return function (value) { if (arguments.length) { values[property] = value; return proxy; } return values[property] || ''; };
            }
            if (property === 'attr' || property === 'data' || property === 'prop') {
                return function (key, value) { if (arguments.length > 1) { values[property + ':' + key] = value; return proxy; } return values[property + ':' + key] || (property === 'prop' ? false : ''); };
            }
            if (property === 'each') { return function () { return proxy; }; }
            if (property === 'map') { return function () { return { get: function () { return []; } }; }; }
            if (property === 'find' || property === 'filter' || property === 'first' || property === 'children' || property === 'next' || property === 'closest' || property === 'siblings') { return function () { return makeChain(0); }; }
            return function () { return proxy; };
        }
    });
    return proxy;
}

class FakeStudio {
    constructor(version) {
        this.store = Object.create(null);
        this.attributes = { 'data-faluss-studio-version': version };
        this.version = version;
        this.collection = '';
        this.form = makeChain(1);
        this.form.attr = (name) => name === 'action' ? 'https://faluss.test/wp-admin/admin-post.php' : '';
        this.form.find = (selector) => {
            if (selector.indexOf('faluss_link_studio_nonce') !== -1) { const field = makeChain(1); field.val = () => 'nonce'; return field; }
            return makeChain(0);
        };
    }
    data(key, value) { if (arguments.length > 1) { this.store[key] = value; return this; } return this.store[key]; }
    removeData(key) { delete this.store[key]; return this; }
    addClass() { return this; }
    removeClass() { return this; }
    attr(key, value) { if (arguments.length > 1) { this.attributes[key] = value; if (key === 'data-faluss-studio-version') { this.version = value; } return this; } return this.attributes[key] || ''; }
    find(selector) {
        if (selector === '.faluss-link-studio__form') { return this.form; }
        if (selector === '[data-fl-aggregate-version]') { const field = makeChain(1), owner = this; field.val = function (value) { if (arguments.length) { owner.version = value; return field; } return owner.version; }; return field; }
        if (selector === '[data-fl-active-collection]') { const field = makeChain(1), owner = this; field.val = function (value) { if (arguments.length) { owner.collection = value; return field; } return owner.collection; }; return field; }
        return makeChain(selector === '.faluss-link-studio__notice' ? 1 : 0);
    }
}

const sent = [];
const pendingFetches = [];
const fakeWindow = {
    setTimeout: function () { return 1; },
    clearTimeout: function () {},
    requestAnimationFrame: function (callback) { callback(); },
    matchMedia: function () { return { matches: false }; },
    addEventListener: function () {},
    fetch: function (url, options) {
        sent.push(Object.fromEntries(options.body.entries()));
        return new Promise(function (resolve) { pendingFetches.push(resolve); });
    }
};
const fakeDocument = {};
function jquery(value) {
    if (typeof value === 'function') { return makeChain(0); }
    return makeChain(value && typeof value === 'string' && value.charAt(0) === '<' ? 1 : 0);
}
jquery.trim = function (value) { return String(value || '').trim(); };

const context = {
    window: fakeWindow,
    document: fakeDocument,
    jQuery: jquery,
    URL: URL,
    FormData: FormData,
    Promise: Promise,
    Object: Object,
    Array: Array,
    String: String,
    JSON: JSON,
    Error: Error,
    console: console,
    requestAnimationFrame: fakeWindow.requestAnimationFrame,
    FileReader: function () {}
};
vm.runInNewContext(source, context, { filename: 'faluss-link-editor.js' });
const enqueue = fakeWindow.__falussHotfixTest.enqueueStudioMutation;
const hydrate = fakeWindow.__falussHotfixTest.hydrateCanonicalBlocks;
const tick = () => new Promise((resolve) => setImmediate(resolve));
const response = (ok, code, version) => ({
    ok: ok,
    json: () => Promise.resolve({ success: ok, data: { code: code, message: ok ? 'saved' : 'conflict', state: { blocks: [], version: version, profile: {}, preferences: {}, links_html: '', collections_html: '', collection_html: '', preview_html: '' } } })
});

(async function () {
    const studio = new FakeStudio('a'.repeat(64));
    const first = enqueue(studio, 'save_appearance', { page_background: '#111111' }, { key: 'appearance' });
    assert(sent.length === 1, 'The first mutation must start immediately.');
    const second = enqueue(studio, 'save_appearance', { page_background: '#222222' }, { key: 'appearance' });
    const third = enqueue(studio, 'save_appearance', { hero_transition_color: '#333333' }, { key: 'appearance' });
    assert(sent.length === 1, 'A running mutation must serialize later changes.');
    pendingFetches.shift()(response(true, 'saved', 'b'.repeat(64)));
    await tick(); await tick(); await tick();
    assert(sent.length === 2, 'A consolidated pending mutation must start immediately after the running save.');
    assert(sent[1].page_background === '#222222' && sent[1].hero_transition_color === '#333333', 'Changes made during autosave must be consolidated, not dropped.');
    assert(sent[1].aggregate_version === 'b'.repeat(64), 'The pending mutation must use the server-returned canonical version.');
    pendingFetches.shift()(response(true, 'saved', 'c'.repeat(64)));
    assert(await first && await second && await third, 'Every consolidated caller must resolve after persistence.');
    assert(sent.length === 2, 'Consolidation must avoid duplicate saves.');

    const conflictStudio = new FakeStudio('d'.repeat(64));
    const stale = enqueue(conflictStudio, 'save_header', { available: '1' }, { key: 'header' });
    const postConflictChange = enqueue(conflictStudio, 'save_header', { available: '0' }, { key: 'header' });
    pendingFetches.shift()(response(false, 'stale_version', 'e'.repeat(64)));
    await tick(); await tick(); await tick();
    assert(sent.length === 4, 'A user change made during a stale request must remain queued after canonical rehydration.');
    assert(sent[3].available === '0' && sent[3].aggregate_version === 'e'.repeat(64), 'The post-conflict pending change must run against the new canonical version.');
    pendingFetches.shift()(response(true, 'saved', 'f'.repeat(64)));
    assert(!(await stale) && await postConflictChange, 'The stale mutation must be refused while the later pending change is persisted.');

    const collectionId = '11111111-2222-4333-8444-555555555555';
    hydrate(conflictStudio, { blocks: [], active_collection: collectionId, version: 'f'.repeat(64), links_html: '', collections_html: '', collection_html: '', preview_html: '' });
    assert(conflictStudio.collection === collectionId && conflictStudio.attr('data-faluss-studio-collection') === collectionId, 'Canonical hydration must restore the active collection as well as its panels.');

    process.stdout.write('FL-HOTFIX-01 autosave queue: OK\n');
})().catch(function (error) { process.stderr.write('FAIL: ' + error.message + '\n'); process.exit(1); });
