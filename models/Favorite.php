<?php
function fav_has($productId) {
    $u = current_user(); if (!$u) return false;
    $st = db()->prepare('SELECT 1 FROM favorites WHERE user_id=? AND product_id=?');
    $st->execute(array($u['id'], (int)$productId));
    return (bool)$st->fetch();
}
function fav_toggle($productId) {
    $u = current_user(); if (!$u) return false;
    if (fav_has($productId)) {
        db()->prepare('DELETE FROM favorites WHERE user_id=? AND product_id=?')->execute(array($u['id'], (int)$productId));
        return false;
    }
    // price_at_fav: favori eklenirken mevcut fiyatı kaydet (indirim alarmı için)
    try {
        $pSt = db()->prepare('SELECT price FROM products WHERE id=? LIMIT 1');
        $pSt->execute([(int)$productId]);
        $priceNow = (float)($pSt->fetchColumn() ?: 0);
    } catch (Exception $e) { $priceNow = 0; }
    db()->prepare('INSERT IGNORE INTO favorites (user_id,product_id,price_at_fav) VALUES (?,?,?)')
        ->execute(array($u['id'], (int)$productId, $priceNow ?: null));
    return true;
}
function fav_product_count(int $pid): int {
    try {
        $st = db()->prepare('SELECT COUNT(*) FROM favorites WHERE product_id=?');
        $st->execute([$pid]);
        return (int)$st->fetchColumn();
    } catch (Exception $e) { return 0; }
}
function fav_count() {
    $u = current_user(); if (!$u) return 0;
    $st = db()->prepare('SELECT COUNT(*) FROM favorites WHERE user_id=?');
    $st->execute(array($u['id']));
    return (int)$st->fetchColumn();
}
function fav_ids() {
    $u = current_user(); if (!$u) return array();
    static $cache = null;
    if ($cache === null) {
        $st = db()->prepare('SELECT product_id FROM favorites WHERE user_id=?');
        $st->execute(array($u['id']));
        $cache = array_map('intval', array_column($st->fetchAll(), 'product_id'));
    }
    return $cache;
}
