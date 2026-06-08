<?php
/**
 * Stok Rezervasyonu Temizleme Cron'u
 * ──────────────────────────────────
 * Her 5 dakikada çalıştırılması önerilir (cPanel cron tarifesi:
 * 5 dakikada bir, her saat, her gün, her ay, her gün):
 *   "*\/5 * * * * php /home/.../cron/cart-reservation-cleanup.php"
 *
 * Süresi dolmuş rezervasyonları siler. (PDP'deki "Sepetlerde X adet bekliyor"
 * göstergesi otomatik düzelir — feature_helpers.php her zaman expires_at > NOW() bakar)
 */

if (PHP_SAPI !== 'cli' && !defined('CRON_BYPASS')) {
    http_response_code(403);
    exit('Forbidden');
}

define('APP_ROOT', dirname(__DIR__));
require_once APP_ROOT . '/core/bootstrap.php';

try {
    $st = db()->prepare("DELETE FROM cart_reservations WHERE expires_at < NOW()");
    $st->execute();
    $deleted = $st->rowCount();
    echo "Cleanup: {$deleted} expired reservation removed.\n";
} catch (\Throwable $e) {
    echo "[ERROR] " . $e->getMessage() . "\n";
    exit(1);
}
