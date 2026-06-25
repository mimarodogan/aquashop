<?php
/**
 * Ürün listeleme sayfası — controller.
 * Filtreleme, sıralama, sayfalama (AJAX dahil) ve veri hazırlama burada yapılır.
 */
require_once __DIR__ . '/../../includes/functions.php';

$page = 'products';
$title = 'Ürünler';

// Sayfalama parametreleri
define('PRODUCTS_PER_PAGE', 20);
$offset = max(0, (int)($_GET['offset'] ?? 0));
$isAjax = ($_GET['ajax'] ?? '') === 'page';

// ---- Parametreleri normalize et ----
$catSingle = isset($_GET['cat']) ? trim($_GET['cat']) : '';            // /urun/klasik için tek slug
$catMulti  = isset($_GET['cats']) ? (array)$_GET['cats'] : array();    // çoklu seçim
$brand     = isset($_GET['brand']) ? trim($_GET['brand']) : '';
$brandMulti= isset($_GET['brands']) ? (array)$_GET['brands'] : array();
$q         = isset($_GET['q']) ? trim($_GET['q']) : '';
$sort      = isset($_GET['sort']) ? $_GET['sort'] : 'new';
$priceMin  = isset($_GET['pmin']) && $_GET['pmin']!=='' ? (float)$_GET['pmin'] : null;
$priceMax  = isset($_GET['pmax']) && $_GET['pmax']!=='' ? (float)$_GET['pmax'] : null;
$inStock   = !empty($_GET['stock']);
$onSale    = !empty($_GET['sale']);

// /urun/klasik gelirse $catSingle dolu — onu da multi listesine ekle
if ($catSingle !== '' && !in_array($catSingle, $catMulti, true)) {
    $catMulti[] = $catSingle;
}
if ($brand !== '' && !in_array($brand, $brandMulti, true)) {
    $brandMulti[] = $brand;
}
$catMulti   = array_values(array_filter(array_unique(array_map('strval', $catMulti))));
$brandMulti = array_values(array_filter(array_unique(array_map('strval', $brandMulti))));

// ---- WHERE oluştur ----
$where = array('p.is_active = 1', 'p.deleted_at IS NULL');
$args  = array();

if ($catMulti) {
    // Seçili slug'ların + alt kategorilerinin ID'lerini bul
    $expandSlugs = $catMulti;
    $expandIds   = [];
    try {
        $place = implode(',', array_fill(0, count($catMulti), '?'));
        // Alt kategorilerin slug'larını da ekle
        $stCh = db()->prepare("SELECT child.slug FROM categories child JOIN categories parent ON child.parent_id=parent.id WHERE parent.slug IN ($place)");
        $stCh->execute($catMulti);
        foreach ($stCh->fetchAll() as $row) $expandSlugs[] = $row['slug'];
        $expandSlugs = array_values(array_unique($expandSlugs));
        // Slug → ID dönüştür
        $place2 = implode(',', array_fill(0, count($expandSlugs), '?'));
        $stIds = db()->prepare("SELECT id FROM categories WHERE slug IN ($place2)");
        $stIds->execute($expandSlugs);
        $expandIds = array_column($stIds->fetchAll(), 'id');
    } catch (Exception $e) {}

    if ($expandIds) {
        $inId = implode(',', array_fill(0, count($expandIds), '?'));
        // Hem category_id hem de product_categories pivot tablosunu kontrol et
        $where[] = "(p.category_id IN ($inId) OR p.id IN (SELECT product_id FROM product_categories WHERE category_id IN ($inId)))";
        foreach ($expandIds as $eid) $args[] = $eid;
        foreach ($expandIds as $eid) $args[] = $eid;
    }
}
if ($brandMulti) {
    $in = implode(',', array_fill(0, count($brandMulti), '?'));
    $where[] = "p.brand IN ($in)";
    foreach ($brandMulti as $bs) $args[] = $bs;
}
if ($q !== '') {
    $where[] = '(p.name LIKE ? OR p.short_desc LIKE ? OR p.sku LIKE ?)';
    $args[] = "%$q%"; $args[] = "%$q%"; $args[] = "%$q%";
}
if ($priceMin !== null) { $where[] = 'p.price >= ?'; $args[] = $priceMin; }
if ($priceMax !== null) { $where[] = 'p.price <= ?'; $args[] = $priceMax; }
if ($inStock)           { $where[] = 'p.stock > 0'; }
if ($onSale)            { $where[] = 'p.old_price IS NOT NULL AND p.old_price > p.price'; }

switch ($sort) {
    case 'price_asc':  $orderBy = 'p.price ASC'; break;
    case 'price_desc': $orderBy = 'p.price DESC'; break;
    case 'name':       $orderBy = 'p.name ASC'; break;
    case 'popular':    $orderBy = 'p.is_featured DESC, p.created_at DESC'; break;
    default:           $orderBy = 'p.created_at DESC';
}

// ---- Veriyi çek ----
$products = array();
$totalCount = 0;
$categories = array();
$brands = array();
$priceRange = array('min'=>0,'max'=>0);
try {
    // Toplam sayı (filtreli)
    $cntSql = "SELECT COUNT(*) FROM products p LEFT JOIN categories c ON c.id=p.category_id WHERE ".implode(' AND ',$where);
    $cnt = db()->prepare($cntSql); $cnt->execute($args); $totalCount = (int)$cnt->fetchColumn();

    // Sayfalı liste (LIMIT 20 OFFSET X)
    $sql = "SELECT p.*, c.name AS cat_name, c.slug AS cat_slug
            FROM products p LEFT JOIN categories c ON c.id=p.category_id
            WHERE ".implode(' AND ',$where)." ORDER BY $orderBy
            LIMIT " . PRODUCTS_PER_PAGE . " OFFSET " . $offset;
    $st = db()->prepare($sql); $st->execute($args); $products = $st->fetchAll();

    $categories = db()->query("SELECT c.*,
        (SELECT COUNT(DISTINCT p.id) FROM products p
         LEFT JOIN product_categories pc ON pc.product_id = p.id
         WHERE p.is_active=1 AND (p.category_id=c.id OR pc.category_id=c.id)
        ) AS cnt
        FROM categories c ORDER BY c.sort_order ASC, c.name ASC")->fetchAll();
    $brands     = db()->query("SELECT brand, COUNT(*) AS cnt FROM products WHERE brand IS NOT NULL AND brand<>'' AND is_active=1 GROUP BY brand ORDER BY brand")->fetchAll();
    $r = db()->query("SELECT MIN(price) AS mn, MAX(price) AS mx FROM products WHERE is_active=1")->fetch();
    if ($r) $priceRange = array('min'=>(float)$r['mn'], 'max'=>(float)$r['mx']);
} catch (Throwable $e) {}

// Aktif filtre sayısı
$activeCount = 0;
$activeCount += count($catMulti);
$activeCount += count($brandMulti);
if ($priceMin !== null) $activeCount++;
if ($priceMax !== null) $activeCount++;
if ($inStock)           $activeCount++;
if ($onSale)            $activeCount++;
if ($q !== '')          $activeCount++;

$sortLabels = array(
    'new'=>'En Yeniler', 'popular'=>'Popüler',
    'price_asc'=>'Fiyat: Düşükten Yükseğe', 'price_desc'=>'Fiyat: Yüksekten Düşüğe',
    'name'=>'İsme Göre (A-Z)'
);

// Sayfa sonunda görüntülenecek "Daha Fazla" butonu için yardımcı
$hasMore = ($offset + count($products)) < $totalCount;
$nextOffset = $offset + PRODUCTS_PER_PAGE;

// Toplam sayfa & geçerli sayfa (numeric pagination için)
$totalPages  = max(1, (int)ceil($totalCount / PRODUCTS_PER_PAGE));
$currentPage = (int)floor($offset / PRODUCTS_PER_PAGE) + 1;

// Pretty URL korunsun: tek kategori "/urun/<slug>" path'inde kalsın
$_pgBase = (!empty($catSingle) && count($catMulti) === 1 && empty($_GET['cats']))
    ? url('products', ['cat' => $catSingle])
    : url('products');
$_pgQueryBase = array_diff_key($_GET, ['offset'=>'', 'ajax'=>'', 'cat'=>'']);

// Sayfa URL üretici — closure (controller ve view ortak scope paylaşır)
$page_url = function ($pageNum) use ($_pgBase, $_pgQueryBase) {
    $q = $_pgQueryBase;
    if ($pageNum > 1) $q['offset'] = ($pageNum - 1) * PRODUCTS_PER_PAGE;
    return $_pgBase . ($q ? '?' . http_build_query($q) : '');
};

// rel=prev / rel=next link'leri (header.php $paginationLinks'ı head'e basar)
$paginationLinks = [];
if ($currentPage > 1)           $paginationLinks['prev'] = $page_url($currentPage - 1);
if ($currentPage < $totalPages) $paginationLinks['next'] = $page_url($currentPage + 1);

// AJAX isteği — sadece kartları üret, header/footer yok
if ($isAjax) {
    $favIds = fav_ids();
    $cardBack = $_SERVER['REQUEST_URI'] ?? url('products');
    foreach ($products as $p) {
        include __DIR__ . '/../../components/product-card.php';
    }
    // Buton durumunu işaret et — istemci data-has-more okur
    echo '<div data-has-more="'.($hasMore?'1':'0').'" data-next-offset="'.$nextOffset.'" data-total="'.$totalCount.'" style="display:none"></div>';
    exit;
}

// JSON-LD şemaları (CollectionPage + ItemList + BreadcrumbList)
$extraSchemas = array();
$__lbase  = (isset($_SERVER['HTTPS'])&&$_SERVER['HTTPS']!=='off' ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? '');

// BreadcrumbList — her zaman emit et (filtreli/sayfalı dahil)
$__bcCrumbs = array(
    array('name' => 'Anasayfa', 'url' => $__lbase . url('home')),
    array('name' => 'Ürünler',  'url' => $__lbase . url('products')),
);
if (count($catMulti) === 1) {
    $__bcCatSlug = $catMulti[0];
    $__bcCatName = '';
    foreach ($categories as $__bcC) if ($__bcC['slug'] === $__bcCatSlug) { $__bcCatName = $__bcC['name']; break; }
    if ($__bcCatName) {
        $__bcCrumbs[] = array(
            'name' => $__bcCatName,
            'url'  => $__lbase . url('products', array('cat' => $__bcCatSlug)),
        );
    }
}
if (function_exists('jsonld_breadcrumb')) {
    $__bcLD = jsonld_breadcrumb($__bcCrumbs);
    if ($__bcLD) $extraSchemas[] = $__bcLD;
}
unset($__bcCrumbs, $__bcCatSlug, $__bcCatName, $__bcC, $__bcLD);

// CollectionPage + ItemList — kategori sayfalarıyla tutarlı yapılandırılmış veri.
// Yalnızca filtresiz/aramasız ana listede ve 1. sayfada bas: bu URL canonical (/urun) ile
// birebir eşleşir; filtreli/sayfalı/arama varyantlarında yanıltıcı şema üretmez.
if (!empty($products) && $activeCount === 0 && $offset === 0) {
    $__lbase  = (isset($_SERVER['HTTPS'])&&$_SERVER['HTTPS']!=='off' ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? '');
    $__litems = array();
    foreach (array_slice($products, 0, 20) as $__li => $__lp) {
        $__litems[] = array(
            '@type'    => 'ListItem',
            'position' => $__li + 1,
            'url'      => $__lbase . url('product', array('slug' => $__lp['slug'])),
            'name'     => $__lp['name'],
        );
    }
    $extraSchemas[] = array(
        '@context'   => 'https://schema.org',
        '@type'      => 'CollectionPage',
        'name'       => 'Tüm Ürünler',
        'url'        => $__lbase . url('products'),
        'mainEntity' => array(
            '@type'           => 'ItemList',
            'numberOfItems'   => $totalCount,
            'itemListElement' => $__litems,
        ),
    );
    unset($__lbase, $__litems, $__li, $__lp);
}

// View tarafının kullandığı yardımcı fonksiyon
function remove_param_url($params, $key, $value=null/*kaldırılacak değer*/, $alsoKey=null) {
    $p = $params;
    if (isset($p[$key])) {
        if (is_array($p[$key]) && $value !== null) {
            $p[$key] = array_values(array_filter($p[$key], function($v) use ($value){ return $v !== $value; }));
            if (!$p[$key]) unset($p[$key]);
        } else {
            unset($p[$key]);
        }
    }
    if ($alsoKey && isset($p[$alsoKey])) {
        if (is_array($p[$alsoKey]) && $value !== null) {
            $p[$alsoKey] = array_values(array_filter($p[$alsoKey], function($v) use ($value){ return $v !== $value; }));
            if (!$p[$alsoKey]) unset($p[$alsoKey]);
        } else {
            unset($p[$alsoKey]);
        }
    }
    $qs = http_build_query($p);
    $base = url('products');
    return $qs ? "$base?$qs" : $base;
}
