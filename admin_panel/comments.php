<?php
$page='comments'; $title='Yorumlar';
require_once __DIR__ . '/core/auth.php';

if ($_SERVER['REQUEST_METHOD']==='POST' && csrf_check(isset($_POST['csrf'])?$_POST['csrf']:null)) {
    $a = isset($_POST['action']) ? $_POST['action'] : '';
    $id = (int)(isset($_POST['id']) ? $_POST['id'] : 0);
    if ($a==='approve') db()->prepare('UPDATE comments SET is_approved=1 WHERE id=?')->execute(array($id));
    if ($a==='unapprove') db()->prepare('UPDATE comments SET is_approved=0 WHERE id=?')->execute(array($id));
    if ($a==='delete')   db()->prepare('DELETE FROM comments WHERE id=?')->execute(array($id));
    flash_set('ok','Güncellendi.');
    redirect('comments.php' . (isset($_GET['filter']) ? '?filter='.urlencode($_GET['filter']) : ''));
}

$filter = isset($_GET['filter']) ? $_GET['filter'] : 'all';
$where=array(); $args=array();
if ($filter==='pending')   { $where[]='c.is_approved=0'; }
if ($filter==='approved')  { $where[]='c.is_approved=1'; }
if ($filter==='product')   { $where[]="c.target_type='product'"; }
if ($filter==='blog')      { $where[]="c.target_type='blog'"; }

$sql = "SELECT c.*, u.name AS author_name,
        CASE WHEN c.target_type='product' THEN (SELECT name FROM products WHERE id=c.target_id)
             ELSE (SELECT title FROM blog_posts WHERE id=c.target_id) END AS target_title,
        CASE WHEN c.target_type='product' THEN (SELECT slug FROM products WHERE id=c.target_id)
             ELSE (SELECT slug FROM blog_posts WHERE id=c.target_id) END AS target_slug
        FROM comments c JOIN users u ON u.id=c.user_id";
if ($where) $sql .= ' WHERE ' . implode(' AND ',$where);
$sql .= ' ORDER BY c.created_at DESC';
$st = db()->prepare($sql); $st->execute($args); $rows = $st->fetchAll();

$cPending = (int)db()->query('SELECT COUNT(*) FROM comments WHERE is_approved=0')->fetchColumn();
require_once __DIR__ . '/core/header.php';
?>
<div class="panel">
  <div class="toolbar">
    <div style="display:flex;gap:8px;flex-wrap:wrap">
      <a class="btn btn-secondary btn-sm" href="?filter=all"      style="<?= $filter==='all'?'color:var(--gold);border-color:var(--gold)':'' ?>">Tümü</a>
      <a class="btn btn-secondary btn-sm" href="?filter=pending"  style="<?= $filter==='pending'?'color:var(--gold);border-color:var(--gold)':'' ?>">Onay Bekleyen (<?= $cPending ?>)</a>
      <a class="btn btn-secondary btn-sm" href="?filter=approved" style="<?= $filter==='approved'?'color:var(--gold);border-color:var(--gold)':'' ?>">Onaylı</a>
      <a class="btn btn-secondary btn-sm" href="?filter=product"  style="<?= $filter==='product'?'color:var(--gold);border-color:var(--gold)':'' ?>">Ürün Yorumları</a>
      <a class="btn btn-secondary btn-sm" href="?filter=blog"     style="<?= $filter==='blog'?'color:var(--gold);border-color:var(--gold)':'' ?>">Blog Yorumları</a>
    </div>
  </div>

  <table>
    <thead><tr><th>Tarih</th><th>Yazan</th><th>Tür</th><th>İçerik</th><th>Yorum</th><th>Puan</th><th>Durum</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($rows as $c): ?>
      <tr style="<?= $c['is_approved']?'':'opacity:.7' ?>">
        <td class="muted" style="font-size:12px"><?= e($c['created_at']) ?></td>
        <td><?= e($c['author_name']) ?></td>
        <td><span class="status"><?= $c['target_type']==='product'?'Ürün':'Blog' ?></span></td>
        <td><?php $href = $c['target_type']==='product' ? '../product.php?slug=' : '../post.php?slug='; ?><a href="<?= e($href.$c['target_slug']) ?>" target="_blank" style="color:var(--gold)"><?= e($c['target_title'] ?? '-') ?></a></td>
        <td style="max-width:380px"><?= e(mb_substr($c['body'],0,180)) ?><?= mb_strlen($c['body'])>180?'…':'' ?></td>
        <td><?= $c['rating'] ? str_repeat('★',(int)$c['rating']) : '-' ?></td>
        <td><span class="status <?= $c['is_approved']?'paid':'cancelled' ?>"><?= $c['is_approved']?'onaylı':'beklemede' ?></span></td>
        <td>
          <?php if ($c['is_approved']): ?>
            <form method="post" style="display:inline"><input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="unapprove"><input type="hidden" name="id" value="<?= (int)$c['id'] ?>"><button class="btn btn-secondary btn-sm">Gizle</button></form>
          <?php else: ?>
            <form method="post" style="display:inline"><input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="approve"><input type="hidden" name="id" value="<?= (int)$c['id'] ?>"><button class="btn btn-secondary btn-sm">Onayla</button></form>
          <?php endif; ?>
          <form method="post" style="display:inline" onsubmit="return confirm('Silinsin mi?')"><input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int)$c['id'] ?>"><button class="btn btn-secondary btn-sm">Sil</button></form>
        </td>
      </tr>
    <?php endforeach; ?>
    <?php if (!$rows): ?><tr><td colspan="8" class="muted">Yorum yok.</td></tr><?php endif; ?>
    </tbody>
  </table>
</div>
<?php require_once __DIR__ . '/core/footer.php'; ?>
