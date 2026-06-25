<?php
/**
 * Çekirdek başlatıcı: tüm istekler bu dosyayı dahil eder.
 * Tüm public fonksiyonların API'si korunmuştur.
 */
define('APP_ROOT', dirname(__DIR__));

// Temaya uygun HTTP hata sayfaları (aq_render_error). Bağımsız; erken yüklenir
// ki exception handler tetiklendiğinde hazır olsun.
require_once APP_ROOT . '/includes/error_page.php';

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

    // Request ID üret → kullanıcı bunu destek ekibine söyleyebilir; log'a yaz.
    $__reqId = substr(bin2hex(random_bytes(4)), 0, 8);
    error_log('[req=' . $__reqId . '] ' . get_class($e) . ': ' . $e->getMessage()
        . ' in ' . $e->getFile() . ':' . $e->getLine());
    $__isDev = defined('APP_ENV') && APP_ENV === 'development';

    // Temaya uygun 500 hata sayfası. O-10 GÜVENLİK: teknik detay yalnız development'ta.
    aq_render_error(500, $__reqId, $__isDev
        ? (get_class($e) . ': ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine())
        : null);
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
