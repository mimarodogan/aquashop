<?php
/**
 * POS satış tamamlama endpoint'i
 * POST JSON body:  { csrf, items:[{id,name,price,qty,variation_id?}], payment, customer_name }
 * Döner: { ok, order_id, receipt }
 */
require_once __DIR__ . '/../core/bootstrap.php';
require_once APP_ROOT . '/models/Order.php';
require_once APP_ROOT . '/includes/stock.php';

header('Content-Type: application/json; charset=utf-8');
header('X-Robots-Tag: noindex');

// Sadece admin erişebilir
$admin = current_user();
if (!$admin || $admin['role'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Yetkisiz erişim']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Yalnızca POST']);
    exit;
}

$body = json_decode(file_get_contents('php://input'), true);
if (!$body) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Geçersiz JSON']);
    exit;
}

// CSRF kontrolü
if (!csrf_check($body['csrf'] ?? '')) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Güvenlik hatası (CSRF)']);
    exit;
}

$items        = $body['items'] ?? [];
$payment      = in_array($body['payment'] ?? '', ['nakit','kart','havale'], true)
                    ? $body['payment'] : 'nakit';
$customerName = trim($body['customer_name'] ?? '') ?: 'Mağaza Müşterisi';
$note         = trim($body['note'] ?? '');

if (empty($items)) {
    echo json_encode(['ok' => false, 'error' => 'Sepet boş']);
    exit;
}

// Sepet doğrulama & toplam hesaplama
$validItems = [];
$total = 0.0;

foreach ($items as $item) {
    $productId   = (int)($item['id'] ?? 0);
    $variationId = (int)($item['variation_id'] ?? 0);
    $qty         = max(1, (int)($item['qty'] ?? 1));

    if ($productId <= 0) continue;

    // Ürünü DB'den doğrula
    $p = db()->prepare('SELECT id, name, price, stock, has_variations FROM products WHERE id=? AND is_active=1');
    $p->execute([$productId]);
    $product = $p->fetch();
    if (!$product) continue;

    $price = (float)$product['price'];

    if ($variationId > 0) {
        $vs = db()->prepare('SELECT id, label, price, stock FROM product_variations WHERE id=? AND product_id=?');
        $vs->execute([$variationId, $productId]);
        $variation = $vs->fetch();
        if (!$variation) continue;
        $price = (float)$variation['price'];
        $availStock = (int)$variation['stock'];
    } else {
        $availStock = (int)$product['stock'];
    }

    if ($availStock < $qty) {
        echo json_encode([
            'ok'    => false,
            'error' => '"' . $product['name'] . '" için yeterli stok yok (mevcut: ' . $availStock . ')',
        ]);
        exit;
    }

    $lineTotal = $price * $qty;
    $total    += $lineTotal;

    $validItems[] = [
        'id'           => $productId,
        'name'         => $product['name'],
        'price'        => $price,
        'qty'          => $qty,
        'variation_id' => $variationId ?: null,
        'line_total'   => $lineTotal,
    ];
}

if (empty($validItems)) {
    echo json_encode(['ok' => false, 'error' => 'Geçerli ürün bulunamadı']);
    exit;
}

// --- orders tablosuna gerekli kolonları ekle (varsa atla) ---
try {
    db()->exec("ALTER TABLE orders ADD COLUMN IF NOT EXISTS source ENUM('web','pos') NOT NULL DEFAULT 'web'");
    db()->exec("ALTER TABLE orders ADD COLUMN IF NOT EXISTS pos_cashier_id INT NULL");
    db()->exec("ALTER TABLE orders ADD COLUMN IF NOT EXISTS payment_status ENUM('pending','paid','failed','refunded','partial_refund') NOT NULL DEFAULT 'pending'");
    db()->exec("ALTER TABLE orders ADD COLUMN IF NOT EXISTS paid_at DATETIME NULL");
    db()->exec("ALTER TABLE orders ADD COLUMN IF NOT EXISTS shipping_amount DECIMAL(10,2) NOT NULL DEFAULT 0");
    db()->exec("ALTER TABLE orders ADD COLUMN IF NOT EXISTS subtotal DECIMAL(10,2) NOT NULL DEFAULT 0");
    db()->exec("ALTER TABLE orders ADD COLUMN IF NOT EXISTS vat_amount DECIMAL(10,2) NOT NULL DEFAULT 0");
} catch (\Throwable $e) { /* Zaten var */ }

// --- Sipariş oluştur ---
$pdo = db();
$pdo->beginTransaction();
try {
    $roundedTotal = round($total, 2);

    // KDV hesapla (ürün fiyatları KDV dahildir)
    $vatRate    = (float)setting('vat_rate', '20');
    $vatAmount  = $vatRate > 0 ? round($roundedTotal * $vatRate / (100 + $vatRate), 2) : 0.0;
    $netSubtotal = round($roundedTotal - $vatAmount, 2); // KDV hariç tutar

    $st = $pdo->prepare(
        "INSERT INTO orders
            (user_id, full_name, email, phone, address, city, total, subtotal, shipping_amount,
             vat_amount, status, payment_status, paid_at, payment_method, note, source, pos_cashier_id)
         VALUES (NULL, ?, '', '', 'Mağaza satışı', '', ?, ?, 0.00,
                 ?, 'delivered', 'paid', NOW(), ?, ?, 'pos', ?)"
    );
    $st->execute([
        $customerName,
        $roundedTotal,   // total  (KDV dahil)
        $netSubtotal,    // subtotal (KDV hariç net)
        $vatAmount,      // vat_amount
        $payment,
        $note ?: null,
        (int)$admin['id'],
    ]);
    $orderId = (int)$pdo->lastInsertId();

    // order_items
    $sti = $pdo->prepare(
        'INSERT INTO order_items (order_id, product_id, variation_id, product_name, qty, price) VALUES (?,?,?,?,?,?)'
    );
    foreach ($validItems as $vi) {
        $sti->execute([
            $orderId,
            $vi['id'],
            $vi['variation_id'],
            $vi['name'],
            $vi['qty'],
            $vi['price'],
        ]);
    }

    $pdo->commit();
} catch (\Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    echo json_encode(['ok' => false, 'error' => 'Veritabanı hatası: ' . $e->getMessage()]);
    exit;
}

// --- Stok düş ---
stock_apply_order($orderId, 'pos_sale');

// --- Fiş verisi ---
$receipt = [
    'order_id'      => $orderId,
    'cashier'       => $admin['name'],
    'customer_name' => $customerName,
    'payment'       => $payment,
    'items'         => $validItems,
    'total'         => round($total, 2),
    'date'          => date('d.m.Y H:i'),
];

echo json_encode(['ok' => true, 'order_id' => $orderId, 'receipt' => $receipt], JSON_UNESCAPED_UNICODE);
