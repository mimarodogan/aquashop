<?php
/**
 * Anlık arama AJAX endpoint'i
 * GET ?q=... → JSON
 */
require_once __DIR__ . '/../core/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');
header('X-Robots-Tag: noindex');

$q = trim((string)($_GET['q'] ?? ''));
if (mb_strlen($q) < 2) {
    echo json_encode(['products' => [], 'posts' => []]);
    exit;
}

// D-2: LIKE özel karakterlerini ('%', '_', '\') literal yap (DoS koruması + tutarlı arama)
$qEsc = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $q);
$like = '%' . $qEsc . '%';
$results = ['products' => [], 'posts' => []];

// Ürünler (max 6)
try {
    $st = db()->prepare(
        'SELECT id, name, slug, image, price, old_price, price_on_request
         FROM products
         WHERE is_active=1 AND (name LIKE ? OR sku LIKE ? OR brand LIKE ?)
         ORDER BY is_featured DESC, created_at DESC
         LIMIT 6'
    );
    $st->execute([$like, $like, $like]);
    foreach ($st->fetchAll() as $p) {
        $results['products'][] = [
            'name'             => $p['name'],
            'url'              => url('product', ['slug' => $p['slug']]),
            'image'            => $p['image'] ?? null,
            'price'            => (!empty($p['price_on_request']) && (float)($p['price'] ?? 0) <= 0) ? 'İletişime Geçin' : money($p['price']),
            'old_price'        => ((!empty($p['price_on_request']) && (float)($p['price'] ?? 0) <= 0) || !$p['old_price']) ? null : money($p['old_price']),
            'price_on_request' => !empty($p['price_on_request']),
        ];
    }
} catch (Exception $e) {}

// Blog yazıları (max 4)
try {
    $st = db()->prepare(
        'SELECT title, slug FROM blog_posts
         WHERE is_published=1 AND (title LIKE ? OR excerpt LIKE ?)
         ORDER BY published_at DESC
         LIMIT 4'
    );
    $st->execute([$like, $like]);
    foreach ($st->fetchAll() as $post) {
        $results['posts'][] = [
            'title' => $post['title'],
            'url'   => url('blog_post', ['slug' => $post['slug']]),
        ];
    }
} catch (Exception $e) {}

echo json_encode($results, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
