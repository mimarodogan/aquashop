<?php
require_once __DIR__ . '/../includes/functions.php';
$page='about';

$st = db()->prepare("SELECT * FROM pages WHERE slug='hakkimizda' AND is_published=1");
$st->execute();
$pg = $st->fetch();
$title = $pg ? $pg['title'] : 'Hakkımızda';

include __DIR__ . '/../includes/header.php';
?>
<section class="page-header">
  <div class="container">
    <span class="kicker">Kurumsal</span>
    <h1 style="margin-top:10px"><?= e($title) ?></h1>
    <div class="breadcrumb"><a href="<?= url('home') ?>">Anasayfa</a><span>/</span>Hakkımızda</div>
  </div>
</section>
<section>
  <div class="container" style="padding-top:40px;padding-bottom:56px">
    <?php if ($pg && !empty($pg['cover_image'])): ?>
      <img loading="lazy" decoding="async"
           src="<?= e($pg['cover_image']) ?>"
           alt="<?= e($title) ?>"
           style="float:left;width:min(420px,100%);max-width:48%;aspect-ratio:4/3;object-fit:cover;border-radius:var(--radius-lg);margin:0 40px 24px 0;box-shadow:var(--shadow-sm)">
    <?php endif; ?>
    <div class="cms-content">
      <?php if ($pg): ?>
        <span class="kicker">Hikayemiz</span>
        <div style="margin-top:14px"><?= embed_videos($pg["content"]) ?></div>
      <?php else: ?>
        <p class="muted">Bu sayfa henüz oluşturulmadı. Admin > Sayfalar bölümünden "Hakkımızda" başlıklı bir sayfa ekleyin.</p>
      <?php endif; ?>
    </div>
    <div style="clear:both"></div>
  </div>
</section>

<style>
@media(max-width:680px){
  .container img[style*="float:left"]{
    float:none !important;
    width:100% !important;
    max-width:100% !important;
    margin:0 0 24px 0 !important;
    aspect-ratio:16/9;
  }
}
</style>
<!-- inline style removed: see assets/css/ -->
<?php include __DIR__ . '/../includes/footer.php'; ?>
