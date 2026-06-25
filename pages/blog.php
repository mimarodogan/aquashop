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
      <article class="aq-blog-list-card">
        <a class="aq-blog-list-image" href="<?= e(url('blog_post', ['slug'=>$p['slug']])) ?>">
          <?php if (!empty($p['cover_image'])): ?>
            <img loading="lazy" decoding="async" width="600" height="400" src="<?= e($p['cover_image']) ?>" alt="<?= e($p['title']) ?>">
          <?php else: ?>
            <span class="aq-ph"><?= e(mb_substr($p['title'],0,1)) ?></span>
          <?php endif; ?>
        </a>
        <div class="aq-blog-list-content">
          <span><?= e($p['cat_name'] ?? 'Genel') ?> · <?= e(date('d.m.Y', strtotime($p['published_at'] ?? $p['created_at']))) ?></span>
          <h2><a href="<?= e(url('blog_post', ['slug'=>$p['slug']])) ?>"><?= e($p['title']) ?></a></h2>
          <?php if (!empty($p['excerpt'])): ?><p><?= e(mb_substr(strip_tags($p['excerpt']), 0, 150)) ?><?= mb_strlen(strip_tags($p['excerpt'])) > 150 ? '…' : '' ?></p><?php endif; ?>
          <a class="aq-blog-list-link" href="<?= e(url('blog_post', ['slug'=>$p['slug']])) ?>">Devamını Oku <i class="bi bi-arrow-right"></i></a>
        </div>
      </article>
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
<section class="aq-all-categories-page aq-blog-index-page">
  <div class="aq-container">
    <div class="aq-blog-page-head">
      <div class="aq-breadcrumb"><a href="<?= url('home') ?>">Ana Sayfa</a><span>Blog</span><?php if($catSlug): ?><span><?= e($catSlug) ?></span><?php endif; ?></div>
      <div class="aq-blog-page-head-card">
        <span>Blog</span>
        <h1><?= $catSlug ? e(ucfirst($catSlug)) : 'Blog' ?></h1>
        <p>Akvaryum hobiniz için bakım, kurulum, ürün seçimi ve pratik kullanım önerileri.</p>
      </div>
    </div>

    <form class="aq-blog-filter" method="get">
      <?php if ($catSlug !== ''): ?><input type="hidden" name="cat" value="<?= e($catSlug) ?>"><?php endif; ?>
      <input type="search" name="q" value="<?= e($q) ?>" placeholder="Yazı ara..." aria-label="Yazı ara">
      <button type="submit"><i class="bi bi-search"></i> Ara</button>
    </form>

    <?php if ($cats): ?>
    <div class="aq-blog-cat-pills" aria-label="Blog kategorileri">
      <a href="<?= url('blog') ?>" class="<?= !$catSlug ? 'is-active' : '' ?>">Tümü</a>
      <?php foreach ($cats as $c): ?>
        <a href="<?= e(url('blog', ['cat'=>$c['slug']])) ?>" class="<?= $catSlug===$c['slug'] ? 'is-active' : '' ?>"><?= e($c['name']) ?> <span><?= (int)$c['cnt'] ?></span></a>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>

      <div class="aq-blog-list-grid" id="blog-grid">
        <?php if ($posts): foreach ($posts as $p): ?>
          <article class="aq-blog-list-card">
            <a class="aq-blog-list-image" href="<?= e(url('blog_post', ['slug'=>$p['slug']])) ?>">
              <?php if (!empty($p['cover_image'])): ?>
                <img loading="lazy" decoding="async" width="600" height="400" src="<?= e($p['cover_image']) ?>" alt="<?= e($p['title']) ?>">
              <?php else: ?>
                <span class="aq-ph"><?= e(mb_substr($p['title'],0,1)) ?></span>
              <?php endif; ?>
            </a>
            <div class="aq-blog-list-content">
              <span><?= e($p['cat_name'] ?? 'Genel') ?> · <?= e(date('d.m.Y', strtotime($p['published_at'] ?? $p['created_at']))) ?></span>
              <h2><a href="<?= e(url('blog_post', ['slug'=>$p['slug']])) ?>"><?= e($p['title']) ?></a></h2>
              <?php if (!empty($p['excerpt'])): ?><p><?= e(mb_substr(strip_tags($p['excerpt']), 0, 150)) ?><?= mb_strlen(strip_tags($p['excerpt'])) > 150 ? '…' : '' ?></p><?php endif; ?>
              <a class="aq-blog-list-link" href="<?= e(url('blog_post', ['slug'=>$p['slug']])) ?>">Devamını Oku <i class="bi bi-arrow-right"></i></a>
            </div>
          </article>
        <?php endforeach; else: ?>
          <div class="aq-blog-empty-state">Henüz yazı yok.</div>
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
</section>

<?php include __DIR__ . '/../includes/footer.php'; ?>
