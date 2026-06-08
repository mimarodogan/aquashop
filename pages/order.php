<?php
require_once __DIR__ . '/../includes/functions.php';
$user = current_user();
if (!$user) redirect(url('login'));

$id = (int)(isset($_GET['id']) ? $_GET['id'] : 0);
// Y-9 GÜVENLİK: email match SADECE guest order (user_id IS NULL) için geçerli.
// Aksi halde başkasının e-postasını alarak başkalarının siparişlerine erişim mümkün olurdu.
$st = db()->prepare('SELECT * FROM orders WHERE id=? AND (user_id=? OR (user_id IS NULL AND email=?))');
$st->execute(array($id, $user['id'], $user['email']));
$o = $st->fetch();
if (!$o) { http_response_code(404); $title='Sipariş bulunamadı'; include __DIR__ . '/../includes/header.php'; echo '<section class="container" style="padding:120px 0"><h1>Sipariş bulunamadı</h1></section>'; include __DIR__ . '/../includes/footer.php'; exit; }

$items = db()->prepare('SELECT * FROM order_items WHERE order_id=?'); $items->execute(array($id)); $items = $items->fetchAll();
$title = 'Sipariş #' . $o['id'];
include __DIR__ . '/../includes/header.php';
$tu = tracking_url($o['tracking_carrier'], $o['tracking_number']);

// Ödeme/sipariş YENİ tamamlandıysa (checkout/callback'ten taze geldi) teşekkür başlığı göster.
// GA bloğu birazdan ga_pending_purchase'ı sileceği için bayrağı burada yakalıyoruz.
$justCompleted = (!empty($_SESSION['ga_pending_purchase']) && (int)$_SESSION['ga_pending_purchase'] === (int)$id);

// GA4 purchase event'i — sadece checkout/payment_callback'ten yeni gelen siparişlerde,
// kullanıcının sipariş geçmişine geri bakmasında tekrar basılmaz (idempotent).
if (!empty($_SESSION['ga_pending_purchase']) && (int)$_SESSION['ga_pending_purchase'] === (int)$id) {
    $__ga_items = [];
    foreach ($items as $__i => $__it) {
        $__pRow = [
            'id'    => $__it['product_id'] ?? 0,
            'name'  => $__it['product_name'] ?? '',
            'price' => $__it['price'] ?? 0,
            'sku'   => null,
        ];
        $__variant = !empty($__it['variation_label']) ? ['label' => $__it['variation_label']] : null;
        $__ga_items[] = analytics_ecommerce_item($__pRow, (int)$__it['qty'], $__variant, $__i);
    }
    analytics_event('purchase', [
        'transaction_id' => (string)$o['id'],
        'value'          => round((float)$o['total'], 2),
        'currency'       => 'TRY',
        'tax'            => round((float)($o['vat_amount'] ?? 0), 2),
        'shipping'       => round((float)($o['shipping_amount'] ?? 0), 2),
        'coupon'         => $o['coupon_code'] ?? null,
        'items'          => $__ga_items,
    ]);
    // Tek seferlik — flag'i temizle
    unset($_SESSION['ga_pending_purchase']);

    // Meta CAPI server-side gönderim (varsa)
    if (function_exists('meta_capi_send_purchase')) {
        @meta_capi_send_purchase($o, $items);
    }
}
?>
<section class="page-header">
  <div class="container">
    <?php if ($justCompleted): ?>
      <span class="kicker">Teşekkürler</span>
      <h1 style="margin-top:10px">Bizi tercih ettiğiniz için teşekkür ederiz 🎉</h1>
      <p class="muted" style="margin:10px 0 0;font-size:15px;line-height:1.6">
        Siparişiniz <strong style="color:var(--ink)">#<?= (int)$o['id'] ?></strong> başarıyla alındı.
        <?= ($o['payment_method'] ?? '') === 'havale'
            ? 'Havale/EFT bilgileri e-postanıza gönderildi; ödemeniz onaylanınca hazırlamaya başlayacağız.'
            : 'Ödemeniz alındı, siparişinizi hazırlamaya başlıyoruz.' ?>
        Detayları aşağıda görebilirsiniz.
      </p>
      <div class="breadcrumb" style="margin-top:12px"><a href="<?= url('account') ?>">Hesabım</a><span>/</span>Sipariş Detayı</div>
    <?php else: ?>
      <span class="kicker">Hesabım</span>
      <h1 style="margin-top:10px">Sipariş #<?= (int)$o['id'] ?></h1>
      <div class="breadcrumb"><a href="<?= url('account') ?>">Hesabım</a><span>/</span>Sipariş Detayı</div>
    <?php endif; ?>
  </div>
</section>

<section>
  <div class="container" style="display:grid;grid-template-columns:2fr 1fr;gap:32px;align-items:start">
    <div>
      <div class="panel" style="padding:0">
        <h3 style="padding:24px 24px 0">Ürünler</h3>
        <table>
          <thead><tr><th>Ürün</th><th>Adet</th><th>Fiyat</th><th>Toplam</th></tr></thead>
          <tbody>
            <?php foreach ($items as $it): ?>
              <tr><td><?= e($it['product_name']) ?></td><td><?= (int)$it['qty'] ?></td><td><?= money($it['price']) ?></td><td style="color:var(--gold);font-weight:600"><?= money($it['price']*$it['qty']) ?></td></tr>
            <?php endforeach; ?>
          </tbody>
        </table>
        <div style="display:flex;justify-content:space-between;padding:18px 24px;font-size:18px;color:var(--gold);font-weight:600;border-top:1px solid var(--gold-border)"><span>Toplam</span><span><?= money($o['total']) ?></span></div>
      </div>
    </div>
    <aside>
      <div class="panel">
        <h3>Durum</h3>
        <p style="margin:6px 0 14px"><span class="status <?= e($o['status']) ?>"><?= e(status_label($o['status'])) ?></span></p>
        <p class="muted" style="font-size:12px">Sipariş Tarihi</p>
        <p><?= e($o['created_at']) ?></p>
        <?php if ($o['shipped_at']): ?>
          <p class="muted" style="font-size:12px;margin-top:10px">Kargoya Verildiği Tarih</p>
          <p><?= e($o['shipped_at']) ?></p>
        <?php endif; ?>
        <?php if ($o['status']==='cancelled' && !empty($o['cancellation_reason'])): ?>
          <div class="divider"></div>
          <p class="muted" style="font-size:12px">İptal Tarihi</p>
          <p style="margin-bottom:10px"><?= e($o['cancelled_at']) ?></p>
          <p class="muted" style="font-size:12px">İptal Sebebi</p>
          <p style="color:#f5a3a3"><?= nl2br(e($o['cancellation_reason'])) ?></p>
        <?php endif; ?>
      </div>

      <div class="panel">
        <h3>Kargo Takibi</h3>
        <?php if ($o['tracking_number']): ?>
          <p class="muted" style="font-size:12px">Kargo Firması</p>
          <p style="margin-bottom:10px"><strong style="color:var(--champagne)"><?= e(carrier_label($o['tracking_carrier'])) ?></strong></p>
          <p class="muted" style="font-size:12px">Takip Numarası</p>
          <p style="margin-bottom:14px;color:var(--gold);font-family:monospace;letter-spacing:.05em"><?= e($o['tracking_number']) ?></p>
          <?php if ($tu): ?>
            <a class="btn btn-primary btn-block" href="<?= e($tu) ?>" target="_blank">Kargomu Takip Et →</a>
          <?php else: ?>
            <p class="muted" style="font-size:13px">Bu firmanın takip linki tanımsız; numarayı manuel sorgulamanız gerekebilir.</p>
          <?php endif; ?>
        <?php else: ?>
          <p class="muted">Siparişiniz henüz kargoya verilmedi. Kargoya verildiğinde takip numarası bu alanda görünecek.</p>
        <?php endif; ?>
      </div>

      <div class="panel">
        <h3>Teslimat Adresi</h3>
        <p><strong style="color:var(--champagne)"><?= e($o['full_name']) ?></strong></p>
        <p class="muted" style="font-size:13px"><?= e($o['phone']) ?></p>
        <div class="divider"></div>
        <p><?= nl2br(e($o['address'])) ?></p>
        <p><?= e($o['city']) ?></p>
        <div class="divider"></div>
        <p class="muted" style="font-size:12px">Ödeme</p>
        <p><?= e(payment_label($o['payment_method'])) ?></p>
      </div>
    </aside>
  </div>
</section>
<?php include __DIR__ . '/../includes/footer.php'; ?>
