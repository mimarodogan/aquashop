/* Ürün detay — canlı (ornek-site.test) tasarım etkileşimleri:
   galeri thumb değiştirme, adet kontrol, varyasyon seçimi. */
(function () {
  'use strict';

  var main = document.getElementById('aqProductMainImage');

  /* Galeri: thumb tıklayınca ana görseli değiştir */
  var thumbs = document.querySelectorAll('.aq-product-thumbs button');
  thumbs.forEach(function (btn) {
    btn.addEventListener('click', function () {
      var url = btn.getAttribute('data-media-url');
      if (url && main) { main.src = url; main.style.display = ''; }
      thumbs.forEach(function (b) { b.classList.remove('is-active'); });
      btn.classList.add('is-active');
    });
  });

  /* Adet kontrol */
  var qty = document.getElementById('aqProductQty');
  function clampQty() {
    if (!qty) return;
    var min = parseInt(qty.min, 10) || 1;
    var max = parseInt(qty.max, 10) || 9999;
    var v = parseInt(qty.value, 10) || min;
    qty.value = Math.max(min, Math.min(max, v));
  }
  var minus = document.querySelector('.aq-qty-minus');
  var plus = document.querySelector('.aq-qty-plus');
  if (minus && qty) minus.addEventListener('click', function () { qty.value = (parseInt(qty.value, 10) || 1) - 1; clampQty(); });
  if (plus && qty) plus.addEventListener('click', function () { qty.value = (parseInt(qty.value, 10) || 1) + 1; clampQty(); });
  if (qty) qty.addEventListener('input', clampQty);

  /* Para formatı (TRY) — money() ile aynı: 1.600,00 ₺ */
  function fmt(n) {
    n = parseFloat(n) || 0;
    return n.toLocaleString('tr-TR', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + ' ₺';
  }

  /* Varyasyon seçimi → fiyat / eski fiyat / indirim / stok / görsel güncelle */
  var chips = document.querySelectorAll('.aq-variation-chip');
  if (chips.length) {
    var priceEl = document.getElementById('aqProductPrice');
    var oldEl = document.getElementById('aqProductOldPrice');
    var discEl = document.getElementById('aqProductDiscount');
    var stockEl = document.getElementById('aqProductStock');
    var variantInput = document.getElementById('aqVariantId');
    chips.forEach(function (chip) {
      chip.addEventListener('click', function () {
        if (chip.disabled) return;
        chips.forEach(function (c) { c.classList.remove('is-active'); });
        chip.classList.add('is-active');
        var price = parseFloat(chip.getAttribute('data-price')) || 0;
        var old = parseFloat(chip.getAttribute('data-old')) || 0;
        var stock = parseInt(chip.getAttribute('data-stock'), 10) || 0;
        var img = chip.getAttribute('data-image');
        if (priceEl) priceEl.textContent = fmt(price);
        if (oldEl) {
          if (old > price) { oldEl.textContent = fmt(old); oldEl.style.display = ''; }
          else { oldEl.style.display = 'none'; }
        }
        if (discEl) {
          if (old > price) {
            var pct = Math.max(0, Math.round((1 - price / old) * 100));
            discEl.querySelector('strong').textContent = '%' + pct;
            discEl.style.display = '';
          } else { discEl.style.display = 'none'; }
        }
        if (stockEl) stockEl.textContent = stock + ' adet';
        if (variantInput) variantInput.value = chip.getAttribute('data-variant');
        if (img && main) { main.src = img; main.style.display = ''; }
        if (qty) { qty.max = Math.max(1, stock); clampQty(); }
      });
    });
  }
})();
