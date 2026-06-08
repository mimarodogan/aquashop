<?php
$page='banners'; $title='Bannerlar';
require_once __DIR__ . '/core/auth.php';
require_once __DIR__ . '/../includes/media.php';

if ($_SERVER['REQUEST_METHOD']==='POST' && csrf_check(isset($_POST['csrf'])?$_POST['csrf']:null)) {
    $a = isset($_POST['action']) ? $_POST['action'] : '';
    if ($a==='create') {
        $img=null;
        if (!empty($_FILES['image_file']['name'])) {
            $r = media_upload_from_files($_FILES['image_file']);
            if ($r['ok']) $img = $r['path'];
            else flash_set('err','Görsel yüklenemedi: '.$r['error']);
        }
        if (!$img) { flash_set('err','Banner görseli zorunlu.'); redirect('banners.php'); }
        $st=db()->prepare('INSERT INTO banners (image,link,title,alt,sort_order,is_active) VALUES (?,?,?,?,?,?)');
        $st->execute(array(
            $img,
            trim($_POST['link'] ?? ''),
            trim($_POST['title'] ?? ''),
            trim($_POST['alt'] ?? ''),
            (int)($_POST['sort_order'] ?? 0),
            !empty($_POST['is_active']) ? 1 : 0,
        ));
        flash_set('ok','Banner eklendi.');
    }
    if ($a==='update') {
        $id=(int)$_POST['id'];
        $img = $_POST['image'] ?? null;
        if (!empty($_FILES['image_file']['name'])) {
            $r = media_upload_from_files($_FILES['image_file']);
            if ($r['ok']) $img = $r['path'];
        }
        $st=db()->prepare('UPDATE banners SET image=?, link=?, title=?, alt=?, sort_order=?, is_active=? WHERE id=?');
        $st->execute(array(
            $img,
            trim($_POST['link'] ?? ''),
            trim($_POST['title'] ?? ''),
            trim($_POST['alt'] ?? ''),
            (int)($_POST['sort_order'] ?? 0),
            !empty($_POST['is_active']) ? 1 : 0,
            $id
        ));
        flash_set('ok','Banner güncellendi.');
    }
    if ($a==='delete') {
        db()->prepare('DELETE FROM banners WHERE id=?')->execute(array((int)$_POST['id']));
        flash_set('ok','Banner silindi.');
    }
    redirect('banners.php');
}

$rows = db()->query('SELECT * FROM banners ORDER BY sort_order ASC, id DESC')->fetchAll();
require_once __DIR__ . '/core/header.php';
?>
<style>
.banner-card {
  border: 1px solid var(--gold-border);
  border-radius: var(--radius);
  overflow: hidden;
  background: var(--olive-2);
  margin-bottom: 16px;
}
.banner-card-header {
  display: flex;
  align-items: center;
  gap: 16px;
  padding: 16px;
  background: rgba(201,162,75,.06);
  border-bottom: 1px solid var(--gold-border);
}
.banner-card-header img {
  width: 200px;
  height: 110px;
  object-fit: cover;
  border-radius: 6px;
  border: 1px solid var(--gold-border);
  flex-shrink: 0;
}
.banner-card-body {
  padding: 20px;
  display: grid;
  gap: 14px;
}
.banner-card-footer {
  padding: 14px 20px;
  border-top: 1px solid var(--gold-border);
  display: flex;
  gap: 10px;
  align-items: center;
  background: rgba(0,0,0,.15);
}
.banner-badge {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 4px 10px;
  border-radius: 999px;
  font-size: 11px;
  font-weight: 600;
  letter-spacing: .08em;
}
.banner-badge.active   { background: rgba(79,92,38,.3); color: #9ab54a; border: 1px solid #9ab54a55; }
.banner-badge.inactive { background: rgba(154,42,42,.2); color: #f5a3a3; border: 1px solid #9A2A2A44; }
</style>

<!-- Yeni Banner Ekle -->
<div class="panel">
  <h3>Yeni Banner Ekle</h3>
  <p class="muted" style="font-size:13px;margin-bottom:16px">Anasayfadaki slider'a yeni bir görsel ekleyin. Önerilen oran 16:9 veya 4:3. Görsel otomatik WebP'ye dönüştürülür.</p>
  <form method="post" enctype="multipart/form-data" style="display:grid;gap:16px;max-width:720px">
    <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
    <input type="hidden" name="action" value="create">

    <div class="field">
      <label>Görsel <span style="color:#f5a3a3">*</span></label>
      <input type="file" name="image_file" accept="image/*" required>
      <small class="muted">WebP'ye dönüştürülür ve sıkıştırılır. Önerilen oran 16:9 veya 4:3.</small>
    </div>

    <div class="row-2">
      <div class="field">
        <label>Banner Başlığı <small class="muted">(opsiyonel, görselin üzerine bindirilir)</small></label>
        <input name="title" placeholder="Örn: Yeni Sezon Akvaryumları">
        <small class="muted">Görselin üzerinde beyaz yazı olarak görünür.</small>
      </div>
      <div class="field">
        <label>Yönlendirme Linki <small class="muted">(tıklanınca gidilecek sayfa)</small></label>
        <input name="link" placeholder="Örn: products.php?cat=kampanya veya https://…">
        <small class="muted">Boş bırakırsanız banner tıklanamaz.</small>
      </div>
    </div>

    <div class="row-2">
      <div class="field">
        <label>Alt Metin <small class="muted">(erişilebilirlik &amp; SEO)</small></label>
        <input name="alt" placeholder="Görseli tarif eden kısa metin">
        <small class="muted">Ekran okuyucu kullanıcıları ve Google görseller için.</small>
      </div>
      <div class="field">
        <label>Sıra <small class="muted">(küçük sayı öne geçer)</small></label>
        <input type="number" name="sort_order" value="0" min="0">
        <small class="muted">0, 1, 2… şeklinde sıralayın.</small>
      </div>
    </div>

    <div>
      <label style="display:inline-flex;align-items:center;gap:10px;font-size:14px;cursor:pointer">
        <input type="checkbox" name="is_active" value="1" checked style="width:16px;height:16px">
        Aktif — hemen yayına al
      </label>
    </div>

    <div><button class="btn btn-primary">Banner Ekle</button></div>
  </form>
</div>

<!-- Mevcut Bannerlar -->
<div class="panel">
  <h3>Mevcut Bannerlar <span class="muted" style="font-weight:400;font-size:14px">(<?= count($rows) ?> adet)</span></h3>
  <?php if (!$rows): ?>
    <p class="muted">Henüz banner eklenmemiş.</p>
  <?php endif; ?>

  <?php foreach ($rows as $b): ?>
    <div class="banner-card">
      <!-- Başlık: önizleme + özet bilgi -->
      <div class="banner-card-header">
        <img src="<?= e($b['image']) ?>" alt="<?= e($b['alt']) ?>">
        <div style="flex:1;min-width:0">
          <div style="display:flex;align-items:center;gap:10px;margin-bottom:8px">
            <strong style="font-size:15px"><?= $b['title'] ? e($b['title']) : '<span class="muted" style="font-style:italic">Başlık yok</span>' ?></strong>
            <span class="banner-badge <?= $b['is_active']?'active':'inactive' ?>">
              <?= $b['is_active'] ? '● Aktif' : '○ Pasif' ?>
            </span>
          </div>
          <?php if ($b['link']): ?>
            <div class="muted" style="font-size:12px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">
              🔗 <?= e($b['link']) ?>
            </div>
          <?php endif; ?>
          <div class="muted" style="font-size:12px;margin-top:6px">Sıra: <?= (int)$b['sort_order'] ?></div>
        </div>
      </div>

      <!-- Düzenleme formu -->
      <form method="post" enctype="multipart/form-data">
        <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
        <input type="hidden" name="action" value="update">
        <input type="hidden" name="id" value="<?= (int)$b['id'] ?>">
        <input type="hidden" name="image" value="<?= e($b['image']) ?>">

        <div class="banner-card-body">
          <div class="field">
            <label>Görseli Değiştir <small class="muted">(seçmezseniz mevcut görsel korunur)</small></label>
            <input type="file" name="image_file" accept="image/*">
          </div>
          <div class="row-2">
            <div class="field">
              <label>Banner Başlığı</label>
              <input name="title" value="<?= e($b['title']) ?>" placeholder="Görselin üzerindeki metin (opsiyonel)">
            </div>
            <div class="field">
              <label>Yönlendirme Linki</label>
              <input name="link" value="<?= e($b['link']) ?>" placeholder="products.php?cat=… veya https://…">
            </div>
          </div>
          <div class="row-2">
            <div class="field">
              <label>Alt Metin (SEO / erişilebilirlik)</label>
              <input name="alt" value="<?= e($b['alt']) ?>" placeholder="Görseli tarif eden kısa metin">
            </div>
            <div class="field">
              <label>Sıra</label>
              <input type="number" name="sort_order" value="<?= (int)$b['sort_order'] ?>" min="0">
            </div>
          </div>
          <div>
            <label style="display:inline-flex;align-items:center;gap:10px;font-size:14px;cursor:pointer">
              <input type="checkbox" name="is_active" value="1" <?= $b['is_active']?'checked':'' ?> style="width:16px;height:16px">
              Aktif
            </label>
          </div>
        </div>

        <div class="banner-card-footer">
          <button class="btn btn-primary">Kaydet</button>
        </form>
          <form method="post" style="display:inline" onsubmit="return confirm('Bu banner silinsin mi?')">
            <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="id" value="<?= (int)$b['id'] ?>">
            <button class="btn btn-secondary" style="color:#f5a3a3;border-color:#9A2A2A">Sil</button>
          </form>
          <span class="muted" style="font-size:12px;margin-left:auto">Banner #<?= (int)$b['id'] ?></span>
        </div>
    </div>
  <?php endforeach; ?>
</div>

<?php require_once __DIR__ . '/core/footer.php'; ?>
