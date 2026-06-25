<?php
require_once __DIR__ . '/../includes/functions.php';
$page='contact'; $title='İletişim'; $sent=false; $err=null;

if ($_SERVER['REQUEST_METHOD']==='POST' && csrf_check($_POST['csrf'] ?? null)) {
    $n  = trim($_POST['name']    ?? '');
    $em = trim($_POST['email']   ?? '');
    $ph = trim($_POST['phone']   ?? '');
    $sb = trim($_POST['subject'] ?? '');
    $msg= trim($_POST['message'] ?? '');
    $pref = in_array($_POST['contact_pref'] ?? '', ['email','sms','phone'], true) ? $_POST['contact_pref'] : '';
    $kvkk = !empty($_POST['kvkk']);

    if (!$n || !$em || !$msg)            { $err = 'Lütfen zorunlu alanları doldurun.'; }
    elseif (!filter_var($em, FILTER_VALIDATE_EMAIL)) { $err = 'Geçerli bir e-posta girin.'; }
    elseif (!$kvkk)                       { $err = 'Devam etmek için KVKK metnini onaylamalısınız.'; }
    else {
        $prefLabel = ['email'=>'E-Posta','sms'=>'SMS','phone'=>'Telefon Araması'][$pref] ?? '';
        $extras = [];
        if ($ph)        $extras[] = "Telefon: " . $ph;
        if ($prefLabel) $extras[] = "Tercih edilen iletişim: " . $prefLabel;
        $finalMsg = $msg . ($extras ? "\n\n— — —\n" . implode("\n", $extras) : '');
        try {
            db()->prepare('INSERT INTO contact_messages (name,email,subject,message) VALUES (?,?,?,?)')
                ->execute([$n,$em,$sb,$finalMsg]);
            $sent=true;
        } catch (Throwable $e) { $err='Mesaj gönderilemedi.'; }
    }
}

// Harita embed: dört giriş biçimini de kabul et, src'yi güvenli çıkar (sadece google.* domainleri)
//  1) Tam <iframe ... src="..."> HTML (klasik Google Maps "Harita yerleştir" çıktısı)
//  2) Doğrudan embed URL'i (https://www.google.com/maps/embed?pb=...)
//  3) Plus Code (örn. "6X95+9P Nilüfer, Bursa") — WAF tetiklemez
//  4) Düz adres metni (örn. "Adres bilgisi")
$mapEmbed = trim((string)setting('contact_map_embed',''));
$mapSrc = '';
if ($mapEmbed !== '') {
    if (preg_match('~src=["\']([^"\']+)["\']~i', $mapEmbed, $m)) {
        // 1) iframe HTML
        $mapSrc = $m[1];
    } elseif (stripos($mapEmbed, 'http') === 0) {
        // 2) URL
        $mapSrc = $mapEmbed;
    } elseif (!preg_match('~[<>]~', $mapEmbed)) {
        // 3-4) Plus Code veya adres metni → güvenli embed URL'sine çevir
        $mapSrc = 'https://www.google.com/maps?q=' . rawurlencode($mapEmbed) . '&output=embed';
    }
    // O-8 GÜVENLİK: regex'i sıkılaştır — sadece resmi google.com / google.<tld>/maps yolunu kabul et.
    // Önceki regex 'google.com.evil.tr' bypass'ına izin veriyordu (CSP frame-src tarayıcıda bloklar ama defense-in-depth).
    if ($mapSrc && !preg_match('~^https://(?:www\.)?google\.(?:com|com\.[a-z]{2,3}|[a-z]{2,3})/(?:maps|embed)~i', $mapSrc)) {
        $mapSrc = '';
    }
}

$socials = array_filter([
    'instagram' => trim((string)setting('social_instagram','')),
    'facebook'  => trim((string)setting('social_facebook','')),
    'twitter'   => trim((string)setting('social_twitter','')),
    'youtube'   => trim((string)setting('social_youtube','')),
    'linkedin'  => trim((string)setting('social_linkedin','')),
    'tiktok'    => trim((string)setting('social_tiktok','')),
]);

$phone = trim((string)setting('contact_phone','+90 555 000 00 00'));
$phoneHref = preg_replace('/\D+/', '', $phone);
$email = trim((string)setting('contact_email','info@example.com'));
$address = trim((string)setting('contact_address','İstanbul, Türkiye'));
$contactSiteName = trim((string)setting('site_name','')) ?: SITE_NAME_FALLBACK;
$hoursText = trim(implode(' ', array_filter([
    setting('hours_weekday','Pazartesi - Cuma · 09:00 - 18:00'),
    setting('hours_saturday','Cumartesi · 10:00 - 16:00'),
    setting('hours_sunday','Pazar · Kapalı'),
])));
$waEnabled = setting('whatsapp_enabled','0') === '1';
$waNumber = preg_replace('/\D+/', '', (string)setting('whatsapp_number',''));
$waMsg = trim((string)setting('whatsapp_message','Merhaba, bilgi almak istiyorum.'));
$waHref = ($waEnabled && $waNumber !== '') ? ('https://wa.me/' . $waNumber . ($waMsg !== '' ? '?text=' . rawurlencode($waMsg) : '')) : '';

include __DIR__ . '/../includes/header.php';
?>
<section class="aq-contact-hero">
  <div class="aq-container">
    <nav class="aq-breadcrumb" aria-label="Sayfa yolu">
      <a href="<?= url('home') ?>">Ana Sayfa</a>
      <span>İletişim</span>
    </nav>
    <span class="aq-contact-kicker">İletişim</span>
    <h1>Size yardımcı olmak için buradayız</h1>
    <p>Ürün seçimi, sipariş süreci, kargo durumu veya akvaryum bakım ürünleri hakkında bizimle iletişime geçebilirsiniz.</p>
    <?php if ($waHref): ?>
      <a class="aq-contact-hero-whatsapp" href="<?= e($waHref) ?>" target="_blank" rel="noopener"><i class="bi bi-whatsapp"></i> WhatsApp</a>
    <?php endif; ?>
  </div>
</section>

<section class="aq-contact-page">
  <div class="aq-container">
    <div class="aq-contact-info-grid">
      <article class="aq-contact-info-card">
        <span><i class="bi bi-geo-alt"></i></span>
        <div>
          <strong>Adres</strong>
          <p><?= nl2br(e($address)) ?></p>
        </div>
      </article>
      <article class="aq-contact-info-card">
        <span><i class="bi bi-telephone"></i></span>
        <div>
          <strong>Telefon</strong>
          <p><a href="tel:<?= e($phoneHref) ?>"><?= e($phone) ?></a></p>
        </div>
      </article>
      <article class="aq-contact-info-card">
        <span><i class="bi bi-envelope"></i></span>
        <div>
          <strong>E-posta</strong>
          <p><a href="mailto:<?= e($email) ?>"><?= e($email) ?></a></p>
        </div>
      </article>
      <article class="aq-contact-info-card">
        <span><i class="bi bi-clock"></i></span>
        <div>
          <strong>Çalışma Saatleri</strong>
          <p><?= e($hoursText) ?></p>
        </div>
      </article>
    </div>

    <div class="aq-contact-layout">
      <section class="aq-contact-form-card">
        <span class="aq-contact-kicker">Bize Yazın</span>
        <h2>İletişim Formu</h2>
        <p>Formu doldurun, ekibimiz en kısa sürede sizinle iletişime geçsin.</p>

        <?php if ($sent): ?>
          <div class="alert alert-ok" role="status">Mesajınız iletildi. En kısa sürede dönüş yapacağız.</div>
        <?php endif; ?>
        <?php if ($err): ?>
          <div class="alert alert-err" role="alert"><?= e($err) ?></div>
        <?php endif; ?>

        <form method="post" class="aq-contact-form" novalidate>
          <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
          <div class="aq-contact-form-grid">
            <label>Ad Soyad
              <input name="name" required placeholder="Adınızı ve soyadınızı yazın" value="<?= e($_POST['name'] ?? '') ?>">
            </label>
            <label>E-posta
              <input name="email" type="email" required placeholder="ornek@mail.com" value="<?= e($_POST['email'] ?? '') ?>">
            </label>
            <label>Telefon
              <input name="phone" type="tel" placeholder="05xx xxx xx xx" value="<?= e($_POST['phone'] ?? '') ?>">
            </label>
            <label>Konu
              <?php $selectedSubject = $_POST['subject'] ?? ''; ?>
              <select name="subject">
                <option value="">Konu seçiniz</option>
                <?php foreach (['Ürün Danışmanlığı','Sipariş Durumu','Kargo ve Teslimat','İade ve Değişim','Diğer'] as $subjectOption): ?>
                  <option value="<?= e($subjectOption) ?>" <?= $selectedSubject === $subjectOption ? 'selected' : '' ?>><?= e($subjectOption) ?></option>
                <?php endforeach; ?>
              </select>
            </label>
          </div>
          <label>Mesajınız
            <textarea name="message" rows="6" required placeholder="Mesajınızı yazın..."><?= e($_POST['message'] ?? '') ?></textarea>
          </label>
          <label class="aq-contact-consent">
            <input type="checkbox" name="kvkk" value="1" required <?= !empty($_POST['kvkk']) ? 'checked' : '' ?>>
            <span>Kişisel verilerimin iletişim talebimin yanıtlanması amacıyla işlenmesini kabul ediyorum.</span>
          </label>
          <button class="aq-contact-submit" type="submit">Mesajı Gönder</button>
        </form>
      </section>

      <aside class="aq-contact-support-card">
        <span><i class="bi bi-headset"></i></span>
        <h3>Hızlı Destek</h3>
        <p>Ürün seçimi veya siparişinizle ilgili hızlı destek almak için WhatsApp üzerinden bize yazabilirsiniz.</p>
        <?php if ($waHref): ?>
          <a href="<?= e($waHref) ?>" target="_blank" rel="noopener"><i class="bi bi-whatsapp"></i> WhatsApp’dan Yazın</a>
        <?php else: ?>
          <a href="tel:<?= e($phoneHref) ?>"><i class="bi bi-telephone"></i> Hemen Arayın</a>
        <?php endif; ?>
        <div class="aq-contact-perks">
          <div><i class="bi bi-shield-check"></i><strong>Güvenli Alışveriş</strong><small>SSL korumalı güvenli ödeme altyapısı.</small></div>
          <div><i class="bi bi-truck"></i><strong>Hızlı Teslimat</strong><small>Stokta olan ürünlerde hızlı kargo süreci.</small></div>
          <div><i class="bi bi-arrow-repeat"></i><strong>Kolay İade</strong><small>Memnuniyet odaklı kolay iade ve değişim.</small></div>
        </div>
      </div>
    </div>

    <?php if ($socials): ?>
      <div class="aq-contact-socials" aria-label="Sosyal medya bağlantıları">
        <strong>Bizi takip edin</strong>
        <div>
          <?php
          $socialIconMap = ['twitter' => 'twitter-x'];
          foreach ($socials as $name => $url):
            $iconName = $socialIconMap[$name] ?? $name;
          ?>
            <a href="<?= e($url) ?>" target="_blank" rel="noopener" aria-label="<?= e(ucfirst($name)) ?>"><i class="bi bi-<?= e($iconName) ?>"></i></a>
          <?php endforeach; ?>
        </div>
      </div>
    <?php endif; ?>
  </div>
</section>

<?php if ($mapSrc): ?>
<section class="aq-contact-map-section" aria-label="İşletme konumu">
  <div class="aq-container">
    <div class="aq-contact-map-card">
      <div>
        <span class="aq-contact-kicker">Konum Bilgisi</span>
        <h2><?= e($contactSiteName) ?></h2>
        <p><?= nl2br(e($address)) ?></p>
        <a href="https://www.google.com/maps/search/?api=1&query=<?= rawurlencode($address) ?>" target="_blank" rel="noopener">Haritada Aç</a>
      </div>
      <iframe src="<?= e($mapSrc) ?>" width="100%" height="420" style="border:0;display:block" allowfullscreen loading="lazy" referrerpolicy="no-referrer-when-downgrade" title="İşletme konumu haritası"></iframe>
    </div>
  </div>
</section>
<?php endif; ?>

<section class="aq-contact-faq">
  <div class="aq-container">
    <h2>Sık Sorulan İletişim Soruları</h2>
    <div class="aq-contact-faq-grid">
      <details open>
        <summary>Kargo süreci hakkında nasıl bilgi alabilirim?</summary>
        <p>Sipariş numaranızla birlikte bize form, telefon veya WhatsApp üzerinden ulaşabilirsiniz.</p>
      </details>
      <details>
        <summary>Ürün seçimi için destek veriyor musunuz?</summary>
        <p>Evet, akvaryum hacminize ve ihtiyacınıza göre doğru ürün seçimi konusunda yardımcı oluyoruz.</p>
      </details>
      <details>
        <summary>İade ve değişim talepleri nasıl yapılır?</summary>
        <p>İade ve değişim talepleriniz için iletişim formunu doldurabilir veya müşteri hizmetlerimize ulaşabilirsiniz.</p>
      </details>
    </div>
  </div>
</section>

<?php include __DIR__ . '/../includes/footer.php'; ?>
