<?php
$page='categories'; $title='Kategoriler';
require_once __DIR__ . '/core/auth.php';
require_once __DIR__ . '/../includes/media.php';

/**
 * Verilen metni URL-safe slug'a dönüştürür.
 * Boş gelirse isimden otomatik üretir.
 */
function make_category_slug(string $slug, string $name): string {
    $tr = ['ç'=>'c','ğ'=>'g','ı'=>'i','ö'=>'o','ş'=>'s','ü'=>'u',
           'Ç'=>'c','Ğ'=>'g','İ'=>'i','Ö'=>'o','Ş'=>'s','Ü'=>'u'];
    $s = trim($slug) !== '' ? trim($slug) : $name;
    $s = strtolower(strtr($s, $tr));
    $s = trim(preg_replace('~[^a-z0-9]+~', '-', $s), '-');
    return $s ?: 'kategori';
}

if ($_SERVER['REQUEST_METHOD']==='POST' && csrf_check(isset($_POST['csrf'])?$_POST['csrf']:null)) {
    $action = isset($_POST['action']) ? $_POST['action'] : '';
    if ($action === 'create') {
        $n    = trim(isset($_POST['name'])?$_POST['name']:'');
        $s    = make_category_slug(isset($_POST['slug'])?$_POST['slug']:'', $n);
        $desc = trim(isset($_POST['description'])?$_POST['description']:'') ?: null;
        $mt   = trim(isset($_POST['meta_title'])?$_POST['meta_title']:'') ?: null;
        $md   = trim(isset($_POST['meta_description'])?$_POST['meta_description']:'') ?: null;
        $parent = !empty($_POST['parent_id']) ? (int)$_POST['parent_id'] : null;
        $img = null;
        if (!empty($_FILES['image_file']['name'])) {
            // Kategori thumbnail — 480x480 max, kalite 80 (yuvarlak ikon olarak ~88-120px gösteriliyor)
            $r = media_upload_from_files($_FILES['image_file'], array('max_width'=>480,'max_height'=>480,'quality'=>80));
            if ($r['ok']) $img = $r['path'];
        }
        if ($n) {
            try {
                db()->prepare('INSERT INTO categories (name,slug,image,parent_id,description,meta_title,meta_description) VALUES (?,?,?,?,?,?,?)')
                    ->execute(array($n,$s,$img,$parent,$desc,$mt,$md));
                flash_set('ok','Kategori eklendi.');
            } catch (Exception $e) { flash_set('err','Eklenemedi (slug benzersiz olmalı).'); }
        }
    } elseif ($action === 'update') {
        $id   = (int)$_POST['id'];
        $n    = trim($_POST['name']);
        $s    = make_category_slug($_POST['slug'] ?? '', $n);
        $sort = (int)$_POST['sort_order'];
        $parent = !empty($_POST['parent_id']) ? (int)$_POST['parent_id'] : null;
        $desc = trim($_POST['description'] ?? '') ?: null;
        $mt   = trim($_POST['meta_title'] ?? '') ?: null;
        $md   = trim($_POST['meta_description'] ?? '') ?: null;
        // Kendi kendinin parent'ı olmasın
        if ($parent === $id) $parent = null;
        $img = $_POST['image'] ?? null;
        if (!empty($_FILES['image_file']['name'])) {
            // Kategori thumbnail — 480x480 max, kalite 80 (yuvarlak ikon olarak ~88-120px gösteriliyor)
            $r = media_upload_from_files($_FILES['image_file'], array('max_width'=>480,'max_height'=>480,'quality'=>80));
            if ($r['ok']) $img = $r['path'];
        }
        try {
            db()->prepare('UPDATE categories SET name=?, slug=?, image=?, sort_order=?, parent_id=?, description=?, meta_title=?, meta_description=? WHERE id=?')
                ->execute(array($n,$s,$img,$sort,$parent,$desc,$mt,$md,$id));
            flash_set('ok','Kategori güncellendi.');
        } catch (\Throwable $e) {
            flash_set('err', 'Güncellenemedi: Bu slug zaten başka bir kategoride kullanılıyor. Lütfen farklı bir slug girin.');
        }
    } elseif ($action === 'delete') {
        db()->prepare('DELETE FROM categories WHERE id=?')->execute(array((int)$_POST['id']));
        flash_set('ok','Kategori silindi (alt kategorileri kök seviyeye taşındı).');
    } elseif ($action === 'remove_image') {
        db()->prepare('UPDATE categories SET image=NULL WHERE id=?')->execute(array((int)$_POST['id']));
        flash_set('ok','Görsel kaldırıldı.');
    }
    redirect('categories.php');
}

// Kategorileri ağaç olarak çek
$all = db()->query('SELECT c.*, (SELECT COUNT(*) FROM products WHERE category_id=c.id) AS cnt FROM categories c ORDER BY sort_order ASC, name ASC')->fetchAll();
$byId = array();
foreach ($all as $c) { $byId[$c['id']] = $c; $byId[$c['id']]['children'] = array(); }
$roots = array();
foreach ($byId as $id => $c) {
    if (!empty($c['parent_id']) && isset($byId[$c['parent_id']])) {
        $byId[$c['parent_id']]['children'][] = & $byId[$id];
    } else {
        $roots[] = & $byId[$id];
    }
}

// Düzenleme formu için seçili kategori (URL'de ?edit=ID)
$editId  = !empty($_GET['edit']) ? (int)$_GET['edit'] : 0;
$editCat = null;
if ($editId && isset($byId[$editId])) $editCat = $byId[$editId];

require_once __DIR__ . '/core/header.php';

$siteBase = defined('SITE_URL') ? rtrim(SITE_URL, '/') : '';
?>

<style>
.cat-meta-fields{background:var(--surface-2,#f8f8f8);border:1px solid var(--gold-border);border-radius:6px;padding:16px;margin-top:10px}
.cat-meta-fields label{font-size:12px;letter-spacing:.1em;text-transform:uppercase;color:var(--muted-text)}
.char-count{font-size:11px;color:var(--muted-text);text-align:right;margin-top:2px}
.char-warn{color:#c0392b}
</style>

<div style="display:grid;grid-template-columns:1fr 2fr;gap:24px;align-items:start">

  <!-- ── Sol: Yeni / Düzenle Formu ───────────────────────────── -->
  <div class="panel">
    <h3><?= $editCat ? 'Kategoriyi Düzenle' : 'Yeni Kategori' ?></h3>
    <form method="post" enctype="multipart/form-data" style="display:grid;gap:14px">
      <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
      <input type="hidden" name="action" value="<?= $editCat ? 'update' : 'create' ?>">
      <?php if ($editCat): ?>
        <input type="hidden" name="id" value="<?= (int)$editCat['id'] ?>">
        <input type="hidden" name="image" value="<?= e($editCat['image'] ?? '') ?>">
      <?php endif; ?>

      <div class="field">
        <label>Ad <span style="color:#c0392b">*</span></label>
        <input name="name" required value="<?= e($editCat['name'] ?? '') ?>">
      </div>

      <div class="field">
        <label>Üst Kategori</label>
        <select name="parent_id">
          <option value="">— Kök kategori —</option>
          <?php foreach ($roots as $r):
            if ($editCat && $r['id'] === $editCat['id']) continue; ?>
            <option value="<?= (int)$r['id'] ?>" <?= (!empty($editCat['parent_id']) && $editCat['parent_id']==$r['id'])?'selected':'' ?>><?= e($r['name']) ?></option>
            <?php foreach ($r['children'] as $ch):
              if ($editCat && $ch['id'] === $editCat['id']) continue; ?>
              <option value="<?= (int)$ch['id'] ?>" <?= (!empty($editCat['parent_id']) && $editCat['parent_id']==$ch['id'])?'selected':'' ?>>— <?= e($ch['name']) ?></option>
            <?php endforeach; ?>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="field">
        <label>Slug <small class="muted">(boş bırakılırsa otomatik)</small></label>
        <input name="slug" value="<?= e($editCat['slug'] ?? '') ?>" placeholder="ornek-kategori">
        <?php if ($editCat): ?>
          <small class="muted" style="margin-top:4px;display:block">
            URL: <a href="<?= e($siteBase . '/kategoriler/' . $editCat['slug']) ?>" target="_blank" style="color:var(--gold)">/kategoriler/<?= e($editCat['slug']) ?></a>
          </small>
        <?php endif; ?>
      </div>

      <?php if ($editCat): ?>
      <div class="field">
        <label>Sıra</label>
        <input name="sort_order" type="number" value="<?= (int)($editCat['sort_order'] ?? 0) ?>" style="width:80px">
      </div>
      <?php endif; ?>

      <div class="field">
        <label>Görsel (yuvarlak ikon)</label>
        <?php if (!empty($editCat['image'])): ?>
          <img src="<?= e($editCat['image']) ?>" alt="" style="width:64px;height:64px;border-radius:999px;border:1px solid var(--gold-border);object-fit:cover;margin-bottom:8px">
        <?php endif; ?>
        <input type="file" name="image_file" accept="image/*">
        <small class="muted">WebP'ye dönüştürülür ve sıkıştırılır.</small>
      </div>

      <!-- SEO & İçerik Alanları -->
      <div class="cat-meta-fields">
        <p style="font-size:12px;font-weight:600;letter-spacing:.1em;text-transform:uppercase;margin-bottom:12px;color:var(--muted-text)">📋 SEO & Açıklama</p>

        <div class="field" style="margin-bottom:12px">
          <label>Kategori Açıklaması <small class="muted">(sayfada gösterilir)</small></label>
          <textarea name="description" rows="3" style="resize:vertical"><?= e($editCat['description'] ?? '') ?></textarea>
        </div>

        <div class="field" style="margin-bottom:12px">
          <label>Meta Başlık <small class="muted">(boş = kategori adı kullanılır)</small></label>
          <input name="meta_title" maxlength="70" value="<?= e($editCat['meta_title'] ?? '') ?>"
                 oninput="document.getElementById('mt-count-<?= (int)($editCat['id'] ?? 0) ?>').textContent=this.value.length">
          <div class="char-count" id="mt-count-<?= (int)($editCat['id'] ?? 0) ?>"><?= mb_strlen($editCat['meta_title'] ?? '') ?></div>
          <small class="muted">Tavsiye: 50–60 karakter</small>
        </div>

        <div class="field">
          <label>Meta Açıklama <small class="muted">(arama sonuçlarında görünür)</small></label>
          <textarea name="meta_description" rows="3" maxlength="165" style="resize:vertical"
                    oninput="var c=document.getElementById('md-count-<?= (int)($editCat['id'] ?? 0) ?>');c.textContent=this.value.length;c.className='char-count'+(this.value.length>155?' char-warn':'')"><?= e($editCat['meta_description'] ?? '') ?></textarea>
          <div class="char-count <?= mb_strlen($editCat['meta_description'] ?? '')>155 ? 'char-warn' : '' ?>"
               id="md-count-<?= (int)($editCat['id'] ?? 0) ?>"><?= mb_strlen($editCat['meta_description'] ?? '') ?></div>
          <small class="muted">Tavsiye: 120–155 karakter</small>
        </div>
      </div>

      <div style="display:flex;gap:10px;flex-wrap:wrap">
        <button class="btn btn-primary"><?= $editCat ? 'Güncelle' : 'Ekle' ?></button>
        <?php if ($editCat): ?>
          <a class="btn btn-secondary" href="categories.php">İptal</a>
          <a class="btn btn-secondary" href="<?= e($siteBase . '/kategoriler/' . $editCat['slug']) ?>" target="_blank">Sayfayı Gör ↗</a>
        <?php endif; ?>
      </div>
    </form>
  </div>

  <!-- ── Sağ: Mevcut Kategoriler ─────────────────────────────── -->
  <div class="panel">
    <h3>Mevcut Kategoriler</h3>
    <table>
      <thead>
        <tr>
          <th>Görsel</th>
          <th>Ad / Slug</th>
          <th>Üst</th>
          <th>Sıra</th>
          <th>Ürün</th>
          <th></th>
        </tr>
      </thead>
      <tbody>
      <?php
      function render_cat_row($c, $depth, $roots, $byId) {
          $siteBase2 = defined('SITE_URL') ? rtrim(SITE_URL, '/') : '';
          $isEdit = isset($_GET['edit']) && (int)$_GET['edit'] === (int)$c['id'];
          ?>
          <tr <?= $depth>0 ? 'style="background:rgba(107,122,47,.04)"' : '' ?>>
            <td>
              <?php if (!empty($c['image'])): ?>
                <img src="<?= e($c['image']) ?>" alt="" style="width:44px;height:44px;border-radius:999px;border:1px solid var(--gold-border);object-fit:cover">
              <?php else: ?>
                <div style="width:44px;height:44px;border-radius:999px;border:1px dashed var(--gold-border);display:grid;place-items:center;font-size:11px;color:var(--muted-text)">—</div>
              <?php endif; ?>
            </td>
            <td>
              <div style="padding-left:<?= $depth*16 ?>px">
                <?php if ($depth > 0): ?><span class="muted" aria-hidden="true">└ </span><?php endif; ?>
                <strong><?= e($c['name']) ?></strong><br>
                <code style="font-size:11px;color:var(--muted-text)">/kategoriler/<?= e($c['slug']) ?></code>
                <?php if (!empty($c['meta_description'])): ?>
                  <br><small class="muted" style="font-size:11px" title="<?= e($c['meta_description']) ?>">📋 Meta açıklama mevcut</small>
                <?php endif; ?>
              </div>
            </td>
            <td style="font-size:13px">
              <?php
              if (!empty($c['parent_id']) && isset($byId[$c['parent_id']])) {
                  echo e($byId[$c['parent_id']]['name']);
              } else {
                  echo '<span class="muted">Kök</span>';
              }
              ?>
            </td>
            <td style="font-size:13px"><?= (int)$c['sort_order'] ?></td>
            <td style="font-size:13px"><?= (int)$c['cnt'] ?></td>
            <td style="white-space:nowrap">
              <a class="btn btn-secondary btn-sm" href="?edit=<?= (int)$c['id'] ?>">Düzenle</a>
              <a class="btn btn-secondary btn-sm" href="<?= e($siteBase2 . '/kategoriler/' . $c['slug']) ?>" target="_blank" title="Kategori sayfasını aç">↗</a>
              <form method="post" style="display:inline"
                    onsubmit="return confirm('«<?= e(addslashes($c['name'])) ?>» silinsin mi? Alt kategorileri kök seviyeye taşınır.')">
                <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="id" value="<?= (int)$c['id'] ?>">
                <button class="btn btn-secondary btn-sm" style="color:#c0392b">Sil</button>
              </form>
            </td>
          </tr>
          <?php
          foreach ($c['children'] as $child) {
              render_cat_row($child, $depth + 1, $roots, $byId);
          }
      }
      foreach ($roots as $rootCat) render_cat_row($rootCat, 0, $roots, $byId);
      ?>
      </tbody>
    </table>
  </div>
</div>
<?php require_once __DIR__ . '/core/footer.php'; ?>
