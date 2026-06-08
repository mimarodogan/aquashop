<?php
$page='blog_import'; $title='Blog Yazılarını İçe Aktar';
require_once __DIR__ . '/../core/auth.php';
require_once __DIR__ . '/../../includes/wp_importer.php';

$importDir = __DIR__ . '/../../uploads/import';
@is_dir($importDir) or @mkdir($importDir, 0755, true);

// ── AJAX: kapak görseli indirme ──────────────────────────────────────────────
if (($_GET['ajax'] ?? '') === 'download_batch') {
    header('Content-Type: application/json; charset=utf-8');
    if (csrf_check($_POST['csrf'] ?? null)) {
        try {
            $r = wp_blog_download_batch(20);
            echo json_encode(['ok'=>true] + $r);
        } catch (\Throwable $e) {
            echo json_encode(['ok'=>false,'error'=>$e->getMessage()]);
        }
    } else {
        echo json_encode(['ok'=>false,'error'=>'CSRF']);
    }
    exit;
}

// ── POST işlemleri ───────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD']==='POST' && csrf_check($_POST['csrf'] ?? null)) {

    $action = $_POST['action'] ?? '';

    // Başarısız görselleri yeniden dene
    if ($action === 'retry_failed') {
        wp_blog_retry_failed();
        flash_set('ok','Başarısız görseller yeniden kuyruğa alındı. Görsel indirmeyi başlatın.');
        redirect('import.php');
    }

    // İçe aktarmayı temizle
    if ($action === 'truncate') {
        wp_blog_truncate();
        flash_set('ok','Tüm blog yazıları ve görsel kuyruğu silindi.');
        redirect('import.php');
    }

    $selectedFile = basename($_POST['xml_file'] ?? '');
    $xmlPath = $importDir . '/' . $selectedFile;

    if (!file_exists($xmlPath)) {
        flash_set('err','XML dosyası bulunamadı: ' . htmlspecialchars($selectedFile));
        redirect('import.php');
    }

    // Önizleme
    if ($action === 'preview') {
        try {
            $_SESSION['wp_blog_xml']     = $xmlPath;
            $_SESSION['wp_blog_parsed']  = null; // büyük olabilir, sadece istatistik tut
            $parsed = wp_blog_parse($xmlPath);
            $_SESSION['wp_blog_preview'] = [
                'file'                => $selectedFile,
                'post_count'          => count($parsed['posts']),
                'attachment_count'    => count($parsed['attachments']),
                'category_count'      => count($parsed['categories']),
                'posts_with_thumb'    => $parsed['posts_with_thumb'],
                'posts_without_thumb' => $parsed['posts_without_thumb'],
                'categories'          => $parsed['categories'],
                'sample'              => array_map(fn($p) => [
                    'title'         => $p['title'],
                    'slug'          => $p['slug'],
                    'category_name' => $p['category_name'],
                    'published_at'  => $p['published_at'],
                    'has_thumb'     => (bool)$p['thumbnail_id'],
                    'excerpt_len'   => mb_strlen($p['excerpt']),
                ], array_slice($parsed['posts'], 0, 6)),
            ];
        } catch (\Throwable $e) {
            flash_set('err','XML okuma hatası: ' . $e->getMessage());
        }
        redirect('import.php');
    }

    // Uygula
    if ($action === 'apply') {
        $skipExisting = !empty($_POST['skip_existing']);
        $clearFirst   = !empty($_POST['clear_first']);
        $xmlPath2     = $_SESSION['wp_blog_xml'] ?? $xmlPath;

        if (!file_exists($xmlPath2)) {
            flash_set('err','XML oturumda bulunamadı, tekrar önizleyin.');
            redirect('import.php');
        }

        @set_time_limit(0);
        @ini_set('memory_limit','512M');
        try {
            if ($clearFirst) wp_blog_truncate();

            // Mevcut admini yazar olarak ata
            $adminId = (int)($_SESSION['admin_id'] ?? 0);

            $parsed = wp_blog_parse($xmlPath2);
            $stats  = wp_blog_apply($parsed, $skipExisting, $adminId);
            unset($_SESSION['wp_blog_preview']);

            $msg = $stats['posts'].' yazı eklendi · '
                 . $stats['categories'].' yeni kategori · '
                 . $stats['queued_images'].' kapak görseli kuyruğa alındı';
            if ($stats['skipped']) $msg .= ' · '.$stats['skipped'].' yazı atlandı (mevcut)';
            if ($stats['errors'])  $msg .= ' · '.count($stats['errors']).' hata';
            flash_set('ok', $msg);
        } catch (\Throwable $e) {
            flash_set('err','İçe aktarma hatası: ' . $e->getMessage());
        }
        redirect('import.php');
    }
}

// ── Sayfa verisi ─────────────────────────────────────────────────────────────
$xmlFiles = [];
foreach (glob($importDir . '/*.xml') ?: [] as $f) {
    $xmlFiles[] = ['path'=>$f,'name'=>basename($f),'size'=>filesize($f),'mtime'=>filemtime($f)];
}
$preview  = $_SESSION['wp_blog_preview'] ?? null;
$queueRow = wp_blog_queue_stats();

require_once __DIR__ . '/../core/header.php';
?>

<div class="panel">
  <h3>WordPress Blog Yazıları İçe Aktarımı</h3>
  <p class="muted" style="margin-bottom:18px">
    WordPress WXR (XML) dışa aktarma dosyasından blog yazılarını, kategorilerini ve kapak görsellerini sisteme aktarır.
    XML dosyasını <code>/public_html/uploads/import/</code> klasörüne yükle (cPanel File Manager), ardından aşağıdaki adımları uygula.
  </p>
  <div style="padding:12px 14px;border-radius:6px;background:rgba(79,92,38,.06);border:1px solid rgba(79,92,38,.2);font-size:13px;color:var(--leaf);margin-bottom:18px">
    💡 <strong>İpucu:</strong> WordPress admin → Araçlar → Dışa Aktar → <em>Tüm içerik</em> veya <em>Yazılar</em> seçerek WXR dosyasını indir. "Tüm içerik" seçimi ek (medya) kayıtlarını da içerdiğinden kapak görselleri otomatik indirilir.
  </div>

  <?php if (!$xmlFiles): ?>
    <div class="alert alert-err">📂 <code>uploads/import/</code> klasöründe XML dosyası yok. Önce dosyayı sunucuya yükleyin.</div>
  <?php else: ?>
    <form method="post" style="display:grid;gap:14px;max-width:680px">
      <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
      <div class="field">
        <label>XML Dosyası</label>
        <select name="xml_file">
          <?php foreach ($xmlFiles as $f): ?>
            <option value="<?= e($f['name']) ?>" <?= ($preview['file'] ?? '')===$f['name']?'selected':'' ?>>
              <?= e($f['name']) ?> (<?= number_format($f['size']/1024/1024,2) ?> MB · <?= date('d.m.Y H:i',$f['mtime']) ?>)
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div>
        <button type="submit" name="action" value="preview" class="btn btn-secondary">1️⃣ Önizleme (Çözümle)</button>
      </div>
    </form>
  <?php endif; ?>
</div>

<?php if ($preview): ?>
<div class="panel">
  <h3>Önizleme — <?= e($preview['file']) ?></h3>

  <div class="kpis" style="margin-bottom:10px">
    <div class="kpi"><div class="lbl">Blog Yazısı</div><div class="val"><?= $preview['post_count'] ?></div></div>
    <div class="kpi"><div class="lbl">Kategori</div><div class="val"><?= $preview['category_count'] ?></div></div>
    <div class="kpi"><div class="lbl">Ek (Attachment)</div><div class="val"><?= $preview['attachment_count'] ?></div></div>
  </div>
  <div class="kpis" style="margin-bottom:18px">
    <div class="kpi" style="background:rgba(107,122,47,.08);border-color:rgba(107,122,47,.3)">
      <div class="lbl">Kapak Görselli</div>
      <div class="val" style="color:var(--leaf)"><?= $preview['posts_with_thumb'] ?></div>
    </div>
    <div class="kpi" style="background:<?= $preview['posts_without_thumb']>0?'rgba(154,42,42,.08)':'rgba(107,122,47,.08)' ?>;border-color:<?= $preview['posts_without_thumb']>0?'rgba(154,42,42,.3)':'rgba(107,122,47,.3)' ?>">
      <div class="lbl">Görselsiz Yazı</div>
      <div class="val" style="color:<?= $preview['posts_without_thumb']>0?'#9A2A2A':'var(--leaf)' ?>"><?= $preview['posts_without_thumb'] ?></div>
    </div>
  </div>

  <!-- Kategoriler -->
  <?php if ($preview['categories']): ?>
  <h4 style="font-family:'Inter',sans-serif;font-size:12px;letter-spacing:.18em;text-transform:uppercase;color:var(--muted-text);margin:0 0 8px">Kategoriler (oluşturulacak veya eşleştirilecek)</h4>
  <div style="display:flex;flex-wrap:wrap;gap:6px;margin-bottom:18px">
    <?php foreach ($preview['categories'] as $slug => $name): ?>
      <span class="chip"><?= e($name) ?> <span style="font-family:monospace;font-size:10px;color:var(--muted-text)"><?= e($slug) ?></span></span>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>

  <!-- Örnek yazılar -->
  <h4 style="font-family:'Inter',sans-serif;font-size:12px;letter-spacing:.18em;text-transform:uppercase;color:var(--muted-text);margin:0 0 8px">İlk 6 yazı (örnek)</h4>
  <table style="margin-bottom:20px">
    <thead><tr><th>Başlık</th><th>Slug</th><th>Kategori</th><th>Yayın Tarihi</th><th>Kapak</th></tr></thead>
    <tbody>
      <?php foreach ($preview['sample'] as $sp): ?>
        <tr>
          <td><?= e($sp['title']) ?></td>
          <td><span style="font-family:monospace;font-size:11px"><?= e($sp['slug']) ?></span></td>
          <td><?= e($sp['category_name'] ?: '—') ?></td>
          <td style="font-size:12px;color:var(--muted-text)"><?= e(date('d.m.Y', strtotime($sp['published_at']))) ?></td>
          <td><?= $sp['has_thumb'] ? '<span style="color:var(--leaf)">✓</span>' : '<span style="color:#9A2A2A">✗</span>' ?></td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>

  <!-- Uygulama formu -->
  <form method="post" style="display:grid;gap:14px;max-width:680px">
    <input type="hidden" name="csrf"     value="<?= e(csrf_token()) ?>">
    <input type="hidden" name="xml_file" value="<?= e($preview['file']) ?>">
    <input type="hidden" name="action"   value="apply">

    <div style="display:flex;flex-direction:column;gap:10px;padding:14px;border:1px solid var(--gold-border);border-radius:var(--radius);background:var(--cream)">
      <label style="display:flex;gap:10px;align-items:flex-start;font-size:14px">
        <input type="checkbox" name="skip_existing" value="1" checked>
        <span><strong>Mevcut yazıları atla</strong> — aynı slug'lı yazı zaten varsa üzerine yazma <span class="muted">(önerilir)</span></span>
      </label>
      <label style="display:flex;gap:10px;align-items:flex-start;font-size:14px">
        <input type="checkbox" name="clear_first" value="1"
               onchange="this.checked && !confirm('Tüm mevcut blog yazıları SİLİNECEK. Devam?') && (this.checked=false)">
        <span style="color:#9A2A2A"><strong>Önce tüm blog yazılarını sil</strong> — sıfırdan başla (geri alınamaz)</span>
      </label>
    </div>

    <div>
      <button class="btn btn-primary" type="submit">2️⃣ Uygula → Blog Yazılarını Yaz</button>
    </div>
  </form>
</div>
<?php endif; ?>

<?php if ($queueRow['pending'] > 0 || $queueRow['done'] > 0 || $queueRow['failed'] > 0): ?>
<div class="panel">
  <h3>Kapak Görseli İndirme Kuyruğu</h3>
  <div class="kpis" style="margin-bottom:14px">
    <div class="kpi">
      <div class="lbl">Bekliyor</div>
      <div class="val" id="q-pending"><?= $queueRow['pending'] ?></div>
    </div>
    <div class="kpi">
      <div class="lbl">Tamam</div>
      <div class="val" id="q-done"><?= $queueRow['done'] ?></div>
    </div>
    <div class="kpi" style="<?= $queueRow['failed']>0?'background:rgba(154,42,42,.08);border-color:rgba(154,42,42,.3)':'' ?>">
      <div class="lbl">Başarısız</div>
      <div class="val" id="q-failed" style="<?= $queueRow['failed']>0?'color:#9A2A2A':'' ?>"><?= $queueRow['failed'] ?></div>
    </div>
  </div>

  <?php if ($queueRow['pending'] > 0): ?>
    <p class="muted" style="margin-bottom:14px">Kapak görselleri WordPress sunucusundan indirilir ve WebP'ye çevrilir. Sayfa açıkken ilerleme canlı görünür.</p>
    <button id="start-dl" class="btn btn-primary" data-csrf="<?= e(csrf_token()) ?>">3️⃣ Görsel İndirmeyi Başlat</button>
    <div id="dl-status" style="margin-top:14px;font-size:14px;color:var(--muted-text)"></div>
    <script>
    (function(){
      var btn = document.getElementById('start-dl');
      if (!btn) return;
      var status = document.getElementById('dl-status');
      var pending = document.getElementById('q-pending');
      var done = document.getElementById('q-done');
      var failed = document.getElementById('q-failed');
      var csrf = btn.getAttribute('data-csrf')||'';
      var running = false;

      function tick(){
        var fd = new FormData(); fd.append('csrf',csrf);
        fetch('?ajax=download_batch',{method:'POST',body:fd,credentials:'same-origin'})
          .then(function(r){return r.json();})
          .then(function(j){
            if(!j.ok){status.textContent='Hata: '+(j.error||'?');running=false;btn.disabled=false;btn.textContent='Tekrar Dene';return;}
            done.textContent=(parseInt(done.textContent,10)||0)+j.done;
            failed.textContent=(parseInt(failed.textContent,10)||0)+j.failed;
            pending.textContent=j.remaining;
            status.textContent='Bu turda: '+j.done+' indirildi'+(j.failed?', '+j.failed+' hata':'')+'. Kalan: '+j.remaining;
            if(j.remaining>0&&running){setTimeout(tick,250);}
            else if(j.remaining===0){status.textContent='✅ Tüm görseller işlendi.';btn.disabled=false;btn.textContent='Yeniden Çalıştır';running=false;}
          })
          .catch(function(e){status.textContent='Ağ hatası: '+e;running=false;btn.disabled=false;});
      }
      btn.addEventListener('click',function(){
        if(running)return;running=true;btn.disabled=true;btn.textContent='İndiriliyor…';status.textContent='Başlatılıyor…';tick();
      });
    })();
    </script>
  <?php else: ?>
    <p style="color:var(--leaf)">✅ Görsel kuyruğunda bekleyen yok.</p>
  <?php endif; ?>

  <?php if ($queueRow['failed'] > 0): ?>
  <div style="margin-top:14px;padding:14px;background:rgba(154,42,42,.06);border:1px solid rgba(154,42,42,.3);border-radius:var(--radius)">
    <p style="font-size:13px;color:#7A1F1F;margin:0 0 10px">
      <strong><?= $queueRow['failed'] ?> kapak görseli</strong> indirilemedi. Kaynak sunucu kapalı veya URL değişmiş olabilir.
    </p>
    <form method="post">
      <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
      <input type="hidden" name="action" value="retry_failed">
      <button class="btn btn-secondary btn-sm" type="submit">↺ Başarısızları Yeniden Dene</button>
    </form>
  </div>
  <?php endif; ?>
</div>
<?php endif; ?>

<div class="panel">
  <h3>Temizlik</h3>
  <p class="muted" style="margin-bottom:14px">
    İçe aktarılan tüm blog yazılarını, blog SSS'lerini ve görsel kuyruğunu temizler. Blog kategorileri korunur.
    Bu işlem <strong>geri alınamaz</strong>.
  </p>
  <form method="post" onsubmit="return confirm('Tüm blog yazıları ve görsel kuyruğu silinecek. Kategoriler korunur. Devam?')">
    <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
    <input type="hidden" name="action" value="truncate">
    <button class="btn btn-secondary" style="border-color:#9A2A2A;color:#9A2A2A" type="submit">🗑 Blog Yazılarını Temizle</button>
  </form>
</div>

<?php require_once __DIR__ . '/../core/footer.php'; ?>
