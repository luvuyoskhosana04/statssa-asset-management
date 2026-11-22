const test = require('node:test');
const assert = require('node:assert/strict');
const {preview} = require('../../../public/js/quantity-adjustment.js');
test('preview shows the resulting available stock for additions and removals', () => {
    assert.deepEqual(preview(11, '5'), {error:null, delta:5, after:16});
    assert.deepEqual(preview(11, '-3'), {error:null, delta:-3, after:8});
});
test('audit-only adjustment preserves stock and removing all available stock is valid', () => {
    assert.deepEqual(preview(11, '0'), {error:null, delta:0, after:11});
    assert.deepEqual(preview(11, '-11'), {error:null, delta:-11, after:0});
});
test('removing unavailable stock is rejected before saving', () => {
    assert.equal(preview(11, '-12').error, 'exceeds');
    assert.equal(preview(0, '-1').error, 'exceeds');
});
test('blank, fractional and non-numeric changes do not produce a misleading balance', () => {
    assert.deepEqual(preview(11, ''), {error:'empty', delta:null, after:null});
    for (const value of ['1.5', 'text', 'Infinity', '9007199254740992']) assert.equal(preview(11, value).error, 'invalid');
});
test('unknown availability stays unknown while retaining a valid signed adjustment', () => {
    assert.deepEqual(preview(null, '5'), {error:null, delta:5, after:null});
});
