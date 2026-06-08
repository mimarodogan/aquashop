<?php
/**
 * Stoka gelince haber ver — bildirim gönderici.
 * Bir ürün stoka girince bekleyen tüm e-postalara mail atar.
 */
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/mailer.php';

if (!function_exists('restock_send_notifications')) {
function restock_send_notifications(int $productId): int {
    $pdo = db();
    $st = $pdo->prepare('SELECT * FROM products WHERE id=?');
    $st->execute([$productId]);
    $p = $st->fetch();
    if (!$p) return 0;

    $pending = $pdo->prepare("SELECT id, email FROM restock_notifications WHERE product_id=? AND notified_at IS NULL");
    $pending->execute([$productId]);
    $rows = $pending->fetchAll();
    if (!$rows) return 0;

    $base = trim((string)setting('site_url','')) ?: ('https://' . ($_SERVER['HTTP_HOST'] ?? 'localhost'));
    $url = rtrim($base,'/') . '/urun/' . rawurlencode($p['slug']);

    $body  = '<p>İyi haber!</p>';
    $body .= '<p><strong>' . e($p['name']) . '</strong> ürünü tekrar stokta. Stok azalmadan tedarik etmek için sayfayı ziyaret edebilirsiniz.</p>';
    if (!empty($p['image'])) {
        $imgUrl = strpos($p['image'], 'http')===0 ? $p['image'] : ($base . $p['image']);
        $body .= '<p><img src="' . e($imgUrl) . '" alt="' . e($p['name']) . '" style="max-width:300px;border-radius:8px"></p>';
    }
    $html = mail_template('Stoka Geldi: ' . $p['name'], $body, 'Ürünü Görüntüle', $url);

    $upd = $pdo->prepare('UPDATE restock_notifications SET notified_at=NOW() WHERE id=?');
    $sent = 0;
    foreach ($rows as $r) {
        $ok = mail_send($r['email'], 'Stoka Geldi: ' . $p['name'], $html);
        if ($ok) {
            $upd->execute([(int)$r['id']]);
            $sent++;
        }
    }
    return $sent;
}}
