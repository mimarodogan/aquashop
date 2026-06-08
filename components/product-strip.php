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
$__sectionStyle = $__bg === 'cream'
    ? 'background:var(--cream);border-top:1px solid var(--gold-border);border-bottom:1px solid var(--gold-border)'
    : '';
$cardBack = $stripCardBack ?? 'index.php';
?>
<section style="<?= e($__sectionStyle) ?>">
  <div class="container">
    <div class="section-head">
      <?php if (!empty($stripKicker)): ?><span class="kicker"><?= e($stripKicker) ?></span><?php endif; ?>
      <h2><?= e($stripTitle) ?></h2>
      <div class="ornament-divider"><span class="line"></span><span class="diamond"></span><span class="line"></span></div>
    </div>
    <div class="grid">
      <?php $favIds = function_exists('fav_ids') ? fav_ids() : []; ?>
      <?php foreach ($stripItems as $p): ?>
        <?php include __DIR__ . '/product-card.php'; ?>
      <?php endforeach; ?>
    </div>
  </div>
</section>
<?php
// Cleanup so next include doesn't leak state
unset($stripTitle, $stripKicker, $stripItems, $stripBg, $stripCardBack);
?>
