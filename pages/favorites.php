<?php
require_once __DIR__ . '/../includes/functions.php';
$page = ''; $title = 'Favorilerim';
$user = current_user();
if (!$user) redirect(url('login'));

$rows = db()->prepare("SELECT p.*, f.created_at AS fav_at, c.name AS cat_name
                       FROM favorites f
                       JOIN products p ON p.id = f.product_id
                       LEFT JOIN categories c ON c.id = p.category_id
                       WHERE f.user_id = ?
                       ORDER BY f.created_at DESC");
$rows->execute(array($user['id']));
$rows = $rows->fetchAll();

include __DIR__ . '/../includes/header.php';
?>
<section class="page-header">
  <div class="container">
    <span class="kicker">Hesap</span>
    <h1 style="margin-top:10px">Favorilerim</h1>
    <div class="breadcrumb"><a href="<?= url('home') ?>">Anasayfa</a><span>/</span>Favorilerim</div>
  </div>
</section>
<section>
  <div class="container">
    <?php if (!$rows): ?>
      <div class="panel center" style="padding:80px"><h3>Favori listeniz boş</h3><p class="muted" style="margin:14px 0 24px">Beğendiğiniz ürünleri kalp ikonuna tıklayarak favorilerinize ekleyebilirsiniz.</p><a class="btn btn-primary" href="<?= url('products') ?>">Ürünleri Keşfet</a></div>
    <?php else: ?>
      <p class="muted" style="margin-bottom:24px"><?= count($rows) ?> ürün</p>
      <div class="grid grid-3">
        <?php foreach ($rows as $p): ?>
          <div class="card">
            <a href="<?= e(url('product', ['slug'=>$p['slug']])) ?>">
              <div class="card-img">
                <?php if (!empty($p['image'])): ?>
                  <img loading="lazy" decoding="async" src="<?= e($p['image']) ?>" alt="<?= e($p['name']) ?>" style="width:100%;height:100%;object-fit:cover">
                <?php else: ?>
                  <span class="ph"><?= e(mb_substr($p['name'],0,1)) ?></span>
                <?php endif; ?>
              </div>
              <div class="card-body">
                <span class="cat"><?= e($p['cat_name'] ?? 'Genel') ?></span>
                <h3><?= e($p['name']) ?></h3>
                <div class="card-foot"><span class="price"><?= money($p['price']) ?></span></div>
              </div>
            </a>
            <div style="padding:0 22px 18px">
              <form method="post" action="favorite-toggle.php" style="display:flex;gap:8px">
                <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="id"   value="<?= (int)$p['id'] ?>">
                <input type="hidden" name="back" value="favorites.php">
                <button class="btn btn-secondary btn-sm" style="flex:1">♥ Favoriden Çıkar</button>
              </form>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>
</section>
<?php include __DIR__ . '/../includes/footer.php'; ?>
