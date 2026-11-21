(function () {
    'use strict';
    function isScanner(device) {
        return (device.collections || []).some(function (collection) { return collection.usagePage === 0x8c; });
    }
    function createMonitor(hid, notify) {
        var device = null, problem = hid ? 'checking' : 'unsupported', version = 0;
        function publish() { notify({device: device, problem: problem}); }
        async function refresh() {
            if (!hid) { publish(); return; }
            var current = ++version;
            try {
                var devices = (await hid.getDevices()).filter(isScanner);
                if (current !== version) return;
                if (!devices.length && problem === 'disconnected') { publish(); return; }
                device = devices.find(function (candidate) { return candidate === device; }) || devices[0] || null;
                problem = device ? null : 'unidentified'; publish();
            } catch (error) { if (current === version) { device = null; problem = 'blocked'; publish(); } }
        }
        async function identify() {
            if (!hid) return;
            try {
                var devices = await hid.requestDevice({filters: [{usagePage: 0x8c}]});
                if (!devices.length) return;
                var scanner = devices.find(isScanner);
                if (scanner) { version++; device = scanner; problem = null; publish(); }
            } catch (error) {
                if (error.name === 'NotFoundError') return;
                version++; device = null; problem = 'blocked'; publish();
            }
        }
        if (hid) {
            hid.addEventListener('connect', function (event) {
                if (!isScanner(event.device)) return;
                version++; device = event.device; problem = null; publish();
            });
            hid.addEventListener('disconnect', function (event) {
                if (event.device !== device) return;
                version++; problem = 'disconnected'; publish();
            });
        }
        return {refresh: refresh, identify: identify};
    }
    function statusFor(problem, focused, inputReceived) {
        if (problem === 'disconnected' && !inputReceived) return 'error';
        return focused ? 'ready' : 'paused';
    }
    if (typeof module !== 'undefined' && module.exports) { module.exports = {createMonitor: createMonitor, isScanner: isScanner, statusFor: statusFor}; return; }
    var root = document.getElementById('receiving-workspace');
    if (!root || !root.classList.contains('barcode-intake')) return;
    var copy = JSON.parse(root.dataset.scannerCopy), badge = document.getElementById('receive-scanner-status');
    var help = document.getElementById('receive-scanner-status-help'), identify = document.getElementById('receive-identify');
    var input = document.getElementById('receive-barcode'), resume = document.getElementById('receive-resume');
    var state = {device: null, problem: 'checking'}, inputReceived = false;
    function render() {
        var focused = document.activeElement === input;
        var name = state.device && !state.problem && (state.device.productName || copy.generic_name);
        var status = statusFor(state.problem, focused, inputReceived);
        badge.dataset.state = status;
        badge.textContent = status === 'error' ? copy.disconnected.replace(':name', state.device && state.device.productName || copy.generic_name)
            : name ? (status === 'ready' ? copy.ready : copy.paused).replace(':name', name)
            : status === 'ready' ? copy.input_ready : copy.input_paused;
        help.hidden = status === 'ready';
        help.textContent = status === 'error' ? copy.disconnect_help : copy.pause_help;
        identify.hidden = !navigator.hid || focused;
        resume.hidden = focused;
        root.classList.toggle('is-scanning', focused);
    }
    var monitor = createMonitor(navigator.hid, function (value) {
        if (value.problem === 'disconnected' && state.problem !== 'disconnected') inputReceived = false;
        state = value; render();
    });
    input.addEventListener('input', function () { inputReceived = true; render(); });
    identify.addEventListener('click', async function () {
        identify.disabled = true;
        await monitor.identify(); identify.disabled = false; input.focus(); render();
    });
    input.addEventListener('focus', render); input.addEventListener('blur', render);
    window.addEventListener('focus', function () { monitor.refresh(); });
    document.addEventListener('visibilitychange', function () { if (!document.hidden) monitor.refresh(); });
    render(); monitor.refresh();
})();
