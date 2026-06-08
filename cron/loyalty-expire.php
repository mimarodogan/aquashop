<?php
/**
 * Sadakat Puanı Expire Cron'u
 * ───────────────────────────
 * Günlük çalıştırın:
 *   php /home/kullanici/public_html/cron/loyalty-expire.php
 *
 * Davranış:
 *  - loyalty_transactions tablosunda type='earn' ve expires_at < NOW() olan satırları işle
 *  - Her biri için bakiyeyi azaltma değil — net hesap: kullanıcının toplam expire'i ne kadar?
 *  - Aynı earn ikinci kez expire edilmesin diye expire kaydı eklenince orijinal earn'ın
 *    expires_at'ı NULL'a çekilir (idempotent).
 */

if (PHP_SAPI !== 'cli' && !defined('CRON_BYPASS')) {
    http_response_code(403);
    exit('Forbidden');
}

define('APP_ROOT', dirname(__DIR__));
require_once APP_ROOT . '/core/bootstrap.php';

$processed = 0;
$errors = 0;

try {
    // Kullanıcı bazında expire olacak puanları topla
    $st = db()->query(
        "SELECT user_id, SUM(points) AS total_expire, GROUP_CONCAT(id) AS tx_ids
         FROM loyalty_transactions
         WHERE type = 'earn'
           AND expires_at IS NOT NULL
           AND expires_at < NOW()
           AND points > 0
         GROUP BY user_id"
    );
    foreach ($st->fetchAll() as $row) {
        $userId  = (int)$row['user_id'];
        $expirePts = (int)$row['total_expire'];
        $txIds   = $row['tx_ids'];

        if ($expirePts <= 0) continue;

        // Şu anki bakiye expire'den az olabilir (kullanıcı zaten harcamış olabilir)
        $balance = (int)db()->prepare('SELECT points FROM loyalty_points WHERE user_id = ?')->execute([$userId]);
        $balRow  = db()->prepare('SELECT points FROM loyalty_points WHERE user_id = ?');
        $balRow->execute([$userId]);
        $balance = (int)($balRow->fetchColumn() ?: 0);

        $actualExpire = min($expirePts, $balance);
        if ($actualExpire > 0) {
            $ok = loyalty_apply_delta($userId, -$actualExpire, 'expire', null, "Otomatik expire — {$expirePts} puan süresi doldu (bakiyenizden {$actualExpire} düşüldü)");
            if (!$ok) { $errors++; continue; }
        }

        // İlgili earn satırlarının expires_at'ını NULL'a çek (tekrar işlenmesin)
        db()->prepare(
            "UPDATE loyalty_transactions SET expires_at = NULL WHERE id IN ($txIds)"
        )->execute();

        $processed++;
        echo "[OK] user={$userId}: {$actualExpire}/{$expirePts} puan expire edildi.\n";
    }
} catch (\Throwable $e) {
    echo "[ERROR] " . $e->getMessage() . "\n";
    exit(1);
}

echo "\nTamamlandı: {$processed} kullanıcı işlendi, {$errors} hata.\n";
