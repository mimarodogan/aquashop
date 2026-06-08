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
?>
<link rel="stylesheet" href="<?= SITE_URL ?>/assets/css/auth.css">

<div class="auth-wrap">
  <div class="auth-card">
    <div class="auth-tabs">
      <a href="<?= url('login') ?>" class="active">Giriş Yap</a>
      <a href="<?= url('register') ?>">Üye Ol</a>
    </div>
    <div class="auth-body">
      <?php if ($err) toast_now('error', $err); ?>
      <form method="post" autocomplete="on">
        <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
        <div class="field"><input type="email" name="email" placeholder="E-posta" aria-label="E-posta adresi" required></div>
        <div class="field input-icon">
          <input type="password" name="password" id="pw" placeholder="Şifre" aria-label="Şifre" required>
          <button type="button" onclick="var i=document.getElementById('pw');i.type=i.type==='password'?'text':'password'" aria-label="Şifreyi göster/gizle">
            <?= ic('eye', '', 18) ?>
          </button>
        </div>
        <div style="text-align:right;margin:6px 0 6px;font-size:13px"><a href="<?= url('forgot-password') ?>" style="color:var(--leaf);text-decoration:underline">Şifremi unuttum</a></div>
        <button type="submit" class="auth-submit">Giriş Yap</button>
      </form>
    </div>
  </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
