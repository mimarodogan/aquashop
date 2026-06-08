(function () {
  'use strict';

  /* ── Filtre Drawer ─────────────────────────────────────────────
     Çalışma mantığı:
     - #btn-filter  → drawer'ı açar
     - #filter-drawer-close + overlay → kapatır
     - Escape tuşu  → kapatır
     - focus trap   → açıkken odak drawer içinde kalır
     ─────────────────────────────────────────────────────────── */

  var drawer  = document.getElementById('filter-drawer');
  var overlay = document.getElementById('filter-drawer-overlay');
  var closeBtn = document.getElementById('filter-drawer-close');
  var openBtn  = document.getElementById('btn-filter');

  if (!drawer || !openBtn) return; // kategori sayfası değilse çık

  /* ── Sort popup (mevcut kategori sayfası kodu ile uyumlu) ─── */
  var sortPop = document.getElementById('sort-pop');
  var sortBtn = document.getElementById('btn-sort');

  function openSort() {
    if (!sortPop) return;
    sortPop.classList.add('show');
    if (sortBtn) sortBtn.setAttribute('aria-expanded', 'true');
    document.addEventListener('keydown', onSortKey);
  }
  function closeSort() {
    if (!sortPop) return;
    sortPop.classList.remove('show');
    if (sortBtn) sortBtn.setAttribute('aria-expanded', 'false');
    document.removeEventListener('keydown', onSortKey);
  }
  function onSortKey(e) { if (e.key === 'Escape') closeSort(); }

  if (sortBtn) {
    sortBtn.addEventListener('click', function () {
      sortPop && sortPop.classList.contains('show') ? closeSort() : openSort();
    });
  }
  if (sortPop) {
    sortPop.querySelectorAll('[data-close="sort"]').forEach(function (el) {
      el.addEventListener('click', closeSort);
    });
  }

  /* ── Filter Drawer açma/kapama ──────────────────────────── */
  function openDrawer() {
    drawer.classList.add('show');
    drawer.setAttribute('aria-hidden', 'false');
    openBtn.setAttribute('aria-expanded', 'true');
    document.body.style.overflow = 'hidden';
    document.addEventListener('keydown', onDrawerKey);
    // İlk fokuslanabilir elementi bul
    var first = drawer.querySelector('button, input, select, a[href]');
    if (first) setTimeout(function () { first.focus(); }, 50);
  }

  function closeDrawer() {
    drawer.classList.remove('show');
    drawer.setAttribute('aria-hidden', 'true');
    openBtn.setAttribute('aria-expanded', 'false');
    document.body.style.overflow = '';
    document.removeEventListener('keydown', onDrawerKey);
    openBtn.focus();
  }

  function onDrawerKey(e) {
    if (e.key === 'Escape') { closeDrawer(); return; }
    // Focus trap
    if (e.key === 'Tab') {
      var focusable = Array.from(
        drawer.querySelectorAll('button:not([disabled]), input:not([disabled]), select:not([disabled]), a[href], textarea:not([disabled]), [tabindex]:not([tabindex="-1"])')
      ).filter(function (el) { return el.offsetParent !== null; });
      if (!focusable.length) return;
      var first = focusable[0];
      var last  = focusable[focusable.length - 1];
      if (e.shiftKey && document.activeElement === first) {
        e.preventDefault(); last.focus();
      } else if (!e.shiftKey && document.activeElement === last) {
        e.preventDefault(); first.focus();
      }
    }
  }

  openBtn.addEventListener('click', function () {
    drawer.classList.contains('show') ? closeDrawer() : openDrawer();
  });

  if (closeBtn)  closeBtn.addEventListener('click',  closeDrawer);
  if (overlay)   overlay.addEventListener('click',   closeDrawer);

  /* ── Fiyat aralığı: min > max kontrolü ─────────────────── */
  var form = document.getElementById('filter-form');
  if (form) {
    form.addEventListener('submit', function () {
      var minEl = form.querySelector('[name="price_min"]');
      var maxEl = form.querySelector('[name="price_max"]');
      if (minEl && maxEl && minEl.value !== '' && maxEl.value !== '') {
        var mn = parseFloat(minEl.value);
        var mx = parseFloat(maxEl.value);
        if (mn > mx) {
          // değerleri yer değiştir
          var tmp = minEl.value;
          minEl.value = maxEl.value;
          maxEl.value = tmp;
        }
      }
      // Boş değerleri gönderme (temiz URL için)
      if (minEl && minEl.value === '') minEl.disabled = true;
      if (maxEl && maxEl.value === '') maxEl.disabled = true;
    });
  }

  /* ── Temizle butonu: sadece sort parametresini koru ─────── */
  // (href zaten PHP tarafında doğru ayarlandı)

  /* ── Drawer başlangıç durumu ────────────────────────────── */
  drawer.setAttribute('aria-hidden', 'true');

})();
