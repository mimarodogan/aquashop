<?php
/**
 * AJAX: Sepete ekle — sayfa değiştirmeden modal için.
 *
 * POST: csrf, product_id, qty (default 1), variant_id (opsiyonel)
 * Yanıt: JSON { ok, product:{name,image,price,slug}, cart:{count,total}, error? }
 */
require_once __DIR__ . '/../core/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');
function jout(array $d, int $c = 200): void {
    http_response_code($c);
    echo json_encode($d, JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') jout(['ok'=>false,'error'=>'method'], 405);
if (!csrf_check($_POST['csrf'] ?? null)) jout(['ok'=>false,'error'=>'csrf'], 403);

$pid = (int)($_POST['product_id'] ?? 0);
$qty = max(1, (int)($_POST['qty'] ?? 1));
$vid = !empty($_POST['variant_id']) ? (int)$_POST['variant_id'] : null;
if ($pid <= 0) jout(['ok'=>false,'error'=>'invalid_product'], 422);

// Ürün var ve aktif mi?
$st = db()->prepare('SELECT id, name, slug, price, image, stock, has_variations FROM products WHERE id = ? AND is_active = 1 AND deleted_at IS NULL');
$st->execute([$pid]);
$p = $st->fetch();
if (!$p) jout(['ok'=>false,'error'=>'not_found'], 404);

// Varyasyonluysa variant_id zorunlu
if (!empty($p['has_variations']) && !$vid) {
    jout(['ok'=>false,'error'=>'variant_required'], 422);
}

// Stok kontrolü (basit, full kontrol cart_add içinde olabilir)
$availableStock = (int)$p['stock'];
$displayPrice   = (float)$p['price'];
$displayImage   = $p['image'];
if ($vid) {
    $vst = db()->prepare('SELECT label, price, stock, image FROM product_variations WHERE id = ? AND product_id = ?');
    $vst->execute([$vid, $pid]);
    $v = $vst->fetch();
    if (!$v) jout(['ok'=>false,'error'=>'invalid_variant'], 422);
    $availableStock = (int)$v['stock'];
    $displayPrice   = (float)$v['price'];
    if (!empty($v['image'])) $displayImage = $v['image'];
}
if ($availableStock < $qty) {
    jout(['ok'=>false,'error'=>'out_of_stock','available'=>$availableStock], 422);
}

// Sepete ekle
cart_add($pid, $qty, $vid);
cart_persist();

// Rezervasyon (Faz 7.A — feature flag içeride kontrol)
if (function_exists('reservation_add')) reservation_add($pid, $vid, $qty);

// Sepet özeti
$items = cart_items();
$count = 0; $total = 0;
foreach ($items as $it) {
    $count += (int)$it['qty'];
    $total += (float)$it['price'] * (int)$it['qty'];
}

jout([
    'ok' => true,
    'product' => [
        'id'    => (int)$p['id'],
        'name'  => $p['name'],
        'slug'  => $p['slug'],
        'price' => $displayPrice,
        'image' => $displayImage,
        'qty'   => $qty,
    ],
    'cart' => [
        'count'    => $count,
        'total'    => $total,
        'total_fmt'=> money($total),
        'url'      => url('cart'),
    ],
]);
