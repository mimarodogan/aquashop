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

<main id="main">
  <div class="container" style="padding:40px 20px 80px">

    <!-- Breadcrumb -->
    <nav aria-label="Breadcrumb" style="font-size:13px;color:var(--muted);margin-bottom:24px">
      <a href="<?= url('home') ?>" style="color:var(--muted);text-decoration:none">Anasayfa</a>
      <span style="margin:0 6px">›</span>
      <span>Kategoriler</span>
    </nav>

    <h1 style="font-family:var(--font-serif);font-size:clamp(1.8rem,4vw,2.6rem);margin-bottom:8px">Kategoriler</h1>
    <p style="color:var(--muted);margin-bottom:40px">
      <?= count($parents) ?> ana kategori
      <?php $subTotal = array_sum(array_map('count', $children)); if ($subTotal): ?>
        &nbsp;·&nbsp; <?= $subTotal ?> alt kategori
      <?php endif; ?>
    </p>

    <?php if (!$parents): ?>
      <p style="color:var(--muted);padding:60px 0;text-align:center">Henüz kategori eklenmemiş.</p>
    <?php endif; ?>

    <div class="cat-list-grid">
      <?php foreach ($parents as $parent):
        $subs = $children[$parent['id']] ?? [];
        $totalCount = (int)$parent['product_count'];
        foreach ($subs as $s) $totalCount += (int)$s['product_count'];
      ?>
        <div class="cat-list-card">
          <!-- Ana kategori başlık -->
          <a href="<?= e(url('category', ['slug' => $parent['slug']])) ?>" class="cat-list-head">
            <div class="cat-list-img">
              <?php if (!empty($parent['image'])): ?>
                <img loading="lazy" decoding="async" width="400" height="240" src="<?= e($parent['image']) ?>" alt="<?= e($parent['name']) ?>">
              <?php else: ?>
                <span class="cat-list-initial"><?= e(mb_substr($parent['name'], 0, 1)) ?></span>
              <?php endif; ?>
            </div>
            <div class="cat-list-info">
              <strong><?= e($parent['name']) ?></strong>
              <span class="cat-list-count"><?= $totalCount ?> ürün</span>
            </div>
            <svg class="cat-list-arrow" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="m9 18 6-6-6-6"/></svg>
          </a>

          <!-- Alt kategoriler -->
          <?php if ($subs): ?>
            <div class="cat-list-subs">
              <?php foreach ($subs as $sub): ?>
                <a href="<?= e(url('category', ['slug' => $sub['slug']])) ?>" class="cat-list-sub">
                  <span><?= e($sub['name']) ?></span>
                  <span class="cat-list-sub-count"><?= (int)$sub['product_count'] ?></span>
                </a>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
</main>

<style>
.cat-list-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
  gap: 20px;
}
.cat-list-card {
  border: 1px solid var(--gold-border);
  border-radius: 12px;
  overflow: hidden;
  background: var(--surface, #fff);
  transition: box-shadow .2s, transform .2s;
}
.cat-list-card:hover {
  box-shadow: 0 6px 24px rgba(0,0,0,.10);
  transform: translateY(-2px);
}
.cat-list-head {
  display: flex;
  align-items: center;
  gap: 14px;
  padding: 16px 18px;
  text-decoration: none;
  color: inherit;
  transition: background .18s;
}
.cat-list-head:hover { background: rgba(107,122,47,.06); }
.cat-list-card:has(.cat-list-subs) .cat-list-head {
  border-bottom: 1px solid var(--gold-border);
}
.cat-list-img {
  width: 56px; height: 56px;
  border-radius: 50%;
  overflow: hidden; flex-shrink: 0;
  border: 2px solid var(--gold-border);
  display: flex; align-items: center; justify-content: center;
  background: var(--bg-subtle, #f5f3ee);
}
.cat-list-img img { width:100%; height:100%; object-fit:cover; }
.cat-list-initial {
  font-size: 22px; font-family: var(--font-serif); color: var(--gold);
}
.cat-list-info { flex: 1; min-width: 0; }
.cat-list-info strong {
  display: block; font-size: 15px; font-weight: 600;
  color: var(--text-primary, #1a1a0f);
  white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
}
.cat-list-count { font-size: 12px; color: var(--muted); }
.cat-list-arrow {
  width: 18px; height: 18px; color: var(--muted); flex-shrink: 0;
  transition: transform .18s, color .18s;
}
.cat-list-head:hover .cat-list-arrow { transform: translateX(3px); color: var(--gold); }
.cat-list-subs { padding: 8px 0; }
.cat-list-sub {
  display: flex; align-items: center; justify-content: space-between;
  padding: 9px 18px 9px 36px;
  text-decoration: none; color: var(--text-secondary, #444);
  font-size: 14px; transition: background .15s, color .15s;
  position: relative;
}
.cat-list-sub::before {
  content: '↳'; position: absolute; left: 18px;
  color: var(--muted); font-size: 12px;
}
.cat-list-sub:hover { background: rgba(107,122,47,.06); color: var(--gold); }
.cat-list-sub-count {
  font-size: 12px; color: var(--muted);
  background: var(--bg-subtle, #f5f3ee);
  border: 1px solid var(--gold-border);
  border-radius: 10px; padding: 1px 9px;
  min-width: 28px; text-align: center;
}
@media (max-width: 600px) {
  .cat-list-grid { grid-template-columns: 1fr; }
}
</style>

<?php require_once __DIR__ . '/../components/footer.php'; ?>
