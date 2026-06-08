<?php
/**
 * Login Throttle — DB tabanlı brute-force koruması (K-5).
 * Session tabanlı eski yöntemin aksine, attacker yeni cookie/session ile
 * sayacı sıfırlayamaz; IP ve email başına persistent sayaç tutulur.
 *
 * Migration: sql/migrate_login_throttle.sql
 */

if (!defined('LOGIN_THROTTLE_MAX_ATTEMPTS'))   define('LOGIN_THROTTLE_MAX_ATTEMPTS', 5);
if (!defined('LOGIN_THROTTLE_WINDOW_SECONDS')) define('LOGIN_THROTTLE_WINDOW_SECONDS', 900); // 15 dk

/**
 * Başarısız deneme limiti aşıldı mı?
 * IP veya email bazlı pencere içinde MAX_ATTEMPTS aşıldıysa true.
 */
function login_throttle_blocked(string $ip, string $email = ''): bool {
    if ($ip === '' || $ip === '0.0.0.0') return false;
    try {
        // IP bazlı
        $st = db()->prepare(
            'SELECT COUNT(*) FROM login_attempts
             WHERE ip=? AND success=0
               AND attempted_at > DATE_SUB(NOW(), INTERVAL ? SECOND)'
        );
        $st->execute([$ip, LOGIN_THROTTLE_WINDOW_SECONDS]);
        if ((int)$st->fetchColumn() >= LOGIN_THROTTLE_MAX_ATTEMPTS) return true;

        // Email bazlı (verilirse)
        if ($email !== '') {
            $st = db()->prepare(
                'SELECT COUNT(*) FROM login_attempts
                 WHERE email=? AND success=0
                   AND attempted_at > DATE_SUB(NOW(), INTERVAL ? SECOND)'
            );
            $st->execute([$email, LOGIN_THROTTLE_WINDOW_SECONDS]);
            if ((int)$st->fetchColumn() >= LOGIN_THROTTLE_MAX_ATTEMPTS) return true;
        }
    } catch (\Throwable $e) {
        // Tablo yoksa: throttle pas geç (site kırılmasın); error_log'a yaz.
        error_log('[login_throttle] check failed: ' . $e->getMessage());
    }
    return false;
}

/**
 * Bir denemeyi kaydet (başarılı/başarısız).
 * Başarılı login sonrası eski başarısız sayaçları silmez — sadece toplu GC günlük.
 */
function login_throttle_record(string $ip, string $email, bool $success): void {
    if ($ip === '' || $ip === '0.0.0.0') return;
    try {
        db()->prepare('INSERT INTO login_attempts (ip, email, success) VALUES (?,?,?)')
            ->execute([$ip, $email !== '' ? $email : null, $success ? 1 : 0]);

        // Olasılığa bağlı garbage collection: 7 günden eski kayıtları sil
        if (mt_rand(1, 200) === 1) {
            db()->exec('DELETE FROM login_attempts WHERE attempted_at < DATE_SUB(NOW(), INTERVAL 7 DAY)');
        }
    } catch (\Throwable $e) {
        error_log('[login_throttle] record failed: ' . $e->getMessage());
    }
}
