<?php
$page = 'reports_sales'; $title = 'Satış Raporu';

// Auth & header
require_once __DIR__ . '/../core/auth.php';

// Tarih aralığı (varsayılan: son 30 gün)
$from = $_GET['from'] ?? date('Y-m-d', strtotime('-30 days'));
$to   = $_GET['to']   ?? date('Y-m-d');

// Validate (basit format kontrolü)
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $from)) $from = date('Y-m-d', strtotime('-30 days'));
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $to))   $to   = date('Y-m-d');

$paidWhere = "status IN ('paid','shipped','delivered')";
$args = [$from . ' 00:00:00', $to . ' 23:59:59'];

// KDV oranı (settings tablosundan; default %20)
// vat_amount=0 olan siparişler (POS + eski online) için totale göre KDV geri hesaplanır.
$vatRate = (float)setting('vat_rate', '20');
$kdvExpr = $vatRate > 0
    ? "SUM(CASE WHEN COALESCE(vat_amount,0)>0 THEN vat_amount ELSE ROUND(total*{$vatRate}/(100+{$vatRate}),2) END)"
    : "SUM(COALESCE(vat_amount,0))";

// CSV export
if (($_GET['export'] ?? '') === 'csv') {
    $rows = db()->prepare(
        "SELECT DATE(created_at) AS gun,
                COUNT(*)              AS sip,
                SUM(total)            AS ciro,
                {$kdvExpr}            AS kdv,
                SUM(shipping_amount)  AS kargo,
                SUM(discount_amount)  AS indirim
         FROM orders
         WHERE $paidWhere AND created_at BETWEEN ? AND ?
         GROUP BY DATE(created_at)
         ORDER BY gun ASC"
    );
    $rows->execute($args);
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="satis-raporu-' . $from . '_' . $to . '.csv"');
    $out = fopen('php://output', 'w');
    fprintf($out, "\xEF\xBB\xBF"); // BOM (Excel TR uyumu)
    fputcsv($out, ['Tarih','Sipariş','Ciro (₺)','KDV (₺)','Kargo (₺)','İndirim (₺)','AOV (₺)']);
    foreach ($rows as $r) {
        $aov = (int)$r['sip'] > 0 ? (float)$r['ciro'] / (int)$r['sip'] : 0;
        fputcsv($out, [
            $r['gun'],
            (int)$r['sip'],
            number_format((float)$r['ciro'], 2, ',', '.'),
            number_format((float)$r['kdv'], 2, ',', '.'),
            number_format((float)$r['kargo'], 2, ',', '.'),
            number_format((float)$r['indirim'], 2, ',', '.'),
            number_format($aov, 2, ',', '.'),
        ]);
    }
    fclose($out);
    exit;
}

require_once __DIR__ . '/../core/header.php';

// Günlük breakdown
$daily = db()->prepare(
    "SELECT DATE(created_at) AS gun,
            COUNT(*)              AS sip,
            SUM(total)            AS ciro,
            {$kdvExpr}            AS kdv,
            SUM(shipping_amount)  AS kargo,
            SUM(discount_amount)  AS indirim
     FROM orders
     WHERE $paidWhere AND created_at BETWEEN ? AND ?
     GROUP BY DATE(created_at)
     ORDER BY gun DESC"
);
$daily->execute($args);
$daily = $daily->fetchAll();

// Özet
$summary = db()->prepare(
    "SELECT COUNT(*) AS sip, SUM(total) AS ciro, {$kdvExpr} AS kdv,
            SUM(shipping_amount) AS kargo, SUM(discount_amount) AS indirim
     FROM orders
     WHERE $paidWhere AND created_at BETWEEN ? AND ?"
);
$summary->execute($args);
$sum = $summary->fetch() ?: ['sip'=>0,'ciro'=>0,'kdv'=>0,'kargo'=>0,'indirim'=>0];
$aov = (int)$sum['sip'] > 0 ? (float)$sum['ciro'] / (int)$sum['sip'] : 0;

// Maksimum ciro — bar grafik için
$maxCiro = 0;
foreach ($daily as $d) if ((float)$d['ciro'] > $maxCiro) $maxCiro = (float)$d['ciro'];
?>

<style>
.rep-toolbar{display:flex;gap:14px;flex-wrap:wrap;align-items:flex-end;margin-bottom:18px;padding:14px 18px;background:var(--olive-2);border-radius:10px;border:1px solid var(--gold-border)}
.rep-toolbar .field{flex:0 0 auto;margin:0}
.rep-toolbar .field label{display:block;font-size:11px;letter-spacing:.18em;text-transform:uppercase;color:var(--muted-text);margin-bottom:6px}
.rep-toolbar input{padding:8px 12px;border:1px solid var(--gold-border);background:var(--olive-2);color:var(--ink);border-radius:6px}
.rep-summary{display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:12px;margin-bottom:18px}
.rep-summary .kpi-card{padding:14px 16px}
.rep-summary .val{font-size:20px}
.bar-row{display:grid;grid-template-columns:110px 1fr 110px;gap:12px;align-items:center;padding:6px 0;border-bottom:1px solid rgba(255,255,255,.04)}
.bar-row:last-child{border-bottom:none}
.bar-bg{height:10px;background:rgba(255,255,255,.06);border-radius:5px;overflow:hidden}
.bar-fg{height:100%;background:linear-gradient(90deg,#8fa454,#c8b560);border-radius:5px}
</style>

<form method="get" class="rep-toolbar">
  <div class="field"><label>Başlangıç</label><input type="date" name="from" value="<?= e($from) ?>"></div>
  <div class="field"><label>Bitiş</label><input type="date" name="to" value="<?= e($to) ?>"></div>
  <button class="btn btn-primary" type="submit">Filtrele</button>
  <a class="btn btn-secondary" href="?from=<?= e($from) ?>&to=<?= e($to) ?>&export=csv">CSV İndir</a>
  <div style="margin-left:auto;display:flex;gap:6px">
    <a class="btn btn-secondary btn-sm" href="?from=<?= date('Y-m-d') ?>&to=<?= date('Y-m-d') ?>">Bugün</a>
    <a class="btn btn-secondary btn-sm" href="?from=<?= date('Y-m-d', strtotime('-7 days')) ?>&to=<?= date('Y-m-d') ?>">7 Gün</a>
    <a class="btn btn-secondary btn-sm" href="?from=<?= date('Y-m-d', strtotime('-30 days')) ?>&to=<?= date('Y-m-d') ?>">30 Gün</a>
    <a class="btn btn-secondary btn-sm" href="?from=<?= date('Y-m-01') ?>&to=<?= date('Y-m-d') ?>">Bu Ay</a>
  </div>
</form>

<div class="rep-summary">
  <div class="kpi-card"><div class="lbl">Sipariş</div><div class="val"><?= (int)$sum['sip'] ?></div></div>
  <div class="kpi-card"><div class="lbl">Toplam Ciro</div><div class="val"><?= money($sum['ciro']) ?></div></div>
  <div class="kpi-card"><div class="lbl">AOV</div><div class="val"><?= money($aov) ?></div></div>
  <div class="kpi-card"><div class="lbl">KDV</div><div class="val"><?= money($sum['kdv']) ?></div></div>
  <div class="kpi-card"><div class="lbl">Kargo</div><div class="val"><?= money($sum['kargo']) ?></div></div>
  <div class="kpi-card"><div class="lbl">İndirim</div><div class="val" style="color:#e4a3a3"><?= money($sum['indirim']) ?></div></div>
</div>

<div class="panel">
  <h3 style="font-size:13px;letter-spacing:.18em;text-transform:uppercase;color:var(--gold);margin:0 0 14px;font-family:Georgia,serif">Günlük Detay</h3>
  <?php if ($daily): ?>
    <table>
      <thead><tr><th>Tarih</th><th>Sipariş</th><th>Ciro</th><th>KDV</th><th>Kargo</th><th>İndirim</th><th style="width:30%">Görsel</th></tr></thead>
      <tbody>
      <?php foreach ($daily as $d): $pct = $maxCiro > 0 ? ((float)$d['ciro'] / $maxCiro) * 100 : 0; ?>
        <tr>
          <td><?= e($d['gun']) ?></td>
          <td><?= (int)$d['sip'] ?></td>
          <td style="color:var(--gold);font-weight:600"><?= money($d['ciro']) ?></td>
          <td><?= money($d['kdv']) ?></td>
          <td><?= money($d['kargo']) ?></td>
          <td style="color:#e4a3a3"><?= money($d['indirim']) ?></td>
          <td><div class="bar-bg"><div class="bar-fg" style="width:<?= round($pct) ?>%"></div></div></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  <?php else: ?>
    <p class="muted">Seçilen aralıkta onaylı sipariş yok.</p>
  <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../core/footer.php'; ?>
