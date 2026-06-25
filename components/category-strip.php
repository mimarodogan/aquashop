<?php
// Beklenen: $cats (kategoriler dizisi)
$cats = isset($cats) ? $cats : category_all();
if (!$cats) return;
?>
<section class="aq-category-section" aria-label="Kategoriler" data-carousel data-visible-desktop="8" data-visible-tablet="5" data-visible-mobile="3">
  <div class="aq-container">
    <div class="aq-section-title-row aq-title-compact">
      <div>
        <span>Mağaza</span>
        <h2>Kategoriler</h2>
      </div>
    </div>
    <div class="aq-category-slider-wrap">
      <div>
        <button type="button" class="aq-category-prev aq-category-side-arrow aq-category-side-prev" data-dir="-1" aria-label="Sola kaydır"><i class="bi bi-chevron-left"></i></button>
        <button type="button" class="aq-category-next aq-category-side-arrow aq-category-side-next" data-dir="1" aria-label="Sağa kaydır"><i class="bi bi-chevron-right"></i></button>
      </div>
      <div class="aq-category-viewport">
        <div class="aq-category-track">
          <?php foreach ($cats as $c): ?>
            <a class="aq-category-card" href="<?= e(url('category', ['slug'=>$c['slug']])) ?>">
              <span>
                <?php if (!empty($c['image'])): ?>
                  <img loading="lazy" decoding="async" width="120" height="120" src="<?= e($c['image']) ?>" alt="<?= e($c['name']) ?>">
                <?php else: ?>
                  <strong><?= e(mb_substr($c['name'],0,1)) ?></strong>
                <?php endif; ?>
              </span>
              <strong><?= e($c['name']) ?></strong>
            </a>
          <?php endforeach; ?>
        </div>
      </div>
    </div>
  </div>
</section>
