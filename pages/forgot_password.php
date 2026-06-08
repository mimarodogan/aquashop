<?php
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/mailer.php';
$page = 'login'; $title = 'Şifremi Unuttum';
$err = null; $sent = false;

if ($_SERVER['REQUEST_METHOD']==='POST' && csrf_check($_POST['csrf'] ?? null)) {
    $email = trim((string)($_POST['email'] ?? ''));
    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $err = 'Geçerli bir e-posta adresi girin.';
    } else {
        // Kullanıcıyı bul (case-insensitive + whitespace toleransı)
        $st = db()->prepare('SELECT id, name, email FROM users WHERE LOWER(TRIM(email))=LOWER(TRIM(?)) LIMIT 1');
        $st->execute([$email]);
        $user = $st->fetch();
        // O-3: enumeration log'unu kapat (artık "found=YES" sızdırmıyoruz)
        error_log('[forgot] reset requested');

        if ($user) {
            // Y-2 GÜVENLİK: plain token kullanıcıya gider, DB'de SADECE sha256 hash saklanır.
            // DB sızıntısı veya read-only SQLi durumunda aktif reset URL'leri sömürülemez.
            $token     = bin2hex(random_bytes(32));
            $tokenHash = hash('sha256', $token);
            $expires   = date('Y-m-d H:i:s', time() + 3600);
            db()->prepare('UPDATE users SET password_reset_token=?, password_reset_expires=? WHERE id=?')
                ->execute([$tokenHash, $expires, (int)$user['id']]);

            // Mail için ABSOLUTE URL gerekli — sırayla: admin ayarı → SITE_URL sabit → $_SERVER fallback
            $adminUrl = trim((string)setting('site_url',''));
            if ($adminUrl !== '') {
                $base = rtrim($adminUrl, '/');
            } elseif (defined('SITE_URL') && SITE_URL !== '') {
                $base = rtrim(SITE_URL, '/');
            } else {
                $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || ($_SERVER['SERVER_PORT'] ?? '') == 443 ? 'https' : 'http';
                $base = $scheme . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost');
            }
            $resetUrl = $base . '/sifre-sifirla?token=' . urlencode($token);
            $body  = '<p>Merhaba ' . e($user['name']) . ',</p>';
            $body .= '<p>Hesabınız için şifre sıfırlama talebi aldık. Aşağıdaki butona tıklayarak yeni şifre belirleyebilirsiniz.</p>';
            $body .= '<p style="font-size:13px;color:#5F5F5F">Bu bağlantı <strong>1 saat</strong> içinde geçerlidir. Talep sizden gelmediyse bu e-postayı yok sayabilirsiniz; mevcut şifreniz değişmez.</p>';
            $html = mail_template('Şifre Sıfırlama', $body, 'Yeni Şifre Belirle', $resetUrl);

            // Plain-text versiyon — HTML render edilmeyen istemciler için açık URL
            $text  = "Merhaba " . $user['name'] . ",\n\n";
            $text .= "Hesabınız için şifre sıfırlama talebi aldık.\n\n";
            $text .= "Yeni şifre belirlemek için bu bağlantıya gidin:\n";
            $text .= $resetUrl . "\n\n";
            $text .= "Bağlantı 1 saat içinde geçerlidir. Talep sizden gelmediyse bu e-postayı yok sayın.\n";

            $sentOk = mail_send($user['email'], 'Şifre Sıfırlama', $html, $text);
            // O-3: kullanıcı varlık bilgisini log'a yazma — sadece sonucu logla
            error_log('[forgot] mail_send result=' . ($sentOk ? 'OK' : 'FAIL'));
        }
        // Güvenlik: kullanıcı var/yok bilgisi sızdırmamak için her durumda aynı mesaj
        $sent = true;
    }
}

include __DIR__ . '/../includes/header.php';
?>
<section class="page-header">
  <div class="container">
    <span class="kicker">Hesap</span>
    <h1 style="margin-top:10px">Şifremi Unuttum</h1>
    <div class="breadcrumb"><a href="<?= url('home') ?>">Anasayfa</a><span>/</span><a href="<?= url('login') ?>">Giriş</a><span>/</span>Şifremi Unuttum</div>
  </div>
</section>

<section><div class="container" style="max-width:480px">
  <div class="panel">
    <?php if ($sent): ?>
      <div class="alert alert-ok" role="status">
        Eğer bu e-posta sistemimizde kayıtlıysa, şifre sıfırlama bağlantısı az önce gönderildi.
        Birkaç dakika içinde gelmezse spam/önemsiz klasörünü kontrol edin.
      </div>
      <div style="margin-top:18px;text-align:center">
        <a href="<?= url('login') ?>" class="link-arrow">Giriş sayfasına dön →</a>
      </div>
    <?php else: ?>
      <h3 style="margin-bottom:8px">Şifrenizi Sıfırlayın</h3>
      <p class="muted" style="margin-bottom:22px">Hesabınıza ait e-posta adresini girin; size yeni şifre oluşturma bağlantısı gönderelim.</p>
      <?php if ($err): ?><div class="alert alert-err" role="alert"><?= e($err) ?></div><?php endif; ?>
      <form method="post" novalidate style="display:grid;gap:16px">
        <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
        <div class="field">
          <label for="fp-email">E-posta</label>
          <input id="fp-email" name="email" type="email" required autofocus value="<?= e($_POST['email'] ?? '') ?>" placeholder="ornek@eposta.com">
        </div>
        <button class="btn btn-primary btn-block btn-lg">Sıfırlama Bağlantısı Gönder</button>
        <div style="text-align:center;margin-top:8px">
          <a href="<?= url('login') ?>" style="color:var(--muted-text);font-size:13px;text-decoration:underline">Giriş sayfasına dön</a>
        </div>
      </form>
    <?php endif; ?>
  </div>
</div></section>
<?php include __DIR__ . '/../includes/footer.php'; ?>
