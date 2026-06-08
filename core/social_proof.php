<?php
/**
 * Sosyal Kanıt & Aciliyet Widget'ları — Conversion Quick Wins.
 *
 * - stock_badge_html($stock) → "Sadece N kaldı" rozeti
 * - product_purchase_count_24h($productId) → son 24 saatte kaç kez satıldı
 * - product_viewers_now($productId) → son 5 dk içinde kaç tekil oturum gördü
 * - social_proof_html($p) → tek satır HTML, PDP veya kart için
 *
 * Tüm fonksiyonlar minimum eşik altında HİÇ render etmez —
 * "1 kişi inceliyor" gibi sahte/zayıf sinyaller kullanıcı güvenini sarsar.
 */

if (!function_exists('low_stock_threshold')) {
    function low_stock_threshold(): int {
        $t = (int)setting('low_stock_badge_threshold', '10');
        return $t > 0 ? $t : 10;
    }
}

if (!function_exists('stock_badge_html')) {
    /**
     * Stok rozeti HTML'i — PDP, ürün kartı, sepet'te kullanılabilir.
     * - stock <= 0    → "Stokta Yok" (kırmızı)
     * - 1 ≤ stock ≤ low_stock_threshold → "Sadece N kaldı" (amber)
     * - stock > threshold → boş string (rozet gerek yok)
     */
    function stock_badge_html(int $stock, ?int $threshold = null): string {
        if ($threshold === null) $threshold = low_stock_threshold();
        if ($stock <= 0) {
            return '<span class="stock-badge stock-out">Stokta Yok</span>';
        }
        if ($stock <= $threshold) {
            return '<span class="stock-badge stock-low">⚡ Sadece ' . (int)$stock . ' adet kaldı</span>';
        }
        return '';
    }
}

if (!function_exists('product_purchase_count_24h')) {
    /**
     * Bir ürünün son 24 saat içindeki satış adedini döndür.
     * 60 saniye basit memoize cache (aynı request içinde tekrar sorulursa).
     */
    function product_purchase_count_24h(int $productId): int {
        if ($productId <= 0) return 0;
        static $cache = [];
        if (isset($cache[$productId])) return $cache[$productId];
        try {
            $st = db()->prepare(
                "SELECT COALESCE(SUM(oi.qty), 0)
                 FROM order_items oi
                 JOIN orders o ON o.id = oi.order_id
                 WHERE oi.product_id = ?
                   AND o.status IN ('paid','shipped','delivered')
                   AND o.created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)"
            );
            $st->execute([$productId]);
            $n = (int)$st->fetchColumn();
        } catch (\Throwable $e) { $n = 0; }
        return $cache[$productId] = $n;
    }
}

if (!function_exists('product_viewers_now')) {
    /**
     * Bir ürünü son 5 dakikada gören tekil oturum (kendi oturumun hariç).
     * product_views tablosu yoksa 0 döner.
     */
    function product_viewers_now(int $productId, int $windowSeconds = 300): int {
        if ($productId <= 0) return 0;
        static $cache = [];
        $key = $productId . ':' . $windowSeconds;
        if (isset($cache[$key])) return $cache[$key];
        try {
            $mySid = $_SESSION['pv_session'] ?? '';
            $st = db()->prepare(
                "SELECT COUNT(DISTINCT session_id)
                 FROM product_views
                 WHERE product_id = ?
                   AND viewed_at >= DATE_SUB(NOW(), INTERVAL ? SECOND)
                   AND (session_id IS NULL OR session_id <> ?)"
            );
            $st->execute([$productId, $windowSeconds, $mySid]);
            $n = (int)$st->fetchColumn();
        } catch (\Throwable $e) { $n = 0; }
        return $cache[$key] = $n;
    }
}

if (!function_exists('social_proof_html')) {
    /**
     * PDP veya ürün kartında gösterilecek sosyal kanıt satırı.
     * "5 kişi son 24 saatte aldı · 3 kişi şu an inceliyor" gibi.
     * Sinyal yoksa boş döner.
     */
    function social_proof_html(int $productId, int $minPurchases = 2, int $minViewers = 2): string {
        $parts = [];
        $purch = product_purchase_count_24h($productId);
        if ($purch >= $minPurchases) {
            $parts[] = '<span class="sp-buy">🔥 Son 24 saatte <strong>' . (int)$purch . '</strong> satıldı</span>';
        }
        $view = product_viewers_now($productId);
        if ($view >= $minViewers) {
            $parts[] = '<span class="sp-view">👁 Şu an <strong>' . (int)$view . '</strong> kişi inceliyor</span>';
        }
        if (!$parts) return '';
        return '<div class="social-proof" role="status" aria-live="polite">' . implode(' · ', $parts) . '</div>';
    }
}
