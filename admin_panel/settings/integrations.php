<?php
$page = 'settings'; $title = 'Entegrasyonlar';
require_once __DIR__ . '/../core/auth.php';
require_once __DIR__ . '/_save.php';
require_once __DIR__ . '/../../includes/mailer.php';

/* ── POST handler ────────────────────────────────────────────── */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf_check($_POST['csrf'] ?? null)) {
    $st = db()->prepare('INSERT INTO settings (setting_key,setting_value) VALUES (?,?) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value)');

    $textKeys = [
        // iyzico
        'iyz_env','iyz_api_key','iyz_secret_key','iyz_max_installment',
        // SMS
        'sms_provider','sms_user','sms_sender',
        'sms_tpl_order_confirm','sms_tpl_order_shipped','sms_tpl_order_delivered',
        // SMTP
        'smtp_host','smtp_port','smtp_user','smtp_secure',
        'smtp_from_email','smtp_from_name',
        // Anthropic AI
        'anthropic_api_key',
    ];
    foreach ($textKeys as $k) $st->execute([$k, trim((string)($_POST[$k] ?? ''))]);

    foreach (['iyz_enabled','sms_enabled'] as $k) {
        $st->execute([$k, !empty($_POST[$k]) ? '1' : '0']);
    }

    // Şifreler — boş geldiyse mevcut değeri koru
    foreach (['smtp_pass','sms_pass'] as $k) {
        if (isset($_POST[$k]) && $_POST[$k] !== '') {
            $st->execute([$k, $_POST[$k]]);
        }
    }

    // SMTP test isteği
    if (($_POST['action'] ?? '') === 'smtp_test') {
        $to = trim((string)($_POST['test_to'] ?? '')) ?: trim((string)setting('contact_email',''));
        if ($to === '' || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
            flash_set('err','Ayarlar kaydedildi ama test için geçerli bir alıcı e-postası girmediniz.');
        } else {
            $html = mail_template('SMTP Test', '<p>Merhaba,</p><p>Bu, SMTP ayarlarınızla gönderilmiş bir test e-postasıdır. Bunu okuyabiliyorsanız posta gönderimi çalışıyor demektir.</p>');
            $ok = mail_send($to, 'SMTP Test', $html);
            flash_set($ok?'ok':'err', $ok
                ? 'Ayarlar kaydedildi ve test e-postası gönderildi: '.$to.' (Spam klasörünü de kontrol edin.)'
                : 'Ayarlar kaydedildi ama gönderim başarısız. cPanel → Error Log\'da [smtp] satırlarına bakın.');
        }
        redirect('integrations.php');
    }

    flash_set('ok','Ayarlar kaydedildi.'); redirect('integrations.php');
}

// SDK kontrolü (iyzico)
require_once __DIR__ . '/../../includes/iyzico.php';
$sdkOk = file_exists(__DIR__ . '/../../vendor/iyzipay-php/IyzipayBootstrap.php');

require_once __DIR__ . '/../core/header.php';
?>

<?php settings_sub_header(
    'Entegrasyonlar',
    'iyzico ödeme, SMS sağlayıcı (NetGSM/İletiMerkezi), SMTP e-posta, Anthropic AI.'
); ?>

<form method="post" style="display:grid;gap:24px;max-width:880px">
  <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">

  <div class="panel">
    <h3>💳 Ödeme · iyzico</h3>
    <small class="muted" style="display:block;margin:-6px 0 14px">
      API anahtarlarını <a href="https://merchant.iyzipay.com" target="_blank" rel="noopener" style="color:var(--leaf);text-decoration:underline">iyzico Merchant</a> panelinden alın.
      Önce <strong>sandbox</strong> ile test edin, hazır olunca <strong>Canlı</strong>'ya geçin.
    </small>
    <div style="display:grid;gap:14px">
      <label style="display:flex;gap:10px;align-items:center">
        <input type="checkbox" name="iyz_enabled" value="1" <?= setting('iyz_enabled','0')==='1'?'checked':'' ?>>
        Kart ile ödemeyi aktifleştir
      </label>
      <div class="row-2">
        <div class="field">
          <label>Çevre</label>
          <select name="iyz_env">
            <option value="sandbox" <?= setting('iyz_env','sandbox')==='sandbox'?'selected':'' ?>>Sandbox (test)</option>
            <option value="live"    <?= setting('iyz_env','sandbox')==='live'?'selected':'' ?>>Canlı (production)</option>
          </select>
        </div>
        <div class="field">
          <label>Maksimum Taksit</label>
          <select name="iyz_max_installment">
            <?php for ($i=1; $i<=12; $i++): ?>
              <option value="<?= $i ?>" <?= ((int)setting('iyz_max_installment','6'))===$i?'selected':'' ?>><?= $i==1?'Tek çekim':$i.' taksit' ?></option>
            <?php endfor; ?>
          </select>
        </div>
      </div>
      <div class="field"><label>API Anahtarı (apiKey)</label><input name="iyz_api_key" value="<?= e(setting('iyz_api_key','')) ?>" autocomplete="off" spellcheck="false"></div>
      <div class="field">
        <label>Güvenlik Anahtarı (secretKey)</label>
        <input name="iyz_secret_key" value="<?= e(setting('iyz_secret_key','')) ?>" autocomplete="off" spellcheck="false">
        <small class="muted">Anahtarlar veritabanında saklanır; HTTPS olmadan üretim ortamında kullanmayın.</small>
      </div>
      <div style="font-size:13px;padding:12px 14px;border-radius:6px;background:<?= $sdkOk?'var(--success-soft)':'var(--danger-soft)' ?>;border:1px solid <?= $sdkOk?'var(--success-border)':'var(--danger-border)' ?>;color:<?= $sdkOk?'var(--success-text)':'var(--danger-text)' ?>">
        <strong>SDK durumu:</strong> <?= $sdkOk ? 'iyzipay-php SDK yüklü ✓' : 'SDK bulunamadı — vendor/iyzipay-php/ altına yükleyin (README\'deki talimatlar).' ?>
      </div>
    </div>
  </div>

  <div class="panel">
    <h3>📱 SMS Bildirimleri</h3>
    <small class="muted" style="display:block;margin:-6px 0 14px">
      Sipariş onayı, kargo çıkışı, teslim ve şifre sıfırlama SMS'leri.
      Sağlayıcı: <a href="https://www.netgsm.com.tr" target="_blank" rel="noopener" style="color:var(--leaf);text-decoration:underline">NetGSM</a> veya <a href="https://www.iletimerkezi.com" target="_blank" rel="noopener" style="color:var(--leaf);text-decoration:underline">İletiMerkezi</a>.
    </small>
    <div style="display:grid;gap:14px">
      <label style="display:flex;gap:10px;align-items:center">
        <input type="checkbox" name="sms_enabled" value="1" <?= setting('sms_enabled','0')==='1'?'checked':'' ?>>
        SMS gönderimini aktifleştir
      </label>
      <div class="row-2">
        <div class="field">
          <label>SMS Sağlayıcı</label>
          <select name="sms_provider">
            <?php $sp = (string)setting('sms_provider','netgsm'); ?>
            <option value="netgsm"       <?= $sp==='netgsm'?'selected':'' ?>>NetGSM</option>
            <option value="iletimerkezi" <?= $sp==='iletimerkezi'?'selected':'' ?>>İletiMerkezi</option>
          </select>
        </div>
        <div class="field">
          <label>Gönderen Başlığı (Onaylı)</label>
          <input name="sms_sender" value="<?= e(setting('sms_sender','')) ?>" placeholder="MARKAADI" maxlength="11">
          <small class="muted">Sağlayıcıda onaylanmış başlık. Max 11 karakter.</small>
        </div>
      </div>
      <div class="row-2">
        <div class="field"><label>API Kullanıcı Adı</label><input name="sms_user" value="<?= e(setting('sms_user','')) ?>" autocomplete="off"></div>
        <div class="field">
          <label>API Şifresi</label>
          <input type="password" name="sms_pass" value="" placeholder="<?= setting('sms_pass','')!==''?'••••••• (kayıtlı)':'API şifresi' ?>" autocomplete="new-password">
        </div>
      </div>

      <h4 style="font-family:'Inter',sans-serif;font-size:11px;letter-spacing:.22em;text-transform:uppercase;color:var(--muted-text);margin:6px 0 0">Mesaj Şablonları (opsiyonel)</h4>
      <small class="muted" style="margin-top:-8px">Değişkenler: <code>{ad}</code>, <code>{order_id}</code>, <code>{total}</code>, <code>{tracking}</code>, <code>{magaza}</code>. Türkçe karakterler için 70/SMS, ASCII için 160/SMS.</small>
      <div class="field"><label>Sipariş Onayı</label><input name="sms_tpl_order_confirm" value="<?= e(setting('sms_tpl_order_confirm','')) ?>" placeholder="Sayin {ad}, {magaza}: #{order_id} no'lu siparisiniz alindi. Toplam: {total}."></div>
      <div class="field"><label>Kargo Çıkışı</label><input name="sms_tpl_order_shipped" value="<?= e(setting('sms_tpl_order_shipped','')) ?>" placeholder="{magaza}: #{order_id} no'lu siparisiniz kargoya verildi. Takip: {tracking}"></div>
      <div class="field"><label>Teslim</label><input name="sms_tpl_order_delivered" value="<?= e(setting('sms_tpl_order_delivered','')) ?>" placeholder="{magaza}: #{order_id} no'lu siparisiniz teslim edildi."></div>
    </div>
  </div>

  <div class="panel">
    <h3>📧 E-posta Gönderimi · SMTP</h3>
    <small class="muted" style="display:block;margin:-6px 0 14px">
      Şifre sıfırlama, sipariş bildirimi gibi otomatik postaları göndermek için SMTP bilgilerini girin.
    </small>
    <div style="background:var(--success-soft);border:1px solid var(--success-border);border-radius:6px;padding:14px 18px;margin-bottom:16px;display:flex;align-items:center;gap:14px;flex-wrap:wrap">
      <div>
        <strong style="font-size:13px">Gmail ile Hızlı Kur</strong>
        <p class="muted" style="font-size:12px;margin:3px 0 0">Gmail için Google hesabınızda <strong>2 Adımlı Doğrulama</strong> açık olmalı ve <strong>Uygulama Şifresi</strong> oluşturulmalıdır.</p>
      </div>
      <button type="button" class="btn btn-secondary btn-sm" id="gmail-preset" style="white-space:nowrap">Gmail Ayarlarını Doldur</button>
    </div>
    <div style="display:grid;gap:14px">
      <div class="row-2">
        <div class="field"><label>SMTP Sunucu</label><input name="smtp_host" value="<?= e(setting('smtp_host','')) ?>" placeholder="mail.siteniz.com"></div>
        <div class="field">
          <label>Port</label>
          <select name="smtp_port">
            <?php $sp=(string)setting('smtp_port','587'); foreach (['465','587','25','2525'] as $p): ?>
              <option value="<?= $p ?>" <?= $sp===$p?'selected':'' ?>><?= $p ?> <?= $p==='465'?'(SSL)':($p==='587'?'(TLS)':'') ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>
      <div class="row-2">
        <div class="field">
          <label>Şifreleme</label>
          <select name="smtp_secure">
            <?php $ss=(string)setting('smtp_secure','tls'); foreach (['tls'=>'STARTTLS (587)','ssl'=>'SSL (465)','none'=>'Şifresiz (25 — önerilmez)'] as $k=>$v): ?>
              <option value="<?= $k ?>" <?= $ss===$k?'selected':'' ?>><?= $v ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="field"><label>Kullanıcı (E-posta)</label><input name="smtp_user" value="<?= e(setting('smtp_user','')) ?>" placeholder="noreply@siteniz.com" autocomplete="off"></div>
      </div>
      <div class="field">
        <label>Şifre</label>
        <input name="smtp_pass" type="password" value="" placeholder="<?= setting('smtp_pass','')!==''?'••••••• (kayıtlı — değiştirmek için yeniden girin)':'SMTP şifresi' ?>" autocomplete="new-password">
      </div>
      <div class="row-2">
        <div class="field">
          <label>Gönderen E-posta</label>
          <input name="smtp_from_email" type="email" value="<?= e(setting('smtp_from_email','')) ?>" placeholder="noreply@siteniz.com">
        </div>
        <div class="field"><label>Gönderen İsmi</label><input name="smtp_from_name" value="<?= e(setting('smtp_from_name','')) ?>" placeholder="Site Adınız"></div>
      </div>
    </div>

    <div class="divider"></div>
    <div style="display:flex;gap:10px;align-items:flex-end;flex-wrap:wrap">
      <div class="field" style="flex:1;min-width:240px;margin:0">
        <label>Test gönderim adresi</label>
        <input name="test_to" type="email" placeholder="kendi-mailim@gmail.com">
      </div>
      <button type="submit" name="action" value="smtp_test" class="btn btn-secondary">Önce Kaydet, Sonra Test Gönder</button>
    </div>
    <small class="muted" style="display:block;margin-top:10px">Test butonu da formdaki tüm alanları kaydeder, sonra test postası gönderir. Posta gelmiyor ve hata da yoksa Spam/Önemsiz klasörüne bakın.</small>
  </div>

  <div class="panel">
    <h3>✨ Yapay Zeka · Anthropic Claude</h3>
    <small class="muted" style="display:block;margin:-6px 0 14px">
      API anahtarını <a href="https://console.anthropic.com" target="_blank" rel="noopener" style="color:var(--leaf);text-decoration:underline">console.anthropic.com</a> adresinden alın.
      Ürün düzenleme sayfasında "AI ile Doldur" butonu bu anahtarı kullanır.
    </small>
    <div class="field" style="max-width:480px">
      <label>Anthropic API Anahtarı</label>
      <input type="password" name="anthropic_api_key" value="<?= e(setting('anthropic_api_key','')) ?>" placeholder="sk-ant-api03-..." autocomplete="off" spellcheck="false">
      <small class="muted">Güvenli tutun — kimseyle paylaşmayın.</small>
    </div>
  </div>

  <div><button class="btn btn-primary">Kaydet</button></div>
</form>

<script>
(function(){
  var btn = document.getElementById('gmail-preset');
  if (!btn) return;
  btn.addEventListener('click', function(){
    var f = document.querySelector('form');
    if (!f) return;
    var set = function(n, v){
      var el = f.querySelector('[name="' + n + '"]');
      if (!el) return;
      el.value = v;
      if (el.tagName === 'SELECT') {
        for (var i=0; i<el.options.length; i++) el.options[i].selected = (el.options[i].value === v);
      }
    };
    set('smtp_host', 'smtp.gmail.com');
    set('smtp_port', '587');
    set('smtp_secure', 'tls');
    alert('Gmail ayarları dolduruldu.\nKullanıcı alanına Gmail adresinizi,\nŞifre alanına Google Uygulama Şifrenizi girin ve Kaydet\'e basın.');
  });
})();
</script>

<?php require_once __DIR__ . '/../core/footer.php'; ?>
