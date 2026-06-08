<?php
/**
 * Ürün detay sayfası — controller
 *
 * Sorumluluklar:
 *  - Slug'a göre ürünü çek (404 yoksa)
 *  - POST: sepete ekle (varyasyon kontrolü dahil)
 *  - Galeri, ilgili ürünler, SSS, JSON-LD şemalarını hazırla
 *
 * Görüntülenen değişkenler view tarafına aktarılır:
 *  $p, $title, $page, $gallery, $related, $reviewsList, $productFaqs,
 *  $extraSchemas, $base, $productUrl
 */
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/variations.php';
require_once __DIR__ . '/../../components/json-ld.php';

$page  = 'product';

$slug = $_GET['slug'] ?? '';
$st = db()->prepare(
    "SELECT p.*, c.name AS cat_name, c.slug AS cat_slug
     FROM products p
     LEFT JOIN categories c ON c.id = p.category_id
     WHERE p.slug = ? AND p.is_active = 1 AND p.deleted_at IS NULL"
);
$st->execute([$slug]);
$p = $st->fetch();

if (!$p) {
    http_response_code(404);
    $title = 'Bulunamadı';
    include __DIR__ . '/../../includes/header.php';
    echo '<section class="container" style="padding:120px 0"><h1>Ürün bulunamadı</h1></section>';
    include __DIR__ . '/../../includes/footer.php';
    exit;
}

$title = $p['name'];

// POST: sepete ekleme
if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf_check($_POST['csrf'] ?? null)) {
    $variantId = !empty($_POST['variant_id']) ? (int)$_POST['variant_id'] : null;
    if (product_has_variations((int)$p['id']) && !$variantId) {
        flash_set('err', 'Lütfen bir seçenek belirleyin.');
        redirect(url('product', ['slug' => $p['slug']]));
    }
    $__addedQty = max(1, (int)($_POST['qty'] ?? 1));
    cart_add((int)$p['id'], $__addedQty, $variantId);
    cart_persist(); // terk edilmiş sepet takibi

    // GA4 add_to_cart — bir sonraki sayfada (cart) tek seferlik bas
    $_SESSION['ga_pending_add_to_cart'] = [
        'product_id'   => (int)$p['id'],
        'product_name' => $p['name'],
        'price'        => (float)$p['price'],
        'sku'          => $p['sku'] ?? null,
        'cat_name'     => $p['cat_name'] ?? null,
        'qty'          => $__addedQty,
        'variant_id'   => $variantId,
    ];
    flash_set('success', 'Ürün sepete eklendi.');
    redirect(url('cart'));
}

// Ürün görüntüleme istatistiği (sosyal kanıt + dashboard için)
product_view_track((int)$p['id']);

// Galeri görselleri (ana + alt görseller)
$gallery = [];
if (!empty($p['image'])) {
    $gallery[] = $p['image'];
}
try {
    $g = db()->prepare(
        "SELECT path FROM product_images
         WHERE product_id = ?
         ORDER BY sort_order ASC, id ASC"
    );
    $g->execute([$p['id']]);
    foreach ($g->fetchAll() as $row) {
        $gallery[] = $row['path'];
    }
} catch (Exception $e) {
    // product_images tablosu yoksa görmezden gel
}
$gallery = array_values(array_unique(array_filter($gallery)));

// İlgili ürünler (aynı kategoriden, rastgele 4 adet)
$relatedSt = db()->prepare(
    "SELECT * FROM products
     WHERE is_active = 1 AND deleted_at IS NULL AND id <> ? AND category_id = ?
     ORDER BY RAND()
     LIMIT 4"
);
$relatedSt->execute([$p['id'], $p['category_id']]);
$related = $relatedSt->fetchAll();

// JSON-LD şemaları
$base       = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https' : 'http')
            . '://' . ($_SERVER['HTTP_HOST'] ?? '');
$productUrl = url('product', ['slug' => $p['slug']]);

$extraSchemas = [
    jsonld_product($p),
    jsonld_breadcrumb([
        ['name' => 'Anasayfa',        'url' => url('home')],
        ['name' => 'Ürünler',         'url' => url('products')],
        ['name' => $p['cat_name'] ?? 'Ürünler', 'url' => url('category', ['slug' => $p['cat_slug'] ?? ''])],
        ['name' => $p['name'],        'url' => $productUrl],
    ]),
];

$reviewsList = comment_list('product', $p['id']);
foreach (jsonld_reviews($reviewsList, $p['name'], 'Product') as $rv) {
    $extraSchemas[] = $rv;
}

// Ürüne özel SSS
$productFaqs = [];
try {
    $stf = db()->prepare('SELECT question, answer FROM product_faqs WHERE product_id = ? ORDER BY sort_order, id');
    $stf->execute([$p['id']]);
    $productFaqs = $stf->fetchAll();
} catch (Exception $e) {
    // product_faqs tablosu yoksa görmezden gel
}

if ($productFaqs) {
    $faqEntities = [];
    foreach ($productFaqs as $f) {
        $faqEntities[] = [
            '@type' => 'Question',
            'name'  => $f['question'],
            'acceptedAnswer' => ['@type' => 'Answer', 'text' => $f['answer']],
        ];
    }
    $extraSchemas[] = [
        '@context'   => 'https://schema.org',
        '@type'      => 'FAQPage',
        'mainEntity' => $faqEntities,
    ];
}

// View tarafında kullanılan ek değişkenler
$avg      = comment_avg_rating($p['id']);
$comments = $reviewsList;
