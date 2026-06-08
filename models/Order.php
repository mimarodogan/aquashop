<?php
function order_create($data, $items) {
    $pdo = db(); $pdo->beginTransaction();
    try {
        $st = $pdo->prepare("INSERT INTO orders (user_id,full_name,email,phone,address,city,total,status,payment_method,note) VALUES (?,?,?,?,?,?,?, 'pending', ?, ?)");
        $st->execute(array(
            $data['user_id'] ?? null, $data['full_name'], $data['email'], $data['phone'],
            $data['address'], $data['city'], $data['total'], $data['payment_method'], $data['note'] ?? null
        ));
        $orderId = (int)$pdo->lastInsertId();
        $sti = $pdo->prepare("INSERT INTO order_items (order_id,product_id,product_name,qty,price) VALUES (?,?,?,?,?)");
        foreach ($items as $it) $sti->execute(array($orderId, $it['id'], $it['name'], $it['qty'], $it['price']));
        $pdo->commit();
        return $orderId;
    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }
}
function order_find($id) {
    $st = db()->prepare("SELECT * FROM orders WHERE id=?");
    $st->execute(array((int)$id));
    return $st->fetch();
}
function order_items($orderId) {
    $st = db()->prepare("SELECT * FROM order_items WHERE order_id=?");
    $st->execute(array((int)$orderId));
    return $st->fetchAll();
}
function order_for_user($userId, $email) {
    $st = db()->prepare("SELECT * FROM orders WHERE user_id=? OR email=? ORDER BY created_at DESC");
    $st->execute(array((int)$userId, $email));
    return $st->fetchAll();
}
function order_update_status($id, $status) {
    db()->prepare("UPDATE orders SET status=? WHERE id=?")->execute(array($status, (int)$id));
}
function order_cancel($id, $reason, $byUserId = null) {
    $st = db()->prepare("UPDATE orders SET status='cancelled', cancellation_reason=?, cancelled_at=NOW(), cancelled_by=? WHERE id=?");
    $st->execute(array($reason, $byUserId, (int)$id));
}

function order_set_tracking($id, $carrier, $number) {
    $sets = array('tracking_carrier=?','tracking_number=?'); $args = array($carrier ?: null, $number ?: null);
    if ($number) { $sets[] = "status='shipped'"; $sets[] = 'shipped_at=NOW()'; }
    db()->prepare('UPDATE orders SET '.implode(', ', $sets).' WHERE id=?')->execute(array_merge($args, array((int)$id)));
}
