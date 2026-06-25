<?php
require_once __DIR__ . '/../includes/functions.php';
$title = 'Üye Ol'; $err = null;

if ($_SERVER['REQUEST_METHOD']==='POST' && csrf_check(isset($_POST['csrf']) ? $_POST['csrf'] : null)) {
    $first  = trim(isset($_POST['first_name']) ? $_POST['first_name'] : '');
    $last   = trim(isset($_POST['last_name'])  ? $_POST['last_name']  : '');
    $email  = trim(isset($_POST['email'])      ? $_POST['email']      : '');
    $pw     = isset($_POST['password'])        ? $_POST['password']   : '';
    $birth  = trim(isset($_POST['birth_date']) ? $_POST['birth_date'] : '');
    $phone  = trim(isset($_POST['phone'])      ? $_POST['phone']      : '');
    $emailC = !empty($_POST['email_consent']) ? 1 : 0;
    $smsC   = !empty($_POST['sms_consent'])   ? 1 : 0;
    $terms  = !empty($_POST['terms']);

    // gg.aa.yyyy → YYYY-MM-DD
    $birthSql = null;
    if ($birth !== '') {
        $parts = preg_split('/[\.\/-]/', $birth);
        if (count($parts) === 3) {
            list($d,$m,$y) = $parts;
            if (checkdate((int)$m,(int)$d,(int)$y)) $birthSql = sprintf('%04d-%02d-%02d',$y,$m,$d);
        }
        if ($birthSql === null) {
            // YYYY-MM-DD da kabul (HTML5 input)
            if (preg_match('/^\d{4}-\d{2}-\d{2}$/',$birth)) $birthSql = $birth;
        }
    }

    if (!$first || !$last || !$email || strlen($pw) < 10) {                      // O-1: 10 karakter min
        $err = 'Lütfen tüm alanları doldurun (şifre en az 10 karakter).';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 190) {
        $err = 'Geçerli bir e-posta adresi girin.';
    } elseif (strlen($pw) > 200) {
        $err = 'Şifre en fazla 200 karakter olabilir.';
    } elseif (!$terms) {
        $err = 'Üyelik koşullarını kabul etmelisiniz.';
    } else {
        $fullName = $first . ' ' . $last;
        try {
            $st = db()->prepare('INSERT INTO users (name,first_name,last_name,email,password,phone,birth_date,email_consent,sms_consent,role) VALUES (?,?,?,?,?,?,?,?,?,?)');
            $st->execute(array($fullName,$first,$last,$email,password_hash($pw,PASSWORD_DEFAULT),$phone,$birthSql,$emailC,$smsC,'customer'));
            $newUserId = (int)db()->lastInsertId();
            // Y-11 GÜVENLİK: session fixation koruması (saldırgan kurbana sabitlenmiş SID veremesin)
            session_regenerate_id(true);
            csrf_rotate();                                                       // Y-4
            $_SESSION['user_id'] = $newUserId;
            flash_set('success','Hesabınız oluşturuldu.');

            // Hoş geldin maili gönder
            try {
                require_once __DIR__ . '/../includes/mailer.php';
                $tmpl = mail_template_get(
                    'welcome',
                    ['{{isim}}' => $first],
                    'Hoş Geldiniz — ' . setting('site_name', ''),
                    '<p>Merhaba <strong>' . htmlspecialchars($first, ENT_QUOTES) . '</strong>,</p>'
                    . '<p>{{site_adi}} ailesine hoş geldiniz! Hesabınız başarıyla oluşturuldu.</p>'
                    . '<p>Binlerce ürün arasından kolayca alışveriş yapabilir, siparişlerinizi takip edebilirsiniz.</p>'
                );
                $body = mail_template($tmpl['subject'], $tmpl['body_html'], 'Alışverişe Başla', url('products'));
                mail_send($email, $tmpl['subject'], $body);
            } catch (Exception $e) { /* mail gönderimi isteğe bağlı */ }

            redirect(url('account'));
        } catch (PDOException $e) {
            // O-3 (enumeration) + O-2 (PDO leak): hem 1062 hem diğer hatalar için NÖTR mesaj.
            // Saldırgan duplicate e-posta üzerinden hesap varlığını sıralayamasın.
            error_log('[register] PDOException: ' . $e->getMessage());
            $err = 'Kayıt sırasında bir sorun oluştu. Lütfen daha sonra tekrar deneyin veya farklı bir e-posta ile deneyin.';
        }
    }
}

include __DIR__ . '/../includes/header.php';
?>
<link rel="stylesheet" href="<?= SITE_URL ?>/assets/css/auth.css">

<div class="auth-wrap">
  <div class="auth-card">
    <div class="auth-tabs">
      <a href="<?= url('login') ?>">Giriş Yap</a>
      <a href="<?= url('register') ?>" class="active">Üye Ol</a>
    </div>
    <div class="auth-body">
      <?php if ($err) toast_now('error', $err); ?>
      <form method="post" autocomplete="on">
        <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">

        <div class="field"><input type="text" name="first_name" placeholder="Adınız" aria-label="Adınız" required value="<?= e(isset($_POST['first_name'])?$_POST['first_name']:'') ?>"></div>
        <div class="field"><input type="text" name="last_name"  placeholder="Soyadınız" aria-label="Soyadınız" required value="<?= e(isset($_POST['last_name'])?$_POST['last_name']:'') ?>"></div>
        <div class="field"><input type="email" name="email" placeholder="E-posta" aria-label="E-posta adresi" required value="<?= e(isset($_POST['email'])?$_POST['email']:'') ?>"></div>

        <div class="field input-icon">
          <input type="password" name="password" id="pw" placeholder="Şifre (en az 10 karakter)" aria-label="Şifre" minlength="10" required>
          <button type="button" onclick="var i=document.getElementById('pw');i.type=i.type==='password'?'text':'password'" aria-label="Şifreyi göster/gizle">
            <?= e3d('eye', 18) ?>
          </button>
        </div>

        <div class="field"><input type="text" name="birth_date" id="reg-birth" placeholder="gg/aa/yyyy" aria-label="Doğum tarihi (gün/ay/yıl)" pattern="\d{2}/\d{2}/\d{4}" inputmode="numeric" maxlength="10" autocomplete="bday" title="Gün/Ay/Yıl — örn. 01/07/1990"></div>

        <div class="field">
          <div class="phone-row">
            <div class="cc"><span class="flag">🇹🇷</span><span>+90</span></div>
            <input type="tel" name="phone" placeholder="501 234 56 78" aria-label="Cep telefonu" inputmode="tel">
          </div>
        </div>

        <div class="consents" aria-label="İzinler ve üyelik onayları">
          <label class="consent-row">
            <input type="checkbox" name="email_consent" value="1" <?= !empty($_POST['email_consent']) ? 'checked' : '' ?>>
            <span>Kampanya, duyuru ve bilgilendirmelerden <strong>e-posta</strong> ile haberdar olmak istiyorum.</span>
          </label>
          <label class="consent-row">
            <input type="checkbox" name="sms_consent" value="1" <?= !empty($_POST['sms_consent']) ? 'checked' : '' ?>>
            <span>Kampanya, duyuru ve bilgilendirmelerden <strong>SMS</strong> ile haberdar olmak istiyorum.</span>
          </label>
          <label class="consent-row consent-row-required">
            <input type="checkbox" name="terms" value="1" required <?= !empty($_POST['terms']) ? 'checked' : '' ?>>
            <span><a href="<?= url('page', ['slug'=>'uyelik-kosullari']) ?>" target="_blank" rel="noopener">Üyelik koşullarını</a> ve <a href="<?= url('page', ['slug'=>'kvkk']) ?>" target="_blank" rel="noopener">kişisel verilerimin korunmasını</a> kabul ediyorum.</span>
          </label>
        </div>

        <button type="submit" class="auth-submit">Üye Ol</button>
      </form>
    </div>
  </div>
</div>

<script>
/* Doğum tarihi otomatik bölümleme: 01071981 → 01/07/1981 */
(function () {
  var el = document.getElementById('reg-birth');
  if (!el) return;
  el.addEventListener('input', function () {
    var d = el.value.replace(/\D/g, '').slice(0, 8); // sadece rakam, en fazla 8 hane
    var out = d.slice(0, 2);
    if (d.length >= 3) out += '/' + d.slice(2, 4);
    if (d.length >= 5) out += '/' + d.slice(4, 8);
    el.value = out;
  });
})();
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
