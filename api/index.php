<?php
/**
 * Hafif JSON API — agent/LLM dostu makine-okunur uç noktalar.
 *   GET /api/products            → tüm aktif ürünler
 *   GET /api/product/{slug}      → tek ürün (yorumlar dahil)
 *   GET /api/blog                → tüm yazılar
 *   GET /api/blog/{slug}         → tek yazı
 *   GET /api/categories          → kategoriler
 *   GET /api/info                → kurum bilgisi
 */
require_once __DIR__ . '/../core/bootstrap.php';
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Cache-Control: public, max-age=300');

$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$uri = trim($uri, '/');
$parts = explode('/', $uri);
// 'api' segmentini at
if (!empty($parts) && $parts[0] === 'api') array_shift($parts);
$resource = $parts[0] ?? '';
$id       = $parts[1] ?? '';

$base = (isset($_SERVER['HTTPS'])&&$_SERVER['HTTPS']!=='off' ? 'https':'http') . '://' . ($_SERVER['HTTP_HOST'] ?? '');

function out($data, $status = 200) {
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    exit;
}

function product_to_array($p) {
    global $base;
    return array(
        'id'          => (int)$p['id'],
        'slug'        => $p['slug'],
        'name'        => $p['name'],
        'sku'         => $p['sku'] ?? null,
        'category'    => $p['cat_name'] ?? null,
        'short_desc'  => $p['short_desc'] ?? null,
        'description' => $p['description'] ?? null,
        'price'       => (float)$p['price'],
        'old_price'   => isset($p['old_price']) && $p['old_price'] !== null ? (float)$p['old_price'] : null,
        'currency'    => 'TRY',
        'stock'       => (int)$p['stock'],
        'in_stock'    => (int)$p['stock'] > 0,
        'image'       => !empty($p['image']) ? (strpos($p['image'],'http')===0 ? $p['image'] : $base.$p['image']) : null,
        'url'         => $base . url('product', array('slug'=>$p['slug'])),
    );
}

function post_to_array($p) {
    global $base;
    return array(
        'id'           => (int)$p['id'],
        'slug'         => $p['slug'],
        'title'        => $p['title'],
        'excerpt'      => $p['excerpt'] ?? null,
        'content_html' => $p['content'] ?? null,
        'category'     => $p['cat_name'] ?? null,
        'author'       => $p['author_name'] ?? null,
        'cover_image'  => !empty($p['cover_image']) ? (strpos($p['cover_image'],'http')===0 ? $p['cover_image'] : $base.$p['cover_image']) : null,
        'published_at' => $p['published_at'] ?? $p['created_at'],
        'views'        => (int)($p['views'] ?? 0),
        'url'          => $base . url('blog_post', array('slug'=>$p['slug'])),
    );
}

try {
    switch ($resource) {

        case '':
        case 'info':
            out(array(
                'name'    => setting('site_name'),
                'tagline' => setting('site_tagline'),
                'email'   => setting('contact_email'),
                'phone'   => setting('contact_phone'),
                'address' => setting('contact_address'),
                'site'    => $base . '/',
                'endpoints' => array(
                    'products'    => $base . '/api/products',
                    'product'     => $base . '/api/product/{slug}',
                    'blog'        => $base . '/api/blog',
                    'blog_post'   => $base . '/api/blog/{slug}',
                    'categories'  => $base . '/api/categories',
                ),
            ));

        case 'products':
            $rows = db()->query("SELECT p.*, c.name AS cat_name FROM products p LEFT JOIN categories c ON c.id=p.category_id WHERE p.is_active=1 ORDER BY p.created_at DESC")->fetchAll();
            out(array_map('product_to_array', $rows));

        case 'product':
            if ($id === '') out(array('error'=>'slug gerekli'), 400);
            $st = db()->prepare("SELECT p.*, c.name AS cat_name FROM products p LEFT JOIN categories c ON c.id=p.category_id WHERE p.slug=? AND p.is_active=1 LIMIT 1");
            $st->execute(array($id));
            $p = $st->fetch();
            if (!$p) out(array('error'=>'bulunamadı'), 404);
            $arr = product_to_array($p);
            // Yorum & ortalama
            $arr['rating'] = comment_avg_rating($p['id']);
            $arr['reviews'] = array_map(function($c){
                return array('author'=>$c['author_name'],'rating'=>$c['rating']?(int)$c['rating']:null,'body'=>$c['body'],'date'=>$c['created_at']);
            }, comment_list('product', $p['id']));
            out($arr);

        case 'blog':
            if ($id !== '') {
                $st = db()->prepare("SELECT p.*, c.name AS cat_name, u.name AS author_name FROM blog_posts p LEFT JOIN blog_categories c ON c.id=p.category_id LEFT JOIN users u ON u.id=p.author_id WHERE p.slug=? AND p.is_published=1 LIMIT 1");
                $st->execute(array($id));
                $p = $st->fetch();
                if (!$p) out(array('error'=>'bulunamadı'), 404);
                $arr = post_to_array($p);
                $arr['comments'] = array_map(function($c){
                    return array('author'=>$c['author_name'],'body'=>$c['body'],'date'=>$c['created_at']);
                }, comment_list('blog', $p['id']));
                out($arr);
            }
            $rows = db()->query("SELECT p.*, c.name AS cat_name FROM blog_posts p LEFT JOIN blog_categories c ON c.id=p.category_id WHERE p.is_published=1 ORDER BY COALESCE(p.published_at,p.created_at) DESC")->fetchAll();
            out(array_map('post_to_array', $rows));

        case 'categories':
            $rows = db()->query("SELECT id, name, slug, sort_order FROM categories ORDER BY sort_order ASC, name ASC")->fetchAll();
            foreach ($rows as &$r) {
                $r['url'] = $base . url('products', array('cat'=>$r['slug']));
            }
            out($rows);

        default:
            out(array('error'=>'kaynak bulunamadı','available'=>array('info','products','product/{slug}','blog','blog/{slug}','categories')), 404);
    }
} catch (Exception $e) {
    // O-2/O-6 GÜVENLİK: hata detayı kullanıcıya gönderme — sadece log'a yaz, generic mesaj döndür
    error_log('[api] Exception: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
    out(array('error'=>'Sunucu hatası. Lütfen daha sonra tekrar deneyin.'), 500);
}
