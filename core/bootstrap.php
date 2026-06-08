<?php
/**
 * Çekirdek başlatıcı: tüm istekler bu dosyayı dahil eder.
 * Tüm public fonksiyonların API'si korunmuştur.
 */
define('APP_ROOT', dirname(__DIR__));

/* ── Global hata yakalayıcı ──────────────────────────────────────────────
 * Ham PHP hataları yerine kullanıcı dostu mesaj gösterir.
 * Hata detayı sunucu log'una yazılır, ekrana yansımaz.
 * ──────────────────────────────────────────────────────────────────────── */
set_exception_handler(function (\Throwable $e): void {
    // Sunucu log'una yaz
    error_log('[EXCEPTION] ' . get_class($e) . ': ' . $e->getMessage()
        . ' in ' . $e->getFile() . ':' . $e->getLine());

    // Zaten çıktı başladıysa sadece mesaj ekle
    if (headers_sent()) {
        echo '<div style="margin:20px;padding:16px 20px;background:#fff3cd;border:1px solid #ffc107;border-radius:8px;font-family:sans-serif;font-size:14px;color:#856404">'
           . '<strong>⚠️ Bir hata oluştu.</strong> Lütfen tekrar deneyin. Sorun devam ederse yönetici ile iletişime geçin.</div>';
        return;
    }

    http_response_code(500);

    // Admin panel mi, yoksa site mi?
    $isAdmin = str_contains($_SERVER['REQUEST_URI'] ?? '', '/admin_panel/');

    // O-10 GÜVENLİK: stack trace / file:line ASLA UI'da gösterme. Sadece error_log'a yaz.
    // Request ID üret → kullanıcı bunu destek ekibine söyleyebilir.
    $__reqId = substr(bin2hex(random_bytes(4)), 0, 8);
    error_log('[req=' . $__reqId . '] ' . get_class($e) . ': ' . $e->getMessage()
        . ' in ' . $e->getFile() . ':' . $e->getLine());
    $__isDev = defined('APP_ENV') && APP_ENV === 'development';

    if ($isAdmin) {
        // Admin hata sayfası — detay sadece development ortamında görünür
        $techBlock = '';
        if ($__isDev) {
            $techBlock = '<code>' . htmlspecialchars(get_class($e) . ': ' . $e->getMessage(), ENT_QUOTES) . '</code>';
        } else {
            $techBlock = '<code>Request ID: ' . $__reqId . '</code>';
        }
        echo '<!DOCTYPE html><html lang="tr"><head><meta charset="UTF-8">
<title>Hata — Yönetim</title>
<style>
  body{margin:0;font-family:"Inter",sans-serif;background:#1a1f14;display:flex;align-items:center;justify-content:center;min-height:100vh}
  .box{background:#242b1c;border:1px solid #4a5a2a;border-radius:12px;padding:40px 48px;max-width:520px;width:90%;text-align:center}
  .ic{font-size:48px;margin-bottom:16px}
  h2{color:#c8b560;font-size:20px;margin:0 0 12px;font-family:serif}
  p{color:#a8a090;font-size:14px;line-height:1.7;margin:0 0 24px}
  code{display:block;background:#111;color:#e05c5c;padding:12px 16px;border-radius:6px;font-size:12px;text-align:left;margin-bottom:24px;word-break:break-all;line-height:1.6}
  a{display:inline-block;padding:10px 24px;background:#4a5a2a;color:#c8b560;border-radius:6px;text-decoration:none;font-size:13px;font-weight:600}
  a:hover{background:#5a6e34}
</style></head><body>
<div class="box">
  <div class="ic">⚠️</div>
  <h2>Beklenmedik Bir Hata Oluştu</h2>
  <p>İşlem tamamlanamadı. Hata sunucu günlüğüne kaydedildi.</p>
  ' . $techBlock . '
  <a href="javascript:history.back()">← Geri Dön</a>
</div></body></html>';
    } else {
        // Site hata sayfası — UI'da SADECE request ID. Stack trace yok.
        $techHtml = '<div style="margin-top:24px;text-align:center">'
            . '<p style="font-size:11px;color:#9a8870;margin:0">Request ID: <code style="background:#f6f3e9;padding:2px 6px;border-radius:4px;font-family:monospace">' . $__reqId . '</code></p>'
            . '</div>';

        echo '<!DOCTYPE html><html lang="tr"><head><meta charset="UTF-8">
<title>Bir Hata Oluştu</title>
<style>
  body{margin:0;font-family:sans-serif;background:#f9f5ed;display:flex;align-items:center;justify-content:center;min-height:100vh;padding:20px}
  .box{background:#fff;border:1px solid #e8dfc8;border-radius:12px;padding:40px 48px;max-width:640px;width:100%;text-align:center;box-shadow:0 4px 24px rgba(0,0,0,.08)}
  h2{color:#3d3d2e;font-size:22px;margin:0 0 12px}
  p{color:#7a7060;font-size:14px;line-height:1.7;margin:0 0 24px}
  a{display:inline-block;padding:10px 24px;background:#5a6a2e;color:#fff;border-radius:6px;text-decoration:none;font-size:13px;font-weight:600}
</style></head><body>
<div class="box">
  <div style="font-size:48px;margin-bottom:16px">⚠️</div>
  <h2>Beklenmedik Bir Hata</h2>
  <p>Üzgünüz, bir şeyler ters gitti. Sorun ekibimize iletildi.<br>Lütfen daha sonra tekrar deneyin.</p>
  <a href="/">Ana Sayfaya Dön</a>
  ' . $techHtml . '
</div></body></html>';
    }
    exit;
});

set_error_handler(function (int $severity, string $message, string $file, int $line): bool {
    if (!($severity & error_reporting())) return false;
    error_log('[ERROR] ' . $message . ' in ' . $file . ':' . $line);
    throw new \ErrorException($message, 0, $severity, $file, $line);
});

require_once APP_ROOT . '/core/Database.php';
require_once APP_ROOT . '/core/Session.php';
require_once APP_ROOT . '/core/Csrf.php';
require_once APP_ROOT . '/core/Flash.php';
require_once APP_ROOT . '/core/rate_limit.php';
require_once APP_ROOT . '/core/login_throttle.php';
require_once APP_ROOT . '/core/google_fonts.php';
require_once APP_ROOT . '/core/helpers.php';
require_once APP_ROOT . '/core/analytics_events.php';
require_once APP_ROOT . '/core/MetaCAPI.php';
require_once APP_ROOT . '/core/page_view_tracker.php';
require_once APP_ROOT . '/core/social_proof.php';
require_once APP_ROOT . '/core/cross_sell.php';
require_once APP_ROOT . '/core/feature_helpers.php';
require_once APP_ROOT . '/core/SMS.php';
require_once APP_ROOT . '/core/Auth.php';

require_once APP_ROOT . '/models/Setting.php';
require_once APP_ROOT . '/models/Cart.php';
require_once APP_ROOT . '/models/Favorite.php';
require_once APP_ROOT . '/models/Comment.php';
require_once APP_ROOT . '/models/Media.php';
require_once APP_ROOT . '/models/Product.php';
require_once APP_ROOT . '/models/Category.php';
require_once APP_ROOT . '/models/Order.php';
require_once APP_ROOT . '/models/User.php';
require_once APP_ROOT . '/models/BlogPost.php';
require_once APP_ROOT . '/models/BlogCategory.php';
require_once APP_ROOT . '/models/Page.php';
require_once APP_ROOT . '/models/Banner.php';
require_once APP_ROOT . '/models/Newsletter.php';
require_once APP_ROOT . '/models/Seo.php';
require_once APP_ROOT . '/models/Loyalty.php';
require_once APP_ROOT . '/includes/icons.php';
