<?php
/**
 * Fiyatlandırma yardımcıları — KDV, kargo, sepet toplamları.
 */
require_once __DIR__ . '/functions.php';

if (!function_exists('vat_rate')) {
function vat_rate(): float {
    return (float)setting('vat_rate', '20');
}}

if (!function_exists('vat_display_mode')) {
function vat_display_mode(): string {
    $m = setting('vat_display', 'included');
    return $m === 'excluded' ? 'excluded' : 'included';
}}

if (!function_exists('vat_label')) {
function vat_label(): string {
    return vat_display_mode() === 'included' ? 'KDV dahil' : 'KDV hariç';
}}

/**
 * Verilen toplamdan KDV'yi ayrıştır.
 * - 'included' modunda: total → subtotal + vat (geri ayrıştır)
 * - 'excluded' modunda: total = subtotal → vat ekle
 * Döndürür: ['subtotal'=>..., 'vat'=>..., 'total'=>...]
 */
if (!function_exists('vat_breakdown')) {
function vat_breakdown(float $amount): array {
    $rate = vat_rate();
    if ($rate <= 0) return ['subtotal'=>$amount, 'vat'=>0.0, 'total'=>$amount];
    if (vat_display_mode() === 'included') {
        $sub = round($amount / (1 + $rate/100), 2);
        $vat = round($amount - $sub, 2);
        return ['subtotal'=>$sub, 'vat'=>$vat, 'total'=>$amount];
    }
    // excluded
    $vat = round($amount * $rate / 100, 2);
    return ['subtotal'=>$amount, 'vat'=>$vat, 'total'=>round($amount + $vat, 2)];
}}

if (!function_exists('shipping_cost')) {
function shipping_cost(float $cartSubtotal): float {
    $flat = (float)setting('shipping_flat', '0');
    $free = (float)setting('shipping_free_threshold', '0');
    if ($flat <= 0) return 0.0;
    if ($free > 0 && $cartSubtotal >= $free) return 0.0;
    return $flat;
}}

if (!function_exists('cart_pricing')) {
function cart_pricing(): array {
    $items = function_exists('cart_items') ? cart_items() : [];
    $itemsTotal = 0.0;
    foreach ($items as $it) $itemsTotal += (float)$it['price'] * (int)$it['qty'];

    $vat = vat_breakdown($itemsTotal);
    $ship = shipping_cost(vat_display_mode()==='included' ? $vat['total'] : $vat['subtotal']);

    // Kupon (session'da tutuluyor)
    $discount = 0.0; $couponCode = null; $couponId = null; $freeShipping = false;
    if (!empty($_SESSION['cart_coupon'])) {
        require_once __DIR__ . '/coupons.php';
        $u = function_exists('current_user') ? current_user() : null;
        $check = coupon_validate($_SESSION['cart_coupon'], $vat['total'], $u['id'] ?? null);
        if ($check['ok']) {
            $discount = (float)$check['discount'];
            $couponCode = $check['coupon']['code'];
            $couponId = (int)$check['coupon']['id'];
            $freeShipping = !empty($check['free_shipping']);
            if ($freeShipping) $ship = 0.0;
        } else {
            // Geçersiz olduysa session'dan temizle
            unset($_SESSION['cart_coupon']);
        }
    }

    // Sadakat puanı kullanımı — session'da kullanılmak istenen puan adedi tutulur.
    // Kupon + kargo sonrası ödenecek tutarı AŞAMAZ; min. kullanım eşiğine uyulur.
    $loyaltyPoints = 0; $loyaltyValue = 0.0; $loyaltyBalance = 0;
    if (!empty($_SESSION['cart_points'])) {
        require_once __DIR__ . '/../models/Loyalty.php';
        $lu = function_exists('current_user') ? current_user() : null;
        if ($lu && function_exists('loyalty_enabled') && loyalty_enabled()) {
            $loyaltyBalance = loyalty_balance((int)$lu['id']);
            $want = min((int)$_SESSION['cart_points'], $loyaltyBalance);
            $preLoyalty = max(0, $vat['total'] - $discount + $ship);
            $val = loyalty_value_of($want);
            if ($val > $preLoyalty) {                       // tutarı aşıyorsa sığacak kadarına indir
                $rate = loyalty_redeem_rate();
                $want = $rate > 0 ? (int)floor($preLoyalty / $rate) : 0;
                $val  = loyalty_value_of($want);
            }
            if ($want >= loyalty_min_redeem() && $val > 0) {
                $loyaltyPoints = $want;
                $loyaltyValue  = $val;
            }
        }
    }

    $grand = max(0, $vat['total'] - $discount + $ship - $loyaltyValue);

    // ── KDV'yi NİHAİ (indirimli) tutar üzerinden yeniden hesapla ────────────────
    // Kupon + puan indirimi KDV matrahını düşürür. $grand zaten KDV dahil ödenecek
    // tutar olduğundan içindeki KDV'yi geri ayrıştırırız (display modundan bağımsız —
    // müşteri her hâlükârda KDV dahil tutarı öder). Aksi halde sepet özetinde
    // "Toplam" ile döküm tutmaz ve fatura/rapor indirimsiz (yanlış) KDV kaydeder.
    $rate     = vat_rate();
    $vatFinal = $rate > 0 ? round($grand * $rate / (100 + $rate), 2) : 0.0;
    $netFinal = round($grand - $vatFinal, 2);

    return [
        'subtotal'        => $netFinal,        // indirimler sonrası KDV hariç net (fatura/rapor)
        'vat'             => $vatFinal,         // indirimler sonrası gerçek KDV (matrah düşürülmüş)
        'vat_rate'        => $rate,
        'items_total'     => $vat['total'],     // KDV dahil ürün toplamı (indirimsiz liste fiyatı)
        'shipping'        => $ship,
        'shipping_free_at'=> (float)setting('shipping_free_threshold','0'),
        'discount'        => $discount,
        'coupon_code'     => $couponCode,
        'coupon_id'       => $couponId,
        'free_shipping'   => $freeShipping,
        'loyalty_points'  => $loyaltyPoints,
        'loyalty_value'   => $loyaltyValue,
        'loyalty_balance' => $loyaltyBalance,
        'grand_total'     => round($grand, 2),
    ];
}}
