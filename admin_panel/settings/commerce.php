<?php
$page = 'settings'; $title = 'Satış & Operasyon';
require_once __DIR__ . '/../core/auth.php';
require_once __DIR__ . '/_save.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    settings_save_fields(
        [
            // Kargo
            'shipping_flat','shipping_free_threshold',
            // Yasal / Mali
            'vat_rate','vat_display',
            'company_legal_name','company_tax_office','company_tax_no','company_mersis',
            // Banka
            'bank_name','bank_iban','bank_account_holder',
            // Stok uyarısı
            'low_stock_threshold','low_stock_alert_email',
            // Düşük stok rozet eşiği (storefront PDP)
            'low_stock_badge_threshold',
        ],
        [],
        [],
        'commerce.php'
    );
}

require_once __DIR__ . '/../core/header.php';
?>

<?php settings_sub_header(
    'Satış & Operasyon',
    'Kargo ücretleri, KDV oranı, fatura/şirket bilgileri, banka hesabı ve stok uyarısı.'
); ?>

<form method="post" style="display:grid;gap:24px;max-width:880px">
  <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">

  <div class="panel">
    <h3>🚚 Kargo</h3>
    <small class="muted" style="display:block;margin:-6px 0 14px">
      Ücretsiz kargo eşiği sepet sayfasında "X₺ daha ekle, kargo bedava" şeklinde progress bar olarak gösterilir.
    </small>
    <div style="display:grid;gap:14px">
      <div class="row-2">
        <div class="field">
          <label>Sabit Kargo Ücreti (₺)</label>
          <input name="shipping_flat" type="number" step="0.01" min="0" value="<?= e(setting('shipping_flat','89.90')) ?>">
        </div>
        <div class="field">
          <label>Ücretsiz Kargo Eşiği (₺)</label>
          <input name="shipping_free_threshold" type="number" step="0.01" min="0" value="<?= e(setting('shipping_free_threshold','1999')) ?>">
          <small class="muted">Bu tutar üzerinde kargo bedava. 0 = her zaman ücretli.</small>
        </div>
      </div>
    </div>
  </div>

  <div class="panel">
    <h3>📋 Yasal & Mali</h3>
    <small class="muted" style="display:block;margin:-6px 0 14px">
      Faturalarda, sipariş onay e-postasında ve kullanıcı sözleşmelerinde kullanılır. Yasal zorunluluk.
    </small>
    <div style="display:grid;gap:14px">
      <div class="row-2">
        <div class="field">
          <label>KDV Oranı (%)</label>
          <input name="vat_rate" type="number" step="0.1" min="0" max="50" value="<?= e(setting('vat_rate','20')) ?>">
          <small class="muted">2024+ standart oran %20. Gıda gibi indirimli kalemler için ayarlayın.</small>
        </div>
        <div class="field">
          <label>KDV Gösterim Şekli</label>
          <select name="vat_display">
            <?php $vd = setting('vat_display','included'); ?>
            <option value="included" <?= $vd==='included'?'selected':'' ?>>Fiyat KDV dahil ("KDV dahil" yazısıyla)</option>
            <option value="excluded" <?= $vd==='excluded'?'selected':'' ?>>Fiyat KDV hariç ("KDV hariç" yazısıyla)</option>
          </select>
        </div>
      </div>

      <h4 style="font-family:'Inter',sans-serif;font-size:11px;letter-spacing:.22em;text-transform:uppercase;color:var(--muted-text);margin:6px 0 0">Şirket Bilgileri</h4>
      <div class="row-2">
        <div class="field"><label>Yasal Ünvan</label><input name="company_legal_name" value="<?= e(setting('company_legal_name','')) ?>" placeholder="Örn. ABC San. Tic. Ltd. Şti."></div>
        <div class="field"><label>Vergi Dairesi</label><input name="company_tax_office" value="<?= e(setting('company_tax_office','')) ?>" placeholder="Örn. Nilüfer V.D."></div>
      </div>
      <div class="row-2">
        <div class="field"><label>Vergi/T.C. No</label><input name="company_tax_no" value="<?= e(setting('company_tax_no','')) ?>" placeholder="1234567890"></div>
        <div class="field"><label>Mersis No (opsiyonel)</label><input name="company_mersis" value="<?= e(setting('company_mersis','')) ?>" placeholder="0000000000000000"></div>
      </div>

      <h4 style="font-family:'Inter',sans-serif;font-size:11px;letter-spacing:.22em;text-transform:uppercase;color:var(--muted-text);margin:6px 0 0">Havale / EFT Bilgileri</h4>
      <small class="muted" style="margin-top:-8px">Havale ile ödeyen müşteriye sipariş mailinde IBAN gösterilir.</small>
      <div class="row-2">
        <div class="field"><label>Banka Adı</label><input name="bank_name" value="<?= e(setting('bank_name','')) ?>" placeholder="Garanti Bankası"></div>
        <div class="field"><label>Hesap Sahibi</label><input name="bank_account_holder" value="<?= e(setting('bank_account_holder','')) ?>" placeholder="Şirket Ünvanınız"></div>
      </div>
      <div class="field"><label>IBAN</label><input name="bank_iban" value="<?= e(setting('bank_iban','')) ?>" placeholder="TR00 0000 0000 0000 0000 0000 00"></div>
    </div>
  </div>

  <div class="panel">
    <h3>⚠ Stok Uyarısı</h3>
    <small class="muted" style="display:block;margin:-6px 0 14px">
      Düşük stok için günlük cron çalışır, eşik altındaki ürünleri tek özet mailde belirtilen adrese gönderir.
    </small>
    <div style="display:grid;gap:14px">
      <div class="row-2">
        <div class="field">
          <label>Düşük Stok Eşiği (admin uyarısı)</label>
          <input name="low_stock_threshold" type="number" min="0" value="<?= e(setting('low_stock_threshold','5')) ?>">
          <small class="muted">Bu sayının altına düşen ürünler için günlük e-posta uyarısı. 0 = kapalı.</small>
        </div>
        <div class="field">
          <label>Uyarı E-postası</label>
          <input name="low_stock_alert_email" type="email" value="<?= e(setting('low_stock_alert_email','')) ?>" placeholder="depo@siteniz.com">
          <small class="muted">Boş bırakılırsa İletişim E-postası kullanılır.</small>
        </div>
      </div>
      <div class="field" style="max-width:380px">
        <label>"Sadece N kaldı" Rozet Eşiği</label>
        <input name="low_stock_badge_threshold" type="number" min="0" value="<?= e(setting('low_stock_badge_threshold','10')) ?>">
        <small class="muted">Ürün sayfasında bu sayının altındaki stok için aciliyet rozeti gösterilir.</small>
      </div>
    </div>
  </div>

  <div><button class="btn btn-primary">Kaydet</button></div>
</form>

<?php require_once __DIR__ . '/../core/footer.php'; ?>
