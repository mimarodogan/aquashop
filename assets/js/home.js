/* Anasayfa — hero slider ve kategori şeridi */
(function () {
  'use strict';

  // Hero Rotator — Opacity-based slider (LCP-friendly, scroll-snap YOK)
  // Tüm slide'lar position:absolute, opacity ile geçiş. JS sadece .active
  // class'ını değiştirir. İlk slide her zaman LCP candidate.
  var rotator = document.querySelector('.hero-rotator');
  if (rotator && rotator.classList.contains('has-multi')) {
    var slides = rotator.querySelectorAll('.hero-slide');
    var dots = rotator.querySelectorAll('.hero-dots button');
    var interval = parseInt(rotator.dataset.rotateInterval || '5000', 10);
    var idx = 0, timer = null;
    var swipeStartX = 0, swipeStartY = 0;

    function go(n) {
      var next = ((n % slides.length) + slides.length) % slides.length;
      if (next === idx) return;
      slides[idx].classList.remove('active');
      slides[idx].setAttribute('aria-hidden', 'true');
      slides[idx].setAttribute('tabindex', '-1');
      if (dots[idx]) { dots[idx].classList.remove('active'); dots[idx].removeAttribute('aria-current'); }
      idx = next;
      slides[idx].classList.add('active');
      slides[idx].removeAttribute('aria-hidden');
      slides[idx].removeAttribute('tabindex');
      if (dots[idx]) { dots[idx].classList.add('active'); dots[idx].setAttribute('aria-current', 'true'); }
    }
    function start() { stop(); timer = setInterval(function(){ go(idx + 1); }, interval); }
    function stop()  { if (timer) { clearInterval(timer); timer = null; } }

    // Nokta tıklaması
    dots.forEach(function(d) {
      d.addEventListener('click', function() {
        var n = parseInt(d.dataset.index || '0', 10);
        go(n); start();
      });
    });

    // Sol/sağ ok butonları
    var prevBtn = rotator.querySelector('.hero-nav.prev');
    var nextBtn = rotator.querySelector('.hero-nav.next');
    if (prevBtn) prevBtn.addEventListener('click', function(e){ e.preventDefault(); go(idx - 1); start(); });
    if (nextBtn) nextBtn.addEventListener('click', function(e){ e.preventDefault(); go(idx + 1); start(); });

    // Mouse hover ile duraklat
    rotator.addEventListener('mouseenter', stop);
    rotator.addEventListener('mouseleave', start);

    // Dokunmatik swipe (sol/sağ)
    rotator.addEventListener('touchstart', function(e) {
      stop();
      swipeStartX = e.touches[0].clientX;
      swipeStartY = e.touches[0].clientY;
    }, { passive: true });
    rotator.addEventListener('touchend', function(e) {
      var dx = e.changedTouches[0].clientX - swipeStartX;
      var dy = e.changedTouches[0].clientY - swipeStartY;
      // Yatay hareket dikeyden büyükse ve 40px+ ise swipe
      if (Math.abs(dx) > 40 && Math.abs(dx) > Math.abs(dy)) {
        go(idx + (dx < 0 ? 1 : -1));
      }
      start();
    }, { passive: true });

    // Sayfa görünür/gizli olunca duraklat
    document.addEventListener('visibilitychange', function() {
      if (document.hidden) stop(); else start();
    });

    // Klavye: sol/sağ ok tuşları (rotator focus'taysa)
    rotator.addEventListener('keydown', function(e) {
      if (e.key === 'ArrowLeft')  { go(idx - 1); start(); }
      if (e.key === 'ArrowRight') { go(idx + 1); start(); }
    });

    // LCP yakalandıktan SONRA transition'ları aktive et
    requestAnimationFrame(function () {
      requestAnimationFrame(function () { rotator.classList.add('ready'); });
    });

    // Auto-rotation: Lighthouse 5sn penceresinden sonra başlat (6sn delay)
    var prefersReduced = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    if (!prefersReduced) {
      var kickoff = function () { setTimeout(start, 6000); };
      if (document.readyState === 'complete') kickoff();
      else window.addEventListener('load', kickoff);
    }
  }

  // Kategori şeridi
  var t = document.getElementById('catTrack');
  if (t) {
    var first = t.querySelector('.cat-item');
    if (first) {
      var GAP = 24;
      var itemW = function () { return first.offsetWidth + GAP; };
      var fit = function () {
        var parent = t.parentElement;
        var arrows = parent.querySelectorAll('.cat-nav');
        var arrowsW = 0;
        arrows.forEach(function (a) { arrowsW += a.offsetWidth; });
        var stripGap = 8 * 2;
        var avail = parent.clientWidth - arrowsW - stripGap;
        var iw = itemW();
        var n = Math.max(1, Math.floor((avail + GAP) / iw));
        var visibleW = n * iw - GAP;
        t.style.width = visibleW + 'px';
        t.style.maxWidth = visibleW + 'px';
        t.style.flex = '0 0 auto';
      };
      var step = function () { return Math.max(itemW(), t.clientWidth); };
      var leftBtn = document.querySelector('.cat-nav.left');
      var rightBtn = document.querySelector('.cat-nav.right');
      if (leftBtn)  leftBtn.addEventListener('click', function () { t.scrollBy({ left: -step(), behavior: 'smooth' }); });
      if (rightBtn) rightBtn.addEventListener('click', function () { t.scrollBy({ left:  step(), behavior: 'smooth' }); });

      // İlk fit() çağrısını LCP yakalandıktan SONRA yap (DOM mutation LCP'yi
      // invalidate etmesin). Resize listener her zaman aktif.
      var doFit = function () { setTimeout(fit, 100); };
      if (document.readyState === 'complete') doFit();
      else window.addEventListener('load', doFit);
      window.addEventListener('resize', fit);
    }
  }
})();
