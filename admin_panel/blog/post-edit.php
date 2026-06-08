<?php
$page='blog';
require_once __DIR__ . '/../core/auth.php';
require_once __DIR__ . '/../../includes/media.php';
require_once __DIR__ . '/../../includes/indexnow.php';

$id = (int)(isset($_GET['id']) ? $_GET['id'] : 0);
$post = array(
    'id'=>0,'category_id'=>null,'title'=>'','slug'=>'','excerpt'=>'','content'=>'',
    'cover_image'=>'','is_published'=>1,'published_at'=>null,
);
if ($id) {
    $st = db()->prepare('SELECT * FROM blog_posts WHERE id=?');
    $st->execute(array($id));
    $row = $st->fetch();
    if ($row) $post = $row;
}
$title = $post['id'] ? 'Yazıyı Düzenle' : 'Yeni Yazı';

if ($_SERVER['REQUEST_METHOD']==='POST' && csrf_check(isset($_POST['csrf'])?$_POST['csrf']:null)) {
    $t = trim(isset($_POST['title']) ? $_POST['title'] : '');
    $s = trim(isset($_POST['slug']) ? $_POST['slug'] : '');
    if ($s === '') {
        $s = strtolower(preg_replace('~[^a-z0-9ğüşıöçĞÜŞİÖÇ]+~iu','-', $t));
        $s = trim(strtr($s, array('ğ'=>'g','ü'=>'u','ş'=>'s','ı'=>'i','ö'=>'o','ç'=>'c','Ğ'=>'g','Ü'=>'u','Ş'=>'s','İ'=>'i','Ö'=>'o','Ç'=>'c')), '-');
    }
    $cat = !empty($_POST['category_id']) ? (int)$_POST['category_id'] : null;
    $exc = trim(isset($_POST['excerpt']) ? $_POST['excerpt'] : '');
    $content = isset($_POST['content']) ? $_POST['content'] : '';
    $cover = trim(isset($_POST['cover_image']) ? $_POST['cover_image'] : '');
    if (!empty($_FILES['cover_file']['name'])) {
        // Blog kapak — hem yazı sayfasında hem OG (sosyal paylaşım) olarak kullanılır
        // 1200x800 = makul yükseklik + OG kart için yeterli (1200x630 da uyar)
        $r = media_upload_from_files($_FILES['cover_file'], array('max_width'=>1200,'max_height'=>800,'quality'=>82));
        if ($r['ok']) $cover = $r['path'];
        else flash_set('err','Kapak yüklenemedi: '.$r['error']);
    }
    $pub = !empty($_POST['is_published']) ? 1 : 0;
    $pubAt = !empty($_POST['published_at']) ? $_POST['published_at'] : ($pub ? date('Y-m-d H:i:s') : null);
    $blogAuthorId = !empty($_POST['blog_author_id']) ? (int)$_POST['blog_author_id'] : null;

    if ($t === '') {
        flash_set('err','Başlık zorunludur.');
    } else {
        try {
            if ($post['id']) {
                $st = db()->prepare('UPDATE blog_posts SET category_id=?,title=?,slug=?,excerpt=?,content=?,cover_image=?,is_published=?,published_at=?,blog_author_id=? WHERE id=?');
                $st->execute(array($cat,$t,$s,$exc,$content,$cover,$pub,$pubAt,$blogAuthorId,$post['id']));
                $savedId = $post['id'];
                flash_set('ok','Yazı güncellendi.');
            } else {
                $aid = isset($ADMIN['id']) ? (int)$ADMIN['id'] : null;
                $st = db()->prepare('INSERT INTO blog_posts (category_id,author_id,blog_author_id,title,slug,excerpt,content,cover_image,is_published,published_at) VALUES (?,?,?,?,?,?,?,?,?,?)');
                $st->execute(array($cat,$aid,$blogAuthorId,$t,$s,$exc,$content,$cover,$pub,$pubAt));
                $savedId = (int)db()->lastInsertId();
                flash_set('ok','Yazı eklendi.');
            }

            // SSS kayıtları — temizle, yeniden ekle
            if ($savedId) {
                try {
                    db()->prepare('DELETE FROM blog_post_faqs WHERE post_id=?')->execute(array($savedId));
                    $faqQ = isset($_POST['faq_q']) ? (array)$_POST['faq_q'] : array();
                    $faqA = isset($_POST['faq_a']) ? (array)$_POST['faq_a'] : array();
                    $ins  = db()->prepare('INSERT INTO blog_post_faqs (post_id,question,answer,sort_order) VALUES (?,?,?,?)');
                    $ord  = 0;
                    foreach ($faqQ as $idx => $q) {
                        $q = trim($q);
                        $a = trim($faqA[$idx] ?? '');
                        if ($q !== '' && $a !== '') { $ins->execute(array($savedId, $q, $a, $ord++)); }
                    }
                } catch (Exception $e) { /* tablo yoksa sessizce geç */ }
            }

            // IndexNow — yayında blog yazılarını Bing/Yandex'e bildir
            if ($pub && !empty($s)) {
                $siteBase = defined('SITE_URL') ? rtrim(SITE_URL, '/') : 'https://aquashop.com.tr';
                indexnow_ping([$siteBase . url('blog_post', ['slug' => $s])]);
            }

            redirect('posts.php');
        } catch (PDOException $e) {
            flash_set('err', (isset($e->errorInfo[1]) && $e->errorInfo[1]==1062) ? 'Bu slug zaten kullanılıyor.' : 'Kayıt başarısız: '.$e->getMessage());
        }
    }
}

// Mevcut SSS kayıtlarını çek
$faqs = array();
if ($post['id']) {
    try {
        $st = db()->prepare('SELECT * FROM blog_post_faqs WHERE post_id=? ORDER BY sort_order, id');
        $st->execute(array($post['id']));
        $faqs = $st->fetchAll();
    } catch (Exception $e) {}
}

$cats = db()->query('SELECT * FROM blog_categories ORDER BY name')->fetchAll();

// Blog yazarları — blog_authors tablosu yoksa boş dizi
$blogAuthors = [];
try {
    $blogAuthors = db()->query('SELECT id, name, title FROM blog_authors WHERE is_active=1 ORDER BY name ASC')->fetchAll();
} catch (\Throwable $e) {}

require_once __DIR__ . '/../core/header.php';
?>

<div class="panel">
  <form method="post" enctype="multipart/form-data" style="display:grid;gap:18px">
    <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">

    <div class="row-2">
      <div class="field"><label>Başlık</label><input name="title" value="<?= e($post['title']) ?>" required></div>
      <div class="field"><label>Slug (boşsa otomatik)</label><input name="slug" value="<?= e($post['slug']) ?>" placeholder="ornek-yazi"></div>
    </div>

    <div class="row-2">
      <div class="field"><label>Kategori</label>
        <select name="category_id"><option value="">— Yok —</option>
        <?php foreach ($cats as $c): ?>
          <option value="<?= (int)$c['id'] ?>" <?= ($post['category_id']==$c['id'])?'selected':'' ?>><?= e($c['name']) ?></option>
        <?php endforeach; ?>
        </select>
      </div>
      <div class="field"><label>Yayın Tarihi</label><input type="datetime-local" name="published_at" value="<?= $post['published_at']? e(date('Y-m-d\TH:i', strtotime($post['published_at']))):'' ?>"></div>
    </div>

    <?php if ($blogAuthors): ?>
    <div class="field">
      <label>Yazar <small class="muted">— yazı altında biyografi kartı olarak görünür</small></label>
      <select name="blog_author_id">
        <option value="">— Yazar seçin —</option>
        <?php foreach ($blogAuthors as $a): ?>
          <option value="<?= (int)$a['id'] ?>"
                  <?= ((int)($post['blog_author_id'] ?? 0) === (int)$a['id']) ? 'selected' : '' ?>>
            <?= e($a['name']) ?><?= $a['title'] ? ' — ' . e($a['title']) : '' ?>
          </option>
        <?php endforeach; ?>
      </select>
      <small class="muted">Yazar yönetimi için: <a href="authors.php" style="color:var(--gold)">Blog Yazarları →</a></small>
    </div>
    <?php endif; ?>

    <div class="field">
      <label>Kapak Görseli</label>
      <?php if (!empty($post['cover_image'])): ?>
        <div style="margin-bottom:8px"><img src="<?= e($post['cover_image']) ?>" alt="Kapak görseli" style="max-width:280px;border:1px solid var(--gold-border);border-radius:6px"></div>
      <?php endif; ?>
      <input type="file" name="cover_file" accept="image/*">
      <small class="muted">Yüklenen görsel otomatik WebP'ye dönüştürülür ve sıkıştırılır.</small>
      <input type="hidden" name="cover_image" value="<?= e($post['cover_image']) ?>">
    </div>
    <div class="field"><label>Özet (kart altında görünür, ~250 karakter)</label><textarea name="excerpt" rows="3" maxlength="500"><?= e($post['excerpt']) ?></textarea></div>

    <div class="field">
      <label>İçerik</label>
      <textarea id="content" name="content" rows="20"><?= e($post['content']) ?></textarea>
    </div>

    <label style="display:flex;gap:10px;align-items:center"><input type="checkbox" name="is_published" value="1" <?= $post['is_published']?'checked':'' ?>> Yayında</label>

    <!-- ── Sık Sorulan Sorular (SSS) ───────────────────────── -->
    <div class="field" style="border-top:1px solid var(--gold-border);padding-top:24px;margin-top:6px">
      <label style="display:flex;justify-content:space-between;align-items:center">
        <span>📋 Sık Sorulan Sorular (SSS)</span>
        <button type="button" class="btn btn-secondary btn-sm" id="faq-add">+ Yeni Soru</button>
      </label>
      <small class="muted">Bu sorular yazı sayfasında akordiyon olarak görünür ve Google <strong>FAQPage</strong> schema'sı olarak yayınlanır — SEO'da öne çıkan snippet kazancı sağlar.</small>

      <div id="faq-list" style="display:flex;flex-direction:column;gap:14px;margin-top:14px">
        <?php if ($faqs): foreach ($faqs as $f): ?>
          <div class="faq-row" style="border:1px solid var(--gold-border);border-radius:8px;padding:14px;background:rgba(107,122,47,.04)">
            <div style="display:flex;gap:10px;align-items:center;margin-bottom:8px">
              <strong style="color:var(--gold);font-size:12px;letter-spacing:.18em;text-transform:uppercase">Soru</strong>
              <button type="button" class="btn btn-secondary btn-sm faq-del" style="margin-left:auto;color:#c0392b;border-color:#c0392b">Sil</button>
            </div>
            <input type="text" name="faq_q[]" value="<?= e($f['question']) ?>" placeholder="Sorunuz…" style="width:100%;margin-bottom:10px">
            <strong style="color:var(--gold);font-size:12px;letter-spacing:.18em;text-transform:uppercase;display:block;margin-bottom:6px">Cevap</strong>
            <textarea name="faq_a[]" rows="3" placeholder="Cevabınız…" style="width:100%;resize:vertical"><?= e($f['answer']) ?></textarea>
          </div>
        <?php endforeach; endif; ?>
      </div>

      <template id="faq-template">
        <div class="faq-row" style="border:1px solid var(--gold-border);border-radius:8px;padding:14px;background:rgba(107,122,47,.04)">
          <div style="display:flex;gap:10px;align-items:center;margin-bottom:8px">
            <strong style="color:var(--gold);font-size:12px;letter-spacing:.18em;text-transform:uppercase">Soru</strong>
            <button type="button" class="btn btn-secondary btn-sm faq-del" style="margin-left:auto;color:#c0392b;border-color:#c0392b">Sil</button>
          </div>
          <input type="text" name="faq_q[]" placeholder="Sorunuz…" style="width:100%;margin-bottom:10px">
          <strong style="color:var(--gold);font-size:12px;letter-spacing:.18em;text-transform:uppercase;display:block;margin-bottom:6px">Cevap</strong>
          <textarea name="faq_a[]" rows="3" placeholder="Cevabınız…" style="width:100%;resize:vertical"></textarea>
        </div>
      </template>
    </div>

    <div style="display:flex;gap:12px">
      <button class="btn btn-primary">Kaydet</button>
      <a class="btn btn-secondary" href="posts.php">Vazgeç</a>
      <?php if ($post['id']): ?><a class="btn btn-secondary" href="<?= e(url('blog_post', ['slug'=>$post['slug']])) ?>" target="_blank">Önizle ↗</a><?php endif; ?>
    </div>
  </form>
</div>

<script>
(function(){
  var addBtn  = document.getElementById('faq-add');
  var list    = document.getElementById('faq-list');
  var tmpl    = document.getElementById('faq-template');
  if (!addBtn || !list || !tmpl) return;

  addBtn.addEventListener('click', function(){
    var clone = tmpl.content.cloneNode(true);
    list.appendChild(clone);
    list.lastElementChild.querySelector('input[type=text]').focus();
  });

  list.addEventListener('click', function(e){
    if (e.target.classList.contains('faq-del')) {
      if (confirm('Bu soruyu silmek istediğinizden emin misiniz?')) {
        e.target.closest('.faq-row').remove();
      }
    }
  });
})();
</script>

<!-- Zengin Metin Editörü: TinyMCE (CDN) -->
<script src="https://cdn.jsdelivr.net/npm/tinymce@6/tinymce.min.js" referrerpolicy="origin"></script>
<script defer src="<?= SITE_URL ?>/assets/js/admin/tinymce-init.js?v=<?= @filemtime(__DIR__ . '/../../assets/js/admin/tinymce-init.js') ?: time() ?>"></script>

<?php require_once __DIR__ . '/../core/footer.php'; ?>
