<?php
$page = 'settings'; $title = 'Pazarlama & Müşteri';
require_once __DIR__ . '/../core/auth.php';
require_once __DIR__ . '/_save.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    settings_save_fields(
        [
            // Sadakat
            'loyalty_earn_rate','loyalty_redeem_rate','loyalty_min_redeem','loyalty_expire_months',
            'loyalty_tier_loyal_min','loyalty_tier_vip_min',
            // WhatsApp
            'whatsapp_number','whatsapp_message',
            // AI Danışman
            'ai_assistant_name','ai_assistant_category','ai_assistant_greeting','ai_assistant_model',
            // SEO Defaults
            'seo_author','seo_publisher','seo_robots','seo_twitter_handle','seo_default_og_image',
            // Faz 6/7 — dönüşüm özellikleri
            'gift_wrap_price','reservation_minutes','price_drop_min_percent',
        ],
        [
            'loyalty_enabled','whatsapp_enabled','ai_assistant_enabled',
            // Faz 6/7 toggle'ları
            'compare_enabled','cart_modal_enabled','recently_viewed_enabled','bestsellers_enabled',
            'saved_items_enabled','gift_wrap_enabled','reservation_enabled',
            'price_drop_alerts_enabled','qna_enabled','review_media_enabled',
        ],
        [],
        'marketing.php'
    );
}

require_once __DIR__ . '/../core/header.php';
?>

<?php settings_sub_header(
    'Pazarlama & Müşteri',
    'Sadakat programı, WhatsApp hızlı iletişim, SEO varsayılan etiketleri.'
); ?>

<form method="post" style="display:grid;gap:24px;max-width:880px">
  <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">

  <div class="panel">
    <h3>🏆 Sadakat Programı (Puan)</h3>
    <small class="muted" style="display:block;margin:-6px 0 14px">
      Müşteriler her siparişten puan kazanır, checkout'ta puan kullanarak indirim alır. Teslim olunca puan otomatik verilir, iade'de geri alınır.
    </small>
    <div style="display:grid;gap:14px">
      <label style="display:flex;gap:10px;align-items:center">
        <input type="checkbox" name="loyalty_enabled" value="1" <?= setting('loyalty_enabled','0')==='1'?'checked':'' ?>>
        Sadakat programını aktifleştir
      </label>
      <div class="row-2">
        <div class="field">
          <label>Kazanma Oranı (kaç ₺'ye 1 puan)</label>
          <input name="loyalty_earn_rate" type="number" step="0.01" min="0.01" value="<?= e(setting('loyalty_earn_rate','1')) ?>" placeholder="1">
          <small class="muted">1 = 1 TL harcamaya 1 puan. 5 = 5 TL'ye 1 puan.</small>
        </div>
        <div class="field">
          <label>Kullanma Değeri (1 puan = kaç ₺)</label>
          <input name="loyalty_redeem_rate" type="number" step="0.01" min="0.01" value="<?= e(setting('loyalty_redeem_rate','0.10')) ?>" placeholder="0.10">
          <small class="muted">0.10 = 10 puan = 1 TL indirim.</small>
        </div>
      </div>
      <div class="row-2">
        <div class="field">
          <label>Min. Kullanılabilir Puan</label>
          <input name="loyalty_min_redeem" type="number" min="1" value="<?= e(setting('loyalty_min_redeem','100')) ?>" placeholder="100">
          <small class="muted">Bu altındaki bakiye checkout'ta kullanılamaz.</small>
        </div>
        <div class="field">
          <label>Puan Geçerlilik Süresi (ay)</label>
          <input name="loyalty_expire_months" type="number" min="1" max="60" value="<?= e(setting('loyalty_expire_months','12')) ?>" placeholder="12">
          <small class="muted">Kazanma tarihinden N ay sonra puan expire olur.</small>
        </div>
      </div>

      <h4 style="font-family:'Inter',sans-serif;font-size:11px;letter-spacing:.22em;text-transform:uppercase;color:var(--muted-text);margin:6px 0 0">Seviye Eşikleri (12 Aylık Harcama)</h4>
      <div class="row-2">
        <div class="field">
          <label>Sadık Müşteri Eşiği (₺)</label>
          <input name="loyalty_tier_loyal_min" type="number" min="0" value="<?= e(setting('loyalty_tier_loyal_min','2000')) ?>" placeholder="2000">
        </div>
        <div class="field">
          <label>VIP Müşteri Eşiği (₺)</label>
          <input name="loyalty_tier_vip_min" type="number" min="0" value="<?= e(setting('loyalty_tier_vip_min','10000')) ?>" placeholder="10000">
        </div>
      </div>
    </div>
  </div>

  <div class="panel">
    <h3>💬 WhatsApp Hızlı İletişim</h3>
    <small class="muted" style="display:block;margin:-6px 0 14px">
      Sitenin sağ alt köşesinde yüzen yeşil WhatsApp butonu. Tıklayan müşteri WhatsApp Web/App'te hazır mesajla sohbete başlar.
    </small>
    <div style="display:grid;gap:14px">
      <label style="display:flex;gap:10px;align-items:center">
        <input type="checkbox" name="whatsapp_enabled" value="1" <?= setting('whatsapp_enabled','0')==='1'?'checked':'' ?>>
        Yüzen WhatsApp butonunu site genelinde göster
      </label>
      <div class="row-2">
        <div class="field">
          <label>WhatsApp Numarası</label>
          <input name="whatsapp_number" value="<?= e(setting('whatsapp_number','')) ?>" placeholder="905551234567">
          <small class="muted">Ülke kodu ile birlikte, başında <strong>+</strong> ve boşluk olmadan.</small>
        </div>
        <div class="field">
          <label>Hazır Mesaj</label>
          <input name="whatsapp_message" value="<?= e(setting('whatsapp_message','Merhaba, bilgi almak istiyorum.')) ?>">
        </div>
      </div>
    </div>
  </div>

  <div class="panel">
    <h3>🤖 AI Danışman (Sohbet)</h3>
    <small class="muted" style="display:block;margin:-6px 0 14px">
      Site genelinde sağ alt köşede yüzen yapay zeka sohbet asistanı. Sitedeki gerçek ürünleri linkleyerek önerir ve genel kullanım/bakım sorularını yanıtlar.
      <strong>Açıkken WhatsApp balonunun yerini alır</strong> — WhatsApp, sohbet içinde "insana bağlan" linki olarak korunur.
    </small>
    <?php if (trim((string)setting('anthropic_api_key','')) === ''): ?>
      <div style="background:rgba(154,42,42,.1);border:1px solid #9A2A2A44;color:#9A2A2A;padding:10px 13px;border-radius:8px;margin-bottom:14px;font-size:13px">
        ⚠️ Çalışması için Anthropic API anahtarı gerekli.
        <a href="<?= SITE_URL ?>/admin_panel/settings/integrations.php" style="color:#9A2A2A;text-decoration:underline;font-weight:600">Entegrasyonlar → Yapay Zeka</a> bölümünden ekleyin.
      </div>
    <?php endif; ?>
    <div style="display:grid;gap:14px">
      <label style="display:flex;gap:10px;align-items:center">
        <input type="checkbox" name="ai_assistant_enabled" value="1" <?= setting('ai_assistant_enabled','0')==='1'?'checked':'' ?>>
        AI Danışmanı site genelinde göster
      </label>
      <div class="row-2">
        <div class="field">
          <label>Asistan Adı</label>
          <input name="ai_assistant_name" value="<?= e(setting('ai_assistant_name','')) ?>" placeholder="<?= e(trim((string)setting('site_name','Mağaza')) . ' Danışmanı') ?>">
          <small class="muted">Boş bırakılırsa mağaza adından türetilir.</small>
        </div>
        <div class="field">
          <label>Uzmanlık Alanı</label>
          <input name="ai_assistant_category" value="<?= e(setting('ai_assistant_category','')) ?>" placeholder="akvaryum ve evcil hayvan ürünleri">
          <small class="muted">Asistanın hangi konuda uzman gibi davranacağı.</small>
        </div>
      </div>
      <div class="field">
        <label>Karşılama Mesajı</label>
        <input name="ai_assistant_greeting" value="<?= e(setting('ai_assistant_greeting','')) ?>" placeholder="Merhaba! 👋 Ürün önerisi veya bakım sorularınızda yardımcı olabilirim. Ne aramıştınız?">
      </div>
      <div class="field" style="max-width:380px">
        <label>Yapay Zeka Modeli</label>
        <select name="ai_assistant_model">
          <?php $am = (string)setting('ai_assistant_model','auto'); ?>
          <option value="auto"   <?= $am==='auto'  ?'selected':'' ?>>Otomatik (hibrit) — basit soru hızlı, karmaşık soru güçlü model (önerilen)</option>
          <option value="haiku"  <?= $am==='haiku' ?'selected':'' ?>>Haiku — en hızlı ve en ucuz</option>
          <option value="sonnet" <?= $am==='sonnet'?'selected':'' ?>>Sonnet — en nitelikli (daha pahalı)</option>
        </select>
      </div>
      <small class="muted">
        Sohbetler analiz ve kötüye kullanım önlemi için kaydedilir (KVKK uyumlu — ham IP saklanmaz).
        Bunun için <code>sql/migrate_ai_assistant.sql</code> migration'ını bir kez çalıştırın.
      </small>
    </div>
  </div>

  <div class="panel">
    <h3>🔎 SEO · Varsayılan Etiketler</h3>
    <small class="muted" style="display:block;margin:-6px 0 14px">
      Bu alanlar tüm sayfalarda <code>&lt;meta&gt;</code> etiketleri olarak otomatik basılır.
      Sayfa-spesifik değerler için <a href="<?= SITE_URL ?>/admin_panel/seo_manager.php" style="color:var(--leaf);text-decoration:underline">SEO Yönetimi</a>'ne bakın.
    </small>
    <div style="display:grid;gap:14px">
      <div class="row-2">
        <div class="field">
          <label>Yazar (Meta Author)</label>
          <input name="seo_author" value="<?= e(setting('seo_author','')) ?>" placeholder="Site Adınız">
        </div>
        <div class="field">
          <label>Yayıncı (Meta Publisher)</label>
          <input name="seo_publisher" value="<?= e(setting('seo_publisher','')) ?>" placeholder="Şirket Ünvanınız Ltd.">
        </div>
      </div>
      <div class="row-2">
        <div class="field">
          <label>Varsayılan Robots Etiketi</label>
          <select name="seo_robots">
            <?php $sr = (string)setting('seo_robots','index, follow'); ?>
            <option value="index, follow" <?= $sr==='index, follow'?'selected':'' ?>>index, follow — Tara ve indeksle (önerilen)</option>
            <option value="noindex, follow" <?= $sr==='noindex, follow'?'selected':'' ?>>noindex, follow — İndeksleme, linkleri takip et</option>
            <option value="index, nofollow" <?= $sr==='index, nofollow'?'selected':'' ?>>index, nofollow — İndeksle, linkleri takip etme</option>
            <option value="noindex, nofollow" <?= $sr==='noindex, nofollow'?'selected':'' ?>>noindex, nofollow — Tamamen gizle (staging için)</option>
          </select>
        </div>
        <div class="field">
          <label>Twitter / X Kullanıcı Adı</label>
          <input name="seo_twitter_handle" value="<?= e(setting('seo_twitter_handle','')) ?>" placeholder="@kullaniciadi">
        </div>
      </div>
      <div class="field">
        <label>Varsayılan Sosyal Görseli (OG / Twitter Card)</label>
        <input name="seo_default_og_image" value="<?= e(setting('seo_default_og_image','')) ?>" placeholder="/uploads/og-default.webp">
        <small class="muted">Sayfa kendi görselini belirtmediğinde sosyal medya paylaşımlarında kullanılır. Önerilen 1200×630 px.</small>
      </div>
    </div>
  </div>

  <div class="panel">
    <h3>✨ Dönüşüm &amp; Etkileşim Özellikleri</h3>
    <small class="muted" style="display:block;margin:-6px 0 14px">
      Her bir özelliği ayrı ayrı açıp kapatabilirsin. Hepsi varsayılan olarak kapalıdır — site sade kalır, ihtiyacın olanı aç.
    </small>
    <div style="display:grid;gap:12px">

      <div style="border:1px solid var(--gold-border);border-radius:8px;padding:14px;background:var(--cream)">
        <label style="display:flex;gap:10px;align-items:flex-start">
          <input type="checkbox" name="compare_enabled" value="1" <?= setting('compare_enabled','0')==='1'?'checked':'' ?> style="margin-top:2px">
          <div style="flex:1">
            <strong style="display:block;font-size:14px;color:var(--ink)">🔍 Ürün Karşılaştırma</strong>
            <small class="muted" style="font-size:12px">Müşteri 3 ürüne kadar yan yana karşılaştırabilir. Ürün kartlarında "+ Karşılaştır" butonu görünür.</small>
          </div>
        </label>
      </div>

      <div style="border:1px solid var(--gold-border);border-radius:8px;padding:14px;background:var(--cream)">
        <label style="display:flex;gap:10px;align-items:flex-start">
          <input type="checkbox" name="cart_modal_enabled" value="1" <?= setting('cart_modal_enabled','1')==='1'?'checked':'' ?> style="margin-top:2px">
          <div style="flex:1">
            <strong style="display:block;font-size:14px;color:var(--ink)">🛒 Sepete Eklendi Mini Modal</strong>
            <small class="muted" style="font-size:12px">Sayfa değiştirmeden onay popup'ı; "Sepete git" veya "Alışverişe devam" seçenekleri.</small>
          </div>
        </label>
      </div>

      <div style="border:1px solid var(--gold-border);border-radius:8px;padding:14px;background:var(--cream)">
        <label style="display:flex;gap:10px;align-items:flex-start">
          <input type="checkbox" name="recently_viewed_enabled" value="1" <?= setting('recently_viewed_enabled','1')==='1'?'checked':'' ?> style="margin-top:2px">
          <div style="flex:1">
            <strong style="display:block;font-size:14px;color:var(--ink)">👀 Son Baktıkların</strong>
            <small class="muted" style="font-size:12px">PDP altında + anasayfada "Geçmişte incelediğin ürünler" şeridi. product_views verisi.</small>
          </div>
        </label>
      </div>

      <div style="border:1px solid var(--gold-border);border-radius:8px;padding:14px;background:var(--cream)">
        <label style="display:flex;gap:10px;align-items:flex-start">
          <input type="checkbox" name="bestsellers_enabled" value="1" <?= setting('bestsellers_enabled','1')==='1'?'checked':'' ?> style="margin-top:2px">
          <div style="flex:1">
            <strong style="display:block;font-size:14px;color:var(--ink)">🏆 Çok Satanlar Widget'ı</strong>
            <small class="muted" style="font-size:12px">Anasayfada son 30 günün en çok satan 8 ürünü.</small>
          </div>
        </label>
      </div>

      <div style="border:1px solid var(--gold-border);border-radius:8px;padding:14px;background:var(--cream)">
        <label style="display:flex;gap:10px;align-items:flex-start">
          <input type="checkbox" name="saved_items_enabled" value="1" <?= setting('saved_items_enabled','1')==='1'?'checked':'' ?> style="margin-top:2px">
          <div style="flex:1">
            <strong style="display:block;font-size:14px;color:var(--ink)">📋 "Sonra Al" Listesi</strong>
            <small class="muted" style="font-size:12px">Sepetten "Sonra al"a taşıma. Hesap sayfasında listesi görünür. Sadece giriş yapmış kullanıcılar.</small>
          </div>
        </label>
      </div>

      <div style="border:1px solid var(--gold-border);border-radius:8px;padding:14px;background:var(--cream)">
        <label style="display:flex;gap:10px;align-items:flex-start;margin-bottom:10px">
          <input type="checkbox" name="gift_wrap_enabled" value="1" <?= setting('gift_wrap_enabled','0')==='1'?'checked':'' ?> style="margin-top:2px">
          <div style="flex:1">
            <strong style="display:block;font-size:14px;color:var(--ink)">🎁 Hediye Paketi</strong>
            <small class="muted" style="font-size:12px">Checkout'ta opsiyonel hediye paketi seçeneği + not alanı.</small>
          </div>
        </label>
        <div class="field" style="max-width:220px;margin-left:30px">
          <label>Hediye Paketi Ücreti (₺)</label>
          <input name="gift_wrap_price" type="number" step="0.01" min="0" value="<?= e(setting('gift_wrap_price','25')) ?>">
        </div>
      </div>

      <div style="border:1px solid var(--gold-border);border-radius:8px;padding:14px;background:var(--cream)">
        <label style="display:flex;gap:10px;align-items:flex-start;margin-bottom:10px">
          <input type="checkbox" name="reservation_enabled" value="1" <?= setting('reservation_enabled','0')==='1'?'checked':'' ?> style="margin-top:2px">
          <div style="flex:1">
            <strong style="display:block;font-size:14px;color:var(--ink)">⏳ Stok Rezervasyonu</strong>
            <small class="muted" style="font-size:12px">Sepete eklenen ürünü X dakika için tut. Süre dolunca cron iade eder.</small>
          </div>
        </label>
        <div class="field" style="max-width:220px;margin-left:30px">
          <label>Rezervasyon Süresi (dakika)</label>
          <input name="reservation_minutes" type="number" min="1" max="60" value="<?= e(setting('reservation_minutes','15')) ?>">
        </div>
      </div>

      <div style="border:1px solid var(--gold-border);border-radius:8px;padding:14px;background:var(--cream)">
        <label style="display:flex;gap:10px;align-items:flex-start;margin-bottom:10px">
          <input type="checkbox" name="price_drop_alerts_enabled" value="1" <?= setting('price_drop_alerts_enabled','0')==='1'?'checked':'' ?> style="margin-top:2px">
          <div style="flex:1">
            <strong style="display:block;font-size:14px;color:var(--ink)">💰 Fiyat Düştü Uyarısı</strong>
            <small class="muted" style="font-size:12px">Favori ürünün fiyatı düştüğünde kullanıcıya email. Günlük cron çalışır.</small>
          </div>
        </label>
        <div class="field" style="max-width:220px;margin-left:30px">
          <label>Min. Düşüş Yüzdesi (%)</label>
          <input name="price_drop_min_percent" type="number" step="0.5" min="1" max="50" value="<?= e(setting('price_drop_min_percent','5')) ?>">
        </div>
      </div>

      <div style="border:1px solid var(--gold-border);border-radius:8px;padding:14px;background:var(--cream)">
        <label style="display:flex;gap:10px;align-items:flex-start">
          <input type="checkbox" name="qna_enabled" value="1" <?= setting('qna_enabled','0')==='1'?'checked':'' ?> style="margin-top:2px">
          <div style="flex:1">
            <strong style="display:block;font-size:14px;color:var(--ink)">❓ Ürün Q&amp;A (Soru &amp; Cevap)</strong>
            <small class="muted" style="font-size:12px">PDP'de müşterilerin soru sorabileceği bölüm. Admin onayından sonra yayınlanır. SEO için uzun-kuyruk içerik.</small>
          </div>
        </label>
      </div>

      <div style="border:1px solid var(--gold-border);border-radius:8px;padding:14px;background:var(--cream)">
        <label style="display:flex;gap:10px;align-items:flex-start">
          <input type="checkbox" name="review_media_enabled" value="1" <?= setting('review_media_enabled','0')==='1'?'checked':'' ?> style="margin-top:2px">
          <div style="flex:1">
            <strong style="display:block;font-size:14px;color:var(--ink)">📷 Yorumlara Foto/Video</strong>
            <small class="muted" style="font-size:12px">Müşteri ürün yorumuna kendi fotoğrafını eklesin. Sosyal kanıt + AR alternatifi.</small>
          </div>
        </label>
      </div>

    </div>
  </div>

  <div><button class="btn btn-primary">Kaydet</button></div>
</form>

<?php require_once __DIR__ . '/../core/footer.php'; ?>
