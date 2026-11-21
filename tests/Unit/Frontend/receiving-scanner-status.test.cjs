const test = require('node:test');
const assert = require('node:assert/strict');
const {createMonitor, isScanner, statusFor} = require('../../../public/js/receiving-scanner-status.js');
const scanner = {productName: 'Test barcode scanner', collections: [{usagePage: 0x8c}]};
function hidStub(devices = []) {
    const handlers = {};
    return {getDevices: async () => devices, requestDevice: async () => [scanner],
        addEventListener: (type, handler) => { handlers[type] = handler; },
        emit: (type, device) => handlers[type]({device})};
}
test('previously authorised scanner retains its actual name', async () => {
    let state; const monitor = createMonitor(hidStub([scanner]), value => { state = value; });
    await monitor.refresh(); assert.equal(state.device.productName, 'Test barcode scanner'); assert.equal(state.problem, null);
});
test('ordinary keyboards and other devices are never presented as recognised scanners', async () => {
    let state; const keyboard = {productName: 'Keyboard', collections: [{usagePage: 1}]};
    assert.equal(isScanner(keyboard), false);
    await createMonitor(hidStub([keyboard]), value => { state = value; }).refresh();
    assert.equal(state.device, null); assert.equal(state.problem, 'unidentified');
});
test('disconnect and reconnect events update the recognised device', async () => {
    let state; const hid = hidStub([scanner]); const monitor = createMonitor(hid, value => { state = value; });
    await monitor.refresh(); hid.emit('disconnect', scanner); assert.equal(state.problem, 'disconnected');
    assert.equal(state.device.productName, scanner.productName);
    hid.emit('connect', scanner); assert.equal(state.problem, null);
});
test('unavailable detection and denied permissions have honest failure states', async () => {
    let state; await createMonitor(null, value => { state = value; }).refresh(); assert.equal(state.problem, 'unsupported');
    const hid = hidStub(); hid.getDevices = async () => { throw new Error('permission'); };
    await createMonitor(hid, value => { state = value; }).refresh(); assert.equal(state.problem, 'blocked');
});
test('the picker requests barcode devices only and selection is explicit', async () => {
    let state, filters; const hid = hidStub(); hid.requestDevice = async options => { filters = options; return [scanner]; };
    const monitor = createMonitor(hid, value => { state = value; }); await monitor.refresh();
    assert.equal(state.problem, 'unidentified'); await monitor.identify();
    assert.deepEqual(filters, {filters: [{usagePage: 0x8c}]}); assert.equal(state.device, scanner); assert.equal(state.problem, null);
});
test('cancelling the picker preserves the current connection status', async () => {
    let state; const hid = hidStub([scanner]); hid.requestDevice = async () => [];
    const monitor = createMonitor(hid, value => { state = value; }); await monitor.refresh(); await monitor.identify();
    assert.equal(state.device, scanner); assert.equal(state.problem, null);
});
test('a stale device lookup cannot override a later disconnect', async () => {
    let state, resolve; const hid = hidStub([scanner]);
    const monitor = createMonitor(hid, value => { state = value; }); await monitor.refresh();
    hid.getDevices = () => new Promise(done => { resolve = done; });
    const refresh = monitor.refresh(); hid.emit('disconnect', scanner); resolve([scanner]); await refresh;
    assert.equal(state.problem, 'disconnected');
});

test('working keyboard input is ready even when hardware identification is unavailable', () => {
    for (const problem of ['unidentified', 'unsupported', 'blocked', 'checking', null]) assert.equal(statusFor(problem, true, false), 'ready');
    assert.equal(statusFor('unidentified', false, false), 'paused');
});
test('only a confirmed disconnection is an error, and incoming scans restore readiness', () => {
    assert.equal(statusFor('disconnected', true, false), 'error');
    assert.equal(statusFor('disconnected', true, true), 'ready');
    assert.equal(statusFor(null, true, false), 'ready');
});
