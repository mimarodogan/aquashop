<?php
/**
 * Feature Helpers — Faz 6/7 özellikleri için ortak fonksiyonlar.
 *
 * Tüm fonksiyonlar settings'teki on/off bayrağına bakar; özellik kapalıysa
 * boş dizi / null döner. Bu sayede storefront sayfaları "açık mı?" kontrolü
 * yapmadan çağırabilir, hiçbir şey görünmez veya kart çıkmaz.
 */

/* ── Çok satanlar — anasayfa ───────────────────────────────────── */
if (!function_exists('bestsellers_get')) {
    function bestsellers_get(int $limit = 8, int $days = 30): array {
        if (setting('bestsellers_enabled','0') !== '1') return [];
        try {
            $st = db()->prepare(
                "SELECT p.*, c.name AS cat_name,
                        SUM(oi.qty) AS sold_qty
                 FROM order_items oi
                 JOIN orders o    ON o.id = oi.order_id
                 JOIN products p  ON p.id = oi.product_id
                 LEFT JOIN categories c ON c.id = p.category_id
                 WHERE p.is_active = 1 AND p.deleted_at IS NULL
                   AND o.status IN ('paid','shipped','delivered')
                   AND o.created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
                 GROUP BY p.id
                 ORDER BY sold_qty DESC
                 LIMIT $limit"
            );
            $st->execute([$days]);
            return $st->fetchAll();
        } catch (\Throwable $e) { return []; }
    }
}

/* ── Son baktıkların — PDP / anasayfa ──────────────────────────── */
if (!function_exists('recently_viewed_get')) {
    function recently_viewed_get(int $limit = 10, ?int $excludeProductId = null): array {
        if (setting('recently_viewed_enabled','0') !== '1') return [];
        $sid = $_SESSION['pv_session'] ?? '';
        if (!$sid) return [];
        try {
            $exclude = $excludeProductId ? ' AND pv.product_id <> ' . (int)$excludeProductId : '';
            // Son baktığın ürünler, tekilleştir (her ürünün en son görüntülemesi)
            $st = db()->prepare(
                "SELECT p.*, c.name AS cat_name, MAX(pv.viewed_at) AS last_view
                 FROM product_views pv
                 JOIN products p ON p.id = pv.product_id
                 LEFT JOIN categories c ON c.id = p.category_id
                 WHERE pv.session_id = ?
                   AND p.is_active = 1 AND p.deleted_at IS NULL
                   $exclude
                 GROUP BY p.id
                 ORDER BY last_view DESC
                 LIMIT $limit"
            );
            $st->execute([$sid]);
            return $st->fetchAll();
        } catch (\Throwable $e) { return []; }
    }
}

/* ── Ürün karşılaştırma — session bazlı liste ──────────────────── */
if (!function_exists('compare_enabled')) {
    function compare_enabled(): bool {
        return setting('compare_enabled','0') === '1';
    }
}
if (!function_exists('compare_list')) {
    function compare_list(): array {
        return array_values(array_unique(array_map('intval', $_SESSION['compare_ids'] ?? [])));
    }
}
if (!function_exists('compare_add')) {
    function compare_add(int $productId): bool {
        if (!compare_enabled() || $productId <= 0) return false;
        $list = compare_list();
        if (in_array($productId, $list, true)) return true;
        if (count($list) >= 3) return false; // max 3 ürün
        $list[] = $productId;
        $_SESSION['compare_ids'] = $list;
        return true;
    }
}
if (!function_exists('compare_remove')) {
    function compare_remove(int $productId): void {
        $list = compare_list();
        $_SESSION['compare_ids'] = array_values(array_filter($list, fn($id) => $id !== $productId));
    }
}
if (!function_exists('compare_clear')) {
    function compare_clear(): void { unset($_SESSION['compare_ids']); }
}
if (!function_exists('compare_has')) {
    function compare_has(int $productId): bool {
        return in_array($productId, compare_list(), true);
    }
}

/* ── "Sonra al" listesi ────────────────────────────────────────── */
if (!function_exists('saved_items_enabled')) {
    function saved_items_enabled(): bool {
        return setting('saved_items_enabled','0') === '1';
    }
}
if (!function_exists('saved_items_count')) {
    function saved_items_count(int $userId): int {
        if (!saved_items_enabled() || $userId <= 0) return 0;
        try {
            $st = db()->prepare('SELECT COUNT(*) FROM user_saved_items WHERE user_id = ?');
            $st->execute([$userId]);
            return (int)$st->fetchColumn();
        } catch (\Throwable $e) { return 0; }
    }
}
if (!function_exists('saved_items_list')) {
    function saved_items_list(int $userId): array {
        if (!saved_items_enabled() || $userId <= 0) return [];
        try {
            $st = db()->prepare(
                "SELECT si.*, p.name, p.slug, p.image, p.price, p.stock, p.is_active, p.deleted_at
                 FROM user_saved_items si
                 JOIN products p ON p.id = si.product_id
                 WHERE si.user_id = ? AND p.deleted_at IS NULL
                 ORDER BY si.saved_at DESC"
            );
            $st->execute([$userId]);
            return $st->fetchAll();
        } catch (\Throwable $e) { return []; }
    }
}

/* ── Q&A — onaylı sorular ──────────────────────────────────────── */
if (!function_exists('qna_enabled')) {
    function qna_enabled(): bool {
        return setting('qna_enabled','0') === '1';
    }
}
if (!function_exists('qna_for_product')) {
    function qna_for_product(int $productId, int $limit = 10): array {
        if (!qna_enabled() || $productId <= 0) return [];
        try {
            $st = db()->prepare(
                "SELECT * FROM product_questions
                 WHERE product_id = ? AND is_approved = 1
                 ORDER BY upvotes DESC, created_at DESC
                 LIMIT $limit"
            );
            $st->execute([$productId]);
            return $st->fetchAll();
        } catch (\Throwable $e) { return []; }
    }
}

/* ── Stok rezervasyonu — kaç adet "bekliyor" ───────────────────── */
if (!function_exists('reservation_enabled')) {
    function reservation_enabled(): bool {
        return setting('reservation_enabled','0') === '1';
    }
}
if (!function_exists('reserved_qty_for_product')) {
    /** Bir ürün için şu an aktif rezervasyon sayısı (kendi session'ın hariç) */
    function reserved_qty_for_product(int $productId): int {
        if (!reservation_enabled() || $productId <= 0) return 0;
        $mySid = $_SESSION['pv_session'] ?? '';
        try {
            $st = db()->prepare(
                "SELECT COALESCE(SUM(qty), 0)
                 FROM cart_reservations
                 WHERE product_id = ?
                   AND expires_at > NOW()
                   AND (session_id IS NULL OR session_id <> ?)"
            );
            $st->execute([$productId, $mySid]);
            return (int)$st->fetchColumn();
        } catch (\Throwable $e) { return 0; }
    }
}
if (!function_exists('reservation_add')) {
    /** Sepete eklenince çağrılır — bu session için rezervasyon kaydı oluşturur/yeniler */
    function reservation_add(int $productId, ?int $variantId, int $qty): void {
        if (!reservation_enabled() || $productId <= 0 || $qty <= 0) return;
        $minutes = max(1, (int)setting('reservation_minutes','15'));
        $sid = $_SESSION['pv_session'] ?? '';
        if (!$sid) return;
        $userId = null;
        if (function_exists('current_user')) {
            $u = current_user();
            $userId = $u ? (int)$u['id'] : null;
        }
        try {
            // Aynı session+ürün+varyant için varsa süre uzat
            $del = db()->prepare(
                'DELETE FROM cart_reservations WHERE session_id = ? AND product_id = ? AND (variant_id <=> ?)'
            );
            $del->execute([$sid, $productId, $variantId]);
            $ins = db()->prepare(
                'INSERT INTO cart_reservations (session_id, user_id, product_id, variant_id, qty, expires_at)
                 VALUES (?, ?, ?, ?, ?, DATE_ADD(NOW(), INTERVAL ? MINUTE))'
            );
            $ins->execute([$sid, $userId, $productId, $variantId, $qty, $minutes]);
        } catch (\Throwable $e) {
            error_log('[reservation_add] ' . $e->getMessage());
        }
    }
}
