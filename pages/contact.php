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
//  4) Düz adres metni (örn. "Ataevler Mh., Nilüfer, Bursa")
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

include __DIR__ . '/../includes/header.php';
?>
<section class="page-header">
  <div class="container">
    <span class="kicker">Bize Ulaşın</span>
    <h1 style="margin-top:10px">İletişim</h1>
    <div class="breadcrumb"><a href="<?= url('home') ?>">Anasayfa</a><span>/</span>İletişim</div>
  </div>
</section>

<section class="contact-section">
  <div class="container contact-grid">

    <aside class="contact-info">
      <div class="panel">
        <span class="kicker">İletişim</span>
        <h3 style="margin:8px 0 18px">Bilgilerimiz</h3>
        <ul class="info-list">
          <li>
            <span class="info-ico" aria-hidden="true">
              <?= ic('phone', '', 20) ?>
            </span>
            <div>
              <small>Telefon</small>
              <a href="tel:<?= e(preg_replace('/\s+/','',setting('contact_phone',''))) ?>"><?= e(setting('contact_phone','+90 555 000 00 00')) ?></a>
            </div>
          </li>
          <li>
            <span class="info-ico" aria-hidden="true">
              <?= ic('mail', '', 20) ?>
            </span>
            <div>
              <small>E-posta</small>
              <a href="mailto:<?= e(setting('contact_email','')) ?>"><?= e(setting('contact_email','info@example.com')) ?></a>
            </div>
          </li>
          <li>
            <span class="info-ico" aria-hidden="true">
              <?= ic('map-pin', '', 20) ?>
            </span>
            <div>
              <small>Adres</small>
              <p><?= nl2br(e(setting('contact_address','İstanbul, Türkiye'))) ?></p>
            </div>
          </li>
        </ul>
      </div>

      <div class="panel">
        <span class="kicker">Çalışma Saatleri</span>
        <h3 style="margin:8px 0 14px">Açığız</h3>
        <ul class="hours-list">
          <li><?= ic('clock', '', 16) ?><span><?= e(setting('hours_weekday','Pazartesi – Cuma · 09:00 – 18:00')) ?></span></li>
          <li><?= ic('clock', '', 16) ?><span><?= e(setting('hours_saturday','Cumartesi · 10:00 – 16:00')) ?></span></li>
          <li><?= ic('clock', '', 16) ?><span><?= e(setting('hours_sunday','Pazar · Kapalı')) ?></span></li>
        </ul>
      </div>

      <?php if ($socials): ?>
      <div class="panel">
        <span class="kicker">Sosyal Medya</span>
        <h3 style="margin:8px 0 14px">Takip Edin</h3>
        <div class="social-row">
          <?php
          $socialIconMap = ['twitter' => 'twitter-x'];
          foreach ($socials as $name => $url):
            $iconName = $socialIconMap[$name] ?? $name;
          ?>
            <a href="<?= e($url) ?>" target="_blank" rel="noopener" class="social-btn" aria-label="<?= e(ucfirst($name)) ?>">
              <?= ic($iconName, '', 20) ?>
            </a>
          <?php endforeach; ?>
        </div>
      </div>
      <?php endif; ?>
    </aside>

    <form method="post" class="panel contact-form" novalidate>
      <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
      <span class="kicker">Mesaj Gönder</span>
      <h3 style="margin:8px 0 22px">Bize Yazın</h3>

      <?php if ($sent): ?>
        <div class="alert alert-ok" role="status">Mesajınız iletildi. En kısa sürede dönüş yapacağız.</div>
      <?php endif; ?>
      <?php if ($err): ?>
        <div class="alert alert-err" role="alert"><?= e($err) ?></div>
      <?php endif; ?>

      <div class="row-2">
        <div class="field"><label>Ad Soyad <span aria-hidden="true" class="req">*</span></label><input name="name" required value="<?= e($_POST['name'] ?? '') ?>"></div>
        <div class="field"><label>E-posta <span aria-hidden="true" class="req">*</span></label><input name="email" type="email" required value="<?= e($_POST['email'] ?? '') ?>"></div>
      </div>
      <div class="row-2" style="margin-top:14px">
        <div class="field"><label>Telefon</label><input name="phone" type="tel" value="<?= e($_POST['phone'] ?? '') ?>"></div>
        <div class="field"><label>Konu</label><input name="subject" value="<?= e($_POST['subject'] ?? '') ?>"></div>
      </div>
      <div class="field" style="margin-top:14px"><label>Mesajınız <span aria-hidden="true" class="req">*</span></label><textarea name="message" rows="5" required><?= e($_POST['message'] ?? '') ?></textarea></div>

      <fieldset class="pref-field" style="margin-top:18px;border:0;padding:0">
        <legend>Size nasıl dönüş yapalım? <span aria-hidden="true" class="req">*</span></legend>
        <div class="pref-options">
          <label class="pref-option"><input type="radio" name="contact_pref" value="email" <?= (($_POST['contact_pref']??'email')==='email')?'checked':'' ?>> <span>E-Posta</span></label>
          <label class="pref-option"><input type="radio" name="contact_pref" value="sms"   <?= (($_POST['contact_pref']??'')==='sms')?'checked':'' ?>> <span>SMS</span></label>
          <label class="pref-option"><input type="radio" name="contact_pref" value="phone" <?= (($_POST['contact_pref']??'')==='phone')?'checked':'' ?>> <span>Telefon Araması</span></label>
        </div>
      </fieldset>

      <label class="kvkk-row">
        <input type="checkbox" name="kvkk" value="1" required>
        <span><a href="<?= url('page', ['slug'=>'kvkk']) ?>" target="_blank" rel="noopener">KVKK Aydınlatma Metni</a>'ni okudum ve onaylıyorum. <span aria-hidden="true" class="req">*</span></span>
      </label>

      <button class="btn btn-primary btn-block btn-lg" style="margin-top:22px">Gönder</button>
    </form>

  </div>
</section>

<?php if ($mapSrc): ?>
<section class="map-section" aria-label="İşletme konumu">
  <div class="container">
    <div class="map-wrap">
      <iframe src="<?= e($mapSrc) ?>" width="100%" height="420" style="border:0;display:block" allowfullscreen loading="lazy" referrerpolicy="no-referrer-when-downgrade" title="İşletme konumu haritası"></iframe>
    </div>
  </div>
</section>
<?php endif; ?>

<?php include __DIR__ . '/../includes/footer.php'; ?>
