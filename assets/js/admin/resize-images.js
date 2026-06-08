(function () {
    'use strict';

    var btn = document.getElementById('rz-start');
    if (!btn) return;

    var progress = document.getElementById('rz-progress');
    var bar      = document.getElementById('rz-bar');
    var countEl  = document.getElementById('rz-count');
    var savedEl  = document.getElementById('rz-saved');
    var logEl    = document.getElementById('rz-log');

    var csrf  = btn.getAttribute('data-csrf');
    var total = parseInt(btn.getAttribute('data-total'), 10) || 0;

    function fmtBytes(b) {
        if (b > 1024 * 1024) return (b / 1024 / 1024).toFixed(2) + ' MB';
        return Math.round(b / 1024) + ' KB';
    }

    function appendLog(lines, type) {
        if (!lines || !lines.length) return;
        var color = type === 'err' ? '#a02020' : '#2a5e1a';
        lines.forEach(function (l) {
            var div = document.createElement('div');
            div.style.color = color;
            div.textContent = (type === 'err' ? '✗ ' : '✓ ') + l;
            logEl.appendChild(div);
        });
        logEl.scrollTop = logEl.scrollHeight;
    }

    btn.addEventListener('click', function () {
        if (!confirm(total + ' resim küçültülecek. Devam edilsin mi?')) return;
        btn.disabled = true;
        btn.textContent = 'İşleniyor...';
        progress.style.display = 'block';

        var processed = 0;
        var totalSaved = 0;

        function nextBatch(offset) {
            var fd = new FormData();
            fd.append('csrf', csrf);
            fd.append('offset', offset);

            fetch('?ajax=batch', {
                method: 'POST',
                body: fd,
                credentials: 'same-origin'
            })
                .then(function (r) { return r.json(); })
                .then(function (j) {
                    if (!j.ok) {
                        appendLog(['Hata: ' + (j.error || 'bilinmeyen')], 'err');
                        btn.disabled = false;
                        btn.textContent = 'Tekrar Dene';
                        return;
                    }
                    processed += j.done;
                    totalSaved += j.saved;
                    countEl.textContent = processed;
                    savedEl.textContent = 'Tasarruf: ' + fmtBytes(totalSaved);
                    bar.style.width = (processed / total * 100) + '%';
                    appendLog(j.details);
                    appendLog(j.errors, 'err');

                    if (j.remaining > 0) {
                        // Hemen sıradaki batch'e geç
                        setTimeout(function () { nextBatch(j.next_offset); }, 100);
                    } else {
                        btn.textContent = '✅ Tamamlandı — Toplam ' + fmtBytes(totalSaved) + ' tasarruf';
                        btn.classList.remove('btn-primary');
                        btn.classList.add('btn-secondary');
                        if (window.toast) {
                            window.toast.success('Resim optimizasyonu tamamlandı! ' + fmtBytes(totalSaved) + ' tasarruf edildi.');
                        }
                    }
                })
                .catch(function (e) {
                    appendLog(['Ağ hatası: ' + e.message], 'err');
                    btn.disabled = false;
                    btn.textContent = 'Tekrar Dene';
                });
        }

        nextBatch(0);
    });
})();
