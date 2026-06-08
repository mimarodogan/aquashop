/* ============================================================================
   AI Danışman widget — istemci davranışı
   Kök elemanın data-* attribute'larından yapılandırılır (#ai-assistant).
   ========================================================================== */
(function () {
  'use strict';

  var root = document.getElementById('ai-assistant');
  if (!root) return;

  var endpoint = root.getAttribute('data-endpoint') || '';
  var csrf     = root.getAttribute('data-csrf') || '';
  var waUrl    = root.getAttribute('data-wa') || '';

  var fab     = root.querySelector('[data-ai-toggle]');
  var panel   = root.querySelector('#ai-asst-panel');
  var closeBtn= root.querySelector('[data-ai-close]');
  var body    = root.querySelector('[data-ai-body]');
  var form    = root.querySelector('[data-ai-form]');
  var input   = root.querySelector('[data-ai-text]');
  var sendBtn = root.querySelector('[data-ai-send]');
  var chipsBox= root.querySelector('[data-ai-chips]');

  var history = [];   // [{role, content}] — sunucuya gönderilen kısa bağlam
  var busy = false;
  var opened = false;

  /* ── yardımcılar ─────────────────────────────────────────────────────────── */
  function escHtml(s) {
    return String(s).replace(/[&<>"']/g, function (c) {
      return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
    });
  }

  // Bot metnini güvenle render et: markdown link [x](http.. veya /goreli) + **kalın**, gerisi escape.
  // (javascript: gibi şemalar regex'e takılmaz — yalnız http(s) veya / ile başlayan yollar.)
  function renderRich(raw) {
    var out = '', re = /\[([^\]]+)\]\((https?:\/\/[^\s)]+|\/[^\s)]*)\)/g, last = 0, m;
    while ((m = re.exec(raw))) {
      out += escHtml(raw.slice(last, m.index));
      out += '<a href="' + escHtml(m[2]) + '" target="_blank" rel="noopener">' + escHtml(m[1]) + '</a>';
      last = m.index + m[0].length;
    }
    out += escHtml(raw.slice(last));
    out = out.replace(/\*\*([^*]+)\*\*/g, '<strong>$1</strong>');
    return out;
  }

  function scrollBottom() { body.scrollTop = body.scrollHeight; }

  function addBubble(role, text, asHtml) {
    var wrap = document.createElement('div');
    wrap.className = 'ai-msg ' + (role === 'user' ? 'ai-msg-user' : 'ai-msg-bot');
    var b = document.createElement('div');
    b.className = 'ai-bubble';
    if (asHtml) { b.innerHTML = renderRich(text); } else { b.textContent = text; }
    wrap.appendChild(b);
    body.appendChild(wrap);
    scrollBottom();
    return wrap;
  }

  function showTyping() {
    var wrap = document.createElement('div');
    wrap.className = 'ai-msg ai-msg-bot';
    wrap.setAttribute('data-ai-typing', '');
    wrap.innerHTML = '<div class="ai-bubble ai-typing" style="padding:12px 14px"><span></span><span></span><span></span></div>';
    body.appendChild(wrap);
    scrollBottom();
    return wrap;
  }

  function renderProducts(list) {
    if (!list || !list.length) return;
    var box = document.createElement('div');
    box.className = 'ai-msg ai-msg-bot';
    var grid = document.createElement('div');
    grid.className = 'ai-prods';

    list.forEach(function (p) {
      var a = document.createElement('a');
      a.className = 'ai-prod';
      a.href = p.url || '#';
      a.target = '_blank';
      a.rel = 'noopener';

      if (p.image) {
        var img = document.createElement('img');
        img.className = 'ai-prod-img';
        img.src = p.image; img.alt = p.name || ''; img.loading = 'lazy';
        a.appendChild(img);
      } else {
        var ph = document.createElement('span');
        ph.className = 'ai-prod-ph';
        ph.textContent = '🐟';
        a.appendChild(ph);
      }

      var info = document.createElement('div');
      info.className = 'ai-prod-info';

      var nm = document.createElement('span');
      nm.className = 'ai-prod-name';
      nm.textContent = p.name || '';
      info.appendChild(nm);

      var meta = document.createElement('div');
      meta.className = 'ai-prod-meta';

      var price = document.createElement('span');
      price.className = 'ai-prod-price';
      price.textContent = p.price || '';
      meta.appendChild(price);

      if (p.old_price) {
        var op = document.createElement('span');
        op.className = 'ai-prod-old';
        op.textContent = p.old_price;
        meta.appendChild(op);
      }

      var st = document.createElement('span');
      st.className = 'ai-prod-stock' + (!p.in_stock ? ' out' : (!p.online_sale ? ' req' : ''));
      st.textContent = p.stock_note || '';
      meta.appendChild(st);

      info.appendChild(meta);
      a.appendChild(info);
      grid.appendChild(a);
    });

    box.appendChild(grid);
    body.appendChild(box);
    scrollBottom();
  }

  function renderArticles(list) {
    if (!list || !list.length) return;
    var box = document.createElement('div');
    box.className = 'ai-msg ai-msg-bot';
    var grid = document.createElement('div');
    grid.className = 'ai-prods';

    list.forEach(function (a) {
      var el = document.createElement('a');
      el.className = 'ai-art';
      el.href = a.url || '#';
      el.target = '_blank';
      el.rel = 'noopener';

      var ic = document.createElement('span');
      ic.className = 'ai-art-ic';
      ic.textContent = '📖';
      el.appendChild(ic);

      var info = document.createElement('div');
      info.className = 'ai-prod-info';

      var t = document.createElement('span');
      t.className = 'ai-art-title';
      t.textContent = a.title || '';
      info.appendChild(t);

      if (a.excerpt) {
        var ex = document.createElement('span');
        ex.className = 'ai-art-ex';
        ex.textContent = a.excerpt;
        info.appendChild(ex);
      }

      var go = document.createElement('span');
      go.className = 'ai-art-go';
      go.textContent = 'Rehberi oku →';
      info.appendChild(go);

      el.appendChild(info);
      grid.appendChild(el);
    });

    box.appendChild(grid);
    body.appendChild(box);
    scrollBottom();
  }

  /* ── panel aç/kapat ──────────────────────────────────────────────────────── */
  function open() {
    if (opened) return;
    opened = true;
    panel.hidden = false;
    root.classList.add('is-open');
    fab.setAttribute('aria-expanded', 'true');
    setTimeout(function () { if (input) input.focus(); }, 60);
  }
  function close() {
    opened = false;
    panel.hidden = true;
    root.classList.remove('is-open');
    fab.setAttribute('aria-expanded', 'false');
    fab.focus();
  }

  fab.addEventListener('click', function () { opened ? close() : open(); });
  if (closeBtn) closeBtn.addEventListener('click', close);
  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape' && opened) close();
  });

  /* ── mesaj gönderme ──────────────────────────────────────────────────────── */
  function send(message) {
    message = (message || '').trim();
    if (!message || busy) return;
    busy = true;
    if (sendBtn) sendBtn.disabled = true;

    // çipleri ilk mesajdan sonra kaldır
    if (chipsBox) { chipsBox.remove(); chipsBox = null; }

    addBubble('user', message, false);
    var sendHistory = history.slice(-8);     // önceki turlar
    history.push({ role: 'user', content: message });
    if (input) input.value = '';

    var typing = showTyping();

    var fd = new FormData();
    fd.append('csrf', csrf);
    fd.append('message', message);
    fd.append('history', JSON.stringify(sendHistory));

    fetch(endpoint, { method: 'POST', body: fd, headers: { 'X-Requested-With': 'XMLHttpRequest' } })
      .then(function (r) { return r.json().catch(function () { return { error: 'Yanıt çözümlenemedi.' }; }); })
      .then(function (data) {
        if (typing) typing.remove();
        if (data && data.ok) {
          addBubble('bot', data.reply || '', true);
          history.push({ role: 'assistant', content: data.reply || '' });
          if (data.products && data.products.length) renderProducts(data.products);
          if (data.articles && data.articles.length) renderArticles(data.articles);
        } else {
          var msg = (data && data.error) ? data.error : 'Şu an yanıt veremiyorum.';
          var w = addBubble('bot', msg, false);
          var ho = (data && data.handoff) || waUrl;
          if (ho) {
            var a = document.createElement('a');
            a.href = ho; a.target = '_blank'; a.rel = 'noopener';
            a.style.cssText = 'display:inline-block;margin-top:6px;color:#25D366;font-weight:600;font-size:12px;text-decoration:underline';
            a.textContent = 'WhatsApp\'tan yazın →';
            w.querySelector('.ai-bubble').appendChild(document.createElement('br'));
            w.querySelector('.ai-bubble').appendChild(a);
          }
        }
      })
      .catch(function () {
        if (typing) typing.remove();
        addBubble('bot', 'Bağlantı hatası oluştu. Lütfen tekrar deneyin.', false);
      })
      .then(function () {
        busy = false;
        if (sendBtn) sendBtn.disabled = false;
        if (input) input.focus();
      });
  }

  form.addEventListener('submit', function (e) {
    e.preventDefault();
    send(input ? input.value : '');
  });

  if (chipsBox) {
    chipsBox.addEventListener('click', function (e) {
      var chip = e.target.closest('[data-ai-chip]');
      if (chip) send(chip.textContent);
    });
  }
})();
