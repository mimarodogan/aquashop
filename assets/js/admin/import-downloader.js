(function () {
    'use strict';
    var btn = document.getElementById('start-dl');
    if (!btn) return;

    var status  = document.getElementById('dl-status');
    var pending = document.getElementById('q-pending');
    var done    = document.getElementById('q-done');
    var failed  = document.getElementById('q-failed');
    var csrf    = btn.getAttribute('data-csrf') || '';
    var running = false;

    // Canlı sayaçlar için ana/galeri kırılımı
    var mainDoneEl    = document.querySelector('[data-main-done]');
    var galDoneEl     = document.querySelector('[data-gal-done]');
    var mainFailedEl  = document.querySelector('[data-main-failed]');
    var galFailedEl   = document.querySelector('[data-gal-failed]');

    function tick() {
        var fd = new FormData();
        fd.append('csrf', csrf);
        fetch('?ajax=download_batch', { method: 'POST', body: fd, credentials: 'same-origin' })
            .then(function (r) { return r.json(); })
            .then(function (j) {
                if (!j.ok) {
                    status.textContent = 'Hata: ' + (j.error || 'bilinmeyen');
                    running = false;
                    btn.disabled = false;
                    btn.textContent = 'Tekrar Dene';
                    return;
                }

                // Genel sayaçlar
                if (done)    done.textContent    = (parseInt(done.textContent,    10) || 0) + j.done;
                if (failed)  failed.textContent  = (parseInt(failed.textContent,  10) || 0) + j.failed;
                if (pending) pending.textContent = j.remaining;

                // Kırılım sayaçları
                if (mainDoneEl && j.done_main !== undefined)
                    mainDoneEl.textContent = (parseInt(mainDoneEl.textContent, 10) || 0) + j.done_main;
                if (galDoneEl && j.done_gallery !== undefined)
                    galDoneEl.textContent  = (parseInt(galDoneEl.textContent,  10) || 0) + j.done_gallery;

                var mainTxt = j.done_main    ? j.done_main    + ' ana'    : '';
                var galTxt  = j.done_gallery ? j.done_gallery + ' galeri' : '';
                var breakdown = [mainTxt, galTxt].filter(Boolean).join(', ');
                status.textContent = 'Bu turda: ' + j.done + ' indirildi'
                    + (breakdown ? ' (' + breakdown + ')' : '')
                    + (j.failed ? ', ' + j.failed + ' hata' : '')
                    + '. Kalan: ' + j.remaining;

                if (j.remaining > 0 && running) {
                    setTimeout(tick, 200);
                } else if (j.remaining === 0) {
                    status.textContent = '✅ Tüm görseller işlendi. Sayfayı yenileyerek hata durumunu kontrol edebilirsiniz.';
                    btn.disabled = false;
                    btn.textContent = 'Yeniden Çalıştır';
                    running = false;
                }
            })
            .catch(function (e) {
                status.textContent = 'Ağ hatası: ' + e;
                running = false;
                btn.disabled = false;
            });
    }

    btn.addEventListener('click', function () {
        if (running) return;
        running = true;
        btn.disabled = true;
        btn.textContent = 'İndiriliyor…';
        status.textContent = 'Başlatılıyor…';
        tick();
    });
})();
