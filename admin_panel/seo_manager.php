<?php
$page='seo'; $title='SEO Yönetimi';
require_once __DIR__ . '/core/auth.php';
require_once __DIR__ . '/../includes/media.php';

if ($_SERVER['REQUEST_METHOD']==='POST' && csrf_check($_POST['csrf'] ?? null)) {
    $action = $_POST['action'] ?? '';
    if ($action === 'save') {
        $slug = trim($_POST['page_slug'] ?? '');
        if ($slug === '') {
            flash_set('err','Sayfa anahtarı (slug) zorunludur.');
            redirect('seo_manager.php');
        }
        // Görsel yüklendiyse media library üzerinden işle (WebP'ye dönüştürür)
        // OG image — Facebook/Twitter standardı 1200x630, sosyal medyada paylaşım kartı
        $og = trim($_POST['og_image'] ?? '');
        if (!empty($_FILES['og_file']['name'])) {
            $r = media_upload_from_files($_FILES['og_file'], array('max_width'=>1200,'max_height'=>630,'quality'=>82));
            if ($r['ok']) $og = $r['path'];
            else flash_set('err','Görsel yüklenemedi: '.$r['error']);
        }
        seo_save(array(
            'page_slug'        => $slug,
            'page_label'       => $_POST['page_label']       ?? null,
            'meta_title'       => $_POST['meta_title']       ?? null,
            'meta_description' => $_POST['meta_description'] ?? null,
            'meta_keywords'    => $_POST['meta_keywords']    ?? null,
            'meta_robots'      => $_POST['meta_robots']      ?? null,
            'og_image'         => $og,
        ));
        flash_set('ok','SEO ayarları kaydedildi.');
        redirect('seo_manager.php?slug='.urlencode($slug));
    }
    if ($action === 'delete') {
        seo_delete(trim($_POST['page_slug'] ?? ''));
        flash_set('ok','Silindi.');
        redirect('seo_manager.php');
    }
}

$rows = seo_all();
$editSlug = isset($_GET['slug']) ? trim($_GET['slug']) : '';
$editing  = $editSlug ? seo_get($editSlug) : null;
if ($editSlug && !$editing) {
    $editing = array(
        'page_slug'=>$editSlug, 'page_label'=>'', 'meta_title'=>'',
        'meta_description'=>'', 'meta_keywords'=>'', 'meta_robots'=>'', 'og_image'=>''
    );
}

// Önerilen sayfa slug'ları
$suggestedSlugs = [
    'home'      => 'Anasayfa',
    'products'  => 'Ürünler Listesi',
    'categories_list' => 'Kategoriler Listesi (/kategoriler)',
    'category'  => 'Kategori Sayfası (varsayılan — {title} destekler)',
    'blog'      => 'Blog',
    'post'      => 'Blog Yazısı (varsayılan — {title} destekler)',
    'contact'   => 'İletişim',
    'about'     => 'Hakkımızda',
    'cart'      => 'Sepet',
    'product'   => 'Ürün Detay (varsayılan)',
];

require_once __DIR__ . '/core/header.php';
?>
<style>
.seo-field-help {
  font-size: 12px;
  color: var(--muted-text);
  margin-top: 5px;
  line-height: 1.5;
}
.seo-field-help strong { color: var(--champagne); }
.seo-preview-box {
  background: #fff;
  border-radius: 8px;
  padding: 14px 18px;
  margin-top: 10px;
  font-family: Arial, sans-serif;
  max-width: 560px;
}
.seo-preview-box .sp-url   { font-size: 12px; color: #202124; opacity: .7; }
.seo-preview-box .sp-title { font-size: 18px; color: #1a0dab; margin: 2px 0 3px; line-height: 1.3; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.seo-preview-box .sp-desc  { font-size: 13px; color: #3c4043; line-height: 1.4; }
.seo-char-bar {
  height: 4px;
  border-radius: 2px;
  background: var(--gold-border);
  margin-top: 6px;
  overflow: hidden;
}
.seo-char-bar-fill {
  height: 100%;
  border-radius: 2px;
  background: var(--gold);
  transition: width .2s, background .2s;
}
.slug-shortcut {
  display: inline-flex;
  align-items: center;
  gap: 5px;
  padding: 5px 10px;
  border: 1px solid var(--gold-border);
  border-radius: 5px;
  font-size: 12px;
  color: var(--champagne);
  cursor: pointer;
  text-decoration: none;
  background: transparent;
  transition: border-color .15s;
}
.slug-shortcut:hover { border-color: var(--gold); color: var(--gold); }
</style>

<div style="display:grid;grid-template-columns:280px 1fr;gap:24px;align-items:start">
  <!-- SOL: Sayfa listesi -->
  <div>
    <div class="panel" style="padding:18px">
      <h3 style="margin-bottom:12px">Sayfalar</h3>
      <p class="muted" style="font-size:12px;margin-bottom:14px">Düzenlemek istediğin sayfayı seç.</p>

      <div style="display:flex;flex-direction:column;gap:5px;margin-bottom:14px">
        <?php foreach ($rows as $r): ?>
          <a href="?slug=<?= e($r['page_slug']) ?>"
             style="display:flex;flex-direction:column;padding:9px 12px;border-radius:6px;border:1px solid <?= $editSlug===$r['page_slug']?'var(--gold)':'var(--gold-border)' ?>;background:<?= $editSlug===$r['page_slug']?'rgba(201,162,75,.1)':'transparent' ?>;text-decoration:none;transition:.15s">
            <strong style="font-size:13px;color:<?= $editSlug===$r['page_slug']?'var(--gold)':'var(--champagne)' ?>"><?= e($r['page_label'] ?: $r['page_slug']) ?></strong>
            <code style="font-size:10px;opacity:.6;margin-top:2px"><?= e($r['page_slug']) ?></code>
          </a>
        <?php endforeach; ?>
        <?php if (!$rows): ?>
          <p class="muted" style="font-size:12px">Henüz kayıt yok.</p>
        <?php endif; ?>
      </div>

      <div class="divider"></div>

      <!-- Hızlı slug seçenekleri -->
      <p class="muted" style="font-size:11px;margin-bottom:8px;text-transform:uppercase;letter-spacing:.1em">Hızlı Ekle</p>
      <div style="display:flex;flex-wrap:wrap;gap:6px;margin-bottom:12px">
        <?php foreach ($suggestedSlugs as $sslug => $slabel): if (!in_array($sslug, array_column($rows,'page_slug'))): ?>
          <a href="?slug=<?= e($sslug) ?>" class="slug-shortcut"><?= e($slabel) ?></a>
        <?php endif; endforeach; ?>
      </div>

      <!-- Manuel slug girişi -->
      <form method="get" style="display:flex;gap:6px">
        <input type="text" name="slug" placeholder="slug girin" required
               style="flex:1;padding:7px 10px;background:transparent;border:1px solid var(--gold-border);border-radius:5px;color:var(--champagne);font-size:12px">
        <button class="btn btn-secondary btn-sm">Git</button>
      </form>
    </div>

    <!-- Rehber kutusu -->
    <div class="panel" style="padding:16px;font-size:12px;line-height:1.7">
      <strong style="font-size:11px;letter-spacing:.12em;text-transform:uppercase;color:var(--gold)">Slug Rehberi</strong>
      <div style="margin-top:10px;display:flex;flex-direction:column;gap:5px">
        <?php foreach ($suggestedSlugs as $sslug => $slabel): ?>
          <div><code style="color:var(--gold);background:rgba(201,162,75,.1);padding:1px 5px;border-radius:3px"><?= e($sslug) ?></code>
          <span class="muted"> → <?= e($slabel) ?></span></div>
        <?php endforeach; ?>
        <div class="muted" style="margin-top:8px;font-size:11px">
          Ürün/blog detay sayfaları <code style="font-size:10px">product</code> / <code style="font-size:10px">post</code> slug'larını kullanır.
          CMS sayfaları (<em>page.php</em>) kendi slug'larını kullanır.
        </div>
      </div>
    </div>
  </div>

  <!-- SAĞ: Düzenleme formu -->
  <div class="panel">
    <?php if (!$editing): ?>
      <h3>SEO Yönetimi</h3>
      <p class="muted" style="margin-top:10px">Soldan bir sayfa seç veya hızlı erişim kısayollarından birini kullan.</p>
      <div style="margin-top:20px;padding:16px;border:1px solid var(--gold-border);border-radius:8px;font-size:13px;line-height:1.8">
        <strong style="color:var(--gold)">SEO nedir, neden önemlidir?</strong>
        <ul style="margin:10px 0 0 16px;color:var(--muted-text)">
          <li><strong style="color:var(--champagne)">Meta Title</strong> — Google arama sonuçlarında mavi başlık olarak görünür. Tıklanma oranını doğrudan etkiler.</li>
          <li><strong style="color:var(--champagne)">Meta Description</strong> — Başlığın altındaki gri metin. Sayfanın içeriğini özetler, tıklanmayı artırır.</li>
          <li><strong style="color:var(--champagne)">Open Graph Görseli</strong> — WhatsApp, Facebook, Twitter'da paylaşılınca görünen kapak görseli.</li>
          <li><strong style="color:var(--champagne)">Meta Robots</strong> — Sayfanın arama motorları tarafından indekslenip indekslenmeyeceğini belirler.</li>
        </ul>
      </div>
    <?php else: ?>
      <h3>Düzenle: <span style="color:var(--gold)"><?= e($editing['page_label'] ?: $editing['page_slug']) ?></span></h3>
      <p class="muted" style="font-size:13px;margin-bottom:20px">
        Slug: <code style="color:var(--gold)"><?= e($editing['page_slug']) ?></code>
        — Boş bırakılan alanlar için sistem varsayılanları kullanılır.
        <code style="color:var(--gold);font-size:11px">{title}</code> meta başlıkta dinamik sayfa başlığını temsil eder.
      </p>

      <form method="post" enctype="multipart/form-data" style="display:grid;gap:22px">
        <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
        <input type="hidden" name="action" value="save">

        <!-- Kimlik alanları -->
        <div class="row-2">
          <div class="field">
            <label>Slug (URL anahtarı)</label>
            <input name="page_slug" value="<?= e($editing['page_slug']) ?>" required pattern="[a-z0-9_\-]+" placeholder="home">
            <p class="seo-field-help">Hangi sayfaya ait olduğu. Örn: <strong>home</strong> → anasayfa, <strong>products</strong> → ürünler listesi.</p>
          </div>
          <div class="field">
            <label>Sayfa Etiketi <small class="muted">(sadece admin panelinde görünür)</small></label>
            <input name="page_label" value="<?= e($editing['page_label']) ?>" placeholder="Anasayfa">
          </div>
        </div>

        <!-- Meta Title -->
        <div class="field">
          <label style="display:flex;justify-content:space-between;align-items:center">
            <span>Meta Title — Arama Sonucu Başlığı</span>
            <small id="mt-count" class="muted">— / 60</small>
          </label>
          <input name="meta_title" id="meta_title_inp" value="<?= e($editing['meta_title']) ?>" maxlength="220"
                 placeholder="Örn: {title} · AquaShop">
          <div class="seo-char-bar"><div class="seo-char-bar-fill" id="mt-bar" style="width:0"></div></div>
          <p class="seo-field-help">
            <strong>Nerede görünür:</strong> Google arama listesinde mavi tıklanabilir başlık.<br>
            <strong>İdeal uzunluk:</strong> 50–60 karakter. Kısa tutun, ana anahtar kelimeyi öne alın.<br>
            <strong>{title}</strong> placeholder'ı kullanırsanız dinamik sayfa başlığıyla otomatik değiştirilir.
          </p>
          <!-- Canlı Google önizlemesi -->
          <div class="seo-preview-box" id="google-preview" style="display:none">
            <div class="sp-url"><?= e($_SERVER['HTTP_HOST'] ?? '') ?></div>
            <div class="sp-title" id="preview-title"></div>
            <div class="sp-desc" id="preview-desc"></div>
          </div>
        </div>

        <!-- Meta Description -->
        <div class="field">
          <label style="display:flex;justify-content:space-between;align-items:center">
            <span>Meta Description — Arama Sonucu Özeti</span>
            <small id="md-count" class="muted">— / 160</small>
          </label>
          <textarea name="meta_description" id="meta_desc_inp" rows="3" maxlength="500"
                    placeholder="Sayfanın ne hakkında olduğunu kısaca anlatın…"><?= e($editing['meta_description']) ?></textarea>
          <div class="seo-char-bar"><div class="seo-char-bar-fill" id="md-bar" style="width:0"></div></div>
          <p class="seo-field-help">
            <strong>Nerede görünür:</strong> Google'da başlığın altındaki gri açıklama metni.<br>
            <strong>İdeal uzunluk:</strong> 120–160 karakter. Ürün/hizmetinizin avantajlarını vurgulayın.<br>
            Google bu alanı doğrudan sıralamada kullanmaz ama iyi yazılmış açıklamalar tıklanma oranını artırır.
          </p>
        </div>

        <!-- Meta Keywords -->
        <div class="field">
          <label>Meta Keywords <small class="muted">(virgülle ayır)</small></label>
          <input name="meta_keywords" value="<?= e($editing['meta_keywords']) ?>" placeholder="akvaryum, balık yemi, filtre, aydınlatma">
          <p class="seo-field-help">
            <strong>⚠ Google bu etiketi artık sıralamada kullanmıyor.</strong>
            Yine de bazı eski arama motorları ve iç arama sistemleri okuyabilir. İsteğe bağlı bırakabilirsiniz.
          </p>
        </div>

        <!-- Meta Robots -->
        <div class="field">
          <label>Meta Robots — İndeksleme Kontrolü</label>
          <select name="meta_robots">
            <?php $mr = $editing['meta_robots'] ?? ''; ?>
            <option value="" <?= $mr===''?'selected':'' ?>>(Varsayılan — Ayarlar'dan alınır, genellikle: index, follow)</option>
            <option value="index, follow"    <?= $mr==='index, follow'?'selected':'' ?>>index, follow — ✅ Tara ve indeksle (normal sayfalar)</option>
            <option value="noindex, follow"  <?= $mr==='noindex, follow'?'selected':'' ?>>noindex, follow — Google'da çıkmasın ama linkleri takip etsin</option>
            <option value="index, nofollow"  <?= $mr==='index, nofollow'?'selected':'' ?>>index, nofollow — Sayfayı indeksle ama bağlantıları takip etme</option>
            <option value="noindex, nofollow" <?= $mr==='noindex, nofollow'?'selected':'' ?>>noindex, nofollow — ❌ Tamamen gizle (admin, teşekkür sayfaları vb.)</option>
            <option value="noarchive"        <?= $mr==='noarchive'?'selected':'' ?>>noarchive — Google önbelleğine alma</option>
          </select>
          <p class="seo-field-help">
            Çoğu sayfa için <strong>Varsayılan</strong> veya <strong>index, follow</strong> seçin.
            Sadece gizlemek istediğiniz sayfalar (teşekkür, hesap, ödeme) için <em>noindex</em> kullanın.
          </p>
        </div>

        <!-- Open Graph Görseli -->
        <div class="field">
          <label>Open Graph Görseli <small class="muted">(sosyal medya kapak görseli)</small></label>
          <?php if (!empty($editing['og_image'])): ?>
            <div style="margin-bottom:10px;display:flex;align-items:flex-start;gap:14px">
              <img src="<?= e($editing['og_image']) ?>" alt="OG önizleme"
                   style="max-width:260px;border:1px solid var(--gold-border);border-radius:6px">
              <div class="seo-field-help">
                Mevcut görsel ↑<br>Değiştirmek için aşağıdan yeni dosya seçin.
              </div>
            </div>
          <?php endif; ?>
          <input type="file" name="og_file" accept="image/*">
          <input type="hidden" name="og_image" value="<?= e($editing['og_image']) ?>">
          <p class="seo-field-help">
            <strong>Nerede görünür:</strong> WhatsApp, Facebook, Twitter'da link paylaşılınca çıkan büyük kapak görseli.<br>
            <strong>Önerilen boyut:</strong> 1200×630 piksel (2:1 oran). Yüklenen görsel WebP'ye dönüştürülür.
          </p>
        </div>

        <div style="display:flex;gap:10px;margin-top:6px">
          <button class="btn btn-primary">Kaydet</button>
          <a href="seo_manager.php" class="btn btn-secondary">İptal</a>
        </div>
      </form>

      <?php if (!empty($editing['page_slug']) && in_array($editing['page_slug'], array_column($rows,'page_slug'), true)): ?>
        <div class="divider"></div>
        <form method="post" onsubmit="return confirm('Bu SEO girdisi silinsin mi? İlgili sayfa etkilenmez, sadece SEO ayarları temizlenir.')">
          <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
          <input type="hidden" name="action" value="delete">
          <input type="hidden" name="page_slug" value="<?= e($editing['page_slug']) ?>">
          <button class="btn btn-danger btn-sm">Bu SEO Girdisini Sil</button>
        </form>
      <?php endif; ?>
    <?php endif; ?>
  </div>
</div>

<?php if ($editing): ?>
<script>
(function(){
  var site = '<?= e(addslashes(setting('site_name') ?? 'AquaShop')) ?>';

  function charCounter(inputId, countId, barId, max) {
    var el  = document.getElementById(inputId);
    var cnt = document.getElementById(countId);
    var bar = document.getElementById(barId);
    if (!el || !cnt) return;
    function upd() {
      var l = el.value.length;
      var pct = Math.min(100, Math.round(l / max * 100));
      cnt.textContent = l + ' / ' + max;
      if (bar) {
        bar.style.width = pct + '%';
        bar.style.background = l > max ? '#f5a3a3' : l > max * 0.85 ? 'var(--gold)' : '#4F5C26';
      }
    }
    el.addEventListener('input', upd); upd();
  }

  charCounter('meta_title_inp', 'mt-count', 'mt-bar', 60);
  charCounter('meta_desc_inp',  'md-count', 'md-bar', 160);

  // Canlı Google önizlemesi
  var titleInp = document.getElementById('meta_title_inp');
  var descInp  = document.getElementById('meta_desc_inp');
  var preview  = document.getElementById('google-preview');
  var pTitle   = document.getElementById('preview-title');
  var pDesc    = document.getElementById('preview-desc');

  function updatePreview() {
    var t = titleInp ? titleInp.value.replace('{title}', '') || ('Sayfa · ' + site) : '';
    var d = descInp  ? descInp.value : '';
    if (pTitle) pTitle.textContent = t || ('Sayfa · ' + site);
    if (pDesc)  pDesc.textContent  = d || 'Açıklama girilmemiş.';
    if (preview) preview.style.display = 'block';
  }

  if (titleInp) titleInp.addEventListener('input', updatePreview);
  if (descInp)  descInp.addEventListener('input', updatePreview);
  updatePreview();
})();
</script>
<?php endif; ?>

<?php require_once __DIR__ . '/core/footer.php'; ?>
