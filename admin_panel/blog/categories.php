<?php
$page='blog_categories'; $title='Blog Kategorileri';
require_once __DIR__ . '/../core/auth.php';

if ($_SERVER['REQUEST_METHOD']==='POST' && csrf_check(isset($_POST['csrf'])?$_POST['csrf']:null)) {
    $action = isset($_POST['action']) ? $_POST['action'] : '';
    if ($action === 'create') {
        $n = trim(isset($_POST['name']) ? $_POST['name'] : '');
        $s = trim(isset($_POST['slug']) ? $_POST['slug'] : '');
        if ($s === '') {
            $s = strtolower(strtr($n, array('ğ'=>'g','ü'=>'u','ş'=>'s','ı'=>'i','ö'=>'o','ç'=>'c','Ğ'=>'g','Ü'=>'u','Ş'=>'s','İ'=>'i','Ö'=>'o','Ç'=>'c')));
            $s = trim(preg_replace('~[^a-z0-9]+~i','-',$s),'-');
        }
        if ($n) { try { db()->prepare('INSERT INTO blog_categories (name,slug) VALUES (?,?)')->execute(array($n,$s)); flash_set('ok','Kategori eklendi.'); } catch (Exception $e) { flash_set('err','Eklenemedi (slug benzersiz olmalı).'); } }
    } elseif ($action === 'delete') {
        db()->prepare('DELETE FROM blog_categories WHERE id=?')->execute(array((int)$_POST['id']));
        flash_set('ok','Kategori silindi.');
    } elseif ($action === 'rename') {
        db()->prepare('UPDATE blog_categories SET name=?, slug=? WHERE id=?')
            ->execute(array(trim($_POST['name']), trim($_POST['slug']), (int)$_POST['id']));
        flash_set('ok','Kategori güncellendi.');
    }
    redirect('categories.php');
}

$cats = db()->query('SELECT c.*, (SELECT COUNT(*) FROM blog_posts WHERE category_id=c.id) AS cnt FROM blog_categories c ORDER BY name')->fetchAll();
require_once __DIR__ . '/../core/header.php';
?>
<div style="display:grid;grid-template-columns:1fr 1.6fr;gap:24px">
  <div class="panel">
    <h3>Yeni Kategori</h3>
    <form method="post" style="display:grid;gap:14px">
      <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
      <input type="hidden" name="action" value="create">
      <div class="field"><label>Ad</label><input name="name" required></div>
      <div class="field"><label>Slug (opsiyonel)</label><input name="slug"></div>
      <button class="btn btn-primary">Ekle</button>
    </form>
  </div>
  <div class="panel">
    <h3>Mevcut Kategoriler</h3>
    <table>
      <thead><tr><th>Ad</th><th>Slug</th><th>Yazı</th><th></th></tr></thead>
      <tbody>
      <?php foreach ($cats as $c): ?>
        <tr>
          <td>
            <form method="post" style="display:flex;gap:8px">
              <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
              <input type="hidden" name="action" value="rename">
              <input type="hidden" name="id" value="<?= (int)$c['id'] ?>">
              <input name="name" value="<?= e($c['name']) ?>" style="max-width:200px">
              <input name="slug" value="<?= e($c['slug']) ?>" style="max-width:160px">
              <button class="btn btn-secondary btn-sm">Kaydet</button>
            </form>
          </td>
          <td class="muted"><?= e($c['slug']) ?></td>
          <td><?= (int)$c['cnt'] ?></td>
          <td>
            <form method="post" onsubmit="return confirm('Silinsin mi?')"><input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int)$c['id'] ?>"><button class="btn btn-secondary btn-sm">Sil</button></form>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php require_once __DIR__ . '/../core/footer.php'; ?>
