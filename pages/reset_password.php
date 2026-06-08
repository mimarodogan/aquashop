<?php
require_once __DIR__ . '/../includes/functions.php';
$page = 'login'; $title = 'Yeni Şifre Belirle';
$err = null; $ok = false; $tokenValid = false; $user = null;

$token = trim((string)($_GET['token'] ?? $_POST['token'] ?? ''));

if ($token !== '' && preg_match('/^[a-f0-9]{64}$/', $token)) {
    // Y-2 GÜVENLİK: DB'de hash saklı, gelen token'ı hash'leyip karşılaştır
    $tokenHash = hash('sha256', $token);
    $st = db()->prepare('SELECT id, name, email, password_reset_expires FROM users WHERE password_reset_token=? LIMIT 1');
    $st->execute([$tokenHash]);
    $user = $st->fetch();
    if ($user && strtotime($user['password_reset_expires']) > time()) {
        $tokenValid = true;
    } else {
        $err = 'Bağlantı geçersiz veya süresi dolmuş. Lütfen yeni bir şifre sıfırlama isteği oluşturun.';
    }
} else {
    $err = 'Geçersiz bağlantı.';
}

if ($tokenValid && $_SERVER['REQUEST_METHOD']==='POST' && csrf_check($_POST['csrf'] ?? null)) {
    $pw1 = (string)($_POST['password']  ?? '');
    $pw2 = (string)($_POST['password2'] ?? '');

    if (strlen($pw1) < 10) {                           // O-1: 10 karakter min
        $err = 'Şifre en az 10 karakter olmalı.';
    } elseif ($pw1 !== $pw2) {
        $err = 'Şifreler eşleşmiyor.';
    } else {
        $hash = password_hash($pw1, PASSWORD_DEFAULT);
        db()->prepare('UPDATE users SET password=?, password_reset_token=NULL, password_reset_expires=NULL WHERE id=?')
            ->execute([$hash, (int)$user['id']]);
        // Y-3 GÜVENLİK: şifre değişti — session ID yenile (varsa eski oturum invalidate'lensin)
        session_regenerate_id(true);
        csrf_rotate();                                  // Y-4: token rotation
        $ok = true;
        flash_set('success','Şifreniz güncellendi. Yeni şifrenizle giriş yapabilirsiniz.');
    }
}

include __DIR__ . '/../includes/header.php';
?>
<section class="page-header">
  <div class="container">
    <span class="kicker">Hesap</span>
    <h1 style="margin-top:10px">Yeni Şifre Belirle</h1>
  </div>
</section>

<section><div class="container" style="max-width:480px">
  <div class="panel">
    <?php if ($ok): ?>
      <div class="alert alert-ok" role="status">Şifreniz başarıyla güncellendi.</div>
      <div style="margin-top:18px;text-align:center">
        <a href="<?= url('login') ?>" class="btn btn-primary btn-block">Giriş Yap</a>
      </div>
    <?php elseif (!$tokenValid): ?>
      <div class="alert alert-err" role="alert"><?= e($err ?: 'Geçersiz bağlantı.') ?></div>
      <div style="margin-top:18px;text-align:center">
        <a href="<?= url('forgot-password') ?>" class="btn btn-secondary btn-block">Yeniden Sıfırlama İste</a>
      </div>
    <?php else: ?>
      <h3 style="margin-bottom:8px">Yeni Şifrenizi Girin</h3>
      <p class="muted" style="margin-bottom:22px">Merhaba <strong style="color:var(--ink)"><?= e($user['name']) ?></strong>, lütfen yeni şifrenizi belirleyin.</p>
      <?php if ($err): ?><div class="alert alert-err" role="alert"><?= e($err) ?></div><?php endif; ?>
      <form method="post" novalidate style="display:grid;gap:16px">
        <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
        <input type="hidden" name="token" value="<?= e($token) ?>">
        <div class="field">
          <label>Yeni Şifre <span class="req" aria-hidden="true">*</span></label>
          <input name="password" type="password" required minlength="10" autocomplete="new-password" placeholder="En az 10 karakter">
        </div>
        <div class="field">
          <label>Yeni Şifre (Tekrar) <span class="req" aria-hidden="true">*</span></label>
          <input name="password2" type="password" required minlength="10" autocomplete="new-password">
        </div>
        <button class="btn btn-primary btn-block btn-lg">Şifreyi Kaydet</button>
      </form>
    <?php endif; ?>
  </div>
</div></section>
<?php include __DIR__ . '/../includes/footer.php'; ?>
