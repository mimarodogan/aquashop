/* Ortak istemci tarafı davranışlar — tüm sayfalarda yüklenir */
(function () {
  'use strict';

  // Adet stepper'ları (- 1 +)
  document.querySelectorAll('.qty-stepper').forEach(function (s) {
    var input = s.querySelector('input');
    if (!input) return;
    var min = parseInt(s.dataset.min || '1', 10);
    var max = parseInt(s.dataset.max || '9999', 10);
    s.querySelectorAll('.qty-btn').forEach(function (b) {
      b.addEventListener('click', function () {
        var v = parseInt(input.value || '1', 10) + parseInt(b.dataset.step, 10);
        if (v < min) v = min;
        if (v > max) v = max;
        input.value = v;
      });
    });
  });

  // Mobil menü toggle (data-attribute ile) — aria-expanded + aria-modal sync
  // aria-modal yalnızca menü AÇIKKEN eklenir (kapalıyken her zaman olmak LCP'yi engeller)
  var mmEl = document.getElementById('mobileMenu');
  function mmOpen() {
    document.body.classList.add('mobile-menu-open');
    if (mmEl) mmEl.setAttribute('aria-modal', 'true');
    document.querySelectorAll('[data-toggle="mobile-menu"]').forEach(function(b){
      b.setAttribute('aria-expanded','true');
    });
    var panel = document.querySelector('.mm-panel');
    if (panel) {
      var first = panel.querySelector('a,button');
      if (first) setTimeout(function(){ first.focus(); }, 200);
    }
  }
  function mmClose() {
    document.body.classList.remove('mobile-menu-open');
    if (mmEl) mmEl.removeAttribute('aria-modal');
    document.querySelectorAll('[data-toggle="mobile-menu"]').forEach(function(b){
      b.setAttribute('aria-expanded','false');
    });
    var t = document.querySelector('[data-toggle="mobile-menu"]');
    if (t) t.focus();
  }
  document.querySelectorAll('[data-toggle="mobile-menu"]').forEach(function (b) {
    b.addEventListener('click', function () {
      if (document.body.classList.contains('mobile-menu-open')) { mmClose(); } else { mmOpen(); }
    });
  });
  document.querySelectorAll('[data-close="mobile-menu"]').forEach(function (b) {
    b.addEventListener('click', mmClose);
  });
  // ESC ile mobil menü kapansın
  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape' && document.body.classList.contains('mobile-menu-open')) {
      mmClose();
    }
  });

  // Lightbox (medya kütüphanesi)
  window.lightbox = function (src) {
    var lb = document.getElementById('lb');
    var img = document.getElementById('lbimg');
    if (!lb || !img) return;
    img.src = src;
    lb.classList.add('show');
  };
})();
