<?php
$page='pages'; $title='Sayfalar';
require_once __DIR__ . '/../core/auth.php';
require_once __DIR__ . '/../../includes/legal_templates.php';

if ($_SERVER['REQUEST_METHOD']==='POST' && csrf_check(isset($_POST['csrf'])?$_POST['csrf']:null)) {
    if (($_POST['action'] ?? '')==='delete') {
        db()->prepare('DELETE FROM pages WHERE id=?')->execute(array((int)$_POST['id']));
        flash_set('ok','Sayfa silindi.');
        redirect('list.php');
    }
    if (($_POST['action'] ?? '')==='install_legal') {
        $overwrite = !empty($_POST['overwrite']);
        $s = legal_templates_install($overwrite);
        flash_set('ok',
            sprintf('%d yeni yasal sayfa oluşturuldu. %d güncellendi. %d atlandı (zaten vardı).',
                $s['created'], $s['updated'], $s['skipped'])
        );
        redirect('list.php');
    }
}

$rows = db()->query('SELECT * FROM pages ORDER BY title')->fetchAll();
require_once __DIR__ . '/../core/header.php';
?>
<div class="panel" style="margin-bottom:20px">
  <h3>Yasal Sayfa Şablonları (TKHK / KVKK Uyumlu)</h3>
  <p class="muted" style="margin-bottom:14px">Tek tıkla 4 yasal sayfayı (Ön Bilgilendirme, Mesafeli Satış, KVKK, İade & Değişim) hazır şablonlarla oluşturur. <strong>Ayarlar → Yasal & Mali</strong> bölümündeki şirket bilgilerin (ünvan, vergi no, adres) metinlere otomatik yerleşir. Sonradan istediğin gibi düzenleyebilirsin.</p>
  <div style="display:flex;gap:10px;flex-wrap:wrap">
    <form method="post" style="display:inline" onsubmit="return confirm('Eksik yasal sayfalar oluşturulacak (var olanlara dokunulmaz). Devam edilsin mi?')">
      <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
      <input type="hidden" name="action" value="install_legal">
      <button class="btn btn-primary btn-sm">📜 Eksik Yasal Sayfaları Oluştur</button>
    </form>
    <form method="post" style="display:inline" onsubmit="return confirm('TÜM 4 yasal sayfa fabrika şablonuyla ÜZERINE YAZILACAK. Mevcut düzenlemelerin kaybolur. Devam edilsin mi?')">
      <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
      <input type="hidden" name="action" value="install_legal">
      <input type="hidden" name="overwrite" value="1">
      <button class="btn btn-secondary btn-sm">🔄 Şablonları Üzerine Yaz (sıfırla)</button>
    </form>
  </div>
</div>

<div class="panel">
  <div class="toolbar">
    <p class="muted">Site içindeki kurumsal sayfaları (KVKK, Mesafeli Satış, vb.) buradan yönetin.</p>
    <a class="btn btn-primary btn-sm" href="edit.php">+ Yeni Sayfa</a>
  </div>
  <table>
    <thead><tr><th>Başlık</th><th>Slug</th><th>Durum</th><th>Güncellendi</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($rows as $p): ?>
      <tr>
        <td><strong style="color:var(--champagne)"><?= e($p['title']) ?></strong></td>
        <td class="muted"><?= e(url('page', ['slug'=>$p['slug']])) ?></td>
        <td><span class="status <?= $p['is_published']?'paid':'cancelled' ?>"><?= $p['is_published']?'yayında':'taslak' ?></span></td>
        <td class="muted" style="font-size:12px"><?= e($p['updated_at']) ?></td>
        <td>
          <a class="btn btn-secondary btn-sm" href="<?= e(url('page', ['slug'=>$p['slug']])) ?>" target="_blank">Görüntüle</a>
          <a class="btn btn-secondary btn-sm" href="edit.php?id=<?= (int)$p['id'] ?>">Düzenle</a>
          <form method="post" style="display:inline" onsubmit="return confirm('Silinsin mi?')">
            <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
            <button class="btn btn-secondary btn-sm">Sil</button>
          </form>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</div>
<?php require_once __DIR__ . '/../core/footer.php'; ?>
