<?php
$page = 'loyalty_transactions'; $title = 'Sadakat — Puan İşlemleri';
require_once __DIR__ . '/../core/auth.php';

/* ── Filtreler ───────────────────────────────────────────────────── */
$q     = trim($_GET['q'] ?? '');
$type  = $_GET['type'] ?? '';
$from  = $_GET['from'] ?? date('Y-m-d', strtotime('-30 days'));
$to    = $_GET['to']   ?? date('Y-m-d');
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $from)) $from = date('Y-m-d', strtotime('-30 days'));
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $to))   $to   = date('Y-m-d');

$where = ['lt.created_at BETWEEN ? AND ?'];
$args  = [$from . ' 00:00:00', $to . ' 23:59:59'];
if ($q !== '')    { $where[] = '(u.name LIKE ? OR u.email LIKE ? OR lt.note LIKE ?)'; $args[]="%$q%"; $args[]="%$q%"; $args[]="%$q%"; }
if ($type !== '') { $where[] = 'lt.type = ?'; $args[] = $type; }
$whereSql = 'WHERE ' . implode(' AND ', $where);

/* ── CSV export ─────────────────────────────────────────────────── */
if (($_GET['export'] ?? '') === 'csv') {
    $st = db()->prepare(
        "SELECT lt.*, u.name, u.email
         FROM loyalty_transactions lt
         LEFT JOIN users u ON u.id = lt.user_id
         $whereSql ORDER BY lt.created_at DESC"
    );
    $st->execute($args);
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="loyalty-tx-' . $from . '_' . $to . '.csv"');
    $out = fopen('php://output', 'w');
    fprintf($out, "\xEF\xBB\xBF");
    fputcsv($out, ['Tarih','Müşteri','Email','Tip','Puan','Sipariş','Not','Expire']);
    foreach ($st as $r) fputcsv($out, [
        $r['created_at'], $r['name'] ?? '', $r['email'] ?? '',
        $r['type'], $r['points'], $r['order_id'] ?? '',
        $r['note'] ?? '', $r['expires_at'] ?? '',
    ]);
    fclose($out);
    exit;
}

/* ── KPI ─────────────────────────────────────────────────────────── */
$kpi = [];
try {
    $st = db()->prepare(
        "SELECT type, SUM(points) AS total_pts, COUNT(*) AS cnt
         FROM loyalty_transactions lt LEFT JOIN users u ON u.id = lt.user_id
         $whereSql GROUP BY type"
    );
    $st->execute($args);
    foreach ($st->fetchAll() as $r) $kpi[$r['type']] = $r;
} catch (\Throwable $e) {}

/* ── Liste ───────────────────────────────────────────────────────── */
$pageNum = max(1, (int)($_GET['p'] ?? 1));
$perPage = 50;
$offset  = ($pageNum - 1) * $perPage;

$totalSt = db()->prepare(
    "SELECT COUNT(*) FROM loyalty_transactions lt LEFT JOIN users u ON u.id = lt.user_id $whereSql"
);
$totalSt->execute($args);
$total = (int)$totalSt->fetchColumn();
$totalPages = max(1, (int)ceil($total / $perPage));

$st = db()->prepare(
    "SELECT lt.*, u.name, u.email
     FROM loyalty_transactions lt
     LEFT JOIN users u ON u.id = lt.user_id
     $whereSql
     ORDER BY lt.created_at DESC
     LIMIT $perPage OFFSET $offset"
);
$st->execute($args);
$rows = $st->fetchAll();

$typeLabels = [
    'earn'   => ['Kazanma','#9fce7d'],
    'redeem' => ['Kullanım','#c8b560'],
    'expire' => ['Süresi Doldu','#e4a3a3'],
    'adjust' => ['Manuel Düzeltme','#a8a090'],
    'refund' => ['İade','#9fce7d'],
];

$AP = SITE_URL . '/admin_panel';
require_once __DIR__ . '/../core/header.php';
?>

<style>
.lt-tools{display:flex;gap:10px;flex-wrap:wrap;align-items:flex-end;margin-bottom:18px;padding:14px 18px;background:var(--olive-2);border-radius:10px;border:1px solid var(--gold-border)}
.lt-tools .field{margin:0}
.lt-tools .field label{display:block;font-size:10px;letter-spacing:.18em;text-transform:uppercase;color:var(--muted-text);margin-bottom:6px}
.lt-tools input,.lt-tools select{padding:8px 12px;border:1px solid var(--gold-border);background:var(--olive-2);color:var(--ink);border-radius:6px;font-size:13px}
.lt-kpi{display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:12px;margin-bottom:16px}
.lt-kpi .card{padding:12px 16px;background:var(--olive-2);border:1px solid var(--gold-border);border-radius:8px}
.lt-kpi .lbl{font-size:10px;letter-spacing:.16em;text-transform:uppercase;color:var(--muted-text);margin-bottom:4px}
.lt-kpi .val{font-size:18px;font-weight:600;font-family:Georgia,serif;line-height:1.1}
.tx-pill{padding:3px 9px;border-radius:11px;font-size:11px;font-weight:500;display:inline-block}
.pagination{display:flex;gap:6px;justify-content:center;margin-top:18px}
.pagination a,.pagination span{padding:6px 12px;border:1px solid var(--gold-border);border-radius:5px;text-decoration:none;color:var(--ink);font-size:12px;background:var(--olive-2)}
.pagination .current{background:var(--ink);color:var(--on-dark)}
</style>

<form method="get" class="lt-tools">
  <div class="field"><label>Başlangıç</label><input type="date" name="from" value="<?= e($from) ?>"></div>
  <div class="field"><label>Bitiş</label><input type="date" name="to" value="<?= e($to) ?>"></div>
  <div class="field">
    <label>Tip</label>
    <select name="type">
      <option value="">Tümü</option>
      <?php foreach ($typeLabels as $k => $v): ?>
        <option value="<?= $k ?>" <?= $type===$k?'selected':'' ?>><?= $v[0] ?></option>
      <?php endforeach; ?>
    </select>
  </div>
  <div class="field"><label>Arama (ad/email/not)</label><input type="text" name="q" value="<?= e($q) ?>"></div>
  <button class="btn btn-primary btn-sm" type="submit">Filtrele</button>
  <a class="btn btn-secondary btn-sm" href="?<?= http_build_query(array_merge($_GET, ['export'=>'csv'])) ?>">CSV</a>
  <a class="btn btn-secondary btn-sm" href="customers.php" style="margin-left:auto">← Müşteri Listesi</a>
</form>

<div class="lt-kpi">
  <?php foreach ($typeLabels as $k => $v):
      $r = $kpi[$k] ?? null;
      $cnt = $r['cnt'] ?? 0;
      $pts = (int)($r['total_pts'] ?? 0);
  ?>
    <div class="card">
      <div class="lbl"><?= $v[0] ?></div>
      <div class="val" style="color:<?= $v[1] ?>"><?= ($pts >= 0 ? '+' : '') . number_format($pts,0,',','.') ?></div>
      <small style="color:var(--muted-text);font-size:10px"><?= (int)$cnt ?> işlem</small>
    </div>
  <?php endforeach; ?>
</div>

<div class="panel" style="padding:0">
  <table>
    <thead><tr>
      <th>Tarih</th><th>Müşteri</th><th>Tip</th><th class="num">Puan</th>
      <th>Sipariş</th><th>Not</th><th>Expire</th>
    </tr></thead>
    <tbody>
    <?php if ($rows): foreach ($rows as $r): $tl = $typeLabels[$r['type']] ?? ['?','#aaa']; ?>
      <tr>
        <td style="white-space:nowrap;font-size:12px"><?= e($r['created_at']) ?></td>
        <td>
          <?php if ($r['user_id']): ?>
            <a href="<?= $AP ?>/customers/view.php?id=<?= (int)$r['user_id'] ?>" style="color:var(--champagne);text-decoration:none">
              <strong><?= e($r['name'] ?: 'Silinmiş') ?></strong>
            </a>
            <br><span class="muted" style="font-size:11px"><?= e($r['email'] ?? '') ?></span>
          <?php else: ?>
            <span class="muted">—</span>
          <?php endif; ?>
        </td>
        <td><span class="tx-pill" style="background:rgba(<?= $r['type']==='redeem' || $r['type']==='expire' ? '207,82,82,.16' : '107,170,80,.18' ?>);color:<?= $tl[1] ?>"><?= $tl[0] ?></span></td>
        <td class="num" style="font-weight:600;color:<?= $r['points'] > 0 ? '#9fce7d' : '#e4a3a3' ?>;font-size:14px">
          <?= $r['points'] > 0 ? '+' : '' ?><?= number_format($r['points'],0,',','.') ?>
        </td>
        <td><?php if ($r['order_id']): ?><a href="<?= $AP ?>/orders/view.php?id=<?= (int)$r['order_id'] ?>" style="color:var(--gold)">#<?= (int)$r['order_id'] ?></a><?php else: ?><span class="muted">—</span><?php endif; ?></td>
        <td style="font-size:12px;color:var(--muted-text);max-width:300px"><?= e($r['note'] ?? '—') ?></td>
        <td style="font-size:11px;color:var(--muted-text)"><?= $r['expires_at'] ? e(substr($r['expires_at'],0,10)) : '—' ?></td>
      </tr>
    <?php endforeach; else: ?>
      <tr><td colspan="7" class="muted" style="padding:30px;text-align:center">Filtreye uyan işlem yok.</td></tr>
    <?php endif; ?>
    </tbody>
  </table>
</div>

<?php if ($totalPages > 1): ?>
<div class="pagination">
  <?php for ($i = max(1, $pageNum-2); $i <= min($totalPages, $pageNum+2); $i++): ?>
    <?php $qs = http_build_query(array_merge($_GET, ['p'=>$i])); ?>
    <?php if ($i === $pageNum): ?>
      <span class="current"><?= $i ?></span>
    <?php else: ?>
      <a href="?<?= e($qs) ?>"><?= $i ?></a>
    <?php endif; ?>
  <?php endfor; ?>
  <span style="padding:6px 12px;color:var(--muted-text)"><?= number_format($total) ?> işlem</span>
</div>
<?php endif; ?>

<?php require_once __DIR__ . '/../core/footer.php'; ?>
