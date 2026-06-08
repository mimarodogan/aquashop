<?php
/**
 * Analytics Event Helpers — GA4 Enhanced E-commerce.
 *
 * Sunucu tarafında ürün/sipariş verisini GA4'ün beklediği `item` formatına çevirir,
 * sonra `dataLayer.push({ event, ecommerce })` script tag'i basar.
 *
 * GA4 standart event'ler:
 *   - view_item_list, view_item, select_item
 *   - add_to_cart, remove_from_cart, view_cart
 *   - begin_checkout, add_shipping_info, add_payment_info
 *   - purchase, refund
 *
 * Analytics kapalıysa veya bot algılanırsa hiçbir şey basmaz.
 */

if (!function_exists('analytics_is_enabled')) {
    function analytics_is_enabled(): bool {
        if (setting('analytics_enabled','0') !== '1') return false;
        $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
        if (stripos($ua, 'Chrome-Lighthouse') !== false
         || stripos($ua, 'PageSpeed') !== false
         || stripos($ua, 'Headless') !== false) return false;
        return true;
    }
}

if (!function_exists('analytics_ecommerce_item')) {
    /**
     * Bir ürün satırını GA4 item formatına çevir.
     * $row: products tablo satırı (en azından id, name, price, sku, category_id, cat_name?)
     */
    function analytics_ecommerce_item(array $row, int $qty = 1, ?array $variant = null, $index = null): array {
        $price = (float)($variant['price'] ?? $row['price'] ?? 0);
        $item = [
            'item_id'    => (string)($variant['sku'] ?? $row['sku'] ?? ('p_' . ($row['id'] ?? 0))),
            'item_name'  => (string)($row['name'] ?? ''),
            'price'      => round($price, 2),
            'quantity'   => max(1, (int)$qty),
            'currency'   => 'TRY',
        ];
        if (!empty($row['cat_name']))           $item['item_category'] = (string)$row['cat_name'];
        if (!empty($row['brand']))              $item['item_brand']    = (string)$row['brand'];
        if (!empty($variant['label']))          $item['item_variant']  = (string)$variant['label'];
        if ($index !== null)                    $item['index']         = is_numeric($index) ? (int)$index : 0;
        return $item;
    }
}

if (!function_exists('analytics_event')) {
    /**
     * dataLayer'a event push eden inline script bas.
     * $eventName: GA4 standart event adı (snake_case)
     * $params: ecommerce payload (items, value, currency, transaction_id, ...)
     */
    function analytics_event(string $eventName, array $params = []): void {
        if (!analytics_is_enabled()) return;
        // Önceki ecommerce objesini temizle (GA4 önerisi)
        $payload = [
            'event'      => $eventName,
            'ecommerce'  => $params,
        ];
        $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($json === false) return;
        echo "<script>window.dataLayer=window.dataLayer||[];"
           . "window.dataLayer.push({ecommerce:null});"
           . "window.dataLayer.push({$json});</script>\n";
    }
}
