<script nonce="{{ csrf_token() }}">
(function () {
    'use strict';
    var overlay = document.querySelector('.welcome-reveal');
    var creases = overlay.querySelector('.reveal-creases');
    var contact = overlay.querySelector('.reveal-grip-shadow');
    var hand = overlay.querySelector('.reveal-hand');
    var shell = document.querySelector('.page-shell');
    var skip = overlay.querySelector('.reveal-skip');
    var motion = window.matchMedia('(prefers-reduced-motion: reduce)');
    var frame, active = false, previousFocus, started, offset, gripInset, lastFrame;
    var duration = 3800;
    var cloth = {stiffness: 60, coupling: 28, damping: 24, internalDamping: 7};
    var clothContext = creases.getContext('2d');
    var gridWidth = 61, gridHeight = 45;
    var heights = new Float32Array(gridWidth * gridHeight);
    var velocities = new Float32Array(heights.length);
    var restShape = new Float32Array(heights.length);
    var pixels = clothContext ? clothContext.createImageData(360, 220) : null;
    // A shallow surface patch: smooth grab falloff, curved ridges and damped local motion.
    for (var row = 0; row < gridHeight; row++) {
        for (var column = 0; column < gridWidth; column++) {
            var x = column / (gridWidth - 1) * 360 - 180;
            var y = row / (gridHeight - 1) * 220 - 35;
            var radius = Math.hypot(x * .9, y * 1.12);
            var angle = Math.atan2(y, x);
            var falloff = Math.exp(-radius * radius / 9000);
            var ridges = Math.sin(angle * 5.3 + radius * .022 + .65) + .36 * Math.sin(angle * 8.1 - radius * .019);
            restShape[row * gridWidth + column] = falloff * (22 * Math.exp(-radius * radius / 600) + 8 * ridges * (1 - Math.exp(-radius / 17)));
        }
    }
    function drawCloth(tension, dt) {
        if (!clothContext) { return; }
        var steps = Math.max(1, Math.ceil(dt / (1 / 120)));
        var step = dt / steps;
        for (var iteration = 0; iteration < steps; iteration++) {
            for (var y = 1; y < gridHeight - 1; y++) {
                for (var x = 1; x < gridWidth - 1; x++) {
                    var index = y * gridWidth + x;
                    var neighbours = heights[index - 1] + heights[index + 1] + heights[index - gridWidth] + heights[index + gridWidth] - 4 * heights[index];
                    var relativeVelocity = velocities[index - 1] + velocities[index + 1] + velocities[index - gridWidth] + velocities[index + gridWidth] - 4 * velocities[index];
                    velocities[index] += (cloth.stiffness * (restShape[index] * tension - heights[index]) + cloth.coupling * neighbours - cloth.damping * velocities[index] + cloth.internalDamping * relativeVelocity) * step;
                }
            }
            for (var index = 0; index < heights.length; index++) { heights[index] += velocities[index] * step; }
        }
        for (var py = 0; py < 220; py++) {
            var gy = py / 219 * (gridHeight - 4) + 1;
            var iy = Math.floor(gy), fy = gy - iy;
            for (var px = 0; px < 360; px++) {
                var gx = px / 359 * (gridWidth - 4) + 1;
                var ix = Math.floor(gx), fx = gx - ix;
                var index = iy * gridWidth + ix;
                var next = index + gridWidth;
                var dx = (((heights[index + 1] - heights[index - 1]) * (1 - fx) + (heights[index + 2] - heights[index]) * fx) * (1 - fy) + ((heights[next + 1] - heights[next - 1]) * (1 - fx) + (heights[next + 2] - heights[next]) * fx) * fy) / 12;
                var dy = (((heights[next] - heights[index - gridWidth]) * (1 - fx) + (heights[next + 1] - heights[index - gridWidth + 1]) * fx) * (1 - fy) + ((heights[next + gridWidth] - heights[index]) * (1 - fx) + (heights[next + gridWidth + 1] - heights[index + 1]) * fx) * fy) / 10;
                var lighting = (-dx * .55 - dy * .7) / Math.sqrt(1 + dx * dx + dy * dy);
                var fade = Math.max(0, 1 - Math.pow(Math.hypot((px - 180) / 180, (py - 35) / 190), 2));
                var pixel = (py * 360 + px) * 4;
                var value = lighting > 0 ? 255 : 74;
                pixels.data[pixel] = value; pixels.data[pixel + 1] = value; pixels.data[pixel + 2] = value;
                pixels.data[pixel + 3] = Math.min(110, Math.abs(lighting) * 430) * fade;
            }
        }
        clothContext.putImageData(pixels, 0, 0);
    }
    function ease(value) { return value < .5 ? 4 * value * value * value : 1 - Math.pow(-2 * value + 2, 3) / 2; }
    function clamp(value) { return Math.max(0, Math.min(1, value)); }
    function finish() {
        if (!active) { return; }
        active = false;
        cancelAnimationFrame(frame);
        overlay.hidden = true;
        shell.inert = false;
        shell.style.removeProperty('transform');
        shell.style.removeProperty('border-radius');
        shell.style.removeProperty('transform-origin');
        document.body.classList.remove('welcome-intro-playing');
        if (overlay.contains(document.activeElement)) {
            (previousFocus && previousFocus !== document.body ? previousFocus : document.getElementById('username')).focus({preventScroll: true});
        }
    }
    function render(now) {
        if (!active) { return; }
        var time = now - started;
        var dt = Math.min((now - lastFrame) / 1000, .04);
        lastFrame = now;
        var enter = ease(clamp(time / 850));
        var grip = ease(clamp((time - 800) / 400));
        var lift = ease(clamp((time - 1200) / 1650));
        var release = ease(clamp((time - 2800) / 600));
        var position = offset * (1 - lift);
        var tension = grip * (1 - release);
        var stretch = tension * .028 * Math.sin(lift * Math.PI);
        var contactY = position + gripInset * (1 + stretch);
        shell.style.transform = 'translate3d(0,' + position + 'px,0) scaleY(' + (1 + stretch) + ')';
        shell.style.borderRadius = (16 * (1 - lift)) + 'px ' + (16 * (1 - lift)) + 'px 0 0';
        hand.style.transform = 'translate3d(' + (-grip * 2 + release * 8) + 'px,' + (contactY - (1 - enter) * 240 - release * 210) + 'px,0) rotate(' + (-3 + grip * 4 - release * 5) + 'deg) scale(' + (1 - grip * .025) + ')';
        contact.style.transform = 'translate3d(0,' + contactY + 'px,0) scale(' + (.8 + grip * .2) + ')';
        contact.style.opacity = tension * .65;
        creases.style.transform = 'translate3d(0,' + contactY + 'px,0)';
        creases.style.opacity = 1 - ease(clamp((time - 3400) / 400));
        drawCloth(tension * (1 + .35 * Math.sin(lift * Math.PI)), dt);
        if (time >= duration) { finish(); return; }
        frame = requestAnimationFrame(render);
    }
    function play() {
        if (active || motion.matches || typeof shell.inert === 'undefined') { return; }
        offset = Math.min(136, Math.max(90, window.innerHeight * .15));
        shell.style.transformOrigin = '50% 0';
        gripInset = window.innerWidth <= 760 ? 30 : 38;
        window.scrollTo({top: 0, behavior: 'instant'});
        previousFocus = document.activeElement;
        active = true;
        overlay.hidden = false;
        shell.inert = true;
        document.body.classList.add('welcome-intro-playing');
        heights.fill(0);
        velocities.fill(0);
        started = performance.now();
        lastFrame = started;
        render(started);
    }
    skip.addEventListener('click', finish);
    document.addEventListener('keydown', function (event) {
        if (!active) { return; }
        if (event.key === 'Escape') { finish(); }
        if (event.key === 'Tab') { event.preventDefault(); skip.focus(); }
    });
    window.addEventListener('resize', finish);
    window.addEventListener('pagehide', finish);
    document.addEventListener('visibilitychange', function () { if (document.hidden) { finish(); } });
    motion.addEventListener('change', function () { if (motion.matches) { finish(); } });
    var arrivalPlayed = false;
    var image = hand.querySelector('img');
    function ready() {
        if (!image.naturalWidth) { return; }
        if (arrivalPlayed || document.hidden) { return; }
        arrivalPlayed = true;
        if (!document.querySelector('.role-error')) { play(); }
    }
    document.addEventListener('visibilitychange', function () {
        if (!document.hidden && image.complete && !arrivalPlayed) { ready(); }
    });
    if (image.complete) { ready(); } else { image.addEventListener('load', ready, {once: true}); }
})();
</script>
