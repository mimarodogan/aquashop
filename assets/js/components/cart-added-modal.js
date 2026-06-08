/**
 * Sepete Eklendi Mini Modal
 * ────────────────────────
 * Sepete ekleme formlarını yakalar, AJAX ile /ajax/cart-add.php'ye gönderir,
 * cevap geldiğinde sayfa değiştirmeden modal gösterir.
 *
 * Etkilenir:
 *   • Ürün kartlarındaki .icon-btn formları
 *   • PDP'deki #add-form
 *
 * Hata durumunda (varyasyon zorunlu, stok yetersiz vb.) form'un normal submit'i çalışır.
 */
(function () {
    'use strict';

    var modal = document.getElementById('cart-added-modal');
    if (!modal) return; // settings'te kapalıysa modal yok

    var qsel = function (s, p) { return (p || modal).querySelector(s); };
    var cnt  = qsel('.cam-count');
    var tot  = qsel('.cam-total');
    var name = qsel('.cam-name');
    var img  = qsel('.cam-img');
    var qty  = qsel('.cam-qty');
    var price= qsel('.cam-price');
    var link = qsel('.cam-cart-link');

    function open(data) {
        if (!data || !data.product) return;
        name.textContent  = data.product.name || '';
        qty.textContent   = (data.product.qty || 1) + ' adet';
        price.textContent = formatPrice(data.product.price);
        if (data.product.image) {
            img.src = data.product.image;
            img.style.display = '';
        } else {
            img.style.display = 'none';
        }
        if (data.cart) {
            cnt.textContent = data.cart.count;
            tot.textContent = data.cart.total_fmt || (data.cart.total + '₺');
            if (data.cart.url) link.href = data.cart.url;
        }
        modal.classList.add('open');
        modal.setAttribute('aria-hidden', 'false');
        // Sepet sayısını navbar'da da güncelle
        updateNavCartCount(data.cart.count);
        // ESC + outside click
        document.addEventListener('keydown', escClose);
    }

    function close() {
        modal.classList.remove('open');
        modal.setAttribute('aria-hidden', 'true');
        document.removeEventListener('keydown', escClose);
    }

    function escClose(e) { if (e.key === 'Escape') close(); }

    function formatPrice(v) {
        var n = parseFloat(v);
        if (isNaN(n)) return '';
        return new Intl.NumberFormat('tr-TR', { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(n) + ' ₺';
    }

    function updateNavCartCount(c) {
        var cnts = document.querySelectorAll('.cart-pill .cnt');
        // İlk pill (favori), ikinci pill (sepet) — sepet genelde sonuncu
        // Daha güvenilir: tüm cart-pill'lerin içindeki .cnt'leri güncellemiyoruz, sadece "Sepetim" başlığını taşıyanı
        document.querySelectorAll('.cart-pill').forEach(function (p) {
            if ((p.getAttribute('title') || '').toLowerCase().indexOf('sepet') >= 0) {
                var span = p.querySelector('.cnt');
                if (!span) {
                    span = document.createElement('span');
                    span.className = 'cnt';
                    p.appendChild(span);
                }
                span.textContent = c;
            }
        });
    }

    // Modal kapatma butonları
    modal.querySelectorAll('[data-cam-close]').forEach(function (b) {
        b.addEventListener('click', close);
    });
    modal.addEventListener('click', function (e) {
        if (e.target === modal) close();
    });

    // AJAX intercept — sepete ekle formları
    // PDP form: id="add-form" ya method=post; ürün karttaki form: <a href> ile post yok, modal sadece PDP için
    // Ürün karttaki "icon-btn"ler sadece linkler olduğu için modal'lar PDP'de tetiklenir
    var pdpForm = document.getElementById('add-form');
    if (pdpForm) {
        pdpForm.addEventListener('submit', function (e) {
            var qtyInput = pdpForm.querySelector('input[name=qty]');
            var varInput = pdpForm.querySelector('input[name=variant_id]');
            var pidInput = pdpForm.querySelector('input[name=product_id]');
            // PDP'de product_id form'da olmayabilir — URL'den çıkarmak zor. Fallback: form'a meta veri ekle
            // ya da sadece varsa AJAX yap, yoksa default submit
            var pid = pidInput ? pidInput.value : (pdpForm.getAttribute('data-product-id') || '');
            if (!pid) return; // normal submit
            e.preventDefault();
            var fd = new FormData();
            fd.append('csrf', pdpForm.querySelector('input[name=csrf]').value);
            fd.append('product_id', pid);
            fd.append('qty', qtyInput ? qtyInput.value : 1);
            if (varInput && varInput.value) fd.append('variant_id', varInput.value);
            var addBtn = pdpForm.querySelector('button[type=submit]');
            if (addBtn) { addBtn.disabled = true; addBtn.dataset.origText = addBtn.textContent; addBtn.textContent = 'Ekleniyor...'; }
            fetch((window.__SITE_URL || '') + '/ajax/cart-add.php', {
                method: 'POST', body: fd, credentials: 'same-origin'
            }).then(function (r) { return r.json().catch(function () { return null; }); })
              .then(function (data) {
                  if (data && data.ok) {
                      open(data);
                      if (addBtn) { addBtn.disabled = false; addBtn.textContent = '✓ Eklendi'; setTimeout(function(){ addBtn.textContent = addBtn.dataset.origText || 'Sepete Ekle →'; }, 1500); }
                  } else {
                      // Hata → normal submit ile devam
                      pdpForm.submit();
                  }
              }).catch(function () { pdpForm.submit(); });
        });
    }
})();
