<?php
require_once __DIR__ . '/../includes/functions.php';
$page  = 'categories_list';
$title = 'Kategoriler';

// Tüm kategorileri + ürün sayısını çek
$parents  = [];
$children = [];
try {
    $rows = db()->query("
        SELECT
            c.*,
            (SELECT COUNT(DISTINCT p.id)
             FROM products p
             LEFT JOIN product_categories pc ON pc.product_id = p.id
             WHERE p.is_active = 1
               AND (p.category_id = c.id OR pc.category_id = c.id)
            ) AS product_count
        FROM categories c
        ORDER BY c.parent_id ASC, c.sort_order ASC, c.name ASC
    ")->fetchAll();

    foreach ($rows as $r) {
        if (!$r['parent_id']) {
            $parents[$r['id']] = $r;
        } else {
            $children[(int)$r['parent_id']][] = $r;
        }
    }
} catch (\Throwable $e) {
    // tablo henüz yoksa boş göster
}

// JSON-LD: BreadcrumbList — kategoriler liste sayfası için
$extraSchemas = $extraSchemas ?? [];
$__bcBase = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? '');
if (function_exists('jsonld_breadcrumb')) {
    $__bcLD = jsonld_breadcrumb([
        ['name' => 'Anasayfa',    'url' => $__bcBase . url('home')],
        ['name' => 'Kategoriler', 'url' => $__bcBase . url('categories')],
    ]);
    if ($__bcLD) $extraSchemas[] = $__bcLD;
}
unset($__bcBase, $__bcLD);

require_once __DIR__ . '/../components/header.php';
?>

<section class="aq-all-categories-page aq-all-categories-page-final">
  <div class="aq-container">
    <div class="aq-all-categories-head">
      <nav class="aq-breadcrumb" aria-label="Konum">
        <a href="<?= e(url('home')) ?>">Anasayfa</a>
        <i class="bi bi-chevron-right" aria-hidden="true"></i>
        <span aria-current="page">Kategoriler</span>
      </nav>

      <h1>Kategoriler</h1>
      <p>
        <strong><?= count($parents) ?> ana kategori</strong>
        <?php $subTotal = array_sum(array_map('count', $children)); if ($subTotal): ?>
          <span aria-hidden="true">/</span>
          <strong><?= $subTotal ?> alt kategori</strong>
        <?php endif; ?>
      </p>
    </div>

    <?php if (!$parents): ?>
      <div class="aq-all-categories-empty">
        <span><i class="bi bi-grid-3x3-gap"></i></span>
        <h2>Henüz kategori eklenmemiş</h2>
        <p>Kategori içerikleri hazır olduğunda burada listelenecek.</p>
      </div>
    <?php else: ?>
      <div class="aq-all-categories-grid">
        <?php foreach ($parents as $parent):
          $subs = $children[$parent['id']] ?? [];
          $totalCount = (int)$parent['product_count'];
          foreach ($subs as $s) $totalCount += (int)$s['product_count'];
          $visibleSubs = array_slice($subs, 0, 6);
          $remaining = max(0, count($subs) - count($visibleSubs));
        ?>
          <article class="aq-category-index-card">
            <a href="<?= e(url('category', ['slug' => $parent['slug']])) ?>" class="aq-category-index-card-head">
              <span class="aq-category-index-image">
                <?php if (!empty($parent['image'])): ?>
                  <img loading="lazy" decoding="async" width="120" height="120" src="<?= e($parent['image']) ?>" alt="<?= e($parent['name']) ?>">
                <?php else: ?>
                  <span class="aq-ph"><?= e(mb_substr($parent['name'], 0, 1)) ?></span>
                <?php endif; ?>
              </span>
              <span class="aq-category-index-title">
                <strong><?= e($parent['name']) ?></strong>
                <small><?= $totalCount ?> ürün</small>
              </span>
              <i class="bi bi-chevron-right" aria-hidden="true"></i>
            </a>

            <?php if ($visibleSubs): ?>
              <div class="aq-category-index-children">
                <?php foreach ($visibleSubs as $sub): ?>
                  <a href="<?= e(url('category', ['slug' => $sub['slug']])) ?>">
                    <span><i class="bi bi-chevron-right" aria-hidden="true"></i><?= e($sub['name']) ?></span>
                    <em><?= (int)$sub['product_count'] ?></em>
                  </a>
                <?php endforeach; ?>
                <?php if ($remaining > 0): ?>
                  <a href="<?= e(url('category', ['slug' => $parent['slug']])) ?>" class="aq-category-index-more">
                    <span>+<?= $remaining ?> kategori daha</span>
                  </a>
                <?php endif; ?>
              </div>
            <?php else: ?>
              <div class="aq-category-index-empty">Bu kategoriye bağlı alt kategori bulunmuyor.</div>
            <?php endif; ?>
          </article>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>
</section>

<?php require_once __DIR__ . '/../components/footer.php'; ?>
