<?php
$page='reviews'; $title='Ürün Yorumları';
require_once __DIR__ . '/core/auth.php';

if ($_SERVER['REQUEST_METHOD']==='POST' && csrf_check($_POST['csrf'] ?? null)) {
    $a = $_POST['action'] ?? '';
    $id = (int)$_POST['id'];
    if ($a === 'approve')   { db()->prepare('UPDATE product_reviews SET is_approved=1 WHERE id=?')->execute([$id]); flash_set('ok','Yorum onaylandı.'); }
    if ($a === 'unapprove') { db()->prepare('UPDATE product_reviews SET is_approved=0 WHERE id=?')->execute([$id]); flash_set('ok','Yorum yayından kaldırıldı.'); }
    if ($a === 'delete')    { db()->prepare('DELETE FROM product_reviews WHERE id=?')->execute([$id]); flash_set('ok','Silindi.'); }
    redirect('reviews.php' . (isset($_GET['filter']) ? '?filter='.urlencode($_GET['filter']) : ''));
}

$filter = $_GET['filter'] ?? 'pending';
$where = $filter === 'approved' ? 'is_approved=1' : ($filter === 'all' ? '1=1' : 'is_approved=0');
$rows = db()->query("SELECT r.*, p.name AS product_name, p.slug FROM product_reviews r LEFT JOIN products p ON p.id=r.product_id WHERE $where ORDER BY r.created_at DESC LIMIT 200")->fetchAll();
$counts = db()->query('SELECT SUM(is_approved=0) p, SUM(is_approved=1) a, COUNT(*) t FROM product_reviews')->fetch();

require_once __DIR__ . '/core/header.php';
?>

<div class="kpis">
  <div class="kpi"><div class="lbl">Onay Bekleyen</div><div class="val"><?= (int)$counts['p'] ?></div></div>
  <div class="kpi"><div class="lbl">Yayında</div><div class="val"><?= (int)$counts['a'] ?></div></div>
  <div class="kpi"><div class="lbl">Toplam</div><div class="val"><?= (int)$counts['t'] ?></div></div>
</div>

<div class="panel">
  <div class="toolbar">
    <div class="btn-row">
      <a class="btn btn-secondary btn-sm" href="?filter=pending"  style="<?= $filter==='pending' ?'background:var(--cream);border-color:var(--ink)':'' ?>">Bekleyen (<?= (int)$counts['p'] ?>)</a>
      <a class="btn btn-secondary btn-sm" href="?filter=approved" style="<?= $filter==='approved'?'background:var(--cream);border-color:var(--ink)':'' ?>">Yayında (<?= (int)$counts['a'] ?>)</a>
      <a class="btn btn-secondary btn-sm" href="?filter=all"      style="<?= $filter==='all'     ?'background:var(--cream);border-color:var(--ink)':'' ?>">Tümü</a>
    </div>
  </div>

  <?php if (!$rows): ?>
    <p class="muted">Bu filtreye uyan yorum yok.</p>
  <?php else: ?>
    <?php require_once __DIR__ . '/../includes/reviews.php'; ?>
    <table>
      <thead><tr><th>Ürün</th><th>Yazar</th><th>Puan</th><th>Yorum</th><th>Tarih</th><th>Durum</th><th></th></tr></thead>
      <tbody>
      <?php foreach ($rows as $r): ?>
        <tr>
          <td>
            <?php if ($r['slug']): ?>
              <a href="../urun/<?= e($r['slug']) ?>" target="_blank" style="color:var(--ink)"><?= e($r['product_name']) ?></a>
            <?php else: ?>
              <span class="muted">(silinmiş ürün)</span>
            <?php endif; ?>
          </td>
          <td>
            <?= e($r['author_name']) ?>
            <?php if ($r['is_verified_buyer']): ?><br><small class="muted">✓ Doğrulanmış</small><?php endif; ?>
          </td>
          <td><?= star_html((float)$r['rating'], 12) ?></td>
          <td style="max-width:400px">
            <?php if (!empty($r['title'])): ?><strong style="font-size:13px"><?= e($r['title']) ?></strong><br><?php endif; ?>
            <span style="font-size:13px;color:var(--muted-text);line-height:1.5"><?= nl2br(e(mb_substr($r['body'], 0, 240))) ?><?= mb_strlen($r['body'])>240?'…':'' ?></span>
            <?php
              /* Faz 7.E: Yorum fotoğrafları */
              $rvMedia = !empty($r['media']) ? json_decode($r['media'], true) : [];
              if ($rvMedia):
            ?>
              <div style="display:flex;gap:6px;flex-wrap:wrap;margin-top:8px">
                <?php foreach ($rvMedia as $mUrl): ?>
                  <a href="<?= e(SITE_URL . $mUrl) ?>" target="_blank" style="display:block;width:52px;height:52px;border-radius:5px;overflow:hidden;border:1px solid var(--border)">
                    <img src="<?= e(SITE_URL . $mUrl) ?>" alt="Yorum görseli" style="width:100%;height:100%;object-fit:cover" loading="lazy">
                  </a>
                <?php endforeach; ?>
              </div>
            <?php endif; ?>
          </td>
          <td style="font-size:12px;color:var(--muted-text)"><?= date('d.m.Y', strtotime($r['created_at'])) ?></td>
          <td><span class="status <?= $r['is_approved']?'paid':'pending' ?>"><?= $r['is_approved']?'Yayında':'Bekliyor' ?></span></td>
          <td class="actions">
            <?php if (!$r['is_approved']): ?>
              <form method="post" style="display:inline">
                <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="action" value="approve">
                <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                <button class="btn btn-primary btn-sm">Onayla</button>
              </form>
            <?php else: ?>
              <form method="post" style="display:inline">
                <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="action" value="unapprove">
                <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                <button class="btn btn-secondary btn-sm">Yayından Kaldır</button>
              </form>
            <?php endif; ?>
            <form method="post" style="display:inline" onsubmit="return confirm('Bu yorum silinsin mi?')">
              <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
              <input type="hidden" name="action" value="delete">
              <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
              <button class="btn btn-danger btn-sm">Sil</button>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>
</div>

<?php require_once __DIR__ . '/core/footer.php'; ?>
