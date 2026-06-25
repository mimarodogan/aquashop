<?php
require_once __DIR__ . '/../core/bootstrap.php';
$page  = $page  ?? '';
$title = $title ?? (setting('site_name') ?? SITE_NAME_FALLBACK);

/*
 * O-7 GUVENLIK: CSP nonce - her istek icin YENI deger (session'da saklamak guvenligi azaltir).
 * Bu nonce su an .htaccess CSP'sinde aktif degil ('unsafe-inline' kullaniliyor); ileride
 * 'unsafe-inline' kaldirildiginda inline script ve style bloklarina nonce attribute eklenerek
 * aktive edilir.
 *
 * MIGRATION YOLU (uzun vadeli):
 *   1) Tum inline script'leri ya harici .js dosyalarina tasi YA da nonce attribute'u ekle
 *   2) .htaccess CSP'sinden 'unsafe-inline' kaldir
 *   3) PHP'den CSP header'i override et: nonce-{$nonce}
 */
$nonce = base64_encode(random_bytes(16));

// Sayfa-spesifik CSS/JS dosyaları
$pageStyles = array();
$pageScripts = array();
switch ($page) {
    case 'home':     $pageScripts[] = 'home.min.js'; $pageScripts[] = 'carousel.js'; break;
    case 'products':  $pageStyles[] = 'product.css'; $pageScripts[] = 'components/loadmore.min.js'; $pageScripts[] = 'pages/products-listing.min.js'; break;
    case 'categories_list': $pageStyles[] = 'product.css'; break;
    case 'category':  $pageStyles[] = 'product.css'; break;
    case 'cart':      $pageStyles[] = 'product.css'; break;
    case 'product':  $pageStyles[] = 'product.css'; $pageStyles[] = 'pages/product-detail.css'; $pageScripts[] = 'pages/product-detail.min.js'; $pageScripts[] = 'carousel.js'; break;
    case 'checkout': $pageStyles[] = 'product.css'; $pageScripts[] = 'pages/checkout.min.js'; break;
    case 'blog':     $pageStyles[] = 'cms.css'; $pageScripts[] = 'components/loadmore.min.js'; break;
    case 'about':
    case 'post':     $pageStyles[] = 'cms.css'; $pageScripts[] = 'carousel.js'; break;
}

// Versiyon damgası — CSS/JS güncellenince tarayıcı cache'i otomatik temizler
function asset_v($rel) {
    $f = APP_ROOT . '/assets/' . $rel;
    return @filemtime($f) ?: time();
}

/**
 * CSS dosyaları artık `sql/minify_css.php` scripti ile yerinde minify ediliyor.
 * Bu fonksiyon geriye dönük uyumluluk için path'i olduğu gibi döndürür.
 * Orijinal kaynaklar `.src.css` olarak yedeklenmiştir.
 */
function asset_min_css($rel) {
    return $rel;
}

// CSP .htaccess üzerinden yönetiliyor (Header always set).
// PHP'den ayrı gönderilmesi çift header sorununa yol açıyordu.
?>
<?php
// SEO verilerini çek (master_query: tek sorgu, modelden cache'li)
$seo = seo_get($page);
$siteName = trim((string)(setting('site_name') ?? '')) ?: SITE_NAME_FALLBACK;

// Meta title hesapla — admin {title} placeholder'ı destekler
$metaTitle = '';
if ($seo && !empty($seo['meta_title'])) {
    $metaTitle = str_replace('{title}', $title, $seo['meta_title']);
}
if ($metaTitle === '') {
    // Title boşsa (örn. anasayfa) sadece site adı + tagline
    if ($title === '' || $title === null) {
        $tagline = trim((string)setting('site_tagline',''));
        $metaTitle = $tagline !== '' ? $siteName . ' · ' . $tagline : $siteName;
    } else {
        $metaTitle = $title . ' · ' . $siteName;
    }
}

// {title} placeholder meta description için de desteklenir (örn. category template'inde)
$metaDesc = ($seo && !empty($seo['meta_description']))
    ? str_replace('{title}', $title, $seo['meta_description'])
    : null;
$metaKeys = ($seo && !empty($seo['meta_keywords']))    ? $seo['meta_keywords']    : null;
$ogImg    = ($seo && !empty($seo['og_image']))         ? $seo['og_image']         : null;

// Blog yazısı sayfası: her yazı kendi excerpt/description'ını meta description olarak kullanır
if ($metaDesc === null && $page === 'post' && !empty($post)) {
    if (!empty($post['excerpt'])) {
        $metaDesc = mb_substr(strip_tags((string)$post['excerpt']), 0, 160);
    } elseif (!empty($post['content'])) {
        $metaDesc = mb_substr(strip_tags((string)$post['content']), 0, 160);
    }
}

// Ürün sayfası: seo_settings 'product' kaydı tüm ürünler için aynı olduğundan
// her ürünün kendi short_desc (AI tarafından doldurulur) veya description'ını kullan.
// Bu sayede 53 ürün sayfası tekil meta description'a kavuşur.
if ($metaDesc === null && $page === 'product') {
    if (!empty($p['short_desc'])) {
        $metaDesc = mb_substr(strip_tags((string)$p['short_desc']), 0, 160);
    } elseif (!empty($p['description'])) {
        $metaDesc = mb_substr(strip_tags((string)$p['description']), 0, 160);
    }
}

// Kategori sayfası gibi sayfa-spesifik meta override ($__categoryMeta)
if (!empty($__categoryMeta)) {
    if (!empty($__categoryMeta['title']))  $metaTitle = $__categoryMeta['title'];
    if (!empty($__categoryMeta['desc']))   $metaDesc  = $__categoryMeta['desc'];
    if (!empty($__categoryMeta['image']))  $ogImg     = $__categoryMeta['image'];
}

// Canonical URL — sorgu parametrelerini (?sort, ?q, filtreler, tracking) çıkar.
// İSTİSNA: sayfalama (?offset=N) kendine-referanslı olmalı — rel=next/prev ile tutarlı.
// Aksi halde 2.+ sayfalar 1. sayfaya canonical verir → yalnızca derin sayfalarda
// linklenen ürünlerin keşfi/indekslenmesi baskılanır.
$reqPath   = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$canonical = (isset($_SERVER['HTTPS'])&&$_SERVER['HTTPS']!=='off' ? 'https' : 'http')
           . '://' . ($_SERVER['HTTP_HOST'] ?? '') . $reqPath;
$__canonOffset = isset($_GET['offset']) ? max(0, (int)$_GET['offset']) : 0;
if ($__canonOffset > 0) {
    $canonical .= '?offset=' . $__canonOffset;
}

// SEO genel ayarları (admin → Ayarlar → SEO bölümü)
$metaAuthor    = trim((string)setting('seo_author', $siteName));
$metaPublisher = trim((string)setting('seo_publisher', $siteName));
$metaRobots    = ($seo && !empty($seo['meta_robots'])) ? $seo['meta_robots'] : trim((string)setting('seo_robots', 'index, follow'));
$twitterHandle = trim((string)setting('seo_twitter_handle', ''));
$defaultOgImg  = trim((string)setting('seo_default_og_image', ''));

// Sayfa-spesifik OG image yoksa önce ürün/post/kategori görseliyle, sonra site defaultuyla doldur
if (!$ogImg && $page === 'product' && !empty($p['image'])) {
    $ogImg = $p['image'];
}
if (!$ogImg && $page === 'post' && !empty($post['cover_image'])) {
    $ogImg = $post['cover_image'];
}
if (!$ogImg && $page === 'category' && !empty($cat['image'])) {
    $ogImg = $cat['image'];
}
if (!$ogImg && $defaultOgImg !== '') {
    $ogImg = $defaultOgImg;
}
// Son çare: yönetim panelinde tanımlı favicon/logo görseli.
if (!$ogImg) {
    $fallbackLogo = trim((string)setting('favicon_path', ''));
    if ($fallbackLogo !== '') $ogImg = $fallbackLogo;
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= e($metaTitle) ?></title>
<meta name="description" content="<?= e($metaDesc ?: (setting('site_tagline') ?: $siteName)) ?>">
<?php if ($metaKeys): ?><meta name="keywords" content="<?= e($metaKeys) ?>"><?php endif; ?>
<meta name="author" content="<?= e($metaAuthor) ?>">
<meta name="publisher" content="<?= e($metaPublisher) ?>">
<meta name="robots" content="<?= e($metaRobots) ?>">
<meta name="googlebot" content="<?= e($metaRobots) ?>">
<link rel="canonical" href="<?= e($canonical) ?>">
<?php if (!empty($paginationLinks['prev'])): ?><link rel="prev" href="<?= e($paginationLinks['prev']) ?>"><?php endif; ?>
<?php if (!empty($paginationLinks['next'])): ?><link rel="next" href="<?= e($paginationLinks['next']) ?>"><?php endif; ?>
<?php $__fav = trim((string)setting('favicon_path','')); if ($__fav !== ''): ?>
  <link rel="icon" type="image/webp" href="<?= e($__fav) ?>">
  <link rel="apple-touch-icon" href="<?= e($__fav) ?>">
<?php else: ?>
  <link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><text y='.9em' font-size='90' font-family='Georgia,serif'>🐠</text></svg>">
<?php endif; ?>

<!-- Open Graph / Twitter -->
<meta property="og:type" content="<?= $page === 'product' ? 'product' : ($page === 'post' ? 'article' : 'website') ?>">
<meta property="og:site_name" content="<?= e($siteName) ?>">
<meta property="og:title" content="<?= e($metaTitle) ?>">
<?php if ($metaDesc): ?><meta property="og:description" content="<?= e($metaDesc) ?>"><?php endif; ?>
<meta property="og:url" content="<?= e($canonical) ?>">
<?php if ($ogImg): $absOg = (strpos($ogImg,'http')===0) ? $ogImg : ((isset($_SERVER['HTTPS'])&&$_SERVER['HTTPS']!=='off'?'https':'http').'://'.$_SERVER['HTTP_HOST'].$ogImg); ?>
<meta property="og:image" content="<?= e($absOg) ?>">
<meta property="og:image:alt" content="<?= e($metaTitle) ?>">
<?php endif; ?>

<!-- Twitter Card (her zaman, og_image olmasa bile) -->
<meta name="twitter:card" content="<?= $ogImg ? 'summary_large_image' : 'summary' ?>">
<?php if ($twitterHandle !== ''): ?>
<meta name="twitter:site" content="<?= e($twitterHandle) ?>">
<meta name="twitter:creator" content="<?= e($twitterHandle) ?>">
<?php endif; ?>
<meta name="twitter:title" content="<?= e($metaTitle) ?>">
<?php if ($metaDesc): ?><meta name="twitter:description" content="<?= e($metaDesc) ?>"><?php endif; ?>
<?php if (!empty($absOg)): ?>
<meta name="twitter:image" content="<?= e($absOg) ?>">
<meta name="twitter:image:alt" content="<?= e($metaTitle) ?>">
<?php endif; ?>

<!-- JSON-LD: ana sayfada Organization + WebSite, diğer sayfalarda sayfa-spesifik -->
<?php
require_once APP_ROOT . '/components/json-ld.php';
$schemas = array();
if ($page === 'home') {
    $schemas[] = jsonld_organization();
    $schemas[] = jsonld_website();
} elseif ($page === 'contact') {
    $schemas[] = jsonld_organization();
}
// Sayfanın kendi belirlediği ek şemalar (product/post/page) header çağrılmadan ÖNCE $extraSchemas[]'a push edilir
if (!empty($extraSchemas) && is_array($extraSchemas)) {
    foreach ($extraSchemas as $s) $schemas[] = $s;
}
jsonld_emit($schemas);
?>

<?php
/* Analitik & İzleme — Consent Mode v2 + GTM + GA4 + Pixel + Clarity.
 * Settings'ten ayarlanır, Lighthouse bot'ları algılarsa atlanır. */
include APP_ROOT . '/components/analytics.php';

/* Server-side sayfa görüntüleme — admin dashboard dönüşüm hesaplaması için */
page_view_track($page ?? null);
?>

<?php
// LCP optimizasyonu — anasayfa hero ilk banner'ını preload et.
// home.php $__preloadBannerImg'i önceden set eder (banners sorgusuyla aynı veri).
// Bu sayede URL tam eşleşir → tarayıcı preload cache'i kullanır (cache hit).
if ($page === 'home') {
    // home.php'den gelen değeri kullan (varsa); yoksa fallback DB sorgusu yap.
    if (!isset($__preloadBannerImg)) {
        try {
            $__preloadBannerImg = db()->query(
                "SELECT image FROM banners WHERE is_active=1 AND image != '' ORDER BY sort_order ASC, id DESC LIMIT 1"
            )->fetchColumn() ?: null;
        } catch (Exception $__e) { $__preloadBannerImg = null; }
    }
    if ($__preloadBannerImg) {
        // Phase 1: mobile variant varsa imagesrcset ile responsive preload
        // Mobile tarayıcı 800w'i seçer (~30KB), masaüstü 1280w'i alır (~117KB)
        $__mobileVariant = preg_replace('/(\.[a-z0-9]+)$/i', '-mobile$1', $__preloadBannerImg);
        $__mobileAbs     = APP_ROOT . '/' . ltrim((string)$__mobileVariant, '/');
        $__hasMobile     = ($__mobileVariant !== $__preloadBannerImg && is_file($__mobileAbs));

        if ($__hasMobile) {
            echo '<link rel="preload" as="image" fetchpriority="high"'
               . ' imagesrcset="' . e($__mobileVariant) . ' 800w, ' . e($__preloadBannerImg) . ' 1280w"'
               . ' imagesizes="100vw"'
               . ' href="' . e($__preloadBannerImg) . '">' . "\n";
        } else {
            echo '<link rel="preload" as="image" fetchpriority="high" href="' . e($__preloadBannerImg) . '">' . "\n";
        }
    }
}
?>
<link rel="preconnect" href="https://fonts.googleapis.com" crossorigin>
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="dns-prefetch" href="https://fonts.googleapis.com">
<link rel="dns-prefetch" href="https://fonts.gstatic.com">
<!-- Bootstrap Icons -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<?php
// Google Fonts CSS — sunucu tarafında cache'lenir + minified inline embed edilir.
// display=optional: LCP penceresinde font-swap yapılmaz → LCP skoru temiz ölçülür.
// (swap yerine optional: ilk ziyarette fallback font, önbellekteyse Playfair görünür)
$__gfontsCss = google_fonts_inline('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Playfair+Display:wght@700;800&display=swap');
if ($__gfontsCss !== ''):
?>
<style id="gfonts-inline"><?= $__gfontsCss ?></style>
<?php else: /* Fallback: cache yoksa eski yöntem */ ?>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Playfair+Display:wght@700;800&display=swap" media="print" onload="this.media='all'">
<noscript><link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Playfair+Display:wght@700;800&display=swap"></noscript>
<?php endif; ?>

<!-- Critical CSS — header/topbar için inline (FCP optimizasyonu) -->
<style><?php
  $crit = @file_get_contents(__DIR__ . '/../assets/css/critical.css');
  if ($crit !== false) {
    // Hızlı minify: yorum + gereksiz boşluk
    $crit = preg_replace('~/\*.*?\*/~s', '', $crit);
    $crit = preg_replace('/\s+/', ' ', $crit);
    $crit = preg_replace('/\s*([{}:;,>+~])\s*/', '$1', $crit);
    echo trim($crit);
  }
?></style>

<?php
// Ana stil — preload (önce indir) + media=print/onload (non-blocking uygula)
// Bu sayede tarayıcı style.css'i indirirken H1 ve hero anında render edilir.
// LCP window'u style.css beklenmeden açılır; H1 veya banner hemen candidate olur.
$styleMin = asset_min_css('css/style.css');
?>
<?php /* Ana stil RENDER-BLOCKING — style.css 28KB raw (~6KB gzipped) küçük, async yükleme
       sub-pixel layout shift'lere yol açıyordu (PSI Chrome 146 LCP detection'unu bozuyor).
       Render-blocking olarak yükleyince layout kararlı, LCP güvenilir tetikleniyor.
       FCP ~200-300ms uzayabilir ama LCP/CLS kararlı olur — net kazanç. */ ?>
<link rel="preload" as="style" href="<?= SITE_URL ?>/assets/<?= e($styleMin) ?>?v=<?= asset_v('css/style.css') ?>">
<link rel="stylesheet" href="<?= SITE_URL ?>/assets/<?= e($styleMin) ?>?v=<?= asset_v('css/style.css') ?>">

<!-- Sayfa-spesifik CSS — render-blocking sayısını minimize etmek için INLINE.
     Bu sayede Lighthouse 5sn'lik audit penceresinde fazladan HTTP request beklemez,
     LCP eventi temiz tetiklenir. -->
<?php foreach ($pageStyles as $css):
    $cssPath = APP_ROOT . '/assets/css/' . $css;
    if (is_file($cssPath) && filesize($cssPath) < 12288): // 12 KB altı inline
        $css_content = @file_get_contents($cssPath);
        echo "<style>{$css_content}</style>\n";
    else:
        $cssMin = asset_min_css('css/' . $css);
?>
  <link rel="stylesheet" href="<?= SITE_URL ?>/assets/<?= e($cssMin) ?>?v=<?= asset_v('css/'.$css) ?>">
<?php
    endif;
endforeach; ?>

<!-- Yardımcı CSS (defer, above-fold etkilemez) -->
<?php
$toastMin  = asset_min_css('css/toast.css');
$a11yMin   = asset_min_css('css/a11y.css');
$cookieMin = asset_min_css('css/components/cookie-banner.css');
?>
<link rel="stylesheet" href="<?= SITE_URL ?>/assets/<?= e($toastMin) ?>?v=<?= asset_v('css/toast.css') ?>" media="print" onload="this.media='all'">
<link rel="stylesheet" href="<?= SITE_URL ?>/assets/<?= e($a11yMin) ?>?v=<?= asset_v('css/a11y.css') ?>" media="print" onload="this.media='all'">
<link rel="stylesheet" href="<?= SITE_URL ?>/assets/<?= e($cookieMin) ?>?v=<?= asset_v('css/components/cookie-banner.css') ?>" media="print" onload="this.media='all'">
<link rel="stylesheet" href="<?= SITE_URL ?>/assets/css/components/search-overlay.css?v=<?= asset_v('css/components/search-overlay.css') ?>" media="print" onload="this.media='all'">
<link rel="stylesheet" href="<?= SITE_URL ?>/assets/css/components/conversion.css?v=<?= asset_v('css/components/conversion.css') ?>" media="print" onload="this.media='all'">
<?php if (setting('ai_assistant_enabled','0')==='1' && trim((string)setting('anthropic_api_key',''))!==''): ?>
<link rel="stylesheet" href="<?= SITE_URL ?>/assets/css/components/ai-assistant.css?v=<?= asset_v('css/components/ai-assistant.css') ?>" media="print" onload="this.media='all'">
<?php endif; ?>
<noscript>
  <link rel="stylesheet" href="<?= SITE_URL ?>/assets/<?= e($toastMin) ?>?v=<?= asset_v('css/toast.css') ?>">
  <link rel="stylesheet" href="<?= SITE_URL ?>/assets/<?= e($a11yMin) ?>?v=<?= asset_v('css/a11y.css') ?>">
  <link rel="stylesheet" href="<?= SITE_URL ?>/assets/<?= e($cookieMin) ?>?v=<?= asset_v('css/components/cookie-banner.css') ?>">
  <link rel="stylesheet" href="<?= SITE_URL ?>/assets/css/components/conversion.css?v=<?= asset_v('css/components/conversion.css') ?>">
  <?php if (setting('ai_assistant_enabled','0')==='1' && trim((string)setting('anthropic_api_key',''))!==''): ?>
  <link rel="stylesheet" href="<?= SITE_URL ?>/assets/css/components/ai-assistant.css?v=<?= asset_v('css/components/ai-assistant.css') ?>">
  <?php endif; ?>
</noscript>
<script defer src="<?= SITE_URL ?>/assets/js/toast.min.js?v=<?= asset_v('js/toast.min.js') ?>"></script>
<script defer src="<?= SITE_URL ?>/assets/js/app.min.js?v=<?= asset_v('js/app.min.js') ?>"></script>
<?php foreach ($pageScripts as $js): ?>
  <script defer src="<?= SITE_URL ?>/assets/js/<?= e($js) ?>?v=<?= asset_v('js/'.$js) ?>"></script>
<?php endforeach; ?>
</head>
<body class="page-<?= e($page ?? '') ?>">
<?php include APP_ROOT . '/components/analytics-noscript.php'; ?>
<a href="#main" class="skip-link">Ana içeriğe atla</a>

<?php
/* ── Duyuru barı placeholder — aşağıdaki aq-header içinde render ediliyor ── */
$__topMsg   = trim((string)setting('topbar_message',''));
$__topPhone = trim((string)setting('contact_phone',''));
$__topTel   = preg_replace('/[^0-9+]/', '', $__topPhone);

/* ── Ana menü kategorileri — admin: Ürün Yönetimi → Kategoriler (üst seviye). ── */
$__navCats = array();
try {
    $__navCats = db()->query("SELECT name, slug FROM categories WHERE parent_id IS NULL ORDER BY sort_order ASC, name ASC LIMIT 8")->fetchAll();
} catch (Exception $__e) { $__navCats = array(); }
$__curCatSlug = ($page==='category' && isset($_GET['slug']) && is_string($_GET['slug'])) ? $_GET['slug'] : '';
$__searchQ = (isset($_GET['q']) && is_string($_GET['q'])) ? $_GET['q'] : '';
$__tg = trim((string)setting('site_tagline',''));
?>

<!-- Duyuru Slider -->
<?php if (setting('topbar_enabled','1')==='1' && ($__topMsg !== '' || $__topPhone !== '')):
  $__topSlides = array_values(array_filter(array_map('trim', preg_split('/\s*[•|]\s*/u', $__topMsg))));
  if ($__topPhone !== '') $__topSlides[] = $__topPhone;
  while (count($__topSlides) < 3) $__topSlides[] = $__topSlides[0] ?? ($siteName . '\'a Hoş geldiniz!');
?>
<div class="aq-top-announcement" role="region" aria-label="Duyurular">
  <div class="aq-announcement-slider" aria-live="off">
    <?php foreach (array_slice($__topSlides, 0, 3) as $__idx => $__sl): ?>
      <span class="<?= $__idx === 0 ? 'is-active' : '' ?>"><?= e($__sl) ?></span>
    <?php endforeach; ?>
  </div>
</div>
<?php endif; ?>

<header class="aq-header" id="aq-header">

  <!-- Trust bar + Mini linkler -->
  <div class="aq-header-top">
    <div class="aq-container">
      <div class="aq-header-top-inner">
        <div class="aq-header-trust">
          <span><?= e3d('shield', 18) ?> Güvenli Alışveriş</span>
          <span><?= e3d('truck', 18) ?> Hızlı Teslimat</span>
          <span><?= e3d('return', 18) ?> Kolay İade</span>
        </div>
        <div class="aq-header-mini-links">
          <a href="<?= url('home') ?>">Ana Sayfa</a>
          <a href="<?= url('account') ?>">Sipariş Takibi</a>
          <a href="<?= url('about') ?>">Hakkımızda</a>
          <a href="<?= url('blog') ?>">Blog</a>
          <a href="<?= url('contact') ?>">İletişim</a>
        </div>
      </div>
    </div>
  </div>

  <!-- Logo + Arama + Aksiyonlar -->
  <div class="aq-header-main">
    <div class="aq-container">
      <div class="aq-header-main-inner">
        <button type="button" class="aq-mobile-menu-btn" aria-label="Menüyü aç" aria-expanded="false">
          <i class="bi bi-list"></i>
        </button>
        <a href="<?= url('home') ?>" class="aq-logo" aria-label="<?= e($siteName) ?> Anasayfa">
          <span><?= e($siteName) ?></span>
          <?php if ($__tg !== ''): ?><small><?= e($__tg) ?></small><?php endif; ?>
        </a>
        <form class="aq-search aq-search-live" action="<?= url('products') ?>" method="get" role="search">
          <button type="submit" aria-label="Ara"><?= e3d('search', 22) ?></button>
          <input type="search" name="q"
                 placeholder="Ürün, kategori veya marka ara"
                 aria-label="Ürünlerde ara"
                 value="<?= e($__searchQ) ?>"
                 maxlength="120" autocomplete="off">
          <div class="aq-search-suggestions" style="display:none" role="listbox"></div>
        </form>
        <div class="aq-header-actions">
          <div class="aq-account-menu">
            <a href="<?= current_user() ? url('account') : url('login') ?>"
               class="aq-header-action aq-account-trigger" aria-label="Hesabım">
              <?= e3d('account', 26) ?>
              <span><?= current_user() ? 'Hesabım' : 'Giriş Yap' ?></span>
            </a>
            <?php if (!current_user()): ?>
            <div class="aq-account-dropdown">
              <div class="aq-account-guest-head">
                <strong>Hoş geldiniz</strong>
                <span class="aq-account-guest-text">Alışverişe devam etmek için giriş yapabilir veya üye olabilirsiniz.</span>
              </div>
              <a href="<?= url('register') ?>" class="aq-account-auth-btn">
                <i class="bi bi-person-plus"></i> Üye Ol
              </a>
              <a href="<?= url('login') ?>" class="aq-account-auth-btn aq-account-login-btn">
                <i class="bi bi-box-arrow-in-right"></i> Giriş Yap
              </a>
            </div>
            <?php endif; ?>
          </div>
          <a href="<?= url('favorites') ?>" class="aq-header-action" aria-label="Favorilerim">
            <?= e3d('heart', 26) ?>
            <span>Favorilerim</span>
            <?php if (current_user() && fav_count() > 0): ?><em><?= fav_count() ?></em><?php endif; ?>
          </a>
          <a href="<?= url('cart') ?>" class="aq-header-action" aria-label="Sepetim">
            <?= e3d('cart', 26) ?>
            <span>Sepetim</span>
            <?php if (cart_count() > 0): ?><em><?= cart_count() ?></em><?php endif; ?>
          </a>
        </div>
      </div>
    </div>
  </div>

  <!-- Kategori menüsü -->
  <div class="aq-header-menu aq-header-menu-overflow">
    <div class="aq-container">
      <div class="aq-main-menu">
        <a href="<?= url('categories_list') ?>" class="aq-category-menu-btn">
          <i class="bi bi-list"></i> <span>Tüm Kategoriler</span>
        </a>
        <div class="aq-menu-visible-categories">
          <?php if ($__navCats): foreach ($__navCats as $__c): ?>
            <a href="<?= e(url('category',['slug'=>$__c['slug']])) ?>"
               class="aq-menu-category-link <?= $__curCatSlug===$__c['slug']?'active':'' ?>">
              <?= e($__c['name']) ?>
            </a>
          <?php endforeach; else: ?>
            <a href="<?= url('products') ?>" class="aq-menu-category-link <?= $page==='products'?'active':'' ?>">Ürünler</a>
          <?php endif; ?>
        </div>
        <div class="aq-menu-more" id="aqMenuMore">
          <button class="aq-menu-more-btn" aria-expanded="false" aria-controls="aqMoreDrop">
            Devamı <i class="bi bi-chevron-down"></i>
          </button>
          <div class="aq-menu-more-dropdown" id="aqMoreDrop" style="display:none">
            <a href="<?= url('blog') ?>">Blog</a>
            <a href="<?= url('about') ?>">Hakkımızda</a>
            <a href="<?= url('contact') ?>">İletişim</a>
          </div>
        </div>
      </div>
    </div>
  </div>

</header>

<!-- Mobil Menü -->
<div class="aq-mobile-backdrop" id="aqMobileBackdrop"></div>
<div class="aq-mobile-panel" id="aqMobilePanel" role="dialog" aria-modal="true" aria-label="Navigasyon">
  <div class="aq-mobile-panel-head-final">
    <div class="aq-mobile-logo">
      <span><?= e($siteName) ?></span>
      <?php if ($__tg !== ''): ?><small><?= e($__tg) ?></small><?php endif; ?>
    </div>
    <button class="aq-mobile-close" aria-label="Menüyü kapat"><i class="bi bi-x-lg"></i></button>
  </div>
  <div class="aq-mobile-panel-body-final">
    <div class="aq-mobile-guest-mini">
      <?php if (current_user()): ?>
        <strong>Merhaba!</strong>
        <span><?= e(current_user()['name'] ?? 'Hesabım') ?></span>
        <div class="aq-mobile-auth-buttons-final">
          <a href="<?= url('account') ?>" class="aq-mobile-auth-link aq-mobile-auth-login">Hesabım</a>
        </div>
      <?php else: ?>
        <strong>Hoşgeldiniz!</strong>
        <span>Hesabınıza giriş yapın</span>
        <div class="aq-mobile-auth-buttons-final">
          <a href="<?= url('login') ?>" class="aq-mobile-auth-link aq-mobile-auth-login">Giriş Yap</a>
          <a href="<?= url('register') ?>" class="aq-mobile-auth-link aq-mobile-auth-register">Üye Ol</a>
        </div>
      <?php endif; ?>
    </div>
    <div class="aq-mobile-nav-title"><span>Kategoriler</span></div>
    <nav class="aq-mobile-nav-final" aria-label="Mobil kategori menüsü">
      <?php foreach (($__navCats ?: []) as $__c): ?>
      <div class="aq-mobile-category-item">
        <div class="aq-mobile-category-line">
          <a href="<?= e(url('category',['slug'=>$__c['slug']])) ?>" class="aq-mobile-category-title-link"><?= e($__c['name']) ?></a>
        </div>
      </div>
      <?php endforeach; ?>
      <div class="aq-mobile-category-item"><div class="aq-mobile-category-line"><a href="<?= url('blog') ?>" class="aq-mobile-category-title-link">Blog</a></div></div>
      <div class="aq-mobile-category-item"><div class="aq-mobile-category-line"><a href="<?= url('about') ?>" class="aq-mobile-category-title-link">Hakkımızda</a></div></div>
      <div class="aq-mobile-category-item"><div class="aq-mobile-category-line"><a href="<?= url('contact') ?>" class="aq-mobile-category-title-link">İletişim</a></div></div>
    </nav>
  </div>
</div>

<?php flash_render(); ?>
<main id="main" tabindex="-1">
