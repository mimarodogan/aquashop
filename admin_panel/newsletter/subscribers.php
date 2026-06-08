<?php
$page='newsletter'; $title='Bülten Aboneleri';
require_once __DIR__ . '/../core/auth.php';

if ($_SERVER['REQUEST_METHOD']==='POST' && csrf_check($_POST['csrf'] ?? null)) {
    $a = $_POST['action'] ?? '';
    if ($a==='delete')   db()->prepare('DELETE FROM newsletter_subscribers WHERE id=?')->execute(array((int)$_POST['id']));
    if ($a==='deactivate') db()->prepare('UPDATE newsletter_subscribers SET is_active=0, unsubscribed_at=NOW() WHERE id=?')->execute(array((int)$_POST['id']));
    if ($a==='activate')   db()->prepare('UPDATE newsletter_subscribers SET is_active=1, unsubscribed_at=NULL WHERE id=?')->execute(array((int)$_POST['id']));
    if ($a==='add') {
        $em = trim($_POST['email'] ?? '');
        if (filter_var($em, FILTER_VALIDATE_EMAIL)) {
            $tok = bin2hex(random_bytes(16));
            try { db()->prepare('INSERT INTO newsletter_subscribers (email,token,source) VALUES (?,?,?) ON DUPLICATE KEY UPDATE is_active=1')->execute(array($em,$tok,'admin')); flash_set('ok','Eklendi.'); }
            catch (Exception $e) { flash_set('err','Eklenemedi.'); }
        } else flash_set('err','Geçersiz e-posta.');
    }
    if ($a==='export') {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=aboneler.csv');
        $out = fopen('php://output','w');
        fputs($out, "\xEF\xBB\xBF");
        fputcsv($out, array('Email','Durum','Kayıt'));
        $rows = db()->query('SELECT email,is_active,subscribed_at FROM newsletter_subscribers ORDER BY subscribed_at DESC')->fetchAll();
        foreach ($rows as $r) fputcsv($out, array($r['email'], $r['is_active']?'aktif':'pasif', $r['subscribed_at']));
        fclose($out); exit;
    }
    redirect('subscribers.php');
}

$q = trim($_GET['q'] ?? '');
$f = $_GET['f'] ?? 'all';
$where=array(); $args=array();
if ($q !== '') { $where[]='email LIKE ?'; $args[]="%$q%"; }
if ($f==='active')   $where[]='is_active=1';
if ($f==='inactive') $where[]='is_active=0';
$sql = 'SELECT * FROM newsletter_subscribers';
if ($where) $sql .= ' WHERE ' . implode(' AND ', $where);
$sql .= ' ORDER BY subscribed_at DESC';
$st = db()->prepare($sql); $st->execute($args); $rows = $st->fetchAll();

$cActive = (int)db()->query('SELECT COUNT(*) FROM newsletter_subscribers WHERE is_active=1')->fetchColumn();
$cTotal  = (int)db()->query('SELECT COUNT(*) FROM newsletter_subscribers')->fetchColumn();
require_once __DIR__ . '/../core/header.php';
?>
<div class="kpis" style="grid-template-columns:repeat(3,1fr)">
  <div class="kpi"><div class="lbl">Aktif Abone</div><div class="val"><?= $cActive ?></div></div>
  <div class="kpi"><div class="lbl">Toplam</div><div class="val"><?= $cTotal ?></div></div>
  <div class="kpi"><div class="lbl">Kampanya</div><div class="val"><a href="campaigns.php" style="color:var(--gold)">Yönet →</a></div></div>
</div>

<div class="panel">
  <div class="toolbar">
    <form class="search" method="get" style="display:flex;gap:8px;flex:1;max-width:520px">
      <input type="text" name="q" value="<?= e($q) ?>" placeholder="E-posta ara…">
      <select name="f" onchange="this.form.submit()" style="padding:10px 14px;background:transparent;border:1px solid var(--gold-border);border-radius:5px;color:var(--champagne)">
        <option value="all" <?= $f==='all'?'selected':'' ?>>Tümü</option>
        <option value="active"   <?= $f==='active'?'selected':'' ?>>Aktif</option>
        <option value="inactive" <?= $f==='inactive'?'selected':'' ?>>Pasif</option>
      </select>
    </form>
    <div style="display:flex;gap:8px">
      <form method="post"><input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="export"><button class="btn btn-secondary btn-sm">CSV Dışa Aktar</button></form>
      <a class="btn btn-primary btn-sm" href="campaigns.php?action=new">+ Kampanya Oluştur</a>
    </div>
  </div>

  <form method="post" style="display:flex;gap:8px;margin-bottom:18px;max-width:520px">
    <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
    <input type="hidden" name="action" value="add">
    <input type="email" name="email" placeholder="Manuel abone ekle…" style="flex:1;padding:10px 14px;background:transparent;border:1px solid var(--gold-border);border-radius:5px;color:var(--champagne)">
    <button class="btn btn-secondary btn-sm">Ekle</button>
  </form>

  <table>
    <thead><tr><th>E-posta</th><th>Durum</th><th>Kaynak</th><th>Kayıt</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($rows as $r): ?>
      <tr>
        <td><strong style="color:var(--champagne)"><?= e($r['email']) ?></strong></td>
        <td><span class="status <?= $r['is_active']?'paid':'cancelled' ?>"><?= $r['is_active']?'aktif':'pasif' ?></span></td>
        <td class="muted"><?= e($r['source'] ?? '-') ?></td>
        <td class="muted" style="font-size:12px"><?= e($r['subscribed_at']) ?></td>
        <td>
          <?php if ($r['is_active']): ?>
            <form method="post" style="display:inline"><input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="deactivate"><input type="hidden" name="id" value="<?= (int)$r['id'] ?>"><button class="btn btn-secondary btn-sm">Pasif Yap</button></form>
          <?php else: ?>
            <form method="post" style="display:inline"><input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="activate"><input type="hidden" name="id" value="<?= (int)$r['id'] ?>"><button class="btn btn-secondary btn-sm">Aktifleştir</button></form>
          <?php endif; ?>
          <form method="post" style="display:inline" onsubmit="return confirm('Silinsin mi?')"><input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int)$r['id'] ?>"><button class="btn btn-secondary btn-sm">Sil</button></form>
        </td>
      </tr>
    <?php endforeach; ?>
    <?php if (!$rows): ?><tr><td colspan="5" class="muted">Sonuç yok.</td></tr><?php endif; ?>
    </tbody>
  </table>
</div>
<?php require_once __DIR__ . '/../core/footer.php'; ?>
