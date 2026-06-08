<?php
$page='coupons'; $title='Kuponlar';
require_once __DIR__ . '/core/auth.php';

if ($_SERVER['REQUEST_METHOD']==='POST' && csrf_check($_POST['csrf'] ?? null)) {
    $a = $_POST['action'] ?? '';
    if ($a === 'save') {
        $id     = (int)($_POST['id'] ?? 0);
        $code   = strtoupper(trim($_POST['code'] ?? ''));
        $type   = in_array($_POST['type'] ?? '', ['percent','fixed','free_shipping'], true) ? $_POST['type'] : 'percent';
        $amount = (float)($_POST['amount'] ?? 0);
        $minCart= (float)($_POST['min_cart'] ?? 0);
        $maxDisc= ($_POST['max_discount'] ?? '') !== '' ? (float)$_POST['max_discount'] : null;
        $usageL = ($_POST['usage_limit'] ?? '') !== '' ? (int)$_POST['usage_limit'] : null;
        $perU   = max(0, (int)($_POST['per_user_limit'] ?? 1));
        $starts = trim($_POST['starts_at'] ?? '') ?: null;
        $ends   = trim($_POST['ends_at'] ?? '') ?: null;
        $en     = !empty($_POST['enabled']) ? 1 : 0;
        $notes  = trim($_POST['notes'] ?? '') ?: null;
        if ($code === '') { flash_set('err','Kupon kodu zorunlu.'); redirect('coupons.php'); }
        try {
            if ($id) {
                db()->prepare('UPDATE coupons SET code=?,type=?,amount=?,min_cart=?,max_discount=?,usage_limit=?,per_user_limit=?,starts_at=?,ends_at=?,enabled=?,notes=? WHERE id=?')
                    ->execute([$code,$type,$amount,$minCart,$maxDisc,$usageL,$perU,$starts,$ends,$en,$notes,$id]);
                flash_set('ok','Kupon güncellendi.');
            } else {
                db()->prepare('INSERT INTO coupons (code,type,amount,min_cart,max_discount,usage_limit,per_user_limit,starts_at,ends_at,enabled,notes) VALUES (?,?,?,?,?,?,?,?,?,?,?)')
                    ->execute([$code,$type,$amount,$minCart,$maxDisc,$usageL,$perU,$starts,$ends,$en,$notes]);
                flash_set('ok','Kupon eklendi.');
            }
        } catch (\PDOException $e) {
            flash_set('err', $e->errorInfo[1]==1062 ? 'Bu kupon kodu zaten var.' : 'Kayıt başarısız.');
        }
        redirect('coupons.php');
    }
    if ($a === 'delete') {
        db()->prepare('DELETE FROM coupons WHERE id=?')->execute([(int)$_POST['id']]);
        flash_set('ok','Silindi.'); redirect('coupons.php');
    }
    if ($a === 'toggle') {
        db()->prepare('UPDATE coupons SET enabled=1-enabled WHERE id=?')->execute([(int)$_POST['id']]);
        redirect('coupons.php');
    }
}

$editId = (int)($_GET['edit'] ?? 0);
$editing = null;
if ($editId) {
    $st = db()->prepare('SELECT * FROM coupons WHERE id=?'); $st->execute([$editId]); $editing = $st->fetch();
}
/* Filtreler: kaynak (manual / cartback / welcome / birthday) */
$source = $_GET['source'] ?? '';
$where  = []; $args = [];
switch ($source) {
    case 'auto':     $where[] = "(code LIKE 'CARTBACK%' OR code LIKE 'WELCOME%' OR code LIKE 'BDAY%')"; break;
    case 'cartback': $where[] = "code LIKE 'CARTBACK%'"; break;
    case 'welcome':  $where[] = "code LIKE 'WELCOME%'"; break;
    case 'birthday': $where[] = "code LIKE 'BDAY%'"; break;
    case 'manual':   $where[] = "(code NOT LIKE 'CARTBACK%' AND code NOT LIKE 'WELCOME%' AND code NOT LIKE 'BDAY%')"; break;
}
$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';
$st = db()->prepare("SELECT * FROM coupons $whereSql ORDER BY enabled DESC, id DESC");
$st->execute($args);
$rows = $st->fetchAll();
$totalCoupons = $source === '' ? count($rows) : (int)db()->query('SELECT COUNT(*) FROM coupons')->fetchColumn();

$totalRedemptions = 0; $totalDiscount = 0.0;
try {
    $totalRedemptions = (int)db()->query('SELECT COUNT(*) FROM coupon_redemptions')->fetchColumn();
    $totalDiscount    = (float)db()->query('SELECT COALESCE(SUM(amount),0) FROM coupon_redemptions')->fetchColumn();
} catch (\Throwable $e) {}

// Otomatik kupon istatistikleri
$autoStats = ['cartback'=>0,'welcome'=>0,'birthday'=>0];
try {
    $autoStats = [
        'cartback' => (int)db()->query("SELECT COUNT(*) FROM coupons WHERE code LIKE 'CARTBACK%'")->fetchColumn(),
        'welcome'  => (int)db()->query("SELECT COUNT(*) FROM coupons WHERE code LIKE 'WELCOME%'")->fetchColumn(),
        'birthday' => (int)db()->query("SELECT COUNT(*) FROM coupons WHERE code LIKE 'BDAY%'")->fetchColumn(),
    ];
} catch (\Throwable $e) {}

/* Source belirleme helper'ı (etiket için) */
function coupon_source(string $code): array {
    if (strncmp($code, 'CARTBACK', 8) === 0) return ['🛒 Terk Sepet', 'rgba(201,162,75,.2)', '#c8b560'];
    if (strncmp($code, 'WELCOME',  7) === 0) return ['👋 Exit-intent', 'rgba(107,170,80,.18)', '#9fce7d'];
    if (strncmp($code, 'BDAY',     4) === 0) return ['🎂 Doğum Günü', 'rgba(207,82,82,.16)', '#e4a3a3'];
    return ['Manuel', 'rgba(160,160,160,.12)', '#a8a090'];
}

require_once __DIR__ . '/core/header.php';
?>

<div class="kpis">
  <div class="kpi"><div class="lbl">Toplam Kupon</div><div class="val"><?= count($rows) ?></div></div>
  <div class="kpi"><div class="lbl">Aktif</div><div class="val"><?= count(array_filter($rows, fn($r)=>$r['enabled'])) ?></div></div>
  <div class="kpi"><div class="lbl">Kullanım</div><div class="val"><?= number_format($totalRedemptions,0,',','.') ?></div></div>
  <div class="kpi"><div class="lbl">Toplam İndirim</div><div class="val"><?= money($totalDiscount) ?></div></div>
</div>

<div class="panel">
  <h3><?= $editing ? 'Kuponu Düzenle: '.e($editing['code']) : 'Yeni Kupon' ?></h3>
  <form method="post" style="display:grid;gap:14px;max-width:760px">
    <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
    <input type="hidden" name="action" value="save">
    <input type="hidden" name="id" value="<?= (int)($editing['id'] ?? 0) ?>">

    <div class="row-2">
      <div class="field"><label>Kupon Kodu</label><input name="code" value="<?= e($editing['code'] ?? '') ?>" placeholder="OLIVE10" required style="text-transform:uppercase"></div>
      <div class="field"><label>Tip</label>
        <select name="type" id="coupon-type">
          <?php $t = $editing['type'] ?? 'percent'; ?>
          <option value="percent" <?= $t==='percent'?'selected':'' ?>>Yüzde indirim (%)</option>
          <option value="fixed"   <?= $t==='fixed'?'selected':'' ?>>Sabit tutar (₺)</option>
          <option value="free_shipping" <?= $t==='free_shipping'?'selected':'' ?>>Ücretsiz kargo</option>
        </select>
      </div>
    </div>

    <div class="row-2" id="amount-row">
      <div class="field"><label>Miktar (% veya ₺)</label><input name="amount" type="number" step="0.01" min="0" value="<?= e($editing['amount'] ?? '10') ?>"></div>
      <div class="field"><label>Maks. İndirim Tutarı (₺, sadece %)</label><input name="max_discount" type="number" step="0.01" min="0" value="<?= e($editing['max_discount'] ?? '') ?>" placeholder="örn. 100"></div>
    </div>

    <div class="row-2">
      <div class="field"><label>Min. Sepet Tutarı (₺)</label><input name="min_cart" type="number" step="0.01" min="0" value="<?= e($editing['min_cart'] ?? '0') ?>"></div>
      <div class="field"><label>Toplam Kullanım Limiti</label><input name="usage_limit" type="number" min="0" value="<?= e($editing['usage_limit'] ?? '') ?>" placeholder="boş = sınırsız"></div>
    </div>

    <div class="row-2">
      <div class="field"><label>Kullanıcı Başına Limit</label><input name="per_user_limit" type="number" min="0" value="<?= e($editing['per_user_limit'] ?? '1') ?>"><small class="muted">0 = sınırsız</small></div>
      <div class="field"><label>Notlar (admin için)</label><input name="notes" value="<?= e($editing['notes'] ?? '') ?>" placeholder="örn. Yeni Yıl 2026"></div>
    </div>

    <div class="row-2">
      <div class="field"><label>Başlangıç Tarihi</label><input name="starts_at" type="datetime-local" value="<?= $editing && $editing['starts_at'] ? date('Y-m-d\TH:i', strtotime($editing['starts_at'])) : '' ?>"></div>
      <div class="field"><label>Bitiş Tarihi</label><input name="ends_at" type="datetime-local" value="<?= $editing && $editing['ends_at'] ? date('Y-m-d\TH:i', strtotime($editing['ends_at'])) : '' ?>"></div>
    </div>

    <label style="display:flex;gap:10px;align-items:center"><input type="checkbox" name="enabled" value="1" <?= (!isset($editing) || $editing['enabled'])?'checked':'' ?>> Aktif</label>

    <div class="btn-row">
      <button class="btn btn-primary"><?= $editing?'Güncelle':'Kupon Oluştur' ?></button>
      <?php if ($editing): ?><a class="btn btn-secondary" href="coupons.php">Vazgeç</a><?php endif; ?>
    </div>
  </form>
</div>

<div class="panel">
  <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:14px;margin-bottom:14px">
    <h3 style="margin:0">Kupon Listesi</h3>
    <div style="display:flex;gap:6px;flex-wrap:wrap">
      <a class="btn btn-secondary btn-sm" href="coupons.php" style="<?= $source===''?'background:rgba(201,162,75,.1);color:var(--gold)':'' ?>">Tümü (<?= $totalCoupons ?>)</a>
      <a class="btn btn-secondary btn-sm" href="?source=manual" style="<?= $source==='manual'?'background:rgba(201,162,75,.1);color:var(--gold)':'' ?>">Manuel</a>
      <a class="btn btn-secondary btn-sm" href="?source=auto" style="<?= $source==='auto'?'background:rgba(201,162,75,.1);color:var(--gold)':'' ?>">Otomatik (<?= $autoStats['cartback']+$autoStats['welcome']+$autoStats['birthday'] ?>)</a>
      <a class="btn btn-secondary btn-sm" href="?source=cartback" style="<?= $source==='cartback'?'background:rgba(201,162,75,.1);color:var(--gold)':'' ?>" title="Terk edilmiş sepet kurtarma">🛒 <?= $autoStats['cartback'] ?></a>
      <a class="btn btn-secondary btn-sm" href="?source=welcome" style="<?= $source==='welcome'?'background:rgba(201,162,75,.1);color:var(--gold)':'' ?>" title="Exit-intent newsletter capture">👋 <?= $autoStats['welcome'] ?></a>
      <a class="btn btn-secondary btn-sm" href="?source=birthday" style="<?= $source==='birthday'?'background:rgba(201,162,75,.1);color:var(--gold)':'' ?>" title="Doğum günü kuponu">🎂 <?= $autoStats['birthday'] ?></a>
    </div>
  </div>
  <?php if (!$rows): ?>
    <p class="muted">Bu filtreye uyan kupon yok.</p>
  <?php else: ?>
    <table>
      <thead><tr><th>Kod</th><th>Kaynak</th><th>Tip</th><th>Değer</th><th>Min. Sepet</th><th>Kullanım</th><th>Süre</th><th>Durum</th><th></th></tr></thead>
      <tbody>
      <?php try { foreach ($rows as $c): $src = coupon_source($c['code']); ?>
        <tr style="<?= $c['enabled']?'':'opacity:.55' ?>">
          <td><strong><?= e($c['code']) ?></strong><?php if ($c['notes']): ?><br><small class="muted"><?= e($c['notes']) ?></small><?php endif; ?></td>
          <td><span style="background:<?= $src[1] ?>;color:<?= $src[2] ?>;padding:3px 9px;border-radius:11px;font-size:11px;font-weight:500;white-space:nowrap"><?= $src[0] ?></span></td>
          <td><span class="chip"><?= (['percent'=>'%','fixed'=>'₺','free_shipping'=>'kargo'][$c['type'] ?? ''] ?? '?') ?></span></td>
          <td><?= ($c['type'] ?? '') ==='percent' ? '%'.(int)$c['amount'] : (($c['type'] ?? '')==='fixed' ? money($c['amount']) : 'Ücretsiz Kargo') ?></td>
          <td><?= ($c['min_cart'] ?? 0) > 0 ? money($c['min_cart']) : '—' ?></td>
          <td><?= (int)($c['usage_count'] ?? 0) ?><?= ($c['usage_limit'] ?? null) !== null ? ' / ' . (int)$c['usage_limit'] : '' ?></td>
          <?php
            $ts_start = $c['starts_at'] ? @strtotime($c['starts_at']) : false;
            $ts_end   = $c['ends_at']   ? @strtotime($c['ends_at'])   : false;
          ?>
          <td style="font-size:12px"><?= ($ts_start > 0) ? date('d.m.Y', $ts_start) : '∅' ?> → <?= ($ts_end > 0) ? date('d.m.Y', $ts_end) : '∅' ?></td>
          <td>
            <form method="post" style="display:inline">
              <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
              <input type="hidden" name="action" value="toggle">
              <input type="hidden" name="id" value="<?= (int)$c['id'] ?>">
              <button class="btn btn-secondary btn-sm"><?= $c['enabled']?'Kapat':'Aç' ?></button>
            </form>
          </td>
          <td class="actions">
            <a class="btn btn-secondary btn-sm" href="?edit=<?= (int)$c['id'] ?>">Düzenle</a>
            <form method="post" style="display:inline" onsubmit="return confirm('Bu kupon silinsin mi?')">
              <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
              <input type="hidden" name="action" value="delete">
              <input type="hidden" name="id" value="<?= (int)$c['id'] ?>">
              <button class="btn btn-danger btn-sm">Sil</button>
            </form>
          </td>
        </tr>
      <?php endforeach; } catch (\Throwable $__ex) { ?>
        <tr><td colspan="9" style="color:#c0392b;padding:12px;font-size:12px;font-family:monospace">
          ⚠️ Satır render hatası: <?= htmlspecialchars($__ex->getMessage()) ?>
          <br><small style="color:#888"><?= htmlspecialchars(basename($__ex->getFile()) . ':' . $__ex->getLine()) ?></small>
        </td></tr>
      <?php } ?>
      </tbody>
    </table>
  <?php endif; ?>
</div>

<?php require_once __DIR__ . '/core/footer.php'; ?>
