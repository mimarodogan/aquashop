(function () {
    'use strict';
    var btn = document.querySelector('[data-to-top]');
    if (!btn) return;

    var threshold = 300;
    var ticking = false;

    function update() {
        if (window.scrollY > threshold) btn.classList.add('show');
        else btn.classList.remove('show');
        ticking = false;
    }

    window.addEventListener('scroll', function () {
        if (!ticking) {
            window.requestAnimationFrame(update);
            ticking = true;
        }
    }, { passive: true });

    btn.addEventListener('click', function () {
        var reduce = matchMedia('(prefers-reduced-motion:reduce)').matches;
        window.scrollTo({ top: 0, behavior: reduce ? 'auto' : 'smooth' });
        btn.blur();
    });

    update();
})();
