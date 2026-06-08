<?php
$page  = 'tools_reset';
$title = 'İçerik Sıfırlama';
require_once __DIR__ . '/../core/auth.php';

// Sadece super admin (id=1) erişebilir
if ((int)$ADMIN['id'] !== 1) {
    flash_set('err', 'Bu araca sadece süper yönetici erişebilir.');
    redirect('../dashboard.php');
}

$done    = [];
$errors  = [];
$confirm = ($_POST['confirm_phrase'] ?? '') === 'SIFIRLA';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf_check($_POST['csrf'] ?? null) && $confirm) {

    $groups = $_POST['groups'] ?? [];
    $pdo    = db();

    // FK kısıtlamalarını geçici kapat
    $pdo->exec('SET FOREIGN_KEY_CHECKS = 0');

    try {

        /* ── SİPARİŞLER ──────────────────────────────────────── */
        if (in_array('orders', $groups)) {
            $pdo->exec('TRUNCATE TABLE order_items');
            $pdo->exec('TRUNCATE TABLE orders');
            $pdo->exec('TRUNCATE TABLE abandoned_carts');
            $done[] = 'Siparişler ve terk edilmiş sepetler silindi';
        }

        /* ── BLOG ────────────────────────────────────────────── */
        if (in_array('blog', $groups)) {
            $pdo->exec('TRUNCATE TABLE blog_posts');
            $pdo->exec('TRUNCATE TABLE blog_post_faqs');
            $pdo->exec('TRUNCATE TABLE comments');
            $pdo->exec('TRUNCATE TABLE wp_blog_import_queue');
            // Blog kategorilerini sıfırla ama tabloya dokunma (isteğe bağlı)
            $done[] = 'Blog yazıları, yorumlar ve taslaklar silindi';
        }

        /* ── ÜRÜNLER ─────────────────────────────────────────── */
        if (in_array('products', $groups)) {
            $pdo->exec('TRUNCATE TABLE product_images');
            $pdo->exec('TRUNCATE TABLE product_faqs');
            $pdo->exec('TRUNCATE TABLE favorites');
            $pdo->exec('TRUNCATE TABLE restock_notifications');
            try { $pdo->exec('TRUNCATE TABLE stock_movements'); } catch (\Throwable $e) {}
            // Varyasyonlar
            try { $pdo->exec('TRUNCATE TABLE product_variations'); } catch (\Throwable $e) {}
            $pdo->exec('TRUNCATE TABLE products');
            $done[] = 'Ürünler, görseller, favoriler ve stok hareketleri silindi';
        }

        /* ── KATEGORİLER ─────────────────────────────────────── */
        if (in_array('categories', $groups)) {
            $pdo->exec('TRUNCATE TABLE categories');
            try { $pdo->exec('TRUNCATE TABLE blog_categories'); } catch (\Throwable $e) {}
            $done[] = 'Kategoriler silindi';
        }

        /* ── MÜŞTERİLER ──────────────────────────────────────── */
        if (in_array('customers', $groups)) {
            // Admin hesabını koru
            $adminId = (int)$ADMIN['id'];
            $pdo->exec("DELETE FROM users WHERE id != $adminId AND role != 'admin'");
            $pdo->exec('TRUNCATE TABLE contact_messages');
            $pdo->exec('TRUNCATE TABLE newsletter_subscribers');
            $pdo->exec('TRUNCATE TABLE newsletter_campaigns');
            $done[] = 'Müşteriler ve mesajlar silindi (admin hesabı korundu)';
        }

        /* ── YORUMLAR & DEĞERLENDİRMELER ────────────────────── */
        if (in_array('reviews', $groups)) {
            try { $pdo->exec('TRUNCATE TABLE reviews'); } catch (\Throwable $e) {}
            $pdo->exec('TRUNCATE TABLE comments');
            $done[] = 'Ürün değerlendirmeleri ve blog yorumları silindi';
        }

        /* ── SEO & YÖNLENDİRMELER ───────────────────────────── */
        if (in_array('seo', $groups)) {
            $pdo->exec('TRUNCATE TABLE seo_settings');
            try { $pdo->exec('TRUNCATE TABLE redirects'); } catch (\Throwable $e) {}
            $done[] = 'SEO kayıtları ve yönlendirmeler silindi';
        }

        /* ── MEDYA ───────────────────────────────────────────── */
        if (in_array('media', $groups)) {
            // DB kayıtları
            $pdo->exec('TRUNCATE TABLE media');
            // Fiziksel dosyalar
            $uploadDir = APP_ROOT . '/assets/uploads/';
            $deleted = 0;
            if (is_dir($uploadDir)) {
                $files = new \RecursiveIteratorIterator(
                    new \RecursiveDirectoryIterator($uploadDir, \FilesystemIterator::SKIP_DOTS),
                    \RecursiveIteratorIterator::CHILD_FIRST
                );
                foreach ($files as $f) {
                    if ($f->isFile() && !in_array($f->getFilename(), ['.gitkeep','.htaccess'])) {
                        @unlink($f->getPathname());
                        $deleted++;
                    }
                }
            }
            $done[] = "Medya kütüphanesi ve $deleted fiziksel dosya silindi";
        }

    } catch (\Throwable $e) {
        $errors[] = 'Hata: ' . $e->getMessage();
    }

    $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');

    if ($done) {
        flash_set('ok', implode(' · ', $done));
    }
    if ($errors) {
        flash_set('err', implode(' ', $errors));
    }
    redirect('reset.php');
}

require_once __DIR__ . '/../core/header.php';
?>

<style>
.reset-warning {
    background: #fff3cd;
    border: 2px solid #ffc107;
    border-radius: 10px;
    padding: 18px 22px;
    margin-bottom: 24px;
    display: flex;
    gap: 14px;
    align-items: flex-start;
}
.reset-warning-icon { font-size: 28px; flex-shrink: 0; }
.reset-warning h3 { margin: 0 0 4px; color: #856404; font-size: 15px; }
.reset-warning p  { margin: 0; color: #856404; font-size: 13px; }

.reset-groups {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
    gap: 14px;
    margin: 20px 0;
}
.reset-group {
    border: 1px solid var(--gold-border);
    border-radius: 10px;
    padding: 16px 18px;
    background: var(--olive-2);
    cursor: pointer;
    transition: border-color .15s, background .15s;
    display: flex;
    gap: 12px;
    align-items: flex-start;
}
.reset-group:hover { border-color: var(--gold); background: var(--cream); }
.reset-group input[type=checkbox] { margin-top: 3px; accent-color: #c0392b; width: 16px; height: 16px; flex-shrink: 0; }
.reset-group-info h4 { margin: 0 0 4px; font-size: 14px; color: var(--ink); }
.reset-group-info p  { margin: 0; font-size: 12px; color: var(--muted-text); line-height: 1.5; }
.reset-group.danger { border-color: rgba(192,57,43,.3); background: rgba(192,57,43,.03); }
.reset-group.danger h4 { color: #c0392b; }

.reset-confirm-row {
    background: var(--cream);
    border: 1px solid var(--gold-border);
    border-radius: 10px;
    padding: 18px;
    margin-top: 20px;
}
.reset-confirm-row label { font-size: 13px; color: var(--ink); font-weight: 600; display: block; margin-bottom: 8px; }
.reset-confirm-row input {
    width: 100%;
    padding: 12px 14px;
    border: 2px solid var(--gold-border);
    border-radius: 8px;
    font-size: 16px;
    font-family: monospace;
    letter-spacing: .1em;
    color: var(--ink);
    background: #fff;
    box-sizing: border-box;
    transition: border-color .15s;
}
.reset-confirm-row input:focus { outline: none; border-color: #c0392b; }
.reset-confirm-row input.valid { border-color: #27ae60; }

.reset-submit-btn {
    margin-top: 16px;
    padding: 14px 28px;
    background: #c0392b;
    color: #fff;
    border: none;
    border-radius: 8px;
    font-size: 15px;
    font-weight: 700;
    cursor: pointer;
    letter-spacing: .08em;
    opacity: .4;
    pointer-events: none;
    transition: opacity .2s;
}
.reset-submit-btn.ready { opacity: 1; pointer-events: auto; }
.reset-submit-btn.ready:hover { background: #96281b; }
</style>

<?php if (!$_POST): ?>
<div class="reset-warning">
  <div class="reset-warning-icon">⚠️</div>
  <div>
    <h3>Bu işlem geri alınamaz!</h3>
    <p>Seçtiğiniz içerikler kalıcı olarak silinir. Devam etmeden önce veritabanı yedeği alın.<br>
       <strong>Sistem ayarları ve admin hesabı hiçbir zaman silinmez.</strong></p>
  </div>
</div>
<?php endif; ?>

<div class="panel">
  <h3 style="color:#c0392b;margin-bottom:6px">🗑️ İçerik Sıfırlama</h3>
  <p class="muted" style="margin-bottom:20px">Silmek istediğiniz grupları seçin, ardından onay kelimesini yazın.</p>

  <form method="post" id="resetForm">
    <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">

    <div class="reset-groups">

      <label class="reset-group">
        <input type="checkbox" name="groups[]" value="orders">
        <div class="reset-group-info">
          <h4>📦 Siparişler</h4>
          <p>Tüm siparişler, sipariş kalemleri ve terk edilmiş sepetler</p>
        </div>
      </label>

      <label class="reset-group">
        <input type="checkbox" name="groups[]" value="blog">
        <div class="reset-group-info">
          <h4>📝 Blog Yazıları</h4>
          <p>Blog yazıları, yorumlar ve WordPress içe aktarım kuyruğu</p>
        </div>
      </label>

      <label class="reset-group">
        <input type="checkbox" name="groups[]" value="reviews">
        <div class="reset-group-info">
          <h4>⭐ Değerlendirmeler</h4>
          <p>Ürün yorumları ve değerlendirmeler</p>
        </div>
      </label>

      <label class="reset-group">
        <input type="checkbox" name="groups[]" value="customers">
        <div class="reset-group-info">
          <h4>👥 Müşteriler</h4>
          <p>Üye hesapları (admin korunur), iletişim mesajları, bülten aboneleri</p>
        </div>
      </label>

      <label class="reset-group">
        <input type="checkbox" name="groups[]" value="seo">
        <div class="reset-group-info">
          <h4>🔍 SEO & Yönlendirmeler</h4>
          <p>Tüm SEO kayıtları ve URL yönlendirmeleri</p>
        </div>
      </label>

      <label class="reset-group danger">
        <input type="checkbox" name="groups[]" value="products">
        <div class="reset-group-info">
          <h4>🛒 Ürünler</h4>
          <p>Tüm ürünler, favoriler, stok hareketleri, ürün görselleri</p>
        </div>
      </label>

      <label class="reset-group danger">
        <input type="checkbox" name="groups[]" value="categories">
        <div class="reset-group-info">
          <h4>🗂️ Kategoriler</h4>
          <p>Ürün ve blog kategorilerinin tamamı</p>
        </div>
      </label>

      <label class="reset-group danger">
        <input type="checkbox" name="groups[]" value="media">
        <div class="reset-group-info">
          <h4>🖼️ Medya Kütüphanesi</h4>
          <p>Tüm yüklenmiş görseller — fiziksel dosyalar da silinir!</p>
        </div>
      </label>

    </div>

    <div class="reset-confirm-row">
      <label for="confirm_phrase">Onaylamak için aşağıya <code style="background:#fee;padding:2px 6px;border-radius:4px;color:#c0392b;font-size:14px">SIFIRLA</code> yazın:</label>
      <input type="text" name="confirm_phrase" id="confirm_phrase"
             placeholder="SIFIRLA" autocomplete="off" autocorrect="off" spellcheck="false">
      <button type="submit" class="reset-submit-btn" id="resetBtn">
        🗑️ SEÇİLENLERİ SİL
      </button>
    </div>
  </form>
</div>

<script>
const inp = document.getElementById('confirm_phrase');
const btn = document.getElementById('resetBtn');
const chks = document.querySelectorAll('input[type=checkbox]');

function check() {
    const typed = inp.value.trim() === 'SIFIRLA';
    const anyChecked = [...chks].some(c => c.checked);
    inp.className = typed ? 'valid' : '';
    btn.classList.toggle('ready', typed && anyChecked);
}

inp.addEventListener('input', check);
chks.forEach(c => c.addEventListener('change', check));

document.getElementById('resetForm').addEventListener('submit', function(e) {
    const anyChecked = [...chks].some(c => c.checked);
    if (!anyChecked) { e.preventDefault(); alert('En az bir grup seçin.'); return; }
    if (inp.value.trim() !== 'SIFIRLA') { e.preventDefault(); alert('Onay kelimesini doğru yazın.'); return; }
    if (!confirm('Seçilen içerikler kalıcı olarak silinecek. Emin misiniz?')) { e.preventDefault(); }
});
</script>

<?php require_once __DIR__ . '/../core/footer.php'; ?>
