<?php
$page='products';
require_once __DIR__ . '/../core/auth.php';
require_once __DIR__ . '/../../includes/media.php';
require_once __DIR__ . '/../../includes/indexnow.php';

$id = (int)($_GET['id'] ?? 0);
$p = ['id'=>0,'category_id'=>null,'name'=>'','slug'=>'','sku'=>'','brand'=>'','short_desc'=>'','description'=>'','price'=>0,'old_price'=>null,'price_on_request'=>0,'stock'=>0,'is_active'=>1,'is_featured'=>0,'image'=>''];
if ($id) {
    $st=db()->prepare('SELECT * FROM products WHERE id=?'); $st->execute([$id]); $p = $st->fetch() ?: $p;
}

// Pivot tabloyu otomatik oluştur (yoksa)
try {
    db()->exec("CREATE TABLE IF NOT EXISTS product_categories (
        product_id INT NOT NULL, category_id INT NOT NULL,
        PRIMARY KEY (product_id, category_id), INDEX idx_cat (category_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
} catch (\Throwable $e) {}

// Mevcut ürünün seçili kategorilerini çek
$selectedCatIds = [];
if ($p['id']) {
    try {
        $st = db()->prepare('SELECT category_id FROM product_categories WHERE product_id=?');
        $st->execute([$p['id']]);
        $selectedCatIds = array_column($st->fetchAll(), 'category_id');
    } catch (\Throwable $e) {}
    // pivot boşsa category_id'yi fallback olarak kullan
    if (empty($selectedCatIds) && $p['category_id']) {
        $selectedCatIds = [(int)$p['category_id']];
    }
}
$title = $p['id'] ? 'Ürün Düzenle' : 'Yeni Ürün';

if ($_SERVER['REQUEST_METHOD']==='POST' && csrf_check($_POST['csrf'] ?? null)) {
    $name=trim($_POST['name']??'');
    $trMap=['ç'=>'c','ğ'=>'g','ı'=>'i','ö'=>'o','ş'=>'s','ü'=>'u','Ç'=>'c','Ğ'=>'g','İ'=>'i','Ö'=>'o','Ş'=>'s','Ü'=>'u'];
    $slug=trim($_POST['slug']??'') ?: trim(preg_replace('~[^a-z0-9]+~','-',strtolower(strtr($name,$trMap))),'-');
    // Çoklu kategori — ilki birincil category_id olarak saklanır
    $catIds = array_map('intval', array_filter((array)($_POST['category_ids'] ?? []), 'is_numeric'));
    $cat = !empty($catIds) ? $catIds[0] : null;
    $priceOnRequest = isset($_POST['price_on_request']) ? 1 : 0;
    // "İletişime Geçin" ürünlerinde de fiyat saklanır — frontend'de fiyat + buton gösterilir.
    // (Boş/0 bırakılırsa frontend yalnızca "İletişime Geçin" yazısı gösterir.)
    $price = (float)($_POST['price'] ?? 0);
    $old=$_POST['old_price']!==''?(float)$_POST['old_price']:null;
    $sku=trim($_POST['sku'] ?? '');
    $brand=trim($_POST['brand'] ?? '');
    $stock=(int)$_POST['stock']; $sd=trim($_POST['short_desc']??''); $desc=trim($_POST['description']??'');
    $act=isset($_POST['is_active'])?1:0; $feat=isset($_POST['is_featured'])?1:0;
    $hasVar = isset($_POST['has_variations']) ? 1 : 0;
    $image = isset($_POST['image']) ? trim($_POST['image']) : $p['image'];
    if (!empty($_POST['remove_image'])) {
        $image = null;
    }
    if (!empty($_FILES['image_file']['name'])) {
        // Ürün ana görseli — detay sayfasında 600x600 container, mobilde shrink, retina 2x için 1200 yeterli
        $r = media_upload_from_files($_FILES['image_file'], array('max_width'=>1200,'max_height'=>1200,'quality'=>80));
        if ($r['ok']) $image = $r['path'];
        else flash_set('err','Görsel yüklenemedi: '.$r['error']);
    }
    if ($p['id']) {
        $oldPrice = (float)$p['price']; // fiyat değişikliği kontrolü için
        $st=db()->prepare('UPDATE products SET category_id=?,name=?,slug=?,sku=?,brand=?,short_desc=?,description=?,price=?,old_price=?,price_on_request=?,stock=?,image=?,is_active=?,is_featured=?,has_variations=? WHERE id=?');
        $st->execute(array($cat,$name,$slug,$sku ?: null,$brand ?: null,$sd,$desc,$price,$old,$priceOnRequest,$stock,$image,$act,$feat,$hasVar,$p['id']));
        flash_set('ok','Ürün güncellendi.');
        $productId = $p['id'];

        // Fiyat düştüyse: bu ürünü favorileyen email_consent'li üyelere indirim maili gönder
        if ($price < $oldPrice) {
            try {
                require_once __DIR__ . '/../../includes/mailer.php';
                $favUsers = db()->prepare(
                    'SELECT f.user_id, f.price_at_fav, u.email, u.name
                     FROM favorites f
                     JOIN users u ON u.id = f.user_id
                     WHERE f.product_id = ? AND u.email_consent = 1 AND u.email IS NOT NULL'
                );
                $favUsers->execute([$p['id']]);
                $recipients = $favUsers->fetchAll();
                foreach ($recipients as $fu) {
                    $prevPrice = $fu['price_at_fav'] ? (float)$fu['price_at_fav'] : $oldPrice;
                    if ($price >= $prevPrice) continue; // bu kişi için gerçek indirim değilse atla
                    $tmpl = mail_template_get('price_alert',
                        [
                            '{{isim}}'      => $fu['name'],
                            '{{urun_adi}}'  => $name,
                            '{{eski_fiyat}}'=> number_format($prevPrice, 2, ',', '.') . ' ₺',
                            '{{yeni_fiyat}}'=> number_format($price,     2, ',', '.') . ' ₺',
                            '{{urun_url}}'  => (defined('SITE_URL') ? rtrim(SITE_URL,'/') : '') . '/urun/' . rawurlencode($slug),
                        ],
                        'Favorinizdeki ' . $name . ' indirimde!',
                        '<p>Merhaba <strong>{{isim}}</strong>,</p>'
                        . '<p>Favorilerinize eklediğiniz <strong>{{urun_adi}}</strong> ürününün fiyatı düştü!</p>'
                        . '<p>Eski fiyat: <del>{{eski_fiyat}}</del><br>Yeni fiyat: <strong style="color:#4F5C26">{{yeni_fiyat}}</strong></p>'
                    );
                    $body = mail_template($tmpl['subject'], $tmpl['body_html'], 'Ürünü İncele',
                        (defined('SITE_URL') ? rtrim(SITE_URL,'/') : '') . '/urun/' . rawurlencode($slug));
                    mail_send($fu['email'], $tmpl['subject'], $body);
                    // price_at_fav'ı yeni fiyatla güncelle
                    db()->prepare('UPDATE favorites SET price_at_fav=? WHERE user_id=? AND product_id=?')
                        ->execute([$price, $fu['user_id'], $p['id']]);
                }
            } catch (Exception $e) { /* mail gönderilemezse sessizce geç */ }
        }
    } else {
        $st=db()->prepare('INSERT INTO products (category_id,name,slug,sku,brand,short_desc,description,price,old_price,price_on_request,stock,image,is_active,is_featured,has_variations) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)');
        $st->execute(array($cat,$name,$slug,$sku ?: null,$brand ?: null,$sd,$desc,$price,$old,$priceOnRequest,$stock,$image,$act,$feat,$hasVar));
        flash_set('ok','Ürün eklendi.');
        $productId = (int)db()->lastInsertId();
    }

    // === Çoklu kategori pivot güncelle ===
    if ($productId) {
        try {
            db()->prepare('DELETE FROM product_categories WHERE product_id=?')->execute([$productId]);
            if (!empty($catIds)) {
                $ins = db()->prepare('INSERT IGNORE INTO product_categories (product_id, category_id) VALUES (?,?)');
                foreach ($catIds as $cid) { $ins->execute([$productId, $cid]); }
            }
        } catch (\Throwable $e) {}
    }

    // === Varyasyon kaydetme ===
    if ($productId && !empty($_POST['variations']) && is_array($_POST['variations'])) {
        $vIns = db()->prepare('INSERT INTO product_variations (product_id, label, sku, price, old_price, stock, sort_order) VALUES (?,?,?,?,?,?,?)');
        $vUpd = db()->prepare('UPDATE product_variations SET label=?, sku=?, price=?, old_price=?, stock=?, sort_order=?, is_active=? WHERE id=? AND product_id=?');
        $vDel = db()->prepare('DELETE FROM product_variations WHERE id=? AND product_id=?');
        foreach ($_POST['variations'] as $idx => $v) {
            $vid    = (int)($v['id'] ?? 0);
            $label  = trim($v['label'] ?? '');
            $vsku   = trim($v['sku'] ?? '');
            $vprice = (float)($v['price'] ?? 0);
            $vold   = ($v['old_price'] ?? '') !== '' ? (float)$v['old_price'] : null;
            $vstock = (int)($v['stock'] ?? 0);
            $vact   = !empty($v['is_active']) ? 1 : 0;
            $delete = !empty($v['delete']);
            if ($delete && $vid) { $vDel->execute([$vid, $productId]); continue; }
            if ($label === '') continue;
            if ($vid) {
                $vUpd->execute([$label, $vsku ?: null, $vprice, $vold, $vstock, $idx, $vact, $vid, $productId]);
            } else {
                $vIns->execute([$productId, $label, $vsku ?: null, $vprice, $vold, $vstock, $idx]);
            }
        }
    }

    // Galeri görsellerinden silinmek istenenler
    if (!empty($_POST['gallery_remove']) && is_array($_POST['gallery_remove'])) {
        $del = db()->prepare('DELETE FROM product_images WHERE id=? AND product_id=?');
        foreach ($_POST['gallery_remove'] as $gid) {
            $del->execute(array((int)$gid, (int)$productId));
        }
    }
    // Yeni galeri görselleri (çoklu)
    if ($productId && !empty($_FILES['gallery_files']['name'][0])) {
        $files = $_FILES['gallery_files'];
        $ins = db()->prepare('INSERT INTO product_images (product_id, path, sort_order) VALUES (?,?,?)');
        $count = is_array($files['name']) ? count($files['name']) : 0;
        // Mevcut max sort_order'ı bul
        $stMax = db()->prepare('SELECT COALESCE(MAX(sort_order),0)+1 FROM product_images WHERE product_id=?');
        $stMax->execute(array($productId)); $next = (int)$stMax->fetchColumn();
        $okN=0; $errN=0;
        for ($i=0; $i<$count; $i++) {
            if (empty($files['name'][$i])) continue;
            $f = array(
                'name'=>$files['name'][$i],'tmp_name'=>$files['tmp_name'][$i],
                'error'=>$files['error'][$i],'size'=>$files['size'][$i],'type'=>$files['type'][$i]
            );
            // Galeri görselleri — ana görsel ile aynı: 1200x1200 max
            $r = media_upload_from_files($f, array('max_width'=>1200,'max_height'=>1200,'quality'=>80));
            if ($r['ok']) {
                $ins->execute(array($productId, $r['path'], $next++));
                $okN++;
            } else { $errN++; }
        }
        if ($okN > 0) flash_set('ok',"$okN galeri görseli eklendi" . ($errN ? ", $errN hata" : "."));
        elseif ($errN) flash_set('err',"$errN galeri görseli yüklenemedi.");
    }

    // SSS kayıtları — temizle, yeniden ekle
    if ($productId) {
        db()->prepare('DELETE FROM product_faqs WHERE product_id=?')->execute(array($productId));
        $faqQ = isset($_POST['faq_q']) ? (array)$_POST['faq_q'] : array();
        $faqA = isset($_POST['faq_a']) ? (array)$_POST['faq_a'] : array();
        $ins = db()->prepare('INSERT INTO product_faqs (product_id,question,answer,sort_order) VALUES (?,?,?,?)');
        $i = 0;
        foreach ($faqQ as $idx => $q) {
            $q = trim($q);
            $a = trim($faqA[$idx] ?? '');
            if ($q !== '' && $a !== '') {
                $ins->execute(array($productId, $q, $a, $i++));
            }
        }
    }
    // IndexNow — aktif ürünleri Bing/Yandex'e bildir
    if ($act && !empty($slug)) {
        $siteBase = rtrim((string)setting('site_url', ''), '/');
        if ($siteBase === '') {
            $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
            $siteBase = defined('SITE_URL') ? rtrim(SITE_URL, '/') : ($scheme . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost'));
        }
        indexnow_ping([$siteBase . url('product', ['slug' => $slug])]);
    }

    $backPage = max(1, (int)($_GET['paged'] ?? 1));
    redirect('list.php' . ($backPage > 1 ? '?paged=' . $backPage : ''));
}

// Mevcut FAQ kayıtlarını çek (form için)
$faqs = array();
$gallery = array();
if ($p['id']) {
    try {
        $st = db()->prepare('SELECT * FROM product_faqs WHERE product_id=? ORDER BY sort_order, id');
        $st->execute(array($p['id']));
        $faqs = $st->fetchAll();
    } catch (Exception $e) {}
    try {
        $st = db()->prepare('SELECT * FROM product_images WHERE product_id=? ORDER BY sort_order, id');
        $st->execute(array($p['id']));
        $gallery = $st->fetchAll();
    } catch (Exception $e) {}
}

// Kategorileri ağaç düzeninde yükle (ana → alt)
$cats = db()->query('SELECT * FROM categories ORDER BY parent_id ASC, sort_order ASC, name ASC')->fetchAll();
// Ana kategorileri ve altlarını grupla
$catTree = []; $catById = [];
foreach ($cats as $c) $catById[$c['id']] = $c;
foreach ($cats as $c) {
    if (!$c['parent_id']) $catTree[] = $c;
}
require_once __DIR__ . '/../core/header.php';
?>

<div class="panel">
  <form method="post" enctype="multipart/form-data" style="display:grid;gap:18px;max-width:760px">
    <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
    <div class="field">
      <label>Ürün Görseli</label>
      <?php if (!empty($p['image'])): ?>
        <div style="display:flex;gap:14px;align-items:flex-start;margin-bottom:10px">
          <img src="<?= e($p['image']) ?>" alt="Mevcut ürün görseli" style="width:160px;height:160px;object-fit:cover;border:1px solid var(--gold-border);border-radius:6px">
          <label style="display:flex;gap:8px;align-items:center;font-size:13px;color:#f5a3a3;cursor:pointer">
            <input type="checkbox" name="remove_image" value="1"> Görseli kaldır (kaydedince uygulanır)
          </label>
        </div>
      <?php endif; ?>
      <input type="file" name="image_file" accept="image/*">
      <small class="muted">Yüklenen görsel otomatik WebP'ye dönüştürülür ve sıkıştırılır. Yeni dosya seçersen mevcut görselin yerini alır.</small>
      <input type="hidden" name="image" value="<?= e($p['image']) ?>">
    </div>

    <!-- Galeri görselleri -->
    <div class="field">
      <label style="display:flex;justify-content:space-between;align-items:center">
        <span>Galeri Görselleri (Ana görsel dışında ek fotoğraflar)</span>
        <?php if ($gallery): ?><small class="muted"><?= count($gallery) ?> görsel</small><?php endif; ?>
      </label>
      <small class="muted" style="display:block;margin-bottom:10px">Birden fazla dosya seçebilirsiniz. Ürün detay sayfasında ana görselin altında thumbnail olarak görünür ve tıklayınca büyütülür.</small>

      <?php if ($gallery): ?>
        <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(120px,1fr));gap:10px;margin-bottom:14px">
          <?php foreach ($gallery as $g): ?>
            <label style="position:relative;cursor:pointer">
              <img src="<?= e($g['path']) ?>" alt="" style="width:100%;aspect-ratio:1;object-fit:cover;border:1px solid var(--gold-border);border-radius:6px;display:block">
              <span style="position:absolute;top:6px;right:6px;background:rgba(15,23,42,.7);color:#fff;border-radius:999px;padding:3px 8px;font-size:11px;display:flex;align-items:center;gap:4px">
                <input type="checkbox" name="gallery_remove[]" value="<?= (int)$g['id'] ?>" style="accent-color:#f5a3a3"> Sil
              </span>
            </label>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>

      <input type="file" name="gallery_files[]" accept="image/*" multiple>
      <small class="muted">Yeni görseller ekle (çoklu seçim).</small>
    </div>
    <div class="row-2">
      <div class="field"><label>Ürün Adı</label><input name="name" value="<?= e($p['name']) ?>" required></div>
      <div class="field"><label>Stok Kodu (SKU)</label><input name="sku" value="<?= e($p['sku']) ?>" placeholder="ÖR: TDR1545"></div>
    </div>
    <div class="row-2">
      <div class="field"><label>Marka</label><input name="brand" value="<?= e($p['brand'] ?? '') ?>" list="brand-list" placeholder="Örn: Kavlak"></div>
      <div class="field"><label>Slug (boşsa otomatik)</label><input name="slug" value="<?= e($p['slug']) ?>"></div>
    </div>
    <datalist id="brand-list">
      <?php try { foreach (db()->query("SELECT DISTINCT brand FROM products WHERE brand IS NOT NULL AND brand<>'' ORDER BY brand") as $b): ?>
        <option value="<?= e($b['brand']) ?>"></option>
      <?php endforeach; } catch (Exception $e) {} ?>
    </datalist>
    <div class="field">
      <label>Kategoriler <span class="muted" style="font-weight:400;font-size:12px">— birden fazla seçebilirsiniz, ilk seçilen birincil kategori olur</span></label>
      <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(220px,1fr));gap:6px;max-height:260px;overflow-y:auto;padding:12px;border:1px solid var(--gold-border);border-radius:var(--radius);background:var(--olive-2)">
        <?php foreach ($cats as $c):
            $isChecked = in_array((int)$c['id'], $selectedCatIds);
            $indent = $c['parent_id'] ? 'padding-left:18px;' : '';
            $prefix = $c['parent_id'] ? '↳ ' : '';
        ?>
          <label style="display:flex;align-items:center;gap:8px;cursor:pointer;padding:6px 8px;border-radius:6px;transition:background .15s;font-size:13px;<?= $indent ?>"
                 onmouseover="this.style.background='var(--cream)'" onmouseout="this.style.background=''">
            <input type="checkbox" name="category_ids[]" value="<?= (int)$c['id'] ?>"
                   <?= $isChecked ? 'checked' : '' ?>
                   style="accent-color:var(--gold);width:15px;height:15px;flex-shrink:0">
            <span><?= $prefix . e($c['name']) ?></span>
          </label>
        <?php endforeach; ?>
        <?php if (empty($cats)): ?>
          <p class="muted" style="font-size:12px;margin:0">Henüz kategori oluşturulmamış.</p>
        <?php endif; ?>
      </div>
    </div>
    <div class="field" style="max-width:220px">
      <label>Stok</label>
      <input name="stock" type="number" min="0" value="<?= (int)$p['stock'] ?>">
    </div>
    <div style="margin-bottom:4px">
      <label style="display:inline-flex;align-items:center;gap:8px;font-size:13px;cursor:pointer;padding:8px 14px;border:1px solid var(--gold-border);border-radius:var(--radius);background:var(--olive-2)">
        <input type="checkbox" name="price_on_request" id="price_on_request_toggle" value="1" <?= !empty($p['price_on_request'])?'checked':'' ?>>
        <strong>İletişime Geçin (online satışa kapalı)</strong> — "Sepete Ekle" yerine "İletişime Geçin" butonu gösterilir. Fiyat girilirse hem üründe hem burada görünür; boş/0 bırakılırsa yalnızca "İletişime Geçin" yazısı çıkar.
      </label>
    </div>
    <div class="row-2" id="price-fields">
      <div class="field"><label>Fiyat (₺)</label><input name="price" id="price-input" type="number" step="0.01" value="<?= e($p['price']) ?>"></div>
      <div class="field"><label>Eski Fiyat (₺, opsiyonel)</label><input name="old_price" type="number" step="0.01" value="<?= e($p['old_price']) ?>"></div>
    </div>
    <script>
    (function(){
      var cb = document.getElementById('price_on_request_toggle');
      var pi = document.getElementById('price-input');
      if (!cb || !pi) return;
      // Fiyat alanı her zaman görünür kalır. Normal üründe fiyat zorunlu;
      // "İletişime Geçin" işaretliyse opsiyonel (boş bırakılabilir).
      function sync(){ pi.required = !cb.checked; }
      cb.addEventListener('change', sync);
      sync();
    })();
    </script>
    <!-- ✨ AI ile Doldur -->
    <div style="border:1px solid var(--gold-border);border-radius:10px;padding:16px 18px;background:rgba(107,122,47,.07)">
      <div style="display:flex;align-items:center;gap:12px;flex-wrap:wrap">
        <span style="font-size:13px;font-weight:600;color:var(--champagne)">✨ AI ile Otomatik Doldur</span>
        <span style="font-size:12px;color:var(--muted)">Ürün adı + marka + kategori bilgisiyle açıklama ve SSS üretir</span>
        <button type="button" id="aiFillBtn" class="btn btn-secondary btn-sm" style="margin-left:auto;border-color:var(--gold);color:var(--gold)">
          ✨ AI ile Doldur
        </button>
      </div>
      <div id="aiStatus" style="display:none;margin-top:10px;font-size:13px;color:var(--muted)"></div>
    </div>

    <div class="field"><label>Kısa Açıklama</label><input name="short_desc" value="<?= e($p['short_desc']) ?>"></div>
    <div class="field">
      <label>Detaylı Açıklama</label>
      <div id="desc-editor" class="rt-editor"><?= $p['description'] ?? '' ?></div>
      <textarea name="description" id="desc-textarea" hidden><?= e($p['description'] ?? '') ?></textarea>
      <small class="muted">Başlık, kalın, italik, liste, bağlantı ve alıntı kullanabilirsiniz. Yapıştırırken biçimlendirme otomatik temizlenir.</small>
    </div>

    <!-- Sık Sorulan Sorular -->
    <div class="field">
      <label style="display:flex;justify-content:space-between;align-items:center">
        <span>Sık Sorulan Sorular</span>
        <button type="button" class="btn btn-secondary btn-sm" id="faq-add">+ Yeni Soru</button>
      </label>
      <small class="muted">Bu sorular ürün detay sayfasında akordiyon olarak gösterilir ve Google FAQ schema'sı olarak da yayınlanır (SEO katkısı).</small>
      <div id="faq-list" style="display:flex;flex-direction:column;gap:14px;margin-top:14px">
        <?php if ($faqs): foreach ($faqs as $f): ?>
          <div class="faq-row" style="border:1px solid var(--gold-border);border-radius:8px;padding:14px;background:rgba(15,26,16,.4)">
            <div style="display:flex;gap:10px;align-items:center;margin-bottom:8px">
              <strong style="color:var(--gold);font-size:12px;letter-spacing:.18em;text-transform:uppercase">Soru</strong>
              <button type="button" class="btn btn-secondary btn-sm faq-del" style="margin-left:auto;color:#f5a3a3;border-color:#f5a3a3">Sil</button>
            </div>
            <input type="text" name="faq_q[]" value="<?= e($f['question']) ?>" placeholder="Sorunuz" required style="margin-bottom:10px">
            <strong style="color:var(--gold);font-size:12px;letter-spacing:.18em;text-transform:uppercase;display:block;margin-bottom:6px">Cevap</strong>
            <textarea name="faq_a[]" rows="3" placeholder="Cevabınız" required><?= e($f['answer']) ?></textarea>
          </div>
        <?php endforeach; endif; ?>
      </div>
      <template id="faq-template">
        <div class="faq-row" style="border:1px solid var(--gold-border);border-radius:8px;padding:14px;background:rgba(15,26,16,.4)">
          <div style="display:flex;gap:10px;align-items:center;margin-bottom:8px">
            <strong style="color:var(--gold);font-size:12px;letter-spacing:.18em;text-transform:uppercase">Soru</strong>
            <button type="button" class="btn btn-secondary btn-sm faq-del" style="margin-left:auto;color:#f5a3a3;border-color:#f5a3a3">Sil</button>
          </div>
          <input type="text" name="faq_q[]" placeholder="Sorunuz" required style="margin-bottom:10px">
          <strong style="color:var(--gold);font-size:12px;letter-spacing:.18em;text-transform:uppercase;display:block;margin-bottom:6px">Cevap</strong>
          <textarea name="faq_a[]" rows="3" placeholder="Cevabınız" required></textarea>
        </div>
      </template>
    </div>
    <div style="display:flex;gap:24px;flex-wrap:wrap">
      <label><input type="checkbox" name="is_active" <?= $p['is_active']?'checked':'' ?>> Aktif</label>
      <label><input type="checkbox" name="is_featured" <?= $p['is_featured']?'checked':'' ?>> Öne Çıkan</label>
      <label><input type="checkbox" name="has_variations" id="has-variations-toggle" <?= !empty($p['has_variations'])?'checked':'' ?>> Varyasyonlu Ürün (1L/5L vb.)</label>
    </div>

    <?php
      // Mevcut varyasyonları çek
      $existingVariations = [];
      if ($p['id']) {
          require_once __DIR__ . '/../../includes/variations.php';
          $existingVariations = product_variations((int)$p['id'], false);
      }
    ?>
    <div id="variations-block" style="<?= !empty($p['has_variations'])?'':'display:none' ?>;border:1px solid var(--gold-border);border-radius:var(--radius);padding:18px;margin-top:6px">
      <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:14px">
        <h4 style="margin:0;font-family:'Inter',sans-serif;font-size:13px;letter-spacing:.18em;text-transform:uppercase;color:var(--ink);font-weight:600">Varyasyonlar</h4>
        <button type="button" class="btn btn-secondary btn-sm" id="add-variation">+ Varyasyon Ekle</button>
      </div>
      <p class="muted" style="font-size:13px;margin-bottom:14px">Aynı ürünün farklı boyut/ağırlık/seçeneklerini buradan yönet. Her varyasyonun kendi <strong>fiyat</strong> ve <strong>stoğu</strong> olur. Yukarıdaki ana ürün fiyatı/stoğu varyasyonlu ürünlerde kullanılmaz; sadece yedek olarak tutulur.</p>

      <!-- Kolon başlıkları -->
      <div style="display:grid;grid-template-columns:2fr 1.2fr 1fr 1fr 1fr auto;gap:8px;padding:0 10px;margin-bottom:4px">
        <div style="font-size:11px;color:var(--muted-text);letter-spacing:.08em;text-transform:uppercase;font-weight:600">
          Seçenek / İsim
          <span title="Müşterinin göreceği varyasyon adı. Örn: 250ml, 500ml, 1 Litre" style="cursor:help;opacity:.6">ⓘ</span>
        </div>
        <div style="font-size:11px;color:var(--muted-text);letter-spacing:.08em;text-transform:uppercase;font-weight:600">
          SKU (Stok Kodu)
          <span title="Opsiyonel. Kendi envanter kodunuz. Örn: TDR-500" style="cursor:help;opacity:.6">ⓘ</span>
        </div>
        <div style="font-size:11px;color:var(--muted-text);letter-spacing:.08em;text-transform:uppercase;font-weight:600">
          Satış Fiyatı ₺
          <span title="Müşteriye gösterilen gerçek fiyat" style="cursor:help;opacity:.6">ⓘ</span>
        </div>
        <div style="font-size:11px;color:var(--muted-text);letter-spacing:.08em;text-transform:uppercase;font-weight:600">
          İndirimli? ₺
          <span title="Opsiyonel. Dolu ise üzeri çizili eski fiyat olarak gösterilir" style="cursor:help;opacity:.6">ⓘ</span>
        </div>
        <div style="font-size:11px;color:var(--muted-text);letter-spacing:.08em;text-transform:uppercase;font-weight:600">
          Stok Adedi
          <span title="Bu varyasyonun stoktaki miktarı. 0 ise 'stokta yok' gösterilir" style="cursor:help;opacity:.6">ⓘ</span>
        </div>
        <div style="font-size:11px;color:var(--muted-text);letter-spacing:.08em;text-transform:uppercase;font-weight:600">İşlem</div>
      </div>

      <div id="variations-list" data-start-idx="<?= count($existingVariations) ?>" style="display:flex;flex-direction:column;gap:8px">
        <?php foreach ($existingVariations as $i => $v): ?>
          <div class="variation-row" style="display:grid;grid-template-columns:2fr 1.2fr 1fr 1fr 1fr auto;gap:8px;align-items:center;padding:12px 10px;background:var(--cream);border-radius:var(--radius);border:1px solid var(--gold-border)">
            <input type="hidden" name="variations[<?= $i ?>][id]" value="<?= (int)$v['id'] ?>">
            <input name="variations[<?= $i ?>][label]" value="<?= e($v['label']) ?>" placeholder="örn. 1 Litre Cam Şişe" title="Müşteriye gösterilecek seçenek adı" required>
            <input name="variations[<?= $i ?>][sku]" value="<?= e($v['sku']) ?>" placeholder="Stok kodu (opsiyonel)" title="Kendi iç envanter kodunuz">
            <input name="variations[<?= $i ?>][price]" type="number" step="0.01" min="0" value="<?= e($v['price']) ?>" placeholder="0.00" title="Satış fiyatı (₺)" required>
            <input name="variations[<?= $i ?>][old_price]" type="number" step="0.01" min="0" value="<?= e($v['old_price']) ?>" placeholder="— (opsiyonel)" title="Boş bırakın veya indirim yoksa 0">
            <input name="variations[<?= $i ?>][stock]" type="number" min="0" value="<?= (int)$v['stock'] ?>" placeholder="0" title="Stok adedi (0 = tükendi)" required>
            <div style="display:flex;flex-direction:column;gap:6px;align-items:flex-start">
              <label style="font-size:11px;display:flex;align-items:center;gap:4px;white-space:nowrap"><input type="checkbox" name="variations[<?= $i ?>][is_active]" value="1" <?= $v['is_active']?'checked':'' ?>> Aktif</label>
              <label style="font-size:11px;color:#9A2A2A;display:flex;align-items:center;gap:4px;white-space:nowrap"><input type="checkbox" name="variations[<?= $i ?>][delete]" value="1"> Sil</label>
            </div>
          </div>
        <?php endforeach; ?>
      </div>

      <template id="variation-template">
        <div class="variation-row" style="display:grid;grid-template-columns:2fr 1.2fr 1fr 1fr 1fr auto;gap:8px;align-items:center;padding:12px 10px;background:var(--cream);border-radius:var(--radius);border:1px solid var(--gold-border)">
          <input name="variations[__IDX__][label]" placeholder="örn. 1 Litre Cam Şişe" title="Müşteriye gösterilecek seçenek adı" required>
          <input name="variations[__IDX__][sku]" placeholder="Stok kodu (opsiyonel)" title="Kendi iç envanter kodunuz">
          <input name="variations[__IDX__][price]" type="number" step="0.01" min="0" placeholder="0.00" title="Satış fiyatı (₺)" required>
          <input name="variations[__IDX__][old_price]" type="number" step="0.01" min="0" placeholder="— (opsiyonel)" title="Boş bırakın veya indirim yoksa 0">
          <input name="variations[__IDX__][stock]" type="number" min="0" value="0" placeholder="0" title="Stok adedi" required>
          <label style="font-size:11px;display:flex;align-items:center;gap:4px;white-space:nowrap"><input type="checkbox" name="variations[__IDX__][is_active]" value="1" checked> Aktif</label>
        </div>
      </template>

    </div>
    <div style="display:flex;gap:12px">
      <button class="btn btn-primary">Kaydet</button>
      <a class="btn btn-secondary" href="<?= url('products') ?>">Vazgeç</a>
    </div>
  </form>
</div>

<!-- Quill Rich Text Editor (CDN) + admin product-edit assets -->
<link href="https://cdn.jsdelivr.net/npm/quill@2.0.2/dist/quill.snow.css" rel="stylesheet">
<link rel="stylesheet" href="<?= SITE_URL ?>/assets/css/pages/admin-product-edit.css?v=<?= @filemtime(__DIR__ . '/../../assets/css/pages/admin-product-edit.css') ?: time() ?>">
<script src="https://cdn.jsdelivr.net/npm/quill@2.0.2/dist/quill.js"></script>
<script defer src="<?= SITE_URL ?>/assets/js/admin/product-edit.js?v=<?= @filemtime(__DIR__ . '/../../assets/js/admin/product-edit.js') ?: time() ?>"></script>

<script>
// ✨ AI ile Doldur
(function () {
  const btn    = document.getElementById('aiFillBtn');
  const status = document.getElementById('aiStatus');
  if (!btn) return;

  btn.addEventListener('click', async function () {
    const name     = document.querySelector('[name="name"]')?.value?.trim();
    const brand    = document.querySelector('[name="brand"]')?.value?.trim()    || '';
    const sku      = document.querySelector('[name="sku"]')?.value?.trim()       || '';

    // Seçili kategorilerin adını topla
    const catLabels = [...document.querySelectorAll('[name="category_ids[]"]:checked')]
      .map(cb => cb.closest('label')?.textContent?.trim().replace(/^↳\s*/, '') || '')
      .filter(Boolean).join(', ');

    if (!name) {
      alert('Önce ürün adını girin.');
      return;
    }

    btn.disabled = true;
    btn.textContent = '⏳ Üretiliyor…';
    status.style.display = 'block';
    status.textContent   = 'Claude ile içerik üretiliyor, lütfen bekleyin (5-15 sn)…';

    try {
      const csrf = document.querySelector('[name="csrf"]')?.value || '';
      const fd   = new FormData();
      fd.append('csrf',     csrf);
      fd.append('name',     name);
      fd.append('brand',    brand);
      fd.append('sku',      sku);
      fd.append('category', catLabels);

      const res  = await fetch('<?= SITE_URL ?>/admin_panel/ajax/ai-product-content.php', {
        method: 'POST', body: fd
      });
      const data = await res.json();

      if (data.error) {
        status.innerHTML = '<span style="color:#e05555">⚠ ' + data.error + '</span>';
        return;
      }

      // Kısa açıklama
      const sdInput = document.querySelector('input[name="short_desc"]');
      if (sdInput && data.short_desc) {
        sdInput.value = data.short_desc;
      }

      // Detaylı açıklama — Quill üzerinden güncelle
      if (data.description) {
        // Markdown → HTML dönüştür
        function mdToHtml(md) {
          return md
            .split(/\n\n+/)                                           // bloklara böl
            .map(block => {
              const t = block.trim();
              if (!t) return '';
              if (/^### (.+)/.test(t))  return '<h3>' + t.replace(/^### /, '') + '</h3>';
              if (/^## (.+)/.test(t))   return '<h2>' + t.replace(/^## /, '') + '</h2>';
              if (/^# (.+)/.test(t))    return '<h2>' + t.replace(/^# /, '') + '</h2>';
              // Satır içi: **kalın**, *italik*
              const inline = t
                .replace(/\*\*(.+?)\*\*/g, '<strong>$1</strong>')
                .replace(/\*(.+?)\*/g,     '<em>$1</em>')
                .replace(/\n/g, '<br>');
              return '<p>' + inline + '</p>';
            })
            .filter(Boolean)
            .join('');
        }
        const descHtml = mdToHtml(data.description);

        if (window.__productQuill) {
          // Quill'in kendi API'siyle güncelle
          window.__productQuill.root.innerHTML = descHtml;
          // Hidden textarea'yı da hemen sync et
          const descTA = document.getElementById('desc-textarea');
          if (descTA) descTA.value = window.__productQuill.root.innerHTML;
        } else {
          // Quill henüz yüklenmediyse (nadir) — .ql-editor'u veya textarea'yı doldur
          const qlEditor = document.querySelector('#desc-editor .ql-editor');
          if (qlEditor) {
            qlEditor.innerHTML = descHtml;
          }
          const descTA = document.getElementById('desc-textarea');
          if (descTA) descTA.value = descHtml;
        }
      }

      // SSS — mevcut satırları temizle, yenilerini ekle
      if (data.faqs && data.faqs.length) {
        const faqList = document.getElementById('faq-list');
        const tpl     = document.getElementById('faq-template');
        if (faqList && tpl) {
          // Onay al — dolu ise üzerine yaz?
          const existing = faqList.querySelectorAll('.faq-row');
          if (existing.length > 0) {
            if (!confirm('Mevcut SSS soruları silinip AI tarafından üretilenlerle değiştirilsin mi?')) {
              // sadece açıklama alanları dolduruldu
              status.innerHTML = '<span style="color:#8ab04b">✓ Açıklama alanları dolduruldu. SSS değiştirilmedi.</span>';
              return;
            }
            existing.forEach(r => r.remove());
          }

          data.faqs.forEach(function (f) {
            const clone = tpl.content.cloneNode(true);
            clone.querySelector('[name="faq_q[]"]').value = f.q;
            clone.querySelector('[name="faq_a[]"]').value = f.a;
            faqList.appendChild(clone);
          });

          // Silme butonları için event listener ekle
          faqList.querySelectorAll('.faq-del').forEach(function (delBtn) {
            delBtn.addEventListener('click', function () {
              delBtn.closest('.faq-row').remove();
            });
          });
        }
      }

      status.innerHTML = '<span style="color:#8ab04b">✓ İçerik üretildi! İstersen düzenleyebilirsin, sonra Kaydet\'e bas.</span>';

    } catch (err) {
      status.innerHTML = '<span style="color:#e05555">⚠ Bağlantı hatası: ' + err.message + '</span>';
    } finally {
      btn.disabled    = false;
      btn.textContent = '✨ AI ile Doldur';
    }
  });
})();
</script>

<?php require_once __DIR__ . '/../core/footer.php'; ?>
