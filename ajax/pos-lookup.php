<?php
/**
 * POS ürün arama endpoint'i
 * GET ?q=...  →  SKU / barkod / ürün adı ile arama, JSON döner
 */
require_once __DIR__ . '/../core/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');
header('X-Robots-Tag: noindex');

// Sadece admin erişebilir
$admin = current_user();
if (!$admin || $admin['role'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['error' => 'Yetkisiz erişim']);
    exit;
}

$q = trim($_GET['q'] ?? '');
if (mb_strlen($q) < 1) {
    echo json_encode(['results' => []]);
    exit;
}

require_once APP_ROOT . '/includes/variations.php';

// 1) Önce SKU ile tam eşleşme dene
$st = db()->prepare(
    "SELECT p.id, p.name, p.sku, p.price, p.old_price, p.stock, p.image, p.has_variations,
            p.price_on_request, c.name AS cat_name
     FROM products p
     LEFT JOIN categories c ON c.id = p.category_id
     WHERE p.is_active = 1 AND p.sku = ?
     LIMIT 1"
);
$st->execute([$q]);
$exact = $st->fetch();

if ($exact) {
    $results = [$exact];
} else {
    // 2) Ad veya SKU içeriyor mu?
    $like = '%' . $q . '%';
    $st2 = db()->prepare(
        "SELECT p.id, p.name, p.sku, p.price, p.old_price, p.stock, p.image, p.has_variations,
                p.price_on_request, c.name AS cat_name
         FROM products p
         LEFT JOIN categories c ON c.id = p.category_id
         WHERE p.is_active = 1 AND (p.name LIKE ? OR p.sku LIKE ?)
         ORDER BY p.name ASC
         LIMIT 12"
    );
    $st2->execute([$like, $like]);
    $results = $st2->fetchAll();
}

// Her sonuç için varyasyon fiyat aralığını ekle
$out = [];
foreach ($results as $r) {
    $item = [
        'id'              => (int)$r['id'],
        'name'            => $r['name'],
        'sku'             => $r['sku'] ?? '',
        'price'           => (float)$r['price'],
        'old_price'       => $r['old_price'] ? (float)$r['old_price'] : null,
        'stock'           => (int)$r['stock'],
        'image'           => $r['image'] ?? '',
        'cat_name'        => $r['cat_name'] ?? '',
        'price_on_request'=> !empty($r['price_on_request']),
        'has_variations'  => !empty($r['has_variations']),
        'variations'      => [],
    ];

    if ($item['has_variations']) {
        $vars = product_variations((int)$r['id']);
        foreach ($vars as $v) {
            $item['variations'][] = [
                'id'    => (int)$v['id'],
                'label' => $v['label'],
                'price' => (float)$v['price'],
                'stock' => (int)$v['stock'],
            ];
        }
    }

    $out[] = $item;
}

echo json_encode(['results' => $out], JSON_UNESCAPED_UNICODE);
