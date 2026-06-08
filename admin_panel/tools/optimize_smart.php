<?php
/**
 * Akıllı Resim Optimizasyonu — tip bazında özel boyut hedefleri.
 *
 * /admin_panel/tools/optimize_smart.php
 *
 * Tipler:
 *   - Kategoriler        : max 480x480, kalite 80   (yuvarlak ikon, ~88-120px gösteriliyor)
 *   - Blog yazar avatarları: max 240x240, kalite 80 (biyografi kartında ~80-120px)
 *   - Blog kapakları     : max 1200x800, kalite 82  (yazı + OG paylaşım kartı)
 *   - CMS sayfa kapakları: max 1200x800, kalite 82  (sayfa hero görseli)
 *   - SEO OG görselleri  : max 1200x630, kalite 82  (Facebook/Twitter standardı)
 *
 * Mevcut "Resim Optimizasyonu" (resize_images.php) uploads/ klasörünü 1280px'e
 * indirgerken bu tool DB referanslarını kullanır → hedefli optimizasyon.
 */
$page  = 'tools_optimize_smart';
$title = 'Akıllı Resim Optimizasyonu';
require_once __DIR__ . '/../core/auth.php';
require_once __DIR__ . '/../../models/Media.php';

// Tip tanımları (security whitelist — sadece bunlar geçerli)
// variant: true → -mobile.webp variant üretir (orijinali bozmaz)
// variant: false/yok → görseli yerinde küçültür (.origXXX.webp yedek alır)
$TYPES = array(
    'banners_mobile'=> array('label'=>'⚡ Banner Mobil Variantları (LCP)', 'table'=>'banners',        'col'=>'image',        'maxW'=>800,  'maxH'=>800,  'q'=>78, 'bext'=>'',                'variant'=>true),
    'categories'    => array('label'=>'Kategori Görselleri',              'table'=>'categories',     'col'=>'image',        'maxW'=>480,  'maxH'=>480,  'q'=>80, 'bext'=>'.orig480.webp'),
    'avatars'       => array('label'=>'Blog Yazar Avatarları',            'table'=>'blog_authors',   'col'=>'avatar',       'maxW'=>240,  'maxH'=>240,  'q'=>80, 'bext'=>'.orig240.webp'),
    'products_main' => array('label'=>'Ürün Ana Görselleri',              'table'=>'products',       'col'=>'image',        'maxW'=>1200, 'maxH'=>1200, 'q'=>80, 'bext'=>'.orig1200.webp'),
    'products_gal'  => array('label'=>'Ürün Galeri Görselleri',           'table'=>'product_images', 'col'=>'path',         'maxW'=>1200, 'maxH'=>1200, 'q'=>80, 'bext'=>'.orig1200.webp'),
    'blog_covers'   => array('label'=>'Blog Kapak Görselleri',            'table'=>'blog_posts',     'col'=>'cover_image',  'maxW'=>1200, 'maxH'=>800,  'q'=>82, 'bext'=>'.orig1200.webp'),
    'page_covers'   => array('label'=>'CMS Sayfa Kapakları',              'table'=>'pages',          'col'=>'cover_image',  'maxW'=>1200, 'maxH'=>800,  'q'=>82, 'bext'=>'.orig1200.webp'),
    'seo_og'        => array('label'=>'SEO OG Görselleri',                'table'=>'seo_settings',   'col'=>'og_image',     'maxW'=>1200, 'maxH'=>630,  'q'=>82, 'bext'=>'.origog.webp'),
);

// =========================================================================
// AJAX: optimize bir tip
// =========================================================================
if (($_GET['ajax'] ?? '') === 'optimize') {
    header('Content-Type: application/json; charset=utf-8');
    if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !csrf_check($_POST['csrf'] ?? null)) {
        echo json_encode(array('ok'=>false,'error'=>'CSRF doğrulanamadı'));
        exit;
    }
    $type = $_POST['type'] ?? '';
    if (!isset($TYPES[$type])) {
        echo json_encode(array('ok'=>false,'error'=>'Geçersiz tip'));
        exit;
    }
    @set_time_limit(120);
    @ini_set('memory_limit', '256M');

    $t = $TYPES[$type];

    // Özel iş: banner mobile variant üretimi (resize değil, yeni dosya yaratma)
    if (!empty($t['variant'])) {
        $result = media_create_mobile_variants($t['table'], $t['col'], $t['maxW'], $t['maxH'], $t['q'], '-mobile');
        if (isset($result['error'])) {
            echo json_encode(array('ok'=>false,'error'=>$result['error']));
            exit;
        }
        echo json_encode(array(
            'ok'          => true,
            'variantMode' => true,
            'created'     => $result['created'],
            'existed'     => $result['existed'],
            'skipped'     => $result['skipped'],
            'notFound'    => $result['notFound'],
            'totalSize'   => $result['totalSize'],
            'items'       => array_slice($result['items'], 0, 50),
        ));
        exit;
    }

    // Klasik iş: tablo görsellerini yerinde küçült
    $result = media_optimize_table($t['table'], $t['col'], $t['maxW'], $t['maxH'], $t['q'], false, $t['bext']);

    if (isset($result['error'])) {
        echo json_encode(array('ok'=>false,'error'=>$result['error']));
        exit;
    }
    echo json_encode(array(
        'ok'        => true,
        'resized'   => $result['resized'],
        'skipped'   => $result['skipped'],
        'notFound'  => $result['notFound'],
        'before'    => $result['before'],
        'after'     => $result['after'],
        'saved'     => $result['before'] - $result['after'],
        'items'     => array_slice($result['items'], 0, 50),
    ));
    exit;
}

// =========================================================================
// Normal görünüm: dry-run scan her tip için
// =========================================================================
$gdOk = function_exists('imagecreatefromwebp') && function_exists('imagewebp');

$scans = array();
foreach ($TYPES as $key => $t) {
    if (!empty($t['variant'])) {
        // Variant scan: kaç banner için -mobile.webp eksik?
        $root = APP_ROOT;
        $scanned = $existed = $missing = 0;
        $estSize = 0;
        try {
            $rows = db()->query("SELECT {$t['col']} AS img FROM {$t['table']} WHERE {$t['col']} IS NOT NULL AND {$t['col']} != ''")->fetchAll();
            foreach ($rows as $row) {
                $scanned++;
                $rel = ltrim((string)$row['img'], '/');
                $abs = $root . '/' . $rel;
                if (!is_file($abs)) continue;
                $variantAbs = preg_replace('/(\.[a-z0-9]+)$/i', '-mobile$1', $abs);
                if (file_exists($variantAbs)) {
                    $existed++;
                } else {
                    $missing++;
                    $estSize += (int)(filesize($abs) * 0.30); // tahmini variant boyutu
                }
            }
            $scans[$key] = array('scanned'=>$scanned,'oversized'=>$missing,'existed'=>$existed,'before'=>$estSize,'variant'=>true);
        } catch (Exception $e) {
            $scans[$key] = array('error'=>$e->getMessage());
        }
    } else {
        $r = media_optimize_table($t['table'], $t['col'], $t['maxW'], $t['maxH'], $t['q'], true, $t['bext']);
        $scans[$key] = isset($r['error']) ? array('error'=>$r['error']) : $r;
    }
}

require_once __DIR__ . '/../core/header.php';
?>
<div class="panel">
  <h3>Akıllı Resim Optimizasyonu</h3>
  <p class="muted" style="font-size:13px;margin-bottom:18px">
    Her görsel tipini kullanıldığı yere uygun hedef boyuta küçültür. Sosyal medya OG'leri 1200×630, kategoriler 480×480, avatarlar 240×240.
    Orijinal dosyalar <code>.origXXX.webp</code> olarak yedeklenir.
  </p>

  <?php if (!$gdOk): ?>
    <div class="alert alert-err">⚠️ GD WebP desteği yok. cPanel → PHP Selector'dan <code>gd</code> eklentisini etkinleştirin.</div>
  <?php endif; ?>

  <table style="width:100%;font-size:14px;border-collapse:collapse" class="table">
    <thead>
      <tr style="background:var(--cream);text-align:left">
        <th style="padding:10px 12px">Tip</th>
        <th style="padding:10px 12px">Hedef Boyut</th>
        <th style="padding:10px 12px;text-align:right">Toplam</th>
        <th style="padding:10px 12px;text-align:right">Optimize Edilecek</th>
        <th style="padding:10px 12px;text-align:right">Mevcut Boyut</th>
        <th style="padding:10px 12px">İşlem</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($TYPES as $key => $t):
        $s = $scans[$key];
        $hasError = isset($s['error']);
      ?>
        <tr id="row-<?= e($key) ?>" style="border-bottom:1px solid var(--gold-border)">
          <td style="padding:12px"><strong><?= e($t['label']) ?></strong></td>
          <td style="padding:12px;font-family:monospace;font-size:12px;color:var(--muted-text)"><?= $t['maxW'] ?>×<?= $t['maxH'] ?> · Q<?= $t['q'] ?></td>
          <?php if ($hasError): ?>
            <td colspan="3" style="padding:12px;color:#7A1F1F;font-size:12px">⚠️ <?= e($s['error']) ?></td>
            <td></td>
          <?php else: ?>
            <td style="padding:12px;text-align:right"><?= (int)$s['scanned'] ?></td>
            <td style="padding:12px;text-align:right;color:<?= $s['oversized']>0?'#E0A800':'var(--leaf)' ?>;font-weight:600">
              <?= (int)$s['oversized'] ?>
            </td>
            <td style="padding:12px;text-align:right;font-family:monospace;font-size:12px">
              <?= $s['before'] > 0 ? round($s['before']/1024).' KB' : '—' ?>
            </td>
            <td style="padding:12px">
              <?php if ($s['oversized'] > 0 && $gdOk): ?>
                <button type="button" class="btn btn-primary btn-sm opt-btn"
                        data-type="<?= e($key) ?>"
                        data-csrf="<?= e(csrf_token()) ?>"
                        data-label="<?= e($t['label']) ?>">
                  Optimize Et
                </button>
              <?php elseif ($s['oversized'] === 0): ?>
                <span style="color:var(--leaf);font-size:12px">✅ Tamam</span>
              <?php endif; ?>
            </td>
          <?php endif; ?>
        </tr>
        <tr id="result-<?= e($key) ?>" style="display:none;background:rgba(107,122,47,.04)">
          <td colspan="6" style="padding:12px 16px;font-family:monospace;font-size:12px"></td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>

  <div style="margin-top:24px;padding:14px;background:var(--cream);border-radius:6px;font-size:12px;color:var(--muted-text);line-height:1.7">
    <strong style="color:var(--ink)">Not:</strong> Bu işlem sadece DB'de referans verilen görseli optimize eder. <code>uploads/</code> klasöründeki diğer dosyaları etkilemez (onlar için <a href="resize_images.php" style="color:var(--gold)">Genel Resim Optimizasyonu</a> kullanın).
    <br><br>
    Bundan sonra admin panelinden yüklenecek <strong>tüm yeni görseller otomatik olarak doğru boyuta indirilir</strong> — bu tool sadece tek seferlik geçmiş görseller içindir.
  </div>
</div>

<script>
(function(){
  document.querySelectorAll('.opt-btn').forEach(function(btn){
    btn.addEventListener('click', function(){
      var type = btn.dataset.type;
      var csrf = btn.dataset.csrf;
      var label = btn.dataset.label;
      if (!confirm(label + ' optimize edilecek. Devam edilsin mi?')) return;

      btn.disabled = true;
      btn.textContent = 'İşleniyor...';
      var resultRow = document.getElementById('result-' + type);
      var resultCell = resultRow.querySelector('td');
      resultRow.style.display = 'table-row';
      resultCell.textContent = 'Optimize ediliyor...';

      var fd = new FormData();
      fd.append('csrf', csrf);
      fd.append('type', type);

      fetch('?ajax=optimize', { method:'POST', body:fd, credentials:'same-origin' })
        .then(function(r){ return r.json(); })
        .then(function(d){
          if (!d.ok) {
            resultCell.style.color = '#7A1F1F';
            resultCell.textContent = '❌ Hata: ' + (d.error || 'bilinmeyen');
            btn.disabled = false;
            btn.textContent = 'Optimize Et';
            return;
          }
          var html;
          if (d.variantMode) {
            var totalKB = Math.round(d.totalSize / 1024);
            html = '✅ <strong>' + d.created + ' mobil variant</strong> oluşturuldu '
                 + '(toplam ' + totalKB + ' KB).';
            if (d.existed) html += ' · Zaten var: ' + d.existed;
            if (d.notFound) html += ' · Bulunamayan: ' + d.notFound;
          } else {
            var savedKB = Math.round(d.saved / 1024);
            var beforeKB = Math.round(d.before / 1024);
            var afterKB = Math.round(d.after / 1024);
            html = '✅ <strong>' + d.resized + ' görsel</strong> optimize edildi.'
                 + ' Tasarruf: <strong>' + savedKB + ' KB</strong> ('
                 + beforeKB + ' KB → ' + afterKB + ' KB)';
            if (d.skipped) html += ' · Atlanmış: ' + d.skipped;
            if (d.notFound) html += ' · Bulunamayan: ' + d.notFound;
          }
          if (d.items && d.items.length) {
            html += '<details style="margin-top:8px"><summary style="cursor:pointer;font-size:11px">Detay (' + d.items.length + ' dosya)</summary>'
                  + '<div style="margin-top:6px;font-size:11px;line-height:1.5">';
            d.items.forEach(function(it){
              html += '• ' + it.url + ' (' + (it.from || '') + ' → ' + (it.to || '') + ')<br>';
            });
            html += '</div></details>';
          }
          resultCell.innerHTML = html;
          btn.style.display = 'none';
          // Sayfa yenilenince scan tekrar yapılır → row'daki oversized count 0 olur.
          setTimeout(function(){ location.reload(); }, 4000);
        })
        .catch(function(err){
          resultCell.style.color = '#7A1F1F';
          resultCell.textContent = '❌ Ağ hatası: ' + err.message;
          btn.disabled = false;
          btn.textContent = 'Optimize Et';
        });
    });
  });
})();
</script>

<?php require_once __DIR__ . '/../core/footer.php'; ?>
