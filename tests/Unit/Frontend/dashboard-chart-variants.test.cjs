const test = require('node:test');
const assert = require('node:assert/strict');
const {composition, formatShare} = require('../../../public/js/dashboard-chart-variants.js');
test('category composition retains every category, count and exact total', () => {
    const input = [{label:'Laptops',count:2252},{label:'Tablets',count:41},{label:'Other',count:0}];
    const result = composition(input,false);
    assert.equal(result.total,2293); assert.equal(result.rows.length,3);
    assert.equal(result.rows.find(row => row.label === 'Laptops').value,2252);
    assert.equal(result.rows.find(row => row.label === 'Other').share,0);
    assert.ok(Math.abs(result.rows.reduce((sum,row) => sum+row.share,0)-1)<1e-12);
});
test('financial composition preserves exact supplied values without currency conversion', () => {
    const result = composition([{label:'A',amount:2606461.44},{label:'B',amount:789},{label:'C',amount:2}],true);
    assert.equal(result.total,2607252.44);
    assert.deepEqual(result.rows.map(row=>row.value),[2606461.44,789,2]);
});
test('sorting is deterministic and does not mutate the input records', () => {
    const source=[{label:'Z',count:10},{label:'A',count:10},{label:'B',count:20}];
    const before=JSON.stringify(source); const result=composition(source,false);
    assert.deepEqual(result.rows.map(row=>row.label),['B','A','Z']); assert.equal(JSON.stringify(source),before);
});
test('empty and zero-valued inventories never create invalid proportions', () => {
    assert.deepEqual(composition([],false),{total:0,rows:[]});
    assert.equal(composition([{label:'Empty',count:0}],false).rows[0].share,0);
});
test('negative financial values are retained for the numeric fallback', () => {
    const result=composition([{label:'Adjustment',amount:-10},{label:'Purchase',amount:20}],true);
    assert.deepEqual(result.rows.map(row=>row.value),[20,-10]); assert.equal(result.total,10);
});

test('small nonzero costs are never labelled as zero and near totals stay below 100 percent', () => {
    assert.equal(formatShare(2/2607252.44,true,'en'),'<0.01%');
    assert.equal(formatShare(789/2607252.44,true,'en'),'0.03%');
    assert.equal(formatShare(2606461.44/2607252.44,true,'en'),'99.97%');
    assert.equal(formatShare(0.999999,true,'en'),'>99.99%');
    assert.equal(formatShare(1,true,'en'),'100%');
});
