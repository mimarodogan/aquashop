<?php
/**
 * Doğum Günü Kuponu Cron'u
 * ────────────────────────
 * Günlük çalıştırın (sabah 09:00 önerilir):
 *   php /home/kullanici/public_html/cron/birthday-coupon.php
 *
 * Davranış:
 *  - Bugün doğum günü olan ve aynı yıl içinde henüz kupon almamış kullanıcıları bul
 *    (users.birthday_coupon_year = bu yıl ise atla — idempotent)
 *  - Her birine tek-kullanımlık %15 kupon üret (BDAY-XXXXXX, 14 gün geçerli)
 *  - Email + (varsa) SMS gönder
 *  - users.birthday_coupon_year = YYYY işaretle
 */

if (PHP_SAPI !== 'cli' && !defined('CRON_BYPASS')) {
    http_response_code(403);
    exit('Forbidden');
}

define('APP_ROOT', dirname(__DIR__));
require_once APP_ROOT . '/core/bootstrap.php';
require_once APP_ROOT . '/includes/mailer.php';

$sent = 0; $err = 0;
$thisYear = (int)date('Y');

try {
    /* Bugün doğum günü olan ve bu yıl henüz kupon almamış kullanıcılar.
     * Ek güvenlik filtreleri (suistimal önleme):
     *  - Hesap en az 30 gün eski olmalı (doğum günü yaklaşınca kayıt olmuş olamaz)
     *  - VEYA en az 1 onaylı sipariş vermiş olmalı (gerçek müşteri kanıtı) */
    $st = db()->prepare(
        "SELECT u.id, u.name, u.email, u.phone, u.birth_date
         FROM users u
         WHERE u.role = 'customer'
           AND u.birth_date IS NOT NULL
           AND DATE_FORMAT(u.birth_date, '%m-%d') = DATE_FORMAT(CURDATE(), '%m-%d')
           AND (u.birthday_coupon_year IS NULL OR u.birthday_coupon_year < ?)
           AND (
                u.created_at < DATE_SUB(NOW(), INTERVAL 30 DAY)
             OR EXISTS (SELECT 1 FROM orders o
                        WHERE o.user_id = u.id
                          AND o.status IN ('paid','shipped','delivered'))
           )"
    );
    $st->execute([$thisYear]);

    foreach ($st->fetchAll() as $u) {
        // Kupon üret
        $code = 'BDAY' . strtoupper(substr(bin2hex(random_bytes(4)), 0, 6));
        try {
            $cp = db()->prepare(
                "INSERT INTO coupons (code, type, amount, min_cart, max_discount,
                                      usage_limit, per_user_limit, starts_at, ends_at, enabled, notes)
                 VALUES (?, 'percent', 15.00, 0, NULL, 1, 1, NOW(), DATE_ADD(NOW(), INTERVAL 14 DAY), 1, ?)"
            );
            $cp->execute([$code, 'Auto: birthday gift for user #' . $u['id']]);
        } catch (\Throwable $e) {
            error_log('[birthday] coupon insert failed: ' . $e->getMessage());
            $err++;
            continue;
        }

        // Email
        $name  = $u['name'] ?: 'Değerli Müşterimiz';
        $subject = '🎂 Doğum gününüz kutlu olsun — size özel %15 kuponunuz!';
        $body  = '<p>Sevgili <strong>' . e($name) . '</strong>,</p>'
               . '<p>Doğum gününüzü kutlarız! Bu özel günde sizi düşündük ve <strong>size özel %15 indirim kuponu</strong> hazırladık.</p>'
               . '<div style="margin:24px 0;padding:24px;background:linear-gradient(135deg,#FFF5E6,#FFFAEB);border:2px dashed #C9A24B;border-radius:10px;text-align:center">'
               .   '<p style="margin:0 0 6px;font-size:12px;letter-spacing:.16em;text-transform:uppercase;color:#5F5F5F">Doğum Günü Kuponunuz</p>'
               .   '<p style="margin:0;font-size:28px;font-weight:700;color:#1A1A1A;letter-spacing:.06em;font-family:monospace">' . e($code) . '</p>'
               .   '<p style="margin:10px 0 0;font-size:13px;color:#5F5F5F">%15 indirim · 14 gün geçerli · tek kullanım</p>'
               . '</div>'
               . '<p>Bizimle olduğunuz için teşekkür ederiz. İyi ki varsınız! 🎉</p>';

        $base = trim((string)setting('site_url','')) ?: ('https://' . ($_SERVER['HTTP_HOST'] ?? 'localhost'));
        $cartUrl = rtrim($base,'/') . '/urunler';
        $html = mail_template($subject, $body, 'Kuponu Kullan', $cartUrl);

        $emailOk = mail_send($u['email'], $subject, $html);

        // SMS (varsa)
        if ($emailOk && function_exists('sms_send_template') && !empty($u['phone'])) {
            @sms_send_template((string)$u['phone'], 'birthday', [
                'ad' => $name,
                'coupon' => $code,
            ]);
        }

        if ($emailOk) {
            db()->prepare('UPDATE users SET birthday_coupon_year = ? WHERE id = ?')
                ->execute([$thisYear, $u['id']]);
            $sent++;
            echo "[OK] user={$u['id']} email={$u['email']} coupon={$code}\n";
        } else {
            $err++;
            echo "[FAIL] user={$u['id']} email={$u['email']}\n";
        }
    }
} catch (\Throwable $e) {
    echo "[ERROR] " . $e->getMessage() . "\n";
    exit(1);
}

echo "\nTamamlandı: {$sent} gönderildi, {$err} hata.\n";
