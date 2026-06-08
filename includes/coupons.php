<?php
/**
 * Kupon kod motoru.
 * Sepete uygulanır, indirim hesaplar, geçerlilik kontrol eder.
 */
require_once __DIR__ . '/functions.php';

if (!function_exists('coupon_find')) {
function coupon_find(string $code): ?array {
    $code = strtoupper(trim($code));
    if ($code === '') return null;
    $st = db()->prepare('SELECT * FROM coupons WHERE UPPER(code)=? LIMIT 1');
    $st->execute([$code]);
    $r = $st->fetch();
    return $r ?: null;
}}

/**
 * Kuponu sepet bağlamında validate eder. Geçerliyse
 * ['ok'=>true,'discount'=>X,'free_shipping'=>bool,'coupon'=>row] döner.
 */
if (!function_exists('coupon_validate')) {
function coupon_validate(string $code, float $cartSubtotal, ?int $userId = null): array {
    $c = coupon_find($code);
    if (!$c) return ['ok'=>false, 'error'=>'Kupon bulunamadı.'];
    if (!$c['enabled']) return ['ok'=>false, 'error'=>'Bu kupon aktif değil.'];

    if ($c['starts_at'] && strtotime($c['starts_at']) > time())
        return ['ok'=>false, 'error'=>'Kupon henüz başlamadı.'];
    if ($c['ends_at'] && strtotime($c['ends_at']) < time())
        return ['ok'=>false, 'error'=>'Kuponun süresi doldu.'];

    if ((float)$c['min_cart'] > 0 && $cartSubtotal < (float)$c['min_cart'])
        return ['ok'=>false, 'error'=>'Minimum sepet tutarı: ' . money($c['min_cart'])];

    if ($c['usage_limit'] !== null && (int)$c['usage_count'] >= (int)$c['usage_limit'])
        return ['ok'=>false, 'error'=>'Bu kupon tükendi.'];

    if ($userId && (int)$c['per_user_limit'] > 0) {
        $u = db()->prepare('SELECT COUNT(*) FROM coupon_redemptions WHERE coupon_id=? AND user_id=?');
        $u->execute([(int)$c['id'], $userId]);
        if ((int)$u->fetchColumn() >= (int)$c['per_user_limit'])
            return ['ok'=>false, 'error'=>'Bu kuponu zaten kullandınız.'];
    }

    // İndirim hesabı
    $discount = 0.0; $freeShipping = false;
    switch ($c['type']) {
        case 'percent':
            $discount = round($cartSubtotal * (float)$c['amount'] / 100, 2);
            if ($c['max_discount'] !== null && $discount > (float)$c['max_discount'])
                $discount = (float)$c['max_discount'];
            break;
        case 'fixed':
            $discount = min($cartSubtotal, (float)$c['amount']);
            break;
        case 'free_shipping':
            $freeShipping = true; break;
    }
    return ['ok'=>true, 'discount'=>$discount, 'free_shipping'=>$freeShipping, 'coupon'=>$c];
}}

/**
 * Kupon kullanımını kaydet (sipariş başarılı olduktan sonra çağrılır).
 */
if (!function_exists('coupon_redeem')) {
function coupon_redeem(int $couponId, int $orderId, float $amount, ?int $userId = null): void {
    db()->prepare('INSERT INTO coupon_redemptions (coupon_id, user_id, order_id, amount) VALUES (?,?,?,?)')
        ->execute([$couponId, $userId, $orderId, $amount]);
    db()->prepare('UPDATE coupons SET usage_count = usage_count + 1 WHERE id=?')->execute([$couponId]);
}}
