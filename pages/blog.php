<?php
require_once __DIR__ . '/../includes/functions.php';
$page = 'blog';
$title = 'Blog';

define('BLOG_PER_PAGE', 20);
$offset = max(0, (int)($_GET['offset'] ?? 0));
$isAjax = ($_GET['ajax'] ?? '') === 'page';

$catSlug = isset($_GET['cat']) ? trim($_GET['cat']) : '';
$q       = isset($_GET['q'])   ? trim($_GET['q'])   : '';

$where = array("p.is_published = 1");
$args = array();
if ($catSlug !== '') { $where[] = 'c.slug = ?'; $args[] = $catSlug; }
if ($q !== '')      { $where[] = '(p.title LIKE ? OR p.excerpt LIKE ?)'; $args[] = "%$q%"; $args[] = "%$q%"; }

$posts = array();
$totalCount = 0;
$cats  = array();
try {
    // Toplam (filtreli)
    $cntSql = "SELECT COUNT(*) FROM blog_posts p LEFT JOIN blog_categories c ON c.id=p.category_id WHERE " . implode(' AND ', $where);
    $cnt = db()->prepare($cntSql); $cnt->execute($args); $totalCount = (int)$cnt->fetchColumn();

    $sql = "SELECT p.*, c.name AS cat_name, c.slug AS cat_slug
            FROM blog_posts p LEFT JOIN blog_categories c ON c.id = p.category_id
            WHERE " . implode(' AND ', $where) . "
            ORDER BY COALESCE(p.published_at, p.created_at) DESC
            LIMIT " . BLOG_PER_PAGE . " OFFSET " . $offset;
    $st = db()->prepare($sql); $st->execute($args); $posts = $st->fetchAll();
    $cats = db()->query("SELECT c.*, (SELECT COUNT(*) FROM blog_posts WHERE category_id=c.id AND is_published=1) AS cnt FROM blog_categories c ORDER BY name")->fetchAll();
} catch (Exception $e) {}

$hasMore = ($offset + count($posts)) < $totalCount;
$nextOffset = $offset + BLOG_PER_PAGE;

// AJAX — sadece kartlar
if ($isAjax) {
    foreach ($posts as $p) { ?>
      <a class="card" href="<?= e(url('blog_post', ['slug'=>$p['slug']])) ?>">
        <div class="card-img">
          <?php if (!empty($p['cover_image'])): ?>
            <img loading="lazy" decoding="async" width="600" height="660" src="<?= e($p['cover_image']) ?>" alt="<?= e($p['title']) ?>" style="width:100%;height:100%;object-fit:cover">
          <?php else: ?>
            <span class="ph"><?= e(mb_substr($p['title'],0,1)) ?></span>
          <?php endif; ?>
        </div>
        <div class="card-body">
          <span class="cat"><?= e($p['cat_name'] ?? 'Genel') ?> · <?= e(date('d.m.Y', strtotime($p['published_at'] ?? $p['created_at']))) ?></span>
          <h3 style="font-size:18px"><?= e($p['title']) ?></h3>
          <?php if (!empty($p['excerpt'])): ?><p class="muted" style="font-size:14px"><?= e($p['excerpt']) ?></p><?php endif; ?>
        </div>
      </a>
    <?php }
    echo '<div data-has-more="'.($hasMore?'1':'0').'" data-next-offset="'.$nextOffset.'" data-total="'.$totalCount.'" style="display:none"></div>';
    exit;
}

// Pagination rel links — Googlebot'un tüm sayfaları bulmasını kolaylaştırır
$paginationLinks = [];
if ($offset > 0) {
    $prevOffset = max(0, $offset - BLOG_PER_PAGE);
    $pq = $_GET; unset($pq['ajax']);
    if ($prevOffset > 0) { $pq['offset'] = $prevOffset; } else { unset($pq['offset']); }
    $paginationLinks['prev'] = url('blog') . ($pq ? '?' . http_build_query($pq) : '');
}
if ($hasMore) {
    $pq = $_GET; unset($pq['ajax']); $pq['offset'] = $nextOffset;
    $paginationLinks['next'] = url('blog') . '?' . http_build_query($pq);
}

// JSON-LD: BreadcrumbList — blog index için
$extraSchemas = $extraSchemas ?? [];
$__bcBase = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? '');
$__bcCrumbs = [
    ['name' => 'Anasayfa', 'url' => $__bcBase . url('home')],
    ['name' => 'Blog',     'url' => $__bcBase . url('blog')],
];
if ($catSlug !== '') {
    $__bcCatName = '';
    foreach ($cats as $__bcC) if (($__bcC['slug'] ?? '') === $catSlug) { $__bcCatName = $__bcC['name']; break; }
    if ($__bcCatName) {
        $__bcCrumbs[] = ['name' => $__bcCatName, 'url' => $__bcBase . url('blog', ['cat' => $catSlug])];
    }
}
if (function_exists('jsonld_breadcrumb')) {
    $__bcLD = jsonld_breadcrumb($__bcCrumbs);
    if ($__bcLD) $extraSchemas[] = $__bcLD;
}
unset($__bcBase, $__bcCrumbs, $__bcCatName, $__bcC, $__bcLD);

include __DIR__ . '/../includes/header.php';
?>
<section class="page-header">
  <div class="container">
    <span class="kicker">Yazılar</span>
    <h1 style="margin-top:10px"><?= $catSlug ? e(ucfirst($catSlug)) : 'Blog' ?></h1>
    <div class="breadcrumb"><a href="<?= url('home') ?>">Anasayfa</a><span>/</span>Blog<?php if($catSlug): ?><span>/</span><?= e($catSlug) ?><?php endif; ?></div>
  </div>
</section>

<section>
  <div class="container shop-layout">
    <aside class="filter">
      <h4>Ara</h4>
      <form method="get">
        <?php if ($catSlug !== ''): ?><input type="hidden" name="cat" value="<?= e($catSlug) ?>"><?php endif; ?>
        <div class="field"><input type="text" name="q" value="<?= e($q) ?>" placeholder="Yazı ara…"></div>
      </form>
      <h4>Kategoriler</h4>
      <label><a href="<?= url('blog') ?>" style="<?= !$catSlug?'color:var(--gold)':'' ?>">Tümü</a></label>
      <?php foreach ($cats as $c): ?>
        <label><a href="<?= e(url('blog', ['cat'=>$c['slug']])) ?>" style="<?= $catSlug===$c['slug']?'color:var(--gold)':'' ?>"><?= e($c['name']) ?> <span class="muted">(<?= (int)$c['cnt'] ?>)</span></a></label>
      <?php endforeach; ?>
    </aside>

    <div>
      <div class="grid grid-3" id="blog-grid">
        <?php if ($posts): foreach ($posts as $p): ?>
          <a class="card" href="<?= e(url('blog_post', ['slug'=>$p['slug']])) ?>">
            <div class="card-img">
              <?php if (!empty($p['cover_image'])): ?>
                <img loading="lazy" decoding="async" width="600" height="660" src="<?= e($p['cover_image']) ?>" alt="<?= e($p['title']) ?>" style="width:100%;height:100%;object-fit:cover">
              <?php else: ?>
                <span class="ph"><?= e(mb_substr($p['title'],0,1)) ?></span>
              <?php endif; ?>
            </div>
            <div class="card-body">
              <span class="cat"><?= e($p['cat_name'] ?? 'Genel') ?> · <?= e(date('d.m.Y', strtotime($p['published_at'] ?? $p['created_at']))) ?></span>
              <h3 style="font-size:18px"><?= e($p['title']) ?></h3>
              <?php if (!empty($p['excerpt'])): ?><p class="muted" style="font-size:14px"><?= e($p['excerpt']) ?></p><?php endif; ?>
            </div>
          </a>
        <?php endforeach; else: ?>
          <p class="muted">Henüz yazı yok.</p>
        <?php endif; ?>
      </div>

      <?php if ($totalCount > 0): ?>
        <div class="loadmore-wrap" style="text-align:center;margin-top:40px"
             data-ajax-url="<?= e(rtrim(SITE_URL,'/').'/blog.php') ?>"
             data-grid-id="blog-grid"
             data-button-id="loadmore-btn-blog"
             data-base-query="<?= e(http_build_query(array_diff_key($_GET, ['offset'=>'','ajax'=>'']))) ?>"
             data-next-offset="<?= (int)$nextOffset ?>"
             data-total="<?= (int)$totalCount ?>">
          <p class="muted" style="margin-bottom:14px;font-size:13px">
            <span class="lm-shown"><?= count($posts) ?></span> / <?= $totalCount ?> yazı gösteriliyor
          </p>
          <?php if ($hasMore): ?>
            <button type="button" class="btn btn-secondary btn-lg" id="loadmore-btn-blog">
              Daha Fazla Yazı <span style="opacity:.7;margin-left:8px">(+<?= min(BLOG_PER_PAGE, $totalCount - $offset - count($posts)) ?>)</span>
            </button>
          <?php endif; ?>
        </div>

      <?php endif; ?>
    </div>
  </div>
</section>

<?php include __DIR__ . '/../includes/footer.php'; ?>
