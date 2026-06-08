<?php
$page='products'; $title='Ürünler';
require_once __DIR__ . '/../core/header.php';

// ── Sütun/index yoksa oluştur (migration çalıştırılmamışsa) ─────────────────
try {
    db()->exec("ALTER TABLE products ADD COLUMN IF NOT EXISTS deleted_at DATETIME NULL DEFAULT NULL");
    db()->exec("ALTER TABLE products ADD INDEX IF NOT EXISTS idx_deleted_at (deleted_at)");
} catch(\Throwable $e) {}

// ── POST: tekil & toplu işlemler ────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD']==='POST' && csrf_check($_POST['csrf'] ?? null)) {
    $action = $_POST['action'] ?? '';

    // Tekil → çöp kutusuna taşı (soft delete + is_active=0 ki storefront'ta görünmesin)
    if ($action === 'delete') {
        $id = (int)$_POST['id'];
        db()->prepare("UPDATE products SET deleted_at = NOW(), is_active = 0 WHERE id = ?")->execute([$id]);
        flash_set('ok', 'Ürün çöp kutusuna taşındı.');
        redirect('list.php');
    }

    $ids = array_map('intval', array_filter((array)($_POST['ids'] ?? []), 'is_numeric'));

    // Toplu → çöp kutusuna taşı (soft delete + is_active=0)
    if ($action === 'bulk_delete' && $ids) {
        $in = implode(',', $ids);
        db()->exec("UPDATE products SET deleted_at = NOW(), is_active = 0 WHERE id IN ($in)");
        flash_set('ok', count($ids).' ürün çöp kutusuna taşındı.');
        redirect('list.php');
    }
    if ($action === 'bulk_cat' && $ids && !empty($_POST['bulk_cat_id'])) {
        $catId = (int)$_POST['bulk_cat_id'];
        $in = implode(',', $ids);
        db()->exec("UPDATE products SET category_id=$catId WHERE id IN ($in)");
        try {
            db()->exec("DELETE FROM product_categories WHERE product_id IN ($in)");
            $ins = db()->prepare('INSERT IGNORE INTO product_categories (product_id,category_id) VALUES (?,?)');
            foreach ($ids as $pid) $ins->execute([$pid, $catId]);
        } catch(\Throwable $e){}
        flash_set('ok', count($ids).' ürünün kategorisi değiştirildi.');
        redirect('list.php');
    }
    if (in_array($action, ['bulk_activate','bulk_deactivate','bulk_feature','bulk_unfeature']) && $ids) {
        $in = implode(',', $ids);
        $map = [
            'bulk_activate'   => 'is_active=1',
            'bulk_deactivate' => 'is_active=0',
            'bulk_feature'    => 'is_featured=1',
            'bulk_unfeature'  => 'is_featured=0',
        ];
        db()->exec("UPDATE products SET {$map[$action]} WHERE id IN ($in)");
        flash_set('ok', count($ids).' ürün güncellendi.');
        redirect('list.php');
    }
}

// ── Filtreler & sayfalama ───────────────────────────────────────────────────
define('ADMIN_PP', 20);

$q       = trim($_GET['q']      ?? '');
$filterCat    = (int)($_GET['cat']    ?? 0);
$filterStatus = $_GET['status'] ?? '';   // '' | 'active' | 'passive'
$filterStock  = $_GET['stock']  ?? '';   // '' | 'low' | 'out'
$currentPage  = max(1, (int)($_GET['paged'] ?? 1));
$offset       = ($currentPage - 1) * ADMIN_PP;

// Filtre parametrelerini sayfa değiştirirken korumak için
$filterParams = array_filter([
    'q'      => $q,
    'cat'    => $filterCat ?: '',
    'status' => $filterStatus,
    'stock'  => $filterStock,
], fn($v) => $v !== '' && $v !== 0);

// WHERE oluştur
$where = ['1=1', 'p.deleted_at IS NULL'];
$args  = [];

if ($q !== '') {
    $where[] = '(p.name LIKE ? OR p.sku LIKE ? OR p.short_desc LIKE ?)';
    $args[] = "%$q%"; $args[] = "%$q%"; $args[] = "%$q%";
}
if ($filterCat > 0) {
    // Alt kategorileri de dahil et
    $expandIds = [$filterCat];
    try {
        $stCh = db()->prepare('SELECT id FROM categories WHERE parent_id=?');
        $stCh->execute([$filterCat]);
        foreach ($stCh->fetchAll() as $r) $expandIds[] = (int)$r['id'];
    } catch(\Throwable $e){}
    $inCat = implode(',', $expandIds);
    $where[] = "(p.category_id IN ($inCat) OR p.id IN (SELECT product_id FROM product_categories WHERE category_id IN ($inCat)))";
}
if ($filterStatus === 'active')  { $where[] = 'p.is_active = 1'; }
if ($filterStatus === 'passive') { $where[] = 'p.is_active = 0'; }
if ($filterStock  === 'out')     { $where[] = 'p.stock = 0'; }
if ($filterStock  === 'low')     { $where[] = 'p.stock > 0 AND p.stock <= 5'; }

$whereSql = implode(' AND ', $where);

// Toplam sayı
$totalCount = (int)db()->prepare("SELECT COUNT(*) FROM products p WHERE $whereSql")
                        ->execute($args) ? db()->prepare("SELECT COUNT(*) FROM products p WHERE $whereSql")->execute($args) : 0;
$cntSt = db()->prepare("SELECT COUNT(*) FROM products p WHERE $whereSql");
$cntSt->execute($args);
$totalCount = (int)$cntSt->fetchColumn();

$totalPages = max(1, (int)ceil($totalCount / ADMIN_PP));
$currentPage = min($currentPage, $totalPages);

// Sayfalı liste
$sql = "SELECT p.*, c.name AS cat_name
        FROM products p
        LEFT JOIN categories c ON c.id = p.category_id
        WHERE $whereSql
        ORDER BY p.created_at DESC
        LIMIT " . ADMIN_PP . " OFFSET $offset";
$st = db()->prepare($sql); $st->execute($args);
$rows = $st->fetchAll();

// Filtreler için kategori listesi
$cats = db()->query('SELECT id, name, parent_id FROM categories ORDER BY sort_order ASC, name ASC')->fetchAll();

// Sayfalama URL yardımcısı
function pagUrl(int $p, array $extra=[]): string {
    $params = array_merge($_GET, $extra, ['paged'=>$p]);
    unset($params['paged']); if($p>1) $params['paged']=$p;
    $qs = http_build_query(array_filter($params, fn($v)=>$v!==''&&$v!==0&&$v!==null));
    return 'list.php' . ($qs ? "?$qs" : '');
}
?>

<style>
/* ── Filtre satırı ─────────────────────────────────────────────────────────── */
.filter-bar{display:flex;align-items:center;gap:8px;flex-wrap:wrap;
  padding:10px 0 14px;border-bottom:1px solid var(--gold-border);margin-bottom:14px}
.filter-bar select,.filter-bar input[type=text]{
  padding:6px 10px;border:1px solid var(--gold-border);border-radius:6px;
  background:var(--olive-2);color:var(--champagne);font-size:13px;height:34px}
.filter-bar input[type=text]{min-width:200px}
.filter-bar .btn{height:34px;line-height:1}
.filter-tag{display:inline-flex;align-items:center;gap:4px;padding:3px 10px;
  background:rgba(107,122,47,.18);border:1px solid var(--gold-border);
  border-radius:20px;font-size:12px;color:var(--champagne)}
.filter-tag a{color:var(--gold);text-decoration:none;font-weight:700;margin-left:2px}

/* ── Toplu işlem toolbar ───────────────────────────────────────────────────── */
.bulk-bar{display:none;align-items:center;gap:10px;flex-wrap:wrap;padding:10px 14px;
  background:rgba(107,122,47,.12);border:1px solid var(--gold-border);
  border-radius:8px;margin-bottom:12px}
.bulk-bar.show{display:flex}
.bulk-bar select{padding:6px 10px;border:1px solid var(--gold-border);
  border-radius:6px;background:var(--olive-2);color:var(--champagne);font-size:13px}
.bulk-bar .btn{flex-shrink:0}
#bulkCount{font-size:13px;color:var(--gold);font-weight:600;min-width:80px}
tr:has(.row-chk:checked) td{background:rgba(107,122,47,.08)}
th:first-child,td:first-child{width:36px;text-align:center;padding-right:0}

/* ── Sayfalama ─────────────────────────────────────────────────────────────── */
.pagination{display:flex;align-items:center;gap:6px;justify-content:center;
  margin-top:20px;flex-wrap:wrap}
.pagination a,.pagination span{display:inline-flex;align-items:center;justify-content:center;
  min-width:34px;height:34px;padding:0 10px;border-radius:6px;font-size:13px;
  border:1px solid var(--gold-border);text-decoration:none;color:var(--champagne);
  background:var(--olive-2)}
.pagination a:hover{background:rgba(107,122,47,.3);color:var(--gold)}
.pagination span.current{background:var(--gold);color:#1a1a0f;border-color:var(--gold);font-weight:700}
.pagination span.dots{border:none;background:none;color:var(--muted)}
.pager-info{text-align:center;font-size:12px;color:var(--muted);margin-top:6px}
</style>

<div class="panel">
  <div class="toolbar">
    <div style="font-size:14px;color:var(--muted)">
      Toplam <strong style="color:var(--champagne)"><?= number_format($totalCount) ?></strong> ürün
    </div>
    <div class="btn-row">
      <a class="btn btn-secondary btn-sm" href="import.php">⇪ WordPress İçe Aktar</a>
      <a class="btn btn-primary btn-sm" href="edit.php">+ Yeni Ürün</a>
    </div>
  </div>

  <!-- ── Filtre formu ──────────────────────────────────────────────────────── -->
  <form method="get" class="filter-bar" id="filterForm">
    <input type="text" name="q" value="<?= e($q) ?>" placeholder="Ürün adı, SKU…">

    <select name="cat" onchange="this.form.submit()">
      <option value="">Tüm Kategoriler</option>
      <?php foreach($cats as $c):
        $indent = $c['parent_id'] ? '↳ ' : '';
      ?>
        <option value="<?= (int)$c['id'] ?>" <?= $filterCat===$c['id']?'selected':'' ?>>
          <?= $indent . e($c['name']) ?>
        </option>
      <?php endforeach; ?>
    </select>

    <select name="status" onchange="this.form.submit()">
      <option value="">Tüm Durumlar</option>
      <option value="active"  <?= $filterStatus==='active' ?'selected':'' ?>>✓ Aktif</option>
      <option value="passive" <?= $filterStatus==='passive'?'selected':'' ?>>✗ Pasif</option>
    </select>

    <select name="stock" onchange="this.form.submit()">
      <option value="">Tüm Stok</option>
      <option value="out" <?= $filterStock==='out'?'selected':'' ?>>Stok Yok (0)</option>
      <option value="low" <?= $filterStock==='low'?'selected':'' ?>>Az Stok (1–5)</option>
    </select>

    <button type="submit" class="btn btn-secondary btn-sm">Filtrele</button>
    <?php if ($filterParams): ?>
      <a href="list.php" class="btn btn-secondary btn-sm" style="color:var(--gold)">✕ Temizle</a>
    <?php endif; ?>
  </form>

  <!-- Aktif filtre etiketleri -->
  <?php if ($filterParams): ?>
  <div style="display:flex;gap:6px;flex-wrap:wrap;margin-bottom:10px">
    <?php if ($q): ?>
      <span class="filter-tag">Arama: "<?= e($q) ?>"
        <a href="<?= e(pagUrl(1, ['q'=>''])) ?>">×</a></span>
    <?php endif; ?>
    <?php if ($filterCat > 0):
      $cName = '';
      foreach($cats as $c) if($c['id']==$filterCat){$cName=$c['name'];break;}
    ?>
      <span class="filter-tag">Kategori: <?= e($cName) ?>
        <a href="<?= e(pagUrl(1, ['cat'=>''])) ?>">×</a></span>
    <?php endif; ?>
    <?php if ($filterStatus === 'active'): ?>
      <span class="filter-tag">Durum: Aktif
        <a href="<?= e(pagUrl(1, ['status'=>''])) ?>">×</a></span>
    <?php elseif ($filterStatus === 'passive'): ?>
      <span class="filter-tag">Durum: Pasif
        <a href="<?= e(pagUrl(1, ['status'=>''])) ?>">×</a></span>
    <?php endif; ?>
    <?php if ($filterStock === 'out'): ?>
      <span class="filter-tag">Stok: Yok
        <a href="<?= e(pagUrl(1, ['stock'=>''])) ?>">×</a></span>
    <?php elseif ($filterStock === 'low'): ?>
      <span class="filter-tag">Stok: Az (1–5)
        <a href="<?= e(pagUrl(1, ['stock'=>''])) ?>">×</a></span>
    <?php endif; ?>
  </div>
  <?php endif; ?>

  <!-- ── Toplu işlem formu + tablo ────────────────────────────────────────── -->
  <form method="post" id="bulkForm">
    <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
    <input type="hidden" name="action" id="bulkAction" value="">

    <div class="bulk-bar" id="bulkBar">
      <span id="bulkCount">0 seçili</span>
      <select id="bulkSel" name="_bulk_op">
        <option value="">— Toplu İşlem —</option>
        <option value="bulk_delete">🗑 Sil</option>
        <option value="bulk_cat">📂 Kategori Değiştir</option>
        <option value="bulk_activate">✓ Aktif Yap</option>
        <option value="bulk_deactivate">✗ Pasif Yap</option>
        <option value="bulk_feature">⭐ Öne Çıkar</option>
        <option value="bulk_unfeature">☆ Öne Çıkarmayı Kaldır</option>
      </select>
      <select name="bulk_cat_id" id="bulkCatSel" style="display:none">
        <option value="">— Kategori Seç —</option>
        <?php foreach($cats as $c): ?>
          <option value="<?= (int)$c['id'] ?>"><?= ($c['parent_id']?'↳ ':'') . e($c['name']) ?></option>
        <?php endforeach; ?>
      </select>
      <button type="submit" class="btn btn-primary btn-sm">Uygula</button>
      <button type="button" id="bulkClear" class="btn btn-secondary btn-sm">İptal</button>
    </div>

    <table>
      <thead><tr>
        <th><input type="checkbox" id="checkAll" title="Tümünü seç"></th>
        <th>Görsel</th><th>ID</th><th>Ad</th><th>SKU</th>
        <th>Kategori</th><th>Marka</th><th>Fiyat</th><th>Stok</th><th>Durum</th><th></th>
      </tr></thead>
      <tbody>
      <?php if (!$rows): ?>
        <tr><td colspan="10" style="text-align:center;padding:40px;color:var(--muted)">Ürün bulunamadı.</td></tr>
      <?php endif; ?>
      <?php foreach ($rows as $p): ?>
        <tr>
          <td><input type="checkbox" name="ids[]" value="<?= (int)$p['id'] ?>" class="row-chk"></td>
          <td>
            <?php if (!empty($p['image'])): ?>
              <a href="<?= e($p['image']) ?>" target="_blank"><img src="<?= e($p['image']) ?>" alt="" style="width:54px;height:54px;object-fit:cover;border-radius:6px;border:1px solid var(--gold-border);display:block"></a>
            <?php else: ?>
              <div style="width:54px;height:54px;border-radius:6px;border:1px dashed var(--gold-border);display:grid;place-items:center;color:var(--gold-border);font-size:11px">yok</div>
            <?php endif; ?>
          </td>
          <td>#<?= (int)$p['id'] ?></td>
          <td><strong style="color:var(--champagne)"><?= e($p['name']) ?></strong><br><span class="muted" style="font-size:12px"><?= e($p['slug']) ?></span></td>
          <td class="muted" style="font-family:monospace;font-size:12px"><?= e($p['sku'] ?? '-') ?></td>
          <td><?= e($p['cat_name'] ?? '-') ?></td>
          <td class="muted" style="font-size:13px"><?= e($p['brand'] ?? '-') ?></td>
          <td style="color:var(--gold);font-weight:600"><?= money($p['price']) ?></td>
          <td><?php
            $stock = (int)$p['stock'];
            $color = $stock === 0 ? 'var(--red,#e05555)' : ($stock <= 5 ? '#e0a020' : 'inherit');
            echo "<span style='color:$color;font-weight:".($stock<=5?'600':'400')."'>$stock</span>";
          ?></td>
          <td><span class="status <?= $p['is_active']?'paid':'cancelled' ?>"><?= $p['is_active']?'aktif':'pasif' ?></span></td>
          <td>
            <a class="btn btn-secondary btn-sm" href="edit.php?id=<?= (int)$p['id'] ?>&paged=<?= $currentPage ?>">Düzenle</a>
            <button type="button" class="btn btn-secondary btn-sm"
                    onclick="singleDelete(<?= (int)$p['id'] ?>, '<?= e(addslashes($p['name'])) ?>')">Sil</button>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </form>

  <!-- Tekil silme için ayrı form (bulkForm içinde nested form olmasın) -->
  <form method="post" id="deleteForm" style="display:none">
    <input type="hidden" name="csrf"   value="<?= e(csrf_token()) ?>">
    <input type="hidden" name="action" value="delete">
    <input type="hidden" name="id"     id="deleteId" value="">
  </form>

  <!-- ── Sayfalama ────────────────────────────────────────────────────────── -->
  <?php if ($totalPages > 1): ?>
  <nav class="pagination">
    <?php if ($currentPage > 1): ?>
      <a href="<?= e(pagUrl($currentPage - 1)) ?>">‹</a>
    <?php endif; ?>

    <?php
    // Sayfa numaralarını akıllıca göster: 1 … 4 5 6 … 12
    $range = 2;
    $shown = [];
    for ($i = 1; $i <= $totalPages; $i++) {
        if ($i === 1 || $i === $totalPages ||
            ($i >= $currentPage - $range && $i <= $currentPage + $range)) {
            $shown[] = $i;
        }
    }
    $prev = null;
    foreach ($shown as $pg):
        if ($prev !== null && $pg - $prev > 1): ?>
          <span class="dots">…</span>
        <?php endif;
        if ($pg === $currentPage): ?>
          <span class="current"><?= $pg ?></span>
        <?php else: ?>
          <a href="<?= e(pagUrl($pg)) ?>"><?= $pg ?></a>
        <?php endif;
        $prev = $pg;
    endforeach; ?>

    <?php if ($currentPage < $totalPages): ?>
      <a href="<?= e(pagUrl($currentPage + 1)) ?>">›</a>
    <?php endif; ?>
  </nav>
  <p class="pager-info">
    Sayfa <?= $currentPage ?> / <?= $totalPages ?>
    &nbsp;·&nbsp;
    <?= (($currentPage-1)*ADMIN_PP)+1 ?>–<?= min($currentPage*ADMIN_PP, $totalCount) ?> arası gösteriliyor
  </p>
  <?php endif; ?>

</div>

<script>
// Tekil silme
function singleDelete(id, name) {
  if (!confirm('"' + name + '" silinsin mi?')) return;
  document.getElementById('deleteId').value = id;
  document.getElementById('deleteForm').submit();
}

(function(){
  const bar    = document.getElementById('bulkBar'),
        cnt    = document.getElementById('bulkCount'),
        sel    = document.getElementById('bulkSel'),
        catSel = document.getElementById('bulkCatSel'),
        actInp = document.getElementById('bulkAction'),
        allChk = document.getElementById('checkAll');

  function getChecked(){ return [...document.querySelectorAll('.row-chk')].filter(c=>c.checked); }
  function getAll()    { return [...document.querySelectorAll('.row-chk')]; }

  function sync(){
    const chk = getChecked(), all = getAll();
    bar.classList.toggle('show', chk.length > 0);
    cnt.textContent = chk.length + ' seçili';
    allChk.indeterminate = chk.length > 0 && chk.length < all.length;
    allChk.checked = chk.length > 0 && chk.length === all.length;
  }

  allChk.addEventListener('change', ()=>{ getAll().forEach(c=>c.checked=allChk.checked); sync(); });
  document.querySelectorAll('.row-chk').forEach(c=>c.addEventListener('change', sync));

  sel.addEventListener('change', ()=>{
    catSel.style.display = sel.value === 'bulk_cat' ? '' : 'none';
  });

  document.getElementById('bulkClear').addEventListener('click', ()=>{
    getAll().forEach(c=>c.checked=false);
    allChk.checked = false;
    sync();
  });

  document.getElementById('bulkForm').addEventListener('submit', function(e){
    const op = sel.value;
    if (!op){ e.preventDefault(); alert('Lütfen bir işlem seçin.'); return; }
    if (op === 'bulk_delete' && !confirm(getChecked().length + ' ürün kalıcı olarak silinecek. Emin misiniz?')){ e.preventDefault(); return; }
    if (op === 'bulk_cat' && !catSel.value){ e.preventDefault(); alert('Lütfen kategori seçin.'); return; }
    actInp.value = op;
  });
})();
</script>

<?php require_once __DIR__ . '/../core/footer.php'; ?>
