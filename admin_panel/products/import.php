<?php
$page='products_import'; $title='WordPress İçe Aktar';
require_once __DIR__ . '/../core/auth.php';
require_once __DIR__ . '/../../includes/wp_importer.php';

// Toplu temizleme — short_desc + description'taki WP/Gutenberg artıklarını siler
if ($_SERVER['REQUEST_METHOD']==='POST' && ($_POST['action'] ?? '')==='cleanup_html' && csrf_check($_POST['csrf'] ?? null)) {
    @set_time_limit(0);
    $touched = 0;
    $rows = db()->query('SELECT id, short_desc, description FROM products')->fetchAll();
    $upd = db()->prepare('UPDATE products SET short_desc=?, description=? WHERE id=?');
    foreach ($rows as $r) {
        $newShort = wp_strip_html((string)$r['short_desc']);
        $newDesc  = wp_clean_html((string)$r['description']);
        if ($newShort !== (string)$r['short_desc'] || $newDesc !== (string)$r['description']) {
            $upd->execute([$newShort, $newDesc, (int)$r['id']]);
            $touched++;
        }
    }
    flash_set('ok', $touched . ' ürünün açıklaması temizlendi.');
    redirect('import.php');
}

// AJAX: görsel indirme batch
if (($_GET['ajax'] ?? '') === 'download_batch') {
    header('Content-Type: application/json; charset=utf-8');
    if (csrf_check($_POST['csrf'] ?? null)) {
        try {
            $r = wp_importer_download_batch(20);
            echo json_encode(['ok'=>true] + $r);
        } catch (\Throwable $e) {
            echo json_encode(['ok'=>false,'error'=>$e->getMessage()]);
        }
    } else {
        echo json_encode(['ok'=>false,'error'=>'CSRF']);
    }
    exit;
}

// Başarısız görselleri yeniden kuyruğa al
if ($_SERVER['REQUEST_METHOD']==='POST' && ($_POST['action'] ?? '')==='retry_failed' && csrf_check($_POST['csrf'] ?? null)) {
    $kind = in_array($_POST['retry_kind'] ?? '', ['main','gallery'], true) ? $_POST['retry_kind'] : null;
    wp_importer_retry_failed($kind);
    $label = $kind ? ($kind==='gallery' ? 'Galeri' : 'Ana') . ' görseller' : 'Tüm başarısız görseller';
    flash_set('ok', $label . ' yeniden kuyruğa alındı. "Görsel İndirmeyi Başlat" butonuna tekrar basın.');
    redirect('import.php');
}

$importDir = __DIR__ . '/../../uploads/import';
@is_dir($importDir) or @mkdir($importDir, 0755, true);
$xmlFiles = [];
foreach (glob($importDir . '/*.xml') ?: [] as $f) {
    $xmlFiles[] = ['path'=>$f, 'name'=>basename($f), 'size'=>filesize($f), 'mtime'=>filemtime($f)];
}

$action = $_POST['action'] ?? '';
$preview = null;
$applied = null;
$selectedFile = $_POST['xml_file'] ?? ($xmlFiles[0]['name'] ?? '');

if ($_SERVER['REQUEST_METHOD']==='POST' && csrf_check($_POST['csrf'] ?? null)) {
    $xmlPath = $importDir . '/' . basename($selectedFile);
    if (!file_exists($xmlPath)) {
        flash_set('err', 'XML dosyası bulunamadı: ' . htmlspecialchars($selectedFile));
    } else {
        if ($action === 'preview') {
            try {
                $parsed = wp_importer_parse($xmlPath);
                $preview = [
                    'product_count'          => count($parsed['products']),
                    'attachment_count'        => count($parsed['attachments']),
                    'category_count'          => count($parsed['categories']),
                    'tag_count'               => count($parsed['tags']),
                    'products_with_gallery'   => $parsed['products_with_gallery'],
                    'total_gallery_images'    => $parsed['total_gallery_images'],
                    'products_without_thumb'  => $parsed['products_without_thumb'],
                    'sample'                  => array_slice($parsed['products'], 0, 5),
                    'category_names'          => $parsed['categories'],
                ];
                $_SESSION['wp_import_xml'] = $xmlPath;
            } catch (\Throwable $e) {
                flash_set('err', 'XML okuma hatası: ' . $e->getMessage());
            }
        } elseif ($action === 'apply') {
            $confirmed = !empty($_POST['confirm_truncate']);
            if (!$confirmed) {
                flash_set('err', 'Devam için "Mevcut ürünleri sil" onayı gerekli.');
            } else {
                @set_time_limit(0);
                @ini_set('memory_limit', '512M');
                try {
                    wp_importer_truncate();
                    $parsed = wp_importer_parse($xmlPath);
                    $stats = wp_importer_apply($parsed);
                    $applied = $stats;
                    flash_set('ok',
                        $stats['products'].' ürün eklendi · '.
                        $stats['categories'].' kategori oluşturuldu · '.
                        $stats['queued_images'].' görsel kuyruğa eklendi'
                    );
                } catch (\Throwable $e) {
                    flash_set('err', 'İçe aktarma hatası: ' . $e->getMessage());
                }
            }
        }
    }
}

// Kuyruktaki görsel istatistikleri (main/gallery kırılımı dahil)
$queueRow = wp_importer_queue_stats();

require_once __DIR__ . '/../core/header.php';
?>
<div class="panel">
  <h3>WordPress XML (WXR) Ürün İçe Aktarımı</h3>
  <p class="muted" style="margin-bottom:18px">
    Ürün XML dosyalarını <code>/public_html/uploads/import/</code> klasörüne yükle (cPanel File Manager).
    Sayfa önce dosyayı çözümler, önizler; sonra "Uygula" diyerek DB'ye yazarsın. Görseller arka planda batch'ler halinde indirilir.
  </p>

  <?php if (!$xmlFiles): ?>
    <div class="alert alert-err">📂 <code>uploads/import/</code> klasöründe XML dosyası yok. cPanel File Manager ile XML'i buraya yükleyin.</div>
  <?php else: ?>
    <form method="post" style="display:grid;gap:14px;max-width:680px">
      <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
      <div class="field">
        <label>XML Dosyası</label>
        <select name="xml_file">
          <?php foreach ($xmlFiles as $f): ?>
            <option value="<?= e($f['name']) ?>" <?= $selectedFile===$f['name']?'selected':'' ?>>
              <?= e($f['name']) ?> (<?= number_format($f['size']/1024/1024, 2) ?> MB · <?= date('d.m.Y H:i', $f['mtime']) ?>)
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="btn-row">
        <button type="submit" name="action" value="preview" class="btn btn-secondary">1️⃣ Önizleme (Çözümle)</button>
      </div>
    </form>
  <?php endif; ?>
</div>

<?php if ($preview): ?>
<div class="panel">
  <h3>Önizleme</h3>
  <div class="kpis" style="margin-bottom:18px">
    <div class="kpi"><div class="lbl">Ürün</div><div class="val"><?= $preview['product_count'] ?></div></div>
    <div class="kpi"><div class="lbl">Kategori</div><div class="val"><?= $preview['category_count'] ?></div></div>
    <div class="kpi"><div class="lbl">Ek (Attachment)</div><div class="val"><?= $preview['attachment_count'] ?></div></div>
    <div class="kpi"><div class="lbl">Etiket</div><div class="val"><?= $preview['tag_count'] ?></div></div>
  </div>
  <div class="kpis" style="margin-bottom:18px">
    <div class="kpi" style="background:rgba(107,122,47,.08);border-color:rgba(107,122,47,.3)">
      <div class="lbl">Galerili Ürün</div>
      <div class="val" style="color:var(--leaf)"><?= $preview['products_with_gallery'] ?></div>
    </div>
    <div class="kpi" style="background:rgba(107,122,47,.08);border-color:rgba(107,122,47,.3)">
      <div class="lbl">Toplam Galeri Görseli</div>
      <div class="val" style="color:var(--leaf)"><?= $preview['total_gallery_images'] ?></div>
    </div>
    <div class="kpi" style="background:<?= $preview['products_without_thumb']>0?'rgba(154,42,42,.08)':'rgba(107,122,47,.08)' ?>;border-color:<?= $preview['products_without_thumb']>0?'rgba(154,42,42,.3)':'rgba(107,122,47,.3)' ?>">
      <div class="lbl">Ana Görselsiz</div>
      <div class="val" style="color:<?= $preview['products_without_thumb']>0?'#9A2A2A':'var(--leaf)' ?>"><?= $preview['products_without_thumb'] ?></div>
    </div>
  </div>
  <?php if ($preview['products_with_gallery'] > 0): ?>
  <div style="padding:12px 14px;border-radius:6px;background:rgba(107,122,47,.06);border:1px solid rgba(107,122,47,.3);margin-bottom:18px;font-size:13px;color:var(--leaf)">
    ✓ <strong><?= $preview['products_with_gallery'] ?> ürünün</strong> toplam <strong><?= $preview['total_gallery_images'] ?> ek görseli</strong> var. Uygula adımından sonra "Görsel İndirmeyi Başlat" butonuyla hem ana hem galeri görselleri indirilecek.
  </div>
  <?php endif; ?>

  <h4 style="margin:14px 0 8px;font-family:'Inter',sans-serif;font-size:13px;letter-spacing:.18em;text-transform:uppercase;color:var(--ink-soft)">Örnek 5 ürün</h4>
  <table style="margin-bottom:18px">
    <thead><tr><th>Ad</th><th>Slug</th><th>Kategori</th><th>SKU</th><th>Fiyat</th></tr></thead>
    <tbody>
      <?php foreach ($preview['sample'] as $p): ?>
        <tr>
          <td><?= e($p['name']) ?><?= $p['is_featured']?' ⭐':'' ?></td>
          <td><span style="font-family:monospace;font-size:12px"><?= e($p['slug']) ?></span></td>
          <td><?= e($p['category_name'] ?: '-') ?></td>
          <td><?= e($p['sku'] ?: '-') ?></td>
          <td><?= money($p['price']) ?><?= $p['old_price']?' <span class="muted" style="text-decoration:line-through;font-size:12px">'.money($p['old_price']).'</span>':'' ?></td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>

  <h4 style="margin:14px 0 8px;font-family:'Inter',sans-serif;font-size:13px;letter-spacing:.18em;text-transform:uppercase;color:var(--ink-soft)">Oluşturulacak kategoriler</h4>
  <div style="display:flex;flex-wrap:wrap;gap:6px;margin-bottom:18px">
    <?php foreach ($preview['category_names'] as $slug => $name): ?>
      <span class="chip"><?= e($name) ?></span>
    <?php endforeach; ?>
  </div>

  <form method="post" onsubmit="return confirm('Bu işlem mevcut tüm ürünleri SİLİP yerine WP\'den gelenleri yazacak. Devam edilsin mi?')" style="display:grid;gap:12px;max-width:680px">
    <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
    <input type="hidden" name="xml_file" value="<?= e($selectedFile) ?>">
    <input type="hidden" name="action" value="apply">
    <label style="display:flex;gap:10px;align-items:flex-start;font-size:14px;line-height:1.5">
      <input type="checkbox" name="confirm_truncate" value="1" required>
      <span>Mevcut tüm ürünleri ve görsellerini sileceğimi anladım. Bu işlem geri alınamaz. <br><strong>Hala devam etmek istiyorum.</strong></span>
    </label>
    <button class="btn btn-primary" style="justify-self:start">2️⃣ Uygula → Ürünleri Yaz</button>
  </form>
</div>
<?php endif; ?>

<?php if ($queueRow['pending'] > 0 || $queueRow['done'] > 0 || $queueRow['failed'] > 0): ?>
<div class="panel">
  <h3>Görsel İndirme Kuyruğu</h3>

  <!-- Genel özet -->
  <div class="kpis" style="margin-bottom:10px">
    <div class="kpi"><div class="lbl">Bekliyor</div><div class="val" id="q-pending"><?= $queueRow['pending'] ?></div></div>
    <div class="kpi"><div class="lbl">Tamam</div><div class="val" id="q-done"><?= $queueRow['done'] ?></div></div>
    <div class="kpi" style="<?= $queueRow['failed']>0?'background:rgba(154,42,42,.08);border-color:rgba(154,42,42,.3)':'' ?>">
      <div class="lbl">Başarısız</div>
      <div class="val" id="q-failed" style="<?= $queueRow['failed']>0?'color:#9A2A2A':'' ?>"><?= $queueRow['failed'] ?></div>
    </div>
  </div>

  <!-- Main / Gallery kırılımı -->
  <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-bottom:18px">
    <div style="padding:12px 16px;border:1px solid var(--gold-border);border-radius:var(--radius);background:var(--cream)">
      <div style="font-size:10px;letter-spacing:.2em;text-transform:uppercase;color:var(--muted-text);margin-bottom:6px">Ana Görsel (Kapak)</div>
      <div style="display:flex;gap:14px;font-size:13px">
        <span>✓ <strong><?= $queueRow['main_done'] ?></strong> tamam</span>
        <span style="color:var(--muted-text)">⏳ <?= $queueRow['main_pending'] ?> bekliyor</span>
        <?php if ($queueRow['main_failed'] > 0): ?>
          <span style="color:#9A2A2A">✗ <?= $queueRow['main_failed'] ?> hata</span>
        <?php endif; ?>
      </div>
    </div>
    <div style="padding:12px 16px;border:1px solid var(--gold-border);border-radius:var(--radius);background:var(--cream)">
      <div style="font-size:10px;letter-spacing:.2em;text-transform:uppercase;color:var(--muted-text);margin-bottom:6px">Galeri Görselleri</div>
      <div style="display:flex;gap:14px;font-size:13px">
        <span>✓ <strong><?= $queueRow['gallery_done'] ?></strong> tamam</span>
        <span style="color:var(--muted-text)">⏳ <?= $queueRow['gallery_pending'] ?> bekliyor</span>
        <?php if ($queueRow['gallery_failed'] > 0): ?>
          <span style="color:#9A2A2A">✗ <?= $queueRow['gallery_failed'] ?> hata</span>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <?php if ($queueRow['pending'] > 0): ?>
    <p class="muted" style="margin-bottom:14px">Görseller WordPress sunucusundan tek tek indirilir, WebP'ye çevrilir, ürünlere bağlanır. Sayfayı kapatabilirsin — ama açık tutarsan ilerleme canlı görünür.</p>
    <button id="start-dl" class="btn btn-primary" data-csrf="<?= e(csrf_token()) ?>">3️⃣ Görsel İndirmeyi Başlat</button>
    <div id="dl-status" style="margin-top:14px;font-size:14px;color:var(--muted-text)"></div>
    <script defer src="<?= SITE_URL ?>/assets/js/admin/import-downloader.js?v=<?= @filemtime(__DIR__ . '/../../assets/js/admin/import-downloader.js') ?: time() ?>"></script>
  <?php else: ?>
    <p style="color:var(--leaf);margin-bottom:10px">✅ Görsel kuyruğunda bekleyen yok.</p>
  <?php endif; ?>

  <?php if ($queueRow['failed'] > 0): ?>
  <div style="margin-top:14px;padding:14px;background:rgba(154,42,42,.06);border:1px solid rgba(154,42,42,.3);border-radius:var(--radius)">
    <p style="font-size:13px;color:#7A1F1F;margin:0 0 10px"><strong><?= $queueRow['failed'] ?> görsel</strong> indirilemedi (zaman aşımı, 403, kaynak sunucu kapalı olabilir). Yeniden deneyebilirsiniz:</p>
    <div style="display:flex;gap:10px;flex-wrap:wrap">
      <form method="post">
        <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
        <input type="hidden" name="action" value="retry_failed">
        <input type="hidden" name="retry_kind" value="">
        <button class="btn btn-secondary btn-sm" type="submit">↺ Tümünü Yeniden Dene</button>
      </form>
      <?php if ($queueRow['main_failed'] > 0): ?>
      <form method="post">
        <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
        <input type="hidden" name="action" value="retry_failed">
        <input type="hidden" name="retry_kind" value="main">
        <button class="btn btn-secondary btn-sm" type="submit">↺ Sadece Ana Görseller (<?= $queueRow['main_failed'] ?>)</button>
      </form>
      <?php endif; ?>
      <?php if ($queueRow['gallery_failed'] > 0): ?>
      <form method="post">
        <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
        <input type="hidden" name="action" value="retry_failed">
        <input type="hidden" name="retry_kind" value="gallery">
        <button class="btn btn-secondary btn-sm" type="submit">↺ Sadece Galeri Görseller (<?= $queueRow['gallery_failed'] ?>)</button>
      </form>
      <?php endif; ?>
    </div>
  </div>
  <?php endif; ?>

</div>
<?php endif; ?>

<div class="panel">
  <h3>İçerik Temizliği</h3>
  <p class="muted" style="margin-bottom:14px">
    WordPress/Gutenberg editöründen gelen <code>data-start</code>, <code>data-end</code> gibi nitelikleri,
    boş <code>&lt;p&gt;</code> bloklarını ve fazla <code>&amp;nbsp;</code>'leri tüm ürünlerden temizler.
    <strong>Kısa açıklama</strong> alanından tüm HTML çıkar (düz metne döner);
    <strong>detaylı açıklama</strong>'da yapı korunur, sadece çöp nitelikler silinir.
  </p>
  <form method="post" onsubmit="return confirm('Tüm ürünlerin açıklamaları üzerinden geçilecek. Bu işlem geri alınamaz. Devam edilsin mi?')">
    <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
    <input type="hidden" name="action" value="cleanup_html">
    <button class="btn btn-secondary">🧹 Tüm Ürün Açıklamalarını Temizle</button>
  </form>
</div>

<?php require_once __DIR__ . '/../core/footer.php'; ?>
