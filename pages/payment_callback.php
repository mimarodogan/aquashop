<?php
/**
 * iyzico Checkout Form callback'i.
 * iyzico, ödeme bittiğinde kullanıcıyı buraya POST ile yönlendirir; "token" ile sonucu sorgularız.
 */
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/iyzico.php';
$page = 'cart'; $title = 'Ödeme Sonucu';

$token = $_POST['token'] ?? $_GET['token'] ?? '';
$msg = null; $isOk = false; $orderId = null;

if ($token === '') {
    $msg = 'Geçersiz ödeme dönüşü (token bulunamadı).';
} else {
    // Hangi siparişe ait bul
    $row = db()->prepare('SELECT * FROM orders WHERE iyzico_token=? LIMIT 1');
    $row->execute([$token]);
    $order = $row->fetch();

    if (!$order) {
        $msg = 'Sipariş eşleşmesi bulunamadı.';
    } else {
        $orderId = (int)$order['id'];

        // Daha önce zaten paid'se idempotent çıkış yap
        if ($order['payment_status'] === 'paid') {
            $isOk = true;
            $msg = 'Ödemeniz daha önce alınmış.';
        } else {
            $r = iyzico_retrieve($token);
            $raw = $r['raw'] ?? null;
            if (!$r['ok']) {
                $msg = 'Ödeme doğrulanamadı: ' . ($r['error'] ?? 'bilinmeyen hata');
                db()->prepare("UPDATE payments SET status='failure', error_message=?, raw_response=? WHERE token=?")
                    ->execute([substr($msg,0,250), $raw, $token]);
            } else {
                $res = $r['res'];
                $paymentStatus = method_exists($res,'getPaymentStatus') ? $res->getPaymentStatus() : null;
                $status        = $res->getStatus(); // retrieve API çağrısı başarılı mı (ödeme sonucu DEĞİL)
                // DİKKAT: getStatus()==='success' yalnızca retrieve isteğinin işlendiğini gösterir;
                // paranın gerçekten alındığını GÖSTERMEZ. Ödemenin gerçek sonucu getPaymentStatus()'tadır.
                // Bu yüzden SADECE paymentStatus === 'SUCCESS' olduğunda ödeme alınmış sayılır
                // (aksi halde ödeme alınmadığı halde sipariş "paid" işaretlenir — kritik hata).
                if ($status === 'success' && $paymentStatus === 'SUCCESS') {
                    $payId   = method_exists($res,'getPaymentId') ? $res->getPaymentId() : null;
                    $instal  = method_exists($res,'getInstallment') ? (int)$res->getInstallment() : 1;
                    $cardF   = method_exists($res,'getCardFamily') ? $res->getCardFamily() : null;
                    $cardA   = method_exists($res,'getCardAssociation') ? $res->getCardAssociation() : null;
                    $bin     = method_exists($res,'getBinNumber') ? $res->getBinNumber() : null;
                    $last4   = method_exists($res,'getLastFourDigits') ? $res->getLastFourDigits() : null;

                    // KRİTİK: Stok şemasını transaction DIŞINDA hazırla.
                    // stock_apply_order() içindeki stock_ensure_table() bir CREATE TABLE (DDL) çalıştırır.
                    // Bu transaction içinde olursa MySQL transaction'ı IMPLICIT COMMIT eder → commit()
                    // "There is no active transaction" hatası verir (sipariş #7'deki hatanın sebebi).
                    // Burada bir kez çalıştırınca (static $done) transaction içinde tekrar DDL çalışmaz.
                    require_once __DIR__ . '/../includes/stock.php';
                    stock_ensure_table();
                    try { db()->exec("ALTER TABLE order_items ADD COLUMN IF NOT EXISTS stock_applied_at DATETIME DEFAULT NULL"); } catch (\Throwable $e) {}

                    try {
                        db()->beginTransaction();
                        db()->prepare("UPDATE orders SET payment_status='paid', status=IF(status='pending','paid',status), paid_at=NOW(), iyzico_payment_id=? WHERE id=?")
                            ->execute([$payId, $orderId]);
                        db()->prepare("UPDATE payments SET status='success', iyzico_payment_id=?, installment=?, card_family=?, card_assoc=?, card_last4=?, raw_response=? WHERE token=?")
                            ->execute([$payId, $instal, $cardF, $cardA, $last4, $raw, $token]);
                        // Stoktan düş — şema yukarıda hazırlandığı için burada DDL çalışmaz (transaction güvenli)
                        stock_apply_order($orderId, 'paid');
                        // Kupon kullanımını işle
                        if (!empty($order['coupon_code']) && (float)$order['discount_amount'] > 0) {
                            require_once __DIR__ . '/../includes/coupons.php';
                            $cp = coupon_find($order['coupon_code']);
                            if ($cp) coupon_redeem((int)$cp['id'], $orderId, (float)$order['discount_amount'], $order['user_id'] ?: null);
                        }
                        db()->commit();
                    } catch (\Throwable $txErr) {
                        if (db()->inTransaction()) db()->rollBack();
                        // Ödeme iyzico'da başarılı ama DB güncellemesi başarısız —
                        // siparişi "paid" olarak işaretle, log'a düş, admin bilgilendir
                        error_log('[payment_callback] Transaction hatası sipariş #' . $orderId . ': ' . $txErr->getMessage());
                        try {
                            db()->prepare("UPDATE orders SET payment_status='paid', paid_at=NOW(), iyzico_payment_id=? WHERE id=? AND payment_status!='paid'")
                                ->execute([$payId ?? null, $orderId]);
                        } catch (\Throwable $e2) {}
                        // Transaction geri alındı → stok düşümü de geri alındı.
                        // Sipariş ödendi sayıldığına göre stoku transaction dışında telafi et (idempotent).
                        try {
                            require_once __DIR__ . '/../includes/stock.php';
                            stock_apply_order($orderId, 'paid_recovery');
                        } catch (\Throwable $e3) {
                            error_log('[payment_callback] Stok telafisi başarısız sipariş #' . $orderId . ': ' . $e3->getMessage());
                        }
                        // Devam et — sipariş tamamlandı sayılır
                    }
                    // Sipariş onay e-postası (transaction dışı, hata ödemeyi etkilemez)
                    require_once __DIR__ . '/../includes/order_mailer.php';
                    @order_send_confirmation($orderId);

                    cart_clear();
                    unset($_SESSION['quick_checkout'], $_SESSION['pending_order_id']);
                    // GA4 purchase event'i order.php'de tek seferlik basılsın diye işaret bırak
                    $_SESSION['ga_pending_purchase'] = $orderId;
                    $isOk = true;
                } else {
                    $err = method_exists($res,'getErrorMessage') ? $res->getErrorMessage() : 'Ödeme başarısız.';
                    // Ödeme başarısız — sipariş 'pending' ise iptal et ki admin panelinde
                    // "Bekleyen Sipariş" listesinde takılı kalmasın.
                    db()->prepare("UPDATE orders SET payment_status='failed', status=IF(status='pending','cancelled',status) WHERE id=?")->execute([$orderId]);
                    db()->prepare("UPDATE payments SET status='failure', error_message=?, raw_response=? WHERE token=?")
                        ->execute([substr((string)$err, 0, 250), $raw, $token]);
                    // Checkout'ta rezerve edilen stoğu sessizce geri aç (ürün tekrar satılabilir olsun)
                    try {
                        require_once __DIR__ . '/../includes/stock.php';
                        stock_revert_order($orderId, 'payment_failed', true);
                    } catch (\Throwable $e) {}
                    // Kullanılan sadakat puanını geri ver
                    try {
                        require_once __DIR__ . '/../models/Loyalty.php';
                        @loyalty_revoke_for_order($orderId);
                    } catch (\Throwable $e) {}
                    $msg = 'Ödeme tamamlanamadı: ' . $err;
                }
            }
        }
    }
}

if ($isOk && $orderId) {
    // Ödeme dönüşü çapraz-site POST olduğu için oturum çerezi düşmüş olabilir (SameSite).
    // iyzico token ile doğrulanmış siparişin sahibini geri giriş yaptır ki sipariş sayfası
    // kullanıcıyı login'e atmasın. Yalnızca oturum BOŞSA ve sipariş bir üyeye aitse çalışır
    // (mevcut farklı bir oturumu ASLA ezmez — güvenli).
    if (empty($_SESSION['user_id']) && !empty($order['user_id'])) {
        $_SESSION['user_id'] = (int)$order['user_id'];
    }
    flash_set('success','Ödemeniz alındı. Sipariş No: #' . $orderId);
    // Başarılı ödeme — yedeklenen orijinal sepeti de temizle
    unset($_SESSION['pre_quick_cart']);
    redirect(url('order', ['id'=>$orderId]));
}

// Ödeme başarısız veya hata — Hemen Satın Al akışından gelindiyse orijinal sepeti geri yükle
if (!empty($_SESSION['pre_quick_cart'])) {
    $_SESSION['cart'] = $_SESSION['pre_quick_cart'];
    unset($_SESSION['pre_quick_cart']);
}
unset($_SESSION['pending_order_id'], $_SESSION['quick_checkout']);

include __DIR__ . '/../includes/header.php';
?>
<section class="page-header">
  <div class="container">
    <span class="kicker">Ödeme</span>
    <h1 style="margin-top:10px">Ödeme Sonucu</h1>
  </div>
</section>
<section><div class="container" style="max-width:680px">
  <div class="panel" style="text-align:center">
    <div class="alert alert-err" role="alert"><?= e($msg ?: 'Ödeme tamamlanamadı.') ?></div>
    <p class="muted" style="margin:18px 0 24px">Tekrar denemek için sepete dönebilir veya farklı bir ödeme yöntemi seçebilirsiniz.</p>
    <div class="btn-row" style="justify-content:center">
      <a class="btn btn-primary" href="<?= url('checkout') ?>">Tekrar Dene</a>
      <a class="btn btn-secondary" href="<?= url('cart') ?>">Sepete Dön</a>
    </div>
    <?php if ($orderId): ?>
      <p class="muted" style="margin-top:18px;font-size:12px">Sipariş No: <strong>#<?= (int)$orderId ?></strong></p>
    <?php endif; ?>
  </div>
</div></section>
<?php include __DIR__ . '/../includes/footer.php'; ?>
