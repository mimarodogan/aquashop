<?php
/**
 * Exit-intent kupon AJAX endpoint.
 *
 * Akış:
 *   1) POST email + csrf
 *   2) Email validate + rate limit
 *   3) Newsletter'a kaydet
 *   4) Tekil 7-gün geçerli %5 kupon üret
 *   5) JSON döner: { ok: true, coupon: "WELCOME-XXXXXX" }
 */
require_once __DIR__ . '/../core/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');

function json_out(array $data, int $code = 200): void {
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_out(['ok' => false, 'error' => 'method_not_allowed'], 405);
}

// CSRF
if (!csrf_check($_POST['csrf'] ?? null)) {
    json_out(['ok' => false, 'error' => 'csrf'], 403);
}

// Rate limit: aynı IP'den saatte 3
$rlKey = 'exit_intent_' . md5(client_ip());
if (function_exists('rate_limit_exceeded') && rate_limit_exceeded($rlKey, 3, 3600)) {
    json_out(['ok' => false, 'error' => 'rate_limit'], 429);
}

// Honeypot
if (!empty($_POST['website']) || !empty($_POST['url'])) {
    json_out(['ok' => true, 'coupon' => null]); // sessizce başarılı gibi davran
}

$email = trim($_POST['email'] ?? '');
if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 190) {
    json_out(['ok' => false, 'error' => 'invalid_email'], 422);
}

// Newsletter abone
try {
    if (function_exists('newsletter_subscribe')) {
        newsletter_subscribe($email);
    }
} catch (\Throwable $e) {
    error_log('[exit_intent] newsletter subscribe failed: ' . $e->getMessage());
    // devam et — kuponu yine de ver
}

// Kupon üret — 7 gün geçerli, %5 indirim, per-user 1
$couponCode = 'WELCOME' . strtoupper(substr(bin2hex(random_bytes(4)), 0, 6));
try {
    $st = db()->prepare(
        "INSERT INTO coupons (code, type, amount, min_cart, max_discount,
                              usage_limit, per_user_limit, starts_at, ends_at, enabled, notes)
         VALUES (?, 'percent', 5.00, 0, NULL, 1, 1, NOW(), DATE_ADD(NOW(), INTERVAL 7 DAY), 1, ?)"
    );
    $st->execute([$couponCode, 'Auto: exit-intent capture for ' . $email]);
} catch (\Throwable $e) {
    error_log('[exit_intent] coupon insert failed: ' . $e->getMessage());
    json_out(['ok' => false, 'error' => 'coupon_failed'], 500);
}

// 30 gün boyunca aynı kullanıcıya tekrar gösterme
setcookie('exit_intent_done', '1', [
    'expires'  => time() + 30 * 86400,
    'path'     => '/',
    'samesite' => 'Lax',
    'secure'   => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
    'httponly' => false,
]);

json_out(['ok' => true, 'coupon' => $couponCode, 'message' => 'Kuponunuzu e-postanıza gönderdik!']);
