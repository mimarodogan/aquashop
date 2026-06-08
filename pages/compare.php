<?php
/**
 * Ürün Karşılaştırma sayfası.
 * Session'daki compare_ids listesindeki 2-3 ürünü yan yana gösterir.
 */
require_once __DIR__ . '/../includes/functions.php';

$page = 'compare'; $title = 'Ürün Karşılaştırma';

if (!compare_enabled()) {
    flash_set('err', 'Ürün karşılaştırma özelliği şu an aktif değil.');
    redirect(url('home'));
}

/* ── POST: ürün ekle/çıkar/temizle ──────────────────────────────── */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf_check($_POST['csrf'] ?? null)) {
    $action = $_POST['action'] ?? '';
    $pid    = (int)($_POST['product_id'] ?? 0);
    if ($action === 'add' && $pid > 0) {
        if (!compare_add($pid)) flash_set('err', 'En fazla 3 ürün karşılaştırılabilir.');
    } elseif ($action === 'remove' && $pid > 0) {
        compare_remove($pid);
    } elseif ($action === 'clear') {
        compare_clear();
    }
    $back = $_POST['back'] ?? url('compare');
    if (strpos($back, '://') === false) redirect($back); // same-origin only
    redirect(url('compare'));
}

$ids = compare_list();
$items = [];
if ($ids) {
    $in = implode(',', array_fill(0, count($ids), '?'));
    $st = db()->prepare(
        "SELECT p.*, c.name AS cat_name
         FROM products p
         LEFT JOIN categories c ON c.id = p.category_id
         WHERE p.id IN ($in) AND p.is_active = 1 AND p.deleted_at IS NULL"
    );
    $st->execute($ids);
    $items = $st->fetchAll();
    // Orijinal sırayı koru
    usort($items, fn($a, $b) => array_search($a['id'], $ids) <=> array_search($b['id'], $ids));
}

include __DIR__ . '/../includes/header.php';
?>

<section class="page-header">
  <div class="container">
    <span class="kicker">Karar Verirken</span>
    <h1 style="margin-top:10px">Ürün Karşılaştırma</h1>
    <div class="breadcrumb"><a href="<?= url('home') ?>">Anasayfa</a><span>/</span>Karşılaştırma</div>
  </div>
</section>

<section>
  <div class="container">
    <?php if (!$items): ?>
      <div class="panel center" style="padding:60px 30px;text-align:center">
        <h3>Karşılaştıracak ürün seçilmemiş</h3>
        <p class="muted" style="margin:14px 0 24px">Ürün listesindeki <strong>+ Karşılaştır</strong> butonuyla 2-3 ürün ekleyin.</p>
        <a class="btn btn-primary" href="<?= url('products') ?>">Ürünlere Göz At</a>
      </div>
    <?php else:
      // Karşılaştırma tablosunun satırları — özellik etiketi ve değer çekici
      $cols = count($items);
      $allBrands  = array_filter(array_column($items, 'brand'));
      $allCats    = array_filter(array_column($items, 'cat_name'));
      $hasReviews = false;
      $rs = [];
      foreach ($items as $it) {
          if (function_exists('reviews_summary')) {
              $rs[$it['id']] = reviews_summary((int)$it['id']);
              if (!empty($rs[$it['id']]['count'])) $hasReviews = true;
          }
      }
    ?>
      <div style="overflow-x:auto;background:var(--olive-2);border:1px solid var(--gold-border);border-radius:var(--radius-lg);box-shadow:var(--shadow-xs)">
        <table style="min-width:<?= 220 + (260 * $cols) ?>px">
          <thead>
            <tr>
              <th style="background:var(--cream);padding:18px;border-right:1px solid var(--gold-border);width:220px">
                <form method="post" style="margin:0">
                  <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                  <input type="hidden" name="action" value="clear">
                  <button class="btn btn-secondary btn-sm" type="submit">Tümünü Temizle</button>
                </form>
              </th>
              <?php foreach ($items as $p): ?>
                <th style="padding:18px;text-align:center;width:260px;border-right:1px solid var(--gold-border)">
                  <a href="<?= e(url('product', ['slug' => $p['slug']])) ?>" style="display:block">
                    <?php if (!empty($p['image'])): ?>
                      <img loading="lazy" decoding="async" src="<?= e($p['image']) ?>" alt="<?= e($p['name']) ?>" style="width:100%;max-width:180px;aspect-ratio:1;object-fit:cover;border-radius:var(--radius);margin:0 auto 12px;background:var(--cream)">
                    <?php else: ?>
                      <div style="width:100%;max-width:180px;aspect-ratio:1;background:var(--cream);margin:0 auto 12px;border-radius:var(--radius);display:grid;place-items:center;color:var(--gold);font-size:48px;font-family:'Playfair Display',serif"><?= e(mb_substr($p['name'],0,1)) ?></div>
                    <?php endif; ?>
                    <strong style="display:block;color:var(--ink);font-family:'Inter',sans-serif;font-size:14px;line-height:1.4;font-weight:600"><?= e($p['name']) ?></strong>
                  </a>
                  <form method="post" style="margin:8px 0 0">
                    <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                    <input type="hidden" name="action" value="remove">
                    <input type="hidden" name="product_id" value="<?= (int)$p['id'] ?>">
                    <button type="submit" class="btn btn-secondary btn-sm" style="font-size:10px;padding:4px 10px;min-height:30px">✕ Çıkar</button>
                  </form>
                </th>
              <?php endforeach; ?>
            </tr>
          </thead>
          <tbody>
            <tr>
              <th style="text-align:left;background:var(--cream)">Fiyat</th>
              <?php foreach ($items as $p): ?>
                <td style="text-align:center;font-family:'Playfair Display',serif;font-size:22px;font-weight:600;color:var(--ink)">
                  <?= (!empty($p['price_on_request']) && (float)($p['price'] ?? 0) <= 0) ? '<small style="font-size:12px;color:var(--muted-text)">İletişim</small>' : money($p['price']) ?>
                  <?php if (!empty($p['old_price']) && (float)$p['old_price'] > (float)$p['price']): ?>
                    <br><small style="font-size:13px;color:var(--muted-text);text-decoration:line-through;font-weight:400"><?= money($p['old_price']) ?></small>
                  <?php endif; ?>
                </td>
              <?php endforeach; ?>
            </tr>
            <?php if ($allCats): ?>
            <tr>
              <th style="text-align:left;background:var(--cream)">Kategori</th>
              <?php foreach ($items as $p): ?>
                <td style="text-align:center"><?= e($p['cat_name'] ?? '—') ?></td>
              <?php endforeach; ?>
            </tr>
            <?php endif; ?>
            <?php if ($allBrands): ?>
            <tr>
              <th style="text-align:left;background:var(--cream)">Marka</th>
              <?php foreach ($items as $p): ?>
                <td style="text-align:center"><?= e($p['brand'] ?? '—') ?></td>
              <?php endforeach; ?>
            </tr>
            <?php endif; ?>
            <tr>
              <th style="text-align:left;background:var(--cream)">Stok Durumu</th>
              <?php foreach ($items as $p): ?>
                <td style="text-align:center">
                  <?php if ((int)$p['stock'] > 10 || !empty($p['has_variations'])): ?>
                    <span style="color:var(--leaf);font-weight:600">✓ Stokta</span>
                  <?php elseif ((int)$p['stock'] > 0): ?>
                    <span style="color:#a07000;font-weight:600">⚡ Sadece <?= (int)$p['stock'] ?> kaldı</span>
                  <?php else: ?>
                    <span style="color:#9A2A2A">Stokta yok</span>
                  <?php endif; ?>
                </td>
              <?php endforeach; ?>
            </tr>
            <?php if ($hasReviews): ?>
            <tr>
              <th style="text-align:left;background:var(--cream)">Değerlendirme</th>
              <?php foreach ($items as $p): $r = $rs[$p['id']] ?? null; ?>
                <td style="text-align:center">
                  <?php if (!empty($r['count'])): ?>
                    <strong><?= number_format($r['avg'], 1, ',', '') ?></strong> / 5
                    <br><small class="muted" style="font-size:11px"><?= (int)$r['count'] ?> değerlendirme</small>
                  <?php else: ?>
                    <span class="muted">—</span>
                  <?php endif; ?>
                </td>
              <?php endforeach; ?>
            </tr>
            <?php endif; ?>
            <tr>
              <th style="text-align:left;background:var(--cream)">Kısa Açıklama</th>
              <?php foreach ($items as $p): ?>
                <td style="text-align:center;font-size:13px;color:var(--muted-text);line-height:1.5;padding:14px"><?= e($p['short_desc'] ?? '—') ?></td>
              <?php endforeach; ?>
            </tr>
            <tr>
              <th style="text-align:left;background:var(--cream)">İşlem</th>
              <?php foreach ($items as $p): ?>
                <td style="text-align:center">
                  <a class="btn btn-primary btn-sm" href="<?= e(url('product', ['slug' => $p['slug']])) ?>">İncele</a>
                </td>
              <?php endforeach; ?>
            </tr>
          </tbody>
        </table>
      </div>

      <p class="muted" style="margin-top:18px;font-size:13px;text-align:center">
        <strong>İpucu:</strong> Daha fazla ürün eklemek için ürün listesindeki <strong>+ Karşılaştır</strong> butonunu kullan (max 3).
      </p>
    <?php endif; ?>
  </div>
</section>

<?php include __DIR__ . '/../includes/footer.php'; ?>
