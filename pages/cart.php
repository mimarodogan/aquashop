<?php
require_once __DIR__ . '/../includes/functions.php';
$page = 'cart'; $title = 'Sepet';

if ($_SERVER['REQUEST_METHOD']==='POST' && csrf_check($_POST['csrf'] ?? null)) {
    $action = $_POST['action'] ?? '';
    $cartKey = $_POST['id'] ?? '';  // "productId:variantId" veya integer (geri uyum)
    if ($action === 'update') { cart_update($cartKey, max(0,(int)($_POST['qty'] ?? 0))); cart_persist(); }
    if ($action === 'remove') { cart_remove($cartKey); cart_persist(); }
    if ($action === 'clear')  { cart_clear(); cart_abandon_clear(); }
    if ($action === 'apply_coupon') {
        require_once __DIR__ . '/../includes/coupons.php';
        require_once __DIR__ . '/../includes/pricing.php';
        $code = strtoupper(trim($_POST['coupon_code'] ?? ''));
        if ($code === '') {
            unset($_SESSION['cart_coupon']);
        } else {
            $items = cart_items();
            $itemsTotal = 0; foreach ($items as $it) $itemsTotal += $it['price']*$it['qty'];
            $vat = vat_breakdown($itemsTotal);
            $u = current_user();
            $check = coupon_validate($code, $vat['total'], $u['id'] ?? null);
            if ($check['ok']) {
                $_SESSION['cart_coupon'] = $code;
                flash_set('success','Kupon uygulandı: '.$code);
            } else {
                unset($_SESSION['cart_coupon']);
                flash_set('err', $check['error']);
            }
        }
        redirect(url('cart'));
    }
    if ($action === 'remove_coupon') {
        unset($_SESSION['cart_coupon']);
        flash_set('success','Kupon kaldırıldı.');
        redirect(url('cart'));
    }
    if ($action === 'apply_points') {
        require_once __DIR__ . '/../models/Loyalty.php';
        $u = current_user();
        if ($u && function_exists('loyalty_enabled') && loyalty_enabled()) {
            $bal = loyalty_balance((int)$u['id']);
            // "Tümünü kullan" → bakiyenin tamamını iste; cart_pricing tutara göre kırpar
            $req = !empty($_POST['use_all']) ? $bal : max(0, (int)($_POST['points'] ?? 0));
            $req = min($req, $bal);
            if ($req < loyalty_min_redeem()) {
                unset($_SESSION['cart_points']);
                flash_set('err','En az ' . loyalty_min_redeem() . ' puan kullanabilirsiniz.');
            } else {
                $_SESSION['cart_points'] = $req;
                flash_set('success','Puanlarınız uygulandı.');
            }
        }
        redirect(url('cart'));
    }
    if ($action === 'remove_points') {
        unset($_SESSION['cart_points']);
        flash_set('success','Puan kullanımı kaldırıldı.');
        redirect(url('cart'));
    }
    redirect(url('cart'));
}

include __DIR__ . '/../includes/header.php';
?>
<style>
/* ── Sepet sayfası inline stilleri ── */
.cart-layout{display:grid;grid-template-columns:1.6fr 1fr;gap:48px;align-items:start}
.cart-table{width:100%;border-collapse:collapse}
.cart-table th,.cart-table td{text-align:left;padding:14px 14px;border-bottom:1px solid var(--gold-border);vertical-align:middle}
.cart-table th{font-size:11px;letter-spacing:.22em;text-transform:uppercase;color:var(--muted-text);font-weight:600;background:var(--cream)}
.cart-table .cart-name{color:var(--ink);font-weight:600}
.cart-table .cart-variant{display:block;font-size:13px;color:var(--muted-text);margin-top:3px}
.cart-table .cart-cell-total{color:var(--gold);font-weight:700;white-space:nowrap}
.cart-table .qty-form{display:flex;gap:8px;align-items:center}
.cart-table .qty-input{width:70px;padding:8px;min-height:40px;background:var(--olive-2);border:1px solid var(--gold-border);color:var(--ink);border-radius:var(--radius);font-family:inherit}
.cart-table .row-actions{display:flex;flex-direction:column;gap:6px}
.cart-table .row-actions form{margin:0}
.cart-table .row-actions .btn{width:100%;white-space:nowrap}
.cart-footer-row{padding:18px 24px;display:flex;justify-content:space-between;gap:12px;flex-wrap:wrap}
.cart-footer-row form{margin:0}
@media(max-width:900px){.cart-layout{grid-template-columns:1fr;gap:24px}}
@media(max-width:480px){.crosssell-grid{grid-template-columns:1fr 1fr}.crosssell-item{padding:8px}.crosssell-item .name{font-size:12px;min-height:32px}}
@media(max-width:720px){
  .cart-table,.cart-table tbody{display:block}
  .cart-table thead{display:none}
  .cart-table tr{display:block;border:1px solid var(--gold-border);border-radius:var(--radius);padding:14px;margin:0 14px 12px;background:var(--olive-2);box-shadow:var(--shadow-xs)}
  .cart-table td{display:block;border:none;padding:6px 0;font-size:14px}
  .cart-table td.cart-cell-product{padding-bottom:10px;margin-bottom:8px;border-bottom:1px solid var(--gold-border)}
  .cart-table td.cart-cell-price::before{content:'Birim Fiyat: ';color:var(--muted-text);font-size:12px;font-weight:500}
  .cart-table td.cart-cell-total{text-align:left;padding-top:8px;font-size:16px}
  .cart-table td.cart-cell-total::before{content:'Ara Toplam: ';color:var(--muted-text);font-size:12px;font-weight:500}
  .cart-table td.cart-cell-qty{padding:10px 0;border-top:1px solid var(--gold-border);margin-top:6px}
  .cart-table td.cart-cell-qty::before{content:'Adet: ';display:block;color:var(--muted-text);font-size:12px;font-weight:500;margin-bottom:6px}
  .cart-table .qty-form .btn{flex:1;min-height:44px}
  .cart-table .qty-input{width:88px !important;min-height:44px;font-size:16px !important}
  .cart-table td.cart-cell-actions{padding-top:12px;border-top:1px solid var(--gold-border);margin-top:6px}
  .cart-table .row-actions{flex-direction:row;gap:8px}
  .cart-table .row-actions .btn{font-size:11px;min-height:40px}
  .cart-footer-row{flex-direction:column;padding:14px 16px}
  .cart-footer-row .btn,.cart-footer-row form,.cart-footer-row form .btn{width:100%}
  .cart-summary{padding:20px !important}
  .cart-summary h3{font-size:18px !important}
}
</style>
<?php

// GA4 add_to_cart — product-detail'den buraya redirect olduysa (idempotent flag)
if (!empty($_SESSION['ga_pending_add_to_cart'])) {
    $__a = $_SESSION['ga_pending_add_to_cart'];
    $__row = [
        'id'       => $__a['product_id'],
        'name'     => $__a['product_name'],
        'price'    => $__a['price'],
        'sku'      => $__a['sku']      ?? null,
        'cat_name' => $__a['cat_name'] ?? null,
    ];
    analytics_event('add_to_cart', [
        'currency' => 'TRY',
        'value'    => round((float)$__a['price'] * (int)$__a['qty'], 2),
        'items'    => [ analytics_ecommerce_item($__row, (int)$__a['qty']) ],
    ]);
    unset($_SESSION['ga_pending_add_to_cart']);
}

$items = cart_items();

// Her kalem için güncel stok (input max + uyarı için)
$__itemStocks = [];
foreach ($items as $__ck => $__ci) {
    try {
        if (!empty($__ci['variant_id'])) {
            $__sq = db()->prepare('SELECT stock FROM product_variations WHERE id=?');
            $__sq->execute([(int)$__ci['variant_id']]);
        } else {
            $__sq = db()->prepare('SELECT stock FROM products WHERE id=?');
            $__sq->execute([(int)$__ci['id']]);
        }
        $__itemStocks[$__ck] = max(0, (int)($__sq->fetchColumn() ?: 0));
    } catch (\Exception $e) {
        $__itemStocks[$__ck] = 9999;
    }
}

// GA4 view_cart — sepet sayfası açıldığında
if ($items) {
    $__ga_items = [];
    $__ga_value = 0.0;
    foreach ($items as $__i => $__it) {
        $__pRow = [
            'id'    => $__it['product_id'] ?? 0,
            'name'  => $__it['name']       ?? '',
            'price' => $__it['price']      ?? 0,
            'sku'   => $__it['sku']        ?? null,
        ];
        $__ga_items[] = analytics_ecommerce_item($__pRow, (int)($__it['qty'] ?? 1), null, $__i);
        $__ga_value  += (float)$__it['price'] * (int)$__it['qty'];
    }
    analytics_event('view_cart', [
        'currency' => 'TRY',
        'value'    => round($__ga_value, 2),
        'items'    => $__ga_items,
    ]);
}
?>

<section class="page-header">
  <div class="container">
    <span class="kicker">Alışveriş</span>
    <h1 style="margin-top:10px">Sepetim</h1>
    <div class="breadcrumb"><a href="<?= url('home') ?>">Anasayfa</a><span>/</span>Sepet</div>
  </div>
</section>

<section>
  <div class="container">
    <?php if (!$items): ?>
      <div class="panel center" style="padding:80px"><h3>Sepetiniz boş</h3><p class="muted" style="margin:14px 0 24px">Alışverişe başlamak için ürünlere göz atın.</p><a class="btn btn-primary" href="<?= url('products') ?>">Ürünleri Keşfet</a></div>
    <?php else: ?>
    <div class="cart-layout">
      <div class="panel" style="padding:0">
        <table class="cart-table">
          <thead><tr><th>Ürün</th><th>Fiyat</th><th>Adet</th><th>Ara Toplam</th><th></th></tr></thead>
          <tbody>
          <?php foreach ($items as $cartKey => $it): ?>
            <tr>
              <td class="cart-cell-product">
                <span class="cart-name"><?= e($it['name']) ?></span>
                <?php if (!empty($it['variant_label'])): ?>
                  <span class="cart-variant">Seçenek: <?= e($it['variant_label']) ?></span>
                <?php endif; ?>
              </td>
              <td class="cart-cell-price"><?= money($it['price']) ?></td>
              <td class="cart-cell-qty">
                <?php $__maxQty = $__itemStocks[$cartKey] ?? 9999; ?>
                <?php if ($__maxQty > 0 && (int)$it['qty'] > $__maxQty): ?>
                  <p style="font-size:12px;color:#c0392b;margin:0 0 4px">⚠ Stokta <?= $__maxQty ?> adet kaldı</p>
                <?php elseif ($__maxQty <= 5 && $__maxQty > 0): ?>
                  <p style="font-size:12px;color:#a07000;margin:0 0 4px">Son <?= $__maxQty ?> adet</p>
                <?php elseif ($__maxQty === 0): ?>
                  <p style="font-size:12px;color:#c0392b;margin:0 0 4px">⚠ Stokta kalmadı</p>
                <?php endif; ?>
                <form method="post" class="qty-form">
                  <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                  <input type="hidden" name="action" value="update">
                  <input type="hidden" name="id" value="<?= e($cartKey) ?>">
                  <input type="number" name="qty" value="<?= (int)$it['qty'] ?>" min="1" max="<?= $__maxQty ?>" class="qty-input" inputmode="numeric" aria-label="Adet">
                  <button class="btn btn-secondary btn-sm">Güncelle</button>
                </form>
              </td>
              <td class="cart-cell-total"><?= money($it['price'] * $it['qty']) ?></td>
              <td class="cart-cell-actions">
                <div class="row-actions">
                  <form method="post"><input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="remove"><input type="hidden" name="id" value="<?= e($cartKey) ?>"><button class="btn btn-secondary btn-sm">Kaldır</button></form>
                  <?php if (function_exists('saved_items_enabled') && saved_items_enabled() && current_user()): ?>
                    <form method="post" action="<?= SITE_URL ?>/saved-items.php">
                      <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                      <input type="hidden" name="action" value="save_from_cart">
                      <input type="hidden" name="cart_key" value="<?= e($cartKey) ?>">
                      <button class="btn btn-secondary btn-sm" type="submit" title="Sonra al listesine taşı">📋 Sonra Al</button>
                    </form>
                  <?php endif; ?>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
        <div class="cart-footer-row">
          <a class="btn btn-secondary btn-sm" href="<?= url('products') ?>">← Alışverişe Devam</a>
          <form method="post"><input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="clear"><button class="btn btn-secondary btn-sm">Sepeti Temizle</button></form>
        </div>
      </div>

      <?php require_once __DIR__ . '/../includes/pricing.php'; $pr = cart_pricing(); ?>
      <aside class="panel cart-summary">
        <h3 style="margin-bottom:18px">Sipariş Özeti</h3>
        <div style="display:flex;justify-content:space-between;padding:8px 0;border-bottom:1px solid var(--gold-border)"><span>Ara Toplam</span><span><?= money($pr['items_total']) ?></span></div>
        <?php if ($pr['discount'] > 0): ?>
          <div style="display:flex;justify-content:space-between;padding:8px 0;border-bottom:1px solid var(--gold-border);color:var(--leaf)"><span>İndirim (<?= e($pr['coupon_code']) ?>)</span><span>− <?= money($pr['discount']) ?></span></div>
        <?php endif; ?>
        <?php if (!empty($pr['loyalty_value']) && $pr['loyalty_value'] > 0): ?>
          <div style="display:flex;justify-content:space-between;padding:8px 0;border-bottom:1px solid var(--gold-border);color:var(--leaf)"><span>🏆 Puan indirimi (<?= (int)$pr['loyalty_points'] ?> puan)</span><span>− <?= money($pr['loyalty_value']) ?></span></div>
        <?php endif; ?>
        <div style="display:flex;justify-content:space-between;padding:8px 0;border-bottom:1px solid var(--gold-border)">
          <span>Kargo</span>
          <span><?php if ($pr['shipping'] > 0): ?><?= money($pr['shipping']) ?><?php else: ?><span style="color:var(--leaf);font-weight:600">Ücretsiz</span><?php endif; ?></span>
        </div>
        <?php if ((float)($pr['shipping_free_at'] ?? 0) > 0):
            $__free = (float)$pr['shipping_free_at'];
            $__cur  = (float)$pr['items_total'];
            $__pct  = max(0, min(100, ($__cur / $__free) * 100));
            $__rem  = $__free - $__cur;
            $__done = $__rem <= 0;
        ?>
        <div class="free-shipping-meter <?= $__done ? 'fsm-done' : '' ?>" aria-live="polite">
          <p class="fsm-text">
            <?php if ($__done): ?>
              🎉 <strong>Tebrikler!</strong> Ücretsiz kargo kazandınız.
            <?php else: ?>
              🚚 Ücretsiz kargo için <strong><?= money($__rem) ?></strong> daha ekleyin.
            <?php endif; ?>
          </p>
          <div class="fsm-track" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="<?= round($__pct) ?>">
            <div class="fsm-fill" style="width: <?= round($__pct) ?>%"></div>
          </div>
        </div>
        <?php endif; ?>
        <div style="display:flex;justify-content:space-between;padding:14px 0 4px;font-size:18px;color:var(--ink);font-weight:600"><span>Toplam</span><span><?= money($pr['grand_total']) ?></span></div>
        <?php if ($pr['vat'] > 0): ?>
          <div style="display:flex;justify-content:space-between;padding:0 0 10px;font-size:12px;color:var(--muted-text)"><span>KDV dahil (%<?= number_format($pr['vat_rate'], 0, ',', '') ?>)</span><span><?= money($pr['vat']) ?></span></div>
        <?php endif; ?>

        <!-- Kupon kodu -->
        <div style="margin-top:18px;padding-top:16px;border-top:1px solid var(--gold-border)">
          <?php if ($pr['coupon_code']): ?>
            <div style="display:flex;justify-content:space-between;align-items:center;padding:10px 12px;background:rgba(107,122,47,.08);border:1px solid rgba(107,122,47,.3);border-radius:var(--radius)">
              <span style="font-size:13px"><strong style="color:var(--leaf)"><?= e($pr['coupon_code']) ?></strong> kuponu uygulandı</span>
              <form method="post" style="display:inline">
                <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="action" value="remove_coupon">
                <button class="btn btn-secondary btn-sm">Kaldır</button>
              </form>
            </div>
          <?php else: ?>
            <form method="post" style="display:flex;gap:8px">
              <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
              <input type="hidden" name="action" value="apply_coupon">
              <input type="text" name="coupon_code" placeholder="İndirim kuponu" style="flex:1;padding:10px 12px;border:1px solid var(--field-border);border-radius:var(--radius);background:var(--olive-2);color:var(--ink);font-size:14px;text-transform:uppercase">
              <button class="btn btn-secondary btn-sm">Uygula</button>
            </form>
          <?php endif; ?>
        </div>

        <?php /* Sadakat puanı kullanımı — giriş yapmış ve yeterli bakiyesi olan müşteriye */ ?>
        <?php if (function_exists('loyalty_enabled') && loyalty_enabled() && ($__lu = current_user())):
            require_once __DIR__ . '/../models/Loyalty.php';
            $__lbal = loyalty_balance((int)$__lu['id']);
            if ($__lbal >= loyalty_min_redeem()): ?>
        <div style="margin-top:14px;padding-top:14px;border-top:1px solid var(--gold-border)">
          <?php if (!empty($pr['loyalty_points'])): ?>
            <div style="display:flex;justify-content:space-between;align-items:center;gap:10px;padding:10px 12px;background:rgba(201,162,75,.08);border:1px solid rgba(201,162,75,.3);border-radius:var(--radius)">
              <span style="font-size:13px">🏆 <strong style="color:var(--gold)"><?= (int)$pr['loyalty_points'] ?> puan</strong> kullanıldı (−<?= money($pr['loyalty_value']) ?>)</span>
              <form method="post" style="display:inline">
                <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="action" value="remove_points">
                <button class="btn btn-secondary btn-sm">Kaldır</button>
              </form>
            </div>
          <?php else: ?>
            <form method="post">
              <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
              <input type="hidden" name="action" value="apply_points">
              <input type="hidden" name="use_all" value="1">
              <button class="btn btn-secondary btn-sm" style="width:100%">🏆 <?= (int)$__lbal ?> puanımı kullan (≈ <?= money(loyalty_value_of($__lbal)) ?> indirim)</button>
            </form>
          <?php endif; ?>
        </div>
        <?php endif; endif; ?>

        <a class="btn btn-primary btn-block" href="<?= url('checkout') ?>" style="margin-top:18px">Ödeme Adımına Geç →</a>
      </aside>
    </div>

    <?php
    /* Cross-sell: birlikte sıkça alınanlar veya aynı kategoriden öne çıkanlar */
    $crossSellItems = cross_sell_for_cart($items, 3);
    if ($crossSellItems):
    ?>
    <div class="crosssell" role="complementary" aria-label="Sıkça birlikte alınanlar">
      <h3>✨ Bunlarla harika gider</h3>
      <div class="crosssell-grid">
        <?php foreach ($crossSellItems as $cs): ?>
          <div class="crosssell-item">
            <a href="<?= e(url('product', ['slug' => $cs['slug']])) ?>" style="text-decoration:none;color:inherit;width:100%">
              <?php if (!empty($cs['image'])): ?>
                <img loading="lazy" decoding="async" src="<?= e($cs['image']) ?>" alt="<?= e($cs['name']) ?>">
              <?php else: ?>
                <div style="width:100%;aspect-ratio:1;background:var(--cream);border-radius:6px;margin-bottom:8px;display:grid;place-items:center;color:var(--gold);font-size:32px;font-family:'Playfair Display',serif"><?= e(mb_substr($cs['name'],0,1)) ?></div>
              <?php endif; ?>
              <p class="name"><?= e($cs['name']) ?></p>
              <p class="price">
                <?php if (!empty($cs['price_on_request'])): ?>
                  <span style="font-size:12px;color:var(--muted-text)">İletişime Geçin</span>
                <?php else: ?>
                  <?= money($cs['price']) ?>
                <?php endif; ?>
              </p>
            </a>
            <?php if (empty($cs['has_variations']) && empty($cs['price_on_request']) && (int)$cs['stock'] > 0): ?>
              <form method="post" action="<?= SITE_URL ?><?= e(url('product', ['slug' => $cs['slug']])) ?>">
                <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="qty" value="1">
                <button class="btn btn-secondary btn-sm" type="submit">Sepete Ekle</button>
              </form>
            <?php else: ?>
              <a class="btn btn-secondary btn-sm" href="<?= e(url('product', ['slug' => $cs['slug']])) ?>">İncele</a>
            <?php endif; ?>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
    <?php endif; ?>

    <?php endif; ?>
  </div>
</section>

<?php include __DIR__ . '/../includes/footer.php'; ?>
