<?php
require_once __DIR__ . '/../includes/functions.php';
$page = ''; $title = 'İade Talebi';
$user = current_user();
if (!$user) redirect(url('login') . '?redirect=' . urlencode($_SERVER['REQUEST_URI'] ?? '/'));

$orderId = (int)($_GET['order'] ?? 0);
$err = null; $ok = false;

// Sipariş kullanıcıya ait mi — Y-9: email match sadece guest order için
$st = db()->prepare('SELECT * FROM orders WHERE id=? AND (user_id=? OR (user_id IS NULL AND email=?))');
$st->execute([$orderId, $user['id'], $user['email']]);
$order = $st->fetch();
if (!$order) {
    flash_set('err','Sipariş bulunamadı veya size ait değil.');
    redirect(url('account'));
}

// 30 gün içinde mi
$daysAgo = (time() - strtotime($order['created_at'])) / 86400;
if ($daysAgo > 30) {
    flash_set('err','Bu sipariş 30 günden eski, iade talebi oluşturulamaz.');
    redirect(url('account'));
}

// Sipariş kalemleri
$it = db()->prepare('SELECT * FROM order_items WHERE order_id=?');
$it->execute([$orderId]);
$orderItems = $it->fetchAll();

// Bu sipariş için açık iade talebi var mı
$existing = db()->prepare('SELECT * FROM return_requests WHERE order_id=? ORDER BY id DESC LIMIT 1');
$existing->execute([$orderId]);
$existingReturn = $existing->fetch();

if ($_SERVER['REQUEST_METHOD']==='POST' && csrf_check($_POST['csrf'] ?? null)) {
    if ($existingReturn && in_array($existingReturn['status'], ['pending','approved','processing'], true)) {
        $err = 'Bu sipariş için zaten açık bir iade talebi mevcut.';
    } else {
        $reason = trim($_POST['reason'] ?? '');
        $desc   = trim($_POST['description'] ?? '');
        $selectedItems = $_POST['items'] ?? [];
        if (!$reason)              $err = 'İade nedeni seçilmelidir.';
        elseif (!$selectedItems)   $err = 'En az bir ürün seçmelisiniz.';
        elseif (mb_strlen($desc) < 10) $err = 'Lütfen iade nedenini en az 10 karakter olarak detaylandırın.';
        else {
            try {
                // Y-8 IDOR koruması: gönderilen order_item_id'lerin BU siparişe ait olduğunu doğrula
                $validIdsToQty = [];   // [order_item_id => max_qty]
                foreach ($orderItems as $oi) {
                    $validIdsToQty[(int)$oi['id']] = (int)$oi['qty'];
                }
                db()->beginTransaction();
                $ins = db()->prepare('INSERT INTO return_requests (order_id, user_id, reason, description) VALUES (?,?,?,?)');
                $ins->execute([$orderId, $user['id'], $reason, $desc]);
                $rid = (int)db()->lastInsertId();
                $insIt = db()->prepare('INSERT INTO return_items (return_id, order_item_id, qty) VALUES (?,?,?)');
                foreach ($selectedItems as $oiId => $qty) {
                    $oiId = (int)$oiId; $qty = (int)$qty;
                    // Yalnız bu siparişe ait order_item_id'leri kabul et + qty üst sınırı
                    if ($oiId <= 0 || $qty <= 0) continue;
                    if (!isset($validIdsToQty[$oiId])) continue;       // başka siparişin item'ı → atla
                    if ($qty > $validIdsToQty[$oiId]) $qty = $validIdsToQty[$oiId];
                    $insIt->execute([$rid, $oiId, $qty]);
                }
                db()->commit();

                // Admin'e bilgilendirme maili
                try {
                    require_once __DIR__ . '/../includes/mailer.php';
                    $to = trim((string)setting('contact_email',''));
                    if ($to) {
                        $body  = '<p>Yeni iade talebi alındı:</p>';
                        $body .= '<p><strong>Sipariş:</strong> #' . $orderId . '<br>';
                        $body .= '<strong>Müşteri:</strong> ' . e($user['name']) . ' (' . e($user['email']) . ')<br>';
                        $body .= '<strong>Neden:</strong> ' . e($reason) . '</p>';
                        $body .= '<p style="background:#F4F4F4;padding:12px;border-radius:6px">' . nl2br(e($desc)) . '</p>';
                        $base = trim((string)setting('site_url','')) ?: ('https://' . ($_SERVER['HTTP_HOST'] ?? 'localhost'));
                        $url = rtrim($base,'/') . '/admin_panel/orders/view.php?id=' . $orderId;
                        $html = mail_template('Yeni İade Talebi', $body, 'Siparişi İncele', $url);
                        @mail_send($to, 'İade Talebi #' . $rid . ' · Sipariş #' . $orderId, $html);
                    }
                } catch (\Throwable $e) {}

                $ok = true;
            } catch (\Throwable $e) {
                if (db()->inTransaction()) db()->rollBack();
                $err = 'İade talebi oluşturulamadı.';
            }
        }
    }
}

include __DIR__ . '/../includes/header.php';
?>
<section class="page-header">
  <div class="container">
    <span class="kicker">Hesap</span>
    <h1 style="margin-top:10px">İade Talebi</h1>
    <div class="breadcrumb"><a href="<?= url('home') ?>">Anasayfa</a><span>/</span><a href="<?= url('account') ?>">Hesabım</a><span>/</span>İade #<?= (int)$orderId ?></div>
  </div>
</section>

<section><div class="container" style="max-width:760px">
  <?php if ($ok): ?>
    <div class="panel" style="text-align:center">
      <div class="alert alert-ok" role="status">İade talebiniz alındı. Tarafımıza ulaşan ürünün incelenmesi sonrası 14 gün içinde iade işlemi gerçekleştirilir.</div>
      <p class="muted" style="margin:18px 0 24px">İade talebinizin durumunu Hesabım → Siparişlerim'den takip edebilirsiniz.</p>
      <div class="btn-row" style="justify-content:center">
        <a class="btn btn-primary" href="<?= url('account') ?>">Hesabıma Dön</a>
      </div>
    </div>
  <?php elseif ($existingReturn && in_array($existingReturn['status'], ['pending','approved','processing'], true)): ?>
    <div class="panel">
      <div class="alert alert-err">Bu sipariş için açık bir iade talebiniz bulunuyor.</div>
      <p><strong>Talep #<?= (int)$existingReturn['id'] ?></strong> · <?= e(['pending'=>'Beklemede','approved'=>'Onaylandı','processing'=>'İşleniyor'][$existingReturn['status']] ?? $existingReturn['status']) ?></p>
      <p class="muted" style="font-size:13px;margin-top:8px">Oluşturulma: <?= e(date('d.m.Y H:i', strtotime($existingReturn['created_at']))) ?></p>
      <p style="margin-top:14px"><strong>Neden:</strong> <?= e($existingReturn['reason']) ?></p>
      <p style="margin-top:8px;color:var(--muted-text)"><?= nl2br(e($existingReturn['description'])) ?></p>
      <div style="margin-top:18px"><a class="btn btn-secondary" href="<?= url('account') ?>">Hesabıma Dön</a></div>
    </div>
  <?php else: ?>
    <div class="panel">
      <h3 style="margin-bottom:8px">Sipariş #<?= (int)$orderId ?></h3>
      <p class="muted" style="margin-bottom:18px;font-size:13px">Sipariş Tarihi: <?= e(date('d.m.Y', strtotime($order['created_at']))) ?> · Toplam: <?= money($order['total']) ?></p>
      <?php if ($err): ?><div class="alert alert-err"><?= e($err) ?></div><?php endif; ?>

      <form method="post" style="display:grid;gap:16px">
        <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">

        <fieldset style="border:1px solid var(--gold-border);border-radius:var(--radius);padding:14px">
          <legend style="font-size:11px;letter-spacing:.22em;text-transform:uppercase;color:var(--ink);font-weight:600;padding:0 8px">İade edilecek ürünler</legend>
          <?php foreach ($orderItems as $oi): ?>
            <div style="display:flex;align-items:center;gap:12px;padding:10px 0;border-bottom:1px solid var(--gold-border)">
              <input type="number" name="items[<?= (int)$oi['id'] ?>]" min="0" max="<?= (int)$oi['qty'] ?>" value="0" style="width:64px;padding:8px;text-align:center">
              <div style="flex:1">
                <div style="font-weight:500"><?= e($oi['product_name']) ?></div>
                <div class="muted" style="font-size:13px">Sipariş: <?= (int)$oi['qty'] ?> adet · Birim: <?= money($oi['price']) ?></div>
              </div>
            </div>
          <?php endforeach; ?>
          <small class="muted" style="display:block;margin-top:10px">İade etmek istediğiniz miktarı yazın. Sıfır = iade etmiyorum.</small>
        </fieldset>

        <div class="field">
          <label>İade Nedeni <span class="req" aria-hidden="true">*</span></label>
          <select name="reason" required>
            <option value="">— Seçin —</option>
            <option>Ürün hasarlı / kırık geldi</option>
            <option>Yanlış ürün gönderildi</option>
            <option>Ürün açıklamaya uymuyor</option>
            <option>Beklediğim gibi değil</option>
            <option>Cayma hakkı (14 gün içinde)</option>
            <option>Beden / boyut uymadı</option>
            <option>Diğer</option>
          </select>
        </div>

        <div class="field">
          <label>Açıklama <span class="req" aria-hidden="true">*</span></label>
          <textarea name="description" rows="4" required minlength="10" placeholder="İade sebebini detaylandırın. Hasarlıysa fotoğraf çekip bizimle iletişim kurabilirsiniz."></textarea>
        </div>

        <div style="padding:14px;border-left:3px solid var(--gold);background:var(--cream);font-size:13px;line-height:1.6;border-radius:0 var(--radius) var(--radius) 0">
          <strong>Önemli:</strong> İade onaylandıktan sonra ürünleri orijinal ambalajıyla bize göndermeniz gerekir. <a href="<?= url('page', ['slug'=>'iade-degisim']) ?>" target="_blank" style="color:var(--leaf)">İade & Değişim Koşulları</a>'nı okuyunuz.
        </div>

        <div class="btn-row">
          <button class="btn btn-primary">İade Talebini Gönder</button>
          <a class="btn btn-secondary" href="<?= url('account') ?>">Vazgeç</a>
        </div>
      </form>
    </div>
  <?php endif; ?>
</div></section>

<?php include __DIR__ . '/../includes/footer.php'; ?>
