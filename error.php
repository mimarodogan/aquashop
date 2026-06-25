<?php
/**
 * Apache ErrorDocument hedefi — SUNUCU seviyesi hatalar için temalı sayfa.
 * (PHP seviyesi hatalar router.php / bootstrap.php exception handler tarafından
 *  zaten temalı gösteriliyor; bu dosya yalnız Apache'nin ürettiği hatalar için.)
 *
 * Minimal ve sağlam: yalnız SITE_URL/APP_ENV sabitlerini ve error_page'i yükler;
 * veritabanına dokunmaz — DB çökse bile hata sayfası render olur.
 */
require_once __DIR__ . '/config/db.php';          // SITE_URL, APP_ENV (DB bağlantısı yok)
require_once __DIR__ . '/includes/error_page.php';

$code = (int)($_GET['code'] ?? ($_SERVER['REDIRECT_STATUS'] ?? 500));
if (!in_array($code, [400, 401, 403, 404, 500, 503], true)) {
    $code = 500;
}
aq_render_error($code);
