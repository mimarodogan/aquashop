<?php
/**
 * Cross-Sell — "Sıkça birlikte alınanlar".
 *
 * Sepet sayfası veya ürün detayda gösterilecek önerileri üretir.
 * Yaklaşım:
 *   1) Sepetteki product_id'lerle BİRLİKTE en sık satın alınan ürünleri bul (collaborative)
 *   2) Yeterli birlikte-satım verisi yoksa: aynı kategoriden öne çıkanlara düş
 *   3) Sepette zaten olan ürünler hariç tutulur
 *
 * Sonuç: en fazla $limit ürün, products tablosundan tam satır.
 */

if (!function_exists('cross_sell_for_cart')) {
    function cross_sell_for_cart(array $cartItems, int $limit = 3): array {
        $excludeIds = [];
        foreach ($cartItems as $it) {
            $pid = (int)($it['product_id'] ?? $it['id'] ?? 0);
            if ($pid > 0) $excludeIds[] = $pid;
        }
        $excludeIds = array_values(array_unique($excludeIds));
        if (!$excludeIds) return [];

        // 1) Birlikte alınanlar (collaborative)
        try {
            $in = implode(',', array_fill(0, count($excludeIds), '?'));
            $sql = "SELECT oi2.product_id,
                           SUM(oi2.qty)             AS together_qty,
                           COUNT(DISTINCT oi2.order_id) AS together_orders
                    FROM order_items oi1
                    JOIN order_items oi2
                      ON oi2.order_id = oi1.order_id
                     AND oi2.product_id NOT IN ($in)
                    JOIN orders o ON o.id = oi1.order_id
                    WHERE oi1.product_id IN ($in)
                      AND o.status IN ('paid','shipped','delivered')
                      AND o.created_at >= DATE_SUB(NOW(), INTERVAL 180 DAY)
                    GROUP BY oi2.product_id
                    ORDER BY together_orders DESC, together_qty DESC
                    LIMIT $limit";
            $args = array_merge($excludeIds, $excludeIds);
            $st = db()->prepare($sql);
            $st->execute($args);
            $togetherIds = array_column($st->fetchAll(), 'product_id');
        } catch (\Throwable $e) { $togetherIds = []; }

        $candidateIds = $togetherIds;

        // 2) Yetmediyse aynı kategoriden tamamla
        if (count($candidateIds) < $limit) {
            $need = $limit - count($candidateIds);
            try {
                $exclude2 = array_merge($excludeIds, $candidateIds);
                $in2 = implode(',', array_fill(0, count($exclude2), '?'));
                // Sepetteki ürünlerin kategorilerini bul
                $catSt = db()->prepare("SELECT DISTINCT category_id FROM products WHERE id IN ($in)");
                $catSt->execute($excludeIds);
                $cats = array_filter(array_column($catSt->fetchAll(), 'category_id'));
                if ($cats) {
                    $inCat = implode(',', array_fill(0, count($cats), '?'));
                    $sql2 = "SELECT id FROM products
                             WHERE is_active = 1
                               AND category_id IN ($inCat)
                               AND id NOT IN ($in2)
                               AND (stock > 0 OR has_variations = 1)
                             ORDER BY RAND()
                             LIMIT $need";
                    $st2 = db()->prepare($sql2);
                    $st2->execute(array_merge($cats, $exclude2));
                    $candidateIds = array_merge($candidateIds, array_column($st2->fetchAll(), 'id'));
                }
            } catch (\Throwable $e) {}
        }

        if (!$candidateIds) return [];

        // 3) Ürün satırlarını çek
        $candidateIds = array_slice(array_unique($candidateIds), 0, $limit);
        $in3 = implode(',', array_fill(0, count($candidateIds), '?'));
        try {
            $st3 = db()->prepare(
                "SELECT p.*, c.name AS cat_name
                 FROM products p
                 LEFT JOIN categories c ON c.id = p.category_id
                 WHERE p.id IN ($in3) AND p.is_active = 1"
            );
            $st3->execute($candidateIds);
            $rows = $st3->fetchAll();
        } catch (\Throwable $e) { return []; }

        // Orijinal sıralamayı koru
        usort($rows, function ($a, $b) use ($candidateIds) {
            return array_search($a['id'], $candidateIds) <=> array_search($b['id'], $candidateIds);
        });
        return $rows;
    }
}
