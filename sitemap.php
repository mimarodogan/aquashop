<?php
/**
 * Sitemap — index ve alt sitemap'ler tek dosyada.
 *
 * URL'ler:
 *   /sitemap.xml           → index (varsayılan)
 *   /sitemap.xml?type=static     → ana sayfa, ürünler, blog, hakkımızda, iletişim
 *   /sitemap.xml?type=products   → tüm aktif ürünler (image extension dahil)
 *   /sitemap.xml?type=categories → tüm kategoriler
 *   /sitemap.xml?type=blog       → yayında blog yazıları
 *   /sitemap.xml?type=pages      → CMS sayfaları
 *
 * router.php / .htaccess /sitemap.xml → /sitemap.php yönlendirir.
 *
 * Geri uyum: ?type yoksa eski "tek-büyük-sitemap" döner (lastmod, image extension).
 * Yeni Search Console'da index URL'i kullanılır.
 */
require_once __DIR__ . '/core/bootstrap.php';
require_once __DIR__ . '/models/Seo.php';  // seo_get() — template-level noindex kontrolü için
header('Content-Type: application/xml; charset=utf-8');

/**
 * seo_settings.meta_robots template-level kontrolü.
 * Admin 'product' / 'category' / 'blog' / 'page' template'ini noindex yaptıysa
 * o sitemap bölümü boş döner (çelişen sinyali önler).
 */
function sm_is_noindex(string $pageSlug): bool {
    if (!function_exists('seo_get')) return false;
    $s = seo_get($pageSlug);
    if (!$s || empty($s['meta_robots'])) return false;
    return stripos($s['meta_robots'], 'noindex') !== false;
}

// Base URL: SITE_URL sabiti doluysa kullan, yoksa canlı domain'den türet.
// setting('site_url') kasıtla kullanılmıyor — DB'de eski domain kalabilir.
$base = (defined('SITE_URL') && SITE_URL !== '')
    ? rtrim(SITE_URL, '/')
    : ((isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https' : 'http')
       . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost'));

function sm_abs(string $url): string {
    global $base;
    if (preg_match('~^https?://~i', $url)) return $url;
    return $base . '/' . ltrim($url, '/');
}

function sm_url(string $loc, ?string $lastmod = null, string $priority = '0.5', string $freq = 'weekly', array $images = []): void {
    echo "  <url>\n";
    echo "    <loc>" . htmlspecialchars($loc) . "</loc>\n";
    if ($lastmod) echo "    <lastmod>" . date('Y-m-d', strtotime($lastmod)) . "</lastmod>\n";
    echo "    <changefreq>$freq</changefreq>\n";
    echo "    <priority>$priority</priority>\n";
    foreach ($images as $img) {
        echo "    <image:image>\n";
        echo "      <image:loc>" . htmlspecialchars(sm_abs($img['url'])) . "</image:loc>\n";
        if (!empty($img['title']))   echo "      <image:title>" . htmlspecialchars($img['title']) . "</image:title>\n";
        if (!empty($img['caption'])) echo "      <image:caption>" . htmlspecialchars($img['caption']) . "</image:caption>\n";
        echo "    </image:image>\n";
    }
    echo "  </url>\n";
}

function sm_open_urlset(): void {
    echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
    echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"' . "\n";
    echo '        xmlns:image="http://www.google.com/schemas/sitemap-image/1.1">' . "\n";
}
function sm_close_urlset(): void { echo '</urlset>'; }

/* ─────────────────────────────────────────────────────────────── */

$type = $_GET['type'] ?? 'index';

/* ── 1) Index (varsayılan) ───────────────────────────────────── */
if ($type === 'index') {
    // Her sitemap için en son updated_at'i bul → lastmod
    $modProducts = '';
    $modBlog = '';
    $modPages = '';
    try { $modProducts = (string)db()->query("SELECT MAX(GREATEST(COALESCE(updated_at,created_at), created_at)) FROM products WHERE is_active=1")->fetchColumn(); } catch (\Throwable $e) {}
    try { $modBlog     = (string)db()->query("SELECT MAX(GREATEST(COALESCE(updated_at,created_at), created_at)) FROM blog_posts WHERE is_published=1")->fetchColumn(); } catch (\Throwable $e) {}
    try { $modPages    = (string)db()->query("SELECT MAX(updated_at) FROM pages WHERE is_published=1")->fetchColumn(); } catch (\Throwable $e) {}

    echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
    echo '<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
    foreach ([
        ['loc' => '/sitemap.xml?type=static',     'mod' => null],
        ['loc' => '/sitemap.xml?type=products',   'mod' => $modProducts],
        ['loc' => '/sitemap.xml?type=categories', 'mod' => null],
        ['loc' => '/sitemap.xml?type=blog',       'mod' => $modBlog],
        ['loc' => '/sitemap.xml?type=pages',      'mod' => $modPages],
    ] as $sm) {
        echo "  <sitemap>\n";
        echo "    <loc>" . htmlspecialchars($base . $sm['loc']) . "</loc>\n";
        if ($sm['mod']) echo "    <lastmod>" . date('Y-m-d', strtotime($sm['mod'])) . "</lastmod>\n";
        echo "  </sitemap>\n";
    }
    echo '</sitemapindex>';
    exit;
}

/* ── 2) Statik sayfalar ──────────────────────────────────────── */
if ($type === 'static') {
    sm_open_urlset();
    sm_url(url('home'),     null, '1.0', 'daily');
    sm_url(url('products'), null, '0.9', 'daily');
    sm_url(url('blog'),     null, '0.7', 'weekly');
    sm_url(url('about'),    null, '0.5', 'monthly');
    sm_url(url('contact'),  null, '0.5', 'monthly');
    sm_close_urlset();
    exit;
}

/* ── 3) Ürünler ─────────────────────────────────────────────── */
if ($type === 'products') {
    sm_open_urlset();
    // Template noindex → boş urlset (çelişen sinyali önler)
    if (sm_is_noindex('product')) { sm_close_urlset(); exit; }
    try {
        // lastmod için updated_at/created_at deneyelim; sütun yoksa basit sorguyufallback olarak kullan
        $hasLastmod = false;
        try {
            $st = db()->query('SELECT id, name, slug, image,
                                      COALESCE(updated_at, created_at) AS m
                               FROM products WHERE is_active=1 AND deleted_at IS NULL ORDER BY id DESC');
            $hasLastmod = true;
        } catch (\Throwable $e) {
            // deleted_at veya updated_at/created_at sütunu yoksa basit sorgu
            try {
                $st = db()->query('SELECT id, name, slug, image FROM products WHERE is_active=1 ORDER BY id DESC');
            } catch (\Throwable $e2) { $st = new ArrayObject(); }
        }
        foreach ($st as $p) {
            $images = [];
            if (!empty($p['image'])) {
                $images[] = ['url'=>$p['image'], 'title'=>$p['name']];
            }
            try {
                $g = db()->prepare('SELECT path FROM product_images WHERE product_id=? ORDER BY sort_order ASC LIMIT 10');
                $g->execute([(int)$p['id']]);
                foreach ($g->fetchAll() as $gi) {
                    if (!empty($gi['path'])) $images[] = ['url'=>$gi['path'], 'title'=>$p['name']];
                }
            } catch (\Throwable $e) {}
            sm_url(url('product', ['slug'=>$p['slug']]), ($hasLastmod && !empty($p['m'])) ? $p['m'] : null, '0.8', 'weekly', $images);
        }
    } catch (\Throwable $e) {}
    sm_close_urlset();
    exit;
}

/* ── 4) Kategoriler ─────────────────────────────────────────── */
if ($type === 'categories') {
    sm_open_urlset();
    if (sm_is_noindex('category')) { sm_close_urlset(); exit; }
    try {
        $hasLastmodC = false;
        try {
            $stC = db()->query('SELECT name, slug, image, COALESCE(updated_at, created_at) AS m FROM categories ORDER BY sort_order ASC, name ASC');
            $hasLastmodC = true;
        } catch (\Throwable $e) {
            $stC = db()->query('SELECT name, slug, image FROM categories ORDER BY sort_order ASC, name ASC');
        }
        foreach ($stC as $c) {
            $images = [];
            if (!empty($c['image'])) $images[] = ['url'=>$c['image'], 'title'=>$c['name']];
            sm_url(url('category', ['slug'=>$c['slug']]), ($hasLastmodC && !empty($c['m'])) ? $c['m'] : null, '0.7', 'weekly', $images);
        }
    } catch (\Throwable $e) {}
    sm_close_urlset();
    exit;
}

/* ── 5) Blog yazıları ───────────────────────────────────────── */
if ($type === 'blog') {
    sm_open_urlset();
    if (sm_is_noindex('blog_post') || sm_is_noindex('blog')) { sm_close_urlset(); exit; }
    try {
        foreach (db()->query('SELECT title, slug, excerpt, cover_image,
                              COALESCE(updated_at, published_at, created_at) AS m
                              FROM blog_posts WHERE is_published=1 ORDER BY m DESC') as $bp) {
            $images = [];
            if (!empty($bp['cover_image'])) {
                $images[] = ['url'=>$bp['cover_image'], 'title'=>$bp['title'], 'caption'=>$bp['excerpt'] ?? ''];
            }
            sm_url(url('blog_post', ['slug'=>$bp['slug']]), $bp['m'], '0.7', 'monthly', $images);
        }
    } catch (\Throwable $e) {}
    sm_close_urlset();
    exit;
}

/* ── 6) CMS sayfaları ───────────────────────────────────────── */
if ($type === 'pages') {
    sm_open_urlset();
    if (sm_is_noindex('page')) { sm_close_urlset(); exit; }
    try {
        foreach (db()->query('SELECT slug, updated_at FROM pages WHERE is_published=1 ORDER BY updated_at DESC') as $pg) {
            sm_url(url('page', ['slug'=>$pg['slug']]), $pg['updated_at'], '0.5', 'monthly');
        }
    } catch (\Throwable $e) {}
    sm_close_urlset();
    exit;
}

/* Bilinmeyen tip → boş geçerli XML */
sm_open_urlset();
sm_close_urlset();
