<?php
/**
 * Toplu Stok Yönetimi — CSV dışa/içe aktarma + satır içi düzenleme
 */
require_once __DIR__ . '/../../core/bootstrap.php';
require_once __DIR__ . '/../../includes/schema_guard.php';
$AP   = rtrim(SITE_URL, '/') . '/admin_panel';
$page = 'products_stock';

// ── Yetki ─────────────────────────────────────────────────────────────────────
$u = current_user();
if (!$u || $u['role'] !== 'admin') { redirect(url('login')); }
admin_ensure_runtime_schema();

// ── CSV EXPORT ─────────────────────────────────────────────────────────────────
if (isset($_GET['export'])) {
    $rows = db()->query(
        "SELECT p.id, p.sku, p.name, p.brand, p.stock,
                COALESCE((SELECT SUM(v.stock) FROM product_variations v WHERE v.product_id=p.id),0) AS var_stock,
                p.has_variations
         FROM products p
         WHERE p.deleted_at IS NULL
         ORDER BY p.name ASC"
    )->fetchAll();

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="stok_' . date('Ymd_Hi') . '.csv"');
    header('Cache-Control: no-cache');

    $out = fopen('php://output', 'w');
    // UTF-8 BOM — Excel'in Türkçe karakterleri doğru okuması için
    fwrite($out, "\xEF\xBB\xBF");

    fputcsv($out, ['ID', 'SKU', 'Ürün Adı', 'Marka', 'Mevcut Stok', 'Yeni Stok', 'Not'], ';');

    foreach ($rows as $r) {
        $stock = $r['has_variations'] ? (int)$r['var_stock'] : (int)$r['stock'];
        fputcsv($out, [
            $r['id'],
            $r['sku'] ?? '',
            $r['name'],
            $r['brand'] ?? '',
            $stock,
            $stock,   // Yeni Stok — kullanıcı bu sütunu düzenler
            '',
        ], ';');
    }
    fclose($out);
    exit;
}

// ── CSV IMPORT ─────────────────────────────────────────────────────────────────
$importResult = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'csv_import') {
    if (!csrf_check($_POST['csrf'] ?? null)) {
        flash_set('err', 'Geçersiz istek (CSRF).');
        redirect('stock.php');
    }

    $file = $_FILES['csv_file'] ?? null;
    if (!$file || $file['error'] !== UPLOAD_ERR_OK) {
        flash_set('err', 'Dosya yüklenemedi.');
        redirect('stock.php');
    }

    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, ['csv', 'txt'])) {
        flash_set('err', 'Sadece .csv uzantılı dosya kabul edilir.');
        redirect('stock.php');
    }

    $handle = fopen($file['tmp_name'], 'r');
    if (!$handle) {
        flash_set('err', 'Dosya okunamadı.');
        redirect('stock.php');
    }

    // BOM temizle
    $bom = fread($handle, 3);
    if ($bom !== "\xEF\xBB\xBF") rewind($handle);

    // Başlık satırını atla
    $header = fgetcsv($handle, 1000, ';');
    if (!$header) { fclose($handle); flash_set('err', 'CSV başlığı okunamadı.'); redirect('stock.php'); }

    // Sütun indexlerini bul (büyük/küçük harf, boşluk toleranslı)
    $hNorm  = array_map(fn($h) => mb_strtolower(trim($h)), $header);
    $idxId  = array_search('id', $hNorm);
    $idxNew = array_search('yeni stok', $hNorm);
    if ($idxId === false || $idxNew === false) {
        fclose($handle);
        flash_set('err', "CSV'de 'ID' ve 'Yeni Stok' sütunları bulunamadı. Orijinal şablonu kullanın.");
        redirect('stock.php');
    }

    $updStmt = db()->prepare('UPDATE products SET stock=? WHERE id=? AND deleted_at IS NULL AND has_variations=0');
    $updated = 0; $skipped = 0; $errors = [];

    while (($row = fgetcsv($handle, 1000, ';')) !== false) {
        if (empty($row[$idxId])) { $skipped++; continue; }
        $pid      = (int)$row[$idxId];
        $newStock = trim($row[$idxNew] ?? '');
        if (!is_numeric($newStock) || $newStock < 0) { $skipped++; continue; }
        $cnt = $updStmt->execute([(int)$newStock, $pid]) ? $updStmt->rowCount() : 0;
        if ($cnt) $updated++; else $skipped++;
    }
    fclose($handle);

    flash_set('ok', "$updated ürün stoğu güncellendi." . ($skipped ? " $skipped satır atlandı (varyasyonlu veya geçersiz)." : ''));
    redirect('stock.php');
}

// ── INLINE SAVE (tek tek veya toplu form) ──────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'bulk_save') {
    if (!csrf_check($_POST['csrf'] ?? null)) {
        flash_set('err', 'Geçersiz istek (CSRF).');
        redirect('stock.php');
    }
    $stocks = (array)($_POST['stocks'] ?? []);
    $stmt   = db()->prepare('UPDATE products SET stock=? WHERE id=? AND deleted_at IS NULL AND has_variations=0');
    $cnt    = 0;
    foreach ($stocks as $pid => $val) {
        if (!is_numeric($pid) || !is_numeric($val) || $val < 0) continue;
        $stmt->execute([(int)$val, (int)$pid]);
        $cnt += $stmt->rowCount();
    }
    flash_set('ok', "$cnt ürün stoğu güncellendi.");
    redirect('stock.php');
}

// ── Ürünleri çek ──────────────────────────────────────────────────────────────
$q    = trim($_GET['q'] ?? '');
$filterStock = $_GET['stock_filter'] ?? ''; // 'low' | 'out' | ''

$where = ['p.deleted_at IS NULL'];
$params = [];

if ($q !== '') {
    $where[] = '(p.name LIKE ? OR p.sku LIKE ? OR p.brand LIKE ?)';
    $params[] = "%$q%"; $params[] = "%$q%"; $params[] = "%$q%";
}

$sql = "SELECT p.id, p.sku, p.name, p.brand, p.stock, p.is_active, p.has_variations,
               COALESCE((SELECT SUM(v.stock) FROM product_variations v WHERE v.product_id=p.id),0) AS var_stock
        FROM products p
        WHERE " . implode(' AND ', $where) . "
        ORDER BY p.name ASC";

$stmt = db()->prepare($sql);
$stmt->execute($params);
$products = $stmt->fetchAll();

// Stok filtresini PHP tarafında uygula (SQL HAVING daha karmaşık olurdu)
if ($filterStock === 'out') {
    $products = array_filter($products, fn($r) => (int)($r['has_variations'] ? $r['var_stock'] : $r['stock']) === 0);
} elseif ($filterStock === 'low') {
    $products = array_filter($products, fn($r) => (int)($r['has_variations'] ? $r['var_stock'] : $r['stock']) > 0 && (int)($r['has_variations'] ? $r['var_stock'] : $r['stock']) <= 5);
}

$total = count($products);
require_once __DIR__ . '/../core/header.php';
?>
<style>
.stock-page { max-width: 1200px; }
.stock-toolbar { display:flex; gap:10px; flex-wrap:wrap; align-items:center; margin-bottom:16px; }
.stock-toolbar input[type="search"] {
    flex:1; min-width:200px; max-width:360px;
    padding:8px 12px; border:1px solid var(--gold-border);
    border-radius:var(--radius); background:var(--olive-2); color:var(--champagne); font-size:14px;
}
.stock-toolbar select {
    padding:8px 12px; border:1px solid var(--gold-border);
    border-radius:var(--radius); background:var(--olive-2); color:var(--champagne); font-size:14px;
}
.stock-table { width:100%; border-collapse:collapse; font-size:13px; }
.stock-table th { text-align:left; padding:10px 12px; border-bottom:2px solid var(--gold-border);
    color:var(--gold); font-size:12px; text-transform:uppercase; letter-spacing:.04em; }
.stock-table td { padding:8px 12px; border-bottom:1px solid rgba(107,122,47,.15); vertical-align:middle; }
.stock-table tr:hover td { background:rgba(107,122,47,.06); }
.stock-input { width:90px; padding:5px 8px; border:1px solid var(--gold-border);
    border-radius:6px; background:var(--olive-2); color:var(--champagne);
    font-size:14px; text-align:center; }
.stock-input:focus { outline:none; border-color:var(--gold); }
.stock-badge { display:inline-block; padding:2px 8px; border-radius:999px; font-size:12px; font-weight:600; }
.s-ok   { background:rgba(50,180,50,.15); color:#5dba5d; }
.s-low  { background:rgba(220,160,20,.15); color:#d4a020; }
.s-out  { background:rgba(220,60,60,.15);  color:#e05555; }
.var-note { font-size:11px; color:var(--muted); }
.import-box { border:2px dashed var(--gold-border); border-radius:10px; padding:24px;
    text-align:center; background:rgba(107,122,47,.06); }
.import-box input[type="file"] { display:none; }
.import-box label { cursor:pointer; display:inline-flex; align-items:center; gap:8px;
    padding:10px 20px; border:1px solid var(--gold-border); border-radius:var(--radius);
    background:var(--olive-2); color:var(--champagne); font-size:14px;
    transition:background .15s; }
.import-box label:hover { background:var(--cream); }
.import-filename { margin-top:8px; font-size:13px; color:var(--gold); }
.section-card { border:1px solid var(--gold-border); border-radius:10px;
    padding:20px 22px; background:rgba(15,26,16,.3); margin-bottom:20px; }
.section-card h3 { margin:0 0 14px; font-size:15px; color:var(--gold); display:flex; align-items:center; gap:8px; }
</style>

<div class="stock-page">
  <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;margin-bottom:20px">
    <h1 style="margin:0;font-size:22px">📦 Toplu Stok Yönetimi</h1>
    <div style="display:flex;gap:10px;flex-wrap:wrap">
      <a href="?export=1" class="btn btn-secondary btn-sm">⬇ Excel / CSV İndir</a>
      <a href="list.php" class="btn btn-secondary btn-sm">← Ürün Listesi</a>
    </div>
  </div>

  <!-- Import bölümü -->
  <div class="section-card">
    <h3>📤 CSV/Excel'den İçe Aktar</h3>
    <p style="font-size:13px;color:var(--muted);margin:0 0 14px">
      Önce <a href="?export=1" style="color:var(--gold)">şablonu indirin</a>, <strong>"Yeni Stok"</strong> sütununu düzenleyip kaydedin, ardından buraya yükleyin.
      Varyasyonlu ürünlerin ana stoğu güncellenmez — her varyasyonu ürün düzenleme sayfasından güncelleyin.
    </p>
    <form method="post" enctype="multipart/form-data" id="importForm">
      <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
      <input type="hidden" name="action" value="csv_import">
      <div class="import-box">
        <input type="file" name="csv_file" id="csvFile" accept=".csv,.txt">
        <label for="csvFile">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><path d="m17 8-5-5-5 5"/><path d="M12 3v12"/></svg>
          Dosya Seç (.csv)
        </label>
        <div class="import-filename" id="importFilename">Dosya seçilmedi</div>
        <div style="margin-top:14px">
          <button type="submit" class="btn btn-primary btn-sm" id="importBtn" disabled>Yükle ve Güncelle</button>
        </div>
      </div>
    </form>
  </div>

  <!-- Inline stok tablosu -->
  <div class="section-card">
    <h3>✏️ Stokları Düzenle <span style="font-weight:400;font-size:13px;color:var(--muted)">(<?= $total ?> ürün)</span></h3>

    <!-- Filtreler -->
    <form method="get" class="stock-toolbar">
      <input type="search" name="q" value="<?= e($q) ?>" placeholder="Ürün adı, SKU veya marka…">
      <select name="stock_filter" onchange="this.form.submit()">
        <option value="" <?= $filterStock===''?'selected':'' ?>>Tüm Stoklar</option>
        <option value="out" <?= $filterStock==='out'?'selected':'' ?>>Tükenmiş (0)</option>
        <option value="low" <?= $filterStock==='low'?'selected':'' ?>>Kritik (1–5)</option>
      </select>
      <button type="submit" class="btn btn-secondary btn-sm">Filtrele</button>
      <?php if ($q || $filterStock): ?>
        <a href="stock.php" class="btn btn-secondary btn-sm">Temizle</a>
      <?php endif; ?>
    </form>

    <form method="post" id="bulkStockForm">
      <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
      <input type="hidden" name="action" value="bulk_save">

      <?php if (empty($products)): ?>
        <p style="text-align:center;padding:30px;color:var(--muted)">Ürün bulunamadı.</p>
      <?php else: ?>
      <div style="overflow-x:auto">
        <table class="stock-table">
          <thead>
            <tr>
              <th>ID</th>
              <th>SKU</th>
              <th>Ürün Adı</th>
              <th>Marka</th>
              <th>Mevcut Stok</th>
              <th>Yeni Stok</th>
              <th>Durum</th>
            </tr>
          </thead>
          <tbody>
          <?php foreach ($products as $r):
              $curStock = (int)($r['has_variations'] ? $r['var_stock'] : $r['stock']);
              if ($curStock === 0)        $badgeCls = 's-out';
              elseif ($curStock <= 5)     $badgeCls = 's-low';
              else                        $badgeCls = 's-ok';
          ?>
            <tr>
              <td style="color:var(--muted)"><?= (int)$r['id'] ?></td>
              <td style="font-family:monospace;font-size:12px"><?= e($r['sku'] ?? '—') ?></td>
              <td>
                <a href="edit.php?id=<?= (int)$r['id'] ?>" style="color:var(--champagne)" target="_blank"><?= e($r['name']) ?></a>
              </td>
              <td><?= e($r['brand'] ?? '—') ?></td>
              <td>
                <span class="stock-badge <?= $badgeCls ?>"><?= $curStock ?></span>
              </td>
              <td>
                <?php if ($r['has_variations']): ?>
                  <span class="var-note">Varyasyonlu — <a href="edit.php?id=<?= (int)$r['id'] ?>#variations" style="color:var(--gold)">düzenle</a></span>
                <?php else: ?>
                  <input type="number" class="stock-input" name="stocks[<?= (int)$r['id'] ?>]"
                         value="<?= $curStock ?>" min="0"
                         data-orig="<?= $curStock ?>"
                         onchange="markChanged(this)">
                <?php endif; ?>
              </td>
              <td><span class="status <?= $r['is_active']?'paid':'cancelled' ?>"><?= $r['is_active']?'aktif':'pasif' ?></span></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>

      <div style="display:flex;justify-content:flex-end;gap:10px;margin-top:16px;align-items:center">
        <span id="changedNote" style="font-size:13px;color:var(--gold);display:none">● Kaydedilmemiş değişiklikler var</span>
        <button type="button" class="btn btn-secondary btn-sm" onclick="resetChanges()">Sıfırla</button>
        <button type="submit" class="btn btn-primary btn-sm">Tüm Değişiklikleri Kaydet</button>
      </div>
      <?php endif; ?>
    </form>
  </div>
</div>

<script>
(function(){
  // Dosya seçimi label güncelleme
  var fileInp = document.getElementById('csvFile');
  var fname   = document.getElementById('importFilename');
  var importBtn = document.getElementById('importBtn');
  if (fileInp) {
    fileInp.addEventListener('change', function(){
      var f = this.files[0];
      fname.textContent  = f ? f.name : 'Dosya seçilmedi';
      importBtn.disabled = !f;
    });
  }

  // Değişiklik takibi
  var changed = false;
  window.markChanged = function(inp){
    var orig = parseInt(inp.dataset.orig, 10);
    inp.style.borderColor = (parseInt(inp.value, 10) !== orig) ? 'var(--gold)' : '';
    changed = document.querySelectorAll('.stock-input').some(function(i){
      return parseInt(i.value,10) !== parseInt(i.dataset.orig,10);
    });
    document.getElementById('changedNote').style.display = changed ? '' : 'none';
  };

  window.resetChanges = function(){
    document.querySelectorAll('.stock-input').forEach(function(i){
      i.value = i.dataset.orig;
      i.style.borderColor = '';
    });
    changed = false;
    document.getElementById('changedNote').style.display = 'none';
  };

  // Sayfa terk uyarısı
  window.addEventListener('beforeunload', function(e){
    if (changed){ e.preventDefault(); e.returnValue=''; }
  });
  document.getElementById('bulkStockForm')?.addEventListener('submit', function(){
    changed = false; // submit sırasında uyarıyı kapat
  });
})();
</script>

<?php require_once __DIR__ . '/../core/footer.php'; ?>
