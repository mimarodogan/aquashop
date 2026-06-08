<?php
$page = 'abandoned_carts'; $title = 'Terk Edilmiş Sepetler';
require_once __DIR__ . '/core/auth.php';

/* ── Manuel hatırlatma gönderme / sıfırlama ─────────────────────── */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf_check($_POST['csrf'] ?? null)) {
    $action = $_POST['action'] ?? '';
    $id     = (int)($_POST['cart_id'] ?? 0);

    if ($action === 'send_now' && $id > 0) {
        // CRON_BYPASS ile cron'u tek satır için manuel çalıştır
        define('CRON_BYPASS', true);
        $r = db()->prepare('SELECT * FROM abandoned_carts WHERE id = ?');
        $r->execute([$id]);
        $row = $r->fetch();
        if ($row) {
            // reminder_step + 1'i zorla göndermek için cron mantığını basitleştirilmiş şekilde uygula
            $step = min(3, (int)$row['reminder_step'] + 1);
            // Cron dosyasını bir kez include et (ilk çağrı bütün processStep'leri çağırır — biz spesifik istiyoruz, yapmıyoruz)
            // Bunun yerine manuel "yeniden hatırlat" — son hatırlatma tarihini bugün'den geriye al ki cron yarın bu kaydı yakalasın
            db()->prepare('UPDATE abandoned_carts SET last_reminder_at = DATE_SUB(NOW(), INTERVAL 13 HOUR) WHERE id = ?')->execute([$id]);
            flash_set('ok', 'Bir sonraki cron çalışmasında bu sepet için hatırlatma gönderilecek.');
        }
        redirect('abandoned-carts.php');
    }

    if ($action === 'reset' && $id > 0) {
        db()->prepare('UPDATE abandoned_carts SET reminder_step = 0, last_reminder_at = NULL, coupon_code = NULL WHERE id = ?')->execute([$id]);
        flash_set('ok', 'Sepet sıfırlandı; tüm hatırlatmalar yeniden başlayacak.');
        redirect('abandoned-carts.php');
    }

    if ($action === 'delete' && $id > 0) {
        db()->prepare('DELETE FROM abandoned_carts WHERE id = ?')->execute([$id]);
        flash_set('ok', 'Kayıt silindi.');
        redirect('abandoned-carts.php');
    }
}

/* ── Filtreler ───────────────────────────────────────────────────── */
$q     = trim($_GET['q'] ?? '');
$step  = isset($_GET['step']) && $_GET['step'] !== '' ? (int)$_GET['step'] : null;

$where = ['1=1'];
$args  = [];
if ($q !== '')         { $where[] = '(ac.email LIKE ? OR u.name LIKE ?)'; $args[] = "%$q%"; $args[] = "%$q%"; }
if ($step !== null)    { $where[] = 'ac.reminder_step = ?'; $args[] = $step; }
$whereSql = 'WHERE ' . implode(' AND ', $where);

/* ── KPI ─────────────────────────────────────────────────────────── */
$kpi = [];
try {
    $kpi['total']     = (int)db()->query("SELECT COUNT(*) FROM abandoned_carts WHERE updated_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)")->fetchColumn();
    $kpi['recovered'] = (int)db()->query("SELECT COUNT(*) FROM orders WHERE coupon_code LIKE 'CARTBACK%' AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)")->fetchColumn();
    $kpi['rev']       = (float)db()->query("SELECT COALESCE(SUM(total),0) FROM orders WHERE coupon_code LIKE 'CARTBACK%' AND status IN ('paid','shipped','delivered') AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)")->fetchColumn();
    $kpi['by_step'] = [];
    foreach (db()->query("SELECT reminder_step, COUNT(*) AS cnt FROM abandoned_carts WHERE updated_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) GROUP BY reminder_step") as $r) {
        $kpi['by_step'][(int)$r['reminder_step']] = (int)$r['cnt'];
    }
} catch (\Throwable $e) { $kpi = ['total'=>0,'recovered'=>0,'rev'=>0,'by_step'=>[]]; }

/* ── Liste ───────────────────────────────────────────────────────── */
$pageNum = max(1, (int)($_GET['p'] ?? 1));
$perPage = 50;
$offset  = ($pageNum - 1) * $perPage;

$totalSt = db()->prepare("SELECT COUNT(*) FROM abandoned_carts ac LEFT JOIN users u ON u.id = ac.user_id $whereSql");
$totalSt->execute($args);
$total = (int)$totalSt->fetchColumn();
$totalPages = max(1, (int)ceil($total / $perPage));

$st = db()->prepare(
    "SELECT ac.*, u.name AS user_name, u.email AS user_email
     FROM abandoned_carts ac
     LEFT JOIN users u ON u.id = ac.user_id
     $whereSql
     ORDER BY ac.updated_at DESC
     LIMIT $perPage OFFSET $offset"
);
$st->execute($args);
$rows = $st->fetchAll();

$AP = SITE_URL . '/admin_panel';
require_once __DIR__ . '/core/header.php';

$stepLabels = [0 => 'Yeni (gönderilmedi)', 1 => '1. Hatırlatma (24h)', 2 => '2. Hatırlatma (72h+kupon)', 3 => '3. Hatırlatma (7gün son şans)'];
$stepColors = [0 => '#a8a090', 1 => '#9fce7d', 2 => '#c8b560', 3 => '#e4a3a3'];
?>

<style>
.ac-tools{display:flex;gap:10px;flex-wrap:wrap;align-items:flex-end;margin-bottom:18px;padding:14px 18px;background:var(--olive-2);border-radius:10px;border:1px solid var(--gold-border)}
.ac-tools .field{margin:0}
.ac-tools .field label{display:block;font-size:10px;letter-spacing:.18em;text-transform:uppercase;color:var(--muted-text);margin-bottom:6px}
.ac-tools input,.ac-tools select{padding:8px 12px;border:1px solid var(--gold-border);background:var(--olive-2);color:var(--ink);border-radius:6px;font-size:13px}
.ac-kpi{display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:12px;margin-bottom:16px}
.ac-kpi .card{padding:12px 16px;background:var(--olive-2);border:1px solid var(--gold-border);border-radius:8px}
.ac-kpi .lbl{font-size:10px;letter-spacing:.16em;text-transform:uppercase;color:var(--muted-text);margin-bottom:4px}
.ac-kpi .val{font-size:20px;color:var(--gold);font-weight:600;font-family:Georgia,serif;line-height:1.1}
.ac-step{padding:3px 9px;border-radius:11px;font-size:11px;font-weight:500;display:inline-block;white-space:nowrap}
.ac-summary{font-size:12px;color:var(--muted-text);max-width:280px}
.ac-summary strong{color:var(--champagne)}
.pagination{display:flex;gap:6px;justify-content:center;margin-top:18px}
.pagination a,.pagination span{padding:6px 12px;border:1px solid var(--gold-border);border-radius:5px;text-decoration:none;color:var(--ink);font-size:12px;background:var(--olive-2)}
.pagination .current{background:var(--ink);color:var(--on-dark)}
</style>

<form method="get" class="ac-tools">
  <div class="field"><label>Arama</label><input type="text" name="q" value="<?= e($q) ?>" placeholder="email / ad"></div>
  <div class="field">
    <label>Adım</label>
    <select name="step">
      <option value="">Tümü</option>
      <?php foreach ($stepLabels as $k => $v): ?>
        <option value="<?= $k ?>" <?= $step===$k?'selected':'' ?>><?= e($v) ?></option>
      <?php endforeach; ?>
    </select>
  </div>
  <button class="btn btn-primary btn-sm" type="submit">Filtrele</button>
</form>

<div class="ac-kpi">
  <div class="card"><div class="lbl">30g Toplam Terk</div><div class="val"><?= $kpi['total'] ?></div></div>
  <div class="card"><div class="lbl">Kurtarılan Sipariş</div><div class="val" style="color:#9fce7d"><?= $kpi['recovered'] ?></div></div>
  <div class="card"><div class="lbl">Kurtarılan Ciro</div><div class="val"><?= money($kpi['rev']) ?></div></div>
  <div class="card"><div class="lbl">Henüz Gönderilmedi</div><div class="val"><?= (int)($kpi['by_step'][0] ?? 0) ?></div></div>
  <div class="card"><div class="lbl">1. Adım Aldı</div><div class="val"><?= (int)($kpi['by_step'][1] ?? 0) ?></div></div>
  <div class="card"><div class="lbl">2. Adım (+kupon)</div><div class="val"><?= (int)($kpi['by_step'][2] ?? 0) ?></div></div>
  <div class="card"><div class="lbl">3. Adım (son şans)</div><div class="val" style="color:#e4a3a3"><?= (int)($kpi['by_step'][3] ?? 0) ?></div></div>
</div>

<div class="panel" style="padding:0">
  <table>
    <thead><tr>
      <th>Müşteri</th><th>Sepet Özeti</th><th>Adım</th>
      <th>Son Hatırlatma</th><th>Kupon</th><th>Terk Tarihi</th><th>İşlem</th>
    </tr></thead>
    <tbody>
    <?php if ($rows): foreach ($rows as $r):
        $cart = json_decode($r['cart_json'], true) ?: [];
        $itemCount = count($cart);
        $cartTotal = 0;
        foreach ($cart as $it) $cartTotal += (float)($it['price'] ?? 0) * (int)($it['qty'] ?? 1);
    ?>
      <tr>
        <td>
          <?php if ($r['user_id']): ?>
            <a href="<?= $AP ?>/customers/view.php?id=<?= (int)$r['user_id'] ?>" style="color:var(--champagne);text-decoration:none">
              <strong><?= e($r['user_name'] ?: $r['email']) ?></strong>
            </a>
          <?php else: ?>
            <strong style="color:var(--champagne)">Misafir</strong>
          <?php endif; ?>
          <br><span class="muted" style="font-size:11px;font-family:monospace"><?= e($r['email']) ?></span>
        </td>
        <td>
          <div class="ac-summary">
            <strong><?= $itemCount ?> ürün</strong> · <span style="color:var(--gold)"><?= money($cartTotal) ?></span>
            <br>
            <?php $first = array_slice($cart, 0, 2); foreach ($first as $it): ?>
              · <?= e(mb_substr($it['name'] ?? '', 0, 40)) ?> × <?= (int)($it['qty'] ?? 1) ?><br>
            <?php endforeach; ?>
            <?php if ($itemCount > 2): ?><span class="muted">+ <?= $itemCount - 2 ?> daha…</span><?php endif; ?>
          </div>
        </td>
        <td><span class="ac-step" style="background:rgba(<?= $r['reminder_step']>1?'201,162,75,.18':'107,170,80,.14' ?>);color:<?= $stepColors[(int)$r['reminder_step']] ?>"><?= e($stepLabels[(int)$r['reminder_step']]) ?></span></td>
        <td style="font-size:12px;color:var(--muted-text)"><?= $r['last_reminder_at'] ? e($r['last_reminder_at']) : '<span class="muted">—</span>' ?></td>
        <td>
          <?php if ($r['coupon_code']): ?>
            <code style="font-size:11px;background:rgba(201,162,75,.15);color:var(--gold);padding:3px 8px;border-radius:4px;font-weight:600"><?= e($r['coupon_code']) ?></code>
          <?php else: ?>
            <span class="muted">—</span>
          <?php endif; ?>
        </td>
        <td style="font-size:12px;color:var(--muted-text);white-space:nowrap"><?= e($r['updated_at']) ?></td>
        <td style="white-space:nowrap">
          <?php if ((int)$r['reminder_step'] < 3): ?>
          <form method="post" style="display:inline">
            <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="action" value="send_now">
            <input type="hidden" name="cart_id" value="<?= (int)$r['id'] ?>">
            <button class="btn btn-secondary btn-sm" type="submit" title="Bir sonraki cron'da bu sepeti hatırlat">↻ Hatırlat</button>
          </form>
          <?php endif; ?>
          <form method="post" style="display:inline">
            <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="action" value="reset">
            <input type="hidden" name="cart_id" value="<?= (int)$r['id'] ?>">
            <button class="btn btn-secondary btn-sm" type="submit" onclick="return confirm('Tüm hatırlatmalar sıfırlansın mı?')" title="Adım sıfırla">⟲</button>
          </form>
          <form method="post" style="display:inline">
            <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="cart_id" value="<?= (int)$r['id'] ?>">
            <button class="btn btn-secondary btn-sm" type="submit" onclick="return confirm('Bu kayıt silinsin mi?')" title="Sil" style="color:#e4a3a3">✕</button>
          </form>
        </td>
      </tr>
    <?php endforeach; else: ?>
      <tr><td colspan="7" class="muted" style="padding:30px;text-align:center">Filtreye uyan sepet yok.</td></tr>
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
  <span style="padding:6px 12px;color:var(--muted-text)"><?= number_format($total) ?> kayıt</span>
</div>
<?php endif; ?>

<p class="muted" style="margin-top:14px;font-size:12px;line-height:1.6">
  <strong>İpucu:</strong> Hatırlatmalar <code>cron/abandoned-cart.php</code> tarafından günlük gönderilir.
  "↻ Hatırlat" butonu, kayıt için bir sonraki cron çalışmasında hatırlatma tetikler. 30 gün geçen kayıtlar otomatik olarak işlenmez.
</p>

<?php require_once __DIR__ . '/core/footer.php'; ?>
