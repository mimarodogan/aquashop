<?php
/**
 * Ürün detay görünümü.
 * core/controllers/product_detail.php tarafından sağlanan değişkenleri kullanır.
 */
include __DIR__ . "/../../includes/header.php";

// GA4 view_item — ürün detay görüntülenmesi
analytics_event('view_item', [
    'currency' => 'TRY',
    'value'    => round((float)$p['price'], 2),
    'items'    => [ analytics_ecommerce_item($p, 1) ],
]);
?>

<section style="padding:24px 0 0">
  <div class="container">
    <?php
    $crumbs = [
        ['name' => 'Anasayfa', 'url' => url('home')],
        ['name' => 'Ürünler',  'url' => url('products')],
    ];
    if (!empty($p['cat_name'])) {
        $__catSlug = $p['cat_slug'] ?? null;
        if (!$__catSlug && !empty($p['category_id'])) {
            // cat_slug controller'da çekilmediyse fallback sorgu
            $__s = db()->prepare('SELECT slug FROM categories WHERE id=?');
            $__s->execute([$p['category_id']]);
            $__catSlug = $__s->fetchColumn() ?: null;
        }
        if ($__catSlug) {
            $crumbs[] = ['name' => $p['cat_name'], 'url' => url('category', ['slug' => $__catSlug])];
        }
    }
    $crumbs[] = ['name' => $p['name'], 'url' => null];
    include __DIR__ . '/../../components/breadcrumb.php';
    ?>
  </div>
</section>

<section style="padding-top:30px">
  <div class="container product-detail">
    <div class="pd-image-col">
      <div class="pd-main" style="position:relative">
        <?php if ($gallery): ?>
          <img id="pd-mainImg" loading="eager" decoding="async" width="600" height="600" src="<?= e($gallery[0]) ?>" alt="<?= e($p['name']) ?>">
        <?php else: ?>
          <span class="ph" style="font-size:140px"><?= e(mb_substr($p['name'],0,1)) ?></span>
        <?php endif; ?>
        <!-- Favori butonu — anasayfadaki kart gibi görselin sağ üstünde -->
        <form method="post" action="<?= SITE_URL ?>/favorite-toggle.php" style="position:absolute;top:14px;right:14px;z-index:2;margin:0">
          <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
          <input type="hidden" name="id"   value="<?= (int)$p['id'] ?>">
          <input type="hidden" name="back" value="<?= e(url('product', ['slug'=>$p['slug']])) ?>">
          <?php $isFav = fav_has($p['id']); $favCnt = fav_product_count($p['id']); ?>
          <button class="fav-btn <?= $isFav?'active':'' ?>" type="submit" aria-label="<?= $isFav?'Favoriden çıkar':'Favorilere ekle' ?>">
            <?= ic('heart', '', 20) ?>
            <?php if ($favCnt > 0): ?><span class="fav-count"><?= $favCnt ?></span><?php endif; ?>
          </button>
        </form>
      </div>
      <?php if (count($gallery) > 1): ?>
        <div class="pd-thumbs">
          <?php foreach ($gallery as $i=>$g): ?>
            <button type="button" class="pd-thumb <?= $i===0?'active':'' ?>" data-src="<?= e($g) ?>" aria-label="Görsel <?= $i+1 ?>">
              <img loading="lazy" decoding="async" width="120" height="120" src="<?= e($g) ?>" alt="<?= e($p['name']) ?> <?= $i+1 ?>">
            </button>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>

    <div class="pd-info">
      <span class="kicker"><?= e($p['cat_name'] ?? 'Ürün') ?><?php if (!empty($p['brand'])): ?> · <?= e($p['brand']) ?><?php endif; ?></span>
      <h1 class="pd-title" style="margin:14px 0 18px;font-family:'Playfair Display',serif;font-size:34px;font-weight:500;line-height:1.15"><?= e($p['name']) ?></h1>
      <?php if (!empty($p['short_desc'])): ?>
        <p style="color:var(--champagne);font-size:16px;margin-bottom:24px"><?= e($p['short_desc']) ?></p>
      <?php endif; ?>

      <?php
        require_once __DIR__ . '/../../includes/pricing.php';
        require_once __DIR__ . '/../../includes/variations.php';
        $hasVar = product_has_variations((int)$p['id']);
        $variations = $hasVar ? product_variations((int)$p['id']) : [];
        // Görüntülenecek başlangıç fiyat/eski fiyat — varyasyonluysa ilk varyasyon
        $displayPrice = $p['price']; $displayOld = $p['old_price'] ?? null;
        $displayStock = (int)$p['stock'];
        if ($hasVar && $variations) {
            $first = $variations[0];
            $displayPrice = $first['price'];
            $displayOld   = $first['old_price'];
            $displayStock = (int)$first['stock'];
        }
      ?>
      <?php $priceOnRequest = !empty($p['price_on_request']); ?>
      <?php /* Fiyat: normal üründe her zaman; "İletişime Geçin" üründe fiyat girilmişse göster */ ?>
      <?php $showPrice = !$priceOnRequest || (float)$displayPrice > 0; ?>
      <?php if ($showPrice): ?>
      <div class="pd-price-row">
        <span class="pd-price" id="pd-price"><?= money($displayPrice) ?></span>
        <span class="pd-oldprice" id="pd-oldprice" style="<?= empty($displayOld) ? 'display:none' : '' ?>"><?= $displayOld ? money($displayOld) : '' ?></span>
        <span class="pd-discount" id="pd-discount" style="<?= (empty($displayOld) || $displayOld <= $displayPrice) ? 'display:none' : '' ?>">%<?= $displayOld && $displayOld > $displayPrice ? max(0, round((1 - $displayPrice/$displayOld) * 100)) : 0 ?></span>
      </div>
      <p class="muted" style="font-size:12px;margin:-4px 0 0;letter-spacing:.05em"><?= e(vat_label()) ?></p>
      <?php endif; ?>

      <?php if (!$priceOnRequest): ?>
      <?php /* Düşük stok aciliyeti — varyasyon değişirse JS ile güncellenir */ ?>
      <div id="pd-stock-badge" data-threshold="<?= (int)low_stock_threshold() ?>"><?= stock_badge_html($displayStock) ?></div>

      <?php /* Stok rezervasyonu — başkalarının sepetinde bekleyen miktar */ ?>
      <?php if (function_exists('reservation_enabled') && reservation_enabled()):
        $__reserved = reserved_qty_for_product((int)$p['id']);
        if ($__reserved > 0): ?>
          <p style="margin-top:8px;font-size:12px;color:#a07000;display:inline-flex;align-items:center;gap:4px">⏳ Şu an <strong><?= (int)$__reserved ?></strong> adet başka sepetlerde rezerve</p>
      <?php endif; endif; ?>

      <?php /* Sosyal kanıt: son 24sa satış + şu an inceleyen sayısı */ ?>
      <?= social_proof_html((int)$p['id']) ?>
      <?php else: ?>
      <?php /* Online satışa kapalı ürün — fiyat (varsa) yukarıda gösterildi, burada bilgilendirme */ ?>
      <div class="pd-contact-price" style="margin:18px 0 6px;padding:16px 20px;background:var(--cream);border:1px solid var(--gold-border);border-radius:var(--radius)">
        <p style="margin:0 0 4px;font-size:13px;color:var(--muted-text);letter-spacing:.08em">📞 Bu ürün online satışa kapalıdır</p>
        <p style="margin:0;font-size:15px;color:var(--ink);font-weight:600">Fiyat ve sipariş için bizimle iletişime geçin</p>
      </div>
      <?php endif; ?>

      <?php require_once __DIR__ . '/../../includes/reviews.php'; $rs = reviews_summary((int)$p['id']); ?>
      <?php if ($rs['count'] > 0): ?>
        <div style="display:flex;align-items:center;gap:10px;margin-top:14px">
          <?= star_html((float)$rs['avg'], 16) ?>
          <span style="font-size:13px;color:var(--muted-text)"><strong style="color:var(--ink)"><?= number_format($rs['avg'], 1, ',', '') ?></strong> · <a href="#yorumlar" style="color:var(--leaf);text-decoration:underline"><?= (int)$rs['count'] ?> değerlendirme</a></span>
        </div>
      <?php endif; ?>

      <?php if (!empty($p['sku'])): ?>
        <p class="muted" style="font-size:13px;margin:18px 0 6px">Stok Kodu: <span style="font-family:monospace;color:var(--champagne)"><?= e($p['sku']) ?></span></p>
      <?php endif; ?>

      <?php if ($hasVar && $variations): ?>
      <div class="variation-picker" style="margin:18px 0 0">
        <label style="font-size:11px;letter-spacing:.22em;text-transform:uppercase;color:var(--ink);font-weight:600;display:block;margin-bottom:10px">Seçenek</label>
        <div style="display:flex;flex-wrap:wrap;gap:8px">
          <?php foreach ($variations as $i => $v): ?>
            <button type="button" class="variation-chip <?= $i===0?'active':'' ?>"
              data-variant="<?= (int)$v['id'] ?>"
              data-price="<?= e($v['price']) ?>"
              data-old="<?= e($v['old_price'] ?? '') ?>"
              data-stock="<?= (int)$v['stock'] ?>"
              data-image="<?= e($v['image'] ?? '') ?>"
              style="padding:10px 16px;border:2px solid <?= $i===0?'var(--ink)':'var(--gold-border)' ?>;background:<?= $i===0?'var(--ink)':'var(--olive-2)' ?>;color:<?= $i===0?'var(--on-dark)':'var(--ink)' ?>;border-radius:var(--radius);font-size:14px;font-weight:500;cursor:pointer;transition:all var(--t);<?= (int)$v['stock']<=0?'opacity:.5;text-decoration:line-through':'' ?>"
              <?= (int)$v['stock']<=0?'disabled':'' ?>>
              <?= e($v['label']) ?>
            </button>
          <?php endforeach; ?>
        </div>
      </div>
      <?php endif; ?>

      <?php $stockForUI = $hasVar && $variations ? (int)$variations[0]['stock'] : (int)$p['stock']; ?>
      <?php if ($priceOnRequest): ?>
      <div class="cart-actions" style="margin-top:18px">
        <a href="<?= url('contact') ?>" class="btn btn-primary" style="display:inline-flex;align-items:center;gap:10px;text-decoration:none">
          <?= ic('phone', '', 18) ?>
          İletişime Geçin
        </a>
      </div>
      <?php elseif ($stockForUI > 0 || $hasVar): ?>
      <div class="cart-actions" style="margin-top:18px">
        <form method="post" class="add-form" id="add-form" data-product-id="<?= (int)$p['id'] ?>">
          <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
          <input type="hidden" name="product_id" value="<?= (int)$p['id'] ?>">
          <?php if ($hasVar && $variations): ?>
            <input type="hidden" name="variant_id" id="selected-variant" value="<?= (int)$variations[0]['id'] ?>">
          <?php endif; ?>
          <div class="qty-stepper" data-min="1" data-max="<?= $stockForUI ?>" id="qty-stepper">
            <button type="button" class="qty-btn" data-step="-1" aria-label="Azalt">−</button>
            <input type="number" name="qty" value="1" min="1" max="<?= $stockForUI ?>" inputmode="numeric" id="qty-input" aria-label="Ürün adedi">
            <button type="button" class="qty-btn" data-step="1" aria-label="Artır">+</button>
          </div>
          <button class="btn btn-primary add-btn" type="submit" id="add-btn" <?= $stockForUI<=0?'disabled':'' ?>><?= $stockForUI<=0?'Stokta Yok':'Sepete Ekle →' ?></button>
        </form>
      </div>
      <?php else: ?>
      <div class="out-of-stock" style="margin-top:18px;padding:18px;border:1px solid var(--gold-border);border-radius:var(--radius);background:var(--cream)">
        <p style="font-weight:600;color:var(--ink);margin:0 0 6px;font-size:15px">Şu anda stokta yok</p>
        <?php $oos_user = current_user(); ?>
        <?php if ($oos_user): ?>
          <?php
          // Üye zaten bu ürün için bildirim kaydı var mı?
          $alreadyNotify = false;
          try {
              $chk = db()->prepare('SELECT 1 FROM restock_notifications WHERE product_id=? AND email=? AND notified_at IS NULL LIMIT 1');
              $chk->execute([(int)$p['id'], $oos_user['email']]);
              $alreadyNotify = (bool)$chk->fetch();
          } catch (Exception $e) {}
          ?>
          <?php if ($alreadyNotify): ?>
            <p class="muted" style="font-size:13px;margin:0">
              ✓ Stok geldiğinde <strong><?= e($oos_user['email']) ?></strong> adresine bildirim gönderilecek.
            </p>
          <?php else: ?>
            <p class="muted" style="font-size:13px;margin:0 0 12px">Stoka girdiğinde sizi haberdar edelim.</p>
            <form method="post" action="<?= SITE_URL ?>/restock-notify.php">
              <input type="hidden" name="csrf"       value="<?= e(csrf_token()) ?>">
              <input type="hidden" name="product_id" value="<?= (int)$p['id'] ?>">
              <input type="hidden" name="email"      value="<?= e($oos_user['email']) ?>">
              <button class="btn btn-primary btn-sm" type="submit">Haber Ver</button>
            </form>
          <?php endif; ?>
        <?php else: ?>
          <p class="muted" style="font-size:13px;margin:0 0 12px">Bu ürün stoka geldiğinde size e-posta ile haber verelim.</p>
          <form method="post" action="<?= SITE_URL ?>/restock-notify.php" style="display:flex;gap:8px;flex-wrap:wrap">
            <input type="hidden" name="csrf"       value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="product_id" value="<?= (int)$p['id'] ?>">
            <input type="email"  name="email"      required placeholder="ornek@eposta.com"
                   style="flex:1;min-width:200px;padding:11px 14px;border:1px solid var(--field-border);border-radius:var(--radius);background:var(--olive-2);color:var(--ink);font-size:14px">
            <button class="btn btn-primary btn-sm" type="submit">Haber Ver</button>
          </form>
        <?php endif; ?>
      </div>
      <?php endif; ?>

      <?php
        // Hızlı satın al butonu — sadece stok varsa
        $hasAnyStock = $hasVar
            ? (function() use ($variations) { foreach ($variations as $v) if ((int)$v['stock'] > 0) return true; return false; })()
            : ((int)$p['stock'] > 0);
      ?>
      <?php if ($hasAnyStock && !$priceOnRequest): ?>
      <form method="post" action="<?= SITE_URL ?>/satin-al.php" style="margin-top:10px">
        <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
        <input type="hidden" name="id"   value="<?= (int)$p['id'] ?>">
        <input type="hidden" name="qty"  value="1" id="buyQty">
        <button class="btn btn-secondary" type="submit" style="width:100%;height:48px;font-weight:600;letter-spacing:.18em">SATIN AL — KREDİ KARTIYLA →</button>
      </form>
      <?php endif; ?>

      <!-- Sosyal medya paylaşımı -->
      <div class="pd-share">
        <span class="muted" style="font-size:12px;letter-spacing:.18em;text-transform:uppercase">Paylaş</span>
        <a class="pd-share-btn" data-brand="whatsapp" target="_blank" rel="noopener" aria-label="WhatsApp" href="https://api.whatsapp.com/send?text=<?= rawurlencode($p['name'].' — '.$productUrl) ?>"><?= ic('whatsapp', '', 18) ?></a>
        <a class="pd-share-btn" data-brand="facebook" target="_blank" rel="noopener" aria-label="Facebook" href="https://www.facebook.com/sharer/sharer.php?u=<?= rawurlencode($productUrl) ?>"><?= ic('facebook', '', 18) ?></a>
        <a class="pd-share-btn" data-brand="twitter-x" target="_blank" rel="noopener" aria-label="X / Twitter" href="https://twitter.com/intent/tweet?url=<?= rawurlencode($productUrl) ?>&text=<?= rawurlencode($p['name']) ?>"><?= ic('twitter-x', '', 18) ?></a>
        <a class="pd-share-btn" data-brand="linkedin" target="_blank" rel="noopener" aria-label="LinkedIn" href="https://www.linkedin.com/sharing/share-offsite/?url=<?= rawurlencode($productUrl) ?>"><?= ic('linkedin', '', 18) ?></a>
        <a class="pd-share-btn" data-brand="email" target="_blank" rel="noopener" aria-label="E-posta" href="mailto:?subject=<?= rawurlencode($p['name']) ?>&body=<?= rawurlencode($productUrl) ?>"><?= ic('mail', '', 18) ?></a>
        <button type="button" class="pd-share-btn" data-brand="copy" id="copy-link" aria-label="Linki kopyala"><?= ic('link', '', 18) ?></button>
      </div>
    </div>
  </div>
</section>

<!-- Açıklama: tam genişlik -->
<section style="padding-top:32px">
  <div class="container">
    <div class="panel">
      <h2 style="font-size:20px;margin-bottom:12px;color:var(--gold);font-family:inherit;font-weight:600">Açıklama</h2>
      <div class="prose" style="color:var(--champagne);font-size:15px;line-height:1.85"><?php
        $__desc = $p['description'] ?? '';
        if ($__desc !== '' && strip_tags($__desc) !== $__desc) {
          // Zengin metin (HTML) — admin tarafından girilmiş, izin verilen etiketleri yayınla
          // O-9 GÜVENLİK: <a> whitelist'te ama href filtresi yoktu. sanitize_html() javascript: ve on*'u sıyırır.
          $__allowed = strip_tags($__desc, '<p><br><strong><b><em><i><u><s><ol><ul><li><h2><h3><h4><blockquote><a>');
          echo sanitize_html($__allowed);
        } else {
          // Eski düz metin kayıtları
          echo nl2br(e($__desc));
        }
      ?></div>
    </div>
  </div>
</section>

<?php if ($productFaqs): ?>
<section style="padding-top:32px">
  <div class="container">
    <div class="panel">
      <h2 style="font-size:20px;margin-bottom:14px;color:var(--gold);font-family:inherit;font-weight:600">Sık Sorulan Sorular</h2>
      <div class="pd-faqs">
        <?php foreach ($productFaqs as $i=>$f): ?>
          <details class="pd-faq" <?= $i===0?'open':'' ?>>
            <summary><?= e($f['question']) ?></summary>
            <div class="pd-faq-a"><?= nl2br(e($f['answer'])) ?></div>
          </details>
        <?php endforeach; ?>
      </div>
    </div>
  </div>
</section>
<?php endif; ?>

<section id="yorumlar" style="padding-top:32px">
  <div class="container">
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

<!-- ════════════════════════════════════════════════
     Faz 7.C: Ürün Soru & Cevap
     ════════════════════════════════════════════════ -->
<?php
$productQuestions = db()->prepare(
    "SELECT * FROM product_questions
      WHERE product_id=? AND is_approved=1
      ORDER BY (answer IS NOT NULL) DESC, upvotes DESC, created_at DESC
      LIMIT 20"
);
$productQuestions->execute([(int)$p['id']]);
$productQuestions = $productQuestions->fetchAll();
?>
<section id="sorular" style="padding-top:32px">
  <div class="container">
    <div class="panel">
      <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;flex-wrap:wrap;gap:12px">
        <h2 style="font-size:20px;margin:0;font-family:inherit;font-weight:600">Soru &amp; Cevap</h2>
        <span style="font-size:14px;color:var(--champagne)"><?= count($productQuestions) ?> soru</span>
      </div>

      <?php if ($productQuestions): ?>
        <div style="display:grid;gap:16px;margin-bottom:28px">
          <?php foreach ($productQuestions as $pq): ?>
            <div style="border:1px solid var(--gold-border);border-radius:var(--radius);overflow:hidden">
              <!-- Soru -->
              <div style="padding:14px 18px;background:var(--olive-2)">
                <div style="display:flex;gap:10px;align-items:flex-start">
                  <span style="font-size:18px;line-height:1;flex-shrink:0;margin-top:1px">❓</span>
                  <div style="flex:1;min-width:0">
                    <p style="margin:0;font-size:15px;color:var(--ink);font-weight:500"><?= nl2br(e($pq['question'])) ?></p>
                    <p style="margin:4px 0 0;font-size:12px;color:var(--muted-text)"><?= e($pq['asker_name']) ?> · <?= e(date('d.m.Y', strtotime($pq['created_at']))) ?></p>
                  </div>
                </div>
              </div>
              <!-- Cevap -->
              <?php if ($pq['answer']): ?>
                <div style="padding:14px 18px;border-top:1px solid var(--gold-border);background:var(--cream)">
                  <div style="display:flex;gap:10px;align-items:flex-start">
                    <span style="font-size:18px;line-height:1;flex-shrink:0;margin-top:1px">💡</span>
                    <div>
                      <p style="margin:0;font-size:14px;color:var(--ink);line-height:1.65"><?= nl2br(e($pq['answer'])) ?></p>
                      <p style="margin:4px 0 0;font-size:12px;color:var(--leaf);font-weight:600">Mağaza Cevabı <?php if ($pq['answered_at']): ?>· <?= e(date('d.m.Y', strtotime($pq['answered_at']))) ?><?php endif; ?></p>
                    </div>
                  </div>
                </div>
              <?php else: ?>
                <div style="padding:10px 18px;border-top:1px solid var(--gold-border);background:var(--cream)">
                  <p style="margin:0;font-size:13px;color:var(--muted-text);font-style:italic">⏳ Henüz cevaplanmadı. Yakında yanıtlanacak.</p>
                </div>
              <?php endif; ?>
            </div>
          <?php endforeach; ?>
        </div>
      <?php else: ?>
        <p class="muted" style="margin-bottom:20px">Henüz soru sorulmamış. İlk soruyu siz sorun!</p>
      <?php endif; ?>

      <!-- Soru gönderme formu -->
      <div style="border-top:1px solid var(--gold-border);padding-top:20px">
        <h4 style="font-family:'Inter',sans-serif;font-size:13px;letter-spacing:.18em;text-transform:uppercase;color:var(--ink);margin:0 0 14px;font-weight:600">Soru Sor</h4>
        <?php
        $cu2  = $cu ?? current_user();
        $qTs  = time();
        $qTt  = $qTs . '|' . hash_hmac('sha256', 'q:' . $qTs, session_id() . 'q');
        ?>
        <form method="post" action="<?= SITE_URL ?>/question-submit.php" style="display:grid;gap:12px;max-width:580px">
          <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
          <input type="hidden" name="product_id" value="<?= (int)$p['id'] ?>">
          <input type="hidden" name="back" value="<?= e(url('product', ['slug'=>$p['slug']])) ?>">
          <input type="hidden" name="q_tt" value="<?= e($qTt) ?>">
          <?php /* Honeypot — gerçek kullanıcıya görünmez, botlar doldurur */ ?>
          <div aria-hidden="true" style="position:absolute;left:-9999px;width:1px;height:1px;overflow:hidden">
            <label for="q_url_hp">URL (boş bırakın)</label>
            <input type="text" id="q_url_hp" name="q_url" value="" autocomplete="off" tabindex="-1">
          </div>
          <?php if (!$cu2): ?>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px">
              <div class="field" style="margin:0">
                <label for="asker_name">Adınız <span class="req">*</span></label>
                <input id="asker_name" name="asker_name" required maxlength="120" placeholder="Adınız">
              </div>
              <div class="field" style="margin:0">
                <label for="asker_email">E-posta (opsiyonel)</label>
                <input id="asker_email" type="email" name="asker_email" maxlength="190" placeholder="cevap@mail.com">
              </div>
            </div>
          <?php endif; ?>
          <div class="field" style="margin:0">
            <label for="asker_question">Sorunuz <span class="req">*</span></label>
            <textarea id="asker_question" name="question" rows="3" required minlength="10" maxlength="1000"
                      placeholder="Ürün hakkında merak ettiğiniz bir şey mi var?"></textarea>
          </div>
          <div>
            <button class="btn btn-secondary">Soruyu Gönder</button>
            <small class="muted" style="margin-left:10px">Sorular onay sonrası yayında görünür.</small>
          </div>
        </form>
      </div>
    </div>
  </div>
</section>

<?php if ($related): ?>
<section>
  <div class="container">
    <div class="section-head">
      <span class="kicker">Bunlar da İlginizi Çekebilir</span>
      <h2>Benzer Ürünler</h2>
    </div>
    <div class="grid">
      <?php foreach ($related as $r): ?>
        <a class="card" href="<?= e(url('product', ['slug'=>$r['slug']])) ?>">
          <div class="card-img">
            <?php if (!empty($r['image'])): ?>
              <img loading="lazy" decoding="async" src="<?= e($r['image']) ?>" alt="<?= e($r['name']) ?>" style="width:100%;height:100%;object-fit:cover">
            <?php else: ?>
              <span class="ph"><?= e(mb_substr($r['name'],0,1)) ?></span>
            <?php endif; ?>
          </div>
          <div class="card-body"><span class="cat">İlgili</span><h3><?= e($r['name']) ?></h3>
            <div class="card-foot"><span class="price"><?= money($r['price']) ?></span></div>
          </div>
        </a>
      <?php endforeach; ?>
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
  // DataTransfer ile gerçek file input'u güncelle
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

  // Drop zone güncelle
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

var lbGroups = {};   // { groupId: [{src, alt},...] }
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

/* ── 3) Soru formu karakter sayacı ─────────────────────── */
var qArea = document.querySelector('textarea[name="question"]');
if (qArea) {
  var counter = document.createElement('small');
  counter.style.cssText = 'color:var(--muted-text);font-size:11px';
  qArea.insertAdjacentElement('afterend', counter);
  var updateCnt = function(){
    var len = qArea.value.length;
    counter.textContent = len + ' / 1000 karakter' + (len < 10 ? ' (en az 10)' : '');
    counter.style.color = len < 10 ? '#c0392b' : 'var(--muted-text)';
  };
  qArea.addEventListener('input', updateCnt);
  updateCnt();
}

})();
</script>
