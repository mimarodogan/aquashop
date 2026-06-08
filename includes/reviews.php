<?php
/**
 * Ürün yorumları + 5 yıldız puanlama yardımcısı.
 */
require_once __DIR__ . '/functions.php';

if (!function_exists('reviews_summary')) {
function reviews_summary(int $productId): array {
    $st = db()->prepare('SELECT COUNT(*) as c, COALESCE(AVG(rating),0) as avg FROM product_reviews WHERE product_id=? AND is_approved=1');
    $st->execute([$productId]);
    $r = $st->fetch();
    return ['count'=>(int)$r['c'], 'avg'=>round((float)$r['avg'], 1)];
}}

if (!function_exists('reviews_list')) {
function reviews_list(int $productId, int $limit = 20): array {
    $st = db()->prepare('SELECT * FROM product_reviews WHERE product_id=? AND is_approved=1 ORDER BY created_at DESC LIMIT ?');
    $st->bindValue(1, $productId, \PDO::PARAM_INT);
    $st->bindValue(2, $limit, \PDO::PARAM_INT);
    $st->execute();
    return $st->fetchAll();
}}

/**
 * Kullanıcı bu ürünü daha önce ödeyerek aldı mı? (Verified Buyer rozeti için)
 */
if (!function_exists('reviews_verified_buyer')) {
function reviews_verified_buyer(?int $userId, ?string $email, int $productId): bool {
    if (!$userId && !$email) return false;
    $where = []; $args = [];
    if ($userId) { $where[] = 'o.user_id=?'; $args[] = $userId; }
    if ($email)  { $where[] = 'o.email=?';   $args[] = $email;  }
    $w = '(' . implode(' OR ', $where) . ')';
    $args[] = $productId;
    $sql = "SELECT 1 FROM orders o JOIN order_items i ON i.order_id=o.id
            WHERE $w AND i.product_id=? AND o.payment_status='paid' LIMIT 1";
    $st = db()->prepare($sql);
    $st->execute($args);
    return (bool)$st->fetchColumn();
}}

if (!function_exists('star_html')) {
function star_html(float $rating, int $size = 14): string {
    $full = floor($rating);
    $half = ($rating - $full) >= 0.5 ? 1 : 0;
    $empty = 5 - $full - $half;
    $out = '<span class="stars" style="display:inline-flex;gap:1px;color:#E0A800;font-size:'.$size.'px;line-height:1">';
    for ($i = 0; $i < $full; $i++) $out .= '★';
    if ($half) $out .= '<span style="position:relative;display:inline-block;width:'.$size.'px"><span style="color:#E5E5E5">★</span><span style="position:absolute;left:0;top:0;color:#E0A800;width:50%;overflow:hidden">★</span></span>';
    for ($i = 0; $i < $empty; $i++) $out .= '<span style="color:#E5E5E5">★</span>';
    return $out . '</span>';
}}
