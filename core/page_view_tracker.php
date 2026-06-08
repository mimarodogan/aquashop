<?php
/**
 * Page View Tracker — server-side basit sayım.
 *
 * Header.php tarafından çağrılır, $page değişkenine göre kaydeder.
 * Bot'ları filtreler, admin sayfalarını yoksayar.
 *
 * Aynı session'da aynı sayfa için 30 saniye içindeki tekrar isteklerini
 * (örn. reload) tek görüntüleme sayar — basit dedupe.
 */

if (!function_exists('page_view_track')) {
    function page_view_track(?string $pageType = null): void {
        // Admin paneli kayıtlanmaz
        $uri = $_SERVER['REQUEST_URI'] ?? '/';
        if (stripos($uri, '/admin_panel/') !== false || stripos($uri, '/admin/') !== false) return;

        // Bot/sentetik araç filtresi
        $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
        if ($ua === '') return;
        $botPatterns = [
            'bot', 'crawler', 'spider', 'curl', 'wget', 'python', 'lighthouse',
            'pagespeed', 'headless', 'preview', 'monitor', 'pingdom', 'uptime',
            'googleother', 'bingpreview', 'facebookexternal',
        ];
        $uaLower = strtolower($ua);
        foreach ($botPatterns as $bp) {
            if (strpos($uaLower, $bp) !== false) return;
        }

        // Sadece GET — POST/AJAX kaydedilmez
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') return;

        // Session ID — analytics için anonim
        if (empty($_SESSION['pv_session'])) {
            $_SESSION['pv_session'] = bin2hex(random_bytes(16));
        }
        $sid = $_SESSION['pv_session'];

        // Dedupe: aynı session + aynı URL son 30 saniyede kaydedilmiş mi?
        $dedupeKey = 'pv_last_' . md5($uri);
        $now = time();
        if (!empty($_SESSION[$dedupeKey]) && ($now - (int)$_SESSION[$dedupeKey]) < 30) return;
        $_SESSION[$dedupeKey] = $now;

        try {
            $userId = null;
            if (function_exists('current_user')) {
                $u = current_user();
                if ($u && !empty($u['id'])) $userId = (int)$u['id'];
            }
            $st = db()->prepare(
                'INSERT INTO page_views (url, page_type, session_id, user_id, referrer)
                 VALUES (?, ?, ?, ?, ?)'
            );
            $st->execute([
                substr($uri, 0, 500),
                $pageType ? substr($pageType, 0, 32) : null,
                $sid,
                $userId,
                isset($_SERVER['HTTP_REFERER']) ? substr($_SERVER['HTTP_REFERER'], 0, 500) : null,
            ]);
        } catch (\Throwable $e) {
            // Tablo yoksa veya başka bir DB hatasında sessiz geç —
            // sayfa görüntüleme tracking'i kritik bir akış değil.
            error_log('[page_view_track] ' . $e->getMessage());
        }
    }
}

if (!function_exists('product_view_track')) {
    function product_view_track(int $productId): void {
        if ($productId <= 0) return;

        // Bot filtre — page_view_track ile aynı
        $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
        if ($ua === '') return;
        $uaLower = strtolower($ua);
        foreach (['bot','crawler','spider','headless','lighthouse','pagespeed'] as $bp) {
            if (strpos($uaLower, $bp) !== false) return;
        }

        if (empty($_SESSION['pv_session'])) {
            $_SESSION['pv_session'] = bin2hex(random_bytes(16));
        }
        $sid = $_SESSION['pv_session'];

        // Dedupe: aynı session aynı ürünü 5 dakikada bir kayar
        $dk = 'pdv_' . $productId;
        $now = time();
        if (!empty($_SESSION[$dk]) && ($now - (int)$_SESSION[$dk]) < 300) return;
        $_SESSION[$dk] = $now;

        try {
            $userId = null;
            if (function_exists('current_user')) {
                $u = current_user();
                if ($u && !empty($u['id'])) $userId = (int)$u['id'];
            }
            $st = db()->prepare(
                'INSERT INTO product_views (product_id, session_id, user_id) VALUES (?, ?, ?)'
            );
            $st->execute([$productId, $sid, $userId]);
        } catch (\Throwable $e) {
            error_log('[product_view_track] ' . $e->getMessage());
        }
    }
}
