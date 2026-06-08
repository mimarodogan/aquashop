<?php
/**
 * Bülten abonelik handler.
 * - Sadece POST ve geçerli CSRF token'ı kabul eder.
 * - Same-origin redirect zorunlu (open-redirect koruması).
 * - Rate limit: aynı session/IP'den 1 saatte max 3 abonelik denemesi.
 * - Honeypot field (website/url) ile bot tespit.
 */
require_once __DIR__ . '/../core/bootstrap.php';

// 1) Sadece POST + CSRF
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !csrf_check($_POST['csrf'] ?? null)) {
    flash_set('err', 'Geçersiz istek.');
    redirect(SITE_URL . '/');
}

// 2) Rate limit (3/saat per IP)
$rlKey = 'newsletter_' . md5(client_ip());
if (rate_limit_exceeded($rlKey, 3, 3600)) {
    flash_set('err', 'Çok fazla deneme. Lütfen daha sonra tekrar deneyin.');
    redirect(SITE_URL . '/');
}

// 3) Geri dönüş URL — sadece aynı host
$back = $_SERVER['HTTP_REFERER'] ?? (SITE_URL . '/');
if (strpos($back, '://') !== false) {
    $host = parse_url($back, PHP_URL_HOST);
    if ($host !== ($_SERVER['HTTP_HOST'] ?? '')) {
        $back = SITE_URL . '/';
    }
}

// 4) Honeypot — bot'lar genelde tüm görünmez alanları doldurur
if (!empty($_POST['website']) || !empty($_POST['url'])) {
    // Sessizce başarılı gibi davran (bot'u bilgilendirme, DB'ye yazma)
    flash_set('success', 'Bülten aboneliğiniz tamamlandı.');
    redirect($back);
}

// 5) E-posta doğrula
$email = trim($_POST['email'] ?? '');
if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 190) {
    flash_set('err', 'Geçerli bir e-posta girin.');
    redirect($back);
}

// 6) Kayıt
try {
    newsletter_subscribe($email);
    flash_set('success', 'Bülten aboneliğiniz tamamlandı.');
} catch (Exception $e) {
    flash_set('err', 'Kayıt başarısız.');
}
redirect($back);
