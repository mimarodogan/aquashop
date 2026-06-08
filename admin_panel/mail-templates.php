<?php
$page = 'mail_templates'; $title = 'Mail Şablonları';
require_once __DIR__ . '/core/auth.php';

// Desteklenen şablonlar ve açıklamaları
$TEMPLATES = [
    'welcome'        => ['label' => 'Hoş Geldin Maili',            'vars' => ['{{isim}}', '{{site_adi}}', '{{site_url}}']],
    'price_alert'    => ['label' => 'Favori Ürün İndirim Alarmı',   'vars' => ['{{isim}}', '{{urun_adi}}', '{{eski_fiyat}}', '{{yeni_fiyat}}', '{{urun_url}}', '{{site_adi}}']],
    'abandoned_cart' => ['label' => 'Terk Edilmiş Sepet',           'vars' => ['{{isim}}', '{{sepet_ozeti}}', '{{sepet_url}}', '{{site_adi}}']],
    'restock_notify' => ['label' => 'Stok Bildirimi (Ürün Geldi)',   'vars' => ['{{urun_adi}}', '{{urun_url}}', '{{site_adi}}']],
    'restock_confirm'=> ['label' => 'Stok Bildirimi (Kayıt Onayı)', 'vars' => ['{{urun_adi}}', '{{site_adi}}']],
    'order_confirm'  => ['label' => 'Sipariş Onayı',                'vars' => ['{{isim}}', '{{siparis_no}}', '{{siparis_ozeti}}', '{{siparis_url}}', '{{site_adi}}']],
];

// POST — kaydet
if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf_check($_POST['csrf'] ?? null)) {
    $key     = trim($_POST['key'] ?? '');
    $subject = trim($_POST['subject'] ?? '');
    $body    = $_POST['body_html'] ?? '';
    if ($key && $subject && $body) {
        try {
            $st = db()->prepare('INSERT INTO mail_templates (`key`, subject, body_html)
                VALUES (?,?,?) ON DUPLICATE KEY UPDATE subject=VALUES(subject), body_html=VALUES(body_html)');
            $st->execute([$key, $subject, $body]);
            flash_set('ok', 'Şablon kaydedildi.');
        } catch (Exception $e) {
            flash_set('err', 'Kayıt başarısız: ' . $e->getMessage());
        }
    }
    redirect('mail-templates.php' . ($key ? '?edit=' . urlencode($key) : ''));
}

// Düzenleme formu için seçili şablon
$editKey = $_GET['edit'] ?? '';
$editRow = null;
if ($editKey && isset($TEMPLATES[$editKey])) {
    try {
        $st = db()->prepare('SELECT * FROM mail_templates WHERE `key`=? LIMIT 1');
        $st->execute([$editKey]);
        $editRow = $st->fetch() ?: ['key' => $editKey, 'subject' => '', 'body_html' => ''];
    } catch (Exception $e) {
        $editRow = ['key' => $editKey, 'subject' => '', 'body_html' => ''];
    }
}

// Tüm kayıtlı şablonları çek
$saved = [];
try {
    $rows = db()->query('SELECT `key`, updated_at FROM mail_templates')->fetchAll();
    foreach ($rows as $r) $saved[$r['key']] = $r['updated_at'];
} catch (Exception $e) {}

require_once __DIR__ . '/core/header.php';
?>

<style>
.tmpl-grid{display:grid;grid-template-columns:260px 1fr;gap:24px;align-items:start}
.tmpl-list a{display:flex;align-items:center;justify-content:space-between;padding:12px 16px;border-radius:6px;font-size:14px;color:var(--text);border:1px solid transparent;transition:all .2s;margin-bottom:4px}
.tmpl-list a:hover{background:var(--surface-2);border-color:var(--gold-border)}
.tmpl-list a.active{background:var(--surface-2);border-color:var(--gold);color:var(--gold)}
.var-chips{display:flex;flex-wrap:wrap;gap:6px;margin:10px 0}
.var-chip{font-family:monospace;font-size:12px;padding:4px 10px;background:var(--surface-2);border:1px solid var(--gold-border);border-radius:4px;cursor:pointer;transition:background .15s}
.var-chip:hover{background:var(--gold-border)}
</style>

<div class="tmpl-grid">
  <!-- SOL: Şablon listesi -->
  <div class="panel">
    <h3 style="margin-bottom:16px">Şablonlar</h3>
    <div class="tmpl-list">
      <?php foreach ($TEMPLATES as $k => $info): ?>
        <a href="?edit=<?= urlencode($k) ?>" class="<?= $editKey === $k ? 'active' : '' ?>">
          <span><?= e($info['label']) ?></span>
          <?php if (isset($saved[$k])): ?>
            <span class="muted" style="font-size:11px" title="Son güncelleme">✓</span>
          <?php else: ?>
            <span class="muted" style="font-size:11px" title="Varsayılan kullanılıyor">—</span>
          <?php endif; ?>
        </a>
      <?php endforeach; ?>
    </div>
    <p class="muted" style="font-size:12px;margin-top:14px;line-height:1.6">
      ✓ = özelleştirilmiş<br>— = sistem varsayılanı
    </p>
  </div>

  <!-- SAĞ: Düzenleme formu -->
  <div class="panel">
    <?php if ($editRow): ?>
      <?php $tmplInfo = $TEMPLATES[$editKey]; ?>
      <h3 style="margin-bottom:6px"><?= e($tmplInfo['label']) ?></h3>
      <p class="muted" style="font-size:13px;margin-bottom:20px">Şablon içeriğini düzenleyin. Değişkenler gönderim sırasında otomatik doldurulur.</p>

      <!-- Kullanılabilir değişkenler -->
      <div style="margin-bottom:20px">
        <p style="font-size:12px;font-weight:600;letter-spacing:.1em;text-transform:uppercase;color:var(--muted-text);margin-bottom:6px">Kullanılabilir Değişkenler</p>
        <div class="var-chips">
          <?php foreach ($tmplInfo['vars'] as $v): ?>
            <span class="var-chip" onclick="insertVar('<?= e(addslashes($v)) ?>')" title="İçeriğe ekle"><?= e($v) ?></span>
          <?php endforeach; ?>
        </div>
        <small class="muted">Bir değişkene tıklayarak metin editörünün imlecine ekleyin.</small>
      </div>

      <form method="post" style="display:grid;gap:16px">
        <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
        <input type="hidden" name="key"  value="<?= e($editKey) ?>">

        <div class="field">
          <label>Konu Satırı</label>
          <input name="subject" value="<?= e($editRow['subject']) ?>" placeholder="Konu satırını girin…" required>
          <small class="muted">Değişkenler (örn. {{isim}}) konu satırında da kullanılabilir.</small>
        </div>

        <div class="field">
          <label>Mail İçeriği (HTML)</label>
          <textarea id="tmpl-body" name="body_html" rows="18" style="font-family:monospace;font-size:13px;resize:vertical"><?= e($editRow['body_html']) ?></textarea>
          <small class="muted">Değişkenleri <code>{{degisken_adi}}</code> formatında kullanın. Temel HTML etiketleri desteklenir. Dış kapsayıcı ve header/footer otomatik eklenir.</small>
        </div>

        <div style="display:flex;gap:10px;flex-wrap:wrap">
          <button class="btn btn-primary">Kaydet</button>
          <a class="btn btn-secondary" href="mail-templates.php">İptal</a>
          <?php if (isset($saved[$editKey])): ?>
            <form method="post" style="display:inline" onsubmit="return confirm('Şablon sıfırlansın mı? Özelleştirme silinecek, sistem varsayılanı kullanılacak.')">
              <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
              <input type="hidden" name="key" value="<?= e($editKey) ?>">
              <input type="hidden" name="subject" value="">
              <input type="hidden" name="body_html" value="">
              <input type="hidden" name="_reset" value="1">
            </form>
          <?php endif; ?>
        </div>
      </form>
    <?php else: ?>
      <div style="text-align:center;padding:80px 20px">
        <p class="muted" style="font-size:15px">Sol taraftan düzenlemek istediğiniz şablonu seçin.</p>
      </div>
    <?php endif; ?>
  </div>
</div>

<?php if ($editRow): ?>
<script>
function insertVar(v) {
  var ta = document.getElementById('tmpl-body');
  if (!ta) return;
  var s = ta.selectionStart, e = ta.selectionEnd;
  ta.value = ta.value.substring(0, s) + v + ta.value.substring(e);
  ta.selectionStart = ta.selectionEnd = s + v.length;
  ta.focus();
}
</script>
<?php endif; ?>

<?php require_once __DIR__ . '/core/footer.php'; ?>
