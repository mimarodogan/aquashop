(function () {
    'use strict';

    function ready(fn) {
        if (document.readyState !== 'loading') { fn(); }
        else { document.addEventListener('DOMContentLoaded', fn); }
    }

    // Sıralama & Filtre dialog'ları
    function initDialogs() {
        function openDlg(el, btn) {
            if (!el) return;
            el.classList.add('show');
            if (btn) btn.setAttribute('aria-expanded', 'true');
            document.body.style.overflow = 'hidden';
        }
        function closeDlg(el, btn) {
            if (!el) return;
            el.classList.remove('show');
            if (btn) btn.setAttribute('aria-expanded', 'false');
            document.body.style.overflow = '';
        }

        var sp = document.getElementById('sort-pop');
        var fd = document.getElementById('filter-drawer');
        var bSort = document.getElementById('btn-sort');
        var bFilt = document.getElementById('btn-filter');

        if (bSort) bSort.addEventListener('click', function (e) { e.preventDefault(); openDlg(sp, bSort); });
        if (bFilt) bFilt.addEventListener('click', function (e) { e.preventDefault(); openDlg(fd, bFilt); });

        document.querySelectorAll('[data-close="sort"]').forEach(function (b) {
            b.addEventListener('click', function () { closeDlg(sp, bSort); });
        });
        document.querySelectorAll('[data-close="filter"]').forEach(function (b) {
            b.addEventListener('click', function () { closeDlg(fd, bFilt); });
        });
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') {
                if (sp && sp.classList.contains('show')) closeDlg(sp, bSort);
                if (fd && fd.classList.contains('show')) closeDlg(fd, bFilt);
            }
        });
    }

    ready(initDialogs);
})();
