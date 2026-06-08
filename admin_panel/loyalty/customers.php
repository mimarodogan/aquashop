<?php
$page = 'loyalty_customers'; $title = 'Sadakat — Müşteri Puanları';
require_once __DIR__ . '/../core/auth.php';

/* ── Manuel puan ekle/çıkar ──────────────────────────────────────── */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf_check($_POST['csrf'] ?? null)) {
    if (($_POST['action'] ?? '') === 'adjust') {
        $uid    = (int)($_POST['user_id'] ?? 0);
        $points = (int)($_POST['points'] ?? 0);
        $note   = trim($_POST['note'] ?? '');
        if ($uid > 0 && $points !== 0) {
            $type = $points > 0 ? 'adjust' : 'adjust';
            $noteText = ($note ?: 'Manuel düzeltme') . ' — admin tarafından';
            if (loyalty_apply_delta($uid, $points, 'adjust', null, $noteText)) {
                flash_set('ok', abs($points) . ' puan ' . ($points>0?'eklendi':'düşüldü') . '.');
            } else {
                flash_set('err', 'İşlem başarısız.');
            }
        }
        redirect('customers.php');
    }
}

/* ── Filtreler ───────────────────────────────────────────────────── */
$q       = trim($_GET['q'] ?? '');
$minPts  = (int)($_GET['min_pts'] ?? 0);
$tier    = $_GET['tier'] ?? '';

$where = ['u.role = ?'];
$args  = ['customer'];
if ($q !== '')      { $where[] = '(u.name LIKE ? OR u.email LIKE ?)'; $args[] = "%$q%"; $args[] = "%$q%"; }
if ($minPts > 0)    { $where[] = 'COALESCE(lp.points, 0) >= ?'; $args[] = $minPts; }
if ($tier !== '')   { $where[] = 'u.loyalty_tier = ?'; $args[] = $tier; }
$whereSql = 'WHERE ' . implode(' AND ', $where);

/* ── Özet KPI'lar ────────────────────────────────────────────────── */
$kpi = [];
try {
    $kpi['active_members']    = (int)db()->query("SELECT COUNT(*) FROM loyalty_points WHERE points > 0")->fetchColumn();
    $kpi['total_points']      = (int)db()->query("SELECT COALESCE(SUM(points),0) FROM loyalty_points")->fetchColumn();
    $kpi['total_lifetime']    = (int)db()->query("SELECT COALESCE(SUM(points_lifetime),0) FROM loyalty_points")->fetchColumn();
    $kpi['total_redeemed']    = (int)db()->query("SELECT COALESCE(SUM(-points),0) FROM loyalty_transactions WHERE type='redeem'")->fetchColumn();
    $kpi['total_expired']     = (int)db()->query("SELECT COALESCE(SUM(-points),0) FROM loyalty_transactions WHERE type='expire'")->fetchColumn();
} catch (\Throwable $e) { $kpi = ['active_members'=>0,'total_points'=>0,'total_lifetime'=>0,'total_redeemed'=>0,'total_expired'=>0]; }

/* ── Liste ───────────────────────────────────────────────────────── */
$pageNum = max(1, (int)($_GET['p'] ?? 1));
$perPage = 50;
$offset  = ($pageNum - 1) * $perPage;

$totalSt = db()->prepare(
    "SELECT COUNT(*) FROM users u
     LEFT JOIN loyalty_points lp ON lp.user_id = u.id
     $whereSql"
);
$totalSt->execute($args);
$total = (int)$totalSt->fetchColumn();
$totalPages = max(1, (int)ceil($total / $perPage));

$st = db()->prepare(
    "SELECT u.id, u.name, u.email, u.loyalty_tier, u.created_at,
            COALESCE(lp.points, 0)          AS points,
            COALESCE(lp.points_lifetime, 0) AS lifetime,
            (SELECT COALESCE(SUM(total),0) FROM orders o WHERE o.user_id=u.id AND o.status IN ('paid','shipped','delivered')) AS ltv,
            (SELECT MAX(created_at) FROM orders o WHERE o.user_id=u.id) AS last_order
     FROM users u
     LEFT JOIN loyalty_points lp ON lp.user_id = u.id
     $whereSql
     ORDER BY points DESC, ltv DESC
     LIMIT $perPage OFFSET $offset"
);
$st->execute($args);
$rows = $st->fetchAll();

$AP = SITE_URL . '/admin_panel';
require_once __DIR__ . '/../core/header.php';
?>

<style>
.lk-tools{display:flex;gap:10px;flex-wrap:wrap;align-items:flex-end;margin-bottom:18px;padding:14px 18px;background:var(--olive-2);border-radius:10px;border:1px solid var(--gold-border)}
.lk-tools .field{margin:0}
.lk-tools .field label{display:block;font-size:10px;letter-spacing:.18em;text-transform:uppercase;color:var(--muted-text);margin-bottom:6px}
.lk-tools input,.lk-tools select{padding:8px 12px;border:1px solid var(--gold-border);background:var(--olive-2);color:var(--ink);border-radius:6px;font-size:13px}
.lk-kpi{display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:12px;margin-bottom:16px}
.lk-kpi .card{padding:12px 16px;background:var(--olive-2);border:1px solid var(--gold-border);border-radius:8px}
.lk-kpi .lbl{font-size:10px;letter-spacing:.16em;text-transform:uppercase;color:var(--muted-text);margin-bottom:4px}
.lk-kpi .val{font-size:20px;color:var(--gold);font-weight:600;font-family:Georgia,serif;line-height:1.1}
.tier-new{background:rgba(160,160,160,.12);color:#a8a090;padding:3px 9px;border-radius:11px;font-size:11px}
.tier-loyal{background:rgba(107,170,80,.18);color:#9fce7d;padding:3px 9px;border-radius:11px;font-size:11px;font-weight:600}
.tier-vip{background:rgba(201,162,75,.22);color:#c8b560;padding:3px 9px;border-radius:11px;font-size:11px;font-weight:600}
.adjust-form{display:inline-flex;gap:4px;align-items:center}
.adjust-form input{padding:5px 8px;width:70px;border:1px solid var(--gold-border);background:var(--olive-2);color:var(--ink);border-radius:4px;font-size:12px}
.pagination{display:flex;gap:6px;justify-content:center;margin-top:18px}
.pagination a,.pagination span{padding:6px 12px;border:1px solid var(--gold-border);border-radius:5px;text-decoration:none;color:var(--ink);font-size:12px;background:var(--olive-2)}
.pagination .current{background:var(--ink);color:var(--on-dark)}
</style>

<form method="get" class="lk-tools">
  <div class="field"><label>Arama</label><input type="text" name="q" value="<?= e($q) ?>" placeholder="ad / email"></div>
  <div class="field">
    <label>Seviye</label>
    <select name="tier">
      <option value="">Tümü</option>
      <option value="new"   <?= $tier==='new'?'selected':'' ?>>Yeni Üye</option>
      <option value="loyal" <?= $tier==='loyal'?'selected':'' ?>>Sadık Müşteri</option>
      <option value="vip"   <?= $tier==='vip'?'selected':'' ?>>VIP</option>
    </select>
  </div>
  <div class="field"><label>Min. Puan</label><input type="number" name="min_pts" value="<?= $minPts ?: '' ?>" placeholder="0"></div>
  <button class="btn btn-primary btn-sm" type="submit">Filtrele</button>
  <a class="btn btn-secondary btn-sm" href="transactions.php" style="margin-left:auto">→ Tüm Puan İşlemleri</a>
</form>

<div class="lk-kpi">
  <div class="card"><div class="lbl">Aktif Üyeler</div><div class="val"><?= $kpi['active_members'] ?></div></div>
  <div class="card"><div class="lbl">Dolaşımdaki Puan</div><div class="val"><?= number_format($kpi['total_points'], 0, ',', '.') ?></div></div>
  <div class="card"><div class="lbl">Toplam Kazandırılan</div><div class="val"><?= number_format($kpi['total_lifetime'], 0, ',', '.') ?></div></div>
  <div class="card"><div class="lbl">Kullanılan</div><div class="val"><?= number_format($kpi['total_redeemed'], 0, ',', '.') ?></div></div>
  <div class="card"><div class="lbl">Expire Olan</div><div class="val" style="color:#e4a3a3"><?= number_format($kpi['total_expired'], 0, ',', '.') ?></div></div>
</div>

<div class="panel" style="padding:0">
  <table>
    <thead><tr>
      <th>Müşteri</th><th>Seviye</th><th class="num">Puan Bakiyesi</th><th class="num">Toplam Kazanılan</th>
      <th class="num">LTV (Ciro)</th><th>Son Sipariş</th><th>Manuel Düzeltme</th>
    </tr></thead>
    <tbody>
    <?php $tierLabels = ['new'=>'Yeni','loyal'=>'Sadık','vip'=>'VIP']; ?>
    <?php if ($rows): foreach ($rows as $u): ?>
      <tr>
        <td>
          <a href="<?= $AP ?>/customers/view.php?id=<?= (int)$u['id'] ?>" style="color:var(--champagne);text-decoration:none">
            <strong><?= e($u['name'] ?: 'İsimsiz') ?></strong>
          </a>
          <br><span class="muted" style="font-size:11px"><?= e($u['email']) ?></span>
        </td>
        <td><span class="tier-<?= e($u['loyalty_tier'] ?? 'new') ?>"><?= e($tierLabels[$u['loyalty_tier'] ?? 'new']) ?></span></td>
        <td class="num" style="color:var(--gold);font-weight:600;font-size:16px"><?= number_format($u['points'], 0, ',', '.') ?></td>
        <td class="num"><?= number_format($u['lifetime'], 0, ',', '.') ?></td>
        <td class="num"><?= money($u['ltv']) ?></td>
        <td style="font-size:12px;color:var(--muted-text)"><?= $u['last_order'] ? e(substr($u['last_order'],0,10)) : '—' ?></td>
        <td>
          <form method="post" class="adjust-form">
            <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="action" value="adjust">
            <input type="hidden" name="user_id" value="<?= (int)$u['id'] ?>">
            <input type="number" name="points" placeholder="±0" required>
            <input type="text" name="note" placeholder="not (ops.)" style="width:120px">
            <button class="btn btn-secondary btn-sm" type="submit" onclick="return confirm('Bakiyeyi değiştirmek istediğinize emin misiniz?')" title="Puan ekle/çıkar (+/-)">✓</button>
          </form>
        </td>
      </tr>
    <?php endforeach; else: ?>
      <tr><td colspan="7" class="muted" style="padding:30px;text-align:center">Filtreye uyan müşteri yok.</td></tr>
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
  <span style="padding:6px 12px;color:var(--muted-text)"><?= number_format($total) ?> müşteri</span>
</div>
<?php endif; ?>

<?php require_once __DIR__ . '/../core/footer.php'; ?>
