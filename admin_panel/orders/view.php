<?php
$page='orders';
require_once __DIR__ . '/../core/auth.php';
require_once __DIR__ . '/../../includes/iyzico.php';
$id=(int)($_GET['id']??0);
$st=db()->prepare('SELECT * FROM orders WHERE id=?'); $st->execute([$id]); $o=$st->fetch();
if (!$o){ flash_set('err','Sipariş bulunamadı.'); redirect('list.php'); }

// POS siparişi mi? (mağazada yapılan satış — kargo yoktur)
$isPOS = (!empty($o['source']) && $o['source'] === 'pos')
      || (!empty($o['address']) && $o['address'] === 'Mağaza satışı');

if ($_SERVER['REQUEST_METHOD']==='POST' && csrf_check($_POST['csrf'] ?? null)) {
    $action = $_POST['action'] ?? '';
    if ($action === 'status' && isset($_POST['status'])){
        db()->prepare('UPDATE orders SET status=? WHERE id=?')->execute([$_POST['status'],$id]);
        flash_set('ok','Durum güncellendi.'); redirect('view.php?id='.$id);
    }
    if ($action === 'cancel') {
        $reason = trim($_POST['cancellation_reason'] ?? '');
        if ($reason === '') {
            flash_set('err','İptal sebebi yazmalısınız.');
        } else {
            order_cancel($id, $reason, (int)$ADMIN['id']);
            flash_set('ok','Sipariş iptal edildi.');
        }
        redirect('view.php?id='.$id);
    }
    if ($action === 'restore_cancel') {
        db()->prepare("UPDATE orders SET status='pending', cancellation_reason=NULL, cancelled_at=NULL, cancelled_by=NULL WHERE id=?")->execute(array($id));
        flash_set('ok','İptal kaldırıldı, sipariş yeniden Beklemede.');
        redirect('view.php?id='.$id);
    }
    if ($action === 'iyzico_refund') {
        // iyzico üzerinden tam iade (Cancel — aynı gün) veya Refund (sonrası)
        if (!iyzico_sdk_loaded()) {
            flash_set('err','iyzipay-php SDK yüklü değil.');
            redirect('view.php?id='.$id);
        }
        if (empty($o['iyzico_payment_id']) || $o['payment_status'] !== 'paid') {
            flash_set('err','Bu sipariş iyzico üzerinden ödenmemiş.');
            redirect('view.php?id='.$id);
        }
        $opts = iyzico_options_obj();
        if (!$opts) { flash_set('err','iyzico ayarları eksik.'); redirect('view.php?id='.$id); }

        try {
            // Önce Cancel'ı dene (aynı gün, tam iade)
            $cancelReq = new \Iyzipay\Request\CreateCancelRequest();
            $cancelReq->setLocale(\Iyzipay\Model\Locale::TR);
            $cancelReq->setConversationId('C-'.$id.'-'.bin2hex(random_bytes(3)));
            $cancelReq->setPaymentId($o['iyzico_payment_id']);
            $cancelReq->setIp($_SERVER['REMOTE_ADDR'] ?? '127.0.0.1');
            $res = \Iyzipay\Model\Cancel::create($cancelReq, $opts);

            if ($res->getStatus() === 'success') {
                db()->beginTransaction();
                db()->prepare("UPDATE orders SET payment_status='refunded', status='cancelled', cancellation_reason=COALESCE(cancellation_reason,'iyzico iadesi'), cancelled_at=NOW(), cancelled_by=? WHERE id=?")
                    ->execute([(int)$ADMIN['id'], $id]);
                db()->prepare('INSERT INTO refunds (order_id, amount, reason, status, raw_response, created_by) VALUES (?,?,?,?,?,?)')
                    ->execute([$id, $o['total'], 'Tam iade (Cancel)', 'success', $res->getRawResult(), (int)$ADMIN['id']]);
                db()->commit();
                flash_set('ok','İade başarılı: ödeme iyzico üzerinden iptal edildi.');
            } else {
                $err = $res->getErrorMessage() ?: 'iyzico iade hatası';
                flash_set('err','İade başarısız: '.$err);
            }
        } catch (\Throwable $e) {
            flash_set('err','İade hatası: '.$e->getMessage());
        }
        redirect('view.php?id='.$id);
    }
    if ($action === 'tracking') {
        $car = trim($_POST['tracking_carrier'] ?? '');
        $num = trim($_POST['tracking_number']  ?? '');
        $sets = array('tracking_carrier=?','tracking_number=?');
        $args = array($car ?: null, $num ?: null);
        // Takip numarası girildiyse ve durum henüz "kargoda/teslim" değilse otomatik kargoya çek
        if ($num && !in_array($o['status'], array('shipped','delivered'))) {
            $sets[] = 'status=?'; $args[] = 'shipped';
            $sets[] = 'shipped_at=NOW()';
        }
        $sql = 'UPDATE orders SET ' . implode(', ', $sets) . ' WHERE id=?';
        $args[] = $id;
        db()->prepare($sql)->execute($args);

        // Kargo numarası yeni eklendiyse müşteriye SMS bildirimi
        if ($num && !in_array($o['status'], array('shipped','delivered'))) {
            require_once __DIR__ . '/../../includes/order_mailer.php';
            @order_send_shipped_notification($id);
        }
        flash_set('ok','Kargo bilgisi güncellendi.');
        redirect('view.php?id='.$id);
    }
}

$items=db()->prepare('SELECT * FROM order_items WHERE order_id=?'); $items->execute([$id]); $items=$items->fetchAll();
$title='Sipariş #'.$id;
require_once __DIR__ . '/../core/header.php';
?>
<div style="display:grid;grid-template-columns:2fr 1fr;gap:24px">
  <div class="panel">
    <h3>Ürünler</h3>
    <table><thead><tr><th>Ürün</th><th>Adet</th><th>Fiyat</th><th>Toplam</th></tr></thead><tbody>
    <?php foreach ($items as $it): ?>
      <tr><td><?= e($it['product_name']) ?></td><td><?= (int)$it['qty'] ?></td><td><?= money($it['price']) ?></td><td style="color:var(--gold);font-weight:600"><?= money($it['price']*$it['qty']) ?></td></tr>
    <?php endforeach; ?>
    </tbody></table>
    <div style="display:flex;justify-content:flex-end;padding:18px 14px;font-size:18px;color:var(--gold);font-weight:600">Toplam: <?= money($o['total']) ?></div>
  </div>
  <aside>
    <?php if ($isPOS): ?>
    <div class="panel" style="background:rgba(39,174,96,.07);border:1px solid rgba(39,174,96,.25)">
      <div style="display:flex;align-items:center;gap:8px;color:#1e8449;font-weight:700;font-size:15px">
        🏪 Mağaza Satışı
      </div>
      <p style="margin:8px 0 0;font-size:12px;color:#555">
        Müşteri ürünü yerinde teslim almıştır.<br>Kargo ve takip işlemi uygulanmaz.
      </p>
    </div>
    <?php endif; ?>
    <div class="panel">
      <h3>Durum</h3>
      <p style="margin-bottom:14px"><span class="status <?= e($o['status']) ?>"><?= e(status_label($o['status'])) ?></span></p>
      <?php if (!$isPOS && $o['status'] !== 'cancelled'): ?>
        <form method="post" style="display:grid;gap:10px">
          <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
          <input type="hidden" name="action" value="status">
          <select name="status">
            <?php foreach (status_options() as $s=>$lbl): if ($s==='cancelled') continue; ?>
              <option value="<?= e($s) ?>" <?= $o['status']===$s?'selected':'' ?>><?= e($lbl) ?></option>
            <?php endforeach; ?>
          </select>
          <button class="btn btn-primary btn-sm">Güncelle</button>
        </form>
      <?php endif; ?>
    </div>

    <div class="panel">
      <h3>İptal</h3>
      <?php if ($o['status'] === 'cancelled'): ?>
        <p class="muted" style="font-size:12px">İptal Tarihi</p>
        <p style="margin-bottom:10px"><?= e($o['cancelled_at']) ?></p>
        <p class="muted" style="font-size:12px">Sebep</p>
        <p style="margin-bottom:14px;color:var(--champagne)"><?= nl2br(e($o['cancellation_reason'])) ?></p>
        <form method="post" onsubmit="return confirm('İptal kaldırılsın mı? Sipariş Beklemede durumuna döner.')">
          <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
          <input type="hidden" name="action" value="restore_cancel">
          <button class="btn btn-secondary btn-sm">İptali Kaldır</button>
        </form>
      <?php else: ?>
        <form method="post" style="display:grid;gap:10px" onsubmit="return confirm('Sipariş iptal edilsin mi? Müşteri belirttiğiniz sebebi görür.')">
          <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
          <input type="hidden" name="action" value="cancel">
          <div class="field">
            <label>İptal Sebebi</label>
            <textarea name="cancellation_reason" rows="3" required placeholder="Örn: Stokta yok, müşteri talebi, ödeme sorunu..."></textarea>
          </div>
          <button class="btn btn-secondary btn-sm" style="color:#f5a3a3;border-color:#f5a3a3">Siparişi İptal Et</button>
        </form>
      <?php endif; ?>
    </div>

    <?php if (!$isPOS): ?>
    <div class="panel">
      <h3>Kargo Bilgisi</h3>
      <?php $tu = tracking_url($o['tracking_carrier'], $o['tracking_number']); ?>
      <?php if ($o['tracking_number']): ?>
        <p style="margin-bottom:6px"><strong style="color:var(--champagne)"><?= e(carrier_label($o['tracking_carrier'])) ?></strong></p>
        <p class="muted" style="font-size:12px">Takip No</p>
        <p style="margin-bottom:10px;color:var(--gold);font-family:monospace"><?= e($o['tracking_number']) ?></p>
        <?php if ($tu): ?><a class="btn btn-secondary btn-sm" href="<?= e($tu) ?>" target="_blank">Kargo Takibini Aç</a><?php endif; ?>
        <?php if ($o['shipped_at']): ?><p class="muted" style="font-size:12px;margin-top:10px">Gönderim: <?= e($o['shipped_at']) ?></p><?php endif; ?>
        <div class="divider"></div>
      <?php endif; ?>
      <form method="post" style="display:grid;gap:10px">
        <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
        <input type="hidden" name="action" value="tracking">
        <div class="field">
          <label>Kargo Firması</label>
          <select name="tracking_carrier">
            <option value="">— seçin —</option>
            <?php foreach (carriers() as $k=>$c): ?>
              <option value="<?= e($k) ?>" <?= ($o['tracking_carrier']===$k)?'selected':'' ?>><?= e($c['label']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="field"><label>Takip Numarası</label><input name="tracking_number" value="<?= e($o['tracking_number']) ?>" placeholder="Kargo takip no"></div>
        <small class="muted">Takip numarası girildiğinde sipariş durumu otomatik "Kargoda" olur.</small>
        <button class="btn btn-primary btn-sm">Kaydet</button>
      </form>
    </div>
    <?php endif; /* !$isPOS — kargo paneli sonu */ ?>
    <div class="panel">
      <h3>Ödeme</h3>
      <?php
        $payLabels = ['pending'=>'Bekliyor','paid'=>'Ödendi','failed'=>'Başarısız','refunded'=>'İade edildi','partial_refund'=>'Kısmi iade'];
        $payClass  = ['pending'=>'pending','paid'=>'paid','failed'=>'cancelled','refunded'=>'cancelled','partial_refund'=>'shipped'];
        $ps = $o['payment_status'] ?? 'pending';
      ?>
      <p class="muted" style="font-size:12px">Yöntem</p>
      <p style="margin-bottom:10px"><?= e(payment_label($o['payment_method'])) ?></p>
      <p class="muted" style="font-size:12px">Durum</p>
      <p style="margin-bottom:10px"><span class="status <?= e($payClass[$ps] ?? 'pending') ?>"><?= e($payLabels[$ps] ?? $ps) ?></span></p>
      <?php if (!empty($o['paid_at'])): ?>
        <p class="muted" style="font-size:12px">Ödeme Tarihi</p>
        <p style="margin-bottom:10px"><?= e($o['paid_at']) ?></p>
      <?php endif; ?>
      <?php if (!empty($o['iyzico_payment_id'])): ?>
        <p class="muted" style="font-size:12px">iyzico Ödeme ID</p>
        <p style="margin-bottom:10px;font-family:monospace;font-size:12px;color:var(--leaf)"><?= e($o['iyzico_payment_id']) ?></p>
      <?php endif; ?>

      <?php if ($o['payment_method']==='kart' && $ps==='paid' && !empty($o['iyzico_payment_id'])): ?>
        <div class="divider"></div>
        <form method="post" onsubmit="return confirm('Tam iade yapılsın mı? Ödeme iyzico üzerinden iptal edilir, sipariş iptal durumuna geçer. Bu işlem geri alınamaz.')">
          <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
          <input type="hidden" name="action" value="iyzico_refund">
          <button class="btn btn-danger btn-sm btn-block">Tam İade (iyzico)</button>
        </form>
        <small class="muted" style="display:block;margin-top:8px;font-size:11px">Aynı gün → Cancel (anında). Sonrası → ayrıca iyzico Merchant panelinden kısmi iade yapabilirsiniz.</small>
      <?php endif; ?>
    </div>

    <div class="panel">
      <h3>Müşteri</h3>
      <p><strong><?= e($o['full_name']) ?></strong></p>
      <p class="muted" style="font-size:13px"><?= e($o['email']) ?></p>
      <p class="muted" style="font-size:13px"><?= e($o['phone']) ?></p>
      <div class="divider"></div>
      <p class="muted" style="font-size:12px">Adres</p>
      <p><?= nl2br(e($o['address'])) ?></p>
      <p><?= e($o['city']) ?></p>
      <div class="divider"></div>
      <p class="muted" style="font-size:12px">Ödeme</p>
      <p><?= e(payment_label($o['payment_method'])) ?></p>
      <?php if ($o['note']): ?><div class="divider"></div><p class="muted" style="font-size:12px">Not</p><p><?= nl2br(e($o['note'])) ?></p><?php endif; ?>
    </div>
  </aside>
</div>
<?php require_once __DIR__ . '/../core/footer.php'; ?>
