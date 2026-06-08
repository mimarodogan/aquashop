<?php
$page='media'; $title='Medya Kütüphanesi';
require_once __DIR__ . '/../core/auth.php';
require_once __DIR__ . '/../../includes/media.php';

// 30 gün geçen çöp kayıtlarını otomatik temizle
media_purge_old();

if ($_SERVER['REQUEST_METHOD']==='POST' && csrf_check(isset($_POST['csrf'])?$_POST['csrf']:null)) {
    $action = isset($_POST['action']) ? $_POST['action'] : '';
    if ($action === 'upload' && isset($_FILES['files'])) {
        $ok=0; $err=0; $msgs=array();
        $files = $_FILES['files'];
        $count = is_array($files['name']) ? count($files['name']) : 0;
        for ($i=0; $i<$count; $i++) {
            $f = array(
                'name'=>$files['name'][$i],'tmp_name'=>$files['tmp_name'][$i],
                'error'=>$files['error'][$i],'size'=>$files['size'][$i],'type'=>$files['type'][$i]
            );
            $r = media_upload_from_files($f);
            if ($r['ok']) $ok++; else { $err++; $msgs[] = $f['name'].': '.$r['error']; }
        }
        flash_set($err?'err':'ok', "$ok yüklendi, $err hata. " . implode(' ', $msgs));
        redirect('library.php');
    }
    if ($action === 'delete') {
        media_soft_delete((int)$_POST['id']);
        flash_set('ok','Görsel çöp kutusuna taşındı.');
        redirect('library.php');
    }
    if ($action === 'delete_unused') {
        $rows = db()->query('SELECT id, filename FROM media WHERE deleted_at IS NULL')->fetchAll();
        $n=0; foreach ($rows as $r) { if (media_usage_count($r['filename']) === 0) { media_soft_delete($r['id']); $n++; } }
        flash_set('ok',"$n kullanılmayan görsel çöp kutusuna taşındı.");
        redirect('library.php');
    }
    if ($action === 'scan') {
        $added = media_scan();
        flash_set('ok',"$added yeni dosya kütüphaneye eklendi.");
        redirect('library.php');
    }
}

// Filtre ve arama parametreleri
$filter  = isset($_GET['filter']) ? $_GET['filter'] : 'all'; // all, used, unused
$search  = isset($_GET['q'])      ? trim($_GET['q'])  : '';
$perPage = isset($_GET['per_page']) ? (int)$_GET['per_page'] : 20;
if ($perPage < 1)   $perPage = 20;
if ($perPage > 100) $perPage = 100;
$pgNum   = isset($_GET['pg']) ? max(1,(int)$_GET['pg']) : 1;

// Tüm kayıtları sayılar için çek (hafif sürüm — sadece id+filename)
$totalAll   = (int)db()->query('SELECT COUNT(*) FROM media WHERE deleted_at IS NULL')->fetchColumn();
$totalTrash = (int)db()->query('SELECT COUNT(*) FROM media WHERE deleted_at IS NOT NULL')->fetchColumn();

// Arama + filtre için SQL yap
$whereConditions = ['deleted_at IS NULL'];
$params = [];
if ($search !== '') {
    $whereConditions[] = 'filename LIKE ?';
    $params[] = '%' . $search . '%';
}

// "used" / "unused" filtre için önce kullanım sayısını bilmeden filtreleyemeyiz;
// unused/used büyük kütüphanelerde performans sorununa yol açabilir, yine de tüm seti çekip filtreleyelim.
$whereSQL = implode(' AND ', $whereConditions);
$sql = "SELECT * FROM media WHERE $whereSQL ORDER BY created_at DESC";
$allRows = db()->prepare($sql);
$allRows->execute($params);
$rows = $allRows->fetchAll();

// Kullanım hesapla
foreach ($rows as &$r) { $r['_use'] = media_usage($r['filename']); $r['_uses'] = count($r['_use']); }
unset($r);

// used/unused filtrele
if ($filter==='used')   $rows = array_values(array_filter($rows, function($r){ return $r['_uses']>0; }));
if ($filter==='unused') $rows = array_values(array_filter($rows, function($r){ return $r['_uses']===0; }));

// Sayfalama hesapla
$totalRows  = count($rows);
$totalPages = max(1, (int)ceil($totalRows / $perPage));
$pgNum      = min($pgNum, $totalPages);
$offset     = ($pgNum - 1) * $perPage;
$rows       = array_slice($rows, $offset, $perPage);

// URL yardımcısı — mevcut parametreleri korur
function media_url(array $extra = []): string {
    $base = array_filter([
        'filter'   => isset($_GET['filter'])   ? $_GET['filter']   : null,
        'q'        => isset($_GET['q'])        ? $_GET['q']        : null,
        'per_page' => isset($_GET['per_page']) ? $_GET['per_page'] : null,
        'pg'       => isset($_GET['pg'])       ? $_GET['pg']       : null,
    ]);
    $merged = array_merge($base, $extra);
    $merged = array_filter($merged, fn($v) => $v !== null && $v !== '');
    return 'library.php' . ($merged ? '?' . http_build_query($merged) : '');
}

require_once __DIR__ . '/../core/header.php';
?>
<link rel="stylesheet" href="../../assets/css/media-library.css">

<div class="panel">
  <h3>Yeni Görsel Yükle</h3>
  <form method="post" enctype="multipart/form-data" id="upform">
    <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
    <input type="hidden" name="action" value="upload">
    <label class="upload-zone" id="dropzone">
      <div class="ic">⬆</div>
      <div><strong>Görselleri sürükleyin</strong> veya seçmek için tıklayın</div>
      <div class="muted" style="font-size:12px;margin-top:6px">JPG, PNG, GIF, WebP — otomatik WebP'ye dönüştürülür ve sıkıştırılır (kalite <?= MEDIA_QUALITY ?>, maks <?= MEDIA_MAX_W ?>px)</div>
      <input type="file" name="files[]" multiple accept="image/*" style="display:none" id="fileinput" onchange="document.getElementById('upform').submit()">
    </label>
  </form>
</div>

<div class="panel">
  <!-- Araç çubuğu: sekmeler + arama + sil araçları -->
  <div class="toolbar" style="flex-wrap:wrap;gap:10px">
    <div class="media-tabs" style="flex-wrap:wrap">
      <a href="<?= e(media_url(['filter'=>'all','pg'=>1])) ?>"    class="<?= $filter==='all'?'active':'' ?>">Tümü (<?= $totalAll ?>)</a>
      <a href="<?= e(media_url(['filter'=>'used','pg'=>1])) ?>"   class="<?= $filter==='used'?'active':'' ?>">Kullanımda</a>
      <a href="<?= e(media_url(['filter'=>'unused','pg'=>1])) ?>" class="<?= $filter==='unused'?'active':'' ?>">Kullanılmıyor</a>
      <a href="trash.php">Çöp Kutusu (<?= $totalTrash ?>)</a>
    </div>
    <div style="display:flex;gap:8px;flex-wrap:wrap;align-items:center">
      <!-- Arama formu -->
      <form method="get" style="display:flex;gap:6px">
        <?php if ($filter !== 'all'): ?><input type="hidden" name="filter" value="<?= e($filter) ?>"><?php endif; ?>
        <?php if ($perPage !== 20): ?><input type="hidden" name="per_page" value="<?= $perPage ?>"><?php endif; ?>
        <input type="search" name="q" value="<?= e($search) ?>" placeholder="Dosya adı ara…" style="width:200px;padding:6px 10px;background:transparent;border:1px solid var(--gold-border);border-radius:5px;color:var(--champagne);font-size:13px">
        <button class="btn btn-secondary btn-sm">Ara</button>
        <?php if ($search): ?><a href="<?= e(media_url(['q'=>null,'pg'=>1])) ?>" class="btn btn-secondary btn-sm">Temizle</a><?php endif; ?>
      </form>
      <!-- Sayfa başı -->
      <form method="get" style="display:flex;gap:6px;align-items:center">
        <?php if ($filter !== 'all'): ?><input type="hidden" name="filter" value="<?= e($filter) ?>"><?php endif; ?>
        <?php if ($search): ?><input type="hidden" name="q" value="<?= e($search) ?>"><?php endif; ?>
        <input type="hidden" name="pg" value="1">
        <label style="font-size:12px;color:var(--muted-text)">Sayfa başı:</label>
        <select name="per_page" onchange="this.form.submit()" style="padding:5px 8px;background:var(--olive-2);border:1px solid var(--gold-border);border-radius:5px;color:var(--champagne);font-size:13px">
          <?php foreach ([20,50,100] as $pp): ?>
            <option value="<?= $pp ?>" <?= $perPage===$pp?'selected':'' ?>><?= $pp ?></option>
          <?php endforeach; ?>
        </select>
      </form>
      <!-- İşlem düğmeleri -->
      <form method="post" onsubmit="return confirm('Sitede kullanılmayan tüm görseller çöp kutusuna taşınsın mı?')"><input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="delete_unused"><button class="btn btn-secondary btn-sm">Kullanılmayanları Sil</button></form>
      <form method="post"><input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="scan"><button class="btn btn-secondary btn-sm">Klasörü Tara</button></form>
    </div>
  </div>

  <?php if ($search): ?>
    <p class="muted" style="font-size:13px;margin-bottom:10px">«<?= e($search) ?>» araması — <?= $totalRows ?> sonuç</p>
  <?php endif; ?>

  <div class="media-grid">
    <?php if (!$rows): ?>
      <p class="muted" style="grid-column:1/-1">
        <?= $search ? 'Aramanızla eşleşen görsel bulunamadı.' : 'Henüz görsel yok.' ?>
      </p>
    <?php endif; ?>
    <?php foreach ($rows as $m): ?>
      <div class="media-card">
        <div class="media-thumb" onclick="lightbox('<?= e($m['path']) ?>')"><img src="<?= e($m['path']) ?>" alt=""></div>
        <div class="media-info">
          <div class="name"><?= e($m['filename']) ?></div>
          <div class="meta"><?= (int)$m['width'] ?>×<?= (int)$m['height'] ?> · <?= number_format($m['size']/1024,0) ?> KB</div>
          <div class="uses"><?php if ($m['_uses']): ?>
            <strong><?= $m['_uses'] ?> yerde kullanılıyor</strong>
            <?php $first = $m['_use'][0] ?? null; if ($first): ?>
              <span class="use-summary">· <?= e(mb_substr($first['title'],0,28)) ?><?= mb_strlen($first['title'])>28?'…':'' ?> (<?= e($first['type']) ?>)<?= $m['_uses']>1?' +'.($m['_uses']-1).' daha':'' ?></span>
            <?php endif; ?>
          <?php else: ?><span class="muted" style="font-size:11px">kullanılmıyor</span><?php endif; ?></div>
        </div>
        <div class="media-actions">
          <a class="btn btn-secondary btn-sm" href="<?= e($m['path']) ?>" target="_blank">Aç</a>
          <button type="button" class="btn btn-secondary btn-sm" onclick="navigator.clipboard.writeText(location.origin+'<?= e($m['path']) ?>');this.textContent='Kopyalandı'">URL</button>
          <form method="post" style="flex:1" onsubmit="return confirm('Çöp kutusuna taşınsın mı?')"><input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int)$m['id'] ?>"><button class="btn btn-secondary btn-sm" style="width:100%">Sil</button></form>
        </div>
      </div>
    <?php endforeach; ?>
  </div>

  <!-- Sayfalama -->
  <?php if ($totalPages > 1): ?>
    <div style="display:flex;justify-content:center;gap:6px;margin-top:24px;flex-wrap:wrap">
      <?php if ($pgNum > 1): ?>
        <a href="<?= e(media_url(['pg'=>$pgNum-1])) ?>" class="btn btn-secondary btn-sm">← Önceki</a>
      <?php endif; ?>
      <?php
        $start = max(1, $pgNum - 3);
        $end   = min($totalPages, $pgNum + 3);
        if ($start > 1): ?>
          <a href="<?= e(media_url(['pg'=>1])) ?>" class="btn btn-secondary btn-sm">1</a>
          <?php if ($start > 2): ?><span style="padding:4px 8px;color:var(--muted-text)">…</span><?php endif; ?>
        <?php endif; ?>
      <?php for ($i = $start; $i <= $end; $i++): ?>
        <a href="<?= e(media_url(['pg'=>$i])) ?>"
           class="btn btn-secondary btn-sm <?= $i===$pgNum?'active':'' ?>"
           style="<?= $i===$pgNum?'border-color:var(--gold);color:var(--gold)':'' ?>"><?= $i ?></a>
      <?php endfor; ?>
      <?php if ($end < $totalPages): ?>
          <?php if ($end < $totalPages - 1): ?><span style="padding:4px 8px;color:var(--muted-text)">…</span><?php endif; ?>
          <a href="<?= e(media_url(['pg'=>$totalPages])) ?>" class="btn btn-secondary btn-sm"><?= $totalPages ?></a>
      <?php endif; ?>
      <?php if ($pgNum < $totalPages): ?>
        <a href="<?= e(media_url(['pg'=>$pgNum+1])) ?>" class="btn btn-secondary btn-sm">Sonraki →</a>
      <?php endif; ?>
    </div>
    <p class="muted" style="text-align:center;margin-top:10px;font-size:12px">
      Sayfa <?= $pgNum ?> / <?= $totalPages ?> · Toplam <?= $totalRows ?> görsel gösteriliyor
    </p>
  <?php endif; ?>
</div>

<div class="lightbox" id="lb" onclick="this.classList.remove('show')"><img id="lbimg" src="" alt=""></div>
<script src="../../assets/js/media-library.js"></script>

<?php require_once __DIR__ . '/../core/footer.php'; ?>
