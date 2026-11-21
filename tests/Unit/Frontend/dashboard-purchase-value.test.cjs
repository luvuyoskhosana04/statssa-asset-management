const test = require('node:test');
const assert = require('node:assert/strict');
const {buildLedger} = require('../../../public/js/dashboard-purchase-value.js');
test('monthly records aggregate without changing the total or priced asset count', () => {
    const result = buildLedger([{currency:'ZAR',period:'2026-10-01',amount:100,priced:2},{currency:'ZAR',period:'2026-10-01',amount:20,priced:1}], 'ZAR');
    assert.equal(result.total,120); assert.equal(result.priced,3); assert.equal(result.average,40);
    assert.deepEqual(result.entries,[{period:'2026-10-01',amount:120,priced:3}]);
});
test('currencies stay separate without conversion', () => {
    const rows=[{currency:'ZAR',period:'2026-10-01',amount:10,priced:1},{currency:'USD',period:'2026-10-01',amount:100,priced:2}];
    assert.equal(buildLedger(rows,'ZAR').total,10); assert.equal(buildLedger(rows,'USD').total,100);
});
test('undated costs reconcile to the total and remain outside the dated ledger', () => {
    const result=buildLedger([{currency:'ZAR',period:'2026-10-01',amount:10,priced:1},{currency:'ZAR',period:null,amount:90,priced:2}], 'ZAR');
    assert.equal(result.total,100); assert.equal(result.priced,3); assert.deepEqual(result.undated,{amount:90,priced:2});
    assert.equal(result.entries.reduce((sum,row)=>sum+row.amount,0)+result.undated.amount,result.total);
});
test('invalid purchase dates never become fabricated months', () => {
    const result=buildLedger([{currency:'ZAR',period:'2026-13-01',amount:10,priced:1},{currency:'ZAR',period:'0000-00-01',amount:20,priced:1}], 'ZAR');
    assert.deepEqual(result.entries,[]); assert.equal(result.undated.amount,30);
});
test('zero cost is retained as a real priced record', () => {
    const result=buildLedger([{currency:'ZAR',period:'2026-10-01',amount:0,priced:1}], 'ZAR');
    assert.equal(result.priced,1); assert.equal(result.average,0); assert.equal(result.entries.length,1);
});
test('chronology spans years without mutating the supplied records', () => {
    const rows=[{currency:'ZAR',period:'2025-12-01',amount:20,priced:1},{currency:'ZAR',period:'2026-01-01',amount:10,priced:1}];
    const before=JSON.stringify(rows); const result=buildLedger(rows,'ZAR');
    assert.deepEqual(result.entries.map(row=>row.period),['2026-01-01','2025-12-01']); assert.equal(JSON.stringify(rows),before);
});
test('empty inventory has no amounts, averages or invented acquisition entries', () => {
    assert.deepEqual(buildLedger([],'ZAR'),{total:0,priced:0,average:0,undated:{amount:0,priced:0},entries:[]});
});
