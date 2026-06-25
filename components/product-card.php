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
<article class="aq-product-card">
  <form method="post" action="<?= SITE_URL ?>/favorite-toggle.php" class="aq-fav-form">
    <input type="hidden" name="csrf"  value="<?= e(csrf_token()) ?>">
    <input type="hidden" name="id"    value="<?= (int)$p['id'] ?>">
    <input type="hidden" name="back"  value="<?= e($cardBack) ?>">
    <button class="aq-fav-btn <?= in_array((int)$p['id'], $favIds) ? 'is-active' : '' ?>" type="submit" aria-label="Favorilere ekle">
      <i class="bi bi-heart aq-fav-off" aria-hidden="true"></i>
      <span class="aq-fav-on"><?= e3d('heart', 20) ?></span>
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

  <a href="<?= e(url('product', ['slug' => $p['slug']])) ?>">
    <div class="aq-product-image">
      <?php if (!$inStock && !$hasVar): ?>
        <span class="aq-badge aq-badge-out">Stokta Yok</span>
      <?php elseif ($hasDiscount): ?>
        <span class="aq-badge aq-badge-sale">İndirim</span>
      <?php elseif (!$hasVar && $inStock && (int)$p['stock'] <= low_stock_threshold()): ?>
        <?= stock_badge_html((int)$p['stock']) ?>
      <?php endif; ?>

      <?php if (!empty($p['image'])): ?>
        <img loading="lazy" decoding="async" width="600" height="600"
             src="<?= e($p['image']) ?>" alt="<?= e($p['name']) ?>">
      <?php else: ?>
        <span class="aq-ph"><?= e(mb_substr($p['name'], 0, 1)) ?></span>
      <?php endif; ?>
    </div>

    <div class="aq-product-info">
      <span class="aq-product-cat"><?= e($p['cat_name'] ?? 'Genel') ?></span>
      <h3><?= e($p['name']) ?></h3>
      <div class="aq-product-bottom">
        <span class="aq-price">
          <?php if ($priceOnRequest && (float)($p['price'] ?? 0) <= 0): ?>
            <strong>İletişime Geçin</strong>
          <?php elseif ($hasVar && $pmin > 0 && $pmax > 0 && $pmin !== $pmax): ?>
            <strong><?= money($pmin) ?> – <?= money($pmax) ?></strong>
          <?php elseif ($hasVar && $pmin > 0): ?>
            <strong><?= money($pmin) ?></strong>
          <?php else: ?>
            <strong><?= money($p['price']) ?></strong>
            <?php if ($hasDiscount): ?>
              <del><?= money($p['old_price']) ?></del>
            <?php endif; ?>
          <?php endif; ?>
        </span>
        <?php if ($priceOnRequest): ?>
          <span class="aq-cart-mini" role="img" aria-label="İletişime geçin" title="İletişime Geçin"><?= e3d('phone', 20) ?></span>
        <?php else: ?>
          <span class="aq-cart-mini" role="img" aria-label="Sepete ekle"><?= e3d('cart', 20) ?></span>
        <?php endif; ?>
      </div>
    </div>
  </a>
</article>
