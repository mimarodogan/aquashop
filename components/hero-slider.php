<?php
// Beklenen: $banners
$banners = isset($banners) ? $banners : banner_active();
if (!$banners): ?>
  <div class="ornament">◆</div>
<?php return; endif; ?>
<div class="hero-slider" id="heroSlider">
  <div class="hero-track">
    <?php
    $fallbackAlt = trim((string)setting('site_name','AquaShop')) . ' — Anasayfa banner';
    foreach ($banners as $i => $b):
        $href = !empty($b['link']) ? $b['link'] : null;
        // Alt önceliği: alt → title → site adı + sıra
        $imgAlt = trim((string)($b['alt'] ?? '')) ?: trim((string)($b['title'] ?? '')) ?: ($fallbackAlt . ' ' . ($i+1));
        $imgTitle = trim((string)($b['title'] ?? '')) ?: $imgAlt;
    ?>
      <div class="hero-slide <?= $i===0?'active':'' ?>">
        <?php if ($href): ?><a href="<?= e($href) ?>" title="<?= e($imgTitle) ?>"><?php endif; ?>
        <?php if ($i === 0): ?>
          <img loading="eager" fetchpriority="high" decoding="async" width="1280" height="512" src="<?= e($b['image']) ?>" alt="<?= e($imgAlt) ?>" title="<?= e($imgTitle) ?>">
        <?php else: ?>
          <img loading="lazy" decoding="async" width="1280" height="512" src="<?= e($b['image']) ?>" alt="<?= e($imgAlt) ?>" title="<?= e($imgTitle) ?>">
        <?php endif; ?>
        <?php if (!empty($b['title'])): ?><div class="hero-cap"><?= e($b['title']) ?></div><?php endif; ?>
        <?php if ($href): ?></a><?php endif; ?>
      </div>
    <?php endforeach; ?>
  </div>
  <?php if (count($banners) > 1): ?>
    <button type="button" class="hero-nav prev" aria-label="Önceki">‹</button>
    <button type="button" class="hero-nav next" aria-label="Sonraki">›</button>
    <div class="hero-dots">
      <?php for ($i=0;$i<count($banners);$i++): ?>
        <?php $btnLbl = !empty($b['title']) ? ('Slayt ' . ($i+1) . ': ' . $b['title']) : ('Slayt ' . ($i+1) . ' / ' . count($banners)); ?>
        <button type="button" data-i="<?= $i ?>" class="<?= $i===0?'active':'' ?>" aria-label="<?= e($btnLbl) ?>"></button>
      <?php endfor; ?>
    </div>
  <?php endif; ?>
</div>
