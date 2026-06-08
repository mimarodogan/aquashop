<?php
/**
 * Sepet — varyasyon (variation) destekli.
 * Sepet item key formatı: "productId:variantId" ("variantId" yoksa "0").
 * Eski integer key'ler de okunabilir kalır (geri uyum).
 */

function cart_items() {
    return isset($_SESSION['cart']) ? $_SESSION['cart'] : array();
}
function cart_count() {
    $n = 0; foreach (cart_items() as $it) $n += (int)$it['qty']; return $n;
}

function _cart_key($productId, $variantId = null) {
    return (int)$productId . ':' . (int)($variantId ?: 0);
}

function cart_add($productId, $qty = 1, $variantId = null) {
    $st = db()->prepare('SELECT id,name,price,stock,image,has_variations,price_on_request FROM products WHERE id=? AND is_active=1');
    $st->execute(array((int)$productId));
    $p = $st->fetch();
    if (!$p) return;
    // "İletişime Geçin" / online satışa kapalı ürün — UI gizlense de doğrudan POST ile eklenemesin
    if (!empty($p['price_on_request'])) return;

    // Varyasyon varsa, variantId zorunlu — yoksa varyasyon olmamalı
    $variant = null;
    if (!empty($p['has_variations'])) {
        if (!$variantId) return; // varyasyon seçilmemiş, sepete ekleme
        $vSt = db()->prepare('SELECT * FROM product_variations WHERE id=? AND product_id=? AND is_active=1');
        $vSt->execute([(int)$variantId, (int)$productId]);
        $variant = $vSt->fetch();
        if (!$variant) return;
    } else {
        $variantId = null;
    }

    $key            = _cart_key($p['id'], $variantId);
    $availableStock = $variant ? (int)$variant['stock'] : (int)$p['stock'];
    $cart           = cart_items();
    if (isset($cart[$key])) {
        $newQty = $cart[$key]['qty'] + (int)$qty;
        $cart[$key]['qty'] = min($newQty, $availableStock);
    } else {
        $initialQty = min(max(1, (int)$qty), $availableStock);
        if ($initialQty <= 0) return; // stok yok
        $cart[$key] = array(
            'id'           => (int)$p['id'],
            'variant_id'   => $variantId ? (int)$variantId : null,
            'variant_label'=> $variant ? $variant['label'] : null,
            'name'         => $p['name'],
            'price'        => $variant ? (float)$variant['price'] : (float)$p['price'],
            'image'        => $variant && !empty($variant['image']) ? $variant['image'] : $p['image'],
            'sku'          => $variant ? $variant['sku'] : null,
            'qty'          => $initialQty,
        );
    }
    $_SESSION['cart'] = $cart;
}

function cart_update($key, $qty) {
    // Geri uyumluluk: integer geldiyse productId:0'a çevir
    if (is_numeric($key) && strpos((string)$key, ':') === false) {
        $key = $key . ':0';
    }
    $cart = cart_items();
    if ($qty <= 0) {
        unset($cart[$key]);
    } elseif (isset($cart[$key])) {
        $item = $cart[$key];
        $vid  = !empty($item['variant_id']) ? (int)$item['variant_id'] : null;
        // Güncel stok — aşıma izin verme
        try {
            if ($vid) {
                $stq = db()->prepare('SELECT stock FROM product_variations WHERE id=? AND is_active=1');
                $stq->execute([$vid]);
            } else {
                $stq = db()->prepare('SELECT stock FROM products WHERE id=? AND is_active=1 AND deleted_at IS NULL');
                $stq->execute([(int)$item['id']]);
            }
            $stock = (int)($stq->fetchColumn() ?: 0);
        } catch (\Exception $e) {
            $stock = PHP_INT_MAX; // DB hatasında kısıtlama yapma
        }
        $newQty = min((int)$qty, $stock);
        if ($newQty <= 0) {
            unset($cart[$key]);
        } else {
            $cart[$key]['qty'] = $newQty;
        }
    }
    $_SESSION['cart'] = $cart;
}

function cart_remove($key) {
    if (is_numeric($key) && strpos((string)$key, ':') === false) {
        $key = $key . ':0';
    }
    $cart = cart_items();
    unset($cart[$key]);
    $_SESSION['cart'] = $cart;
}

function cart_clear() { $_SESSION['cart'] = array(); }

/**
 * Giriş yapmış kullanıcının sepetini DB'ye kaydeder (terk edilmiş sepet takibi).
 * cart_add() ve cart_update() sonrasında çağrılır.
 */
function cart_persist(): void {
    $u = current_user();
    if (!$u) return;
    try {
        if (empty($_SESSION['cart'])) {
            // Sepet boşaldı — terk edilmiş sepet kaydını sil
            db()->prepare('DELETE FROM abandoned_carts WHERE user_id=?')->execute([$u['id']]);
            return;
        }
        $json = json_encode(array_values(cart_items()), JSON_UNESCAPED_UNICODE);
        $st = db()->prepare(
            'INSERT INTO abandoned_carts (user_id, email, cart_json, notified_at)
             VALUES (?,?,?,NULL)
             ON DUPLICATE KEY UPDATE cart_json=VALUES(cart_json), updated_at=NOW(), notified_at=NULL'
        );
        $st->execute([$u['id'], $u['email'], $json]);
    } catch (Exception $e) { /* tablo yoksa sessizce geç */ }
}

/**
 * Sipariş tamamlandığında terk edilmiş sepet kaydını temizle.
 */
function cart_abandon_clear(): void {
    $u = current_user();
    if (!$u) return;
    try {
        db()->prepare('DELETE FROM abandoned_carts WHERE user_id=?')->execute([$u['id']]);
    } catch (Exception $e) {}
}

function cart_total() {
    $t = 0.0;
    foreach (cart_items() as $it) $t += $it['price'] * $it['qty'];
    return $t;
}
