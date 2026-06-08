<?php
/**
 * Düşük stok admin uyarısı — eşik altına düşen ürünler için.
 */
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/mailer.php';

if (!function_exists('low_stock_alert_send')) {
function low_stock_alert_send(int $productId, int $currentStock): bool {
    $to = trim((string)setting('low_stock_alert_email', '')) ?: trim((string)setting('contact_email', ''));
    if (!$to || !filter_var($to, FILTER_VALIDATE_EMAIL)) return false;

    $st = db()->prepare('SELECT name, slug, sku FROM products WHERE id=?');
    $st->execute([$productId]);
    $p = $st->fetch();
    if (!$p) return false;

    $base = trim((string)setting('site_url','')) ?: ('https://' . ($_SERVER['HTTP_HOST'] ?? 'localhost'));
    $editUrl = rtrim($base,'/') . '/admin_panel/products/edit.php?id=' . (int)$productId;

    $body  = '<p>Aşağıdaki ürünün stoğu düşük seviyeye ulaştı:</p>';
    $body .= '<div style="padding:14px;border:1px solid #E8E8E8;border-radius:8px;background:#F4F4F4;margin:14px 0">';
    $body .= '<p style="margin:0 0 4px;font-weight:600;color:#0A0A0A">' . e($p['name']) . '</p>';
    if (!empty($p['sku'])) $body .= '<p style="margin:0;font-size:13px;color:#5F5F5F">SKU: <code>' . e($p['sku']) . '</code></p>';
    $body .= '<p style="margin:8px 0 0"><strong style="color:#9A2A2A">Mevcut stok: ' . $currentStock . '</strong> birim</p>';
    $body .= '</div>';
    $body .= '<p style="font-size:13px;color:#5F5F5F">Stoğu yenilemek için yönetici panelinden ürünü düzenleyin.</p>';

    $html = mail_template('Düşük Stok Uyarısı', $body, 'Ürünü Düzenle', $editUrl);
    return mail_send($to, '⚠️ Düşük Stok: ' . $p['name'], $html);
}}
