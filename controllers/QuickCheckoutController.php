<?php
/**
 * Hemen Satın Al — sepete tek ürün ekler ve doğrudan ödeme sayfasına yönlendirir.
 * URL: /satin-al  (form POST)
 */
require_once __DIR__ . '/../core/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !csrf_check($_POST['csrf'] ?? null)) {
    redirect(SITE_URL . '/');
}
$pid = (int)($_POST['id'] ?? 0);
$qty = max(1, (int)($_POST['qty'] ?? 1));
if ($pid <= 0) redirect(SITE_URL . '/');

// Mevcut sepeti yedekle — ödeme başarısız olursa payment_callback'te geri yüklenir
if (!empty($_SESSION['cart'])) {
    $_SESSION['pre_quick_cart'] = $_SESSION['cart'];
}
// Sepeti temizleyip sadece bu ürünü koy (saf "şimdi satın al" akışı)
cart_clear();
cart_add($pid, $qty);

// Kart ödemesi zorunlu — checkout sayfasında flag kullanılacak
$_SESSION['quick_checkout'] = true;
redirect(url('checkout'));
