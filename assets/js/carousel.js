/**
 * Tema Carousel & UI — Vanilla JS
 * Hem [data-carousel] (standart) hem [data-loop-carousel] (otomatik loop) destekler.
 */
(function(){
'use strict';

/* ── YARDIMCI ────────────────────────────────────────────────── */
function qs(sel,ctx){return (ctx||document).querySelector(sel)}
function qsa(sel,ctx){return Array.from((ctx||document).querySelectorAll(sel))}

/* ── STANDART CAROUSEL ──────────────────────────────────────── */
function initCarousel(wrap){
  var viewport  = wrap.querySelector('.aq-products-viewport,.aq-category-viewport');
  var track     = wrap.querySelector('.aq-products-track,.aq-category-track');
  var prevBtn   = wrap.querySelector('.aq-carousel-arrow[data-dir="-1"],.aq-category-side-arrow[data-dir="-1"]');
  var nextBtn   = wrap.querySelector('.aq-carousel-arrow[data-dir="1"],.aq-category-side-arrow[data-dir="1"]');
  if(!track) return;

  var items     = Array.from(track.children);
  var current   = 0;
  var perView   = parseInt(wrap.dataset.visibleDesktop||'5',10);
  var gap       = 14;

  function getPerView(){
    var w = wrap.offsetWidth;
    if(w < 420) return parseInt(wrap.dataset.visibleMobile||'2',10);
    if(w < 800) return parseInt(wrap.dataset.visibleTablet||'3',10);
    return parseInt(wrap.dataset.visibleDesktop||'5',10);
  }

  function getItemWidth(){
    perView = getPerView();
    return (viewport.offsetWidth - gap*(perView-1)) / perView;
  }

  function setWidths(){
    var iw = getItemWidth();
    items.forEach(function(el){
      el.style.width = iw+'px';
      el.style.flex  = '0 0 '+iw+'px';
    });
  }

  function moveTo(idx){
    var iw  = getItemWidth();
    var max = Math.max(0, items.length - perView);
    current = Math.max(0, Math.min(idx, max));
    track.style.transform = 'translateX(-'+( current*(iw+gap) )+'px)';
    if(prevBtn) prevBtn.disabled = current <= 0;
    if(nextBtn) nextBtn.disabled = current >= max;
  }

  setWidths();
  moveTo(0);

  if(prevBtn) prevBtn.addEventListener('click', function(){ moveTo(current-1); });
  if(nextBtn) nextBtn.addEventListener('click', function(){ moveTo(current+1); });

  /* Touch sürükle */
  var startX=0, isDragging=false;
  track.addEventListener('pointerdown',function(e){startX=e.clientX;isDragging=true;track.style.transition='none';});
  document.addEventListener('pointermove',function(e){
    if(!isDragging) return;
    var diff=e.clientX-startX;
    var iw=getItemWidth();
    track.style.transform='translateX(-'+(current*(iw+gap)-diff)+'px)';
  });
  document.addEventListener('pointerup',function(e){
    if(!isDragging) return;
    isDragging=false;
    track.style.transition='';
    var diff=e.clientX-startX;
    if(diff < -40) moveTo(current+1);
    else if(diff > 40) moveTo(current-1);
    else moveTo(current);
  });

  window.addEventListener('resize', function(){setWidths(); moveTo(current);});
}

/* ── LOOP CAROUSEL (sonsuz, otomatik) ───────────────────────── */
function initLoopCarousel(wrap){
  var viewport  = wrap.querySelector('.aq-loop-viewport');
  var track     = wrap.querySelector('.aq-loop-track');
  var prevBtn   = wrap.querySelector('.aq-loop-prev');
  var nextBtn   = wrap.querySelector('.aq-loop-next');
  if(!track) return;

  var speed     = parseInt(wrap.dataset.loopSpeed||'3500',10);
  var gap       = 14;
  var current   = 0;
  var items, cloneCount;

  function getPerView(){
    var w = wrap.offsetWidth;
    if(w<420) return parseInt(wrap.dataset.visibleMobile||'2',10);
    if(w<800) return parseInt(wrap.dataset.visibleTablet||'4',10);
    return parseInt(wrap.dataset.visibleDesktop||'5',10);
  }

  function setup(){
    /* Klonları temizle */
    qsa('.aq-loop-clone',track).forEach(function(el){el.remove();});
    var originals = Array.from(track.children);
    var perView   = getPerView();
    cloneCount    = perView + 1;
    var iw        = (viewport.offsetWidth - gap*(perView-1)) / perView;

    /* Sona klonlar */
    originals.slice(0, cloneCount).forEach(function(el){
      var c = el.cloneNode(true); c.classList.add('aq-loop-clone'); track.appendChild(c);
    });
    /* Başa klonlar */
    originals.slice(-cloneCount).forEach(function(el){
      var c = el.cloneNode(true); c.classList.add('aq-loop-clone'); track.insertBefore(c, track.firstChild);
    });

    items = Array.from(track.children);
    items.forEach(function(el){ el.style.width=iw+'px'; el.style.flex='0 0 '+iw+'px'; });
    current = cloneCount;
    track.style.transition = 'none';
    track.style.transform  = 'translateX(-'+( current*(iw+gap) )+'px)';
  }

  function goTo(idx, animate){
    var perView = getPerView();
    var iw = (viewport.offsetWidth - gap*(perView-1)) / perView;
    if(animate === false) track.style.transition = 'none';
    else track.style.transition = 'transform .4s cubic-bezier(.2,.8,.2,1)';
    current = idx;
    track.style.transform = 'translateX(-'+( current*(iw+gap) )+'px)';
  }

  function next(){ goTo(current+1, true); }
  function prev(){ goTo(current-1, true); }

  track.addEventListener('transitionend', function(){
    var perView   = getPerView();
    var origCount = items.length - cloneCount*2;
    if(current >= origCount + cloneCount){ goTo(cloneCount, false); }
    if(current <= cloneCount - 1)       { goTo(origCount + cloneCount - 1, false); }
  });

  if(prevBtn) prevBtn.addEventListener('click', function(){ clearInterval(timer); prev(); startAuto(); });
  if(nextBtn) nextBtn.addEventListener('click', function(){ clearInterval(timer); next(); startAuto(); });

  var timer;
  function startAuto(){ timer = setInterval(next, speed); }

  setup();
  startAuto();

  /* Pause on hover */
  wrap.addEventListener('mouseenter', function(){ clearInterval(timer); });
  wrap.addEventListener('mouseleave', function(){ startAuto(); });

  /* Touch */
  var startX=0, isDragging2=false;
  track.addEventListener('pointerdown',function(e){startX=e.clientX;isDragging2=true;clearInterval(timer);});
  document.addEventListener('pointerup',function(e){
    if(!isDragging2) return;
    isDragging2=false;
    var diff=e.clientX-startX;
    if(diff<-40) next(); else if(diff>40) prev();
    startAuto();
  });

  window.addEventListener('resize', function(){ setup(); startAuto(); });
}

/* ── HERO ROTATOR ───────────────────────────────────────────── */
function initHeroRotator(rotator){
  var slides   = qsa('.hero-slide,.aq-hero-slide', rotator);
  var dots     = qsa('.hero-dots button,.aq-hero-dots button', rotator);
  var prev     = qs('.hero-nav.prev,.aq-hero-prev', rotator);
  var next     = qs('.hero-nav.next,.aq-hero-next', rotator);
  if(slides.length < 2) return;
  var cur = 0, timer;
  function show(i){
    slides[cur].classList.remove('active', 'is-active');
    slides[cur].setAttribute('aria-hidden', 'true');
    slides[cur].setAttribute('tabindex', '-1');
    if(dots[cur]) {
      dots[cur].classList.remove('active', 'is-active');
      dots[cur].removeAttribute('aria-current');
    }
    cur = (i + slides.length) % slides.length;
    slides[cur].classList.add('active', 'is-active');
    slides[cur].removeAttribute('aria-hidden');
    slides[cur].removeAttribute('tabindex');
    if(dots[cur]) {
      dots[cur].classList.add('active', 'is-active');
      dots[cur].setAttribute('aria-current', 'true');
    }
  }
  function startTimer(){ timer = setInterval(function(){ show(cur+1); }, parseInt(rotator.dataset.rotateInterval||'5000',10)); }
  function resetTimer(){ clearInterval(timer); startTimer(); }
  rotator.classList.add('ready');
  if(prev) prev.addEventListener('click',function(){show(cur-1);resetTimer();});
  if(next) next.addEventListener('click',function(){show(cur+1);resetTimer();});
  dots.forEach(function(d,i){ d.addEventListener('click',function(){show(i);resetTimer();}); });
  startTimer();
}

/* ── ANA MENÜ "DEVAMI" DROPDOWN ────────────────────────────── */
function initMenuMore(){
  var moreBtn  = qs('.aq-menu-more-btn');
  var dropdown = qs('.aq-menu-more-dropdown');
  if(!moreBtn||!dropdown) return;
  moreBtn.addEventListener('click', function(e){
    e.stopPropagation();
    var open = moreBtn.getAttribute('aria-expanded')==='true';
    moreBtn.setAttribute('aria-expanded', open?'false':'true');
    dropdown.style.display = open ? 'none' : 'block';
  });
  document.addEventListener('click', function(){ moreBtn.setAttribute('aria-expanded','false'); dropdown.style.display='none'; });
}

/* ── HESAP DROPDOWN (touch) ─────────────────────────────────── */
function initAccountMenu(){
  var menus = qsa('.aq-account-menu');
  menus.forEach(function(m){
    var btn = m.querySelector('.aq-account-trigger');
    var drop= m.querySelector('.aq-account-dropdown');
    if(!btn||!drop) return;
    btn.addEventListener('click',function(e){
      e.stopPropagation();
      drop.style.display = drop.style.display==='block' ? 'none' : 'block';
    });
  });
  document.addEventListener('click',function(){
    qsa('.aq-account-dropdown').forEach(function(d){ d.style.display=''; });
  });
}

/* ── MOBİL MENÜ ─────────────────────────────────────────────── */
function initMobileMenu(){
  var menuBtn  = qs('.aq-mobile-menu-btn');
  var panel    = qs('.aq-mobile-panel');
  var backdrop = qs('.aq-mobile-backdrop');
  var closeBtn = qs('.aq-mobile-close');
  if(!panel) return;

  function openMenu(){
    panel.classList.add('open');
    if(backdrop) backdrop.classList.add('open');
    document.body.style.overflow='hidden';
  }
  function closeMenu(){
    panel.classList.remove('open');
    if(backdrop) backdrop.classList.remove('open');
    document.body.style.overflow='';
  }
  if(menuBtn)  menuBtn.addEventListener('click', openMenu);
  if(closeBtn) closeBtn.addEventListener('click', closeMenu);
  if(backdrop) backdrop.addEventListener('click', closeMenu);

  /* Alt kategori accordion */
  qsa('.aq-mobile-category-toggle').forEach(function(btn){
    btn.addEventListener('click', function(){
      var item = btn.closest('.aq-mobile-category-item');
      var list = item && item.querySelector('.aq-mobile-subcategory-list');
      if(!list) return;
      var open = list.classList.toggle('open');
      btn.setAttribute('aria-expanded', open?'true':'false');
    });
  });
}

/* ── ARAMA ÖNERİLERİ ─────────────────────────────────────────── */
function initSearchSuggestions(){
  var form  = qs('.aq-search');
  var input = form && form.querySelector('input[name=q]');
  var box   = form && form.querySelector('.aq-search-suggestions');
  if(!input||!box) return;
  var timer2;
  input.addEventListener('input',function(){
    clearTimeout(timer2);
    var q = input.value.trim();
    if(q.length < 2){ box.innerHTML=''; box.style.display='none'; return; }
    timer2 = setTimeout(function(){
      fetch((window.__SITE_URL || '') + '/ajax/search.php?q='+encodeURIComponent(q))
        .then(function(r){ return r.ok ? r.json() : {products: [], posts: []}; })
        .then(function(data){
          var products = data && data.products ? data.products : [];
          var posts = data && data.posts ? data.posts : [];
          if(!products.length && !posts.length){ box.style.display='none'; return; }
          box.innerHTML = '<div class="aq-search-suggestions-inner">'
            + (products.length ? '<div class="aq-search-suggestion-head">Ürün önerileri</div>' : '')
            + products.map(function(d){
              var img = d.image
                ? '<span class="aq-search-suggestion-image"><img src="'+d.image+'" alt=""></span>'
                : '<span class="aq-search-suggestion-image"><i class="bi bi-search"></i></span>';
              return '<a href="'+d.url+'" class="aq-search-suggestion-item">'
                + img
                + '<span class="aq-search-suggestion-info"><strong>'+d.name+'</strong>' + (d.category ? '<span>'+d.category+'</span>' : '') + '</span>'
                + (d.price ? '<span class="aq-search-suggestion-price"><strong>'+d.price+'</strong></span>' : '')
                + '</a>';
            }).join('')
            + (posts.length ? '<div class="aq-search-suggestion-head">Blog</div>' : '')
            + posts.map(function(d){
              return '<a href="'+d.url+'" class="aq-search-suggestion-item">'
                + '<span class="aq-search-suggestion-image"><i class="bi bi-journal-text"></i></span>'
                + '<span class="aq-search-suggestion-info"><strong>'+d.title+'</strong><span>Blog yazısı</span></span>'
                + '</a>';
            }).join('')
            + '</div>';
          box.style.display='block';
        }).catch(function(){ box.style.display='none'; });
    }, 280);
  });
  document.addEventListener('click',function(e){ if(!form.contains(e.target)){ box.style.display='none'; } });
  input.addEventListener('keydown',function(e){ if(e.key==='Escape'){ box.style.display='none'; } });
}

/* ── INIT ────────────────────────────────────────────────────── */
document.addEventListener('DOMContentLoaded',function(){
  /* Standart carousel */
  qsa('[data-carousel]').forEach(initCarousel);
  /* Loop carousel */
  qsa('[data-loop-carousel]').forEach(initLoopCarousel);
  /* Hero rotator */
  qsa('.hero-rotator[data-rotate-interval]').forEach(initHeroRotator);
  /* Menü */
  initMenuMore();
  initAccountMenu();
  initMobileMenu();
  initSearchSuggestions();
});
})();
