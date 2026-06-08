<?php
$page = 'reports_coupons'; $title = 'Kupon Performans Raporu';

require_once __DIR__ . '/../core/auth.php';

$from = $_GET['from'] ?? date('Y-m-d', strtotime('-90 days'));
$to   = $_GET['to']   ?? date('Y-m-d');
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $from)) $from = date('Y-m-d', strtotime('-90 days'));
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $to))   $to   = date('Y-m-d');

$paidWhere = "status IN ('paid','shipped','delivered')";
$args = [$from . ' 00:00:00', $to . ' 23:59:59'];

if (($_GET['export'] ?? '') === 'csv') {
    $rows = db()->prepare(
        "SELECT coupon_code,
                COUNT(*) AS uses,
                COUNT(DISTINCT user_id) AS unique_users,
                SUM(total) AS revenue,
                SUM(discount_amount) AS discount,
                AVG(total) AS avg_order
         FROM orders
         WHERE $paidWhere AND coupon_code IS NOT NULL AND coupon_code <> ''
           AND created_at BETWEEN ? AND ?
         GROUP BY coupon_code
         ORDER BY uses DESC"
    );
    $rows->execute($args);
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="kupon-raporu-' . $from . '_' . $to . '.csv"');
    $out = fopen('php://output', 'w');
    fprintf($out, "\xEF\xBB\xBF");
    fputcsv($out, ['Kupon','Kullanım','Tekil Kullanıcı','Toplam Ciro','Toplam İndirim','Net Ciro','Ort. Sipariş']);
    foreach ($rows as $r) {
        $net = (float)$r['revenue'] - (float)$r['discount'];
        fputcsv($out, [
            $r['coupon_code'], (int)$r['uses'], (int)$r['unique_users'],
            number_format((float)$r['revenue'], 2, ',', '.'),
            number_format((float)$r['discount'], 2, ',', '.'),
            number_format($net, 2, ',', '.'),
            number_format((float)$r['avg_order'], 2, ',', '.'),
        ]);
    }
    fclose($out);
    exit;
}

require_once __DIR__ . '/../core/header.php';

$rows = db()->prepare(
    "SELECT coupon_code,
            COUNT(*) AS uses,
            COUNT(DISTINCT COALESCE(user_id, email)) AS unique_users,
            SUM(total)           AS revenue,
            SUM(discount_amount) AS discount,
            AVG(total)           AS avg_order,
            MIN(created_at)      AS first_used,
            MAX(created_at)      AS last_used
     FROM orders
     WHERE $paidWhere AND coupon_code IS NOT NULL AND coupon_code <> ''
       AND created_at BETWEEN ? AND ?
     GROUP BY coupon_code
     ORDER BY uses DESC"
);
$rows->execute($args);
$rows = $rows->fetchAll();

// Toplam özet
$totalUses    = array_sum(array_column($rows, 'uses'));
$totalRev     = array_sum(array_column($rows, 'revenue'));
$totalDiscount= array_sum(array_column($rows, 'discount'));
$totalNet     = $totalRev - $totalDiscount;

// En çok kullanılan ne kadar — bar grafik için
$maxUses = 0;
foreach ($rows as $r) if ((int)$r['uses'] > $maxUses) $maxUses = (int)$r['uses'];

$AP = SITE_URL . '/admin_panel';
?>

<style>
.rep-toolbar{display:flex;gap:14px;flex-wrap:wrap;align-items:flex-end;margin-bottom:18px;padding:14px 18px;background:var(--olive-2);border-radius:10px;border:1px solid var(--gold-border)}
.rep-toolbar .field{margin:0}
.rep-toolbar .field label{display:block;font-size:11px;letter-spacing:.18em;text-transform:uppercase;color:var(--muted-text);margin-bottom:6px}
.rep-toolbar input{padding:8px 12px;border:1px solid var(--gold-border);background:var(--olive-2);color:var(--ink);border-radius:6px}
.rep-summary{display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:12px;margin-bottom:18px}
.bar-bg{display:inline-block;width:100%;height:8px;background:rgba(255,255,255,.06);border-radius:4px;overflow:hidden}
.bar-fg{display:block;height:100%;background:linear-gradient(90deg,#8fa454,#c8b560);border-radius:4px}
</style>

<form method="get" class="rep-toolbar">
  <div class="field"><label>Başlangıç</label><input type="date" name="from" value="<?= e($from) ?>"></div>
  <div class="field"><label>Bitiş</label><input type="date" name="to" value="<?= e($to) ?>"></div>
  <button class="btn btn-primary" type="submit">Filtrele</button>
  <a class="btn btn-secondary" href="?from=<?= e($from) ?>&to=<?= e($to) ?>&export=csv">CSV İndir</a>
  <a class="btn btn-secondary" href="<?= $AP ?>/coupons.php" style="margin-left:auto">Kuponları Yönet →</a>
</form>

<div class="rep-summary">
  <div class="kpi-card"><div class="lbl">Toplam Kupon Kullanımı</div><div class="val"><?= $totalUses ?></div></div>
  <div class="kpi-card"><div class="lbl">Getirilen Ciro</div><div class="val"><?= money($totalRev) ?></div></div>
  <div class="kpi-card"><div class="lbl">Verilen İndirim</div><div class="val" style="color:#e4a3a3"><?= money($totalDiscount) ?></div></div>
  <div class="kpi-card"><div class="lbl">Net Etki</div><div class="val"><?= money($totalNet) ?></div></div>
</div>

<div class="panel">
  <h3 style="font-size:13px;letter-spacing:.18em;text-transform:uppercase;color:var(--gold);margin:0 0 14px;font-family:Georgia,serif">Kupon Detayları (<?= e($from) ?> → <?= e($to) ?>)</h3>
  <?php if ($rows): ?>
    <table>
      <thead><tr>
        <th>Kupon</th><th>Kullanım</th><th>Tekil Müşteri</th>
        <th>Ciro</th><th>İndirim</th><th>Net</th><th>Ort. Sipariş</th><th style="width:18%">Kullanım Grafiği</th>
      </tr></thead>
      <tbody>
      <?php foreach ($rows as $r): $pct = $maxUses > 0 ? ((int)$r['uses'] / $maxUses) * 100 : 0; $net = (float)$r['revenue'] - (float)$r['discount']; ?>
        <tr>
          <td><strong style="color:var(--champagne)"><?= e($r['coupon_code']) ?></strong></td>
          <td><?= (int)$r['uses'] ?></td>
          <td><?= (int)$r['unique_users'] ?></td>
          <td style="color:var(--gold);font-weight:600"><?= money($r['revenue']) ?></td>
          <td style="color:#e4a3a3"><?= money($r['discount']) ?></td>
          <td style="color:var(--gold);font-weight:600"><?= money($net) ?></td>
          <td><?= money($r['avg_order']) ?></td>
          <td><div class="bar-bg"><div class="bar-fg" style="width:<?= round($pct) ?>%"></div></div></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
    <p class="muted" style="font-size:12px;margin-top:10px">
      <strong>İpucu:</strong> "Tekil Müşteri" sayısı "Kullanım"a yakınsa kupon yeni müşteri çekiyor demektir. Çok düşükse aynı kişiler kullanıyor — yeni segment bul.
    </p>
  <?php else: ?>
    <p class="muted">Seçilen aralıkta kupon kullanan onaylı sipariş yok.</p>
  <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../core/footer.php'; ?>
