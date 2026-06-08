<?php
// Beklenen: $cats (kategoriler dizisi)
$cats = isset($cats) ? $cats : category_all();
if (!$cats) return;
?>
<section class="cat-strip-section" style="padding:60px 0 20px">
  <div class="container">
    <div class="cat-strip">
      <button type="button" class="cat-nav left"  aria-label="Sola kaydır">←</button>
      <div class="cat-track" id="catTrack">
        <?php foreach ($cats as $c): ?>
          <a class="cat-item" href="<?= e(url('products', ['cat'=>$c['slug']])) ?>">
            <span class="cat-circle">
              <?php if (!empty($c['image'])): ?>
                <img loading="lazy" decoding="async" width="120" height="120" src="<?= e($c['image']) ?>" alt="<?= e($c['name']) ?>">
              <?php else: ?>
                <span class="cat-initial"><?= e(mb_substr($c['name'],0,1)) ?></span>
              <?php endif; ?>
            </span>
            <span class="cat-label"><?= e($c['name']) ?></span>
          </a>
        <?php endforeach; ?>
      </div>
      <button type="button" class="cat-nav right" aria-label="Sağa kaydır">→</button>
    </div>
  </div>
</section>
