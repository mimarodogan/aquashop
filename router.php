<?php
/**
 * Front Controller / Router
 * .htaccess'in tüm istekleri buraya yönlendirdiği merkezi giriş noktası.
 * URL'i parçalara ayırıp ilgili sayfaya yönlendirir; slug değerlerini $_GET'e yazar.
 */
require_once __DIR__ . '/core/bootstrap.php';
require_once __DIR__ . '/core/route_helpers.php';
require_once __DIR__ . '/includes/redirects.php';

// Google tracking parametrelerini temizle (srsltid, gclid vb.) → 301 ile temiz URL'e yönlendir
$_trackingParams = ['srsltid', 'gclid', 'gbraid', 'wbraid'];
$_hasTracking = false;
foreach ($_trackingParams as $_tp) { if (isset($_GET[$_tp])) { $_hasTracking = true; break; } }
if ($_hasTracking) {
    $_cleanParams = array_diff_key($_GET, array_flip($_trackingParams));
    $_scheme = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $_cleanUrl = $_scheme . '://' . $_SERVER['HTTP_HOST']
               . parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH)
               . ($_cleanParams ? '?' . http_build_query($_cleanParams) : '');
    header('Location: ' . $_cleanUrl, true, 301);
    exit;
}
unset($_trackingParams, $_hasTracking, $_tp, $_cleanParams, $_scheme, $_cleanUrl);

// URL yönlendirmeleri kontrol — eşleşirse 301/302 ile çıkar
redirect_check();

// İstenen URL'i çöz
$rawUri = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '/';
$uriPath = parse_url($rawUri, PHP_URL_PATH);
$uriPath = trim($uriPath, '/');

// SITE_URL alt klasördeyse (ör. /shop), önek temizle
if (defined('SITE_URL') && SITE_URL !== '') {
    $base = trim(SITE_URL, '/');
    if ($base !== '' && strpos($uriPath, $base) === 0) {
        $uriPath = ltrim(substr($uriPath, strlen($base)), '/');
    }
}

$segments = $uriPath === '' ? array() : array_values(array_filter(explode('/', $uriPath), 'strlen'));

// Türkçe karakter normalizasyonu — iki senaryo:
// 1. Yüzde kodlu hâli: %C4%B1 (ı), %C3%BC (ü), %C3%A7 (ç) vb. → str_ireplace ile yakala
// 2. Sunucu önceden decode etmişse raw hâli: ı, ü, ç vb. → preg_replace /u ile yakala
$_trPc = ['%C3%A7','%C3%87','%C4%9F','%C4%9E','%C4%B1','%C4%B0',
          '%C3%B6','%C3%96','%C5%9F','%C5%9E','%C3%BC','%C3%9C'];
$_trAs = ['c',     'c',     'g',     'g',     'i',     'i',
          'o',     'o',     's',     's',     'u',     'u'    ];
$_normPath = str_ireplace($_trPc, $_trAs, $uriPath);
if ($_normPath !== $uriPath) {
    $_qs = parse_url($rawUri, PHP_URL_QUERY);
    header('Location: /' . ltrim($_normPath, '/') . ($_qs ? '?' . $_qs : ''), true, 301);
    exit;
}
$_dec     = rawurldecode($uriPath);
$_normDec = preg_replace(
    ['/[çÇ]/u', '/[ğĞ]/u', '/[ıİ]/u', '/[öÖ]/u', '/[şŞ]/u', '/[üÜ]/u'],
    ['c',        'g',        'i',        'o',        's',        'u'       ],
    $_dec
);
if ($_normDec !== $_dec) {
    $_qs = parse_url($rawUri, PHP_URL_QUERY);
    header('Location: /' . ltrim($_normDec, '/') . ($_qs ? '?' . $_qs : ''), true, 301);
    exit;
}
unset($_trPc, $_trAs, $_normPath, $_dec, $_normDec, $_qs);

// URL encode'lu karakterleri çöz (ör. %20 → boşluk)
$segments = array_map('rawurldecode', $segments);

$first  = $segments[0] ?? '';
$second = $segments[1] ?? '';
$third  = $segments[2] ?? '';

/**
 * Sayfayı dahil et ve sonlandır.
 */
function dispatch($file) {
    $abs = __DIR__ . '/' . ltrim($file, '/');
    if (!is_file($abs)) {
        http_response_code(404);
        $abs = __DIR__ . '/pages/404.php';
        if (!is_file($abs)) {
            echo '<h1>404 — Sayfa bulunamadı</h1>';
            exit;
        }
    }
    require $abs;
    exit;
}

// =====================================================================
// 1) Anasayfa
// =====================================================================
if ($first === '' || $first === 'home' || $first === 'index.php') {
    dispatch('pages/home.php');
}

// =====================================================================
// 2) Eski .php URL'leri — kullanıcı doğrudan /products.php gibi gelirse
//    .htaccess !-f kuralı zaten root shim'lere yönlendirir. Buraya gelmez.
//    Yine de sağlamlık için manuel haritalama:
// =====================================================================
$phpFiles = array(
    'index.php'=>'pages/home.php', 'products.php'=>'pages/products.php',
    'product.php'=>'pages/product.php', 'cart.php'=>'pages/cart.php',
    'checkout.php'=>'pages/checkout.php', 'login.php'=>'pages/login.php',
    'register.php'=>'pages/register.php', 'logout.php'=>'pages/logout.php',
    'account.php'=>'pages/account.php', 'favorites.php'=>'pages/favorites.php',
    'compare.php'=>'pages/compare.php',
    'about.php'=>'pages/about.php', 'contact.php'=>'pages/contact.php',
    'blog.php'=>'pages/blog.php', 'post.php'=>'pages/post.php',
    'page.php'=>'pages/page.php', 'order.php'=>'pages/order.php',
    'unsubscribe.php'=>'pages/unsubscribe.php',
    'payment_callback.php'=>'pages/payment_callback.php',
    'forgot_password.php'=>'pages/forgot_password.php',
    'reset_password.php'=>'pages/reset_password.php',
    'restock-notify.php'=>'restock-notify.php',
    'return_request.php'=>'pages/return_request.php',
    'review-submit.php'=>'review-submit.php',
);
if (isset($phpFiles[$first])) dispatch($phpFiles[$first]);

// =====================================================================
// 3) Pretty URL eşlemeleri
// =====================================================================
switch ($first) {

    /* /kategoriler/{slug}    → kategori landing sayfası
       /kategoriler           → tüm kategoriler listesi */
    case 'kategoriler':
    case 'categories':
        if ($second !== '') {
            $_GET['cat'] = $second;
            dispatch('pages/category.php');
        }
        // /kategoriler (slug yok) → kategoriler listesi sayfası
        dispatch('pages/categories-list.php');

    /* /products              → tüm ürünler
       /products/{slug}       → ürün detay
       /products/category/{cat-slug} → kategori filtreli liste
    */
    case 'products':
    case 'urun':
        if ($second === '') dispatch('pages/products.php');
        if ($second === 'category' || $second === 'kategori') {
            // Eski URL → yeni /kategoriler/{slug} 301 yönlendirme
            if ($third !== '') {
                header('Location: ' . url('category', ['slug' => $third]), true, 301);
                exit;
            }
            dispatch('pages/products.php');
        }
        // /products/{slug} → ürün veya kategori olabilir (önbellekli kontrol)
        if (route_is_product_slug($second)) {
            $_GET['slug'] = $second;
            dispatch('pages/product.php');
        }
        if (route_is_category_slug($second)) {
            // Eski /urun/{cat-slug} → yeni /kategoriler/{slug} 301 yönlendirme
            header('Location: ' . url('category', ['slug' => $second]), true, 301);
            exit;
        }
        break;

    /* /blog                 → liste
       /blog/category/{slug} → kategori filtreli
       /blog/{slug}          → yazı detay
    */
    case 'blog':
        if ($second === '') dispatch('pages/blog.php');
        if ($second === 'category' || $second === 'kategori') {
            $_GET['cat'] = $third;
            dispatch('pages/blog.php');
        }
        if (route_is_blog_post_slug($second)) {
            $_GET['slug'] = $second;
            dispatch('pages/post.php');
        }
        if (route_is_blog_category_slug($second)) {
            $_GET['cat'] = $second;
            dispatch('pages/blog.php');
        }
        break;

    /* /sayfa/{slug} veya /page/{slug} → CMS sayfası */
    case 'page':
    case 'sayfa':
        if ($second !== '') {
            $_GET['slug'] = $second;
            dispatch('pages/page.php');
        }
        break;

    /* /order/{id} → eski sipariş detay alias'ı */
    case 'order':
        if (ctype_digit($second)) {
            $_GET['id'] = $second;
            dispatch('pages/order.php');
        }
        break;

    /* /odeme         → checkout (sepet sonrası ödeme adımı)
       /odeme/{id}    → tamamlanmış sipariş detayı */
    case 'odeme':
        if (ctype_digit($second)) {
            $_GET['id'] = $second;
            dispatch('pages/order.php');
        }
        dispatch('pages/checkout.php');

    /* iyzico Checkout Form callback */
    case 'odeme-donus':
    case 'payment-callback':
        dispatch('pages/payment_callback.php');

    /* Favicon — admin'den yüklenmişse oraya yönlendir, yoksa 1x1 boş PNG */
    case 'favicon.ico':
    case 'favicon.png':
        $favPath = trim((string)setting('favicon_path',''));
        if ($favPath !== '' && (strpos($favPath, '://') !== false || file_exists(__DIR__ . '/' . ltrim($favPath, '/')))) {
            header('Location: ' . $favPath, true, 302);
            exit;
        }
        // Boş 1x1 PNG fallback (bot'lar 404 görmesin)
        header('Content-Type: image/png');
        header('Cache-Control: public, max-age=86400');
        echo base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkAAIAAAoAAv/lxKUAAAAASUVORK5CYII=');
        exit;

    /* Şifre sıfırlama */
    case 'sifremi-unuttum':
    case 'forgot-password':
        dispatch('pages/forgot_password.php');
    case 'sifre-sifirla':
    case 'reset-password':
        dispatch('pages/reset_password.php');
    case 'iade-talebi':
    case 'return':
        dispatch('pages/return_request.php');

    /* Sade URL'ler */
    case 'sepet':       dispatch('pages/cart.php');
    case 'giris':       dispatch('pages/login.php');
    case 'uye-ol':      dispatch('pages/register.php');
    case 'cikis':       dispatch('pages/logout.php');
    case 'hesabim':     dispatch('pages/account.php');
    case 'favoriler':   dispatch('pages/favorites.php');
    case 'karsilastir': dispatch('pages/compare.php');
    case 'iletisim':    dispatch('pages/contact.php');
    case 'hakkimizda':  dispatch('pages/about.php');
    case 'aboneligi-iptal-et': dispatch('pages/unsubscribe.php');

    /* Admin paneli */
    case 'admin':
    case 'admin_panel':
    case 'yonetim':
        $rest = implode('/', array_slice($segments, 1));
        if ($rest === '' || $rest === 'index.php') dispatch('admin_panel/index.php');
        // K-1 GÜVENLİK: Path traversal koruması — '..' ve null byte içeren segment'leri reddet
        if (strpos($rest, '..') !== false || strpos($rest, "\0") !== false || strpos($rest, '/.') === 0) {
            http_response_code(404); exit;
        }
        // Sadece güvenli karakterler (slug + slash + .php)
        if (!preg_match('~^[A-Za-z0-9_\-/.]+$~', $rest)) {
            http_response_code(404); exit;
        }
        // Subfolder dosyaları (örn. admin/products/list)
        $candidates = array(
            'admin_panel/' . $rest,
            'admin_panel/' . $rest . '.php',
        );
        $adminBase = realpath(__DIR__ . '/admin_panel');
        if ($adminBase === false) { http_response_code(500); exit; }
        $adminBase .= DIRECTORY_SEPARATOR;
        foreach ($candidates as $c) {
            $real = realpath(__DIR__ . '/' . $c);
            // realpath başarısızsa veya admin_panel/ dışına çıkıyorsa atla
            if ($real === false) continue;
            if (strncmp($real, $adminBase, strlen($adminBase)) !== 0) continue;
            if (is_file($real)) dispatch($c);
        }
        break;

    /* Tek-segment CMS sayfası: /kvkk, /sss, /kargo-teslimat vb. */
    default:
        if ($second === '' && $first !== '') {
            if (route_is_cms_page_slug($first)) {
                $_GET['slug'] = $first;
                dispatch('pages/page.php');
            }
            // Mağaza ürün slug'ı kısa yol
            if (route_is_product_slug($first)) {
                $_GET['slug'] = $first;
                dispatch('pages/product.php');
            }
        }
}

// 404
http_response_code(404);
$page = '404'; $title = 'Sayfa Bulunamadı';
include __DIR__ . '/components/header.php';
echo '<section class="container" style="padding:120px 0;text-align:center"><h1>404</h1><p class="muted" style="margin:14px 0 24px">Aradığınız sayfa bulunamadı.</p><a class="btn btn-primary" href="' . e(SITE_URL) . '/">Anasayfa</a></section>';
include __DIR__ . '/components/footer.php';
