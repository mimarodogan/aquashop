<?php
$page = 'settings'; $title = 'Sistem Ayarları';
require_once __DIR__ . '/../core/auth.php';
require_once __DIR__ . '/_save.php';

/* ── Her kategori için: kaç ayar dolu / hangi durum (ok/warn/danger) ── */
$cats = [
    'identity' => [
        'icon' => '🏪',
        'title' => 'Mağaza Kimliği',
        'desc' => 'Site adı, iletişim bilgileri, sosyal medya, çalışma saatleri',
        'href' => 'identity.php',
        'keys' => ['site_name','site_tagline','site_url','contact_email','contact_phone','contact_address','hours_weekday','social_instagram','social_facebook'],
    ],
    'commerce' => [
        'icon' => '💰',
        'title' => 'Satış & Operasyon',
        'desc' => 'Kargo ücretleri, KDV, şirket bilgileri, banka, stok uyarısı',
        'href' => 'commerce.php',
        'keys' => ['shipping_flat','shipping_free_threshold','vat_rate','company_legal_name','company_tax_no','bank_iban','low_stock_threshold'],
    ],
    'marketing' => [
        'icon' => '📣',
        'title' => 'Pazarlama & Müşteri',
        'desc' => 'Sadakat programı, WhatsApp, SEO varsayılan etiketleri',
        'href' => 'marketing.php',
        'keys' => ['loyalty_earn_rate','loyalty_redeem_rate','seo_author','seo_default_og_image','whatsapp_number'],
    ],
    'integrations' => [
        'icon' => '🔌',
        'title' => 'Entegrasyonlar',
        'desc' => 'iyzico ödeme, SMS sağlayıcı, SMTP e-posta, Anthropic AI',
        'href' => 'integrations.php',
        'keys' => ['iyz_api_key','iyz_secret_key','sms_user','sms_sender','smtp_host','smtp_user','anthropic_api_key'],
    ],
    'analytics' => [
        'icon' => '📊',
        'title' => 'Analitik & Ölçüm',
        'desc' => 'GA4, GTM, Meta Pixel, Conversion API, Microsoft Clarity',
        'href' => 'analytics.php',
        'keys' => ['ga4_measurement_id','gtm_container_id','meta_pixel_id','meta_capi_token','clarity_project_id'],
    ],
];

// Her kategori için aktiflik durumu çıkar
foreach ($cats as $k => &$c) {
    $stat = settings_count_filled($c['keys']);
    $c['stat']  = $stat;
    $c['ratio'] = $stat['total'] > 0 ? $stat['filled'] / $stat['total'] : 0;
    if ($c['ratio'] >= 0.8)       { $c['level'] = 'ok';      $c['statLabel'] = '✓ ' . $stat['filled'] . '/' . $stat['total']; }
    elseif ($c['ratio'] >= 0.4)   { $c['level'] = 'warn';    $c['statLabel'] = '◐ ' . $stat['filled'] . '/' . $stat['total']; }
    elseif ($c['ratio'] > 0)      { $c['level'] = 'warn';    $c['statLabel'] = '◐ ' . $stat['filled'] . '/' . $stat['total']; }
    else                          { $c['level'] = 'danger';  $c['statLabel'] = '○ Boş'; }
}
unset($c);

require_once __DIR__ . '/../core/header.php';
?>

<p class="muted" style="margin-bottom:24px;max-width:680px;font-size:14px;line-height:1.6">
  Yönetilecek alanı seçin. Her bölüm odaklı bir form içerir — değiştirdiğiniz alan dışında bir şey kayıp olmaz.
</p>

<div class="hub-grid">
  <?php foreach ($cats as $k => $c): ?>
    <a class="hub-card" href="<?= e($c['href']) ?>" aria-label="<?= e($c['title']) ?>">
      <div class="hc-ico"><?= $c['icon'] ?></div>
      <h3><?= e($c['title']) ?></h3>
      <p class="hc-desc"><?= e($c['desc']) ?></p>
      <span class="hc-stat <?= e($c['level']) ?>"><?= e($c['statLabel']) ?></span>
    </a>
  <?php endforeach; ?>
</div>

<div class="panel" style="margin-top:8px">
  <h3 style="font-size:16px;margin-bottom:8px">💡 İpucu</h3>
  <p class="muted" style="font-size:13px;line-height:1.6;margin:0">
    Her ayar bölümünün üst sağında <strong>aktiflik göstergesi</strong> var:
    <span class="hc-stat ok" style="font-size:11px;padding:2px 8px">✓</span> tüm önemli alanlar dolu ·
    <span class="hc-stat warn" style="font-size:11px;padding:2px 8px">◐</span> bazıları eksik ·
    <span class="hc-stat danger" style="font-size:11px;padding:2px 8px">○</span> henüz hiç girilmemiş.
    <br>Yeni bir entegrasyon eklemeden önce <strong>Entegrasyonlar</strong> kartının durumuna bakmanız önerilir.
  </p>
</div>

<?php require_once __DIR__ . '/../core/footer.php'; ?>
