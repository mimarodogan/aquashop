<?php
$page = 'sms_log'; $title = 'SMS Gönderim Log';
require_once __DIR__ . '/../core/auth.php';

/* ── Manuel yeniden gönderme ───────────────────────────────────────── */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf_check($_POST['csrf'] ?? null)) {
    if (($_POST['action'] ?? '') === 'resend' && !empty($_POST['log_id'])) {
        $r = db()->prepare("SELECT recipient, message, template FROM sms_log WHERE id=?");
        $r->execute([(int)$_POST['log_id']]);
        $row = $r->fetch();
        if ($row && function_exists('sms_send')) {
            $ok = sms_send($row['recipient'], $row['message'], $row['template']);
            flash_set($ok ? 'ok' : 'err', $ok ? 'SMS yeniden gönderildi.' : 'Yeniden gönderim başarısız.');
        } else {
            flash_set('err', 'Log kaydı bulunamadı.');
        }
        redirect('sms-log.php' . (isset($_GET['q']) || isset($_GET['status']) ? '?' . http_build_query($_GET) : ''));
    }
}

/* ── Filtreler ───────────────────────────────────────────────────── */
$q       = trim($_GET['q'] ?? '');
$status  = $_GET['status'] ?? '';
$tpl     = $_GET['tpl'] ?? '';
$from    = $_GET['from'] ?? date('Y-m-d', strtotime('-30 days'));
$to      = $_GET['to']   ?? date('Y-m-d');
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $from)) $from = date('Y-m-d', strtotime('-30 days'));
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $to))   $to   = date('Y-m-d');

$where = ['sent_at BETWEEN ? AND ?'];
$args  = [$from . ' 00:00:00', $to . ' 23:59:59'];
if ($q !== '')      { $where[] = '(recipient LIKE ? OR message LIKE ?)'; $args[] = "%$q%"; $args[] = "%$q%"; }
if ($status !== '') { $where[] = 'status = ?'; $args[] = $status; }
if ($tpl !== '')    { $where[] = 'template = ?'; $args[] = $tpl; }
$whereSql = 'WHERE ' . implode(' AND ', $where);

/* ── CSV export ─────────────────────────────────────────────────── */
if (($_GET['export'] ?? '') === 'csv') {
    $rows = db()->prepare("SELECT * FROM sms_log $whereSql ORDER BY sent_at DESC");
    $rows->execute($args);
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="sms-log-' . $from . '_' . $to . '.csv"');
    $out = fopen('php://output', 'w');
    fprintf($out, "\xEF\xBB\xBF");
    fputcsv($out, ['Tarih','Alıcı','Sağlayıcı','Şablon','Durum','Hata','Mesaj']);
    foreach ($rows as $r) fputcsv($out, [
        $r['sent_at'], $r['recipient'], $r['provider'],
        $r['template'] ?? '', $r['status'],
        $r['error_message'] ?? '', $r['message'],
    ]);
    fclose($out);
    exit;
}

/* ── Özet KPI'lar ────────────────────────────────────────────────── */
$summary = db()->prepare(
    "SELECT
        COUNT(*) AS total,
        SUM(status='success') AS success,
        SUM(status='failure') AS failure,
        COUNT(DISTINCT recipient) AS unique_recipients
     FROM sms_log $whereSql"
);
$summary->execute($args);
$sum = $summary->fetch() ?: ['total'=>0,'success'=>0,'failure'=>0,'unique_recipients'=>0];

/* ── Liste (paginated) ──────────────────────────────────────────── */
$pageNum = max(1, (int)($_GET['p'] ?? 1));
$perPage = 50;
$offset  = ($pageNum - 1) * $perPage;

$listSt = db()->prepare("SELECT * FROM sms_log $whereSql ORDER BY sent_at DESC LIMIT $perPage OFFSET $offset");
$listSt->execute($args);
$rows = $listSt->fetchAll();

$totalRows = (int)$sum['total'];
$totalPages = max(1, (int)ceil($totalRows / $perPage));

/* ── Şablon listesi (filtre dropdown için) ──────────────────────── */
$templates = [];
try {
    $tplSt = db()->query("SELECT DISTINCT template FROM sms_log WHERE template IS NOT NULL ORDER BY template");
    $templates = array_filter(array_column($tplSt->fetchAll(), 'template'));
} catch (\Throwable $e) {}

require_once __DIR__ . '/../core/header.php';
?>

<style>
.smsl-tools{display:flex;gap:10px;flex-wrap:wrap;align-items:flex-end;margin-bottom:18px;padding:14px 18px;background:var(--olive-2);border-radius:10px;border:1px solid var(--gold-border)}
.smsl-tools .field{margin:0}
.smsl-tools .field label{display:block;font-size:10px;letter-spacing:.18em;text-transform:uppercase;color:var(--muted-text);margin-bottom:6px}
.smsl-tools input,.smsl-tools select{padding:8px 12px;border:1px solid var(--gold-border);background:var(--olive-2);color:var(--ink);border-radius:6px;font-size:13px}
.smsl-kpi{display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:12px;margin-bottom:16px}
.smsl-kpi .card{padding:12px 16px;background:var(--olive-2);border:1px solid var(--gold-border);border-radius:8px}
.smsl-kpi .lbl{font-size:10px;letter-spacing:.16em;text-transform:uppercase;color:var(--muted-text);margin-bottom:4px}
.smsl-kpi .val{font-size:20px;color:var(--gold);font-weight:600;font-family:Georgia,serif;line-height:1.1}
.smsl-msg{max-width:300px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;font-size:12px;color:var(--muted-text)}
.smsl-msg:hover{white-space:normal;background:rgba(255,255,255,.03)}
.status-success{background:rgba(107,170,80,.18);color:#9fce7d;padding:3px 9px;border-radius:11px;font-size:11px;font-weight:500}
.status-failure{background:rgba(207,82,82,.16);color:#e4a3a3;padding:3px 9px;border-radius:11px;font-size:11px;font-weight:500}
.pagination{display:flex;gap:6px;justify-content:center;margin-top:18px}
.pagination a,.pagination span{padding:6px 12px;border:1px solid var(--gold-border);border-radius:5px;text-decoration:none;color:var(--ink);font-size:12px;background:var(--olive-2)}
.pagination .current{background:var(--ink);color:var(--on-dark)}
</style>

<form method="get" class="smsl-tools">
  <div class="field"><label>Başlangıç</label><input type="date" name="from" value="<?= e($from) ?>"></div>
  <div class="field"><label>Bitiş</label><input type="date" name="to" value="<?= e($to) ?>"></div>
  <div class="field">
    <label>Durum</label>
    <select name="status">
      <option value="">Tümü</option>
      <option value="success" <?= $status==='success'?'selected':'' ?>>Başarılı</option>
      <option value="failure" <?= $status==='failure'?'selected':'' ?>>Hatalı</option>
    </select>
  </div>
  <div class="field">
    <label>Şablon</label>
    <select name="tpl">
      <option value="">Tümü</option>
      <?php foreach ($templates as $t): ?>
        <option value="<?= e($t) ?>" <?= $tpl===$t?'selected':'' ?>><?= e($t) ?></option>
      <?php endforeach; ?>
    </select>
  </div>
  <div class="field"><label>Arama (alıcı/mesaj)</label><input type="text" name="q" value="<?= e($q) ?>" placeholder="905..."></div>
  <button class="btn btn-primary btn-sm" type="submit">Filtrele</button>
  <a class="btn btn-secondary btn-sm" href="?<?= http_build_query(array_merge($_GET, ['export'=>'csv'])) ?>">CSV İndir</a>
</form>

<div class="smsl-kpi">
  <div class="card"><div class="lbl">Toplam Gönderim</div><div class="val"><?= (int)$sum['total'] ?></div></div>
  <div class="card"><div class="lbl">Başarılı</div><div class="val" style="color:#9fce7d"><?= (int)$sum['success'] ?></div></div>
  <div class="card"><div class="lbl">Hatalı</div><div class="val" style="color:#e4a3a3"><?= (int)$sum['failure'] ?></div></div>
  <div class="card"><div class="lbl">Tekil Alıcı</div><div class="val"><?= (int)$sum['unique_recipients'] ?></div></div>
</div>

<div class="panel" style="padding:0">
  <table>
    <thead><tr>
      <th>Tarih</th><th>Alıcı</th><th>Şablon</th><th>Sağlayıcı</th>
      <th>Durum</th><th>Mesaj / Hata</th><th></th>
    </tr></thead>
    <tbody>
    <?php if ($rows): foreach ($rows as $r): ?>
      <tr>
        <td style="white-space:nowrap;font-size:12px"><?= e($r['sent_at']) ?></td>
        <td style="font-family:monospace;font-size:12px"><?= e($r['recipient']) ?></td>
        <td><?= $r['template'] ? '<code style="font-size:11px;background:rgba(0,0,0,.05);padding:2px 6px;border-radius:3px">' . e($r['template']) . '</code>' : '<span class="muted">—</span>' ?></td>
        <td style="font-size:12px"><?= e($r['provider']) ?></td>
        <td><span class="status-<?= e($r['status']) ?>"><?= $r['status']==='success'?'✓ Gönderildi':'✗ Hata' ?></span></td>
        <td class="smsl-msg" title="<?= e($r['message']) ?>">
          <?= e(mb_substr($r['message'], 0, 80)) ?>
          <?php if (!empty($r['error_message'])): ?>
            <br><small style="color:#e4a3a3">⚠ <?= e($r['error_message']) ?></small>
          <?php endif; ?>
        </td>
        <td style="white-space:nowrap">
          <?php if ($r['status']==='failure'): ?>
          <form method="post" style="display:inline">
            <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="action" value="resend">
            <input type="hidden" name="log_id" value="<?= (int)$r['id'] ?>">
            <button class="btn btn-secondary btn-sm" type="submit" onclick="return confirm('Bu SMS\'i tekrar göndermek ister misiniz?')" title="Yeniden gönder">↻</button>
          </form>
          <?php endif; ?>
        </td>
      </tr>
    <?php endforeach; else: ?>
      <tr><td colspan="7" class="muted" style="padding:30px;text-align:center">Seçilen filtrede SMS kaydı bulunamadı.</td></tr>
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
  <span style="padding:6px 12px;color:var(--muted-text)"><?= number_format($totalRows) ?> kayıt</span>
</div>
<?php endif; ?>

<?php require_once __DIR__ . '/../core/footer.php'; ?>
