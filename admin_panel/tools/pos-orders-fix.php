<?php
/**
 * Geçmiş POS siparişlerini düzelt (tek seferlik araç).
 *
 * Mağaza satışında müşteri ürünü yerinde teslim alır; kargo olmaz.
 * Bu yüzden tüm POS siparişlerinin nihai durumu 'delivered' olmalı.
 *
 * Bu araç şunları yapar:
 *  1. source='pos' veya address='Mağaza satışı' olan siparişleri bulur
 *  2. status='delivered', payment_status='paid', shipping_amount=0 olarak günceller
 *  3. Stok daha önce düşülmemişse stock_apply_order() çağırır
 *
 * Güvenli (idempotent): iki kez çalıştırılsa ikincisinde "Düzeltilecek sipariş yok" der.
 */
require_once __DIR__ . '/../../core/bootstrap.php';
require_once APP_ROOT . '/includes/schema_guard.php';
require_once APP_ROOT . '/includes/stock.php';

$admin = current_user();
if (!$admin || $admin['role'] !== 'admin') {
    http_response_code(403); exit('Yetkisiz');
}
admin_ensure_runtime_schema();

$AP    = rtrim(setting('admin_prefix', '/admin_panel'), '/');
$run   = ($_POST['run'] ?? '') === '1' && csrf_check($_POST['csrf'] ?? '');
$errors = [];
$fixed  = [];

// --- KDV oranı (settings tablosundan; default %20) ---
$vatRate    = (float)setting('vat_rate', '20');
$vatFormula = $vatRate > 0 ? "ROUND(total * {$vatRate} / (100 + {$vatRate}), 2)" : "0";
$netFormula = $vatRate > 0 ? "ROUND(total * 100 / (100 + {$vatRate}), 2)"        : "total";

// --- Kolonların var olduğundan emin ol ---
try {
    db()->exec("ALTER TABLE orders ADD COLUMN IF NOT EXISTS source ENUM('web','pos') NOT NULL DEFAULT 'web'");
    db()->exec("ALTER TABLE orders ADD COLUMN IF NOT EXISTS payment_status ENUM('pending','paid','failed','refunded','partial_refund') NOT NULL DEFAULT 'pending'");
    db()->exec("ALTER TABLE orders ADD COLUMN IF NOT EXISTS paid_at DATETIME NULL");
    db()->exec("ALTER TABLE orders ADD COLUMN IF NOT EXISTS shipping_amount DECIMAL(10,2) NOT NULL DEFAULT 0");
    db()->exec("ALTER TABLE orders ADD COLUMN IF NOT EXISTS subtotal DECIMAL(10,2) NOT NULL DEFAULT 0");
    db()->exec("ALTER TABLE orders ADD COLUMN IF NOT EXISTS vat_amount DECIMAL(10,2) NOT NULL DEFAULT 0");
    db()->exec("ALTER TABLE order_items ADD COLUMN IF NOT EXISTS stock_applied_at DATETIME DEFAULT NULL");
} catch (\Throwable $e) {}

// --- Düzeltilecek siparişler: delivered/cancelled OLMAYAN tüm POS siparişleri ---
$affected = [];
try {
    $affected = db()->query(
        "SELECT id, full_name, total, status, payment_status, shipping_amount, created_at
         FROM orders
         WHERE (source = 'pos' OR address = 'Mağaza satışı')
           AND status NOT IN ('delivered', 'cancelled')
         ORDER BY id ASC"
    )->fetchAll();
} catch (\Throwable $e) {
    $errors[] = 'Sorgulama hatası: ' . $e->getMessage();
}

// --- Zaten düzgün olanlar (bilgi) ---
$alreadyOk = [];
try {
    $alreadyOk = db()->query(
        "SELECT id, full_name, total, status, payment_status, shipping_amount, created_at
         FROM orders
         WHERE (source = 'pos' OR address = 'Mağaza satışı')
           AND status = 'delivered'
         ORDER BY id DESC LIMIT 50"
    )->fetchAll();
} catch (\Throwable $e) {}

if ($run && empty($errors)) {
    foreach ($affected as $ord) {
        $oid = (int)$ord['id'];
        try {
            db()->prepare(
                "UPDATE orders
                 SET status          = 'delivered',
                     payment_status  = 'paid',
                     paid_at         = COALESCE(paid_at, created_at),
                     shipping_amount = 0.00,
                     vat_amount      = {$vatFormula},
                     subtotal        = {$netFormula},
                     source          = 'pos'
                 WHERE id = ?"
            )->execute([$oid]);

            // Stok henüz düşülmemişse düş (idempotent: stock_applied_at kontrolü içerde)
            stock_apply_order($oid, 'pos_retrofix');

            $fixed[] = $oid;
        } catch (\Throwable $e) {
            $errors[] = '#' . $oid . ': ' . $e->getMessage();
        }
    }
    if (empty($errors)) {
        header('Location: ' . $_SERVER['REQUEST_URI'] . '?done=' . count($fixed));
        exit;
    }
}

$done = isset($_GET['done']) ? (int)$_GET['done'] : null;
?>
<!DOCTYPE html>
<html lang="tr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>POS Sipariş Düzeltme — Admin</title>
<style>
  body{font-family:system-ui,sans-serif;margin:0;padding:24px;background:#f5f5f5;color:#333}
  h1{margin-top:0;font-size:20px}
  .card{background:#fff;border-radius:8px;padding:20px;margin-bottom:16px;box-shadow:0 1px 4px rgba(0,0,0,.08)}
  .alert{padding:12px 16px;border-radius:6px;margin-bottom:16px}
  .alert-ok{background:#d4edda;color:#155724}
  .alert-err{background:#f8d7da;color:#721c24}
  table{width:100%;border-collapse:collapse;font-size:13px}
  th,td{text-align:left;padding:8px 10px;border-bottom:1px solid #eee}
  th{background:#f9f9f9;font-weight:600}
  .badge{display:inline-block;padding:2px 8px;border-radius:10px;font-size:11px;font-weight:600}
  .badge-red{background:#fce4e4;color:#c0392b}
  .badge-green{background:#d4edda;color:#155724}
  .badge-blue{background:#d0eaff;color:#0055aa}
  .btn{display:inline-block;padding:10px 20px;border:none;border-radius:6px;cursor:pointer;font-size:14px;font-weight:600}
  .btn-primary{background:#2196f3;color:#fff}
  .btn-primary:hover{background:#1976d2}
  .btn-back{background:#eee;color:#333;text-decoration:none;margin-left:8px}
  .muted{color:#888;font-size:12px}
</style>
</head>
<body>

<h1>🔧 POS Sipariş Düzeltme Aracı</h1>
<p class="muted" style="margin-top:-8px">
  Mağaza satışlarında müşteri ürünü yerinde teslim alır. Tüm POS siparişleri
  <strong>Teslim Edildi</strong> + kargo=0 olmalı.
</p>

<?php if ($done !== null): ?>
<div class="alert alert-ok">✅ <?= $done ?> sipariş <strong>Teslim Edildi</strong> olarak güncellendi. Stok hareketleri uygulandı.</div>
<?php endif; ?>

<?php foreach ($errors as $err): ?>
<div class="alert alert-err">⚠️ <?= htmlspecialchars($err) ?></div>
<?php endforeach; ?>

<!-- Düzeltilecekler -->
<div class="card">
  <h2 style="font-size:16px;margin-top:0">
    Düzeltilecek POS Siparişleri
    <span class="badge <?= count($affected) > 0 ? 'badge-red' : 'badge-green' ?>">
      <?= count($affected) ?> adet
    </span>
  </h2>

  <?php if (empty($affected)): ?>
    <p style="color:#27ae60;margin:0">✅ Düzeltilecek sipariş yok — tüm POS siparişleri doğru durumda.</p>
  <?php else: ?>
    <p class="muted" style="margin-top:0">
      Aşağıdakiler <strong>Teslim Edildi</strong> yapılacak, kargo=0 atanacak,
      stok henüz düşülmemişse düşülecek.
    </p>
    <table>
      <thead>
        <tr><th>#</th><th>Müşteri</th><th>Toplam</th><th>Mevcut Durum</th><th>Kargo</th><th>Tarih</th></tr>
      </thead>
      <tbody>
        <?php foreach ($affected as $ord): ?>
        <tr>
          <td>#<?= (int)$ord['id'] ?></td>
          <td><?= htmlspecialchars($ord['full_name']) ?></td>
          <td><?= money((float)$ord['total']) ?></td>
          <td>
            <span class="badge badge-red"><?= htmlspecialchars($ord['status'] ?: '(boş)') ?></span>
            <span class="muted">/ <?= htmlspecialchars($ord['payment_status']) ?></span>
          </td>
          <td><?= money((float)$ord['shipping_amount']) ?></td>
          <td class="muted"><?= htmlspecialchars($ord['created_at']) ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>

    <form method="POST" style="margin-top:16px"
          onsubmit="return confirm('<?= count($affected) ?> POS siparişi Teslim Edildi yapılacak. Devam edilsin mi?')">
      <input type="hidden" name="run" value="1">
      <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
      <button type="submit" class="btn btn-primary">
        ✅ <?= count($affected) ?> Siparişi Teslim Edildi Yap &amp; Stok Uygula
      </button>
      <a href="<?= $AP ?>/orders/list.php" class="btn btn-back">← Siparişler</a>
    </form>
  <?php endif; ?>
</div>

<!-- Zaten düzgün olanlar -->
<?php if ($alreadyOk): ?>
<div class="card">
  <h2 style="font-size:16px;margin-top:0">
    Zaten Doğru Durumda (Teslim Edildi)
    <span class="badge badge-green"><?= count($alreadyOk) ?> adet</span>
  </h2>
  <table>
    <thead><tr><th>#</th><th>Müşteri</th><th>Toplam</th><th>Durum</th><th>Kargo</th><th>Tarih</th></tr></thead>
    <tbody>
      <?php foreach ($alreadyOk as $ord): ?>
      <tr>
        <td>#<?= (int)$ord['id'] ?></td>
        <td><?= htmlspecialchars($ord['full_name']) ?></td>
        <td><?= money((float)$ord['total']) ?></td>
        <td><span class="badge badge-blue">Teslim Edildi</span></td>
        <td><?= money((float)$ord['shipping_amount']) ?></td>
        <td class="muted"><?= htmlspecialchars($ord['created_at']) ?></td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>
<?php endif; ?>

<p><a href="<?= $AP ?>/tools/migrations.php">← Araçlara dön</a></p>

</body>
</html>
