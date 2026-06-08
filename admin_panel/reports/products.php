<?php
$page = 'reports_products'; $title = 'Ürün Performans Raporu';

require_once __DIR__ . '/../core/auth.php';

$from = $_GET['from'] ?? date('Y-m-d', strtotime('-30 days'));
$to   = $_GET['to']   ?? date('Y-m-d');
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $from)) $from = date('Y-m-d', strtotime('-30 days'));
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $to))   $to   = date('Y-m-d');

$paidWhere = "o.status IN ('paid','shipped','delivered')";
$args = [$from . ' 00:00:00', $to . ' 23:59:59'];

if (($_GET['export'] ?? '') === 'csv') {
    $rows = db()->prepare(
        "SELECT oi.product_id, oi.product_name,
                SUM(oi.qty) AS qty, SUM(oi.qty*oi.price) AS revenue,
                COUNT(DISTINCT oi.order_id) AS orders
         FROM order_items oi
         JOIN orders o ON o.id = oi.order_id
         WHERE $paidWhere AND o.created_at BETWEEN ? AND ?
         GROUP BY oi.product_id, oi.product_name
         ORDER BY qty DESC"
    );
    $rows->execute($args);
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="urun-raporu-' . $from . '_' . $to . '.csv"');
    $out = fopen('php://output', 'w');
    fprintf($out, "\xEF\xBB\xBF");
    fputcsv($out, ['Ürün ID','Ürün','Sipariş Sayısı','Toplam Adet','Toplam Ciro']);
    foreach ($rows as $r) fputcsv($out, [
        (int)$r['product_id'], $r['product_name'],
        (int)$r['orders'], (int)$r['qty'],
        number_format((float)$r['revenue'], 2, ',', '.'),
    ]);
    fclose($out);
    exit;
}

require_once __DIR__ . '/../core/header.php';

$rows = db()->prepare(
    "SELECT oi.product_id, oi.product_name,
            SUM(oi.qty)             AS qty,
            SUM(oi.qty * oi.price)  AS revenue,
            COUNT(DISTINCT oi.order_id) AS orders,
            AVG(oi.price)           AS avg_price
     FROM order_items oi
     JOIN orders o ON o.id = oi.order_id
     WHERE $paidWhere AND o.created_at BETWEEN ? AND ?
     GROUP BY oi.product_id, oi.product_name
     ORDER BY qty DESC
     LIMIT 100"
);
$rows->execute($args);
$products = $rows->fetchAll();

$maxQty = 0;
foreach ($products as $p) if ((int)$p['qty'] > $maxQty) $maxQty = (int)$p['qty'];

// Hiç satılmayan ürünler (son 30 gün stokta olup satılmamış)
$dead = db()->query(
    "SELECT p.id, p.name, p.stock, p.price
     FROM products p
     WHERE p.is_active = 1
       AND NOT EXISTS (
         SELECT 1 FROM order_items oi
         JOIN orders o ON o.id = oi.order_id
         WHERE oi.product_id = p.id
           AND o.status IN ('paid','shipped','delivered')
           AND o.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
       )
     ORDER BY p.stock DESC
     LIMIT 20"
)->fetchAll();

$AP = SITE_URL . '/admin_panel';
?>

<style>
.rep-toolbar{display:flex;gap:14px;flex-wrap:wrap;align-items:flex-end;margin-bottom:18px;padding:14px 18px;background:var(--olive-2);border-radius:10px;border:1px solid var(--gold-border)}
.rep-toolbar .field{flex:0 0 auto;margin:0}
.rep-toolbar .field label{display:block;font-size:11px;letter-spacing:.18em;text-transform:uppercase;color:var(--muted-text);margin-bottom:6px}
.rep-toolbar input{padding:8px 12px;border:1px solid var(--gold-border);background:var(--olive-2);color:var(--ink);border-radius:6px}
.bar-bg{display:inline-block;width:100%;height:8px;background:rgba(255,255,255,.06);border-radius:4px;overflow:hidden}
.bar-fg{display:block;height:100%;background:linear-gradient(90deg,#8fa454,#c8b560);border-radius:4px}
</style>

<form method="get" class="rep-toolbar">
  <div class="field"><label>Başlangıç</label><input type="date" name="from" value="<?= e($from) ?>"></div>
  <div class="field"><label>Bitiş</label><input type="date" name="to" value="<?= e($to) ?>"></div>
  <button class="btn btn-primary" type="submit">Filtrele</button>
  <a class="btn btn-secondary" href="?from=<?= e($from) ?>&to=<?= e($to) ?>&export=csv">CSV İndir</a>
</form>

<div class="panel">
  <h3 style="font-size:13px;letter-spacing:.18em;text-transform:uppercase;color:var(--gold);margin:0 0 14px;font-family:Georgia,serif">En Çok Satan Ürünler (<?= e($from) ?> → <?= e($to) ?>)</h3>
  <?php if ($products): ?>
    <table>
      <thead><tr><th>Ürün</th><th>Sipariş</th><th>Adet</th><th>Ort. Fiyat</th><th>Toplam Ciro</th><th style="width:20%">Grafik</th></tr></thead>
      <tbody>
      <?php foreach ($products as $p): $pct = $maxQty > 0 ? ((int)$p['qty'] / $maxQty) * 100 : 0; ?>
        <tr>
          <td><a href="<?= $AP ?>/products/edit.php?id=<?= (int)$p['product_id'] ?>" style="color:inherit;text-decoration:none"><?= e($p['product_name']) ?></a></td>
          <td><?= (int)$p['orders'] ?></td>
          <td><?= (int)$p['qty'] ?></td>
          <td><?= money($p['avg_price']) ?></td>
          <td style="color:var(--gold);font-weight:600"><?= money($p['revenue']) ?></td>
          <td><div class="bar-bg"><div class="bar-fg" style="width:<?= round($pct) ?>%"></div></div></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  <?php else: ?>
    <p class="muted">Bu aralıkta satılan ürün yok.</p>
  <?php endif; ?>
</div>

<div class="panel" style="margin-top:18px">
  <h3 style="font-size:13px;letter-spacing:.18em;text-transform:uppercase;color:var(--gold);margin:0 0 14px;font-family:Georgia,serif">⚠ Son 30 Gün Satılmayan Aktif Ürünler</h3>
  <?php if ($dead): ?>
    <table>
      <thead><tr><th>Ürün</th><th>Stok</th><th>Fiyat</th><th></th></tr></thead>
      <tbody>
      <?php foreach ($dead as $d): ?>
        <tr>
          <td><?= e($d['name']) ?></td>
          <td><?= (int)$d['stock'] ?></td>
          <td><?= money($d['price']) ?></td>
          <td><a class="btn btn-secondary btn-sm" href="<?= $AP ?>/products/edit.php?id=<?= (int)$d['id'] ?>">Düzenle</a></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
    <p class="muted" style="font-size:12px;margin-top:10px">İpucu: bu ürünler için fiyat ayarı, kampanya, anasayfa öne çıkarma veya görsel/açıklama iyileştirmesi değerlendirin.</p>
  <?php else: ?>
    <p class="muted">Tüm aktif ürünler son 30 günde en az bir kez satıldı 🎉</p>
  <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../core/footer.php'; ?>
