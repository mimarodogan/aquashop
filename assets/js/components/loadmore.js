(function () {
    'use strict';

    function initWrap(wrap) {
        var btnId = wrap.getAttribute('data-button-id');
        var gridSel = wrap.getAttribute('data-grid-selector');
        var gridId = wrap.getAttribute('data-grid-id');
        var ajaxUrl = wrap.getAttribute('data-ajax-url') || '';
        var btn = btnId ? document.getElementById(btnId) : wrap.querySelector('button[data-loadmore-btn]');
        if (!btn) return;
        var grid = gridId
            ? document.getElementById(gridId)
            : (gridSel ? document.querySelector(gridSel) : null);
        if (!grid) return;

        var loading = false;
        btn.addEventListener('click', function () {
            if (loading) return;
            loading = true;
            var origText = btn.textContent;
            btn.disabled = true;
            btn.textContent = 'Yükleniyor…';
            var off = parseInt(wrap.getAttribute('data-next-offset'), 10) || 0;
            var base = wrap.getAttribute('data-base-query') || '';
            var url = ajaxUrl + '?ajax=page&offset=' + off + (base ? '&' + base : '');

            fetch(url, { credentials: 'same-origin' })
                .then(function (r) { return r.text(); })
                .then(function (html) {
                    var temp = document.createElement('div');
                    temp.innerHTML = html;
                    var marker = temp.querySelector('[data-has-more]');
                    var hasMore = '0', nextOff = off;
                    if (marker) {
                        hasMore = marker.getAttribute('data-has-more');
                        nextOff = parseInt(marker.getAttribute('data-next-offset'), 10) || off;
                        marker.remove();
                    }
                    var cards = temp.querySelectorAll('.card');
                    cards.forEach(function (c) { grid.appendChild(c); });
                    var shown = document.querySelector('.lm-shown');
                    if (shown) shown.textContent = (parseInt(shown.textContent, 10) || 0) + cards.length;
                    wrap.setAttribute('data-next-offset', String(nextOff));
                    if (hasMore !== '1') {
                        btn.remove();
                    } else {
                        btn.disabled = false;
                        btn.textContent = origText;
                    }
                    loading = false;
                })
                .catch(function () {
                    btn.disabled = false;
                    btn.textContent = origText;
                    loading = false;
                    alert('Yükleme başarısız oldu. Tekrar deneyin.');
                });
        });
    }

    function initAll() {
        document.querySelectorAll('.loadmore-wrap[data-ajax-url]').forEach(initWrap);
    }

    if (document.readyState !== 'loading') initAll();
    else document.addEventListener('DOMContentLoaded', initAll);
})();
