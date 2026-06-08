<?php
/**
 * Saved Items Controller — "Sonra Al" listesi
 *
 * Action'lar:
 *   save_from_cart  → cart_key'i sepetten kaldır, listeye ekle
 *   move_to_cart    → listeden çıkar, sepete ekle
 *   remove          → listeden çıkar
 *
 * Sadece giriş yapmış kullanıcılar için.
 */
require_once __DIR__ . '/../core/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !csrf_check($_POST['csrf'] ?? null)) {
    flash_set('err','Geçersiz istek.');
    redirect(SITE_URL . '/');
}
if (!saved_items_enabled()) {
    flash_set('err','Bu özellik şu an aktif değil.');
    redirect(url('cart'));
}
$user = current_user();
if (!$user) {
    flash_set('err','"Sonra al" listesini kullanmak için giriş yapmalısınız.');
    redirect(url('login'));
}

$uid    = (int)$user['id'];
$action = $_POST['action'] ?? '';

if ($action === 'save_from_cart') {
    $cartKey = $_POST['cart_key'] ?? '';
    $items   = cart_items();
    if (!isset($items[$cartKey])) {
        flash_set('err','Ürün sepette bulunamadı.');
        redirect(url('cart'));
    }
    $it = $items[$cartKey];
    try {
        $st = db()->prepare(
            'INSERT INTO user_saved_items (user_id, product_id, variant_id, qty)
             VALUES (?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE qty = VALUES(qty), saved_at = NOW()'
        );
        // variant_id PK'nın parçası → NULL olamaz; varyasyonsuz ürün için 0 kullanılır
        $st->execute([$uid, (int)$it['id'], !empty($it['variant_id']) ? (int)$it['variant_id'] : 0, max(1, (int)$it['qty'])]);
        cart_remove($cartKey);
        cart_persist(); // Sepet boşaldıysa terk edilmiş sepet kaydını sil/güncelle
        flash_set('success','Ürün "Sonra al" listenize taşındı.');
    } catch (\Throwable $e) {
        flash_set('err','Kayıt başarısız: ' . $e->getMessage());
    }
    redirect(url('cart'));
}

if ($action === 'move_to_cart') {
    $pid = (int)($_POST['product_id'] ?? 0);
    $vid = !empty($_POST['variant_id']) ? (int)$_POST['variant_id'] : 0; // varyasyonsuz = 0 (PK NULL kabul etmez)
    try {
        $st = db()->prepare('SELECT qty FROM user_saved_items WHERE user_id = ? AND product_id = ? AND (variant_id <=> ?)');
        $st->execute([$uid, $pid, $vid]);
        $qty = (int)($st->fetchColumn() ?: 1);
        if ($qty < 1) $qty = 1;
        cart_add($pid, $qty, $vid);
        cart_persist();
        db()->prepare('DELETE FROM user_saved_items WHERE user_id = ? AND product_id = ? AND (variant_id <=> ?)')
            ->execute([$uid, $pid, $vid]);
        flash_set('success','Ürün sepete taşındı.');
    } catch (\Throwable $e) {
        flash_set('err','İşlem başarısız.');
    }
    redirect(url('account'));
}

if ($action === 'remove') {
    $pid = (int)($_POST['product_id'] ?? 0);
    $vid = !empty($_POST['variant_id']) ? (int)$_POST['variant_id'] : 0; // varyasyonsuz = 0 (PK NULL kabul etmez)
    db()->prepare('DELETE FROM user_saved_items WHERE user_id = ? AND product_id = ? AND (variant_id <=> ?)')
        ->execute([$uid, $pid, $vid]);
    flash_set('success','Listeden çıkarıldı.');
    redirect(url('account'));
}

redirect(url('cart'));
