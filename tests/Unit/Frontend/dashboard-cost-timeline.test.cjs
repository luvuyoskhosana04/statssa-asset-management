const {test} = require('node:test');
const assert = require('node:assert/strict');
const {buildBuckets} = require('../../../public/js/dashboard-cost-timeline.js');

test('month series stays chronological across a year boundary and keeps zero months', () => {
    const result = buildBuckets([
        {currency:'ZAR',period:'2025-11-01',amount:100,priced:1},
        {currency:'ZAR',period:'2026-01-01',amount:250,priced:2},
    ], 'ZAR');
    assert.equal(result.step, 1);
    assert.deepEqual(result.buckets.map(row => row.amount), [100,0,250]);
    assert.deepEqual(result.buckets.map(row => row.priced), [1,0,2]);
});
test('currencies retain a shared time axis while amounts and undated values stay separate', () => {
    const result = buildBuckets([
        {currency:'EUR',period:'2026-01-01',amount:999,priced:1},
        {currency:'ZAR',period:'2026-02-01',amount:100,priced:2},
        {currency:'ZAR',period:null,amount:45,priced:1},
        {currency:'EUR',period:null,amount:1000,priced:2},
    ], 'ZAR');
    assert.deepEqual(result.buckets.map(row => row.amount), [0,100]);
    assert.deepEqual(result.undated, {amount:45,priced:1});
});
test('undated-only cost produces no invented date series', () => {
    const result = buildBuckets([{currency:'ZAR',period:null,amount:50,priced:3}], 'ZAR');
    assert.deepEqual(result.buckets, []);
    assert.deepEqual(result.undated, {amount:50,priced:3});
});
test('long histories aggregate to bounded periods without losing value or asset counts', () => {
    const rows = [{currency:'ZAR',period:'2000-01-01',amount:123,priced:1},{currency:'ZAR',period:'2026-11-01',amount:456,priced:2}];
    const result = buildBuckets(rows, 'ZAR');
    assert.ok(result.buckets.length <= 25);
    assert.equal(result.buckets.reduce((sum,row)=>sum+row.amount,0),579);
    assert.equal(result.buckets.reduce((sum,row)=>sum+row.priced,0),3);
});
test('zero cost remains a dated record rather than a missing value', () => {
    const result = buildBuckets([{currency:'ZAR',period:'2026-02-01',amount:0,priced:1}], 'ZAR');
    assert.equal(result.buckets.length,1);
    assert.equal(result.buckets[0].amount,0);
    assert.equal(result.buckets[0].priced,1);
});
