<?php
/**
 * Müşteri Seviye Güncelleme Cron'u
 * ────────────────────────────────
 * Haftalık çalıştırın (pazartesi 02:00 önerilir):
 *   php /home/kullanici/public_html/cron/loyalty-tier-update.php
 *
 * Davranış:
 *  - Her aktif müşteri için son 12 ayın toplam onaylı harcamasını hesaplar
 *  - Eşiklere göre tier belirler:
 *      < loyal_min       → 'new'
 *      ≥ loyal_min       → 'loyal'
 *      ≥ vip_min         → 'vip'
 *  - users.loyalty_tier'ı günceller (değiştiyse)
 *  - Seviye atlayanları ayrıca raporlar
 */

if (PHP_SAPI !== 'cli' && !defined('CRON_BYPASS')) {
    http_response_code(403);
    exit('Forbidden');
}

define('APP_ROOT', dirname(__DIR__));
require_once APP_ROOT . '/core/bootstrap.php';

$loyalMin = (float)setting('loyalty_tier_loyal_min', '2000');
$vipMin   = (float)setting('loyalty_tier_vip_min',   '10000');

$upgrades = 0; $downgrades = 0; $unchanged = 0;

try {
    // Tüm aktif müşterileri al
    $users = db()->query("SELECT id, name, loyalty_tier FROM users WHERE role = 'customer'")->fetchAll();

    $tierStmt = db()->prepare(
        "UPDATE users SET loyalty_tier = ? WHERE id = ?"
    );

    foreach ($users as $u) {
        // Son 12 ayın toplam onaylı harcaması
        $sp = db()->prepare(
            "SELECT COALESCE(SUM(total), 0) FROM orders
             WHERE user_id = ?
               AND status IN ('paid','shipped','delivered')
               AND created_at >= DATE_SUB(NOW(), INTERVAL 12 MONTH)"
        );
        $sp->execute([$u['id']]);
        $spend = (float)$sp->fetchColumn();

        $newTier = 'new';
        if ($spend >= $vipMin)        $newTier = 'vip';
        elseif ($spend >= $loyalMin)  $newTier = 'loyal';

        if ($newTier === $u['loyalty_tier']) {
            $unchanged++;
            continue;
        }

        $tierRank = ['new'=>0, 'loyal'=>1, 'vip'=>2];
        $oldR = $tierRank[$u['loyalty_tier']] ?? 0;
        $newR = $tierRank[$newTier] ?? 0;

        $tierStmt->execute([$newTier, $u['id']]);
        if ($newR > $oldR) {
            $upgrades++;
            echo "[UP]   user={$u['id']} {$u['loyalty_tier']} → {$newTier} (₺" . number_format($spend,2,',','.') . ")\n";
        } else {
            $downgrades++;
            echo "[DOWN] user={$u['id']} {$u['loyalty_tier']} → {$newTier} (₺" . number_format($spend,2,',','.') . ")\n";
        }
    }
} catch (\Throwable $e) {
    echo "[ERROR] " . $e->getMessage() . "\n";
    exit(1);
}

echo "\nTamamlandı: {$upgrades} yükseldi, {$downgrades} düştü, {$unchanged} değişmedi.\n";
