<?php
$page='redirects'; $title='URL Yönlendirmeleri';
require_once __DIR__ . '/core/auth.php';
require_once __DIR__ . '/../includes/redirects.php';

if ($_SERVER['REQUEST_METHOD']==='POST' && csrf_check($_POST['csrf'] ?? null)) {
    $action = $_POST['action'] ?? '';
    if ($action === 'save') {
        $id = (int)($_POST['id'] ?? 0);
        $type = in_array($_POST['match_type'] ?? '', ['exact','prefix','regex'], true) ? $_POST['match_type'] : 'exact';
        $src  = trim($_POST['source'] ?? '');
        $tgt  = trim($_POST['target'] ?? '');
        $code = in_array((int)($_POST['status_code'] ?? 301), [301,302,307,308], true) ? (int)$_POST['status_code'] : 301;
        $en   = !empty($_POST['enabled']) ? 1 : 0;
        $note = trim($_POST['notes'] ?? '') ?: null;
        if ($src === '' || $tgt === '') {
            flash_set('err','Kaynak ve hedef zorunlu.');
        } else {
            if ($id) {
                db()->prepare('UPDATE redirects SET match_type=?, source=?, target=?, status_code=?, enabled=?, notes=? WHERE id=?')
                    ->execute([$type,$src,$tgt,$code,$en,$note,$id]);
                flash_set('ok','Yönlendirme güncellendi.');
            } else {
                db()->prepare('INSERT INTO redirects (match_type, source, target, status_code, enabled, notes) VALUES (?,?,?,?,?,?)')
                    ->execute([$type,$src,$tgt,$code,$en,$note]);
                flash_set('ok','Yönlendirme eklendi.');
            }
        }
        redirect('redirects.php');
    }
    if ($action === 'delete') {
        db()->prepare('DELETE FROM redirects WHERE id=?')->execute([(int)$_POST['id']]);
        flash_set('ok','Silindi.');
        redirect('redirects.php');
    }
    if ($action === 'toggle') {
        db()->prepare('UPDATE redirects SET enabled=1-enabled WHERE id=?')->execute([(int)$_POST['id']]);
        redirect('redirects.php');
    }
}

$editId = (int)($_GET['edit'] ?? 0);
$editing = null;
if ($editId) {
    $st = db()->prepare('SELECT * FROM redirects WHERE id=?');
    $st->execute([$editId]);
    $editing = $st->fetch();
}

$rows = db()->query('SELECT * FROM redirects ORDER BY enabled DESC, id DESC')->fetchAll();
$totalHits = (int)db()->query('SELECT COALESCE(SUM(hit_count),0) FROM redirects')->fetchColumn();

require_once __DIR__ . '/core/header.php';
?>

<div class="kpis">
  <div class="kpi"><div class="lbl">Toplam Kural</div><div class="val"><?= count($rows) ?></div></div>
  <div class="kpi"><div class="lbl">Aktif</div><div class="val"><?= count(array_filter($rows, fn($r)=>$r['enabled'])) ?></div></div>
  <div class="kpi"><div class="lbl">Toplam Hit</div><div class="val"><?= number_format($totalHits, 0, ',', '.') ?></div></div>
</div>

<div class="panel">
  <h3><?= $editing ? 'Yönlendirmeyi Düzenle #'.(int)$editing['id'] : 'Yeni Yönlendirme' ?></h3>
  <form method="post" style="display:grid;gap:14px;max-width:760px">
    <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
    <input type="hidden" name="action" value="save">
    <input type="hidden" name="id" value="<?= (int)($editing['id'] ?? 0) ?>">

    <div class="row-2">
      <div class="field">
        <label>Eşleşme Tipi</label>
        <select name="match_type">
          <?php $mt = $editing['match_type'] ?? 'exact'; ?>
          <option value="exact"  <?= $mt==='exact'?'selected':'' ?>>Tam yol (exact)</option>
          <option value="prefix" <?= $mt==='prefix'?'selected':'' ?>>Önek (prefix)</option>
          <option value="regex"  <?= $mt==='regex'?'selected':'' ?>>Regex (gelişmiş)</option>
        </select>
        <small class="muted">
          <strong>Tam:</strong> /eski-yol → /yeni-yol &nbsp;|&nbsp;
          <strong>Önek:</strong> /eski-klasor/ altındaki herşey /yeni-klasor/'a &nbsp;|&nbsp;
          <strong>Regex:</strong> ^/urunler/(.+)$ → /urun/$1 (yakalama grupları $1, $2)
        </small>
      </div>
      <div class="field">
        <label>HTTP Kodu</label>
        <select name="status_code">
          <?php $sc = (int)($editing['status_code'] ?? 301); ?>
          <option value="301" <?= $sc===301?'selected':'' ?>>301 — Kalıcı (önerilen)</option>
          <option value="302" <?= $sc===302?'selected':'' ?>>302 — Geçici</option>
          <option value="307" <?= $sc===307?'selected':'' ?>>307 — Geçici (POST koru)</option>
          <option value="308" <?= $sc===308?'selected':'' ?>>308 — Kalıcı (POST koru)</option>
        </select>
      </div>
    </div>

    <div class="field">
      <label>Kaynak (eski yol)</label>
      <input name="source" value="<?= e($editing['source'] ?? '') ?>" placeholder="/urunler/eski-urun veya ^/urunler/(.+)$" required>
      <small class="muted">Sadece <strong>yol</strong> (path) yazın — domain yok. Örn. <code>/urunler/balik</code></small>
    </div>
    <div class="field">
      <label>Hedef (yeni yol)</label>
      <input name="target" value="<?= e($editing['target'] ?? '') ?>" placeholder="/urun/yeni-urun veya /urun/$1" required>
      <small class="muted">Sadece site içi yol. Regex tipinde <code>$1</code>, <code>$2</code> ile yakalanan grupları kullanabilirsiniz.</small>
    </div>
    <div class="field">
      <label>Not (opsiyonel)</label>
      <input name="notes" value="<?= e($editing['notes'] ?? '') ?>" placeholder="örn. URL yapı değişikliği">
    </div>
    <label style="display:flex;gap:10px;align-items:center">
      <input type="checkbox" name="enabled" value="1" <?= (!isset($editing) || $editing['enabled'])?'checked':'' ?>> Aktif
    </label>
    <div class="btn-row">
      <button class="btn btn-primary"><?= $editing ? 'Kaydet' : 'Ekle' ?></button>
      <?php if ($editing): ?><a class="btn btn-secondary" href="redirects.php">Vazgeç</a><?php endif; ?>
    </div>
  </form>
</div>

<div class="panel">
  <h3>Örnek Kullanımlar</h3>
  <div style="display:grid;gap:14px;font-size:13px;line-height:1.6">
    <div>
      <strong>1. Tekil yönlendirme</strong> (slug değiştiğinde):<br>
      <span class="muted">Tip:</span> Tam yol &nbsp;
      <span class="muted">Kaynak:</span> <code>/urun/eski-isim</code> &nbsp;
      <span class="muted">Hedef:</span> <code>/urun/yeni-isim</code>
    </div>
    <div>
      <strong>2. Kategori sayfası taşındı</strong>:<br>
      <span class="muted">Tip:</span> Tam yol &nbsp;
      <span class="muted">Kaynak:</span> <code>/urun/eski-kategori</code> &nbsp;
      <span class="muted">Hedef:</span> <code>/urun/yeni-kategori</code>
    </div>
    <div>
      <strong>3. URL yapısı değişti</strong> (örn. <code>/urunler/X</code> → <code>/urun/X</code>):<br>
      <span class="muted">Tip:</span> Regex &nbsp;
      <span class="muted">Kaynak:</span> <code>^/urunler/(.+)$</code> &nbsp;
      <span class="muted">Hedef:</span> <code>/urun/$1</code>
      <br><small class="muted">Tek kuralla yüzlerce URL'i yönlendirir. <code>$1</code> = yakalanan slug.</small>
    </div>
    <div>
      <strong>4. Klasör tabanlı taşıma</strong>:<br>
      <span class="muted">Tip:</span> Önek &nbsp;
      <span class="muted">Kaynak:</span> <code>/eski-blog/</code> &nbsp;
      <span class="muted">Hedef:</span> <code>/blog/</code>
    </div>
  </div>
</div>

<div class="panel">
  <h3>Yönlendirme Listesi</h3>
  <?php if (!$rows): ?>
    <p class="muted">Henüz yönlendirme yok.</p>
  <?php else: ?>
    <table>
      <thead><tr><th>#</th><th>Tip</th><th>Kaynak → Hedef</th><th>Kod</th><th>Hit</th><th>Son Vuruş</th><th>Durum</th><th></th></tr></thead>
      <tbody>
      <?php foreach ($rows as $r): ?>
        <tr style="<?= $r['enabled']?'':'opacity:.5' ?>">
          <td>#<?= (int)$r['id'] ?></td>
          <td><span class="chip"><?= e($r['match_type']) ?></span></td>
          <td>
            <code style="font-size:12px"><?= e($r['source']) ?></code>
            <span style="color:var(--muted-text)"> → </span>
            <code style="font-size:12px;color:var(--leaf)"><?= e($r['target']) ?></code>
            <?php if ($r['notes']): ?><br><small class="muted"><?= e($r['notes']) ?></small><?php endif; ?>
          </td>
          <td><?= (int)$r['status_code'] ?></td>
          <td><strong><?= number_format((int)$r['hit_count'], 0, ',', '.') ?></strong></td>
          <td style="font-size:12px;color:var(--muted-text)"><?= $r['last_hit_at'] ? e(date('d.m.Y H:i', strtotime($r['last_hit_at']))) : '—' ?></td>
          <td>
            <form method="post" style="display:inline">
              <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
              <input type="hidden" name="action" value="toggle">
              <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
              <button class="btn btn-secondary btn-sm" type="submit"><?= $r['enabled']?'Kapat':'Aç' ?></button>
            </form>
          </td>
          <td class="actions">
            <a class="btn btn-secondary btn-sm" href="?edit=<?= (int)$r['id'] ?>">Düzenle</a>
            <form method="post" style="display:inline" onsubmit="return confirm('Bu yönlendirme silinsin mi?')">
              <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
              <input type="hidden" name="action" value="delete">
              <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
              <button class="btn btn-danger btn-sm">Sil</button>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>
</div>

<?php require_once __DIR__ . '/core/footer.php'; ?>
