<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/sidebar_counts.php';

$page  = $page  ?? '';
$group = $group ?? ''; // alt menü gruplarının açık kalması için (sales/catalog/content/crm/system)
$title = $title ?? 'Yönetim';

// Admin paneli kökü ('/admin_panel')
$AP = SITE_URL . '/admin_panel';

// Sidebar bildirim sayaçları
$_C = admin_sidebar_counts();

/**
 * Menü item helper'ı — link, ikon, aktif durum, opsiyonel badge.
 */
function ap_link(string $href, string $label, string $icon, bool $active, int $badge = 0, string $cls = ''): string {
    $cls    = trim('menu-link ' . $cls . ($active ? ' active' : ''));
    $badgeH = $badge > 0
        ? '<span class="menu-badge">' . $badge . '</span>'
        : '';
    return '<a href="' . htmlspecialchars($href, ENT_QUOTES) . '" class="' . $cls . '">'
         . '<span class="menu-ico">' . $icon . '</span>'
         . '<span class="menu-label">' . htmlspecialchars($label) . '</span>'
         . $badgeH
         . '</a>';
}

// SVG ikon kütüphanesi (inline — CSP'siz, ek HTTP yok)
$ICO = [
    'dashboard' => '<svg viewBox="0 0 24 24"><rect x="3" y="3" width="7" height="9"/><rect x="14" y="3" width="7" height="5"/><rect x="14" y="12" width="7" height="9"/><rect x="3" y="16" width="7" height="5"/></svg>',
    'orders'    => '<svg viewBox="0 0 24 24"><path d="M6 2 3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4Z"/><path d="M3 6h18"/><path d="M16 10a4 4 0 0 1-8 0"/></svg>',
    'pending'   => '<svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg>',
    'coupon'    => '<svg viewBox="0 0 24 24"><path d="M20 12V7H4v10h16v-5"/><path d="M9 11v2"/><path d="M14 11v2"/></svg>',
    'product'   => '<svg viewBox="0 0 24 24"><path d="M21 7 12 2 3 7v10l9 5 9-5V7Z"/><path d="m3 7 9 5 9-5"/><path d="M12 22V12"/></svg>',
    'category'  => '<svg viewBox="0 0 24 24"><path d="M3 7h7v7H3z"/><path d="M14 3h7v7h-7z"/><path d="M14 14h7v7h-7z"/><path d="M3 17h7v4H3z"/></svg>',
    'import'    => '<svg viewBox="0 0 24 24"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><path d="m7 10 5 5 5-5"/><path d="M12 15V3"/></svg>',
    'media'     => '<svg viewBox="0 0 24 24"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="9" cy="9" r="2"/><path d="m21 15-5-5L5 21"/></svg>',
    'page'      => '<svg viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8Z"/><path d="M14 2v6h6"/><path d="M9 13h6"/><path d="M9 17h6"/></svg>',
    'blog'      => '<svg viewBox="0 0 24 24"><path d="M4 4h12a4 4 0 0 1 4 4v12H8a4 4 0 0 1-4-4Z"/><path d="M8 8h8M8 12h8M8 16h5"/></svg>',
    'banner'    => '<svg viewBox="0 0 24 24"><rect x="2" y="6" width="20" height="12" rx="2"/><path d="M2 10h20"/></svg>',
    'layout'    => '<svg viewBox="0 0 24 24"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/></svg>',
    'seo'       => '<svg viewBox="0 0 24 24"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg>',
    'redirect'  => '<svg viewBox="0 0 24 24"><path d="M21 7H3"/><path d="m17 11 4-4-4-4"/><path d="M3 17h18"/><path d="m7 13-4 4 4 4"/></svg>',
    'customer'  => '<svg viewBox="0 0 24 24"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>',
    'review'    => '<svg viewBox="0 0 24 24"><path d="M12 17.27L18.18 21l-1.64-7.03L22 9.24l-7.19-.61L12 2 9.19 8.63 2 9.24l5.46 4.73L5.82 21z"/></svg>',
    'comment'   => '<svg viewBox="0 0 24 24"><path d="M21 11.5a8.4 8.4 0 0 1-1 4 8.5 8.5 0 0 1-7.6 4.5 8.4 8.4 0 0 1-4-1L3 21l2-5.4a8.4 8.4 0 0 1-1-4 8.5 8.5 0 0 1 4.5-7.6 8.4 8.4 0 0 1 4-1 8.5 8.5 0 0 1 8 8Z"/></svg>',
    'mail'      => '<svg viewBox="0 0 24 24"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2Z"/></svg>',
    'newsletter'=> '<svg viewBox="0 0 24 24"><path d="M4 4h16v16H4z"/><path d="m4 4 8 8 8-8"/></svg>',
    'gear'      => '<svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 1 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09a1.65 1.65 0 0 0-1-1.51 1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 1 1-2.83-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09a1.65 1.65 0 0 0 1.51-1 1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 1 1 2.83-2.83l.06.06a1.65 1.65 0 0 0 1.82.33h0a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51h0a1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 1 1 2.83 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82v0a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1Z"/></svg>',
    'home'      => '<svg viewBox="0 0 24 24"><path d="M3 12 12 3l9 9"/><path d="M5 10v10h14V10"/></svg>',
    'pos'       => '<svg viewBox="0 0 24 24"><rect x="2" y="3" width="20" height="14" rx="2"/><path d="M8 21h8M12 17v4"/><path d="M7 8h.01M12 8h.01M7 12h10"/></svg>',
    'trash'     => '<svg viewBox="0 0 24 24"><path d="M3 6h18M8 6V4h8v2M19 6l-1 14H6L5 6"/><path d="M10 11v6M14 11v6"/></svg>',
    'logout'    => '<svg viewBox="0 0 24 24"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><path d="m16 17 5-5-5-5"/><path d="M21 12H9"/></svg>',
    'plus'      => '<svg viewBox="0 0 24 24"><path d="M12 5v14M5 12h14"/></svg>',
    'arrow'     => '<svg viewBox="0 0 24 24"><path d="m9 18 6-6-6-6"/></svg>',
    'question'  => '<svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"/><path d="M12 17h.01"/></svg>',
];

/** Alt menünün başlangıçta açık olup olmayacağı — aktif page veya group eşleşmesi */
$openCatalog = in_array($page, ['products','products_trash','products_stock','products_bulk','categories','products_import','media','media_trash'], true);
$openContent = in_array($page, ['pages','blog','blog_authors','blog_categories','blog_import','banners','layout',
                                'seo','redirects','sss_faq'], true);
// $openSeoSys ve $openCrm artık kullanılmıyor — Müşteri ve İçerik & SEO grupları birleşti
$openSales   = in_array($page, ['orders','coupons','pos','abandoned_carts'], true);
$openReports = in_array($page, ['reports_sales','reports_products','reports_coupons'], true);
$openCustomer= in_array($page, ['customers','reviews','comments','messages','newsletter','newsletter_campaigns',
                                'loyalty_customers','loyalty_transactions','sms_log','questions'], true);
$openSystem  = in_array($page, ['settings','migrations','tools_resize','tools_reset','mail_templates'], true);
?>
<!DOCTYPE html>
<html lang="tr">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />
<title><?= e($title) ?> · Yönetim</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&family=Playfair+Display:wght@500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= SITE_URL ?>/assets/css/style.css?v=<?= @filemtime(__DIR__ . '/../../assets/css/style.css') ?: time() ?>">
<link rel="stylesheet" href="<?= SITE_URL ?>/assets/css/admin.css?v=<?= @filemtime(__DIR__ . '/../../assets/css/admin.css') ?: time() ?>">
<link rel="stylesheet" href="<?= SITE_URL ?>/assets/css/admin-modules.css?v=<?= @filemtime(__DIR__ . '/../../assets/css/admin-modules.css') ?: time() ?>">
<link rel="stylesheet" href="<?= SITE_URL ?>/assets/css/a11y.css?v=<?= @filemtime(__DIR__ . '/../../assets/css/a11y.css') ?: time() ?>">
<link rel="stylesheet" href="<?= SITE_URL ?>/assets/css/toast.css">
<script defer src="<?= SITE_URL ?>/assets/js/toast.js"></script>
</head>
<body>
<div class="admin-wrap">
  <div class="sb-overlay" data-sb-close></div>
  <aside class="sidebar">
    <div class="brand">
      <div><h1>Yönetim</h1><small><?= e(strtoupper((string)(setting('site_name') ?? SITE_NAME_FALLBACK))) ?></small></div>
      <button type="button" class="sb-close" data-sb-close aria-label="Menüyü kapat">×</button>
    </div>

    <!-- ⚡ Hızlı Erişim -->
    <div class="quick-actions" aria-label="Hızlı erişim">
      <a href="<?= $AP ?>/products/edit.php" class="qa qa-primary">
        <?= $ICO['plus'] ?><span>Yeni Ürün</span>
      </a>
      <?php if ($_C['pending_orders'] > 0): ?>
      <a href="<?= $AP ?>/orders/list.php?status=pending" class="qa qa-warn">
        <?= $ICO['pending'] ?><span>Bekleyen Sipariş</span>
        <span class="qa-badge"><?= (int)$_C['pending_orders'] ?></span>
      </a>
      <?php endif; ?>
      <?php $totalReviews = admin_pending_reviews_total(); if ($totalReviews > 0): ?>
      <a href="<?= $AP ?>/reviews.php?filter=pending" class="qa qa-info">
        <?= $ICO['review'] ?><span>Onay Bekleyen Yorum</span>
        <span class="qa-badge"><?= $totalReviews ?></span>
      </a>
      <?php endif; ?>
    </div>

    <nav>
      <?= ap_link("$AP/dashboard.php", 'Komuta Merkezi', $ICO['dashboard'], $page === 'dashboard') ?>

      <!-- 1) SATIŞ -->
      <details class="menu-group" <?= $openSales ? 'open' : '' ?>>
        <summary>
          <span class="menu-ico"><?= $ICO['orders'] ?></span>
          <span class="menu-label">Satış</span>
          <?php $__salesBadge = (int)$_C['pending_orders'] + (int)($_C['paid_orders'] ?? 0); if ($__salesBadge > 0): ?><span class="menu-badge menu-badge-grp"><?= $__salesBadge ?></span><?php endif; ?>
          <span class="menu-caret"><?= $ICO['arrow'] ?></span>
        </summary>
        <div class="menu-sub">
          <?= ap_link("$AP/orders/list.php", 'Tüm Siparişler', $ICO['orders'], $page === 'orders' && empty($_GET['status'])) ?>
          <?= ap_link("$AP/orders/list.php?status=paid", 'Hazırlanacak (Ödendi)', $ICO['pending'], $page === 'orders' && (($_GET['status'] ?? '') === 'paid'), $_C['paid_orders'] ?? 0) ?>
          <?= ap_link("$AP/orders/list.php?status=pending", 'Bekleyenler', $ICO['pending'], $page === 'orders' && (($_GET['status'] ?? '') === 'pending'), $_C['pending_orders']) ?>
          <?= ap_link("$AP/abandoned-carts.php", 'Terk Edilmiş Sepetler', $ICO['orders'], $page === 'abandoned_carts') ?>
          <?= ap_link("$AP/coupons.php", 'Kuponlar & İndirimler', $ICO['coupon'], $page === 'coupons') ?>
          <?= ap_link("$AP/pos.php", 'Mağaza Satışı (POS)', $ICO['pos'], $page === 'pos') ?>
        </div>
      </details>

      <!-- 2) ANALİZ -->
      <details class="menu-group" <?= $openReports ? 'open' : '' ?>>
        <summary>
          <span class="menu-ico"><?= $ICO['dashboard'] ?></span>
          <span class="menu-label">Analiz</span>
          <span class="menu-caret"><?= $ICO['arrow'] ?></span>
        </summary>
        <div class="menu-sub">
          <?= ap_link("$AP/reports/sales.php", 'Satış Raporu', $ICO['orders'], $page === 'reports_sales') ?>
          <?= ap_link("$AP/reports/products.php", 'Ürün Performansı', $ICO['product'], $page === 'reports_products') ?>
          <?= ap_link("$AP/reports/coupons.php", 'Kupon Performansı', $ICO['coupon'], $page === 'reports_coupons') ?>
        </div>
      </details>

      <!-- 3) KATALOG -->
      <details class="menu-group" <?= $openCatalog ? 'open' : '' ?>>
        <summary>
          <span class="menu-ico"><?= $ICO['product'] ?></span>
          <span class="menu-label">Katalog</span>
          <span class="menu-caret"><?= $ICO['arrow'] ?></span>
        </summary>
        <div class="menu-sub">
          <?= ap_link("$AP/products/list.php", 'Ürünler', $ICO['product'], $page === 'products') ?>
          <?= ap_link("$AP/categories.php", 'Kategoriler', $ICO['category'], $page === 'categories') ?>
          <?= ap_link("$AP/products/stock.php", 'Toplu Stok', $ICO['import'], $page === 'products_stock') ?>
          <?= ap_link("$AP/products/bulk-update.php", 'Toplu CSV Güncelle', $ICO['import'], $page === 'products_bulk') ?>
          <?= ap_link("$AP/products/trash.php", 'Çöp Kutusu', $ICO['trash'], $page === 'products_trash', $_C['product_trash']) ?>
          <?= ap_link("$AP/media/library.php", 'Medya Kütüphanesi', $ICO['media'], $page === 'media') ?>
          <?= ap_link("$AP/products/import.php", 'WordPress İçe Aktar', $ICO['import'], $page === 'products_import') ?>
        </div>
      </details>

      <!-- 4) İÇERİK & SEO -->
      <details class="menu-group" <?= $openContent ? 'open' : '' ?>>
        <summary>
          <span class="menu-ico"><?= $ICO['page'] ?></span>
          <span class="menu-label">İçerik &amp; SEO</span>
          <span class="menu-caret"><?= $ICO['arrow'] ?></span>
        </summary>
        <div class="menu-sub">
          <?= ap_link("$AP/pages/list.php", 'Sayfalar (CMS)', $ICO['page'], $page === 'pages') ?>
          <?= ap_link("$AP/pages/sss.php", 'SSS Yönetimi', $ICO['question'], $page === 'sss_faq') ?>
          <?= ap_link("$AP/blog/posts.php", 'Blog Yazıları', $ICO['blog'], $page === 'blog') ?>
          <?= ap_link("$AP/blog/authors.php", 'Blog Yazarları', $ICO['user'] ?? $ICO['blog'], $page === 'blog_authors') ?>
          <?= ap_link("$AP/blog/categories.php", 'Blog Kategorileri', $ICO['category'], $page === 'blog_categories') ?>
          <?= ap_link("$AP/banners.php", 'Bannerlar', $ICO['banner'], $page === 'banners') ?>
          <?= ap_link("$AP/layout.php", 'Sayfa Düzeni', $ICO['layout'], $page === 'layout') ?>
          <?= ap_link("$AP/seo_manager.php", 'SEO Yönetimi', $ICO['seo'], $page === 'seo') ?>
          <?= ap_link("$AP/redirects.php", 'URL Yönlendirme', $ICO['redirect'], $page === 'redirects') ?>
          <?= ap_link("$AP/blog/import.php", 'WordPress\'ten İçe Aktar', $ICO['import'], $page === 'blog_import') ?>
        </div>
      </details>

      <!-- 5) MÜŞTERİ -->
      <details class="menu-group" <?= $openCustomer ? 'open' : '' ?>>
        <summary>
          <span class="menu-ico"><?= $ICO['customer'] ?></span>
          <span class="menu-label">Müşteri</span>
          <?php $crmTotal = $_C['pending_reviews'] + $_C['pending_questions'] + $_C['pending_comments'] + $_C['unread_messages']; if ($crmTotal > 0): ?>
            <span class="menu-badge menu-badge-grp"><?= $crmTotal ?></span>
          <?php endif; ?>
          <span class="menu-caret"><?= $ICO['arrow'] ?></span>
        </summary>
        <div class="menu-sub">
          <?= ap_link("$AP/customers/list.php", 'Müşteri Listesi', $ICO['customer'], $page === 'customers') ?>
          <?= ap_link("$AP/loyalty/customers.php", 'Sadakat · Puanlar', $ICO['customer'], $page === 'loyalty_customers') ?>
          <?= ap_link("$AP/loyalty/transactions.php", 'Sadakat · İşlemler', $ICO['orders'], $page === 'loyalty_transactions') ?>
          <?= ap_link("$AP/reviews.php",   'Ürün Yorumları',   $ICO['review'],   $page === 'reviews',   $_C['pending_reviews']) ?>
          <?= ap_link("$AP/questions.php", 'Ürün Soruları',   $ICO['question'], $page === 'questions', $_C['pending_questions']) ?>
          <?= ap_link("$AP/comments.php",  'Blog Yorumları',  $ICO['comment'],  $page === 'comments',  $_C['pending_comments']) ?>
          <?= ap_link("$AP/messages.php", 'İletişim Mesajları', $ICO['mail'], $page === 'messages', $_C['unread_messages']) ?>
          <?= ap_link("$AP/newsletter/subscribers.php", 'E-Bülten · Aboneler', $ICO['newsletter'], $page === 'newsletter') ?>
          <?= ap_link("$AP/newsletter/campaigns.php", 'E-Bülten · Kampanyalar', $ICO['newsletter'], $page === 'newsletter_campaigns') ?>
          <?= ap_link("$AP/notifications/sms-log.php", 'SMS Gönderim Logu', $ICO['mail'], $page === 'sms_log') ?>
        </div>
      </details>

      <!-- 6) SİSTEM -->
      <details class="menu-group" <?= $openSystem ? 'open' : '' ?>>
        <summary>
          <span class="menu-ico"><?= $ICO['gear'] ?></span>
          <span class="menu-label">Sistem</span>
          <span class="menu-caret"><?= $ICO['arrow'] ?></span>
        </summary>
        <div class="menu-sub">
          <?= ap_link("$AP/settings/index.php", 'Ayarlar (Hub)', $ICO['gear'], $page === 'settings') ?>
          <?= ap_link("$AP/mail-templates.php", 'Mail Şablonları', $ICO['mail'], $page === 'mail_templates') ?>
          <?= ap_link("$AP/tools/migrations.php", 'Veritabanı Migration', $ICO['gear'], $page === 'migrations') ?>
          <?= ap_link("$AP/tools/resize_images.php", 'Resim Optimizasyonu (Genel)', $ICO['media'], $page === 'tools_resize') ?>
          <?= ap_link("$AP/tools/optimize_smart.php", 'Akıllı Optimizasyon (Tipli)', $ICO['media'], $page === 'tools_optimize_smart') ?>
          <?= ap_link("$AP/tools/reset.php", 'İçerik Sıfırlama', $ICO['trash'], $page === 'tools_reset') ?>
        </div>
      </details>

      <div class="menu-divider"></div>
      <?= ap_link(url('home'), 'Siteyi Görüntüle', $ICO['home'], false, 0, 'menu-link-muted') ?>
      <?= ap_link(url('logout'), 'Çıkış', $ICO['logout'], false, 0, 'menu-link-muted') ?>
    </nav>
  </aside>

  <main class="main">
    <div class="topnav">
      <div class="topnav-left">
        <button type="button" class="sb-toggle" data-sb-open aria-label="Menüyü aç">
          <svg viewBox="0 0 24 24"><path d="M4 6h16M4 12h16M4 18h16"/></svg>
        </button>
        <h2><?= e($title) ?></h2>
      </div>
      <div class="who"><span><?= e($ADMIN['name']) ?></span> <a href="<?= url('logout') ?>">Çıkış</a></div>
    </div>
    <?php flash_render(); ?>
<script defer src="<?= SITE_URL ?>/assets/js/admin/sidebar.js?v=<?= @filemtime(__DIR__ . '/../../assets/js/admin/sidebar.js') ?: time() ?>"></script>
