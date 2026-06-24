<?php
/**
 * Ürün detay görünümü — canlı aquashop.com.tr (urun-detay.css) tasarımıyla birebir.
 * core/controllers/product_detail.php tarafından sağlanan değişkenleri kullanır.
 */
include __DIR__ . "/../../includes/header.php";

// GA4 view_item — ürün detay görüntülenmesi
analytics_event('view_item', [
    'currency' => 'TRY',
    'value'    => round((float)$p['price'], 2),
    'items'    => [ analytics_ecommerce_item($p, 1) ],
]);

require_once __DIR__ . '/../../includes/pricing.php';
require_once __DIR__ . '/../../includes/variations.php';
require_once __DIR__ . '/../../includes/reviews.php';

$hasVar     = product_has_variations((int)$p['id']);
$variations = $hasVar ? product_variations((int)$p['id']) : [];

// Başlangıç fiyat/eski fiyat/stok — varyasyonluysa ilk varyasyon
$displayPrice = $p['price'];
$displayOld   = $p['old_price'] ?? null;
$displayStock = (int)$p['stock'];
if ($hasVar && $variations) {
    $first        = $variations[0];
    $displayPrice = $first['price'];
    $displayOld   = $first['old_price'];
    $displayStock = (int)$first['stock'];
}
$priceOnRequest = !empty($p['price_on_request']);
$showPrice      = !$priceOnRequest || (float)$displayPrice > 0;
$discountPct    = (!empty($displayOld) && $displayOld > $displayPrice)
                ? max(0, (int)round((1 - $displayPrice / $displayOld) * 100)) : 0;
$rs       = reviews_summary((int)$p['id']);
$isFav    = fav_has($p['id']);
$inStock  = $displayStock > 0;

// WhatsApp (admin: Ayarlar → Pazarlama)
$__waOn  = setting('whatsapp_enabled', '0') === '1';
$__waNum = preg_replace('/\D+/', '', (string)setting('whatsapp_number', ''));
$__waMsg = trim((string)setting('whatsapp_message', ''));
if ($__waMsg === '') { $__waMsg = $p['name'] . ' ürünü hakkında bilgi almak istiyorum.'; }

// Breadcrumb kategori slug'ı
$__catSlug = $p['cat_slug'] ?? null;
if (!$__catSlug && !empty($p['category_id'])) {
    $__s = db()->prepare('SELECT slug FROM categories WHERE id=?');
    $__s->execute([$p['category_id']]);
    $__catSlug = $__s->fetchColumn() ?: null;
}
?>
<section class="aq-product-detail-page">
  <div class="aq-container">
    <nav class="aq-product-breadcrumb" aria-label="Sayfa yolu">
      <a href="<?= url('home') ?>">Ana Sayfa</a>
      <i class="bi bi-chevron-right"></i>
      <a href="<?= url('products') ?>">Ürünler</a>
      <?php if (!empty($p['cat_name']) && $__catSlug): ?>
        <i class="bi bi-chevron-right"></i>
        <a href="<?= e(url('category', ['slug' => $__catSlug])) ?>"><?= e($p['cat_name']) ?></a>
      <?php endif; ?>
      <i class="bi bi-chevron-right"></i>
      <span><?= e($p['name']) ?></span>
    </nav>

    <div class="aq-product-detail-layout">
      <!-- ── Galeri ── -->
      <section class="aq-product-gallery">
        <div class="aq-product-gallery-main">
          <form method="post" action="<?= SITE_URL ?>/favorite-toggle.php" style="margin:0">
            <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="id"   value="<?= (int)$p['id'] ?>">
            <input type="hidden" name="back" value="<?= e(url('product', ['slug' => $p['slug']])) ?>">
            <button class="aq-detail-fav aq-fav-btn<?= $isFav ? ' is-active' : '' ?>" type="submit" aria-label="<?= $isFav ? 'Favoriden çıkar' : 'Favorilere ekle' ?>">
              <i class="bi bi-heart<?= $isFav ? '-fill' : '' ?>"></i>
            </button>
          </form>
          <?php if ($discountPct > 0): ?>
            <span class="aq-detail-badge">%<?= $discountPct ?> İndirim</span>
          <?php elseif (!empty($p['is_featured'])): ?>
            <span class="aq-detail-badge">Öne Çıkan</span>
          <?php endif; ?>
          <?php if ($gallery): ?>
            <img id="aqProductMainImage" loading="eager" decoding="async" src="<?= e($gallery[0]) ?>" alt="<?= e($p['name']) ?>">
          <?php else: ?>
            <img id="aqProductMainImage" loading="eager" decoding="async" src="" alt="<?= e($p['name']) ?>" style="display:none">
          <?php endif; ?>
        </div>
        <?php if (count($gallery) > 1): ?>
        <div class="aq-product-thumbs">
          <?php foreach ($gallery as $i => $g): ?>
            <button type="button" class="<?= $i === 0 ? 'is-active' : '' ?>" data-media-url="<?= e($g) ?>" aria-label="Görsel <?= $i + 1 ?>">
              <img loading="lazy" decoding="async" src="<?= e($g) ?>" alt="<?= e($p['name']) ?> <?= $i + 1 ?>">
            </button>
          <?php endforeach; ?>
        </div>
        <?php endif; ?>
      </section>

      <!-- ── Satın alma kutusu ── -->
      <section class="aq-product-summary">
        <div class="aq-product-code-row">
          <span><?= !empty($p['sku']) ? 'Ürün Kodu: ' . e($p['sku']) : '' ?></span>
          <?php if (!$priceOnRequest): ?>
          <span class="aq-stock-badge" style="<?= $inStock ? '' : 'background:#fdecec;color:#b91c1c' ?>">
            <i class="bi bi-<?= $inStock ? 'check2-circle' : 'x-circle' ?>"></i> <?= $inStock ? 'Stokta Var' : 'Stokta Yok' ?>
          </span>
          <?php endif; ?>
        </div>

        <h1><?= e($p['name']) ?></h1>

        <div class="aq-detail-rating">
          <div>
            <?php $__full = (int)floor((float)$rs['avg']); for ($i = 1; $i <= 5; $i++): ?>
              <i class="bi bi-star<?= $i <= $__full ? '-fill' : '' ?>"></i>
            <?php endfor; ?>
          </div>
          <strong><?= number_format((float)$rs['avg'], 1, '.', '') ?></strong>
          <span><?= (int)$rs['count'] ?> değerlendirme</span>
        </div>

        <?php if (!empty($p['short_desc'])): ?>
          <p class="aq-product-short-desc"><?= e($p['short_desc']) ?></p>
        <?php endif; ?>

        <?php if ($showPrice): ?>
        <div class="aq-detail-price-box">
          <div class="aq-detail-price">
            <span>Satış Fiyatı</span>
            <strong id="aqProductPrice"><?= money($displayPrice) ?></strong>
            <del id="aqProductOldPrice" style="<?= (!empty($displayOld) && $displayOld > $displayPrice) ? '' : 'display:none' ?>"><?= !empty($displayOld) ? money($displayOld) : '' ?></del>
          </div>
          <div class="aq-detail-discount" id="aqProductDiscount" style="<?= $discountPct > 0 ? '' : 'display:none' ?>">
            <strong>%<?= $discountPct ?></strong><span>İNDİRİM</span>
          </div>
        </div>
        <p style="margin:8px 2px 0;color:#9aa5b2;font-size:11px;font-weight:700"><?= e(vat_label()) ?></p>
        <?php endif; ?>

        <?php if (!$priceOnRequest && $inStock): ?>
        <div class="aq-stock-info-box aq-stock-info-under-price">
          <div><i class="bi bi-box-seam"></i><span>Kalan Stok</span></div>
          <strong id="aqProductStock"><?= $displayStock ?> adet</strong>
        </div>
        <?php endif; ?>

        <div class="aq-detail-benefits">
          <div><i class="bi bi-truck"></i><span>14:00'a kadar verilen siparişlerde hızlı kargo</span></div>
          <div><i class="bi bi-shield-check"></i><span>Güvenli ödeme ve kolay iade avantajı</span></div>
          <div><i class="bi bi-headset"></i><span>Ürün seçimi için uzman destek</span></div>
        </div>

        <?php if (!$priceOnRequest): ?>
          <?php if (function_exists('reservation_enabled') && reservation_enabled()):
            $__reserved = reserved_qty_for_product((int)$p['id']);
            if ($__reserved > 0): ?>
            <p style="margin:13px 0 0;font-size:12px;color:#a07000;display:inline-flex;align-items:center;gap:5px">⏳ Şu an <strong><?= (int)$__reserved ?></strong> adet başka sepetlerde rezerve</p>
          <?php endif; endif; ?>
          <?= social_proof_html((int)$p['id']) ?>
        <?php endif; ?>

        <?php if ($hasVar && $variations): ?>
        <div class="aq-detail-variations" style="margin-top:16px">
          <span style="display:block;color:#17202b;font-size:13px;font-weight:850;margin-bottom:9px">Seçenek</span>
          <div style="display:flex;flex-wrap:wrap;gap:8px">
            <?php foreach ($variations as $i => $v): ?>
              <button type="button" class="aq-variation-chip<?= $i === 0 ? ' is-active' : '' ?>"
                data-variant="<?= (int)$v['id'] ?>"
                data-price="<?= e($v['price']) ?>"
                data-old="<?= e($v['old_price'] ?? '') ?>"
                data-stock="<?= (int)$v['stock'] ?>"
                data-image="<?= e($v['image'] ?? '') ?>"
                <?= (int)$v['stock'] <= 0 ? 'disabled' : '' ?>><?= e($v['label']) ?></button>
            <?php endforeach; ?>
          </div>
        </div>
        <?php endif; ?>

        <?php if ($priceOnRequest): ?>
          <a href="<?= url('contact') ?>" class="aq-detail-add-cart" style="margin-top:18px;width:100%;text-decoration:none"><i class="bi bi-telephone"></i> İletişime Geçin</a>
        <?php elseif ($inStock || $hasVar): ?>
          <form method="post" id="aqProductForm">
            <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="product_id" value="<?= (int)$p['id'] ?>">
            <?php if ($hasVar && $variations): ?>
              <input type="hidden" name="variant_id" id="aqVariantId" value="<?= (int)$variations[0]['id'] ?>">
            <?php endif; ?>
            <div class="aq-quantity-area">
              <span>Adet</span>
              <div class="aq-quantity-control">
                <button type="button" class="aq-qty-minus" aria-label="Adet Azalt"><i class="bi bi-dash"></i></button>
                <input type="number" id="aqProductQty" name="qty" value="1" min="1" max="<?= max(1, $displayStock) ?>" inputmode="numeric">
                <button type="button" class="aq-qty-plus" aria-label="Adet Artır"><i class="bi bi-plus"></i></button>
              </div>
            </div>
            <div class="aq-detail-actions">
              <button class="aq-detail-add-cart" type="submit"<?= $inStock ? '' : ' disabled' ?>><i class="bi bi-cart-plus"></i> <?= $inStock ? 'Sepete Ekle' : 'Stokta Yok' ?></button>
              <button class="aq-detail-buy-now" type="submit" name="buy_now" value="1"<?= $inStock ? '' : ' disabled' ?>><i class="bi bi-lightning-charge"></i> Hemen Satın Al</button>
            </div>
          </form>
        <?php else: ?>
          <div style="margin-top:16px;padding:16px 18px;border:1px solid #e8eef3;border-radius:16px;background:#f8fbfc">
            <p style="font-weight:800;color:#111827;margin:0 0 8px;font-size:14px">Şu anda stokta yok</p>
            <?php $oos_user = current_user(); ?>
            <?php if ($oos_user): ?>
              <form method="post" action="<?= SITE_URL ?>/restock-notify.php">
                <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="product_id" value="<?= (int)$p['id'] ?>">
                <input type="hidden" name="email" value="<?= e($oos_user['email']) ?>">
                <button class="aq-detail-add-cart" type="submit" style="width:100%"><i class="bi bi-bell"></i> Stok Gelince Haber Ver</button>
              </form>
            <?php else: ?>
              <form method="post" action="<?= SITE_URL ?>/restock-notify.php" style="display:flex;gap:8px;flex-wrap:wrap">
                <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="product_id" value="<?= (int)$p['id'] ?>">
                <input type="email" name="email" required placeholder="ornek@eposta.com" style="flex:1;min-width:180px;padding:11px 14px;border:1px solid #dfe8ef;border-radius:12px;font-size:14px">
                <button class="aq-detail-add-cart" type="submit"><i class="bi bi-bell"></i> Haber Ver</button>
              </form>
            <?php endif; ?>
          </div>
        <?php endif; ?>

        <?php if ($__waOn && $__waNum !== ''): ?>
        <a class="aq-detail-whatsapp" target="_blank" rel="noopener" href="https://wa.me/<?= e($__waNum) ?>?text=<?= rawurlencode($__waMsg) ?>"><i class="bi bi-whatsapp"></i> WhatsApp'dan İletişime Geç</a>
        <?php endif; ?>

        <div class="aq-product-meta">
          <div><strong>Marka</strong><span><?= e($p['brand'] ?? '') ?: '—' ?></span></div>
          <div><strong>Kategori</strong><span><?= e($p['cat_name'] ?? 'Genel') ?></span></div>
          <div><strong>Kargo</strong><span>Hızlı gönderim</span></div>
        </div>
      </section>
    </div>
  </div>
</section>

<!-- ── Açıklama ── -->
<section class="aq-product-info-section">
  <div class="aq-container">
    <div class="aq-product-info-grid aq-product-info-grid-full">
      <article class="aq-detail-card aq-detail-description-card">
        <div class="aq-detail-card-head">
          <span>Ürün Bilgisi</span>
          <h2>Açıklama</h2>
        </div>
        <div class="aq-detail-description"><?php
          $__desc = $p['description'] ?? '';
          if ($__desc !== '' && strip_tags($__desc) !== $__desc) {
              // Zengin metin (HTML) — admin tarafından girilmiş, izin verilen etiketler
              // O-9 GÜVENLİK: <a> href filtresi yoktu. sanitize_html() javascript: ve on*'u sıyırır.
              $__allowed = strip_tags($__desc, '<p><br><strong><b><em><i><u><s><ol><ul><li><h2><h3><h4><blockquote><a>');
              echo sanitize_html($__allowed);
          } else {
              echo nl2br(e($__desc)) ?: '<p style="color:#9aa5b2">Bu ürün için henüz açıklama eklenmemiş.</p>';
          }
        ?></div>
      </article>
    </div>
  </div>
</section>

<?php if ($productFaqs): ?>
<!-- ── Sık Sorulan Sorular ── -->
<section class="aq-faq-section">
  <div class="aq-container">
    <div class="aq-detail-card-head" style="text-align:center;margin-bottom:20px">
      <span>SSS</span>
      <h2>Sık Sorulan Sorular</h2>
    </div>
    <div class="aq-faq-list">
      <?php foreach ($productFaqs as $i => $f): ?>
        <div class="aq-faq-item<?= $i === 0 ? ' is-open' : '' ?>">
          <button type="button"><?= e($f['question']) ?><i class="bi bi-chevron-down"></i></button>
          <div><p><?= nl2br(e($f['answer'])) ?></p></div>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>
<?php endif; ?>

<?php $comments = comment_list('product', $p['id']); ?>
<section id="yorumlar" style="padding-top:32px">
  <div class="aq-container">
    <div class="panel">
      <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:18px;flex-wrap:wrap;gap:12px">
        <h2 style="font-size:20px;margin:0;font-family:inherit;font-weight:600">Müşteri Değerlendirmeleri</h2>
        <?php if ($rs['count'] > 0): ?>
          <div style="display:flex;align-items:center;gap:10px">
            <?= star_html((float)$rs['avg'], 18) ?>
            <span style="font-size:14px"><strong><?= number_format($rs['avg'], 1, ',', '') ?></strong> / 5 · <?= (int)$rs['count'] ?> değerlendirme</span>
          </div>
        <?php endif; ?>
      </div>

      <?php $reviews = reviews_list((int)$p['id'], 30); ?>
      <?php if ($reviews): ?>
        <div style="display:grid;gap:18px;margin-bottom:32px">
          <?php foreach ($reviews as $rv): ?>
            <article style="padding:18px;border:1px solid var(--gold-border);border-radius:var(--radius);background:var(--olive-2)">
              <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px;gap:12px;flex-wrap:wrap">
                <div style="display:flex;align-items:center;gap:10px">
                  <strong style="color:var(--ink)"><?= e($rv['author_name']) ?></strong>
                  <?php if ($rv['is_verified_buyer']): ?>
                    <span class="chip" style="background:rgba(107,122,47,.1);color:var(--leaf);border-color:rgba(107,122,47,.3)">✓ Doğrulanmış Alıcı</span>
                  <?php endif; ?>
                </div>
                <?= star_html((float)$rv['rating'], 14) ?>
              </div>
              <?php if (!empty($rv['title'])): ?><h4 style="font-size:15px;margin:6px 0;font-family:'Inter',sans-serif"><?= e($rv['title']) ?></h4><?php endif; ?>
              <p style="color:var(--champagne);font-size:14px;line-height:1.65;margin:0"><?= nl2br(e($rv['body'])) ?></p>
              <?php
                /* ── Faz 7.E: Yorum fotoğrafları ─── */
                $rvMedia = !empty($rv['media']) ? json_decode($rv['media'], true) : [];
                if ($rvMedia):
              ?>
                <div class="rv-photos" style="display:flex;gap:8px;flex-wrap:wrap;margin-top:12px">
                  <?php foreach ($rvMedia as $idx => $photoUrl): ?>
                    <a href="<?= e(SITE_URL . $photoUrl) ?>" class="rv-photo-link"
                       data-lightbox="rv-<?= (int)$rv['id'] ?>" data-idx="<?= $idx ?>"
                       style="display:block;width:80px;height:80px;border-radius:6px;overflow:hidden;border:1px solid var(--gold-border);flex-shrink:0">
                      <img src="<?= e(SITE_URL . $photoUrl) ?>" alt="Yorum görseli <?= $idx+1 ?>"
                           style="width:100%;height:100%;object-fit:cover" loading="lazy">
                    </a>
                  <?php endforeach; ?>
                </div>
              <?php endif; ?>
              <p class="muted" style="font-size:12px;margin:8px 0 0"><?= e(date('d.m.Y', strtotime($rv['created_at']))) ?></p>
            </article>
          <?php endforeach; ?>
        </div>
      <?php else: ?>
        <p class="muted" style="margin-bottom:24px">Bu ürünün ilk değerlendirmesini siz yazın.</p>
      <?php endif; ?>

      <?php $cu = current_user(); ?>
      <?php if ($cu): ?>
      <h4 style="font-family:'Inter',sans-serif;font-size:13px;letter-spacing:.18em;text-transform:uppercase;color:var(--ink);margin:0 0 14px;font-weight:600">Yorum Yaz</h4>
      <?php
        // Zaman token: timestamp + HMAC. Bot'lar 5 saniyeden önce gönderir → reddedilir.
        $rvTs = time();
        $rvTt = $rvTs . '|' . hash_hmac('sha256', 'rv:' . $rvTs, session_id() . 'rv');
      ?>
      <form method="post" action="<?= SITE_URL ?>/review-submit.php"
            enctype="multipart/form-data" style="display:grid;gap:14px;max-width:640px">
        <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
        <input type="hidden" name="product_id" value="<?= (int)$p['id'] ?>">
        <input type="hidden" name="back" value="<?= e(url('product', ['slug'=>$p['slug']])) ?>#yorumlar">
        <input type="hidden" name="rv_tt" value="<?= e($rvTt) ?>">
        <?php /* Honeypot: insanlar görmez, botlar doldurur */ ?>
        <div aria-hidden="true" style="position:absolute;left:-9999px;width:1px;height:1px;overflow:hidden">
          <input type="text" name="rv_url" value="" autocomplete="off" tabindex="-1">
        </div>

        <div class="field">
          <label>Puanınız <span class="req" aria-hidden="true">*</span></label>
          <div class="rating-input" style="display:inline-flex;gap:4px;font-size:32px;line-height:1">
            <?php for ($i=5; $i>=1; $i--): ?>
              <input type="radio" name="rating" value="<?= $i ?>" id="r<?= $i ?>" style="display:none" required>
              <label for="r<?= $i ?>" style="cursor:pointer;color:#E5E5E5;transition:color .15s" data-star="<?= $i ?>">★</label>
            <?php endfor; ?>
          </div>
          <small class="muted">Yıldıza tıklayarak puanlayın</small>
        </div>

        <div class="field"><label>Başlık (opsiyonel)</label><input name="title" maxlength="200" placeholder="örn. Çok memnun kaldım"></div>
        <div class="field"><label>Yorumunuz <span class="req" aria-hidden="true">*</span></label><textarea name="body" rows="4" required minlength="10" placeholder="Ürün hakkındaki düşüncelerinizi paylaşın…"></textarea></div>

        <!-- Faz 7.E: Foto yükleme -->
        <div class="field">
          <label>Fotoğraf ekle (opsiyonel, max 5)</label>
          <input type="file" name="review_photos[]" id="rvPhotoInput" multiple accept="image/jpeg,image/png,image/webp"
                 style="display:none" onchange="rvPhotoPreview(this)">
          <div id="rvDropZone"
               onclick="document.getElementById('rvPhotoInput').click()"
               style="border:2px dashed var(--gold-border);border-radius:8px;padding:20px;text-align:center;cursor:pointer;transition:border-color .2s;background:var(--olive-2)">
            <span style="font-size:24px">📷</span>
            <p style="margin:6px 0 0;font-size:13px;color:var(--champagne)">Fotoğraf seç veya buraya sürükle<br><small>JPG, PNG, WEBP — en fazla 5 fotoğraf, max 5 MB/adet (video desteklenmiyor)</small></p>
          </div>
          <div id="rvPhotoPreviewRow" style="display:flex;gap:8px;flex-wrap:wrap;margin-top:10px"></div>
        </div>

        <div><button class="btn btn-primary">Yorumu Gönder</button></div>
        <small class="muted">Yorumunuz admin onayı sonrası yayında görünür.</small>
      </form>
      <?php else: ?>
      <div style="padding:22px;border:1px solid var(--gold-border);border-radius:var(--radius);background:var(--cream);text-align:center">
        <p style="margin:0 0 12px;color:var(--ink);font-size:15px">Bu ürünü değerlendirmek için giriş yapın.</p>
        <a href="<?= e(url('login')) ?>" class="btn btn-primary btn-sm">Giriş Yap</a>
        <a href="<?= e(url('register')) ?>" class="btn btn-secondary btn-sm" style="margin-left:8px">Üye Ol</a>
      </div>
      <?php endif; ?>
    </div>
  </div>
</section>

<?php if ($related): ?>
<!-- ── Benzer Ürünler ── -->
<section class="aq-related-section">
  <div class="aq-container">
    <div class="aq-detail-card-head" style="text-align:center;margin-bottom:22px">
      <span>Mağazadan</span>
      <h2>Benzer Ürünler</h2>
    </div>
    <div class="aq-product-grid aq-grid-4">
      <?php
        $favIds = fav_ids();
        $cardBack = url('product', ['slug' => $p['slug']]);
        $__mainP = $p; // product-card $p kullanır — döngü sonrası geri yükle
        foreach ($related as $r):
            $p = $r;
            include __DIR__ . '/../../components/product-card.php';
        endforeach;
        $p = $__mainP;
      ?>
    </div>
  </div>
</section>
<?php endif; ?>

<?php
/* ── Son Baktıkların — bu ürünü hariç tut ──────────────────────── */
$recentlyViewed = recently_viewed_get(8, (int)$p['id']);
if ($recentlyViewed) {
    $stripKicker = 'Geçmişinden';
    $stripTitle  = 'Son Baktıkların';
    $stripItems  = $recentlyViewed;
    $stripBg     = 'cream';
    $stripCardBack = url('product', ['slug' => $p['slug']]);
    include __DIR__ . '/../../components/product-strip.php';
}
?>

<?php include __DIR__ . "/../../includes/footer.php"; ?>

<!-- ══════════════════════════════════════════════════════════════
     Faz 7.E – Review photo preview + lightbox
     Faz 7.C – Soru formu karakter sayacı
     ══════════════════════════════════════════════════════════════ -->
<style>
/* ── Lightbox ──────────────────────────────────────────────── */
#rvLightbox {
  display:none;position:fixed;inset:0;z-index:9999;
  background:rgba(0,0,0,.9);align-items:center;justify-content:center;
}
#rvLightbox.open { display:flex; }
#rvLightbox img  { max-width:92vw;max-height:88vh;border-radius:8px;object-fit:contain; }
#rvLightbox .lb-close {
  position:absolute;top:16px;right:20px;font-size:32px;color:#fff;
  cursor:pointer;line-height:1;background:none;border:none;padding:4px;
}
#rvLightbox .lb-arrow {
  position:absolute;top:50%;transform:translateY(-50%);
  background:rgba(255,255,255,.15);border:none;color:#fff;font-size:28px;
  padding:10px 14px;border-radius:8px;cursor:pointer;transition:background .2s;
}
#rvLightbox .lb-arrow:hover { background:rgba(255,255,255,.3); }
#rvLightbox .lb-prev { left:12px; }
#rvLightbox .lb-next { right:12px; }
#rvLightbox .lb-caption {
  position:absolute;bottom:16px;left:50%;transform:translateX(-50%);
  color:rgba(255,255,255,.7);font-size:13px;background:rgba(0,0,0,.5);
  padding:4px 14px;border-radius:999px;
}

/* ── Photo upload drop zone hover ─────────────────────────── */
#rvDropZone:hover { border-color:var(--gold);background:var(--cream); }

/* ── Yorum foto thumbnail (preview) ───────────────────────── */
.rv-preview-thumb {
  position:relative;width:76px;height:76px;border-radius:6px;overflow:hidden;
  border:1px solid var(--gold-border);flex-shrink:0;cursor:pointer;
}
.rv-preview-thumb img { width:100%;height:100%;object-fit:cover; }
.rv-preview-thumb .rm {
  display:none;position:absolute;inset:0;background:rgba(0,0,0,.55);
  align-items:center;justify-content:center;color:#fff;font-size:18px;
  cursor:pointer;
}
.rv-preview-thumb:hover .rm { display:flex; }

/* ── Varyasyon çipleri ────────────────────────────────────── */
.aq-variation-chip {
  min-height:40px;padding:0 16px;border-radius:12px;
  border:1px solid #dfe8ef;background:#fff;color:#17202b;
  font-size:13px;font-weight:800;cursor:pointer;transition:all 200ms ease;
}
.aq-variation-chip.is-active { background:var(--aq-blue);border-color:var(--aq-blue);color:#fff; }
.aq-variation-chip:disabled { opacity:.45;text-decoration:line-through;cursor:not-allowed; }
</style>

<!-- Lightbox DOM -->
<div id="rvLightbox" role="dialog" aria-modal="true" aria-label="Fotoğraf önizleme">
  <button class="lb-close" id="rvLbClose" aria-label="Kapat">×</button>
  <button class="lb-arrow lb-prev" id="rvLbPrev" aria-label="Önceki">‹</button>
  <img id="rvLbImg" src="" alt="Yorum görseli">
  <button class="lb-arrow lb-next" id="rvLbNext" aria-label="Sonraki">›</button>
  <div class="lb-caption" id="rvLbCaption"></div>
</div>

<script>
(function(){
'use strict';

/* ── 1) Foto preview (yorum formu) ──────────────────────── */
var rvFiles   = [];       // DataTransfer'de tutulan gerçek File objeleri
var MAX_FILES = 5;

window.rvPhotoPreview = function(input) {
  var newFiles = Array.from(input.files);
  newFiles.forEach(function(f){
    if (rvFiles.length < MAX_FILES) rvFiles.push(f);
  });
  var dt = new DataTransfer();
  rvFiles.forEach(function(f){ dt.items.add(f); });
  input.files = dt.files;
  renderPreviews();
};

function renderPreviews() {
  var row = document.getElementById('rvPhotoPreviewRow');
  if (!row) return;
  row.innerHTML = '';
  rvFiles.forEach(function(f, idx){
    var url = URL.createObjectURL(f);
    var wrap = document.createElement('div');
    wrap.className = 'rv-preview-thumb';
    wrap.innerHTML =
      '<img src="'+url+'" alt="Önizleme '+(idx+1)+'">'
      + '<div class="rm" title="Kaldır">✕</div>';
    wrap.querySelector('.rm').addEventListener('click', function(){
      rvFiles.splice(idx, 1);
      var inp = document.getElementById('rvPhotoInput');
      var dt2 = new DataTransfer();
      rvFiles.forEach(function(ff){ dt2.items.add(ff); });
      inp.files = dt2.files;
      renderPreviews();
    });
    row.appendChild(wrap);
  });

  var dz = document.getElementById('rvDropZone');
  if (dz) {
    dz.querySelector('p').innerHTML = rvFiles.length > 0
      ? rvFiles.length + ' fotoğraf seçildi' + (rvFiles.length < MAX_FILES ? ' — daha fazla eklemek için tıkla' : '')
      : 'Fotoğraf seç veya buraya sürükle<br><small>JPG, PNG, WEBP — en fazla 5 fotoğraf, max 5 MB/adet</small>';
  }
}

/* Sürükle-bırak desteği */
var dz = document.getElementById('rvDropZone');
if (dz) {
  dz.addEventListener('dragover', function(e){ e.preventDefault(); dz.style.borderColor='var(--gold)'; });
  dz.addEventListener('dragleave', function(){ dz.style.borderColor=''; });
  dz.addEventListener('drop', function(e){
    e.preventDefault();
    dz.style.borderColor = '';
    var inp = document.getElementById('rvPhotoInput');
    var dropped = Array.from(e.dataTransfer.files).filter(function(f){ return f.type.startsWith('image/'); });
    dropped.forEach(function(f){ if (rvFiles.length < MAX_FILES) rvFiles.push(f); });
    var dt = new DataTransfer();
    rvFiles.forEach(function(f){ dt.items.add(f); });
    inp.files = dt.files;
    renderPreviews();
  });
}

/* ── 2) Lightbox (yayındaki yorum fotoğrafları) ────────── */
var lbEl   = document.getElementById('rvLightbox');
var lbImg  = document.getElementById('rvLbImg');
var lbCap  = document.getElementById('rvLbCaption');
var lbClose= document.getElementById('rvLbClose');
var lbPrev = document.getElementById('rvLbPrev');
var lbNext = document.getElementById('rvLbNext');

var lbGroups = {};
var lbCur    = { group:'', idx:0 };

document.querySelectorAll('.rv-photo-link').forEach(function(a){
  var group = a.dataset.lightbox;
  if (!lbGroups[group]) lbGroups[group] = [];
  lbGroups[group].push({ src: a.href, alt: a.querySelector('img').alt });
  a.addEventListener('click', function(e){
    e.preventDefault();
    lbCur.group = group;
    lbCur.idx   = parseInt(a.dataset.idx, 10);
    lbOpen();
  });
});

function lbOpen() {
  var items = lbGroups[lbCur.group] || [];
  var item  = items[lbCur.idx] || {};
  lbImg.src = item.src || '';
  lbImg.alt = item.alt || '';
  lbCap.textContent = (lbCur.idx + 1) + ' / ' + items.length;
  lbPrev.style.display = items.length > 1 ? '' : 'none';
  lbNext.style.display = items.length > 1 ? '' : 'none';
  lbEl.classList.add('open');
  document.body.style.overflow = 'hidden';
}

function lbClose_() {
  lbEl.classList.remove('open');
  document.body.style.overflow = '';
  lbImg.src = '';
}

lbClose.addEventListener('click', lbClose_);
lbEl.addEventListener('click', function(e){ if (e.target === lbEl) lbClose_(); });
lbPrev.addEventListener('click', function(){
  var items = lbGroups[lbCur.group] || [];
  lbCur.idx = (lbCur.idx - 1 + items.length) % items.length;
  lbOpen();
});
lbNext.addEventListener('click', function(){
  var items = lbGroups[lbCur.group] || [];
  lbCur.idx = (lbCur.idx + 1) % items.length;
  lbOpen();
});
document.addEventListener('keydown', function(e){
  if (!lbEl.classList.contains('open')) return;
  if (e.key === 'Escape') lbClose_();
  if (e.key === 'ArrowLeft')  lbPrev.click();
  if (e.key === 'ArrowRight') lbNext.click();
});

/* ── 4) SSS akordeon (canlı tasarım) ───────────────────── */
document.querySelectorAll('.aq-faq-item > button').forEach(function(btn){
  btn.addEventListener('click', function(){
    btn.parentElement.classList.toggle('is-open');
  });
});

})();
</script>
