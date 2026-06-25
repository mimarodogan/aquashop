<?php
/**
 * Product Strip — başlık + grid içinde ürün kartları.
 *
 * Beklenen değişkenler:
 *   $stripTitle    string         (ör. "Çok Satanlar")
 *   $stripKicker   string         (ör. "Son 30 gün")
 *   $stripItems    array          (products satırları — product-card.php'nin beklediği format)
 *   $stripBg       string|null    ('cream' = krem zemin, null = default)
 *   $stripCardBack string|null    (favorite-toggle back URL)
 *
 * $stripItems boşsa hiçbir şey render etmez.
 */
if (empty($stripItems) || !is_array($stripItems)) return;

$__bg = $stripBg ?? null;
$__sectionClass = $__bg === 'cream' ? 'aq-product-section aq-soft-block' : 'aq-product-section';
$cardBack = $stripCardBack ?? 'index.php';
?>
<section class="<?= e($__sectionClass) ?>">
  <div class="aq-container">
    <div class="aq-section-title-row">
      <div>
        <?php if (!empty($stripKicker)): ?><span><?= e($stripKicker) ?></span><?php endif; ?>
        <h2><?= e($stripTitle) ?></h2>
      </div>
    </div>
    <div class="aq-carousel-wrap" data-carousel data-visible-desktop="5" data-visible-tablet="3" data-visible-mobile="2">
      <div class="aq-carousel-controls">
        <button type="button" class="aq-carousel-arrow aq-products-prev" data-dir="-1" aria-label="Geri" disabled><i class="bi bi-chevron-left"></i></button>
        <button type="button" class="aq-carousel-arrow aq-products-next" data-dir="1" aria-label="İleri"><i class="bi bi-chevron-right"></i></button>
      </div>
      <div class="aq-products-viewport">
        <div class="aq-products-track">
          <?php $favIds = function_exists('fav_ids') ? fav_ids() : []; ?>
          <?php foreach ($stripItems as $p): ?>
            <?php include __DIR__ . '/product-card.php'; ?>
          <?php endforeach; ?>
        </div>
      </div>
    </div>
  </div>
</section>
<?php
// Cleanup so next include doesn't leak state
unset($stripTitle, $stripKicker, $stripItems, $stripBg, $stripCardBack);
?>
