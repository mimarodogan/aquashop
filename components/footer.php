</main>
<footer>
  <div class="container">
    <div class="foot-grid">
      <div class="foot-col">
        <div class="logo" style="margin-bottom:18px"><?= e(setting('site_name') ?? SITE_NAME_FALLBACK) ?><?php $tg = trim((string)setting('site_tagline','')); if ($tg !== ''): ?><small><?= e($tg) ?></small><?php endif; ?></div>
        <?php
        // Sosyal medya ikonları — yalnızca admin panelden girilmiş olanlar gösterilir
        $footSocials = array_filter([
            'instagram' => trim((string)setting('social_instagram','')),
            'facebook'  => trim((string)setting('social_facebook','')),
            'twitter'   => trim((string)setting('social_twitter','')),
            'youtube'   => trim((string)setting('social_youtube','')),
            'linkedin'  => trim((string)setting('social_linkedin','')),
            'tiktok'    => trim((string)setting('social_tiktok','')),
        ]);
        if ($footSocials):
            $footSocialIcon = ['twitter' => 'twitter-x']; // X (Twitter) ikon adı
        ?>
        <div class="foot-social">
          <?php foreach ($footSocials as $sName => $sUrl): ?>
            <a href="<?= e($sUrl) ?>" target="_blank" rel="noopener" aria-label="<?= e(ucfirst($sName)) ?>">
              <?= ic($footSocialIcon[$sName] ?? $sName, '', 18) ?>
            </a>
          <?php endforeach; ?>
        </div>
        <?php endif; ?>
      </div>
      <div class="foot-col"><h4>Mağaza</h4>
        <a href="<?= url('products') ?>">Tüm Ürünler</a>
        <a href="<?= e(url('category', ['slug'=>'yeni-sezon'])) ?>">Yeni Sezon</a>
        <a href="<?= e(url('category', ['slug'=>'koleksiyon'])) ?>">Koleksiyonlar</a>
        <a href="<?= e(url('category', ['slug'=>'kampanya'])) ?>">Kampanyalar</a>
      </div>
      <div class="foot-col"><h4>Kurumsal</h4>
        <a href="<?= url('about') ?>">Hakkımızda</a>
        <a href="<?= url('blog') ?>">Blog</a>
        <a href="<?= url('contact') ?>">İletişim</a>
        <a href="<?= url('page', ['slug'=>'kvkk']) ?>">KVKK</a>
        <a href="<?= url('page', ['slug'=>'uyelik-kosullari']) ?>">Üyelik Koşulları</a>
      </div>
      <div class="foot-col"><h4>Yardım</h4>
        <a href="<?= url('account') ?>">Sipariş Takibi</a>
        <a href="<?= url('page', ['slug'=>'kargo-teslimat']) ?>">Kargo &amp; Teslimat</a>
        <a href="<?= url('page', ['slug'=>'iade-degisim']) ?>">İade &amp; Değişim</a>
        <a href="<?= url('page', ['slug'=>'sss']) ?>">SSS</a>
      </div>
    </div>
    <div class="foot-bottom"><span>© <?= date('Y') ?> Tüm Hakları Saklıdır</span><span>Yurt İçi Güvenli Alışveriş</span></div>
  </div>
</footer>

<?php /* Footer'a özel CSS artık style.css içinde (W3C: <style> body içinde olamaz) */ ?>
<script>
/* Footer akordeon — başlığa tıklayınca o kolonu aç/kapat (mobilde etkili, masaüstünde zararsız) */
(function () {
  var mobile = window.matchMedia('(max-width: 768px)').matches;
  document.querySelectorAll('footer .foot-col h4').forEach(function (h) {
    // role/tabindex yalnızca mobilde — masaüstünde başlık, başlık olarak kalır (SR navigasyonu bozulmaz)
    if (mobile) { h.setAttribute('role', 'button'); h.setAttribute('tabindex', '0'); }
    function toggle() { h.parentElement.classList.toggle('foot-open'); }
    h.addEventListener('click', toggle);
    h.addEventListener('keydown', function (e) {
      if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); toggle(); }
    });
  });
})();
</script>

<?php
// Yüzen aksiyon balonu:
//  - AI Danışman açıksa onu göster (WhatsApp balonunun yerini alır).
//    AI açık olsa bile WhatsApp, widget içinde "insana bağlan" linki olarak korunur.
//  - AI kapalıysa klasik WhatsApp balonu (geriye dönük uyum).
require_once __DIR__ . '/../core/AIAssistant.php';
$aiOn       = ai_assistant_enabled();
$waEnabled  = setting('whatsapp_enabled','0') === '1';
$waNumber   = preg_replace('/\D+/','', (string)setting('whatsapp_number',''));
$waMsg      = trim((string)setting('whatsapp_message',''));
$fabPresent = false;

if ($aiOn):
  $fabPresent = true;
  include __DIR__ . '/ai-assistant.php';
?>
<script defer src="<?= SITE_URL ?>/assets/js/components/ai-assistant.min.js?v=<?= asset_v('js/components/ai-assistant.min.js') ?>"></script>
<?php
elseif ($waEnabled && $waNumber !== ''):
  $fabPresent = true;
  $waHref = 'https://wa.me/' . $waNumber . ($waMsg !== '' ? '?text=' . rawurlencode($waMsg) : '');
?>
<a href="<?= e($waHref) ?>" class="wa-fab" target="_blank" rel="noopener" aria-label="WhatsApp ile iletişime geç">
  <?= ic('whatsapp', '', 28) ?>
  <span class="wa-label">WhatsApp</span>
</a>
<?php endif; ?>

<!-- Yukarı çık butonu — sayfa kaydırıldığında görünür -->
<button type="button" class="to-top<?= $fabPresent ? ' to-top-stack' : '' ?>" aria-label="Sayfanın başına dön" data-to-top>
  <?= ic('chevron-up', '', 20) ?>
</button>
<script defer src="<?= SITE_URL ?>/assets/js/components/to-top.min.js?v=<?= asset_v('js/components/to-top.min.js') ?>"></script>

<?php /* KVKK çerez onay banner'ı — kullanıcı henüz onaylamadıysa görünür */ ?>
<?php include __DIR__ . '/cookie-banner.php'; ?>
<script defer src="<?= SITE_URL ?>/assets/js/components/cookie-banner.min.js?v=<?= asset_v('js/components/cookie-banner.min.js') ?>"></script>
<?php /* Cookie consent → analytics consent state köprüsü (GA4 / Pixel / Clarity) */ ?>
<?php if (setting('analytics_enabled','0') === '1'): ?>
<script defer src="<?= SITE_URL ?>/assets/js/components/analytics-consent.min.js?v=<?= asset_v('js/components/analytics-consent.min.js') ?>"></script>
<?php endif; ?>

<?php /* Exit-intent kupon modal — exit_intent_enabled ayarı '1' olduğunda aktif */ ?>
<?php if (setting('exit_intent_enabled','0') === '1'): ?>
<?php include __DIR__ . '/exit-intent.php'; ?>
<script defer src="<?= SITE_URL ?>/assets/js/components/exit-intent.min.js?v=<?= asset_v('js/components/exit-intent.min.js') ?>"></script>
<?php endif; ?>

<?php /* Sepete eklendi mini modal (Faz 6.B) */ ?>
<?php include __DIR__ . '/cart-added-modal.php'; ?>
<?php if (setting('cart_modal_enabled','1') === '1'): ?>
<script defer src="<?= SITE_URL ?>/assets/js/components/cart-added-modal.min.js?v=<?= asset_v('js/components/cart-added-modal.min.js') ?>"></script>
<?php endif; ?>

<?php /* Arama overlay */ ?>
<?php include __DIR__ . '/search-overlay.php'; ?>
<script>window.__SITE_URL = '<?= rtrim(SITE_URL, '/') ?>';</script>
<script defer src="<?= SITE_URL ?>/assets/js/components/search-overlay.min.js?v=<?= asset_v('js/components/search-overlay.min.js') ?>"></script>

</body>
</html>
