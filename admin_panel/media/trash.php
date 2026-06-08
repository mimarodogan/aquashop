<?php
$page='media_trash'; $title='Medya Çöp Kutusu';
require_once __DIR__ . '/../core/auth.php';
require_once __DIR__ . '/../../includes/media.php';

media_purge_old();

if ($_SERVER['REQUEST_METHOD']==='POST' && csrf_check(isset($_POST['csrf'])?$_POST['csrf']:null)) {
    $action = isset($_POST['action']) ? $_POST['action'] : '';
    if ($action === 'restore') { media_restore((int)$_POST['id']); flash_set('ok','Geri yüklendi.'); }
    if ($action === 'hard')    { media_hard_delete((int)$_POST['id']); flash_set('ok','Kalıcı olarak silindi.'); }
    if ($action === 'empty') {
        $rows = db()->query('SELECT id FROM media WHERE deleted_at IS NOT NULL')->fetchAll();
        foreach ($rows as $r) media_hard_delete($r['id']);
        flash_set('ok','Çöp kutusu boşaltıldı.');
    }
    redirect('trash.php');
}

$rows = db()->query('SELECT * FROM media WHERE deleted_at IS NOT NULL ORDER BY deleted_at DESC')->fetchAll();
require_once __DIR__ . '/../core/header.php';
?>
<div class="panel">
  <div class="toolbar">
    <p class="muted">Çöp kutusundaki dosyalar 30 gün sonra otomatik olarak kalıcı silinir.</p>
    <div style="display:flex;gap:8px">
      <a class="btn btn-secondary btn-sm" href="library.php">← Kütüphaneye Dön</a>
      <?php if ($rows): ?><form method="post" onsubmit="return confirm('Çöp kutusu boşaltılsın mı? Bu işlem geri alınamaz.')"><input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="empty"><button class="btn btn-secondary btn-sm">Çöpü Boşalt</button></form><?php endif; ?>
    </div>
  </div>
  <table>
    <thead><tr><th>Önizleme</th><th>Dosya</th><th>Boyut</th><th>Silme Tarihi</th><th>Kalan</th><th></th></tr></thead>
    <tbody>
      <?php foreach ($rows as $m): $days = max(0, 30 - (int)floor((time() - strtotime($m['deleted_at']))/86400)); ?>
        <tr>
          <td><img src="<?= e($m['path']) ?>" alt="" style="width:60px;height:60px;object-fit:cover;border-radius:4px;border:1px solid var(--gold-border)"></td>
          <td><strong style="color:var(--champagne)"><?= e($m['filename']) ?></strong><br><span class="muted" style="font-size:11px"><?= (int)$m['width'] ?>×<?= (int)$m['height'] ?></span></td>
          <td><?= number_format($m['size']/1024,0) ?> KB</td>
          <td class="muted" style="font-size:12px"><?= e($m['deleted_at']) ?></td>
          <td><?= $days ?> gün</td>
          <td>
            <form method="post" style="display:inline"><input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="restore"><input type="hidden" name="id" value="<?= (int)$m['id'] ?>"><button class="btn btn-secondary btn-sm">Geri Yükle</button></form>
            <form method="post" style="display:inline" onsubmit="return confirm('Kalıcı olarak silinsin mi?')"><input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="hard"><input type="hidden" name="id" value="<?= (int)$m['id'] ?>"><button class="btn btn-secondary btn-sm">Kalıcı Sil</button></form>
          </td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$rows): ?><tr><td colspan="6" class="muted">Çöp kutusu boş.</td></tr><?php endif; ?>
    </tbody>
  </table>
</div>
<?php require_once __DIR__ . '/../core/footer.php'; ?>
