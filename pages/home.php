<?php
require_once __DIR__ . '/../includes/functions.php';
$page = 'home';
$title = '';
$featuredLimit = max(0, min(48, (int)setting('home_featured_count', '12')));
$blogLimit     = max(0, min(12, (int)setting('home_blog_count', '4')));

$featured = array();
$cats = array();
$banners = array();
$blogPosts = array();
try {
    if ($featuredLimit > 0) {
        $st = db()->prepare("SELECT * FROM products WHERE is_active=1 AND is_featured=1 ORDER BY created_at DESC LIMIT $featuredLimit");
        $st->execute(); $featured = $st->fetchAll();
        if (count($featured) < $featuredLimit) {
            $need = $featuredLimit - count($featured);
            $exclude = array_map(function($p){ return (int)$p['id']; }, $featured) ?: array(0);
            $in = implode(',', array_fill(0, count($exclude), '?'));
            $st = db()->prepare("SELECT * FROM products WHERE is_active=1 AND id NOT IN ($in) ORDER BY created_at DESC LIMIT $need");
            $st->execute($exclude);
            $featured = array_merge($featured, $st->fetchAll());
        }
    }
    $cats    = db()->query("SELECT * FROM categories WHERE parent_id IS NULL ORDER BY sort_order ASC, name ASC")->fetchAll();
    $banners = db()->query("SELECT * FROM banners WHERE is_active=1 ORDER BY sort_order ASC, id DESC")->fetchAll();
    if ($blogLimit > 0) {
        $st = db()->prepare("SELECT p.*, c.name AS cat_name, c.slug AS cat_slug FROM blog_posts p LEFT JOIN blog_categories c ON c.id=p.category_id WHERE p.is_published=1 ORDER BY COALESCE(p.published_at,p.created_at) DESC LIMIT $blogLimit");
        $st->execute(); $blogPosts = $st->fetchAll();
    }
    $secondaryLimit = max(0, min(24, (int)setting('home_secondary_count','4')));
    $secondaryProducts = array();
    // En çok favoriye eklenenler
    $mostFavedLimit = max(0, min(24, (int)setting('home_most_faved_count','8')));
    $mostFavedProducts = array();
    if ($mostFavedLimit > 0 && setting('home_show_most_faved','1') === '1') {
        try {
            $st = db()->prepare(
                "SELECT p.*, c.name AS cat_name, COUNT(f.product_id) AS fav_count
                 FROM favorites f
                 JOIN products p ON p.id = f.product_id
                 LEFT JOIN categories c ON c.id = p.category_id
                 WHERE p.is_active = 1
                 GROUP BY f.product_id
                 ORDER BY fav_count DESC
                 LIMIT $mostFavedLimit"
            );
            $st->execute();
            $mostFavedProducts = $st->fetchAll();
        } catch (Exception $e) { $mostFavedProducts = array(); }
    }
    if ($secondaryLimit > 0) {
        $exclude = array_map(function($p){ return (int)$p['id']; }, $featured);
        if ($exclude) {
            $in = implode(',', array_fill(0, count($exclude), '?'));
            $st = db()->prepare("SELECT p.*, c.name AS cat_name FROM products p LEFT JOIN categories c ON c.id=p.category_id WHERE p.is_active=1 AND p.id NOT IN ($in) ORDER BY RAND() LIMIT $secondaryLimit");
            $st->execute($exclude);
            $secondaryProducts = $st->fetchAll();
        }
        // Hariç tutunca boş kaldıysa veya öne çıkan yoksa, tüm aktif ürünlerden çek
        if (!$secondaryProducts) {
            $st = db()->prepare("SELECT p.*, c.name AS cat_name FROM products p LEFT JOIN categories c ON c.id=p.category_id WHERE p.is_active=1 ORDER BY RAND() LIMIT $secondaryLimit");
            $st->execute();
            $secondaryProducts = $st->fetchAll();
        }
    }
} catch (Exception $e) { $secondaryProducts = array(); }

// Header'a banner preload URL'ini ilet — header.php bu değişkeni bulursa
// ayrı DB sorgusu yapmaz; URL garantili eşleşir (preload cache hit).
$__preloadBannerImg = null;
foreach ($banners as $__pb) {
    if (!empty($__pb['image'])) { $__preloadBannerImg = $__pb['image']; break; }
}
unset($__pb);

include __DIR__ . '/../includes/header.php';
$siteName = setting('site_name', 'AquaShop');
$siteTagline = trim((string)setting('site_tagline', ''));
?>

<?php
// Anasayfa H1 — settings doluysa görünür, boşsa screen-reader-only (görünmez).
// Görünmez H1: SEO ve a11y için zorunlu (W3C + WAVE bunu istiyor), görsel etkisi yok.
$heroHeadline = trim((string)setting('home_hero_title', ''));
$heroSubline  = trim((string)setting('home_hero_sub',   ''));
$siteNameH1   = trim((string)setting('site_name','AquaShop'));
$siteTagH1    = trim((string)setting('site_tagline','')) ?: trim((string)setting('meta_description',''));
?>
<h1 id="home-h1" class="sr-only"><?= e($siteNameH1) ?><?= $siteTagH1 ? ' · ' . e($siteTagH1) : '' ?></h1>

<?php if ($banners): ?>
<?php
// Hero Rotator — Phase 1 LCP optimizasyonu KORUNUR:
// İlk slide <picture> ile mobil variant'ı servis eder (LCP candidate).
// Diğer slide'lar position:absolute + opacity:0 ile arkada bekler, JS ile dönüş.
// Sıfır CLS: rotator container'ın aspect-ratio'su CSS'te sabit (critical+home.css inline).
$heroFallbackAlt = trim((string)setting('site_name','AquaShop'));
$bannerCount = count($banners);

// İlk banner için mobile variant kontrol
$b0          = $banners[0];
$b0img       = (string)$b0['image'];
$b0mobile    = preg_replace('/(\.[a-z0-9]+)$/i', '-mobile$1', $b0img);
$b0mobAbs    = APP_ROOT . '/' . ltrim((string)$b0mobile, '/');
$b0hasMobile = ($b0mobile !== $b0img && $b0mobile !== null && is_file($b0mobAbs));
?>
<section class="aq-hero-area aq-hero-clean-area" aria-label="Anasayfa banner">
  <div class="aq-container">
    <div class="aq-hero-clean-grid">
    <div class="aq-hero-slider aq-hero-clean-slider hero-rotator<?= $bannerCount > 1 ? ' has-multi' : '' ?>" data-rotate-interval="5000">
      <?php foreach ($banners as $i => $b):
          $href     = !empty($b['link']) ? $b['link'] : null;
          $imgAlt   = trim((string)($b['alt'] ?? '')) ?: trim((string)($b['title'] ?? '')) ?: ($heroFallbackAlt . ($i > 0 ? ' banner ' . ($i+1) : ''));
          $imgTitle = trim((string)($b['title'] ?? '')) ?: $imgAlt;
          $isFirst  = ($i === 0);
      ?>
        <a class="aq-hero-slide aq-hero-clean-slide<?= $isFirst ? ' is-active active' : '' ?>"
           <?= $href ? 'href="'.e($href).'"' : '' ?>
           title="<?= e($imgTitle) ?>"
           <?= !$isFirst ? 'aria-hidden="true" tabindex="-1"' : '' ?>>
          <?php if ($isFirst && $b0hasMobile): ?>
          <picture>
            <source media="(max-width:768px)" srcset="<?= e($b0mobile) ?>" type="image/webp">
            <img
                 loading="eager" fetchpriority="high"
                 width="1280" height="512"
                 src="<?= e($b0img) ?>"
                 alt="<?= e($imgAlt) ?>" title="<?= e($imgTitle) ?>">
          </picture>
          <?php else: ?>
          <img
               <?= $isFirst ? 'loading="eager" fetchpriority="high"' : 'loading="lazy" decoding="async"' ?>
               width="1280" height="512"
               src="<?= e($b['image']) ?>"
               alt="<?= e($imgAlt) ?>" title="<?= e($imgTitle) ?>">
          <?php endif; ?>
        </a>
      <?php endforeach; ?>

      <?php if ($bannerCount > 1): ?>
      <button type="button" class="aq-hero-arrow aq-hero-prev hero-nav prev" aria-label="Önceki banner"><i class="bi bi-chevron-left"></i></button>
      <button type="button" class="aq-hero-arrow aq-hero-next hero-nav next" aria-label="Sonraki banner"><i class="bi bi-chevron-right"></i></button>
      <div class="aq-hero-dots hero-dots" aria-label="Banner seçimi">
        <?php foreach ($banners as $i => $b): ?>
          <button type="button" class="<?= $i===0?'is-active active':'' ?>" data-index="<?= $i ?>" aria-label="Banner <?= $i+1 ?>"<?= $i===0?' aria-current="true"':'' ?>></button>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>
    </div>
    </div>
  </div>
</section>
<?php endif; ?>

<?php
/* ── Promo kartları (hero altı) — admin: Ayarlar → Mağaza Kimliği → Anasayfa Görünümü.
   En az 1 kartın başlığı doluysa gösterilir; boşsa hiç render edilmez. ── */
$__promos = array();
for ($__pi = 1; $__pi <= 2; $__pi++) {
    $__pt = trim((string)setting("home_promo{$__pi}_title", ''));
    if ($__pt !== '') {
        $__promos[] = array(
            'title' => $__pt,
            'text'  => trim((string)setting("home_promo{$__pi}_text", '')),
            'link'  => trim((string)setting("home_promo{$__pi}_link", '')),
        );
    }
}
if ($__promos): ?>
<section class="promo-row-section" aria-label="Öne çıkan kampanyalar">
  <div class="container">
    <div class="aq-promo-row">
      <?php foreach ($__promos as $__idx => $__pr): $__hasLink = $__pr['link'] !== ''; ?>
        <<?= $__hasLink ? 'a' : 'div' ?> class="aq-promo p<?= $__idx + 1 ?>"<?= $__hasLink ? ' href="'.e($__pr['link']).'"' : '' ?>>
          <h3><?= e($__pr['title']) ?></h3>
          <?php if ($__pr['text'] !== ''): ?><p><?= e($__pr['text']) ?></p><?php endif; ?>
          <?php if ($__hasLink): ?><span class="aq-promo-cta">İncele <?= ic('arrow-right','',15) ?></span><?php endif; ?>
        </<?= $__hasLink ? 'a' : 'div' ?>>
      <?php endforeach; ?>
    </div>
  </div>
</section>
<?php endif; ?>

<?php if ($cats && setting('home_show_categories','1')==='1'): ?>
<section class="aq-category-section aq-category-clean-section" aria-label="Kategoriler" data-carousel data-visible-desktop="10" data-visible-tablet="5" data-visible-mobile="3">
  <div class="aq-container">
    <div class="aq-category-slider-wrap">
      <button type="button" class="aq-category-prev aq-category-side-arrow aq-category-side-prev" data-dir="-1" aria-label="Sola kaydır"><i class="bi bi-chevron-left"></i></button>
      <button type="button" class="aq-category-next aq-category-side-arrow aq-category-side-next" data-dir="1" aria-label="Sağa kaydır"><i class="bi bi-chevron-right"></i></button>
      <div class="aq-category-viewport">
        <div class="aq-category-track">
          <?php foreach ($cats as $c): ?>
            <a class="aq-category-card" href="<?= e(url('category', ['slug'=>$c['slug']])) ?>">
              <span>
                <?php if (!empty($c['image'])): ?>
                  <img loading="lazy" decoding="async" width="120" height="120" src="<?= e($c['image']) ?>" alt="<?= e($c['name']) ?>">
                <?php else: ?>
                  <strong><?= e(mb_substr($c['name'],0,1)) ?></strong>
                <?php endif; ?>
              </span>
              <strong><?= e($c['name']) ?></strong>
            </a>
          <?php endforeach; ?>
        </div>
      </div>
    </div>
  </div>
</section>
<!-- inline style removed: see assets/css/ -->
<!-- inline script removed: see assets/js/ -->
<?php endif; ?>

<section class="aq-product-section aq-soft-block">
  <div class="aq-container">
    <div class="aq-section-title-row">
      <div>
        <span><?= e(setting('home_featured_kicker','Öne Çıkan Ürünler')) ?></span>
        <h2><?= e(setting('home_featured_title','Seçkin Koleksiyon')) ?></h2>
      </div>
    </div>
    <?php if ($featured): ?>
    <div class="aq-carousel-wrap" data-carousel data-visible-desktop="5" data-visible-tablet="3" data-visible-mobile="2">
      <div class="aq-carousel-controls">
        <button type="button" class="aq-carousel-arrow aq-products-prev" data-dir="-1" aria-label="Geri" disabled><i class="bi bi-chevron-left"></i></button>
        <button type="button" class="aq-carousel-arrow aq-products-next" data-dir="1" aria-label="İleri"><i class="bi bi-chevron-right"></i></button>
      </div>
      <div class="aq-products-viewport">
        <div class="aq-products-track">
          <?php $favIds = fav_ids(); $cardBack = 'index.php'; foreach ($featured as $p): ?>
            <?php include __DIR__ . '/../components/product-card.php'; ?>
          <?php endforeach; ?>
        </div>
      </div>
    </div>
    <?php else: ?>
      <p class="muted">Henüz ürün eklenmedi.</p>
    <?php endif; ?>
  </div>
</section>

<?php if (!empty($mostFavedProducts) && setting('home_show_most_faved','1')==='1'): ?>
<section class="aq-product-section aq-soft-block">
  <div class="aq-container">
    <div class="aq-section-title-row">
      <div>
        <span><?= e(setting('home_most_faved_kicker','Favoriler')) ?></span>
        <h2><?= e(setting('home_most_faved_title','En Çok Favoriye Eklenenler')) ?></h2>
      </div>
    </div>
    <div class="aq-product-grid aq-grid-5">
      <?php $favIds = fav_ids(); $cardBack = 'index.php'; foreach ($mostFavedProducts as $mf): $p = $mf; ?>
        <?php include __DIR__ . '/../components/product-card.php'; ?>
      <?php endforeach; ?>
    </div>
  </div>
</section>
<?php endif; ?>

<?php if ($blogPosts): ?>
<section class="aq-blog-section">
  <div class="aq-container">
    <div class="aq-section-title-row">
      <div>
        <span><?= e(setting('home_blog_kicker','Blog')) ?></span>
        <h2><?= e(setting('home_blog_title','Son Yazılar')) ?></h2>
      </div>
      <a class="aq-view-all" href="<?= url('blog') ?>">Tüm Yazılar <i class="bi bi-arrow-right"></i></a>
    </div>
    <div class="aq-blog-grid">
      <?php foreach ($blogPosts as $bp): ?>
        <article class="aq-blog-card">
          <a class="aq-blog-image" href="<?= e(url('blog_post', ['slug'=>$bp['slug']])) ?>">
            <?php if (!empty($bp['cover_image'])): ?>
              <img loading="lazy" decoding="async" width="600" height="400" src="<?= e($bp['cover_image']) ?>" alt="<?= e($bp['title']) ?>">
            <?php else: ?>
              <span class="aq-ph"><?= e(mb_substr($bp['title'],0,1)) ?></span>
            <?php endif; ?>
          </a>
          <div class="aq-blog-content">
            <span><?= e($bp['cat_name'] ?? 'Yazı') ?> · <?= e(date('d.m.Y', strtotime($bp['published_at'] ?? $bp['created_at']))) ?></span>
            <h3><a href="<?= e(url('blog_post', ['slug'=>$bp['slug']])) ?>"><?= e($bp['title']) ?></a></h3>
            <?php if (!empty($bp['excerpt'])): ?><p><?= e(mb_substr(strip_tags($bp['excerpt']),0,140)) ?><?= mb_strlen(strip_tags($bp['excerpt']))>140?'…':'' ?></p><?php endif; ?>
            <a class="aq-blog-link" href="<?= e(url('blog_post', ['slug'=>$bp['slug']])) ?>">Devamını Oku <i class="bi bi-arrow-right"></i></a>
          </div>
        </article>
      <?php endforeach; ?>
    </div>
  </div>
</section>
<?php endif; ?>

<?php if (!empty($secondaryProducts)): ?>
<section class="aq-product-section">
  <div class="aq-container">
    <div class="aq-section-title-row">
      <div>
        <span><?= e(setting('home_secondary_kicker','Mağazadan')) ?></span>
        <h2><?= e(setting('home_secondary_title','Beğenebileceğiniz Ürünler')) ?></h2>
      </div>
    </div>
    <div class="aq-product-grid aq-grid-5">
      <?php $favIds = fav_ids(); $cardBack = 'index.php'; foreach ($secondaryProducts as $sp): $p = $sp; ?>
        <?php include __DIR__ . '/../components/product-card.php'; ?>
      <?php endforeach; ?>
    </div>
  </div>
</section>
<?php endif; ?>

<?php
/* ── Çok Satanlar widget (Faz 6.E) ─────────────────────────────── */
$bestsellers = bestsellers_get(8, 30);
if ($bestsellers) {
    $stripKicker = 'Son 30 Gün';
    $stripTitle  = 'En Çok Satanlar';
    $stripItems  = $bestsellers;
    $stripBg     = null;
    include __DIR__ . '/../components/product-strip.php';
}

/* ── Son Baktıkların widget (Faz 6.C) ──────────────────────────── */
$recentlyViewed = recently_viewed_get(8);
if ($recentlyViewed) {
    $stripKicker = 'Devam et';
    $stripTitle  = 'Son Baktıkların';
    $stripItems  = $recentlyViewed;
    $stripBg     = 'cream';
    include __DIR__ . '/../components/product-strip.php';
}
?>

<?php
/* ── Instagram şeridi — admin: Anasayfa Görünümü → Instagram. Varsayılan kapalı.
   Görsel URL'leri (satır satır) girilmezse dekoratif aqua karoları gösterir. ── */
$__igOn      = setting('home_instagram_enabled','0') === '1';
$__igProfile = trim((string)setting('social_instagram',''));
$__igUser    = trim((string)setting('home_instagram_user',''));
if ($__igOn && ($__igProfile !== '' || $__igUser !== '')):
    $__igImgs = array_values(array_filter(array_map('trim', preg_split('/\R+/', (string)setting('home_instagram_images','')))));
    $__igTiles = $__igImgs ?: array_fill(0, 6, '');
    $__igTiles = array_slice($__igTiles, 0, 12);
    $__igHref  = $__igProfile !== '' ? $__igProfile : '#';
?>
<section style="background:var(--cream);border-top:1px solid var(--gold-border);border-bottom:1px solid var(--gold-border)" aria-label="Instagram">
  <div class="container">
    <div class="section-head">
      <span class="kicker"><?= e($__igUser !== '' ? '@'.ltrim($__igUser,'@') : 'Instagram') ?></span>
      <h2><?= e(setting('home_instagram_title','Bizi Instagram\'da Takip Edin')) ?></h2>
      <div class="ornament-divider"><span class="line"></span><span class="diamond"></span><span class="line"></span></div>
    </div>
    <div class="aq-ig">
      <?php foreach ($__igTiles as $__ig): ?>
        <a class="aq-ig-card" href="<?= e($__igHref) ?>"<?= $__igProfile !== '' ? ' target="_blank" rel="noopener"' : '' ?> aria-label="Instagram'da görüntüle">
          <?php if ($__ig !== ''): ?>
            <img loading="lazy" decoding="async" src="<?= e($__ig) ?>" alt="Instagram gönderisi">
          <?php else: ?>
            <span class="ig-ico"><?= ic('instagram','',30) ?></span>
          <?php endif; ?>
        </a>
      <?php endforeach; ?>
    </div>
    <?php if ($__igProfile !== ''): ?>
      <div class="center" style="margin-top:36px"><a class="btn btn-secondary" href="<?= e($__igProfile) ?>" target="_blank" rel="noopener">Takip Et →</a></div>
    <?php endif; ?>
  </div>
</section>
<?php endif; ?>

<!-- inline script removed: see assets/js/ -->

<?php include __DIR__ . '/../includes/footer.php'; ?>
