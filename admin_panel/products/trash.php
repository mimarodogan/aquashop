<?php
$page='products_trash'; $title='Ürün Çöp Kutusu';
require_once __DIR__ . '/../core/header.php';

// ── Sütun var mı? Yoksa oluştur ─────────────────────────────────────────────
try {
    db()->exec("ALTER TABLE products ADD COLUMN IF NOT EXISTS deleted_at DATETIME NULL DEFAULT NULL");
    db()->exec("ALTER TABLE products ADD INDEX IF NOT EXISTS idx_deleted_at (deleted_at)");
} catch(\Throwable $e) {}

// ── 30 günden eski kayıtları otomatik kalıcı sil ────────────────────────────
try {
    $purged = db()->exec(
        "DELETE FROM products WHERE deleted_at IS NOT NULL AND deleted_at < DATE_SUB(NOW(), INTERVAL 30 DAY)"
    );
    if ($purged > 0) {
        flash_set('info', $purged . ' ürün 30 gün dolduğu için kalıcı olarak silindi.');
    }
} catch(\Throwable $e) {}

// ── POST işlemleri ───────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf_check($_POST['csrf'] ?? null)) {
    $action = $_POST['action'] ?? '';

    // Tekil geri yükle
    if ($action === 'restore') {
        $id = (int)$_POST['id'];
        db()->prepare("UPDATE products SET deleted_at = NULL WHERE id = ?")->execute([$id]);
        flash_set('ok', 'Ürün geri yüklendi.');
        redirect('trash.php');
    }

    // Tekil kalıcı sil
    if ($action === 'purge') {
        $id = (int)$_POST['id'];
        db()->prepare("DELETE FROM products WHERE id = ? AND deleted_at IS NOT NULL")->execute([$id]);
        try { db()->prepare("DELETE FROM product_categories WHERE product_id = ?")->execute([$id]); } catch(\Throwable $e){}
        flash_set('ok', 'Ürün kalıcı olarak silindi.');
        redirect('trash.php');
    }

    // Tümünü kalıcı sil
    if ($action === 'purge_all') {
        db()->exec("DELETE FROM products WHERE deleted_at IS NOT NULL");
        try { db()->exec("DELETE pc FROM product_categories pc LEFT JOIN products p ON p.id = pc.product_id WHERE p.id IS NULL"); } catch(\Throwable $e){}
        flash_set('ok', 'Çöp kutusu tamamen boşaltıldı.');
        redirect('trash.php');
    }

    // Toplu geri yükle
    $ids = array_map('intval', array_filter((array)($_POST['ids'] ?? []), 'is_numeric'));
    if ($action === 'bulk_restore' && $ids) {
        $in = implode(',', $ids);
        db()->exec("UPDATE products SET deleted_at = NULL WHERE id IN ($in)");
        flash_set('ok', count($ids) . ' ürün geri yüklendi.');
        redirect('trash.php');
    }

    // Toplu kalıcı sil
    if ($action === 'bulk_purge' && $ids) {
        $in = implode(',', $ids);
        db()->exec("DELETE FROM products WHERE id IN ($in) AND deleted_at IS NOT NULL");
        try { db()->exec("DELETE FROM product_categories WHERE product_id IN ($in)"); } catch(\Throwable $e){}
        flash_set('ok', count($ids) . ' ürün kalıcı olarak silindi.');
        redirect('trash.php');
    }
}

// ── Çöpteki ürünleri çek ────────────────────────────────────────────────────
$q = trim($_GET['q'] ?? '');
$sql  = "SELECT p.*, c.name AS cat_name,
         DATEDIFF(DATE_ADD(p.deleted_at, INTERVAL 30 DAY), NOW()) AS days_left
         FROM products p
         LEFT JOIN categories c ON c.id = p.category_id
         WHERE p.deleted_at IS NOT NULL";
$args = [];
if ($q !== '') {
    $sql .= " AND (p.name LIKE ? OR p.sku LIKE ?)";
    $args[] = "%$q%"; $args[] = "%$q%";
}
$sql .= " ORDER BY p.deleted_at DESC";
$st = db()->prepare($sql); $st->execute($args);
$rows = $st->fetchAll();
$total = count($rows);
?>

<style>
.trash-header{display:flex;align-items:center;gap:10px;flex-wrap:wrap;
  padding:10px 14px;background:rgba(180,40,40,.08);border:1px solid rgba(180,40,40,.25);
  border-radius:8px;margin-bottom:14px;color:var(--champagne)}
.trash-header svg{width:18px;height:18px;stroke:currentColor;fill:none;stroke-width:1.8;flex-shrink:0}
.trash-header strong{color:#e05555}
.trash-header .ml{margin-left:auto;display:flex;gap:8px;flex-wrap:wrap}

.bulk-bar{display:none;align-items:center;gap:10px;flex-wrap:wrap;padding:10px 14px;
  background:rgba(107,122,47,.12);border:1px solid var(--gold-border);
  border-radius:8px;margin-bottom:12px}
.bulk-bar.show{display:flex}
#bulkCount{font-size:13px;color:var(--gold);font-weight:600;min-width:80px}
tr:has(.row-chk:checked) td{background:rgba(107,122,47,.08)}
th:first-child,td:first-child{width:36px;text-align:center;padding-right:0}

.days-badge{display:inline-block;padding:2px 8px;border-radius:12px;font-size:12px;font-weight:600}
.days-ok  {background:rgba(107,122,47,.2);color:#8ab04b}
.days-warn{background:rgba(200,140,20,.2);color:#c8940f}
.days-crit{background:rgba(180,40,40,.2);color:#e05555}
</style>

<div class="panel">
  <!-- Çöp kutusu bilgi bandı -->
  <div class="trash-header">
    <svg viewBox="0 0 24 24"><path d="M3 6h18M8 6V4h8v2M19 6l-1 14H6L5 6"/><path d="M10 11v6M14 11v6"/></svg>
    <span>Çöp kutusunda <strong><?= $total ?> ürün</strong> var &nbsp;·&nbsp; Ürünler silindikten <strong>30 gün sonra</strong> otomatik kalıcı silinir.</span>
    <div class="ml">
      <form class="search" method="get" style="margin:0">
        <input type="text" name="q" value="<?= e($q) ?>" placeholder="Ara…" style="height:32px;font-size:13px">
      </form>
      <?php if ($total > 0): ?>
      <form method="post" onsubmit="return confirm('Çöp kutusu tamamen boşaltılsın mı? Bu işlem geri alınamaz.')">
        <input type="hidden" name="csrf"   value="<?= e(csrf_token()) ?>">
        <input type="hidden" name="action" value="purge_all">
        <button class="btn btn-secondary btn-sm" style="color:#e05555">🗑 Tümünü Kalıcı Sil</button>
      </form>
      <?php endif; ?>
      <a href="list.php" class="btn btn-secondary btn-sm">← Ürün Listesi</a>
    </div>
  </div>

  <!-- Toplu işlem formu -->
  <form method="post" id="bulkForm">
    <input type="hidden" name="csrf"   value="<?= e(csrf_token()) ?>">
    <input type="hidden" name="action" id="bulkAction" value="">

    <div class="bulk-bar" id="bulkBar">
      <span id="bulkCount">0 seçili</span>
      <button type="button" class="btn btn-secondary btn-sm" id="btnBulkRestore">↩ Geri Yükle</button>
      <button type="button" class="btn btn-secondary btn-sm" id="btnBulkPurge" style="color:#e05555">🗑 Kalıcı Sil</button>
      <button type="button" id="bulkClear" class="btn btn-secondary btn-sm">İptal</button>
    </div>

    <table>
      <thead><tr>
        <th><input type="checkbox" id="checkAll"></th>
        <th>Görsel</th><th>ID</th><th>Ad</th><th>Kategori</th>
        <th>Silinme Tarihi</th><th>Kalan Süre</th><th></th>
      </tr></thead>
      <tbody>
      <?php if (!$rows): ?>
        <tr><td colspan="8" style="text-align:center;padding:50px;color:var(--muted)">
          Çöp kutusu boş 🎉
        </td></tr>
      <?php endif; ?>
      <?php foreach ($rows as $p):
        $days = (int)$p['days_left'];
        $dc   = $days > 7 ? 'days-ok' : ($days > 2 ? 'days-warn' : 'days-crit');
        $dl   = $days > 0 ? $days . ' gün' : 'Bugün silinecek';
      ?>
        <tr>
          <td><input type="checkbox" name="ids[]" value="<?= (int)$p['id'] ?>" class="row-chk"></td>
          <td>
            <?php if (!empty($p['image'])): ?>
              <img src="<?= e($p['image']) ?>" alt="" style="width:50px;height:50px;object-fit:cover;border-radius:6px;border:1px solid var(--gold-border);opacity:.7">
            <?php else: ?>
              <div style="width:50px;height:50px;border-radius:6px;border:1px dashed var(--gold-border);display:grid;place-items:center;color:var(--gold-border);font-size:11px;opacity:.7">yok</div>
            <?php endif; ?>
          </td>
          <td class="muted">#<?= (int)$p['id'] ?></td>
          <td>
            <strong style="color:var(--champagne)"><?= e($p['name']) ?></strong>
            <?php if ($p['sku']): ?><br><span class="muted" style="font-size:12px;font-family:monospace"><?= e($p['sku']) ?></span><?php endif; ?>
          </td>
          <td><?= e($p['cat_name'] ?? '-') ?></td>
          <td class="muted" style="font-size:13px"><?= date('d.m.Y H:i', strtotime($p['deleted_at'])) ?></td>
          <td><span class="days-badge <?= $dc ?>"><?= $dl ?></span></td>
          <td style="white-space:nowrap">
            <!-- Geri yükle -->
            <form method="post" style="display:inline">
              <input type="hidden" name="csrf"   value="<?= e(csrf_token()) ?>">
              <input type="hidden" name="action" value="restore">
              <input type="hidden" name="id"     value="<?= (int)$p['id'] ?>">
              <button class="btn btn-secondary btn-sm">↩ Geri Yükle</button>
            </form>
            <!-- Kalıcı sil -->
            <button type="button" class="btn btn-secondary btn-sm" style="color:#e05555"
                    onclick="permDelete(<?= (int)$p['id'] ?>, '<?= e(addslashes($p['name'])) ?>')">🗑 Sil</button>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </form>
</div>

<!-- Kalıcı silme için ayrı form -->
<form method="post" id="purgeForm" style="display:none">
  <input type="hidden" name="csrf"   value="<?= e(csrf_token()) ?>">
  <input type="hidden" name="action" value="purge">
  <input type="hidden" name="id"     id="purgeId" value="">
</form>

<script>
function permDelete(id, name) {
  if (!confirm('"' + name + '" kalıcı olarak silinecek, geri alınamaz. Emin misiniz?')) return;
  document.getElementById('purgeId').value = id;
  document.getElementById('purgeForm').submit();
}

(function(){
  const bar    = document.getElementById('bulkBar'),
        cnt    = document.getElementById('bulkCount'),
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

  document.getElementById('bulkClear').addEventListener('click', ()=>{
    getAll().forEach(c=>c.checked=false); allChk.checked=false; sync();
  });

  document.getElementById('btnBulkRestore').addEventListener('click', ()=>{
    if (!getChecked().length) return;
    actInp.value = 'bulk_restore';
    document.getElementById('bulkForm').submit();
  });

  document.getElementById('btnBulkPurge').addEventListener('click', ()=>{
    if (!getChecked().length) return;
    if (!confirm(getChecked().length + ' ürün kalıcı silinecek, geri alınamaz. Emin misiniz?')) return;
    actInp.value = 'bulk_purge';
    document.getElementById('bulkForm').submit();
  });
})();
</script>

<?php require_once __DIR__ . '/../core/footer.php'; ?>
