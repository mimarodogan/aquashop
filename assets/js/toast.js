/* Toast yöneticisi — flash mesajlarını işler ve programatik gösterim sağlar */
(function () {
  'use strict';

  function getContainer() {
    var c = document.querySelector('.toast-container');
    if (!c) {
      c = document.createElement('div');
      c.className = 'toast-container';
      c.setAttribute('aria-live', 'polite');
      c.setAttribute('aria-atomic', 'true');
      document.body.appendChild(c);
    }
    return c;
  }

  function dismiss(t) {
    if (t._dismissed) return;
    t._dismissed = true;
    t.classList.remove('show');
    t.classList.add('hide');
    setTimeout(function () { if (t.parentNode) t.parentNode.removeChild(t); }, 400);
  }

  function show(message, type, duration) {
    type = type || 'info';
    duration = (typeof duration === 'number') ? duration : 5000;
    var container = getContainer();
    var t = document.createElement('div');
    t.className = 'toast ' + type;
    t.setAttribute('role', 'status');
    var ic = document.createElement('span'); ic.className = 'ic'; t.appendChild(ic);
    var body = document.createElement('div'); body.className = 'body'; body.textContent = message; t.appendChild(body);
    var close = document.createElement('button');
    close.className = 'close'; close.type = 'button'; close.setAttribute('aria-label','Kapat');
    close.innerHTML = '&times;';
    close.addEventListener('click', function () { dismiss(t); });
    t.appendChild(close);
    if (duration > 0) {
      var prog = document.createElement('div');
      prog.className = 'progress';
      prog.style.animationDuration = duration + 'ms';
      t.appendChild(prog);
    }
    container.appendChild(t);
    // Force reflow then animate in
    requestAnimationFrame(function () { t.classList.add('show'); });
    if (duration > 0) {
      setTimeout(function () { dismiss(t); }, duration);
    }
    return t;
  }

  // Sayfa yüklendiğinde sunucudan gelen flash mesajlarını oku
  function bootstrapFlashes() {
    var data = document.getElementById('flash-data');
    if (!data) return;
    try {
      var arr = JSON.parse(data.textContent || '[]');
      arr.forEach(function (f) { show(f.msg, f.type); });
    } catch (e) {}
  }

  // Public API
  window.toast = {
    show: show,
    success: function (m, d) { return show(m, 'success', d); },
    error:   function (m, d) { return show(m, 'error', d); },
    warning: function (m, d) { return show(m, 'warning', d); },
    info:    function (m, d) { return show(m, 'info', d); }
  };

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', bootstrapFlashes);
  } else {
    bootstrapFlashes();
  }
})();
