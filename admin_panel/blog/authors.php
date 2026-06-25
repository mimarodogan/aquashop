<?php
$page = 'blog_authors'; $title = 'Blog Yazarları';
require_once __DIR__ . '/../core/auth.php';
require_once __DIR__ . '/../../includes/media.php';

// ── Tabloyu oluştur (ilk çalışmada) ────────────────────────────
try {
    db()->exec("CREATE TABLE IF NOT EXISTS blog_authors (
        id          INT AUTO_INCREMENT PRIMARY KEY,
        name        VARCHAR(100) NOT NULL,
        title       VARCHAR(120) DEFAULT NULL COMMENT 'Unvan/rol — örn. Akvaryum Uzmanı',
        bio         TEXT         DEFAULT NULL,
        avatar      VARCHAR(255) DEFAULT NULL,
        website     VARCHAR(255) DEFAULT NULL,
        instagram   VARCHAR(100) DEFAULT NULL,
        twitter     VARCHAR(100) DEFAULT NULL,
        is_active   TINYINT(1)   NOT NULL DEFAULT 1,
        created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    // blog_posts tablosuna blog_author_id kolonu ekle (varsa atla)
    db()->exec("ALTER TABLE blog_posts ADD COLUMN IF NOT EXISTS blog_author_id INT NULL DEFAULT NULL");
} catch (\Throwable $e) { /* tablo zaten var */ }

// ── POST handler ────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf_check($_POST['csrf'] ?? null)) {
    $action = $_POST['action'] ?? '';

    if ($action === 'save') {
        $id        = (int)($_POST['id'] ?? 0);
        $name      = trim($_POST['name'] ?? '');
        $title_val = trim($_POST['title_val'] ?? '') ?: null;
        $bio       = trim($_POST['bio'] ?? '') ?: null;
        $website   = trim($_POST['website'] ?? '') ?: null;
        $instagram = trim(ltrim($_POST['instagram'] ?? '', '@')) ?: null;
        $twitter   = trim(ltrim($_POST['twitter'] ?? '', '@')) ?: null;
        $isActive  = !empty($_POST['is_active']) ? 1 : 0;
        $avatar    = trim($_POST['avatar_current'] ?? '') ?: null;

        // Yeni avatar yüklendiyse — yazar avatarları biyografi kartında küçük (~80-120px) gösterilir
        if (!empty($_FILES['avatar_file']['name'])) {
            $r = media_upload_from_files($_FILES['avatar_file'], array('max_width'=>240,'max_height'=>240,'quality'=>80));
            if ($r['ok']) $avatar = $r['path'];
            else flash_set('err', 'Avatar yüklenemedi: ' . $r['error']);
        }

        if ($name === '') {
            flash_set('err', 'Yazar adı zorunludur.');
        } else {
            try {
                if ($id) {
                    db()->prepare(
                        'UPDATE blog_authors SET name=?,title=?,bio=?,avatar=?,website=?,instagram=?,twitter=?,is_active=? WHERE id=?'
                    )->execute([$name, $title_val, $bio, $avatar, $website, $instagram, $twitter, $isActive, $id]);
                    flash_set('ok', 'Yazar güncellendi.');
                } else {
                    db()->prepare(
                        'INSERT INTO blog_authors (name,title,bio,avatar,website,instagram,twitter,is_active) VALUES (?,?,?,?,?,?,?,?)'
                    )->execute([$name, $title_val, $bio, $avatar, $website, $instagram, $twitter, $isActive]);
                    flash_set('ok', 'Yazar eklendi.');
                }
            } catch (\Throwable $e) {
                flash_set('err', 'Kayıt başarısız: ' . $e->getMessage());
            }
        }
        redirect('authors.php');
    }

    if ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        // Blog yazılarındaki referansı temizle
        db()->prepare('UPDATE blog_posts SET blog_author_id=NULL WHERE blog_author_id=?')->execute([$id]);
        db()->prepare('DELETE FROM blog_authors WHERE id=?')->execute([$id]);
        flash_set('ok', 'Yazar silindi.');
        redirect('authors.php');
    }

    if ($action === 'toggle') {
        db()->prepare('UPDATE blog_authors SET is_active=1-is_active WHERE id=?')->execute([(int)($_POST['id'] ?? 0)]);
        redirect('authors.php');
    }
}

// ── Düzenleme ───────────────────────────────────────────────────
$editId = (int)($_GET['edit'] ?? 0);
$editing = null;
if ($editId) {
    $st = db()->prepare('SELECT * FROM blog_authors WHERE id=?');
    $st->execute([$editId]);
    $editing = $st->fetch() ?: null;
}

// ── Tüm yazarlar ────────────────────────────────────────────────
$authors = db()->query(
    'SELECT a.*, (SELECT COUNT(*) FROM blog_posts WHERE blog_author_id=a.id) AS post_count
     FROM blog_authors a ORDER BY a.is_active DESC, a.name ASC'
)->fetchAll();

require_once __DIR__ . '/../core/header.php';
?>

<style>
.author-avatar{width:52px;height:52px;border-radius:50%;border:2px solid var(--gold-border);object-fit:cover}
.author-avatar-ph{width:52px;height:52px;border-radius:50%;border:2px dashed var(--gold-border);display:grid;place-items:center;font-size:18px;background:var(--olive-2);color:var(--muted-text)}
</style>

<div style="display:grid;grid-template-columns:1fr 2fr;gap:24px;align-items:start">

  <!-- ── Form ─────────────────────────────────────────────────── -->
  <div class="panel">
    <h3><?= $editing ? 'Yazarı Düzenle: ' . e($editing['name']) : 'Yeni Yazar' ?></h3>
    <form method="post" enctype="multipart/form-data" style="display:grid;gap:14px">
      <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
      <input type="hidden" name="action" value="save">
      <input type="hidden" name="id" value="<?= (int)($editing['id'] ?? 0) ?>">
      <input type="hidden" name="avatar_current" value="<?= e($editing['avatar'] ?? '') ?>">

      <div class="field">
        <label>Ad Soyad <span style="color:#c0392b">*</span></label>
        <input name="name" required value="<?= e($editing['name'] ?? '') ?>" placeholder="Gökhan Topuz">
      </div>

      <div class="field">
        <label>Unvan / Rol</label>
        <input name="title_val" value="<?= e($editing['title'] ?? '') ?>" placeholder="Akvaryum Uzmanı">
        <small class="muted">Blog yazısı altında gösterilir</small>
      </div>

      <div class="field">
        <label>Kısa Biyografi</label>
        <textarea name="bio" rows="4" placeholder="Yazar hakkında kısa bir tanıtım…"><?= e($editing['bio'] ?? '') ?></textarea>
        <small class="muted">Blog yazısı altında okuyuculara gösterilir (~200 karakter ideal)</small>
      </div>

      <div class="field">
        <label>Avatar Görseli</label>
        <?php if (!empty($editing['avatar'])): ?>
          <img src="<?= e($editing['avatar']) ?>" alt="" class="author-avatar" style="margin-bottom:8px">
        <?php endif; ?>
        <input type="file" name="avatar_file" accept="image/*">
        <small class="muted">Kare görsel önerilir, WebP'ye dönüştürülür</small>
      </div>

      <div class="row-2">
        <div class="field">
          <label>Instagram</label>
          <input name="instagram" value="<?= e($editing['instagram'] ?? '') ?>" placeholder="kullaniciadi">
          <small class="muted">@ olmadan</small>
        </div>
        <div class="field">
          <label>Twitter / X</label>
          <input name="twitter" value="<?= e($editing['twitter'] ?? '') ?>" placeholder="kullaniciadi">
          <small class="muted">@ olmadan</small>
        </div>
      </div>

      <div class="field">
        <label>Web Sitesi</label>
        <input name="website" type="url" value="<?= e($editing['website'] ?? '') ?>" placeholder="https://ornek-site.test">
      </div>

      <label style="display:flex;gap:10px;align-items:center">
        <input type="checkbox" name="is_active" value="1" <?= (!isset($editing) || $editing['is_active']) ? 'checked' : '' ?>>
        Aktif (blog yazılarında görünsün)
      </label>

      <div class="btn-row">
        <button class="btn btn-primary"><?= $editing ? 'Güncelle' : 'Yazar Ekle' ?></button>
        <?php if ($editing): ?>
          <a class="btn btn-secondary" href="authors.php">Vazgeç</a>
        <?php endif; ?>
      </div>
    </form>
  </div>

  <!-- ── Liste ─────────────────────────────────────────────────── -->
  <div class="panel">
    <h3>Yazarlar <span class="muted" style="font-size:13px">(<?= count($authors) ?>)</span></h3>
    <?php if (!$authors): ?>
      <p class="muted">Henüz yazar eklenmemiş.</p>
    <?php else: ?>
      <table>
        <thead>
          <tr>
            <th style="width:60px">Avatar</th>
            <th>Ad / Unvan</th>
            <th>Yazı</th>
            <th>Durum</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($authors as $a): ?>
          <tr style="<?= $a['is_active'] ? '' : 'opacity:.55' ?>">
            <td>
              <?php if (!empty($a['avatar'])): ?>
                <img src="<?= e($a['avatar']) ?>" alt="" class="author-avatar">
              <?php else: ?>
                <div class="author-avatar-ph">👤</div>
              <?php endif; ?>
            </td>
            <td>
              <strong><?= e($a['name']) ?></strong>
              <?php if (!empty($a['title'])): ?>
                <br><small class="muted"><?= e($a['title']) ?></small>
              <?php endif; ?>
              <?php if (!empty($a['instagram'])): ?>
                <br><small class="muted">📸 @<?= e($a['instagram']) ?></small>
              <?php endif; ?>
            </td>
            <td>
              <span style="font-size:13px"><?= (int)$a['post_count'] ?> yazı</span>
            </td>
            <td>
              <form method="post" style="display:inline">
                <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="action" value="toggle">
                <input type="hidden" name="id" value="<?= (int)$a['id'] ?>">
                <button class="btn btn-secondary btn-sm"><?= $a['is_active'] ? 'Pasif Yap' : 'Aktif Et' ?></button>
              </form>
            </td>
            <td class="actions">
              <a class="btn btn-secondary btn-sm" href="?edit=<?= (int)$a['id'] ?>">Düzenle</a>
              <form method="post" style="display:inline"
                    onsubmit="return confirm('«<?= e(addslashes($a['name'])) ?>» silinsin mi? Bu yazara atanmış yazılardan bağlantı kaldırılır.')">
                <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="id" value="<?= (int)$a['id'] ?>">
                <button class="btn btn-danger btn-sm">Sil</button>
              </form>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>
  </div>

</div>

<?php require_once __DIR__ . '/../core/footer.php'; ?>
