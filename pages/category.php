<?php
/**
 * Kategori landing sayfası
 * URL: /kategoriler/{slug}
 * Router → $_GET['cat'] = slug
 */
require_once __DIR__ . '/../includes/functions.php';

$slug = trim($_GET['cat'] ?? '');
if ($slug === '') {
    header('Location: ' . url('products'), true, 302);
    exit;
}

// ── Kategoriyi çek ─────────────────────────────────────────────
$cat = null;
try {
    $st = db()->prepare('SELECT * FROM categories WHERE slug = ? LIMIT 1');
    $st->execute([$slug]);
    $cat = $st->fetch();
} catch (Throwable $e) {}

if (!$cat) {
    http_response_code(404);
    $page = '404'; $title = 'Kategori Bulunamadı';
    include __DIR__ . '/../includes/header.php';
    echo '<section class="container" style="padding:120px 0;text-align:center">
            <h1>404</h1>
            <p class="muted" style="margin:14px 0 24px">Aradığınız kategori bulunamadı.</p>
            <a class="btn btn-primary" href="' . e(url('products')) . '">Tüm Ürünler</a>
          </section>';
    include __DIR__ . '/../includes/footer.php';
    exit;
}

// ── Üst kategori (breadcrumb için) ─────────────────────────────
$parentCat = null;
if (!empty($cat['parent_id'])) {
    try {
        $st = db()->prepare('SELECT id, name, slug FROM categories WHERE id = ? LIMIT 1');
        $st->execute([(int)$cat['parent_id']]);
        $parentCat = $st->fetch();
    } catch (Throwable $e) {}
}

// ── Alt kategoriler ─────────────────────────────────────────────
$subcats = [];
try {
    $st = db()->prepare(
        'SELECT c.*, (SELECT COUNT(*) FROM products WHERE category_id=c.id AND is_active=1) AS cnt
         FROM categories c WHERE c.parent_id = ? ORDER BY c.sort_order ASC, c.name ASC'
    );
    $st->execute([(int)$cat['id']]);
    $subcats = $st->fetchAll();
} catch (Throwable $e) {}

// ── Fiyat aralığı (DB'den min/max) ─────────────────────────────
$priceRange = ['min' => 0, 'max' => 9999];
try {
    $allCatIdsForRange = [(int)$cat['id']];
    foreach ($subcats as $sc) $allCatIdsForRange[] = (int)$sc['id'];
    $inPh2 = implode(',', array_fill(0, count($allCatIdsForRange), '?'));
    $rng = db()->prepare("SELECT MIN(p.price) AS mn, MAX(p.price) AS mx
        FROM products p
        LEFT JOIN product_categories pc ON pc.product_id = p.id
        WHERE p.is_active=1
          AND (p.category_id IN ($inPh2) OR pc.category_id IN ($inPh2))");
    $rng->execute(array_merge($allCatIdsForRange, $allCatIdsForRange));
    $rngRow = $rng->fetch();
    if ($rngRow && $rngRow['mx'] !== null) {
        $priceRange['min'] = (int)floor($rngRow['mn']);
        $priceRange['max'] = (int)ceil($rngRow['mx']);
    }
} catch (Throwable $e) {}

// ── Filtre parametreleri ────────────────────────────────────────
$priceMin  = isset($_GET['price_min']) && $_GET['price_min'] !== '' ? (float)$_GET['price_min'] : null;
$priceMax  = isset($_GET['price_max']) && $_GET['price_max'] !== '' ? (float)$_GET['price_max'] : null;
$saleOnly  = !empty($_GET['sale']) && $_GET['sale'] === '1';
$subFilter = !empty($_GET['cats']) && is_array($_GET['cats'])
             ? array_values(array_unique(array_map('intval', $_GET['cats'])))
             : [];

// Geçerli filtre var mı?
$hasFilter = ($priceMin !== null) || ($priceMax !== null) || $saleOnly || !empty($subFilter);

// ── Bu kategori + alt kategorilerdeki tüm ürünler ───────────────
// Alt kategori filtresi uygulanmışsa sadece seçilenleri al
$baseCatIds = [(int)$cat['id']];
foreach ($subcats as $sc) $baseCatIds[] = (int)$sc['id'];

if (!empty($subFilter)) {
    // Sadece seçili alt kategoriler + ana kategori
    $catIds = array_intersect($baseCatIds, array_merge([(int)$cat['id']], $subFilter));
    $catIds = array_values($catIds);
} else {
    $catIds = $baseCatIds;
}

// Sayfalama
define('CAT_PER_PAGE', 24);
$offset = max(0, (int)($_GET['offset'] ?? 0));
$isAjax = ($_GET['ajax'] ?? '') === 'page';
$sort   = $_GET['sort'] ?? 'new';

switch ($sort) {
    case 'price_asc':  $orderBy = 'p.price ASC';  break;
    case 'price_desc': $orderBy = 'p.price DESC'; break;
    case 'name':       $orderBy = 'p.name ASC';   break;
    case 'popular':    $orderBy = 'p.is_featured DESC, p.created_at DESC'; break;
    default:           $orderBy = 'p.created_at DESC';
}

// ── SQL: filtre koşulları ───────────────────────────────────────
function build_where_extras($priceMin, $priceMax, $saleOnly): array {
    $extras = []; $params = [];
    if ($priceMin !== null) { $extras[] = 'p.price >= ?'; $params[] = $priceMin; }
    if ($priceMax !== null) { $extras[] = 'p.price <= ?'; $params[] = $priceMax; }
    if ($saleOnly)           { $extras[] = 'p.old_price IS NOT NULL AND p.old_price > p.price'; }
    return [$extras, $params];
}

$products   = [];
$totalCount = 0;
try {
    $inPh = implode(',', array_fill(0, count($catIds), '?'));
    [$extras, $extraParams] = build_where_extras($priceMin, $priceMax, $saleOnly);
    $extraSql = $extras ? (' AND ' . implode(' AND ', $extras)) : '';

    // Hem category_id hem de product_categories pivot tablosunu kontrol et
    $cntParams = array_merge($catIds, $catIds, $extraParams);
    $cnt = db()->prepare("SELECT COUNT(DISTINCT p.id) FROM products p
         LEFT JOIN product_categories pc ON pc.product_id = p.id
         WHERE p.is_active=1
           AND (p.category_id IN ($inPh) OR pc.category_id IN ($inPh))
         $extraSql");
    $cnt->execute($cntParams);
    $totalCount = (int)$cnt->fetchColumn();

    $stParams = array_merge($catIds, $catIds, $extraParams);
    $st = db()->prepare(
        "SELECT DISTINCT p.*, c.name AS cat_name, c.slug AS cat_slug
         FROM products p
         LEFT JOIN categories c ON c.id = p.category_id
         LEFT JOIN product_categories pc ON pc.product_id = p.id
         WHERE p.is_active=1
           AND (p.category_id IN ($inPh) OR pc.category_id IN ($inPh))
         $extraSql
         ORDER BY $orderBy
         LIMIT " . CAT_PER_PAGE . " OFFSET $offset"
    );
    $st->execute($stParams);
    $products = $st->fetchAll();
} catch (Throwable $e) {}

$hasMore    = ($offset + count($products)) < $totalCount;
$nextOffset = $offset + CAT_PER_PAGE;

// ── Aktif filtre sayısı (toolbar badge için) ────────────────────
$filterBadge = 0;
if ($priceMin !== null || $priceMax !== null) $filterBadge++;
if ($saleOnly) $filterBadge++;
if (!empty($subFilter)) $filterBadge++;

// ── Yük-daha-fazla için base query (filtre parametreleri dahil) ─
$baseQueryParts = [];
if ($sort !== 'new') $baseQueryParts[] = 'sort=' . urlencode($sort);
if ($priceMin !== null) $baseQueryParts[] = 'price_min=' . (int)$priceMin;
if ($priceMax !== null) $baseQueryParts[] = 'price_max=' . (int)$priceMax;
if ($saleOnly) $baseQueryParts[] = 'sale=1';
foreach ($subFilter as $sf) $baseQueryParts[] = 'cats[]=' . (int)$sf;
$baseQuery = implode('&', $baseQueryParts);

// ── Sayfa meta ─────────────────────────────────────────────────
$page      = 'category';
$title     = !empty($cat['meta_title']) ? $cat['meta_title'] : $cat['name'];
$siteName  = setting('site_name', 'AquaShop');

$sortLabels = [
    'new'        => 'En Yeniler',
    'popular'    => 'Popüler',
    'price_asc'  => 'Fiyat: Düşükten Yükseğe',
    'price_desc' => 'Fiyat: Yüksekten Düşüğe',
    'name'       => 'İsme Göre (A-Z)',
];

// ── AJAX — sadece kartları döndür ──────────────────────────────
if ($isAjax) {
    $favIds = fav_ids();
    foreach ($products as $p): ?>
      <div class="card" style="position:relative">
        <form method="post" action="<?= SITE_URL ?>/favorite-toggle.php" class="fav-form">
          <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
          <input type="hidden" name="id"   value="<?= (int)$p['id'] ?>">
          <input type="hidden" name="back" value="<?= e($_SERVER['REQUEST_URI'] ?? url('category',['slug'=>$slug])) ?>">
          <button class="fav-btn <?= in_array((int)$p['id'],$favIds)?'active':'' ?>" type="submit" aria-label="Favori">
            <?= ic('heart', '', 18) ?>
          </button>
        </form>
        <a href="<?= e(url('product', ['slug'=>$p['slug']])) ?>" style="display:flex;flex-direction:column;flex:1">
          <div class="card-img">
            <?php if (!empty($p['old_price'])): ?><span class="badge">İndirim</span><?php endif; ?>
            <?php if (!empty($p['image'])): ?>
              <img loading="lazy" decoding="async" width="600" height="660"
                   src="<?= e($p['image']) ?>" alt="<?= e($p['name']) ?>"
                   style="width:100%;height:100%;object-fit:cover">
            <?php else: ?>
              <span class="ph"><?= e(mb_substr($p['name'],0,1)) ?></span>
            <?php endif; ?>
          </div>
          <div class="card-body">
            <span class="cat"><?= e($p['cat_name'] ?? 'Genel') ?></span>
            <h3><?= e($p['name']) ?></h3>
            <div class="card-foot">
              <span class="price"><?= money($p['price']) ?>
                <?php if (!empty($p['old_price'])): ?>
                  <span class="price-old"><?= money($p['old_price']) ?></span>
                <?php endif; ?>
              </span>
              <span class="icon-btn" aria-hidden="true"><?= ic('cart', '', 17) ?></span>
            </div>
          </div>
        </a>
      </div>
    <?php endforeach;
    echo '<div data-has-more="'.($hasMore?'1':'0').'" data-next-offset="'.$nextOffset.'" data-total="'.$totalCount.'" style="display:none"></div>';
    exit;
}

// ── Aktif filtre chip listesi ───────────────────────────────────
function cat_filter_remove_url(string $base, array $remove): string {
    $q = $_GET;
    foreach ($remove as $k) unset($q[$k]);
    unset($q['offset'], $q['ajax'], $q['cat']);
    $qs = http_build_query($q);
    return $base . ($qs ? '?' . $qs : '');
}
$catBase = url('category', ['slug' => $slug]);

// ── Header include ──────────────────────────────────────────────
// Öncelik sırası (yüksekten düşüğe):
//  1. Kategorinin kendi meta_description'ı (admin → Kategoriler → Düzenle)
//  2. SEO manager → 'category' slug template'i ({title} = kategori adı)
//  3. Kategorinin açıklama metni (uzun description alanı)
// 4. Auto-fallback (sadece meta_description boşken, template da yoksa)
$__categoryMeta = [
    'title' => $title . ' · ' . $siteName,
    'url'   => url('category', ['slug' => $slug]),
    'image' => $cat['image'] ?? '',
];
if (!empty($cat['meta_description'])) {
    // Kategori kendi meta açıklamasına sahip → doğrudan kullan
    $__categoryMeta['desc'] = $cat['meta_description'];
} elseif (!empty($cat['description'])) {
    // Kategori açıklama metni varsa meta description'a dönüştür
    $__categoryMeta['desc'] = mb_substr(strip_tags($cat['description']), 0, 160);
}
// Yukarıdaki iki koşul sağlanmıyorsa 'desc' set edilmez;
// header.php, SEO manager'daki 'category' template'ini ({title} ile) kullanır.

// Pagination rel links
$paginationLinks = [];
if ($offset > 0) {
    $prevOffset = max(0, $offset - CAT_PER_PAGE);
    $pq = $_GET; unset($pq['ajax'], $pq['cat']);
    if ($prevOffset > 0) { $pq['offset'] = $prevOffset; } else { unset($pq['offset']); }
    $paginationLinks['prev'] = url('category', ['slug' => $slug]) . ($pq ? '?' . http_build_query($pq) : '');
}
if ($hasMore) {
    $pq = $_GET; unset($pq['ajax'], $pq['cat']); $pq['offset'] = $nextOffset;
    $paginationLinks['next'] = url('category', ['slug' => $slug]) . '?' . http_build_query($pq);
}

include __DIR__ . '/../includes/header.php';

// JSON-LD: BreadcrumbList
$crumbs = [['name' => 'Anasayfa', 'url' => url('home')]];
if ($parentCat) {
    $crumbs[] = ['name' => $parentCat['name'], 'url' => url('category', ['slug' => $parentCat['slug']])];
}
$crumbs[] = ['name' => $cat['name'], 'url' => url('category', ['slug' => $slug])];
echo '<script type="application/ld+json">' . json_encode(jsonld_breadcrumb($crumbs), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . '</script>';

// JSON-LD: CollectionPage + ItemList — Google'ın kategori sayfasını anlamasını kolaylaştırır
if (!empty($products)) {
    $__base = (isset($_SERVER['HTTPS'])&&$_SERVER['HTTPS']!=='off' ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? '');
    $__items = [];
    foreach (array_slice($products, 0, 20) as $__i => $__sp) {
        $__items[] = [
            '@type'    => 'ListItem',
            'position' => $__i + 1,
            'url'      => $__base . url('product', ['slug' => $__sp['slug']]),
            'name'     => $__sp['name'],
        ];
    }
    $__colPage = [
        '@context' => 'https://schema.org',
        '@type'    => 'CollectionPage',
        'name'     => $cat['name'],
        'url'      => $__base . url('category', ['slug' => $slug]),
        'mainEntity' => [
            '@type'           => 'ItemList',
            'numberOfItems'   => $totalCount,
            'itemListElement' => $__items,
        ],
    ];
    if (!empty($cat['description'])) {
        $__colPage['description'] = mb_substr(strip_tags($cat['description']), 0, 300);
    }
    echo '<script type="application/ld+json">' . json_encode($__colPage, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . '</script>';
    unset($__base, $__items, $__colPage, $__i, $__sp);
}
?>

<!-- ── Sayfa başlığı ────────────────────────────────────────── -->
<section class="page-header">
  <div class="container">
    <span class="kicker">
      <?php if ($parentCat): ?>
        <a href="<?= e(url('category', ['slug' => $parentCat['slug']])) ?>"><?= e($parentCat['name']) ?></a> /
      <?php endif; ?>
      Kategoriler
    </span>
    <h1 style="margin-top:10px"><?= e($cat['name']) ?></h1>
    <nav class="breadcrumb" aria-label="Konum">
      <a href="<?= e(url('home')) ?>">Anasayfa</a><span aria-hidden="true">/</span>
      <?php if ($parentCat): ?>
        <a href="<?= e(url('category', ['slug' => $parentCat['slug']])) ?>"><?= e($parentCat['name']) ?></a><span aria-hidden="true">/</span>
      <?php endif; ?>
      <span aria-current="page"><?= e($cat['name']) ?></span>
    </nav>
  </div>
</section>

<?php if (!empty($cat['description'])): ?>
<section style="background:var(--cream);border-bottom:1px solid var(--gold-border)">
  <div class="container" style="padding-top:28px;padding-bottom:28px;max-width:840px">
    <div class="prose" style="font-size:15px;line-height:1.75;color:var(--muted-text)">
      <?= nl2br(e($cat['description'])) ?>
    </div>
  </div>
</section>
<?php endif; ?>


<!-- ── Ürün ızgarası ────────────────────────────────────────── -->
<section>
  <div class="container">

    <!-- Toolbar: Filtrele + Sırala -->
    <div class="shop-toolbar">
      <button type="button" class="shop-tool" id="btn-filter"
              aria-haspopup="dialog" aria-expanded="false" aria-controls="filter-drawer">
        <?= ic('filter', '', 18) ?>
        <span>Filtrele<?php if ($filterBadge > 0): ?> <em class="ft-badge">(<?= $filterBadge ?>)</em><?php endif; ?></span>
      </button>
      <button type="button" class="shop-tool" id="btn-sort"
              aria-haspopup="dialog" aria-expanded="false">
        <?= ic('sort', '', 18) ?>
        <span><?= e($sortLabels[$sort] ?? 'Önerilen Sıralama') ?></span>
      </button>
    </div>

    <!-- Aktif filtre chip'leri -->
    <?php if ($hasFilter): ?>
    <div class="active-chips" role="group" aria-label="Aktif filtreler">
      <?php if ($priceMin !== null || $priceMax !== null):
        $chipLabel = '';
        if ($priceMin !== null && $priceMax !== null) $chipLabel = money($priceMin) . ' – ' . money($priceMax);
        elseif ($priceMin !== null) $chipLabel = money($priceMin) . ' ve üzeri';
        else $chipLabel = money($priceMax) . ' ve altı';
        $removeUrl = cat_filter_remove_url($catBase, ['price_min', 'price_max']); ?>
        <a href="<?= e($removeUrl) ?>" class="chip" title="Fiyat filtresini kaldır">
          <?= e($chipLabel) ?> <span aria-hidden="true">×</span>
        </a>
      <?php endif; ?>
      <?php if ($saleOnly):
        $removeUrl = cat_filter_remove_url($catBase, ['sale']); ?>
        <a href="<?= e($removeUrl) ?>" class="chip" title="İndirim filtresini kaldır">
          İndirimli <span aria-hidden="true">×</span>
        </a>
      <?php endif; ?>
      <?php foreach ($subFilter as $sf):
        $scName = '';
        foreach ($subcats as $sc) { if ((int)$sc['id'] === $sf) { $scName = $sc['name']; break; } }
        if (!$scName) continue;
        $q = $_GET;
        $q['cats'] = array_values(array_filter($subFilter, fn($x) => $x !== $sf));
        unset($q['offset'],$q['ajax'],$q['cat']);
        $qs = http_build_query($q);
        $removeUrl = $catBase . ($qs ? '?' . $qs : ''); ?>
        <a href="<?= e($removeUrl) ?>" class="chip" title="<?= e($scName) ?> filtresini kaldır">
          <?= e($scName) ?> <span aria-hidden="true">×</span>
        </a>
      <?php endforeach; ?>
      <?php
        $clearQ = [];
        if ($sort !== 'new') $clearQ['sort'] = $sort;
        $clearQs = http_build_query($clearQ); ?>
      <a href="<?= e($catBase . ($clearQs ? '?' . $clearQs : '')) ?>" class="chip chip-clear">
        Tümünü Temizle
      </a>
    </div>
    <?php endif; ?>

    <!-- Sonuç sayısı -->
    <p class="muted" style="font-size:13px;margin-bottom:28px">
      <?= (int)$totalCount ?> ürün bulundu
    </p>

    <!-- Ürün kartları -->
    <div class="grid grid-3" id="cat-grid">
      <?php $favIds = fav_ids(); if ($products): foreach ($products as $p): ?>
        <div class="card" style="position:relative">
          <form method="post" action="<?= SITE_URL ?>/favorite-toggle.php" class="fav-form">
            <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="id"   value="<?= (int)$p['id'] ?>">
            <input type="hidden" name="back" value="<?= e(url('category', ['slug'=>$slug])) ?>">
            <button class="fav-btn <?= in_array((int)$p['id'],$favIds)?'active':'' ?>" type="submit" aria-label="Favori">
              <?= ic('heart', '', 18) ?>
            </button>
          </form>
          <a href="<?= e(url('product', ['slug'=>$p['slug']])) ?>" style="display:flex;flex-direction:column;flex:1">
            <div class="card-img">
              <?php if (!empty($p['old_price'])): ?><span class="badge">İndirim</span><?php endif; ?>
              <?php if (!empty($p['image'])): ?>
                <img loading="lazy" decoding="async" width="600" height="660"
                     src="<?= e($p['image']) ?>" alt="<?= e($p['name']) ?>"
                     style="width:100%;height:100%;object-fit:cover">
              <?php else: ?>
                <span class="ph"><?= e(mb_substr($p['name'],0,1)) ?></span>
              <?php endif; ?>
            </div>
            <div class="card-body">
              <span class="cat"><?= e($p['cat_name'] ?? 'Genel') ?></span>
              <h3><?= e($p['name']) ?></h3>
              <div class="card-foot">
                <span class="price"><?= money($p['price']) ?>
                  <?php if (!empty($p['old_price'])): ?>
                    <span class="price-old"><?= money($p['old_price']) ?></span>
                  <?php endif; ?>
                </span>
                <span class="icon-btn" aria-hidden="true"><?= ic('cart', '', 17) ?></span>
              </div>
            </div>
          </a>
        </div>
      <?php endforeach; else: ?>
        <p class="muted" style="grid-column:1/-1;text-align:center;padding:80px 20px">
          <?= $hasFilter ? 'Bu filtreyle eşleşen ürün bulunamadı.' : 'Bu kategoride henüz ürün bulunmuyor.' ?>
          <br>
          <?php if ($hasFilter):
            $clearQs2 = $sort !== 'new' ? '?sort=' . urlencode($sort) : ''; ?>
            <a href="<?= e($catBase . $clearQs2) ?>" style="color:var(--gold);margin-top:12px;display:inline-block">Filtreleri temizle →</a>
          <?php else: ?>
            <a href="<?= e(url('products')) ?>" style="color:var(--gold);margin-top:12px;display:inline-block">Tüm ürünlere bak →</a>
          <?php endif; ?>
        </p>
      <?php endif; ?>
    </div>

    <!-- Daha Fazla Yükle -->
    <?php if ($hasMore): ?>
    <div class="loadmore-wrap" style="text-align:center;margin-top:40px"
         data-ajax-url="<?= e(rtrim(SITE_URL,'/')) ?>/kategoriler/<?= e($slug) ?>"
         data-button-id="cat-loadmore"
         data-grid-selector="#cat-grid"
         data-base-query="<?= e($baseQuery) ?>">
      <button id="cat-loadmore" class="btn btn-secondary"
              data-offset="<?= (int)$nextOffset ?>"
              data-total="<?= (int)$totalCount ?>">
        Daha Fazla Yükle
        <span class="muted" style="font-size:13px">
          (<?= (int)count($products) ?> / <?= (int)$totalCount ?>)
        </span>
      </button>
    </div>
    <?php endif; ?>

  </div>
</section>

<!-- ─────────────── Sıralama popup ─────────────────────────── -->
<div class="sort-pop" id="sort-pop" role="dialog" aria-label="Sıralama" aria-modal="true">
  <div class="sort-pop-overlay" data-close="sort"></div>
  <div class="sort-pop-card">
    <div class="sort-pop-head">Sıralama
      <button type="button" class="sort-pop-close" data-close="sort" aria-label="Kapat">×</button>
    </div>
    <ul>
      <?php
      foreach ($sortLabels as $k => $lbl):
          // Sıralama linkinde aktif filtreleri koru
          $sortQ = $_GET;
          unset($sortQ['offset'], $sortQ['ajax'], $sortQ['cat']);
          if ($k === 'new') unset($sortQ['sort']); else $sortQ['sort'] = $k;
          $sortQs = http_build_query($sortQ);
          $href = $catBase . ($sortQs ? '?' . $sortQs : '');
      ?>
        <li>
          <a href="<?= e($href) ?>" class="<?= $sort===$k?'active':'' ?>">
            <?= e($lbl) ?><?php if ($sort===$k): ?> <span aria-hidden="true">✓</span><?php endif; ?>
          </a>
        </li>
      <?php endforeach; ?>
    </ul>
  </div>
</div>

<!-- ─────────────── Filtre Drawer ──────────────────────────── -->
<div class="filter-drawer" id="filter-drawer" role="dialog" aria-label="Filtreler" aria-modal="true">
  <div class="filter-drawer-overlay" id="filter-drawer-overlay"></div>
  <div class="filter-drawer-panel">
    <div class="filter-drawer-head">
      <h3>Filtrele</h3>
      <button type="button" class="filter-drawer-close" id="filter-drawer-close" aria-label="Kapat">×</button>
    </div>

    <form method="get" action="<?= e($catBase) ?>" id="filter-form">
      <?php if ($sort !== 'new'): ?>
        <input type="hidden" name="sort" value="<?= e($sort) ?>">
      <?php endif; ?>

      <div class="filter-drawer-body">

        <!-- Fiyat Aralığı -->
        <details class="ft-section" <?= ($priceMin !== null || $priceMax !== null) ? 'open' : '' ?>>
          <summary>
            <h4>Fiyat Aralığı
              <?php if ($priceMin !== null || $priceMax !== null): ?>
                <em><?= ($priceMin !== null ? (int)$priceMin : $priceRange['min']) ?>₺
                    – <?= ($priceMax !== null ? (int)$priceMax : $priceRange['max']) ?>₺</em>
              <?php endif; ?>
            </h4>
          </summary>
          <div class="ft-pricerow" style="margin-top:8px">
            <input type="number" name="price_min" id="price_min"
                   placeholder="Min ₺"
                   value="<?= $priceMin !== null ? (int)$priceMin : '' ?>"
                   min="<?= $priceRange['min'] ?>" max="<?= $priceRange['max'] ?>"
                   step="1" inputmode="numeric">
            <span>—</span>
            <input type="number" name="price_max" id="price_max"
                   placeholder="Maks ₺"
                   value="<?= $priceMax !== null ? (int)$priceMax : '' ?>"
                   min="<?= $priceRange['min'] ?>" max="<?= $priceRange['max'] ?>"
                   step="1" inputmode="numeric">
          </div>
          <p class="muted" style="font-size:12px;margin-top:8px">
            Aralık: <?= (int)$priceRange['min'] ?>₺ – <?= (int)$priceRange['max'] ?>₺
          </p>
        </details>

        <!-- İndirimli Ürünler -->
        <details class="ft-section" <?= $saleOnly ? 'open' : '' ?>>
          <summary><h4>Durum</h4></summary>
          <label class="ft-check" style="margin-top:8px">
            <input type="checkbox" name="sale" value="1" <?= $saleOnly ? 'checked' : '' ?>>
            Yalnızca İndirimli Ürünler
          </label>
        </details>

        <?php if ($subcats): ?>
        <!-- Alt Kategoriler -->
        <details class="ft-section" <?= !empty($subFilter) ? 'open' : '' ?>>
          <summary>
            <h4>Alt Kategoriler
              <?php if (!empty($subFilter)): ?>
                <em><?= count($subFilter) ?> seçili</em>
              <?php endif; ?>
            </h4>
          </summary>
          <div class="ft-list" style="margin-top:8px">
            <?php foreach ($subcats as $sc): ?>
              <label class="ft-check">
                <input type="checkbox" name="cats[]" value="<?= (int)$sc['id'] ?>"
                       <?= in_array((int)$sc['id'], $subFilter) ? 'checked' : '' ?>>
                <?= e($sc['name']) ?>
                <em><?= (int)$sc['cnt'] ?></em>
              </label>
            <?php endforeach; ?>
          </div>
        </details>
        <?php endif; ?>

      </div><!-- /filter-drawer-body -->

      <div class="filter-drawer-foot">
        <button type="submit" class="btn btn-primary" style="flex:1">Uygula</button>
        <?php
        $clearQ3 = $sort !== 'new' ? '?sort=' . urlencode($sort) : '';
        ?>
        <a href="<?= e($catBase . $clearQ3) ?>" class="btn btn-secondary" id="filter-clear-btn">Temizle</a>
      </div>
    </form>
  </div>
</div>

<script defer src="<?= SITE_URL ?>/assets/js/components/filter-drawer.min.js?v=<?= asset_v('js/components/filter-drawer.min.js') ?>"></script>
<script defer src="<?= SITE_URL ?>/assets/js/components/loadmore.min.js?v=<?= asset_v('js/components/loadmore.min.js') ?>"></script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
