<?php
// Beklenen: $bp (blog_post satırı)
?>
<a class="card" href="<?= SITE_URL ?><?= e(url('blog_post', ['slug'=>$bp['slug']])) ?>">
  <div class="card-img">
    <?php if (!empty($bp['cover_image'])): ?>
      <img loading="lazy" decoding="async" width="600" height="660" src="<?= e($bp['cover_image']) ?>" alt="<?= e($bp['title']) ?>" style="width:100%;height:100%;object-fit:cover">
    <?php else: ?>
      <span class="ph"><?= e(mb_substr($bp['title'],0,1)) ?></span>
    <?php endif; ?>
  </div>
  <div class="card-body">
    <span class="cat"><?= e($bp['cat_name'] ?? 'Yazı') ?> · <?= e(date('d.m.Y', strtotime($bp['published_at'] ?? $bp['created_at']))) ?></span>
    <h3 style="font-size:18px"><?= e($bp['title']) ?></h3>
    <?php if (!empty($bp['excerpt'])): ?>
      <p class="muted" style="font-size:13px"><?= e(mb_substr($bp['excerpt'],0,140)) ?><?= mb_strlen($bp['excerpt'])>140?'…':'' ?></p>
    <?php endif; ?>
  </div>
</a>
