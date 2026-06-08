<?php
/**
 * Stok hareketleri yardımcısı.
 * Tüm stok girdi/çıktıları stock_movements tablosuna yazılır (denetlenebilir).
 */
require_once __DIR__ . '/functions.php';

if (!function_exists('stock_ensure_table')) {
function stock_ensure_table(): void {
    static $done = false; if ($done) return; $done = true;
    db()->exec("CREATE TABLE IF NOT EXISTS stock_movements (
      id INT AUTO_INCREMENT PRIMARY KEY,
      product_id INT NOT NULL,
      delta INT NOT NULL,
      reason VARCHAR(60) NOT NULL,
      reference_type VARCHAR(40) DEFAULT NULL,
      reference_id INT DEFAULT NULL,
      note VARCHAR(255) DEFAULT NULL,
      stock_after INT DEFAULT NULL,
      created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
      INDEX idx_product (product_id),
      INDEX idx_ref (reference_type, reference_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
}}

/**
 * Bir ürünün stoğunu delta kadar değiştirir (negatif = düşüş).
 * Atomik: aynı anda iki sipariş gelirse race condition olmaz.
 */
if (!function_exists('stock_change')) {
function stock_change(int $productId, int $delta, string $reason, ?string $refType = null, ?int $refId = null, ?string $note = null, bool $quiet = false): void {
    stock_ensure_table();
    $pdo = db();
    // Önceki stok
    $before = (int)$pdo->prepare('SELECT stock FROM products WHERE id=?')->execute([$productId]) ?: 0;
    $bSt = $pdo->prepare('SELECT stock FROM products WHERE id=?'); $bSt->execute([$productId]);
    $before = (int)$bSt->fetchColumn();

    $upd = $pdo->prepare('UPDATE products SET stock = stock + ? WHERE id = ?');
    $upd->execute([$delta, $productId]);
    $cur = $pdo->prepare('SELECT stock FROM products WHERE id=?');
    $cur->execute([$productId]);
    $after = (int)$cur->fetchColumn();
    $pdo->prepare('INSERT INTO stock_movements (product_id, delta, reason, reference_type, reference_id, note, stock_after) VALUES (?,?,?,?,?,?,?)')
        ->execute([$productId, $delta, $reason, $refType, $refId, $note, $after]);

    // 0 idi şimdi >0 oldu → restock notify gönder.
    // $quiet=true (rezervasyon iadesi gibi geçici hareketler) ise tetikleme — "tekrar stokta" spam'ı olmasın.
    if (!$quiet && $before <= 0 && $after > 0) {
        try {
            require_once __DIR__ . '/restock_mailer.php';
            restock_send_notifications($productId);
        } catch (\Throwable $e) { /* sessiz */ }
    }

    // Düşük stok uyarısı admin'e — eşik altına yeni düşen ürün için
    $threshold = (int)setting('low_stock_threshold', '5');
    if (!$quiet && $threshold > 0 && $delta < 0 && $after <= $threshold && $before > $threshold) {
        try {
            require_once __DIR__ . '/low_stock_alert.php';
            low_stock_alert_send($productId, $after);
        } catch (\Throwable $e) { /* sessiz */ }
    }
}}

/**
 * Bir siparişin tüm satırlarını stoktan düşer.
 * Aynı sipariş için iki kez çağrılırsa idempotent — order_items.applied_at kontrolü yapar.
 */
if (!function_exists('stock_apply_order')) {
function stock_apply_order(int $orderId, string $reason = 'order_paid'): void {
    stock_ensure_table();
    $pdo = db();
    // ALTER TABLE = DDL → MySQL'de aktif transaction'ı implicit commit eder.
    // Transaction içinden çağrıldıysa (payment_callback gibi) DDL'i atla —
    // kolon zaten mevcut olmalı; değilse migration runner'dan ekle.
    if (!$pdo->inTransaction()) {
        try { $pdo->exec("ALTER TABLE order_items ADD COLUMN IF NOT EXISTS stock_applied_at DATETIME DEFAULT NULL"); } catch (\Throwable $e) {}
    }

    $items = $pdo->prepare('SELECT id, product_id, variation_id, qty, stock_applied_at FROM order_items WHERE order_id=?');
    $items->execute([$orderId]);
    $mark = $pdo->prepare('UPDATE order_items SET stock_applied_at=NOW() WHERE id=?');

    foreach ($items->fetchAll() as $it) {
        if (!empty($it['stock_applied_at'])) continue; // idempotency
        if (!$it['product_id']) continue;
        // Varyasyon varsa varyasyon stoğunu, yoksa ürün stoğunu düş
        if (!empty($it['variation_id'])) {
            stock_change_variation((int)$it['variation_id'], -1 * (int)$it['qty'], $reason, 'order', $orderId);
        } else {
            stock_change((int)$it['product_id'], -1 * (int)$it['qty'], $reason, 'order', $orderId);
        }
        $mark->execute([(int)$it['id']]);
    }
}}

/**
 * Varyasyonun stoğunu değiştirir. Aynı stock_movements tablosuna kayıt düşer
 * ama product_id alanına ana ürün ID'si yazılır (kategori raporları için).
 */
if (!function_exists('stock_change_variation')) {
function stock_change_variation(int $variationId, int $delta, string $reason, ?string $refType = null, ?int $refId = null): void {
    stock_ensure_table();
    $pdo = db();
    $info = $pdo->prepare('SELECT id, product_id, stock, label FROM product_variations WHERE id=?');
    $info->execute([$variationId]);
    $v = $info->fetch();
    if (!$v) return;
    $before = (int)$v['stock'];

    $pdo->prepare('UPDATE product_variations SET stock = stock + ? WHERE id=?')->execute([$delta, $variationId]);
    $cur = $pdo->prepare('SELECT stock FROM product_variations WHERE id=?');
    $cur->execute([$variationId]);
    $after = (int)$cur->fetchColumn();

    $pdo->prepare('INSERT INTO stock_movements (product_id, delta, reason, reference_type, reference_id, note, stock_after) VALUES (?,?,?,?,?,?,?)')
        ->execute([(int)$v['product_id'], $delta, $reason, $refType, $refId, 'Varyasyon: ' . $v['label'], $after]);
}}

/**
 * Sipariş iptal edilince stoğu geri ekler.
 */
if (!function_exists('stock_revert_order')) {
function stock_revert_order(int $orderId, string $reason = 'order_cancelled', bool $quiet = false): void {
    stock_ensure_table();
    $pdo = db();
    $items = $pdo->prepare('SELECT id, product_id, variation_id, qty, stock_applied_at FROM order_items WHERE order_id=?');
    $items->execute([$orderId]);
    $clear = $pdo->prepare('UPDATE order_items SET stock_applied_at=NULL WHERE id=?');
    foreach ($items->fetchAll() as $it) {
        if (empty($it['stock_applied_at'])) continue; // hiç düşülmemişse geri ekleme
        if (!$it['product_id']) continue;
        // Hangi stok düşüldüyse onu geri ekle: varyasyon varsa varyasyon, yoksa ürün
        if (!empty($it['variation_id'])) {
            stock_change_variation((int)$it['variation_id'], (int)$it['qty'], $reason, 'order', $orderId);
        } else {
            stock_change((int)$it['product_id'], (int)$it['qty'], $reason, 'order', $orderId, null, $quiet);
        }
        $clear->execute([(int)$it['id']]);
    }
}}

/**
 * Sipariş satırlarını ATOMİK ve KOŞULLU stoktan düşer (ödeme öncesi rezervasyon).
 * Her satır için "stok >= adet" şartıyla düşer (UPDATE ... WHERE stock >= qty):
 *   - Race-safe: aynı anda iki müşteri son ürünü alamaz (biri başarır, diğeri 0 satır etkiler).
 *   - Yetersizse ['ok'=>false] döner; çağıran transaction'ı ROLLBACK eder (kısmi düşüşler geri alınır).
 *   - Idempotent: stock_applied_at dolu satırları atlar (callback ikinci kez düşmez).
 * ÖNEMLİ: Şema (stock_movements + order_items.stock_applied_at) çağırandan ÖNCE hazır olmalı
 *         (transaction içinde DDL çalıştırmamak için).
 */
if (!function_exists('stock_reserve_for_order')) {
function stock_reserve_for_order(int $orderId): array {
    $pdo = db();
    $items = $pdo->prepare('SELECT id, product_id, variation_id, qty, product_name, stock_applied_at FROM order_items WHERE order_id=?');
    $items->execute([$orderId]);
    $rows = $items->fetchAll();
    $mark = $pdo->prepare('UPDATE order_items SET stock_applied_at=NOW() WHERE id=?');
    $log  = $pdo->prepare('INSERT INTO stock_movements (product_id, delta, reason, reference_type, reference_id, note, stock_after) VALUES (?,?,?,?,?,?,?)');
    foreach ($rows as $it) {
        if (!empty($it['stock_applied_at'])) continue; // zaten düşülmüş
        if (!$it['product_id']) continue;
        $qty = (int)$it['qty'];
        if ($qty <= 0) continue;
        $name = $it['product_name'] ?: ('#' . (int)$it['product_id']);
        if (!empty($it['variation_id'])) {
            $vid = (int)$it['variation_id'];
            $upd = $pdo->prepare('UPDATE product_variations SET stock = stock - ? WHERE id = ? AND stock >= ?');
            $upd->execute([$qty, $vid, $qty]);
            if ($upd->rowCount() === 0) return ['ok'=>false, 'error'=>'"' . $name . '" için yeterli stok kalmadı.'];
            $vr = $pdo->prepare('SELECT stock, product_id, label FROM product_variations WHERE id=?');
            $vr->execute([$vid]); $v = $vr->fetch();
            $log->execute([(int)($v['product_id'] ?? $it['product_id']), -$qty, 'order_reserve', 'order', $orderId, 'Varyasyon: ' . ($v['label'] ?? ''), (int)($v['stock'] ?? 0)]);
        } else {
            $pid = (int)$it['product_id'];
            $upd = $pdo->prepare('UPDATE products SET stock = stock - ? WHERE id = ? AND stock >= ?');
            $upd->execute([$qty, $pid, $qty]);
            if ($upd->rowCount() === 0) return ['ok'=>false, 'error'=>'"' . $name . '" için yeterli stok kalmadı.'];
            $cur = $pdo->prepare('SELECT stock FROM products WHERE id=?');
            $cur->execute([$pid]); $after = (int)$cur->fetchColumn();
            $log->execute([$pid, -$qty, 'order_reserve', 'order', $orderId, null, $after]);
        }
        $mark->execute([(int)$it['id']]);
    }
    return ['ok'=>true, 'error'=>null];
}}

/**
 * Ödemesi tamamlanmamış (terk edilmiş) KART siparişlerini iptal eder ve rezerve edilen
 * stoğu SESSİZCE geri açar (restock e-postası tetiklemez). $minutes dakikadan eski olanlar.
 * Havale siparişlerine DOKUNMAZ (manuel onay bekler). DDL kullanmaz → transaction dışında çağrılmalı.
 */
if (!function_exists('orders_cancel_stale_card')) {
function orders_cancel_stale_card(int $minutes = 30): void {
    $pdo = db();
    $mins = max(5, $minutes);
    try {
        $sel = $pdo->prepare("SELECT id FROM orders
            WHERE payment_method='kart' AND status='pending'
              AND payment_status IN ('pending','failed')
              AND created_at < DATE_SUB(NOW(), INTERVAL {$mins} MINUTE)");
        $sel->execute();
        $ids = $sel->fetchAll(\PDO::FETCH_COLUMN);
    } catch (\Throwable $e) { return; }
    foreach ($ids as $oid) {
        $oid = (int)$oid;
        try { stock_revert_order($oid, 'order_expired', true); } catch (\Throwable $e) {}
        // Kullanılan sadakat puanını geri ver (varsa)
        try { require_once __DIR__ . '/../models/Loyalty.php'; @loyalty_revoke_for_order($oid); } catch (\Throwable $e) {}
        try {
            $pdo->prepare("UPDATE orders SET status='cancelled', cancelled_at=NOW(), cancellation_reason='Ödeme tamamlanmadı (otomatik iptal)' WHERE id=?")->execute([$oid]);
        } catch (\Throwable $e) {
            try { $pdo->prepare("UPDATE orders SET status='cancelled' WHERE id=?")->execute([$oid]); } catch (\Throwable $e2) {}
        }
    }
}}
