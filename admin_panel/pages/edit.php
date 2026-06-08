<?php
$page='pages';
require_once __DIR__ . '/../core/auth.php';
require_once __DIR__ . '/../../includes/media.php';
require_once __DIR__ . '/../../models/Seo.php';

$id = (int)(isset($_GET['id']) ? $_GET['id'] : 0);
$pg = array('id'=>0,'slug'=>'','title'=>'','content'=>'','cover_image'=>'','is_published'=>1);
if ($id) {
    $st = db()->prepare('SELECT * FROM pages WHERE id=?');
    $st->execute(array($id));
    $row = $st->fetch();
    if ($row) $pg = $row;
}
$title = $pg['id'] ? 'Sayfa Düzenle' : 'Yeni Sayfa';

// Mevcut SEO verilerini çek (meta_title, meta_description)
$pgSeo = $pg['slug'] ? seo_get($pg['slug']) : null;

if ($_SERVER['REQUEST_METHOD']==='POST' && csrf_check(isset($_POST['csrf'])?$_POST['csrf']:null)) {
    $t = trim(isset($_POST['title']) ? $_POST['title'] : '');
    $s = trim(isset($_POST['slug']) ? $_POST['slug'] : '');
    if ($s === '') {
        $s = strtolower(strtr($t, array('ç'=>'c','ğ'=>'g','ı'=>'i','ö'=>'o','ş'=>'s','ü'=>'u','Ç'=>'c','Ğ'=>'g','İ'=>'i','Ö'=>'o','Ş'=>'s','Ü'=>'u')));
        $s = trim(preg_replace('~[^a-z0-9]+~i','-',$s),'-');
    }
    $c = isset($_POST['content']) ? $_POST['content'] : '';
    $pub = !empty($_POST['is_published']) ? 1 : 0;

    $cover = isset($_POST['cover_image']) ? $_POST['cover_image'] : ($pg['cover_image'] ?? null);
    if (!empty($_FILES['cover_file']['name'])) {
        // CMS sayfa kapak — blog kapak ile aynı: max 1200x800
        $r = media_upload_from_files($_FILES['cover_file'], array('max_width'=>1200,'max_height'=>800,'quality'=>82));
        if ($r['ok']) $cover = $r['path'];
        else flash_set('err','Görsel yüklenemedi: '.$r['error']);
    }

    if ($t === '' || $s === '') {
        flash_set('err','Başlık ve slug zorunludur.');
    } else {
        try {
            if ($pg['id']) {
                $st = db()->prepare('UPDATE pages SET slug=?,title=?,content=?,cover_image=?,is_published=? WHERE id=?');
                $st->execute(array($s,$t,$c,$cover,$pub,$pg['id']));
                flash_set('ok','Sayfa güncellendi.');
            } else {
                $st = db()->prepare('INSERT INTO pages (slug,title,content,cover_image,is_published) VALUES (?,?,?,?,?)');
                $st->execute(array($s,$t,$c,$cover,$pub));
                flash_set('ok','Sayfa eklendi.');
            }
            // SEO meta verilerini kaydet (seo_settings tablosuna slug bazlı)
            $metaTitle = trim($_POST['meta_title'] ?? '');
            $metaDesc  = trim($_POST['meta_description'] ?? '');
            seo_save([
                'page_slug'        => $s,
                'page_label'       => $t . ' (Sayfa)',
                'meta_title'       => $metaTitle !== '' ? $metaTitle : null,
                'meta_description' => $metaDesc  !== '' ? $metaDesc  : null,
                'meta_keywords'    => $pgSeo['meta_keywords'] ?? null,
                'meta_robots'      => $pgSeo['meta_robots']   ?? null,
                'og_image'         => $pgSeo['og_image']       ?? null,
            ]);
            redirect('list.php');
        } catch (PDOException $e) {
            flash_set('err', (isset($e->errorInfo[1]) && $e->errorInfo[1]==1062) ? 'Bu slug zaten kullanılıyor.' : 'Kayıt başarısız.');
        }
    }
}

require_once __DIR__ . '/../core/header.php';
?>
<div class="panel">
  <form method="post" enctype="multipart/form-data" style="display:grid;gap:18px">
    <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">

    <div class="row-2">
      <div class="field"><label>Başlık</label><input name="title" value="<?= e($pg['title']) ?>" required></div>
      <div class="field"><label>Slug (boşsa otomatik)</label><input name="slug" value="<?= e($pg['slug']) ?>" placeholder="ornek-sayfa"></div>
    </div>

    <div class="field">
      <label>Kapak Görseli</label>
      <?php if (!empty($pg['cover_image'])): ?>
        <div style="margin-bottom:8px"><img src="<?= e($pg['cover_image']) ?>" alt="Kapak görseli" style="max-width:280px;border:1px solid var(--gold-border);border-radius:6px"></div>
      <?php endif; ?>
      <input type="file" name="cover_file" accept="image/*">
      <small class="muted">"Hakkımızda" sayfası için yan çerçevede gösterilir. Otomatik WebP'ye dönüşür.</small>
      <input type="hidden" name="cover_image" value="<?= e($pg['cover_image']) ?>">
    </div>

    <div class="field">
      <label>İçerik</label>
      <textarea id="content" name="content" rows="20"><?= e($pg['content']) ?></textarea>
    </div>

    <!-- SEO Meta Bilgileri -->
    <div style="border:1px solid var(--gold-border);border-radius:var(--radius);padding:18px;background:rgba(201,162,75,.04)">
      <div style="margin-bottom:14px">
        <strong style="font-size:12px;letter-spacing:.12em;text-transform:uppercase;color:var(--gold)">SEO Meta Bilgileri</strong>
        <p class="muted" style="font-size:12px;margin-top:4px">Bu alanlar Google arama sonuçlarında ve sosyal medya paylaşımlarında görünür. Boş bırakırsanız sayfa başlığı otomatik kullanılır.</p>
      </div>
      <div class="field" style="margin-bottom:12px">
        <label style="display:flex;justify-content:space-between">
          <span>Meta Başlık (SEO Title)</span>
          <small class="muted" id="mt-count">— / 60 karakter</small>
        </label>
        <input name="meta_title" id="meta_title_inp" value="<?= e($pgSeo['meta_title'] ?? '') ?>" maxlength="120" placeholder="Boş bırakırsanız sayfa başlığı + site adı kullanılır">
        <small class="muted">Google arama listesinde görünen başlık. 60 karakterin altında tutun.</small>
      </div>
      <div class="field">
        <label style="display:flex;justify-content:space-between">
          <span>Meta Açıklama (Meta Description)</span>
          <small class="muted" id="md-count">— / 160 karakter</small>
        </label>
        <textarea name="meta_description" id="meta_desc_inp" rows="3" maxlength="320" placeholder="Sayfanın kısa açıklaması — Google arama sonuçlarında başlığın altında gösterilir"><?= e($pgSeo['meta_description'] ?? '') ?></textarea>
        <small class="muted">160 karakterin altında tutun. Ne kadar çekici olursa tıklanma oranı o kadar artar.</small>
      </div>
    </div>

    <label style="display:flex;gap:10px;align-items:center"><input type="checkbox" name="is_published" value="1" <?= $pg['is_published']?'checked':'' ?>> Yayında</label>

    <div style="display:flex;gap:12px">
      <button class="btn btn-primary">Kaydet</button>
      <a class="btn btn-secondary" href="pages.php">Vazgeç</a>
      <?php if ($pg['id']): ?>
        <?php $previewUrl = ($pg['slug']==='hakkimizda') ? '../about.php' : '../page.php?slug='.urlencode($pg['slug']); ?>
        <a class="btn btn-secondary" href="<?= e($previewUrl) ?>" target="_blank">Önizle</a>
      <?php endif; ?>
    </div>
  </form>
</div>

<script src="https://cdn.jsdelivr.net/npm/tinymce@6/tinymce.min.js" referrerpolicy="origin"></script>
<script defer src="<?= SITE_URL ?>/assets/js/admin/tinymce-init.js?v=<?= @filemtime(__DIR__ . '/../../assets/js/admin/tinymce-init.js') ?: time() ?>"></script>
<script>
(function(){
  function counter(inputId, counterId, max) {
    var el = document.getElementById(inputId);
    var ct = document.getElementById(counterId);
    if (!el || !ct) return;
    function upd(){ var l=el.value.length; ct.textContent=l+' / '+max+' karakter'; ct.style.color=l>max?'#f5a3a3':l>max*0.85?'var(--gold)':''; }
    el.addEventListener('input', upd); upd();
  }
  counter('meta_title_inp','mt-count',60);
  counter('meta_desc_inp','md-count',160);
})();
</script>

<?php require_once __DIR__ . '/../core/footer.php'; ?>
