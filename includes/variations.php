<?php
/**
 * Ürün varyasyon yardımcıları.
 * Sade model: bir ürünün altında çoklu (label + price + stock + opsiyonel görsel) varyasyonu olabilir.
 * Varyasyonlu ürünlerde products.price/stock saklanmaya devam eder ama gösterimde varyasyondan
 * gelen fiyat/stok kullanılır.
 */
require_once __DIR__ . '/functions.php';

if (!function_exists('product_variations')) {
function product_variations(int $productId, bool $onlyActive = true): array {
    $sql = 'SELECT * FROM product_variations WHERE product_id=?' . ($onlyActive ? ' AND is_active=1' : '') . ' ORDER BY sort_order ASC, id ASC';
    $st = db()->prepare($sql);
    $st->execute([$productId]);
    return $st->fetchAll();
}}

if (!function_exists('variation_get')) {
function variation_get(int $variationId): ?array {
    $st = db()->prepare('SELECT * FROM product_variations WHERE id=?');
    $st->execute([$variationId]);
    $r = $st->fetch();
    return $r ?: null;
}}

if (!function_exists('product_has_variations')) {
function product_has_variations(int $productId): bool {
    static $cache = [];
    if (array_key_exists($productId, $cache)) return $cache[$productId];
    try {
        $st = db()->prepare('SELECT has_variations FROM products WHERE id=?');
        $st->execute([$productId]);
        $cache[$productId] = (bool)$st->fetchColumn();
    } catch (\Throwable $e) { $cache[$productId] = false; }
    return $cache[$productId];
}}

/**
 * Varyasyonlu bir ürünün fiyat aralığını döndürür: [min, max] (eşitse min=max).
 * Liste sayfalarında "29₺ – 89₺" gösterimi için.
 */
if (!function_exists('product_price_range')) {
function product_price_range(int $productId): array {
    $st = db()->prepare('SELECT MIN(price) mn, MAX(price) mx FROM product_variations WHERE product_id=? AND is_active=1');
    $st->execute([$productId]);
    $r = $st->fetch();
    return [(float)($r['mn'] ?? 0), (float)($r['mx'] ?? 0)];
}}

/**
 * Toplam stok — varyasyonların stokları toplamı.
 */
if (!function_exists('product_total_stock')) {
function product_total_stock(int $productId): int {
    $st = db()->prepare('SELECT COALESCE(SUM(stock),0) FROM product_variations WHERE product_id=? AND is_active=1');
    $st->execute([$productId]);
    return (int)$st->fetchColumn();
}}
