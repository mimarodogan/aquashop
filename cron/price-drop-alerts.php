<?php
/**
 * Fiyat Düştü Uyarısı Cron'u (Faz 7.B)
 * ───────────────────────────────────────
 * Günlük çalıştırın (sabah önerilir):
 *   0 9 * * * php /home/.../cron/price-drop-alerts.php
 *
 * favorites tablosundaki price_at_fav alanı kullanılır.
 * Eğer ürünün mevcut fiyatı, kullanıcının favoriye eklediği fiyattan en az
 * X% düşmüşse → email gönder ve price_at_fav'i yeni fiyata güncelle (tekrar
 * uyarı yapılmasın).
 *
 * Settings:
 *   price_drop_alerts_enabled (default '0' — kapalı)
 *   price_drop_min_percent    (default 5 — min %5 düşüş)
 */

if (PHP_SAPI !== 'cli' && !defined('CRON_BYPASS')) {
    http_response_code(403);
    exit('Forbidden');
}

define('APP_ROOT', dirname(__DIR__));
require_once APP_ROOT . '/core/bootstrap.php';
require_once APP_ROOT . '/includes/mailer.php';

if (setting('price_drop_alerts_enabled', '0') !== '1') {
    echo "Fiyat düştü uyarısı kapalı (settings).\n";
    exit(0);
}

$minPct = (float)setting('price_drop_min_percent', '5');
if ($minPct <= 0) $minPct = 5;

$sent = 0; $err = 0;

try {
    $base = trim((string)setting('site_url','')) ?: 'https://' . ($_SERVER['HTTP_HOST'] ?? 'localhost');

    // price_at_fav'i set olan + ürünün mevcut fiyatı düşmüş kayıtlar
    $st = db()->prepare(
        "SELECT f.user_id, f.product_id, f.price_at_fav,
                p.name AS product_name, p.slug AS product_slug, p.image, p.price AS current_price,
                u.email, u.name AS user_name
         FROM favorites f
         JOIN products p ON p.id = f.product_id
         JOIN users u    ON u.id = f.user_id
         WHERE f.price_at_fav IS NOT NULL
           AND p.is_active = 1 AND p.deleted_at IS NULL
           AND p.price < f.price_at_fav
           AND ((f.price_at_fav - p.price) / f.price_at_fav * 100) >= ?
           AND (u.email_consent = 1 OR u.email_consent IS NULL)"
    );
    $st->execute([$minPct]);

    foreach ($st->fetchAll() as $row) {
        $oldP = (float)$row['price_at_fav'];
        $newP = (float)$row['current_price'];
        $dropPct = round((($oldP - $newP) / $oldP) * 100, 1);
        $saving  = $oldP - $newP;

        $url = rtrim($base, '/') . '/urun/' . rawurlencode($row['product_slug']);
        $name = $row['user_name'] ?: 'Değerli Müşterimiz';

        $imgHtml = !empty($row['image'])
            ? '<img src="' . htmlspecialchars($row['image'], ENT_QUOTES) . '" alt="" width="120" height="120" style="object-fit:cover;border-radius:8px;display:block">'
            : '';

        $subject = '💰 Favorinizdeki "' . $row['product_name'] . '" %' . (int)$dropPct . ' indirimde!';
        $body = '<p>Merhaba <strong>' . e($name) . '</strong>,</p>'
              . '<p>Favori listenizdeki ürünün fiyatı düştü:</p>'
              . '<table style="width:100%;border-collapse:collapse;margin:16px 0;border:1px solid #E8E8E8;border-radius:8px">'
              .   '<tr>'
              .     '<td style="padding:14px;width:140px;vertical-align:top">' . $imgHtml . '</td>'
              .     '<td style="padding:14px;vertical-align:top">'
              .       '<p style="margin:0 0 8px;font-size:16px;font-weight:600;color:#1A1A1A">' . e($row['product_name']) . '</p>'
              .       '<p style="margin:0 0 4px"><span style="text-decoration:line-through;color:#888">' . money($oldP) . '</span> → <strong style="color:#4F5C26;font-size:18px">' . money($newP) . '</strong></p>'
              .       '<p style="margin:0;font-size:13px;color:#a07000;font-weight:600">⚡ %' . (int)$dropPct . ' indirim · ' . money($saving) . ' tasarruf</p>'
              .     '</td>'
              .   '</tr>'
              . '</table>'
              . '<p style="font-size:13px;color:#666">Fırsatı kaçırmadan göz atın — bu fiyat geçici olabilir.</p>';

        $html = mail_template($subject, $body, 'Ürüne Git', $url);
        $ok = mail_send($row['email'], $subject, $html);

        if ($ok) {
            // Bir daha aynı düşüş için uyarı yapılmasın — fiyatı yeniden işaretle
            db()->prepare('UPDATE favorites SET price_at_fav = ? WHERE user_id = ? AND product_id = ?')
                ->execute([$newP, $row['user_id'], $row['product_id']]);
            $sent++;
            echo "[OK] user={$row['user_id']} product={$row['product_id']} drop={$dropPct}%\n";
        } else {
            $err++;
            echo "[FAIL] user={$row['user_id']} email={$row['email']}\n";
        }
    }
} catch (\Throwable $e) {
    echo "[ERROR] " . $e->getMessage() . "\n";
    exit(1);
}

echo "\nTamamlandı: {$sent} uyarı gönderildi, {$err} hata.\n";
