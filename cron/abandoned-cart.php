<?php
/**
 * Terk Edilmiş Sepet — Çoklu Adım Hatırlatma Cron'u
 * ───────────────────────────────────────────────────
 * cPanel'de günlük (24 saat) çalıştırın — birden fazla çalıştırma idempotenttir:
 *   php /home/kullanici/public_html/cron/abandoned-cart.php
 *
 * Akış:
 *   Step 1 — 24 saat sonra: nazik hatırlatma
 *   Step 2 — 72 saat sonra: %5 indirim kuponu otomatik oluşturulur ve mailde gönderilir
 *   Step 3 —  7 gün sonra:  son şans + sosyal kanıt
 *
 * Bir sepete en fazla 3 hatırlatma gider; satın alma yapıldığında veya 14 gün geçtiğinde
 * kayıt dokunulmadan kalır (eski kayıtlar cron'da görmezden gelinir).
 *
 * Kupon kodu otomatik üretimi:
 *   "CARTBACK" + 6 karakter (örn. CARTBACKAB12C3), %5 indirim, 7 gün geçerli, per_user 1.
 */

if (PHP_SAPI !== 'cli' && !defined('CRON_BYPASS')) {
    http_response_code(403);
    exit('Forbidden');
}

define('APP_ROOT', dirname(__DIR__));
require_once APP_ROOT . '/core/bootstrap.php';
require_once APP_ROOT . '/includes/mailer.php';

$sent = ['step1' => 0, 'step2' => 0, 'step3' => 0];
$err  = 0;

/* ─────────────────────────────────────────────────────────────────
 * Kupon üret — step 2 için
 * ───────────────────────────────────────────────────────────────── */
function generate_cartback_coupon(string $email): ?string {
    try {
        $code = 'CARTBACK' . strtoupper(substr(bin2hex(random_bytes(4)), 0, 6));
        $st = db()->prepare(
            "INSERT INTO coupons (code, type, amount, min_cart, max_discount,
                                  usage_limit, per_user_limit, starts_at, ends_at, enabled, notes)
             VALUES (?, 'percent', 5.00, 0, NULL, 1, 1, NOW(), DATE_ADD(NOW(), INTERVAL 7 DAY), 1, ?)"
        );
        $st->execute([$code, 'Auto: cart recovery for ' . $email]);
        return $code;
    } catch (\Throwable $e) {
        error_log('[abandoned-cart] coupon gen failed: ' . $e->getMessage());
        return null;
    }
}

/* ─────────────────────────────────────────────────────────────────
 * Sepet özet HTML
 * ───────────────────────────────────────────────────────────────── */
function build_cart_html(array $cart): string {
    $html = '<table style="width:100%;border-collapse:collapse;margin:16px 0">';
    foreach ($cart as $item) {
        $imgHtml = !empty($item['image'])
            ? '<img src="' . htmlspecialchars($item['image'], ENT_QUOTES) . '" alt="" width="48" height="48" style="object-fit:cover;border-radius:4px;vertical-align:middle">'
            : '<span style="display:inline-block;width:48px;height:48px;background:#F4F4F4;border-radius:4px;vertical-align:middle"></span>';
        $variantTxt = !empty($item['variant_label'])
            ? ' <span style="color:#5F5F5F;font-size:12px">(' . htmlspecialchars($item['variant_label'], ENT_QUOTES) . ')</span>'
            : '';
        $lineTotal = number_format((float)($item['price'] ?? 0) * (int)($item['qty'] ?? 1), 2, ',', '.');
        $html .= '<tr style="border-bottom:1px solid #E8E8E8">'
            . '<td style="padding:10px 8px;width:56px">' . $imgHtml . '</td>'
            . '<td style="padding:10px 8px;font-size:14px;color:#1A1A1A">' . htmlspecialchars($item['name'] ?? '', ENT_QUOTES) . $variantTxt . '</td>'
            . '<td style="padding:10px 8px;text-align:right;font-size:14px;font-weight:600;color:#1A1A1A;white-space:nowrap">' . $lineTotal . ' ₺</td>'
            . '</tr>';
    }
    $html .= '</table>';
    return $html;
}

/* ─────────────────────────────────────────────────────────────────
 * Step işleyici — her adım için ayrı sorgu ve template
 * ───────────────────────────────────────────────────────────────── */
function process_step(int $step, string $intervalMin, string $intervalMax, string &$counter): array {
    global $sent, $err;
    $cartUrl = (defined('SITE_URL') ? rtrim(SITE_URL, '/') : '') . '/sepet';

    // Bir önceki adımı tamamlamış (reminder_step = step-1) ve yeterli süre geçmiş kayıtlar
    $prevStep = $step - 1;
    $st = db()->prepare(
        "SELECT ac.id, ac.user_id, ac.email, ac.cart_json, ac.coupon_code, u.name
         FROM abandoned_carts ac
         LEFT JOIN users u ON u.id = ac.user_id
         WHERE ac.reminder_step = ?
           AND ac.updated_at < DATE_SUB(NOW(), INTERVAL $intervalMin)
           AND ac.updated_at > DATE_SUB(NOW(), INTERVAL $intervalMax)
           AND (ac.last_reminder_at IS NULL OR ac.last_reminder_at < DATE_SUB(NOW(), INTERVAL 12 HOUR))
         ORDER BY ac.updated_at ASC
         LIMIT 100"
    );
    $st->execute([$prevStep]);

    foreach ($st->fetchAll() as $row) {
        $cart = json_decode($row['cart_json'], true);
        if (!$cart || !is_array($cart) || count($cart) === 0) continue;

        $name  = $row['name'] ?: 'Değerli Müşterimiz';
        $email = $row['email'];
        $cartHtml = build_cart_html($cart);

        // Step 2 → kupon oluştur (yoksa)
        $couponCode = $row['coupon_code'];
        if ($step === 2 && empty($couponCode)) {
            $couponCode = generate_cartback_coupon($email);
            if ($couponCode) {
                db()->prepare('UPDATE abandoned_carts SET coupon_code = ? WHERE id = ?')
                    ->execute([$couponCode, $row['id']]);
            }
        }

        // Step'e göre template
        switch ($step) {
            case 1:
                $subject = 'Sepetinizde ürünler bekliyor';
                $bodyHtml = '<p>Merhaba <strong>' . htmlspecialchars($name, ENT_QUOTES) . '</strong>,</p>'
                          . '<p>Sepetinizde unutulan ürünler var! Alışverişinizi tamamlamak için hâlâ zaman var.</p>'
                          . '{{sepet_ozeti}}';
                $cta = 'Sepete Dön';
                $tmplKey = 'abandoned_cart';
                break;

            case 2:
                $subject = '🎁 Size özel %5 indirim — sepetinizde sizi bekliyor';
                $bodyHtml = '<p>Merhaba <strong>' . htmlspecialchars($name, ENT_QUOTES) . '</strong>,</p>'
                          . '<p>Hâlâ kararsız mısınız? Sepetinizdeki ürünleri tamamlamanız için size özel <strong>%5 indirim kuponu</strong> hazırladık.</p>';
                if ($couponCode) {
                    $bodyHtml .= '<div style="margin:24px 0;padding:20px;background:#F4F4F4;border:2px dashed #6B7A2F;border-radius:8px;text-align:center">'
                              . '<p style="margin:0 0 6px;font-size:12px;letter-spacing:.16em;text-transform:uppercase;color:#5F5F5F">Kupon Kodunuz</p>'
                              . '<p style="margin:0;font-size:24px;font-weight:700;color:#1A1A1A;letter-spacing:.05em;font-family:monospace">' . htmlspecialchars($couponCode, ENT_QUOTES) . '</p>'
                              . '<p style="margin:8px 0 0;font-size:12px;color:#5F5F5F">7 gün geçerli · checkout sayfasında uygulayın</p>'
                              . '</div>';
                }
                $bodyHtml .= '{{sepet_ozeti}}';
                $cta = 'Kuponu Kullan';
                $tmplKey = 'abandoned_cart_step2';
                break;

            case 3:
                $subject = '⏰ Son hatırlatma — sepetinizdeki ürünler kayboluyor';
                $bodyHtml = '<p>Merhaba <strong>' . htmlspecialchars($name, ENT_QUOTES) . '</strong>,</p>'
                          . '<p>Bu sizin <strong>son hatırlatmamız</strong>. Sepetinizdeki ürünler yakında stoktan düşülebilir veya başkaları tarafından alınabilir.</p>'
                          . '<p style="background:#FFF8E5;padding:12px 16px;border-left:4px solid #C9A24B;margin:16px 0">⚡ <strong>Sosyal kanıt:</strong> Bu ürünler son zamanlarda yoğun ilgi görüyor. Sipariş vermeyi geciktirmeyin.</p>';
                if (!empty($couponCode)) {
                    $bodyHtml .= '<p>Hâlâ aktif olan <strong>%5 indirim kuponunuz</strong>: <code style="background:#F4F4F4;padding:4px 8px;border-radius:4px;font-weight:600">' . htmlspecialchars($couponCode, ENT_QUOTES) . '</code></p>';
                }
                $bodyHtml .= '{{sepet_ozeti}}';
                $cta = 'Hemen Tamamla';
                $tmplKey = 'abandoned_cart_step3';
                break;

            default:
                continue 2;
        }

        $tmpl = mail_template_get($tmplKey, [
            '{{isim}}'        => $name,
            '{{sepet_ozeti}}' => $cartHtml,
            '{{sepet_url}}'   => $cartUrl,
            '{{kupon_kodu}}'  => $couponCode ?? '',
        ], $subject, $bodyHtml);

        $body = mail_template($tmpl['subject'], $tmpl['body_html'], $cta, $cartUrl);

        if (mail_send($email, $tmpl['subject'], $body)) {
            db()->prepare(
                'UPDATE abandoned_carts
                 SET reminder_step = ?, last_reminder_at = NOW(),
                     notified_at = COALESCE(notified_at, NOW())
                 WHERE id = ?'
            )->execute([$step, $row['id']]);
            $counter++;
            echo "[OK step{$step}] {$email}\n";
        } else {
            $err++;
            echo "[FAIL step{$step}] {$email}\n";
        }
    }
    return $sent;
}

try {
    // STEP 1: 24-72 saat arasında ve henüz hiç hatırlatılmamış
    process_step(1, '24 HOUR', '72 HOUR', $sent['step1']);

    // STEP 2: 72 saat - 7 gün arasında ve step=1
    process_step(2, '72 HOUR', '7 DAY',   $sent['step2']);

    // STEP 3: 7-14 gün arasında ve step=2
    process_step(3, '7 DAY',   '14 DAY',  $sent['step3']);

} catch (Exception $e) {
    echo "[ERROR] " . $e->getMessage() . "\n";
    exit(1);
}

echo "\nTamamlandı: Step1={$sent['step1']}, Step2={$sent['step2']}, Step3={$sent['step3']}, Hata={$err}\n";
