const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
function setup(reduce = false) {
    class Element {
        constructor() { this.children = []; this.attributes = {}; this.isConnected = true; this.classList = {add() {}, remove() {}}; }
        set textContent(value) {
            this.textWrites = (this.textWrites || 0) + 1;
            this.text = String(value); this.children = [];
            const element = this;
            this.firstChild = {get nodeValue() { return element.text; }, set nodeValue(value) { element.text = String(value); }};
        }
        get textContent() { return this.children.length ? this.children.map(child => child.textContent).join('') : this.text || ''; }
        replaceChildren(...children) { this.children = children; }
        setAttribute(name, value) { this.attributes[name] = value; }
    }
    const frames = new Map(), events = {}, media = {matches: reduce, addEventListener(name, callback) { this.change = callback; }};
    let now = 0, id = 0, visibility;
    class Observer {
        constructor(callback) { visibility = callback; }
        observe() {}
        unobserve() {}
    }
    const window = {IntersectionObserver: Observer, matchMedia: () => media, addEventListener: (name, callback) => { events[name] = callback; }};
    vm.runInNewContext(fs.readFileSync(require.resolve('../../../public/js/dashboard-numbers.js'), 'utf8'), {
        window, IntersectionObserver: Observer, document: {createElement: () => new Element(), addEventListener() {}},
        performance: {now: () => now}, requestAnimationFrame: callback => { frames.set(++id, callback); return id; }, cancelAnimationFrame: key => frames.delete(key),
    });
    return {element: () => new Element(), set: window.StatsDashboardNumbers.set, count: window.StatsDashboardNumbers.count,
        reveal(element, ratio = 1) { visibility([{target: element, isIntersecting: ratio > 0, intersectionRatio: ratio}]); },
        advance(milliseconds) { now += milliseconds; const pending = [...frames.values()]; frames.clear(); pending.forEach(callback => callback(now)); },
        reduce() { media.matches = true; media.change({matches: true}); }, print: () => events.beforeprint()};
}
const numbers = new Intl.NumberFormat('en-US');
const money = value => 'ZAR ' + new Intl.NumberFormat('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2}).format(value);
test('counts wait for meaningful visibility and keep the exact figure accessible during motion', () => {
    const env = setup(), element = env.element(); env.set(element, 2574, numbers.format);
    assert.equal(element.children[0].textContent, '0');
    assert.equal(element.children[0].attributes['aria-hidden'], 'true');
    assert.equal(element.children[2].textContent, '2,574');
    env.reveal(element, .1); env.advance(1200); assert.equal(element.children[0].textContent, '0');
    env.reveal(element); env.advance(1200);
    const shown = Number(element.children[0].textContent.replaceAll(',', ''));
    assert.ok(shown > 0 && shown < 2574);
    env.advance(1400); assert.equal(element.textContent, '2,574'); assert.equal(element.children.length, 0);
});
test('currency counters finish with the original cents and preserve negative adjustments', () => {
    for (const value of [2607252.44, -789.01]) {
        const env = setup(), element = env.element(); env.set(element, value, money); env.reveal(element); env.advance(2600);
        assert.equal(element.textContent, money(value));
    }
});
test('a new currency or value replaces an unfinished count without an old frame overwriting it', () => {
    const env = setup(), element = env.element(); env.set(element, 2000, money); env.reveal(element); env.advance(400);
    env.set(element, 45.67, value => 'USD ' + value.toFixed(2)); env.reveal(element); env.advance(2600);
    assert.equal(element.textContent, 'USD 45.67');
});
test('reduced motion shows exact figures immediately and finishes running and pending counters', () => {
    const reduced = setup(true), element = reduced.element(); reduced.set(element, 99.8, value => value + '%');
    assert.equal(element.textContent, '99.8%'); assert.equal(element.children.length, 0);
    const env = setup(), running = env.element(), pending = env.element();
    env.set(running, 2574, numbers.format); env.set(pending, 2607252.44, money); env.reveal(running); env.advance(300); env.reduce();
    assert.equal(running.textContent, '2,574'); assert.equal(pending.textContent, 'ZAR 2,607,252.44');
});
test('printing completes offscreen values and zero never needs an animation', () => {
    const env = setup(), element = env.element(), zero = env.element();
    env.set(element, 18, numbers.format); env.set(zero, 0, numbers.format); env.print();
    assert.equal(element.textContent, '18'); assert.equal(zero.textContent, '0'); assert.equal(zero.children.length, 0);
});

test('every frame stays within the actual target and moves monotonically, including decimals and negatives', () => {
    const {frameValue} = require('../../../public/js/dashboard-numbers.js');
    for (const target of [3, 18, 2574, 15.9, 0.998, 2607252.44, -789.01]) {
        let previous = 0;
        for (let frame = 0; frame <= 180; frame++) {
            const current = frameValue(target, frame / 180);
            assert.ok(current >= Math.min(0, target) && current <= Math.max(0, target));
            assert.ok(target >= 0 ? current >= previous : current <= previous);
            previous = current;
        }
        assert.equal(previous, target);
    }
});
test('receiving the same value during a count does not reset or recreate its text', () => {
    const env = setup(), element = env.element(); env.set(element, 2574, numbers.format); env.reveal(element); env.advance(1000);
    const visual = element.children[0], current = visual.textContent;
    env.set(element, 2574, numbers.format);
    assert.equal(element.children[0], visual); assert.equal(visual.textContent, current);
    env.advance(1600); assert.equal(element.textContent, '2,574');
});

test('animation updates the existing text node without rebuilding the DOM every frame', () => {
    const env = setup(), element = env.element(); env.set(element, 2574, numbers.format); env.reveal(element);
    const visual = element.children[0], textNode = visual.firstChild;
    for (let frame = 0; frame < 10; frame++) env.advance(100);
    assert.equal(visual.firstChild, textNode); assert.equal(visual.textWrites, 1);
    assert.ok(Number(visual.textContent.replaceAll(',', '')) > 0);
});

test('rendered asset and alert counts never show decimal places or exceed the final text width', () => {
    for (const target of [3, 18, 11, 2574, 2252]) {
        const env = setup(), element = env.element(); env.count(element, target, numbers.format); env.reveal(element);
        for (let frame = 0; frame < 155; frame++) {
            env.advance(16);
            const shown = element.children[0].textContent;
            assert.match(shown, /^[0-9,]+$/);
            assert.ok(shown.length <= numbers.format(target).length);
            assert.ok(Number(shown.replaceAll(',', '')) <= target);
        }
        env.advance(200); assert.equal(element.textContent, numbers.format(target));
    }
});
test('percentages and money keep their own precision instead of being rounded as asset counts', () => {
    const env = setup(), percent = env.element(), amount = env.element();
    const formatPercent = new Intl.NumberFormat('en-US', {style: 'percent', maximumFractionDigits: 1}).format;
    env.set(percent, 1, formatPercent); env.set(amount, 789.01, money); env.reveal(percent); env.reveal(amount); env.advance(1300);
    assert.equal(percent.children[0].textContent, '50%');
    assert.equal(amount.children[0].textContent, 'ZAR 394.51');
    env.advance(1300); assert.equal(percent.textContent, '100%'); assert.equal(amount.textContent, 'ZAR 789.01');
});
