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
$authSiteName = trim((string)setting('site_name','')) ?: SITE_NAME_FALLBACK;
$authTagline = trim((string)setting('site_tagline',''));
?>
<link rel="stylesheet" href="<?= SITE_URL ?>/assets/css/auth.css">

<section class="aq-auth-page aq-auth-page-clean">
  <div class="aq-container">
    <div class="aq-auth-clean-wrap">
      <div class="aq-auth-card aq-auth-card-clean aq-auth-card-compact">
        <div class="aq-auth-brand-mini">
          <a href="<?= url('home') ?>" class="aq-auth-logo-mini" aria-label="<?= e($authSiteName) ?> Ana Sayfa"><?= e($authSiteName) ?></a>
          <?php if ($authTagline !== ''): ?><span><?= e($authTagline) ?></span><?php endif; ?>
        </div>

        <div class="aq-auth-card-head aq-auth-card-head-center">
          <span>Hesap güvenliği</span>
          <h1>Şifremi Unuttum</h1>
          <p>Hesabınıza ait e-posta adresini girin; yeni şifre bağlantısını gönderelim.</p>
        </div>

        <?php if ($sent): ?>
          <div class="aq-auth-state aq-auth-state-ok" role="status">
            <?= e3d('mail', 30) ?>
            <strong>Bağlantı gönderildi</strong>
            <p>Eğer bu e-posta sistemimizde kayıtlıysa, şifre sıfırlama bağlantısı az önce gönderildi.</p>
          </div>
          <a href="<?= url('login') ?>" class="aq-auth-submit aq-auth-submit-link">
            <i class="bi bi-arrow-left" aria-hidden="true"></i>
            Giriş sayfasına dön
          </a>
        <?php else: ?>
          <?php if ($err): ?><div class="aq-auth-state aq-auth-state-error" role="alert"><?= e($err) ?></div><?php endif; ?>
          <form method="post" novalidate class="aq-auth-form">
            <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
            <label class="aq-auth-field">
              <span>E-posta Adresi</span>
              <div class="aq-auth-input">
                <?= e3d('mail', 18) ?>
                <input id="fp-email" name="email" type="email" required autofocus value="<?= e($_POST['email'] ?? '') ?>" placeholder="ornek@eposta.com">
              </div>
            </label>
            <button class="aq-auth-submit">
              <?= e3d('mail', 18) ?>
              Sıfırlama Bağlantısı Gönder
            </button>
            <p class="aq-auth-switch"><a href="<?= url('login') ?>">Giriş sayfasına dön</a></p>
          </form>
        <?php endif; ?>
      </div>
    </div>
  </div>
</section>
<?php include __DIR__ . '/../includes/footer.php'; ?>
