<?php
require_once __DIR__ . '/../core/bootstrap.php';

$items = cart_items();
if (!$items) redirect(SITE_URL . '/cart.php');

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !csrf_check($_POST['csrf'] ?? null)) {
    redirect(SITE_URL . '/checkout.php');
}
$user = current_user();
$name = trim($_POST['name']    ?? '');
$email= trim($_POST['email']   ?? '');
$phone= trim($_POST['phone']   ?? '');
$addr = trim($_POST['address'] ?? '');
$city = trim($_POST['city']    ?? '');
$note = trim($_POST['note']    ?? '');
$pay  = $_POST['pay'] ?? 'havale';

if (!$name||!$email||!$phone||!$addr||!$city) {
    flash_set('err','Lütfen tüm zorunlu alanları doldurun.');
    redirect(SITE_URL . '/checkout.php');
}
try {
    $orderId = order_create(array(
        'user_id'=>$user['id'] ?? null, 'full_name'=>$name, 'email'=>$email,
        'phone'=>$phone, 'address'=>$addr, 'city'=>$city, 'total'=>cart_total(),
        'payment_method'=>$pay, 'note'=>$note
    ), $items);
    cart_clear();
    flash_set('success','Siparişiniz alındı. Sipariş No: #'.$orderId);
    redirect(SITE_URL . '/order.php?id='.$orderId);
} catch (Exception $e) {
    flash_set('err','Sipariş oluşturulamadı: '.$e->getMessage());
    redirect(SITE_URL . '/checkout.php');
}
