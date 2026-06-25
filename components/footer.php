</main>
<?php
$__footerSiteName = trim((string)(setting('site_name') ?? '')) ?: SITE_NAME_FALLBACK;
$__footerTagline  = trim((string)setting('footer_tagline', '')) ?: trim((string)setting('site_tagline', ''));
$__footerAbout    = trim((string)setting('footer_about', '')) ?: $__footerTagline;
$__fAddr          = trim((string)setting('contact_address',''));
$__fPhone         = trim((string)setting('contact_phone',''));
$__fTel           = preg_replace('/[^0-9+]/','',$__fPhone);
$__fMail          = trim((string)setting('contact_email',''));
$__fHours         = trim((string)setting('working_hours', ''));
$__waEnabled      = setting('whatsapp_enabled','0') === '1';
$__waNumber       = preg_replace('/\D+/','', (string)setting('whatsapp_number',''));
$__waMsg          = trim((string)setting('whatsapp_message',''));
$__footerCats     = array();
try {
    $__footerCats = db()->query("SELECT name, slug FROM categories WHERE parent_id IS NULL ORDER BY sort_order ASC, name ASC LIMIT 5")->fetchAll();
} catch (Exception $__e) { $__footerCats = array(); }
$__footerSocials = array_filter([
    'instagram' => trim((string)setting('social_instagram','')),
    'facebook'  => trim((string)setting('social_facebook','')),
    'twitter-x' => trim((string)setting('social_twitter','')),
    'youtube'   => trim((string)setting('social_youtube','')),
    'linkedin'  => trim((string)setting('social_linkedin','')),
    'tiktok'    => trim((string)setting('social_tiktok','')),
]);
if ($__waEnabled && $__waNumber !== '') {
    $__footerSocials['whatsapp'] = 'https://wa.me/' . $__waNumber . ($__waMsg !== '' ? '?text=' . rawurlencode($__waMsg) : '');
}
?>
<footer class="aq-footer">
  <div class="aq-footer-top">
    <div class="aq-container">
      <div class="aq-footer-benefits">
        <div>
          <i class="bi bi-truck"></i>
          <strong>Hızlı Kargo</strong>
          <span>1.999 TL ve üzeri siparişlerde avantajlı kargo</span>
        </div>
        <div>
          <i class="bi bi-shield-check"></i>
          <strong>Güvenli Ödeme</strong>
          <span>SSL korumalı güvenli alışveriş altyapısı</span>
        </div>
        <div>
          <i class="bi bi-headset"></i>
          <strong>Uzman Destek</strong>
          <span>Akvaryum ürünleri için profesyonel destek</span>
        </div>
        <div>
          <i class="bi bi-arrow-repeat"></i>
          <strong>Kolay İade</strong>
          <span>Memnuniyet odaklı kolay iade süreci</span>
        </div>
      </div>
    </div>
  </div>

  <div class="aq-container">
    <div class="aq-footer-main aq-footer-main-clean">
      <div class="aq-footer-brand">
        <a href="<?= url('home') ?>" class="aq-footer-logo" aria-label="<?= e($__footerSiteName) ?> Ana Sayfa">
          <span><?= e($__footerSiteName) ?></span>
          <?php if ($__footerTagline !== ''): ?><small><?= e($__footerTagline) ?></small><?php endif; ?>
        </a>

        <?php if ($__footerAbout !== ''): ?><p><?= e($__footerAbout) ?></p><?php endif; ?>

        <?php if ($__footerSocials): ?>
        <div class="aq-footer-social">
          <?php foreach ($__footerSocials as $__sName => $__sUrl): ?>
            <a href="<?= e($__sUrl) ?>" target="_blank" rel="noopener" aria-label="<?= e(ucwords(str_replace('-', ' ', $__sName))) ?>">
              <i class="bi bi-<?= e($__sName) ?>"></i>
            </a>
          <?php endforeach; ?>
        </div>
        <?php endif; ?>
      </div>

      <div class="aq-footer-col">
        <h3>Kurumsal</h3>
        <a href="<?= url('home') ?>">Ana Sayfa</a>
        <a href="<?= url('contact') ?>">İletişim</a>
        <a href="<?= url('blog') ?>">Blog</a>
        <a href="<?= url('page', ['slug'=>'cerez-politikasi']) ?>">Çerez Politikası</a>
        <a href="<?= url('about') ?>">Hakkımızda</a>
        <a href="<?= url('page', ['slug'=>'iade-degisim']) ?>">İade &amp; Değişim</a>
        <a href="<?= url('page', ['slug'=>'kargo-teslimat']) ?>">Kargo &amp; Teslimat</a>
      </div>

      <div class="aq-footer-col">
        <h3>Kategoriler</h3>
        <a href="<?= url('categories_list') ?>">Tüm Kategoriler</a>
        <?php foreach ($__footerCats as $__cat): ?>
          <a href="<?= e(url('category', ['slug' => $__cat['slug']])) ?>"><?= e($__cat['name']) ?></a>
        <?php endforeach; ?>
      </div>

      <div class="aq-footer-col">
        <h3>Müşteri Hizmetleri</h3>
        <a href="<?= url('account') ?>">Sipariş Takibi</a>
        <a href="<?= url('account') ?>">Hesabım</a>
        <a href="<?= url('favorites') ?>">Favorilerim</a>
        <a href="<?= url('contact') ?>">Yardım ve Destek</a>
        <a href="<?= url('page', ['slug'=>'kargo-teslimat']) ?>">Kargo ve Teslimat</a>
        <a href="<?= url('page', ['slug'=>'iade-degisim']) ?>">İade ve Değişim</a>
      </div>

      <div class="aq-footer-newsletter">
        <h3>E-Bültene Katıl</h3>
        <p>Yeni ürünler, kampanyalar ve akvaryum bakım önerileri için bültenimize katıl.</p>
        <form action="<?= SITE_URL ?>/newsletter-subscribe.php" method="post">
          <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
          <input type="email" name="email" placeholder="E-posta adresiniz" autocomplete="email">
          <button type="submit">Katıl</button>
        </form>
        <small>Dilediğiniz zaman abonelikten çıkabilirsiniz.</small>
      </div>
    </div>

    <div class="aq-footer-contact-band">
      <?php if ($__fAddr !== ''): ?>
      <div class="aq-footer-contact-item">
        <i class="bi bi-geo-alt"></i>
        <div>
          <strong>Adres</strong>
          <span><?= e($__fAddr) ?></span>
        </div>
      </div>
      <?php endif; ?>

      <?php if ($__fPhone !== ''): ?>
      <div class="aq-footer-contact-item">
        <i class="bi bi-telephone"></i>
        <div>
          <strong>Telefon</strong>
          <a href="tel:<?= e($__fTel) ?>"><?= e($__fPhone) ?></a>
        </div>
      </div>
      <?php endif; ?>

      <?php if ($__fMail !== ''): ?>
      <div class="aq-footer-contact-item">
        <i class="bi bi-envelope"></i>
        <div>
          <strong>E-posta</strong>
          <a href="mailto:<?= e($__fMail) ?>"><?= e($__fMail) ?></a>
        </div>
      </div>
      <?php endif; ?>

      <?php if ($__fHours !== ''): ?>
      <div class="aq-footer-contact-item">
        <i class="bi bi-clock"></i>
        <div>
          <strong>Çalışma Saatleri</strong>
          <span><?= e($__fHours) ?></span>
        </div>
      </div>
      <?php endif; ?>
    </div>

    <div class="aq-footer-bottom">
      <span>© <?= date('Y') ?> <?= e($__footerSiteName) ?>. Tüm hakları saklıdır. · Tasarım &amp; Geliştirme: <a href="https://odogan.com.tr" target="_blank" rel="noopener">Mimar Osman Doğan</a></span>
      <div>
        <a href="<?= url('page', ['slug'=>'cerez-politikasi']) ?>">Çerez Politikası</a>
        <a href="<?= url('page', ['slug'=>'iade-degisim']) ?>">İade &amp; Değişim</a>
        <a href="<?= url('page', ['slug'=>'kargo-teslimat']) ?>">Kargo &amp; Teslimat</a>
        <a href="<?= url('page', ['slug'=>'kisisel-verilerin-korunmasi']) ?>">Kişisel Verilerin Korunması</a>
        <a href="<?= url('page', ['slug'=>'uyelik-kosullari']) ?>">Üyelik Koşulları</a>
      </div>
    </div>
  </div>
</footer>

<?php /* Footer'a özel CSS artık style.css içinde (W3C: <style> body içinde olamaz) */ ?>

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

<?php /* Footer link sütunları — mobilde başlığa tıklayınca açılır/kapanır (CSS yalnız mobilde gizler) */ ?>
<script>
(function(){
  document.querySelectorAll('.aq-footer-col > h3').forEach(function(h){
    h.addEventListener('click', function(){ h.parentElement.classList.toggle('is-open'); });
  });
})();
</script>

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
