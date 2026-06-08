<?php
/**
 * Ödeme görünümü — çok adımlı sihirbaz (Adres → Fatura → Ödeme Yöntemi → Onay).
 * core/controllers/checkout.php tarafından sağlanan değişkenleri kullanır.
 * Tek <form>: tüm alanlar son adımda birlikte gönderilir; backend akışı değişmez.
 */
include __DIR__ . "/../../includes/header.php";

// GA4 begin_checkout — kullanıcı checkout sayfasını gördü
if (!empty($items)) {
    $__ga_items = [];
    foreach ($items as $__i => $__it) {
        $__pRow = [
            'id'    => $__it['product_id'] ?? 0,
            'name'  => $__it['name']       ?? '',
            'price' => $__it['price']      ?? 0,
            'sku'   => $__it['sku']        ?? null,
        ];
        $__ga_items[] = analytics_ecommerce_item($__pRow, (int)($__it['qty'] ?? 1), null, $__i);
    }
    analytics_event('begin_checkout', [
        'currency' => 'TRY',
        'value'    => round((float)($pr['grand_total'] ?? 0), 2),
        'coupon'   => $pr['coupon_code'] ?? null,
        'items'    => $__ga_items,
    ]);
}
?>

<section class="page-header">
  <div class="container">
    <span class="kicker">Sipariş</span>
    <h1 style="margin-top:10px">Ödeme</h1>
    <div class="breadcrumb"><a href="<?= url('home') ?>">Anasayfa</a><span>/</span><a href="<?= url('cart') ?>">Sepet</a><span>/</span>Ödeme</div>
  </div>
</section>

<?php if ($checkoutHtml): ?>
<!-- iyzico Checkout Form yerleştirme — kullanıcı kart bilgisini bu container'da girer -->
<section><div class="container">
  <div class="panel" style="max-width:720px;margin:0 auto;padding:24px">
    <h3 style="margin-bottom:8px">Güvenli Ödeme</h3>
    <p class="muted" style="font-size:13px;margin-bottom:18px">Kart bilgileriniz bizde saklanmaz; iyzico altyapısı ile 3D Secure korumalıdır.</p>
    <div id="iyzipay-checkout-form" class="responsive" style="min-height:480px"></div>
    <?php if (!empty($paymentPageUrl)): ?>
      <p class="muted" style="font-size:12px;margin-top:14px;text-align:center">
        Form açılmazsa <a href="<?= e($paymentPageUrl) ?>" style="color:var(--leaf);text-decoration:underline">güvenli ödeme sayfasını yeni sekmede</a> açabilirsiniz.
      </p>
    <?php endif; ?>
  </div>
</div></section>
<?= $checkoutHtml /* iyzico'nun script'i — #iyzipay-checkout-form içine formu yerleştirir */ ?>
<?php else: ?>

<section>
  <div class="container checkout-grid">
    <form method="post" class="panel" id="checkout-form" novalidate>
      <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
      <?php if ($err): ?><div class="alert alert-err" style="margin-bottom:18px"><?= e($err) ?></div><?php endif; ?>

      <!-- İLERLEME ÇUBUĞU -->
      <div class="co-stepper">
        <div class="co-stepper-item active" data-go="0"><span class="n">1</span><span class="t">Adres</span></div>
        <div class="co-stepper-item" data-go="1"><span class="n">2</span><span class="t">Fatura</span></div>
        <div class="co-stepper-item" data-go="2"><span class="n">3</span><span class="t">Ödeme Yöntemi</span></div>
        <div class="co-stepper-item" data-go="3"><span class="n">4</span><span class="t">Onay</span></div>
      </div>

      <!-- ADIM 1 — TESLİMAT ADRESİ -->
      <div class="co-step active" data-step="0">
        <h3 style="margin-bottom:18px">Teslimat Adresi</h3>

        <?php if ($savedAddresses): ?>
        <div class="field" style="margin-bottom:14px">
          <label>Kayıtlı Adresim Kullan</label>
          <select id="saved-address-picker">
            <option value="">— Yeni adres gir —</option>
            <?php foreach ($savedAddresses as $a): ?>
              <option value="<?= (int)$a['id'] ?>"
                data-name="<?= e($a['full_name']) ?>"
                data-phone="<?= e($a['phone']) ?>"
                data-address="<?= e($a['address']) ?>"
                data-city="<?= e($a['city']) ?>"
                data-zip="<?= e($a['zip']) ?>"
                <?= !empty($a['is_default'])?'selected':'' ?>><?= e($a['label']) ?> — <?= e($a['city']) ?><?= !empty($a['is_default'])?' (varsayılan)':'' ?></option>
            <?php endforeach; ?>
          </select>
          <small class="muted">Yeni bir adresi <a href="<?= url('account') ?>#adresler" target="_blank" style="color:var(--leaf);text-decoration:underline">Hesabım → Adreslerim</a>'den ekleyebilirsiniz.</small>
        </div>
        <?php endif; ?>

        <div class="row-2">
          <div class="field"><label>Ad Soyad <span class="req" aria-hidden="true">*</span></label><input name="name" value="<?= e($_POST['name'] ?? ($user['name'] ?? '')) ?>" required></div>
          <div class="field"><label>E-posta <span class="req" aria-hidden="true">*</span></label><input name="email" type="email" value="<?= e($_POST['email'] ?? ($user['email'] ?? '')) ?>" required></div>
        </div>
        <div class="row-2" style="margin-top:14px">
          <div class="field"><label>Telefon <span class="req" aria-hidden="true">*</span></label><input name="phone" value="<?= e($_POST['phone'] ?? ($user['phone'] ?? '')) ?>" required></div>
          <div class="field"><label>Şehir <span class="req" aria-hidden="true">*</span></label><input name="city" value="<?= e($_POST['city'] ?? '') ?>" required></div>
        </div>
        <div class="field" style="margin-top:14px"><label>Adres <span class="req" aria-hidden="true">*</span></label><textarea name="address" required><?= e($_POST['address'] ?? ($user['address'] ?? '')) ?></textarea></div>
        <div class="row-2" style="margin-top:14px">
          <div class="field"><label>TC Kimlik No</label><input name="identity" maxlength="11" inputmode="numeric" value="<?= e($_POST['identity'] ?? '') ?>"><small class="muted">Yasal gereklilik için tahsil edilir, yalnızca ödeme onayında kullanılır.</small></div>
          <div class="field"><label>Posta Kodu</label><input name="zip" value="<?= e($_POST['zip'] ?? '') ?>"></div>
        </div>

        <?php if ($user): ?>
        <div class="field" style="margin-top:14px;padding:12px 16px;background:rgba(201,162,75,.06);border:1px solid rgba(201,162,75,.3);border-radius:8px">
          <label style="display:flex;align-items:center;gap:10px;cursor:pointer;font-size:14px;color:var(--ink);text-transform:none;letter-spacing:0">
            <input type="checkbox" name="save_address" value="1" <?= ($_SERVER['REQUEST_METHOD'] !== 'POST' || isset($_POST['save_address'])) ? 'checked' : '' ?> style="width:18px;height:18px;min-height:0;flex:0 0 auto;margin:0;padding:0;accent-color:var(--leaf)">
            <strong>Bu adresi adres defterime kaydet</strong>
          </label>
          <div style="margin-top:10px;display:flex;align-items:center;gap:10px;flex-wrap:wrap">
            <span class="muted" style="font-size:13px">Etiket:</span>
            <input name="address_label" value="<?= e($_POST['address_label'] ?? 'Ev') ?>" list="addr-label-options" style="max-width:180px;padding:6px 10px" placeholder="Ev">
            <datalist id="addr-label-options"><option value="Ev"></option><option value="İş"></option><option value="Diğer"></option></datalist>
            <small class="muted" style="font-size:12px">Sonraki siparişlerde "Kayıtlı Adresim"den seçebilirsiniz.</small>
          </div>
        </div>
        <?php endif; ?>

        <div class="co-nav">
          <span></span>
          <button type="button" class="btn btn-primary" data-co-next>Devam →</button>
        </div>
      </div>

      <!-- ADIM 2 — FATURA BİLGİLERİ -->
      <div class="co-step" data-step="1">
        <h3 style="margin-bottom:18px">Fatura Bilgileri</h3>
        <div class="pref-options" style="margin-bottom:14px">
          <label class="pref-option">
            <input type="radio" name="invoice_type" value="individual" <?= (($_POST['invoice_type'] ?? 'individual')==='individual')?'checked':'' ?> data-toggle="invoice-fields">
            <span><strong>Bireysel</strong> — Şahıs adına fatura</span>
          </label>
          <label class="pref-option">
            <input type="radio" name="invoice_type" value="company" <?= (($_POST['invoice_type'] ?? '')==='company')?'checked':'' ?> data-toggle="invoice-fields">
            <span><strong>Kurumsal</strong> — Şirket adına fatura (Vergi/T.C. No ile)</span>
          </label>
        </div>
        <div id="company-fields" style="display:<?= (($_POST['invoice_type'] ?? '')==='company')?'grid':'none' ?>;gap:14px">
          <div class="field"><label>Şirket Ünvanı <span class="req" aria-hidden="true">*</span></label><input name="invoice_company" value="<?= e($_POST['invoice_company'] ?? '') ?>" placeholder="Örn. Demir Ltd. Şti."></div>
          <div class="row-2">
            <div class="field"><label>Vergi Numarası <span class="req" aria-hidden="true">*</span></label><input name="invoice_tax_no" maxlength="11" inputmode="numeric" value="<?= e($_POST['invoice_tax_no'] ?? '') ?>"></div>
            <div class="field"><label>Vergi Dairesi <span class="req" aria-hidden="true">*</span></label><input name="invoice_tax_office" value="<?= e($_POST['invoice_tax_office'] ?? '') ?>" placeholder="Örn. Nilüfer"></div>
          </div>
          <small class="muted">Vergi dairesi onaylı resmi fatura (e-Fatura/e-Arşiv) sipariş tamamlandıktan sonra otomatik düzenlenir.</small>
        </div>

        <div class="field" style="margin-top:18px"><label>Sipariş Notu</label><textarea name="note" placeholder="(opsiyonel)"><?= e($_POST['note'] ?? '') ?></textarea></div>

        <?php if (setting('gift_wrap_enabled','0') === '1'):
          $__gwPrice = (float)setting('gift_wrap_price', '25');
        ?>
          <div class="field" style="margin-top:14px;padding:14px 16px;background:rgba(201,162,75,.06);border:1px solid rgba(201,162,75,.3);border-radius:8px">
            <label style="display:flex;align-items:center;gap:10px;cursor:pointer;font-size:14px;color:var(--ink);text-transform:none;letter-spacing:0">
              <input type="checkbox" name="gift_wrap" value="1" <?= !empty($_POST['gift_wrap']) ? 'checked' : '' ?> style="width:18px;height:18px;min-height:0;flex:0 0 auto;margin:0;padding:0;accent-color:var(--leaf)">
              🎁 <strong>Hediye paketi istiyorum</strong>
              <?php if ($__gwPrice > 0): ?>
                <span style="color:var(--leaf);font-weight:600;margin-left:auto">+<?= money($__gwPrice) ?></span>
              <?php else: ?>
                <span style="color:var(--leaf);font-weight:600;margin-left:auto">Ücretsiz</span>
              <?php endif; ?>
            </label>
            <textarea name="gift_wrap_note" placeholder="Karta yazılacak mesaj (opsiyonel — örn. 'Mutlu yıllar Ayşe!')" rows="2" style="margin-top:10px;font-size:13px"><?= e($_POST['gift_wrap_note'] ?? '') ?></textarea>
          </div>
        <?php endif; ?>

        <div class="co-nav">
          <button type="button" class="btn btn-secondary" data-co-prev>← Geri</button>
          <button type="button" class="btn btn-primary" data-co-next>Devam →</button>
        </div>
      </div>

      <!-- ADIM 3 — ÖDEME YÖNTEMİ -->
      <div class="co-step" data-step="2">
        <h3 style="margin-bottom:18px">Ödeme Yöntemi</h3>
        <div class="pref-options">
        <?php foreach ($methods as $val => $label): ?>
          <label class="pref-option">
            <input type="radio" name="pay" value="<?= e($val) ?>" <?= (($_POST['pay'] ?? array_key_first($methods))===$val)?'checked':'' ?>>
            <span><?= e($label) ?></span>
          </label>
        <?php endforeach; ?>
        </div>
        <p class="muted" style="font-size:12px;margin-top:14px;line-height:1.6">Kart ile ödeme <strong>3D Secure</strong> ile korunur. Havale/EFT seçeneğinde IBAN bilgileri sipariş onayı ekranında ve e-postada paylaşılır.</p>
        <div class="co-nav">
          <button type="button" class="btn btn-secondary" data-co-prev>← Geri</button>
          <button type="button" class="btn btn-primary" data-co-next>Devam →</button>
        </div>
      </div>

      <!-- ADIM 4 — ONAY & ÖDEME -->
      <div class="co-step" data-step="3">
        <h3 style="margin-bottom:18px">Onay & Ödeme</h3>
        <div id="co-review" class="co-review"></div>

        <fieldset style="margin-top:18px;border:1px solid var(--gold-border);border-radius:var(--radius);padding:16px;display:grid;gap:10px">
          <legend style="font-size:11px;letter-spacing:.22em;text-transform:uppercase;color:var(--ink);font-weight:600;padding:0 8px">Yasal Onaylar</legend>
          <p id="co-consent-warn" hidden style="margin:0;color:#9A2A2A;font-size:13px;font-weight:600">⚠ Devam etmek için aşağıdaki tüm onay kutularını işaretlemelisiniz.</p>
          <label style="display:flex;gap:10px;align-items:flex-start;font-size:13px;line-height:1.5;cursor:pointer">
            <input type="checkbox" name="agree_preinfo" value="1" required>
            <span><a href="<?= url('page', ['slug'=>'on-bilgilendirme']) ?>" target="_blank" rel="noopener" style="color:var(--leaf);text-decoration:underline">Ön Bilgilendirme Formu</a>'nu okudum ve onaylıyorum.</span>
          </label>
          <label style="display:flex;gap:10px;align-items:flex-start;font-size:13px;line-height:1.5;cursor:pointer">
            <input type="checkbox" name="agree_contract" value="1" required>
            <span><a href="<?= url('page', ['slug'=>'mesafeli-satis']) ?>" target="_blank" rel="noopener" style="color:var(--leaf);text-decoration:underline">Mesafeli Satış Sözleşmesi</a>'ni okudum ve onaylıyorum.</span>
          </label>
          <label style="display:flex;gap:10px;align-items:flex-start;font-size:13px;line-height:1.5;cursor:pointer">
            <input type="checkbox" name="agree_kvkk" value="1" required>
            <span><a href="<?= url('page', ['slug'=>'kvkk']) ?>" target="_blank" rel="noopener" style="color:var(--leaf);text-decoration:underline">KVKK Aydınlatma Metni</a>'ni okudum ve onaylıyorum.</span>
          </label>
        </fieldset>

        <div class="co-nav">
          <button type="button" class="btn btn-secondary" data-co-prev>← Geri</button>
          <button class="btn btn-primary btn-lg" type="submit">Siparişi Tamamla</button>
        </div>
      </div>
    </form>

    <aside class="panel checkout-summary">
      <h3 style="margin-bottom:18px">Siparişiniz</h3>
      <?php foreach ($items as $it): ?>
        <div class="sum-row">
          <span><?= e($it['name']) ?> <span class="muted">× <?= (int)$it['qty'] ?></span></span>
          <span><?= money($it['price']*$it['qty']) ?></span>
        </div>
      <?php endforeach; ?>
      <div class="sum-row" style="padding-top:10px"><span class="muted">Ara Toplam</span><span><?= money($pr['items_total']) ?></span></div>
      <?php if ($pr['discount'] > 0): ?>
        <div class="sum-row" style="color:var(--leaf)"><span>İndirim (<?= e($pr['coupon_code']) ?>)</span><span>− <?= money($pr['discount']) ?></span></div>
      <?php endif; ?>
      <?php if (!empty($pr['loyalty_value']) && $pr['loyalty_value'] > 0): ?>
        <div class="sum-row" style="color:var(--leaf)"><span>🏆 Puan indirimi (<?= (int)$pr['loyalty_points'] ?> puan)</span><span>− <?= money($pr['loyalty_value']) ?></span></div>
      <?php endif; ?>
      <div class="sum-row"><span class="muted">Kargo</span><span><?= $pr['shipping']>0 ? money($pr['shipping']) : '<span style="color:var(--leaf)">Ücretsiz</span>' ?></span></div>
      <div class="sum-row sum-total"><span>Toplam</span><span><?= money($pr['grand_total']) ?></span></div>
      <?php if ($pr['vat'] > 0): ?>
        <div class="sum-row" style="font-size:12px;color:var(--muted-text)"><span>KDV dahil (%<?= number_format($pr['vat_rate'], 0, ',', '') ?>)</span><span><?= money($pr['vat']) ?></span></div>
      <?php endif; ?>
      <p class="muted" style="font-size:12px;margin-top:14px;line-height:1.6">Kart ile ödeme <strong>3D Secure</strong> ile korunur. Havale/EFT seçeneğinde IBAN bilgileri sipariş onayı ekranında ve e-postada paylaşılır.</p>
    </aside>
  </div>
</section>

<style>
.co-stepper{display:flex;gap:8px;margin-bottom:24px;flex-wrap:wrap}
.co-stepper-item{flex:1 1 0;min-width:108px;display:flex;align-items:center;gap:8px;font-size:13px;color:var(--muted-text);padding:10px 12px;border-radius:8px;border:1px solid var(--gold-border);background:var(--olive-2);transition:border-color .2s,color .2s}
.co-stepper-item .n{width:24px;height:24px;border-radius:50%;display:grid;place-items:center;background:var(--cream);color:var(--muted-text);font-size:12px;font-weight:700;flex:0 0 auto}
.co-stepper-item.active{border-color:var(--gold);color:var(--ink);font-weight:600}
.co-stepper-item.active .n{background:var(--gold);color:var(--on-dark)}
.co-stepper-item.done{cursor:pointer}
.co-stepper-item.done .n{background:var(--leaf);color:#fff}
.co-step{display:none}
.co-step.active{display:block;animation:coFade .2s ease}
@keyframes coFade{from{opacity:0;transform:translateY(8px)}to{opacity:1;transform:none}}
.co-nav{display:flex;justify-content:space-between;gap:12px;margin-top:24px}
.co-nav .btn{min-width:120px}
.co-review .cor{display:flex;justify-content:space-between;gap:14px;padding:12px 0;border-bottom:1px solid var(--gold-border);font-size:14px}
.co-review .cor:last-child{border-bottom:none}
.co-review .cor .lbl{color:var(--muted-text);font-size:12px;letter-spacing:.06em;text-transform:uppercase;flex:0 0 auto}
.co-review .cor .val{color:var(--ink);text-align:right;line-height:1.5}
@media(max-width:560px){.co-stepper-item .t{display:none}.co-stepper-item{min-width:0;justify-content:center}}
</style>
<script>
(function () {
  var form = document.getElementById('checkout-form');
  if (!form) return;
  var steps   = Array.prototype.slice.call(form.querySelectorAll('.co-step'));
  var stepNav = Array.prototype.slice.call(form.querySelectorAll('.co-stepper-item'));
  var current = 0;

  function fieldsOf(i){ return Array.prototype.slice.call(steps[i].querySelectorAll('input, select, textarea')); }
  function validStep(i){
    var fs = fieldsOf(i);
    for (var k = 0; k < fs.length; k++) {
      if (fs[k].disabled) continue;
      if (!fs[k].checkValidity()) { fs[k].reportValidity(); return false; }
    }
    return true;
  }
  function renderReview(){
    var rv = document.getElementById('co-review');
    if (!rv) return;
    function v(n){ var el = form.querySelector('[name="'+n+'"]'); return el ? el.value.trim() : ''; }
    var payInput = form.querySelector('input[name="pay"]:checked');
    var payLabel = payInput ? (payInput.parentNode.querySelector('span') ? payInput.parentNode.querySelector('span').textContent.trim() : payInput.value) : '';
    var inv = (form.querySelector('input[name="invoice_type"]:checked') || {}).value;
    var invTxt = inv === 'company' ? ('Kurumsal — ' + (v('invoice_company') || '')) : 'Bireysel';
    rv.innerHTML =
      '<div class="cor"><span class="lbl">Teslimat</span><span class="val"><strong>' + esc(v('name')) + '</strong> · ' + esc(v('phone')) + '<br>' + esc(v('address')) + '<br>' + esc(v('city')) + (v('zip') ? ' · ' + esc(v('zip')) : '') + '</span></div>' +
      '<div class="cor"><span class="lbl">Fatura</span><span class="val">' + esc(invTxt) + '</span></div>' +
      '<div class="cor"><span class="lbl">Ödeme</span><span class="val">' + esc(payLabel) + '</span></div>';
  }
  function esc(s){ return String(s == null ? '' : s).replace(/[&<>"]/g, function(c){ return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c]; }); }

  function go(i){
    if (i < 0 || i >= steps.length) return;
    steps.forEach(function (s, k) { s.classList.toggle('active', k === i); });
    stepNav.forEach(function (s, k) { s.classList.toggle('active', k === i); s.classList.toggle('done', k < i); });
    current = i;
    if (i === steps.length - 1) renderReview();
    window.scrollTo({ top: 0, behavior: 'smooth' });
  }

  form.addEventListener('click', function (e) {
    if (e.target.closest('[data-co-next]')) { e.preventDefault(); if (validStep(current)) go(current + 1); return; }
    if (e.target.closest('[data-co-prev]')) { e.preventDefault(); go(current - 1); return; }
  });
  // İlerleme çubuğu: sadece tamamlanmış (geçmiş) adımlara geri dönülebilir
  stepNav.forEach(function (s, i) {
    s.addEventListener('click', function () { if (i < current) go(i); });
  });
  var CONSENTS = ['agree_preinfo', 'agree_contract', 'agree_kvkk'];
  function consentsOk() {
    return CONSENTS.every(function (n) { var c = form.querySelector('[name="' + n + '"]'); return c && c.checked; });
  }
  function toggleConsentWarn() {
    var warn = document.getElementById('co-consent-warn');
    if (warn) warn.hidden = consentsOk();
  }
  // Onay kutusu işaretlenince uyarıyı anında gizle
  CONSENTS.forEach(function (n) {
    var el = form.querySelector('[name="' + n + '"]');
    if (el) el.addEventListener('change', toggleConsentWarn);
  });

  // Son gönderim: tüm adımları doğrula, hatalıysa o adıma git. reportValidity SENKRON
  // çağrılır (Safari asenkron/setTimeout'ta doğrulama balonunu göstermez).
  form.addEventListener('submit', function (e) {
    for (var i = 0; i < steps.length; i++) {
      var fs = fieldsOf(i);
      for (var k = 0; k < fs.length; k++) {
        if (fs[k].disabled) continue;
        if (!fs[k].checkValidity()) {
          e.preventDefault();
          if (i !== current) go(i);
          // Eksik onay varsa görünür uyarı (Safari native balonu güvenilmez)
          if ((fs[k].name || '').indexOf('agree_') === 0) toggleConsentWarn();
          try { fs[k].focus(); } catch (_) {}
          fs[k].reportValidity();
          return;
        }
      }
    }
    // hepsi geçerli → form gönderilir
  });

  go(0);
})();
</script>
<?php endif; ?>

<?php include __DIR__ . "/../../includes/footer.php"; ?>
