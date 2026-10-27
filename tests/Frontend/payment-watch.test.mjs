import { test } from 'node:test';
import assert from 'node:assert/strict';
import { initPaymentWatch } from '../../resources/js/payment-watch.js';

function fixture({issued = true, status = 'pending', code = 200} = {}) {
    const timers = new Map();
    const handlers = {};
    const notice = {textContent: ''};
    const calls = [];
    let nextTimer = 0;
    let reloads = 0;
    const document = {
        hidden: false,
        querySelector(selector) {
            return selector === '[data-payment-watch]'
                ? {dataset: {syncUrl: '/orders/9/sync', paymentIssued: String(issued)}} : notice;
        },
        addEventListener(name, handler) { handlers[name] = handler; },
    };
    const window = {
        location: {reload() { reloads++; }},
        addEventListener(name, handler) { handlers[name] = handler; },
    };
    const fetch = async (url, options) => {
        calls.push({url, options});
        return {status: code, ok: code < 400, headers: {get: () => '30'}, json: async () => ({status})};
    };
    const watcher = initPaymentWatch({document, window, navigator: {onLine: true}, fetch,
        csrf: () => 'test-csrf-token',
        setTimeout(fn, delay) { const id = ++nextTimer; timers.set(id, {fn, delay}); return id; },
        clearTimeout(id) { timers.delete(id); },
    });
    return {watcher, timers, handlers, notice, document, calls, get reloads() { return reloads; },
        async tick() { const [id, timer] = timers.entries().next().value; timers.delete(id); await timer.fn(); },
    };
}

test('confirmed payment reloads automatically without a manual status click', async () => {
    const f = fixture({status: 'paid'});
    await f.tick();
    assert.equal(f.calls.length, 1);
    assert.equal(f.calls[0].url, '/orders/9/sync');
    assert.equal(f.calls[0].options.method, 'POST');
    assert.equal(f.calls[0].options.headers['X-CSRF-TOKEN'], 'test-csrf-token');
    assert.equal(f.reloads, 1);
    assert.equal(f.timers.size, 0);
});

test('pending payment keeps polling every ten seconds', async () => {
    const f = fixture();
    await f.tick();
    assert.equal(f.reloads, 0);
    assert.equal([...f.timers.values()][0].delay, 10000);
    await f.tick();
    assert.equal(f.calls.length, 2);
});

test('no transaction means no polling until Snap is opened', () => {
    const f = fixture({issued: false});
    assert.equal(f.timers.size, 0);
    f.watcher.start();
    assert.equal(f.timers.size, 1);
});

test('hidden tabs pause requests and returning to the tab checks immediately', async () => {
    const f = fixture();
    f.document.hidden = true;
    await f.tick();
    assert.equal(f.calls.length, 0);
    f.document.hidden = false;
    f.handlers.visibilitychange();
    await Promise.resolve();
    assert.equal(f.calls.length, 1);
});

test('expired authentication stops automatic requests', async () => {
    const f = fixture({code: 419});
    await f.tick();
    assert.equal(f.timers.size, 0);
    assert.match(f.notice.textContent, /Sesi berakhir/);
});

test('service failures retry without declaring the order paid', async () => {
    const f = fixture({code: 503});
    await f.tick();
    assert.equal(f.reloads, 0);
    assert.equal([...f.timers.values()][0].delay, 15000);
});

test('rate limits respect Retry-After instead of flooding the endpoint', async () => {
    const f = fixture({code: 429});
    await f.tick();
    assert.equal([...f.timers.values()][0].delay, 30000);
});

test('leaving the page clears future polling', () => {
    const f = fixture();
    f.handlers.pagehide();
    assert.equal(f.timers.size, 0);
});

test('returning with the browser back button resumes checking', async () => {
    const f = fixture({status: 'paid'});
    f.handlers.pagehide();
    f.handlers.pageshow({persisted: true});
    await f.tick();
    assert.equal(f.reloads, 1);
    assert.equal(f.calls[0].options.signal.aborted, false);
});
