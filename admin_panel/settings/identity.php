<?php
$page = 'settings'; $title = 'Mağaza Kimliği';
require_once __DIR__ . '/../core/auth.php';
require_once __DIR__ . '/_save.php';
require_once __DIR__ . '/../../includes/media.php';

/* ── POST handler ────────────────────────────────────────────── */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf_check($_POST['csrf'] ?? null)) {
    $st = db()->prepare('INSERT INTO settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)');

    // Favicon yüklemesi — tarayıcı sekmesinde ~16-32px gösterilir, 256x256 PWA için yeterli
    if (!empty($_FILES['favicon_file']['name'])) {
        $r = media_upload_from_files($_FILES['favicon_file'], array('max_width'=>256,'max_height'=>256,'quality'=>90));
        if ($r['ok']) {
            $st->execute(['favicon_path', $r['path']]);
        } else {
            flash_set('err', 'Favicon yüklenemedi: ' . ($r['error'] ?? 'bilinmeyen hata'));
        }
    } elseif (!empty($_POST['favicon_remove'])) {
        $st->execute(['favicon_path', '']);
    }

    settings_save_fields(
        [
            'site_name','site_tagline','site_url',
            'contact_email','contact_phone','contact_address',
            'topbar_message',
            'contact_map_embed',
            'hours_weekday','hours_saturday','hours_sunday',
            'social_instagram','social_facebook','social_twitter',
            'social_youtube','social_linkedin','social_tiktok',
            // Anasayfa görünümü
            'footer_about',
            'home_promo1_title','home_promo1_text','home_promo1_link',
            'home_promo2_title','home_promo2_text','home_promo2_link',
            'home_instagram_user','home_instagram_title','home_instagram_images',
            // Google Yorumları
            'google_reviews_rating','google_reviews_count','google_reviews_maps_url','google_reviews_json',
        ],
        ['topbar_enabled','home_instagram_enabled','google_reviews_enabled'],
        [],
        'identity.php'
    );
}

require_once __DIR__ . '/../core/header.php';
?>

<?php settings_sub_header(
    'Mağaza Kimliği',
    'Sitenizin temel kimlik bilgileri — müşterilere ve arama motorlarına nasıl göründüğü.'
); ?>

<form method="post" enctype="multipart/form-data" style="display:grid;gap:24px;max-width:880px">
  <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">

  <div class="panel">
    <h3>Genel</h3>
    <div style="display:grid;gap:18px">
      <div class="row-2">
        <div class="field"><label>Site Adı</label><input name="site_name" value="<?= e(setting('site_name','')) ?>"></div>
        <div class="field"><label>Slogan / Üst Etiket</label><input name="site_tagline" value="<?= e(setting('site_tagline','')) ?>"></div>
      </div>
      <div class="field">
        <label>Site URL (tam adres)</label>
        <input name="site_url" type="url" value="<?= e(setting('site_url','')) ?>" placeholder="https://siteniz.com">
        <small class="muted">Mail, sitemap ve canonical etiketleri için kullanılır. Sonunda <strong>/</strong> olmadan yazın.</small>
      </div>

      <div class="field">
        <label>Favicon (tarayıcı sekme simgesi)</label>
        <?php $fav = setting('favicon_path',''); if ($fav): ?>
          <div style="display:flex;align-items:center;gap:14px;margin-bottom:8px;padding:10px;background:var(--cream);border-radius:var(--radius);border:1px solid var(--gold-border)">
            <img src="<?= e($fav) ?>" alt="Favicon" style="width:48px;height:48px;border-radius:6px;border:1px solid var(--gold-border);background:#fff;padding:4px">
            <div style="flex:1">
              <div style="font-size:13px;color:var(--ink);font-weight:500">Mevcut favicon</div>
              <div style="font-size:11px;color:var(--muted-text);font-family:monospace;word-break:break-all"><?= e($fav) ?></div>
            </div>
            <label style="display:inline-flex;align-items:center;gap:6px;font-size:12px;cursor:pointer">
              <input type="checkbox" name="favicon_remove" value="1"> Kaldır
            </label>
          </div>
        <?php endif; ?>
        <input type="file" name="favicon_file" accept="image/png,image/jpeg,image/x-icon,image/svg+xml,image/webp">
        <small class="muted">Önerilen kare PNG/SVG (örn. 512×512). Otomatik olarak WebP'ye dönüştürülür.</small>
      </div>

      <h4 style="font-family:'Inter',sans-serif;font-size:12px;letter-spacing:.22em;text-transform:uppercase;color:var(--muted-text);margin-top:6px">Üst Bilgi Bandı</h4>
      <div class="field"><label>Mesaj</label><input name="topbar_message" value="<?= e(setting('topbar_message','')) ?>" placeholder="Örn: Yurt Geneli Ücretsiz Kargo · Güvenli Ödeme · 14 Gün İade"></div>
      <label style="display:flex;gap:10px;align-items:center"><input type="checkbox" name="topbar_enabled" value="1" <?= setting('topbar_enabled','1')==='1'?'checked':'' ?>> Topbar'ı göster</label>
    </div>
  </div>

  <div class="panel">
    <h3>İletişim Bilgileri</h3>
    <div style="display:grid;gap:18px">
      <div class="row-2">
        <div class="field"><label>E-posta</label><input name="contact_email" value="<?= e(setting('contact_email','')) ?>"></div>
        <div class="field"><label>Telefon</label><input name="contact_phone" value="<?= e(setting('contact_phone','')) ?>"></div>
      </div>
      <div class="field"><label>Adres</label><textarea name="contact_address" rows="2"><?= e(setting('contact_address','')) ?></textarea></div>
    </div>
  </div>

  <div class="panel">
    <h3>İşletme Konumu (Harita)</h3>
    <div class="field">
      <label>Google Maps Konumu</label>
      <textarea name="contact_map_embed" rows="3" placeholder="6X95+9P Nilüfer, Bursa  —  veya iframe HTML / embed URL"><?= e(setting('contact_map_embed','')) ?></textarea>
      <small class="muted">
        Dört biçim de kabul edilir:<br>
        <strong>1) En kolay (önerilen):</strong> <code>Plus Code</code> veya açık adres yazın — örn. <code>6X95+9P Nilüfer, Bursa</code>. Otomatik haritalanır, sunucu güvenlik filtresine (WAF) takılmaz.<br>
        <strong>2) URL:</strong> Google Maps embed URL'i — <code>https://www.google.com/maps/embed?pb=...</code><br>
        <strong>3) iframe HTML:</strong> Google Maps → <strong>Paylaş</strong> → <strong>Harita yerleştir</strong> → tüm kodu yapıştırın.<br>
        Yalnızca <code>google.*</code> domain'leri kabul edilir (güvenlik için).
      </small>
    </div>
  </div>

  <div class="panel">
    <h3>Çalışma Saatleri</h3>
    <div style="display:grid;gap:14px">
      <div class="field"><label>Hafta İçi</label><input name="hours_weekday" value="<?= e(setting('hours_weekday','Pazartesi – Cuma · 09:00 – 18:00')) ?>"></div>
      <div class="field"><label>Cumartesi</label><input name="hours_saturday" value="<?= e(setting('hours_saturday','Cumartesi · 10:00 – 16:00')) ?>"></div>
      <div class="field"><label>Pazar</label><input name="hours_sunday" value="<?= e(setting('hours_sunday','Pazar · Kapalı')) ?>"></div>
    </div>
  </div>

  <div class="panel">
    <h3>Sosyal Medya</h3>
    <small class="muted" style="display:block;margin:-6px 0 14px">Tam URL girin (örn. https://instagram.com/markaniz). Boş bırakılan satır gösterilmez.</small>
    <div style="display:grid;gap:14px">
      <div class="row-2">
        <div class="field"><label>Instagram</label><input name="social_instagram" type="url" value="<?= e(setting('social_instagram','')) ?>" placeholder="https://instagram.com/..."></div>
        <div class="field"><label>Facebook</label><input name="social_facebook" type="url" value="<?= e(setting('social_facebook','')) ?>" placeholder="https://facebook.com/..."></div>
      </div>
      <div class="row-2">
        <div class="field"><label>X / Twitter</label><input name="social_twitter" type="url" value="<?= e(setting('social_twitter','')) ?>" placeholder="https://x.com/..."></div>
        <div class="field"><label>YouTube</label><input name="social_youtube" type="url" value="<?= e(setting('social_youtube','')) ?>" placeholder="https://youtube.com/@..."></div>
      </div>
      <div class="row-2">
        <div class="field"><label>LinkedIn</label><input name="social_linkedin" type="url" value="<?= e(setting('social_linkedin','')) ?>" placeholder="https://linkedin.com/company/..."></div>
        <div class="field"><label>TikTok</label><input name="social_tiktok" type="url" value="<?= e(setting('social_tiktok','')) ?>" placeholder="https://tiktok.com/@..."></div>
      </div>
    </div>
  </div>

  <div class="panel">
    <h3>Anasayfa Görünümü</h3>
    <small class="muted" style="display:block;margin:-6px 0 16px">Anasayfadaki promo kartları, Instagram şeridi ve footer açıklaması. Boş bırakılan bölümler sitede gösterilmez.</small>
    <div style="display:grid;gap:18px">

      <div class="field">
        <label>Footer Açıklaması</label>
        <textarea name="footer_about" rows="2" placeholder="Boş bırakılırsa slogan kullanılır."><?= e(setting('footer_about','')) ?></textarea>
        <small class="muted">Footer'da marka adının altında görünen kısa tanıtım metni.</small>
      </div>

      <h4 style="font-family:'Inter',sans-serif;font-size:12px;letter-spacing:.22em;text-transform:uppercase;color:var(--muted-text);margin-top:6px">Promo Kartları (hero altı)</h4>
      <small class="muted" style="margin:-10px 0 0">Başlık girilen kart gösterilir. İki kart yan yana çıkar.</small>
      <?php for ($pi = 1; $pi <= 2; $pi++): ?>
      <div style="border:1px solid var(--gold-border);border-radius:var(--radius);padding:16px;display:grid;gap:12px">
        <strong style="font-size:13px;color:var(--ink)">Kart <?= $pi ?></strong>
        <div class="row-2">
          <div class="field"><label>Başlık</label><input name="home_promo<?= $pi ?>_title" value="<?= e(setting("home_promo{$pi}_title",'')) ?>" placeholder="<?= $pi===1?'Örn: LED Aydınlatma':'Örn: Dış Filtreler' ?>"></div>
          <div class="field"><label>Bağlantı (URL)</label><input name="home_promo<?= $pi ?>_link" value="<?= e(setting("home_promo{$pi}_link",'')) ?>" placeholder="/kategori/aydinlatma"></div>
        </div>
        <div class="field"><label>Alt Metin</label><input name="home_promo<?= $pi ?>_text" value="<?= e(setting("home_promo{$pi}_text",'')) ?>" placeholder="Kısa açıklama (opsiyonel)"></div>
      </div>
      <?php endfor; ?>

      <h4 style="font-family:'Inter',sans-serif;font-size:12px;letter-spacing:.22em;text-transform:uppercase;color:var(--muted-text);margin-top:6px">Instagram Şeridi</h4>
      <label style="display:flex;gap:10px;align-items:center"><input type="checkbox" name="home_instagram_enabled" value="1" <?= setting('home_instagram_enabled','0')==='1'?'checked':'' ?>> Instagram bölümünü göster</label>
      <small class="muted" style="margin:-10px 0 0">Görünmesi için "Sosyal Medya → Instagram" adresinin dolu olması gerekir.</small>
      <div class="row-2">
        <div class="field"><label>Kullanıcı Adı (@)</label><input name="home_instagram_user" value="<?= e(setting('home_instagram_user','')) ?>" placeholder="aquashopbursa"></div>
        <div class="field"><label>Başlık</label><input name="home_instagram_title" value="<?= e(setting('home_instagram_title','')) ?>" placeholder="Bizi Instagram'da Takip Edin"></div>
      </div>
      <div class="field">
        <label>Görsel URL'leri (her satıra bir tane)</label>
        <textarea name="home_instagram_images" rows="4" placeholder="https://...&#10;https://..."><?= e(setting('home_instagram_images','')) ?></textarea>
        <small class="muted">Boş bırakılırsa dekoratif aqua karoları gösterilir; hepsi Instagram profilinize bağlanır.</small>
      </div>

      <h4 style="font-family:'Inter',sans-serif;font-size:12px;letter-spacing:.22em;text-transform:uppercase;color:var(--muted-text);margin-top:6px">Google Yorumları</h4>
      <label style="display:flex;gap:10px;align-items:center"><input type="checkbox" name="google_reviews_enabled" value="1" <?= setting('google_reviews_enabled','1')==='1'?'checked':'' ?>> Google Yorumları bölümünü göster</label>
      <div class="row-3" style="display:grid;grid-template-columns:repeat(3,1fr);gap:18px">
        <div class="field"><label>Ortalama Puan</label><input name="google_reviews_rating" type="number" step="0.1" min="1" max="5" value="<?= e(setting('google_reviews_rating','4.8')) ?>"></div>
        <div class="field"><label>Toplam Yorum Sayısı</label><input name="google_reviews_count" type="number" value="<?= e(setting('google_reviews_count','142')) ?>"></div>
        <div class="field"><label>Harita / Yorum URL</label><input name="google_reviews_maps_url" type="url" value="<?= e(setting('google_reviews_maps_url','https://maps.google.com')) ?>" placeholder="https://g.page/..."></div>
      </div>
      <div class="field">
        <label>Yorumlar (JSON formatında)</label>
        <textarea name="google_reviews_json" rows="6" placeholder='[
  {
    "author": "Ahmet Yılmaz",
    "rating": 5,
    "text": "Harika bir mağaza!",
    "time": "1 hafta önce",
    "avatar": ""
  }
]'><?= e(setting('google_reviews_json','')) ?></textarea>
        <small class="muted">Kendi Google yorumlarınızı JSON formatında girin. Boş bırakırsanız varsayılan yorumlar gösterilir.</small>
      </div>
    </div>
  </div>

  <div><button class="btn btn-primary">Kaydet</button></div>
</form>

<?php require_once __DIR__ . '/../core/footer.php'; ?>
