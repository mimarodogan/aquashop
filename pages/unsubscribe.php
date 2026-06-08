<?php
require_once __DIR__ . '/../includes/functions.php';
$email = trim($_GET['email'] ?? '');
$token = trim($_GET['token'] ?? '');
$ok = false;
if ($email && $token) {
    $st = db()->prepare('SELECT id FROM newsletter_subscribers WHERE email=? AND token=?');
    $st->execute(array($email,$token));
    if ($st->fetch()) {
        db()->prepare('UPDATE newsletter_subscribers SET is_active=0, unsubscribed_at=NOW() WHERE email=?')->execute(array($email));
        $ok = true;
    }
}
$title = 'Bülten Aboneliği';
include __DIR__ . '/../includes/header.php';
?>
<section class="page-header"><div class="container"><span class="kicker">Bülten</span><h1 style="margin-top:10px"><?= $ok ? 'Abonelikten Çıkıldı' : 'Bağlantı Geçersiz' ?></h1></div></section>
<section><div class="container" style="max-width:520px"><div class="panel center" style="padding:40px">
  <?php if ($ok): ?>
    <p>E-posta adresiniz (<strong><?= e($email) ?></strong>) bülten listesinden çıkarıldı. Artık duyuru almayacaksınız.</p>
    <p class="muted" style="margin-top:14px">Vazgeçtiniz mi? <a href="newsletter-subscribe.php?email=<?= urlencode($email) ?>" style="color:var(--gold)">Tekrar abone olun</a>.</p>
  <?php else: ?>
    <p>Abonelik kaydı bulunamadı veya bağlantı geçersiz.</p>
  <?php endif; ?>
  <a class="btn btn-primary" style="margin-top:24px" href="<?= url('home') ?>">Anasayfa</a>
</div></div></section>
<?php include __DIR__ . '/../includes/footer.php'; ?>
