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
<?php if ($heroHeadline || $heroSubline): ?>
<section class="home-headline" aria-labelledby="home-h1">
  <div class="container">
    <h1 id="home-h1" class="<?= $heroHeadline ? 'home-headline-h1' : 'sr-only' ?>"><?= e($heroHeadline ?: ($siteNameH1 . ($siteTagH1 ? ' · ' . $siteTagH1 : ''))) ?></h1>
    <?php if ($heroSubline): ?><p class="home-headline-sub"><?= e($heroSubline) ?></p><?php endif; ?>
  </div>
</section>
<?php else: ?>
<?php /* Görünmez H1 — sadece screen reader + bot için. .sr-only utility class'ı CSS'te var. */ ?>
<h1 id="home-h1" class="sr-only"><?= e($siteNameH1) ?><?= $siteTagH1 ? ' · ' . e($siteTagH1) : '' ?></h1>
<?php endif; ?>

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
<section class="hero-simple" aria-label="Anasayfa banner">
  <div class="container">
    <div class="hero-rotator<?= $bannerCount > 1 ? ' has-multi' : '' ?>" data-rotate-interval="5000">
      <?php foreach ($banners as $i => $b):
          $href     = !empty($b['link']) ? $b['link'] : null;
          $imgAlt   = trim((string)($b['alt'] ?? '')) ?: trim((string)($b['title'] ?? '')) ?: ($heroFallbackAlt . ($i > 0 ? ' banner ' . ($i+1) : ''));
          $imgTitle = trim((string)($b['title'] ?? '')) ?: $imgAlt;
          $isFirst  = ($i === 0);
      ?>
        <a class="hero-slide<?= $isFirst ? ' active' : '' ?>"
           <?= $href ? 'href="'.e($href).'"' : '' ?>
           title="<?= e($imgTitle) ?>"
           <?= !$isFirst ? 'aria-hidden="true" tabindex="-1"' : '' ?>>
          <?php if ($isFirst && $b0hasMobile): ?>
          <picture>
            <source media="(max-width:768px)" srcset="<?= e($b0mobile) ?>" type="image/webp">
            <img class="hero-simple-img"
                 loading="eager" fetchpriority="high"
                 width="1280" height="512"
                 src="<?= e($b0img) ?>"
                 alt="<?= e($imgAlt) ?>" title="<?= e($imgTitle) ?>">
          </picture>
          <?php else: ?>
          <img class="hero-simple-img"
               <?= $isFirst ? 'loading="eager" fetchpriority="high"' : 'loading="lazy" decoding="async"' ?>
               width="1280" height="512"
               src="<?= e($b['image']) ?>"
               alt="<?= e($imgAlt) ?>" title="<?= e($imgTitle) ?>">
          <?php endif; ?>
        </a>
      <?php endforeach; ?>

      <?php if ($bannerCount > 1): ?>
      <button type="button" class="hero-nav prev" aria-label="Önceki banner">‹</button>
      <button type="button" class="hero-nav next" aria-label="Sonraki banner">›</button>
      <div class="hero-dots" aria-label="Banner seçimi">
        <?php foreach ($banners as $i => $b): ?>
          <button type="button" class="<?= $i===0?'active':'' ?>" data-index="<?= $i ?>" aria-label="Banner <?= $i+1 ?>"<?= $i===0?' aria-current="true"':'' ?>></button>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>
    </div>
  </div>
</section>
<?php endif; ?>

<?php if ($cats && setting('home_show_categories','1')==='1'): ?>
<section class="cat-strip-section" aria-label="Kategoriler">
  <div class="container">
    <div class="cat-strip">
      <button type="button" class="cat-nav left"  aria-label="Sola kaydır">←</button>
      <div class="cat-track" id="catTrack">
        <?php foreach ($cats as $c): ?>
          <a class="cat-item" href="<?= e(url('category', ['slug'=>$c['slug']])) ?>">
            <span class="cat-circle">
              <?php if (!empty($c['image'])): ?>
                <img loading="lazy" decoding="async" width="120" height="120" src="<?= e($c['image']) ?>" alt="<?= e($c['name']) ?>">
              <?php else: ?>
                <span class="cat-initial"><?= e(mb_substr($c['name'],0,1)) ?></span>
              <?php endif; ?>
            </span>
            <span class="cat-label"><?= e($c['name']) ?></span>
          </a>
        <?php endforeach; ?>
      </div>
      <button type="button" class="cat-nav right" aria-label="Sağa kaydır">→</button>
    </div>
  </div>
</section>
<!-- inline style removed: see assets/css/ -->
<!-- inline script removed: see assets/js/ -->
<?php endif; ?>

<section>
  <div class="container">
    <div class="section-head">
      <span class="kicker"><?= e(setting('home_featured_kicker','Öne Çıkan Ürünler')) ?></span>
      <h2><?= e(setting('home_featured_title','Seçkin Koleksiyon')) ?></h2>
      <div class="ornament-divider"><span class="line"></span><span class="diamond"></span><span class="line"></span></div>
    </div>
    <div class="grid">
      <?php
      // Banner yoksa ilk ürün görseli LCP adayıdır — eager + fetchpriority="high"
      $__lcpCard = empty($banners);
      $favIds = fav_ids();
      if ($featured): foreach ($featured as $p): ?>
        <div class="card" style="position:relative">
          <form method="post" action="favorite-toggle.php" class="fav-form">
            <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="id"   value="<?= (int)$p['id'] ?>">
            <input type="hidden" name="back" value="index.php">
            <button class="fav-btn <?= in_array((int)$p['id'],$favIds)?'active':'' ?>" type="submit" aria-label="Favori"><?= ic('heart', '', 18) ?></button>
          </form>
          <a href="<?= e(url('product', ['slug'=>$p['slug']])) ?>" style="display:flex;flex-direction:column;flex:1">
          <div class="card-img">
            <?php if (!empty($p['old_price'])): ?><span class="badge">İndirim</span><?php endif; ?>
            <?php if (!empty($p['image'])): ?>
              <img <?= $__lcpCard ? 'loading="eager" fetchpriority="high"' : 'loading="lazy" decoding="async"' ?>
                   width="600" height="600" src="<?= e($p['image']) ?>" alt="<?= e($p['name']) ?>" style="width:100%;height:100%;object-fit:cover">
              <?php $__lcpCard = false; // sadece ilk kart için ?>
            <?php else: ?>
              <span class="ph"><?= e(mb_substr($p['name'],0,1)) ?></span>
            <?php endif; ?>
          </div>
          <div class="card-body">
            <span class="cat">Premium</span>
            <h3><?= e($p['name']) ?></h3>
            <div class="card-foot">
              <?php if (!empty($p['price_on_request']) && (float)($p['price'] ?? 0) <= 0): ?>
                <span class="price" style="font-size:12px;letter-spacing:.08em;color:var(--muted-text)">İletişime Geçin</span>
              <?php else: ?>
                <span class="price"><?= money($p['price']) ?><?php if (!empty($p['old_price'])): ?><span class="price-old"><?= money($p['old_price']) ?></span><?php endif; ?></span>
              <?php endif; ?>
              <span class="icon-btn"><?= ic('cart', '', 17) ?></span>
            </div>
          </div>
          </a>
        </div>
      <?php endforeach; else: ?>
        <p class="muted">Henüz ürün eklenmedi.</p>
      <?php endif; ?>
    </div>
    <div class="center" style="margin-top:48px"><a class="btn btn-secondary" href="<?= url('products') ?>">Tüm Ürünler →</a></div>
  </div>
</section>

<?php if (!empty($mostFavedProducts) && setting('home_show_most_faved','1')==='1'): ?>
<section style="background:var(--cream);border-top:1px solid var(--gold-border);border-bottom:1px solid var(--gold-border)">
  <div class="container">
    <div class="section-head">
      <span class="kicker"><?= e(setting('home_most_faved_kicker','Favoriler')) ?></span>
      <h2><?= e(setting('home_most_faved_title','En Çok Favoriye Eklenenler')) ?></h2>
      <div class="ornament-divider"><span class="line"></span><span class="diamond"></span><span class="line"></span></div>
    </div>
    <div class="grid">
      <?php $favIdsMf = fav_ids(); foreach ($mostFavedProducts as $mf): ?>
        <div class="card" style="position:relative">
          <form method="post" action="favorite-toggle.php" class="fav-form">
            <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="id"   value="<?= (int)$mf['id'] ?>">
            <input type="hidden" name="back" value="index.php">
            <button class="fav-btn <?= in_array((int)$mf['id'],$favIdsMf)?'active':'' ?>" type="submit" aria-label="Favori">
              <?= ic('heart', '', 18) ?>
              <?php if ((int)$mf['fav_count'] > 1): ?>
                <span class="fav-count"><?= (int)$mf['fav_count'] ?></span>
              <?php endif; ?>
            </button>
          </form>
          <a href="<?= e(url('product', ['slug'=>$mf['slug']])) ?>" style="display:flex;flex-direction:column;flex:1">
            <div class="card-img">
              <?php if (!empty($mf['old_price'])): ?><span class="badge">İndirim</span><?php endif; ?>
              <?php if (!empty($mf['image'])): ?>
                <img loading="lazy" decoding="async" width="600" height="600" src="<?= e($mf['image']) ?>" alt="<?= e($mf['name']) ?>" style="width:100%;height:100%;object-fit:cover">
              <?php else: ?>
                <span class="ph"><?= e(mb_substr($mf['name'],0,1)) ?></span>
              <?php endif; ?>
            </div>
            <div class="card-body">
              <span class="cat"><?= e($mf['cat_name'] ?? 'Genel') ?></span>
              <h3><?= e($mf['name']) ?></h3>
              <div class="card-foot">
                <?php if (!empty($mf['price_on_request']) && (float)($mf['price'] ?? 0) <= 0): ?>
                  <span class="price" style="font-size:12px;letter-spacing:.08em;color:var(--muted-text)">İletişime Geçin</span>
                <?php else: ?>
                  <span class="price"><?= money($mf['price']) ?><?php if (!empty($mf['old_price'])): ?><span class="price-old"><?= money($mf['old_price']) ?></span><?php endif; ?></span>
                <?php endif; ?>
                <span class="icon-btn"><?= ic('cart', '', 17) ?></span>
              </div>
            </div>
          </a>
        </div>
      <?php endforeach; ?>
    </div>
    <div class="center" style="margin-top:48px"><a class="btn btn-secondary" href="<?= url('products') ?>">Tüm Ürünler →</a></div>
  </div>
</section>
<?php endif; ?>

<?php if ($blogPosts): ?>
<section style="background:var(--cream);border-top:1px solid var(--gold-border);border-bottom:1px solid var(--gold-border)">
  <div class="container">
    <div class="section-head">
      <span class="kicker"><?= e(setting('home_blog_kicker','Blog')) ?></span>
      <h2><?= e(setting('home_blog_title','Son Yazılar')) ?></h2>
      <div class="ornament-divider"><span class="line"></span><span class="diamond"></span><span class="line"></span></div>
    </div>
    <div class="grid">
      <?php foreach ($blogPosts as $bp): ?>
        <a class="card" href="<?= e(url('blog_post', ['slug'=>$bp['slug']])) ?>">
          <div class="card-img">
            <?php if (!empty($bp['cover_image'])): ?>
              <img loading="lazy" decoding="async" width="600" height="400" src="<?= e($bp['cover_image']) ?>" alt="<?= e($bp['title']) ?>" style="width:100%;height:100%;object-fit:cover">
            <?php else: ?>
              <span class="ph"><?= e(mb_substr($bp['title'],0,1)) ?></span>
            <?php endif; ?>
          </div>
          <div class="card-body">
            <span class="cat"><?= e($bp['cat_name'] ?? 'Yazı') ?> · <?= e(date('d.m.Y', strtotime($bp['published_at'] ?? $bp['created_at']))) ?></span>
            <h3 style="font-size:18px"><?= e($bp['title']) ?></h3>
            <?php if (!empty($bp['excerpt'])): ?><p class="muted" style="font-size:13px"><?= e(mb_substr($bp['excerpt'],0,140)) ?><?= mb_strlen($bp['excerpt'])>140?'…':'' ?></p><?php endif; ?>
          </div>
        </a>
      <?php endforeach; ?>
    </div>
    <div class="center" style="margin-top:48px"><a class="btn btn-secondary" href="<?= url('blog') ?>">Tüm Yazılar →</a></div>
  </div>
</section>
<?php endif; ?>

<?php if (!empty($secondaryProducts)): ?>
<section>
  <div class="container">
    <div class="section-head">
      <span class="kicker"><?= e(setting('home_secondary_kicker','Mağazadan')) ?></span>
      <h2><?= e(setting('home_secondary_title','Beğenebileceğiniz Ürünler')) ?></h2>
      <div class="ornament-divider"><span class="line"></span><span class="diamond"></span><span class="line"></span></div>
    </div>
    <div class="grid">
      <?php $favIds2 = fav_ids(); foreach ($secondaryProducts as $sp): ?>
        <div class="card" style="position:relative">
          <form method="post" action="favorite-toggle.php" class="fav-form">
            <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="id"   value="<?= (int)$sp['id'] ?>">
            <input type="hidden" name="back" value="index.php">
            <button class="fav-btn <?= in_array((int)$sp['id'],$favIds2)?'active':'' ?>" type="submit" aria-label="Favori"><?= ic('heart', '', 18) ?></button>
          </form>
          <a href="<?= e(url('product', ['slug'=>$sp['slug']])) ?>" style="display:flex;flex-direction:column;flex:1">
            <div class="card-img">
              <?php if (!empty($sp['old_price'])): ?><span class="badge">İndirim</span><?php endif; ?>
              <?php if (!empty($sp['image'])): ?>
                <img loading="lazy" decoding="async" width="600" height="600" src="<?= e($sp['image']) ?>" alt="<?= e($sp['name']) ?>" style="width:100%;height:100%;object-fit:cover">
              <?php else: ?>
                <span class="ph"><?= e(mb_substr($sp['name'],0,1)) ?></span>
              <?php endif; ?>
            </div>
            <div class="card-body">
              <span class="cat"><?= e($sp['cat_name'] ?? 'Genel') ?></span>
              <h3><?= e($sp['name']) ?></h3>
              <div class="card-foot">
                <?php if (!empty($sp['price_on_request']) && (float)($sp['price'] ?? 0) <= 0): ?>
                  <span class="price" style="font-size:12px;letter-spacing:.08em;color:var(--muted-text)">İletişime Geçin</span>
                <?php else: ?>
                  <span class="price"><?= money($sp['price']) ?><?php if (!empty($sp['old_price'])): ?><span class="price-old"><?= money($sp['old_price']) ?></span><?php endif; ?></span>
                <?php endif; ?>
                <span class="icon-btn"><?= ic('cart', '', 17) ?></span>
              </div>
            </div>
          </a>
        </div>
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

<?php if (setting('home_show_features','1')==='1'): ?>
<section>
  <div class="container">
    <div class="section-head">
      <span class="kicker">Neden Biz</span>
      <h2>Söz Verdiklerimiz</h2>
      <div class="ornament-divider"><span class="line"></span><span class="diamond"></span><span class="line"></span></div>
    </div>
    <div class="features">
      <div class="feature"><div class="ico"><?= ic('truck', '', 26) ?></div><h3>Hızlı Kargo</h3><p>Aynı Gün Kargo</p></div>
      <div class="feature"><div class="ico"><?= ic('shield-check', '', 26) ?></div><h3>Güvenli Ödeme</h3><p>SSL sertifikalı altyapı.</p></div>
      <div class="feature"><div class="ico"><?= ic('rotate-ccw', '', 26) ?></div><h3>14 Gün İade</h3><p>Kolay ve koşulsuz iade hakkı.</p></div>
      <div class="feature"><div class="ico"><?= ic('headphones', '', 26) ?></div><h3>Ücretsiz Danışmanlık</h3><p>Uzman ekibimiz yanınızda.</p></div>
    </div>
  </div>
</section>
<?php endif; ?>

<?php if (setting('home_show_newsletter','1')==='1'): ?>
<section class="newsletter">
  <div class="container">
    <span class="kicker">Bültene Katılın</span>
    <h2 style="margin-top:14px">Yeniliklerden İlk Siz Haberdar Olun</h2>
    <p>Yeni koleksiyonlar, özel kampanyalar ve yalnızca üyelere özel fırsatlardan haberdar olmak için bültene kaydolun.</p>
    <form class="nl-form" method="post" action="<?= SITE_URL ?>/newsletter-subscribe.php">
      <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
      <!-- Honeypot: gerçek kullanıcılar görmez, bot'lar doldurur -->
      <input type="text" name="website" value="" tabindex="-1" autocomplete="off" style="position:absolute;left:-9999px;height:0;width:0;opacity:0" aria-hidden="true" aria-label="Web sitesi (boş bırakın)">
      <input type="email" name="email" placeholder="E-posta adresiniz" aria-label="E-posta adresi" required maxlength="190">
      <button class="btn btn-primary" type="submit">Abone Ol</button>
    </form>
  </div>
</section>
<?php endif; ?>

<!-- inline script removed: see assets/js/ -->

<?php include __DIR__ . '/../includes/footer.php'; ?>
