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

  // Mobil menü toggle
  var openBtn = document.querySelector('.aq-mobile-menu-btn');
  var closeBtn = document.querySelector('.aq-mobile-close');
  var backdrop = document.querySelector('.aq-mobile-backdrop');
  var panelLinks = document.querySelectorAll('.aq-mobile-panel a');

  function mmOpen() {
    document.body.classList.add('aq-menu-open');
    document.body.classList.add('mobile-menu-open');
  }
  function mmClose() {
    document.body.classList.remove('aq-menu-open');
    document.body.classList.remove('mobile-menu-open');
  }

  if (openBtn && closeBtn && backdrop) {
    openBtn.addEventListener('click', mmOpen);
    closeBtn.addEventListener('click', mmClose);
    backdrop.addEventListener('click', mmClose);
    panelLinks.forEach(function (link) {
      link.addEventListener('click', mmClose);
    });
    document.addEventListener('keydown', function (e) {
      if (e.key === 'Escape') {
        mmClose();
      }
    });
  }

  // Lightbox (medya kütüphanesi)
  window.lightbox = function (src) {
    var lb = document.getElementById('lb');
    var img = document.getElementById('lbimg');
    if (!lb || !img) return;
    img.src = src;
    lb.classList.add('show');
  };
})();
