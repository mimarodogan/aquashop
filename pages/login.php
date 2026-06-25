<?php
require_once __DIR__ . '/../includes/functions.php';
$title = 'Giriş Yap'; $err = null;

if ($_SERVER['REQUEST_METHOD']==='POST' && csrf_check(isset($_POST['csrf']) ? $_POST['csrf'] : null)) {
    $email = trim(isset($_POST['email']) ? $_POST['email'] : '');
    $pw    = isset($_POST['password']) ? $_POST['password'] : '';
    $ip    = client_ip();

    // K-5 Brute-force koruması: DB tabanlı persistent throttle (session ile bypass edilemez)
    // IP başına VEYA email başına 15 dakikada 5 başarısız deneme → kilit.
    if (login_throttle_blocked($ip, $email)) {
        $err = 'Çok fazla başarısız deneme. 15 dakika sonra tekrar deneyin.';
        sleep(2);
    } else {
        $st = db()->prepare('SELECT * FROM users WHERE email=?'); $st->execute(array($email)); $u = $st->fetch();

        // O-4 Timing leak fix: kullanıcı yoksa bile dummy hash'e password_verify çağır
        // (kullanıcı var/yok ayrımı, password_verify süresine bakarak yapılamasın)
        $ok = false;
        if ($u) {
            $ok = password_verify($pw, $u['password']);
        } else {
            password_verify($pw, dummy_password_hash()); // sabit-zaman için, sonucu kullanmıyoruz
        }

        if ($ok && $u) {
            login_throttle_record($ip, $email, true);
            session_regenerate_id(true);    // Session fixation koruması
            csrf_rotate();                   // Y-4: token rotation
            $_SESSION['user_id'] = $u['id'];
            $_SESSION['_started_at'] = time();
            flash_set('success','Hoş geldiniz, '.$u['name']);
            redirect($u['role']==='admin' ? url('admin') : url('account'));
        } else {
            login_throttle_record($ip, $email, false);
            $err = 'E-posta veya şifre hatalı.';
            usleep(random_int(200000, 400000));
        }
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
      <div class="aq-auth-card aq-auth-card-clean">
        <div class="aq-auth-brand-mini">
          <a href="<?= url('home') ?>" class="aq-auth-logo-mini" aria-label="<?= e($authSiteName) ?> Ana Sayfa"><?= e($authSiteName) ?></a>
          <?php if ($authTagline !== ''): ?><span><?= e($authTagline) ?></span><?php endif; ?>
        </div>

        <div class="aq-auth-card-head aq-auth-card-head-center">
          <span>Hesabınıza erişin</span>
          <h1>Giriş Yap</h1>
          <p>Siparişlerinizi takip etmek ve favorilerinize ulaşmak için giriş yapın.</p>
        </div>

        <?php if ($err) toast_now('error', $err); ?>
        <form method="post" autocomplete="on" class="aq-auth-form">
        <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
          <label class="aq-auth-field">
            <span>E-posta Adresi</span>
            <div class="aq-auth-input">
              <?= e3d('mail', 18) ?>
              <input type="email" name="email" placeholder="ornek@eposta.com" aria-label="E-posta adresi" required>
            </div>
          </label>

          <label class="aq-auth-field">
            <span>Şifre</span>
            <div class="aq-auth-input">
              <?= e3d('lock', 18) ?>
              <input type="password" name="password" id="pw" placeholder="Şifreniz" aria-label="Şifre" required>
              <button class="aq-auth-password-toggle" type="button" onclick="var i=document.getElementById('pw');i.type=i.type==='password'?'text':'password'" aria-label="Şifreyi göster/gizle">
                <?= e3d('eye', 18) ?>
              </button>
            </div>
          </label>

          <div class="aq-auth-options">
            <label class="aq-auth-check">
              <input type="checkbox" name="remember" value="1">
              <span>Beni hatırla</span>
            </label>
            <a href="<?= url('forgot-password') ?>">Şifremi unuttum</a>
          </div>

          <button type="submit" class="aq-auth-submit">
            <?= e3d('login', 18) ?>
            Giriş Yap
          </button>

          <p class="aq-auth-switch">Hesabınız yok mu? <a href="<?= url('register') ?>">Üye Ol</a></p>
        </form>
      </div>
    </div>
  </div>
</section>

<?php include __DIR__ . '/../includes/footer.php'; ?>
