<?php
$page = 'settings'; $title = 'Analitik & Ölçüm';
require_once __DIR__ . '/../core/auth.php';
require_once __DIR__ . '/_save.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    settings_save_fields(
        [
            'ga4_measurement_id','gtm_container_id',
            'meta_pixel_id','meta_capi_token','meta_capi_test_event_code',
            'clarity_project_id',
        ],
        ['analytics_enabled','meta_capi_enabled'],
        [],
        'analytics.php'
    );
}

require_once __DIR__ . '/../core/header.php';
?>

<?php settings_sub_header(
    'Analitik & Ölçüm',
    'GA4, Google Tag Manager, Meta Pixel + Conversion API, Microsoft Clarity. Çerez onayı sonrası yüklenir (KVKK uyumlu).'
); ?>

<form method="post" style="display:grid;gap:24px;max-width:880px">
  <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">

  <div class="panel">
    <h3>📊 Master Switch</h3>
    <small class="muted" style="display:block;margin:-6px 0 14px">
      Bu işaret kapalıysa hiçbir tracker yüklenmez (geliştirme/test için). Açık olsa bile boş bırakılan tracker'lar yüklenmez.
    </small>
    <label style="display:flex;gap:10px;align-items:center">
      <input type="checkbox" name="analytics_enabled" value="1" <?= setting('analytics_enabled','0')==='1'?'checked':'' ?>>
      Analitik &amp; izleme kodlarını site genelinde aktifleştir
    </label>
  </div>

  <div class="panel">
    <h3>Google Analytics 4 &amp; Tag Manager</h3>
    <small class="muted" style="display:block;margin:-6px 0 14px">
      GA4 zorunlu, GTM opsiyonel (ileride çoklu tracker yönetimi kolaylaşır).
    </small>
    <div style="display:grid;gap:14px">
      <div class="row-2">
        <div class="field">
          <label>GA4 Measurement ID</label>
          <input name="ga4_measurement_id" value="<?= e(setting('ga4_measurement_id','')) ?>" placeholder="G-XXXXXXXXXX" autocomplete="off" spellcheck="false">
          <small class="muted">Admin → Veri akışları → Web. Format: <code>G-</code> ile başlayan 10-12 karakter.</small>
        </div>
        <div class="field">
          <label>GTM Container ID (opsiyonel)</label>
          <input name="gtm_container_id" value="<?= e(setting('gtm_container_id','')) ?>" placeholder="GTM-XXXXXXX" autocomplete="off" spellcheck="false">
          <small class="muted">GTM kullanıyorsanız GA4'ü de buradan yönetebilirsiniz.</small>
        </div>
      </div>
    </div>
  </div>

  <div class="panel">
    <h3>📘 Meta (Facebook / Instagram) Pixel</h3>
    <small class="muted" style="display:block;margin:-6px 0 14px">
      Pixel ID zorunlu. Conversion API ile iOS14+ kayıplarını sunucudan tamamlarsınız.
    </small>
    <div style="display:grid;gap:14px">
      <div class="row-2">
        <div class="field">
          <label>Pixel ID</label>
          <input name="meta_pixel_id" value="<?= e(setting('meta_pixel_id','')) ?>" placeholder="1234567890123456" autocomplete="off" spellcheck="false">
          <small class="muted">Meta Events Manager → Veri kaynakları → Pixel. 15-16 haneli sayı.</small>
        </div>
        <div class="field">
          <label>Conversion API — Access Token</label>
          <input type="password" name="meta_capi_token" value="<?= e(setting('meta_capi_token','')) ?>" placeholder="EAA..." autocomplete="off" spellcheck="false">
          <small class="muted">Events Manager → Ayarlar → Conversions API → Token oluştur.</small>
        </div>
      </div>
      <div class="row-2">
        <div class="field">
          <label>CAPI Test Event Code (opsiyonel)</label>
          <input name="meta_capi_test_event_code" value="<?= e(setting('meta_capi_test_event_code','')) ?>" placeholder="TEST12345" autocomplete="off">
          <small class="muted">Test events sekmesinden alın. Sadece test sırasında kullanın, sonra silin.</small>
        </div>
        <div class="field" style="display:flex;align-items:flex-end">
          <label style="display:flex;gap:10px;align-items:center;margin:0 0 10px">
            <input type="checkbox" name="meta_capi_enabled" value="1" <?= setting('meta_capi_enabled','0')==='1'?'checked':'' ?>>
            CAPI server-side gönderim aktif
          </label>
        </div>
      </div>
    </div>
  </div>

  <div class="panel">
    <h3>🔥 Microsoft Clarity</h3>
    <small class="muted" style="display:block;margin:-6px 0 14px">
      Heatmap + session recording. Bedava, sınırsız, hızlı kurulum.
    </small>
    <div class="field" style="max-width:480px">
      <label>Clarity Project ID</label>
      <input name="clarity_project_id" value="<?= e(setting('clarity_project_id','')) ?>" placeholder="abcdef1234" autocomplete="off" spellcheck="false">
      <small class="muted">
        <a href="https://clarity.microsoft.com" target="_blank" rel="noopener" style="color:var(--leaf);text-decoration:underline">clarity.microsoft.com</a> → Settings → Setup → Tracking code.
      </small>
    </div>
  </div>

  <div><button class="btn btn-primary">Kaydet</button></div>
</form>

<?php require_once __DIR__ . '/../core/footer.php'; ?>
