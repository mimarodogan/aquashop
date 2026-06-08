<?php
$page='orders'; $title='Siparişler';
require_once __DIR__ . '/../core/auth.php';

/* Toplu işlem POST handler — checkbox + dropdown action */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf_check($_POST['csrf'] ?? null)) {
    $action = $_POST['bulk_action'] ?? '';
    $ids    = array_filter(array_map('intval', $_POST['ids'] ?? []));
    if ($action && $ids) {
        require_once __DIR__ . '/../../includes/order_mailer.php';
        require_once __DIR__ . '/../../includes/stock.php';
        $done = 0;
        $in = implode(',', array_fill(0, count($ids), '?'));
        switch ($action) {
            case 'mark_shipped':
                // tracking_number yoksa atla; varsa shipped + SMS
                $rows = db()->prepare("SELECT id, status, tracking_number FROM orders WHERE id IN ($in)");
                $rows->execute($ids);
                foreach ($rows->fetchAll() as $r) {
                    if (empty($r['tracking_number'])) continue;
                    if (in_array($r['status'], ['shipped','delivered'])) continue;
                    db()->prepare("UPDATE orders SET status='shipped', shipped_at=NOW() WHERE id=?")->execute([$r['id']]);
                    @order_send_shipped_notification((int)$r['id']);
                    $done++;
                }
                flash_set($done?'ok':'err', $done ? "{$done} sipariş kargoya verildi (SMS gönderildi)." : 'Hiçbir sipariş güncellenmedi (takip no eksik veya zaten kargoda).');
                break;

            case 'mark_delivered':
                $rows = db()->prepare("SELECT id, status FROM orders WHERE id IN ($in) AND status='shipped'");
                $rows->execute($ids);
                foreach ($rows->fetchAll() as $r) {
                    db()->prepare("UPDATE orders SET status='delivered', delivered_at=NOW() WHERE id=?")->execute([$r['id']]);
                    @order_send_delivered_notification((int)$r['id']);
                    // Sadakat puanı ödülü
                    if (function_exists('loyalty_award_for_order')) @loyalty_award_for_order((int)$r['id']);
                    $done++;
                }
                flash_set($done?'ok':'err', $done ? "{$done} sipariş teslim edildi olarak işaretlendi (puan ödüllendirildi)." : 'Sadece kargodaki siparişler teslim edilebilir.');
                break;

            case 'mark_cancelled':
                $rows = db()->prepare("SELECT id, status FROM orders WHERE id IN ($in) AND status IN ('pending','paid')");
                $rows->execute($ids);
                foreach ($rows->fetchAll() as $r) {
                    db()->prepare("UPDATE orders SET status='cancelled', cancelled_at=NOW(), cancellation_reason='Toplu iptal (admin)' WHERE id=?")->execute([$r['id']]);
                    // Stok iadesi
                    if (function_exists('stock_revert_order')) @stock_revert_order((int)$r['id'], 'cancelled');
                    // Puanı geri al
                    if (function_exists('loyalty_revoke_for_order')) @loyalty_revoke_for_order((int)$r['id']);
                    $done++;
                }
                flash_set($done?'ok':'err', $done ? "{$done} sipariş iptal edildi (stok ve puan iade edildi)." : 'Sadece bekleyen/ödenen siparişler iptal edilebilir.');
                break;

            case 'resend_confirmation':
                foreach ($ids as $oid) {
                    @order_send_confirmation((int)$oid);
                    $done++;
                }
                flash_set('ok', "{$done} siparişin onay e-postası yeniden gönderildi.");
                break;

            default:
                flash_set('err', 'Bilinmeyen toplu işlem.');
        }
    }
    redirect('list.php' . (isset($_GET['status']) ? '?status=' . urlencode($_GET['status']) : ''));
}

require_once __DIR__ . '/../core/header.php';

$status = $_GET['status'] ?? '';
$where=''; $args=[];
if ($status){ $where='WHERE status=?'; $args[]=$status; }
$rows = db()->prepare("SELECT * FROM orders $where ORDER BY created_at DESC");
$rows->execute($args); $rows=$rows->fetchAll();
?>
<form method="post" id="bulk-form">
<input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">

<div class="panel">
  <div class="toolbar" style="display:flex;justify-content:space-between;align-items:center;gap:14px;flex-wrap:wrap;margin-bottom:14px">
    <div style="display:flex;gap:8px;flex-wrap:wrap">
      <a class="btn btn-secondary btn-sm" href="list.php">Tümü</a>
      <?php foreach (status_options() as $s=>$lbl): ?>
        <a class="btn btn-secondary btn-sm" href="?status=<?= e($s) ?>" style="<?= $status===$s?'background:rgba(201,162,75,.1);color:var(--gold)':'' ?>"><?= e($lbl) ?></a>
      <?php endforeach; ?>
    </div>
    <div style="display:flex;gap:8px;align-items:center" id="bulk-bar" hidden>
      <span style="font-size:12px;color:var(--muted-text)"><span id="bulk-count">0</span> seçili</span>
      <select name="bulk_action" required style="padding:8px 12px;border:1px solid var(--gold-border);background:var(--olive-2);color:var(--ink);border-radius:6px">
        <option value="">İşlem seç…</option>
        <option value="mark_shipped">Kargoya ver (takip no gerekli, SMS gönderir)</option>
        <option value="mark_delivered">Teslim edildi olarak işaretle (SMS)</option>
        <option value="mark_cancelled">İptal et (stok iade)</option>
        <option value="resend_confirmation">Sipariş onay e-postasını tekrar gönder</option>
      </select>
      <button class="btn btn-primary btn-sm" type="submit" onclick="return confirm('Seçili siparişlere bu işlem uygulanacak. Devam edilsin mi?')">Uygula</button>
    </div>
  </div>

  <table>
    <thead><tr>
      <th style="width:36px"><input type="checkbox" id="bulk-all" aria-label="Tümünü seç"></th>
      <th>No</th><th>Müşteri</th><th>Şehir</th><th>Tarih</th><th>Toplam</th><th>Yöntem</th><th>Ödeme</th><th>Durum</th><th></th>
    </tr></thead>
    <tbody>
    <?php
      $payLabels = ['pending'=>'Bekliyor','paid'=>'Ödendi','failed'=>'Başarısız','refunded'=>'İade edildi','partial_refund'=>'Kısmi iade'];
      $payClass  = ['pending'=>'pending','paid'=>'paid','failed'=>'cancelled','refunded'=>'cancelled','partial_refund'=>'shipped'];
      $methodLabels = ['kart'=>'Kart','havale'=>'Havale/EFT','kapida'=>'Kapıda'];
    ?>
    <?php foreach ($rows as $o): ?>
      <?php $ps = $o['payment_status'] ?? 'pending'; ?>
      <tr>
        <td><input type="checkbox" name="ids[]" value="<?= (int)$o['id'] ?>" class="bulk-cb" aria-label="Seç #<?= (int)$o['id'] ?>"></td>
        <td>#<?= (int)$o['id'] ?></td>
        <td>
          <?php if (!empty($o['source']) && $o['source'] === 'pos'): ?>
            <span style="display:inline-block;padding:1px 7px;background:rgba(39,174,96,.12);color:#1e8449;border-radius:10px;font-size:10px;font-weight:700;margin-bottom:3px">🏪 Mağaza</span><br>
          <?php endif; ?>
          <?= e($o['full_name']) ?><br><span class="muted" style="font-size:12px"><?= e($o['email']) ?></span>
        </td>
        <td><?= e($o['city']) ?></td>
        <td><?= e($o['created_at']) ?></td>
        <td style="color:var(--ink);font-weight:600"><?= money($o['total']) ?></td>
        <td><?= e($methodLabels[$o['payment_method']] ?? $o['payment_method']) ?></td>
        <td><span class="status <?= e($payClass[$ps] ?? 'pending') ?>"><?= e($payLabels[$ps] ?? $ps) ?></span></td>
        <td><span class="status <?= e($o['status']) ?>"><?= e(status_label($o['status'])) ?></span></td>
        <td><a class="btn btn-secondary btn-sm" href="view.php?id=<?= (int)$o['id'] ?>">Görüntüle</a></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</div>
</form>

<script>
(function () {
  var allCb  = document.getElementById('bulk-all');
  var rowCbs = document.querySelectorAll('.bulk-cb');
  var bar    = document.getElementById('bulk-bar');
  var count  = document.getElementById('bulk-count');

  function refresh() {
    var n = 0;
    rowCbs.forEach(function (c) { if (c.checked) n++; });
    count.textContent = n;
    bar.hidden = n === 0;
    if (allCb) allCb.checked = (n === rowCbs.length && n > 0);
  }

  if (allCb) allCb.addEventListener('change', function () {
    rowCbs.forEach(function (c) { c.checked = allCb.checked; });
    refresh();
  });
  rowCbs.forEach(function (c) { c.addEventListener('change', refresh); });
})();
</script>

<?php require_once __DIR__ . '/../core/footer.php'; ?>
