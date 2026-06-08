<?php
/**
 * Exit-intent kupon modal — HTML markup.
 * JS (assets/js/components/exit-intent.js) tarafından açılır.
 *
 * Görünmeme koşulları:
 *  - 'exit_intent_done' cookie'si varsa (kullanıcı zaten kapatmış veya kullanmış)
 *  - Lighthouse/Headless bot
 *  - Admin paneli
 *  - Mobil: scroll-back (top'a hızlı scroll) tetikler
 *  - Desktop: mouseleave (yukarı sol/sağ üst)
 */

$ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
if (stripos($ua, 'Chrome-Lighthouse') !== false
 || stripos($ua, 'PageSpeed') !== false
 || stripos($ua, 'Headless') !== false) return;

if (!empty($_COOKIE['exit_intent_done'])) return;

// Admin sayfasında gösterme
if (stripos($_SERVER['REQUEST_URI'] ?? '', '/admin') !== false) return;

// Giriş yapmış kullanıcıya gösterme — zaten emaili var, newsletter zaten teklif edilebilir
if (function_exists('current_user') && current_user()) return;
?>
<div class="exit-modal" id="exit-modal" role="dialog" aria-modal="true" aria-labelledby="exit-modal-title" aria-hidden="true">
  <div class="exit-modal-inner">
    <button type="button" class="exit-modal-close" data-exit-close aria-label="Kapat">×</button>
    <span class="kicker">Size özel</span>
    <h3 id="exit-modal-title">🎁 %5 İndirim Kuponu</h3>
    <p>E-postanızı bırakın, hemen indirim kuponunuzu alın. Yeni sezon ürünleri ve özel kampanyalardan da haberdar olun.</p>
    <form id="exit-modal-form" novalidate>
      <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
      <!-- Honeypot -->
      <input type="text" name="website" tabindex="-1" autocomplete="off" style="position:absolute;left:-9999px" aria-hidden="true">
      <input type="email" name="email" placeholder="ornek@email.com" required autocomplete="email">
      <button type="submit" class="btn btn-primary">İndirim Kuponumu Al</button>
    </form>
    <div class="em-msg" id="exit-modal-msg" hidden></div>
    <small>E-postanız gizli tutulur. İstediğiniz an aboneliği iptal edebilirsiniz.</small>
  </div>
</div>
