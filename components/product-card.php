<?php
// Beklenen değişkenler:
//   $p (ürün satırı: id,name,slug,price,old_price,image,cat_name,stock,has_variations,price_on_request)
//   $favIds (favori product_id'leri — opsiyonel)
//   $cardBack (favori toggle sonrası dönüş — opsiyonel)
$favIds   = isset($favIds)   ? $favIds   : (function_exists('fav_ids') ? fav_ids() : array());
$cardBack = isset($cardBack) ? $cardBack : 'products.php';

$inStock        = (int)($p['stock'] ?? 0) > 0;
$hasDiscount    = !empty($p['old_price']) && (float)$p['old_price'] > (float)$p['price'];
$priceOnRequest = !empty($p['price_on_request']);

// Varyasyon fiyat aralığı
$hasVar = !empty($p['has_variations']);
$pmin = $pmax = 0;
if ($hasVar) {
    require_once __DIR__ . '/../includes/variations.php';
    [$pmin, $pmax] = product_price_range((int)$p['id']);
}
?>
<div class="card" style="position:relative">
  <form method="post" action="<?= SITE_URL ?>/favorite-toggle.php" class="fav-form">
    <input type="hidden" name="csrf"  value="<?= e(csrf_token()) ?>">
    <input type="hidden" name="id"    value="<?= (int)$p['id'] ?>">
    <input type="hidden" name="back"  value="<?= e($cardBack) ?>">
    <button class="fav-btn <?= in_array((int)$p['id'], $favIds) ? 'active' : '' ?>" type="submit" aria-label="Favorilere ekle">
      <?= ic('heart', '', 16) ?>
    </button>
  </form>

  <?php if (function_exists('compare_enabled') && compare_enabled()):
    $__inCompare = compare_has((int)$p['id']);
  ?>
    <form method="post" action="<?= SITE_URL ?>/karsilastir" class="compare-form" style="position:absolute;top:12px;right:54px;z-index:2;margin:0">
      <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
      <input type="hidden" name="action" value="<?= $__inCompare ? 'remove' : 'add' ?>">
      <input type="hidden" name="product_id" value="<?= (int)$p['id'] ?>">
      <input type="hidden" name="back" value="<?= e($cardBack) ?>">
      <button type="submit" class="compare-btn <?= $__inCompare ? 'active' : '' ?>" aria-label="<?= $__inCompare ? 'Karşılaştırmadan çıkar' : 'Karşılaştırmaya ekle' ?>" title="<?= $__inCompare ? 'Karşılaştırmadan çıkar' : 'Karşılaştır' ?>">
        <?php if ($__inCompare): ?>✓<?php else: ?>⇄<?php endif; ?>
      </button>
    </form>
  <?php endif; ?>

  <a href="<?= SITE_URL ?><?= e(url('product', ['slug' => $p['slug']])) ?>" style="display:flex;flex-direction:column;flex:1;text-decoration:none">
    <div class="card-img">
      <?php if (!$inStock && !$hasVar): ?>
        <span class="badge badge-out">Stokta Yok</span>
      <?php elseif ($hasDiscount): ?>
        <span class="badge badge-sale">İndirim</span>
      <?php elseif (!$hasVar && $inStock && (int)$p['stock'] <= low_stock_threshold()): ?>
        <?= stock_badge_html((int)$p['stock']) ?>
      <?php endif; ?>

      <?php if (!empty($p['image'])): ?>
        <img loading="lazy" decoding="async" width="600" height="600"
             src="<?= e($p['image']) ?>" alt="<?= e($p['name']) ?>">
      <?php else: ?>
        <span class="ph"><?= e(mb_substr($p['name'], 0, 1)) ?></span>
      <?php endif; ?>

      <!-- Hover overlay -->
      <div class="card-overlay" aria-hidden="true">
        <span>
          <?= ic('eye', '', 13) ?>
          Görüntüle
        </span>
      </div>
    </div>

    <div class="card-body">
      <span class="cat"><?= e($p['cat_name'] ?? 'Genel') ?></span>
      <h3><?= e($p['name']) ?></h3>
      <div class="card-foot">
        <span class="price">
          <?php if ($priceOnRequest && (float)($p['price'] ?? 0) <= 0): ?>
            <span style="font-size:12px;font-weight:500;color:var(--muted-text);letter-spacing:.06em">İletişime Geçin</span>
          <?php elseif ($hasVar && $pmin > 0 && $pmax > 0 && $pmin !== $pmax): ?>
            <?= money($pmin) ?> – <?= money($pmax) ?>
          <?php elseif ($hasVar && $pmin > 0): ?>
            <?= money($pmin) ?>
          <?php else: ?>
            <?= money($p['price']) ?>
            <?php if ($hasDiscount): ?>
              <span class="price-old"><?= money($p['old_price']) ?></span>
            <?php endif; ?>
          <?php endif; ?>
        </span>
        <?php if ($priceOnRequest): ?>
          <span class="icon-btn" role="img" aria-label="İletişime geçin" title="İletişime Geçin"><?= ic('phone', '', 17) ?></span>
        <?php else: ?>
          <span class="icon-btn" role="img" aria-label="Sepete ekle"><?= ic('cart', '', 17) ?></span>
        <?php endif; ?>
      </div>
    </div>
  </a>
</div>
