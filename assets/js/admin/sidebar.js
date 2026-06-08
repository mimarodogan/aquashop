(function () {
    'use strict';
    var b = document.body;
    document.querySelectorAll('[data-sb-open]').forEach(function (el) {
        el.addEventListener('click', function () { b.classList.add('sb-open'); });
    });
    document.querySelectorAll('[data-sb-close]').forEach(function (el) {
        el.addEventListener('click', function () { b.classList.remove('sb-open'); });
    });
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') b.classList.remove('sb-open');
    });
})();
