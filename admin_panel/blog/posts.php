<?php
$page='blog'; $title='Blog Yazıları';
require_once __DIR__ . '/../core/auth.php';

if ($_SERVER['REQUEST_METHOD']==='POST' && csrf_check(isset($_POST['csrf'])?$_POST['csrf']:null)) {
    if (($_POST['action'] ?? '')==='delete') {
        db()->prepare('DELETE FROM blog_posts WHERE id=?')->execute(array((int)$_POST['id']));
        flash_set('ok','Yazı silindi.');
        redirect('posts.php');
    }
    if (($_POST['action'] ?? '')==='toggle') {
        db()->prepare('UPDATE blog_posts SET is_published = 1 - is_published WHERE id=?')->execute(array((int)$_POST['id']));
        redirect('posts.php');
    }
}

$q = isset($_GET['q']) ? trim($_GET['q']) : '';
$sql = "SELECT p.*, c.name AS cat_name FROM blog_posts p LEFT JOIN blog_categories c ON c.id=p.category_id";
$args = array();
if ($q !== '') { $sql .= " WHERE p.title LIKE ?"; $args[] = "%$q%"; }
$sql .= " ORDER BY COALESCE(p.published_at, p.created_at) DESC";
$st = db()->prepare($sql); $st->execute($args); $rows = $st->fetchAll();

require_once __DIR__ . '/../core/header.php';
?>
<div class="panel">
  <div class="toolbar">
    <form class="search" method="get"><input type="text" name="q" value="<?= e($q) ?>" placeholder="Yazı ara…"></form>
    <div style="display:flex;gap:8px">
      <a class="btn btn-secondary btn-sm" href="categories.php">Kategoriler</a>
      <a class="btn btn-primary btn-sm" href="post-edit.php">+ Yeni Yazı</a>
    </div>
  </div>
  <table>
    <thead><tr><th>Başlık</th><th>Kategori</th><th>Durum</th><th>Görüntülenme</th><th>Yayın</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($rows as $p): ?>
      <tr>
        <td><strong style="color:var(--champagne)"><?= e($p['title']) ?></strong><br><span class="muted" style="font-size:12px"><?= e($p['slug']) ?></span></td>
        <td><?= e($p['cat_name'] ?? '-') ?></td>
        <td><span class="status <?= $p['is_published']?'paid':'cancelled' ?>"><?= $p['is_published']?'yayında':'taslak' ?></span></td>
        <td><?= (int)$p['views'] ?></td>
        <td class="muted" style="font-size:12px"><?= e($p['published_at'] ?? $p['created_at']) ?></td>
        <td>
          <a class="btn btn-secondary btn-sm" href="<?= e(url('blog_post', ['slug'=>$p['slug']])) ?>" target="_blank">Görüntüle</a>
          <a class="btn btn-secondary btn-sm" href="post-edit.php?id=<?= (int)$p['id'] ?>">Düzenle</a>
          <form method="post" style="display:inline"><input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="toggle"><input type="hidden" name="id" value="<?= (int)$p['id'] ?>"><button class="btn btn-secondary btn-sm"><?= $p['is_published']?'Taslağa Al':'Yayınla' ?></button></form>
          <form method="post" style="display:inline" onsubmit="return confirm('Silinsin mi?')"><input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int)$p['id'] ?>"><button class="btn btn-secondary btn-sm">Sil</button></form>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</div>
<?php require_once __DIR__ . '/../core/footer.php'; ?>
