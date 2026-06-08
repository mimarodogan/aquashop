<?php
/**
 * Düşük Stok Admin Uyarı Cron'u
 * ─────────────────────────────
 * cPanel'de günlük çalıştırın:
 *   php /home/kullanici/public_html/cron/low-stock-alert.php
 *
 * Davranış:
 *  - low_stock_threshold ayarı (default 5) altına düşmüş, aktif ürünleri bulur
 *  - Tek bir özet email gönderir (tek tek değil — spam olmasın)
 *  - Aynı ürün 24 saat içinde tekrar uyarılmaz (idempotent dedupe)
 *
 * Atlanma koşulları:
 *  - low_stock_threshold = 0 (kapalı)
 *  - low_stock_alert_email boş ve contact_email de boş
 */

if (PHP_SAPI !== 'cli' && !defined('CRON_BYPASS')) {
    http_response_code(403);
    exit('Forbidden');
}

define('APP_ROOT', dirname(__DIR__));
require_once APP_ROOT . '/core/bootstrap.php';
require_once APP_ROOT . '/includes/mailer.php';
require_once APP_ROOT . '/includes/low_stock_alert.php';

$threshold = (int)setting('low_stock_threshold', '5');
if ($threshold <= 0) { echo "Düşük stok uyarısı kapalı (eşik=0).\n"; exit(0); }

$to = trim((string)setting('low_stock_alert_email','')) ?: trim((string)setting('contact_email',''));
if (!$to || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
    echo "Uyarı email adresi tanımsız.\n"; exit(0);
}

try {
    /* Eşik altındaki aktif ürünleri (varyasyonsuz veya varyasyonlu varyantları) bul.
     * Son 24 saat içinde zaten uyarılmış olanları atla — bunun için settings tablosuna
     * basit bir JSON map tutuyoruz: low_stock_notified = '{"123":1731234567,...}' */
    $notifiedJson = setting('low_stock_notified','') ?: '{}';
    $notified = json_decode($notifiedJson, true) ?: [];
    $now = time();
    // 24+ saat önce uyarılmış olanları "tekrar uyarılabilir" sayalım
    foreach ($notified as $pid => $ts) if (($now - (int)$ts) > 86400) unset($notified[$pid]);

    // Eşik altı ürünler
    $st = db()->prepare(
        "SELECT id, name, sku, stock
         FROM products
         WHERE is_active = 1
           AND has_variations = 0
           AND stock > 0 AND stock <= ?
         ORDER BY stock ASC, name ASC"
    );
    $st->execute([$threshold]);
    $candidates = $st->fetchAll();

    // Zaten uyarılmış olanları çıkar
    $alerts = [];
    foreach ($candidates as $p) {
        if (!isset($notified[$p['id']])) $alerts[] = $p;
    }

    if (!$alerts) { echo "Yeni düşük stok ürünü yok (toplam aday: " . count($candidates) . ").\n"; exit(0); }

    // Toplu özet email
    $base = trim((string)setting('site_url','')) ?: 'https://' . ($_SERVER['HTTP_HOST'] ?? 'localhost');
    $body  = '<p>Aşağıdaki ürünlerin stoğu düşük seviyeye geldi (eşik: ' . $threshold . '):</p>';
    $body .= '<table style="width:100%;border-collapse:collapse;margin:14px 0">';
    $body .= '<thead><tr><th style="text-align:left;padding:10px;background:#F4F4F4;border-bottom:2px solid #1A1A1A">Ürün</th><th style="text-align:left;padding:10px;background:#F4F4F4;border-bottom:2px solid #1A1A1A">SKU</th><th style="text-align:right;padding:10px;background:#F4F4F4;border-bottom:2px solid #1A1A1A">Stok</th></tr></thead><tbody>';
    foreach ($alerts as $p) {
        $editUrl = rtrim($base,'/') . '/admin_panel/products/edit.php?id=' . (int)$p['id'];
        $body .= '<tr>'
              . '<td style="padding:10px;border-bottom:1px solid #E8E8E8"><a href="' . htmlspecialchars($editUrl, ENT_QUOTES) . '" style="color:#1A1A1A;font-weight:600">' . htmlspecialchars($p['name'], ENT_QUOTES) . '</a></td>'
              . '<td style="padding:10px;border-bottom:1px solid #E8E8E8;font-family:monospace;color:#5F5F5F">' . htmlspecialchars($p['sku'] ?? '-', ENT_QUOTES) . '</td>'
              . '<td style="padding:10px;border-bottom:1px solid #E8E8E8;text-align:right;color:#9A2A2A;font-weight:600">' . (int)$p['stock'] . '</td>'
              . '</tr>';
        // İşaretle — tekrar uyarılmasın
        $notified[$p['id']] = $now;
    }
    $body .= '</tbody></table>';
    $body .= '<p style="color:#5F5F5F;font-size:13px">Bu uyarı 24 saatte bir kez gönderilir. Aynı ürünler stok yenilenmediği takdirde 24 saat sonra tekrar listelenir.</p>';

    $html = mail_template('Düşük Stok Özeti', $body, 'Yönetim Paneli', rtrim($base,'/') . '/admin_panel/products/stock.php');
    $ok   = mail_send($to, '⚠️ Düşük Stok Özeti — ' . count($alerts) . ' ürün', $html);

    if ($ok) {
        // Notified state'ini settings'e yaz
        $update = db()->prepare(
            'INSERT INTO settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)'
        );
        $update->execute(['low_stock_notified', json_encode($notified)]);
        echo "OK: " . count($alerts) . " ürün uyarısı gönderildi.\n";
    } else {
        echo "[FAIL] Email gönderilemedi.\n";
        exit(1);
    }
} catch (\Throwable $e) {
    echo "[ERROR] " . $e->getMessage() . "\n";
    exit(1);
}
