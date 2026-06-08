<?php
/**
 * Breadcrumb — görsel + JSON-LD BreadcrumbList.
 *
 * Kullanım:
 *   $crumbs = [
 *     ['name' => 'Anasayfa', 'url' => url('home')],
 *     ['name' => 'Ürünler',  'url' => url('products')],
 *     ['name' => $p['name'], 'url' => null],  // son öğe link olmaz
 *   ];
 *   include __DIR__ . '/breadcrumb.php';
 *
 * JSON-LD ayrı bir helper olarak components/json-ld.php#jsonld_breadcrumb()
 * tarafından üretilir — bu dosya sadece görsel render eder.
 */
if (empty($crumbs) || !is_array($crumbs)) return;
$__bcBase = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? '');
?>
<nav class="breadcrumbs" aria-label="Sayfa konumu" itemscope itemtype="https://schema.org/BreadcrumbList">
  <ol style="list-style:none;margin:0;padding:0;display:flex;flex-wrap:wrap;gap:6px;font-size:12px;letter-spacing:.14em;text-transform:uppercase;color:var(--muted-text,#5F5F5F);align-items:center">
    <?php $__i = 1; $__last = count($crumbs); foreach ($crumbs as $__bc): $__hasUrl = !empty($__bc['url']); ?>
      <li itemprop="itemListElement" itemscope itemtype="https://schema.org/ListItem" style="display:inline-flex;align-items:center;gap:6px">
        <?php if ($__hasUrl && $__i < $__last): ?>
          <a href="<?= e($__bc['url']) ?>" itemprop="item" style="color:var(--ink,#0a0a0a);transition:color .2s">
            <span itemprop="name"><?= e($__bc['name']) ?></span>
          </a>
        <?php else: ?>
          <span itemprop="name" style="color:var(--gold,#6B7A2F);font-weight:600"><?= e($__bc['name']) ?></span>
        <?php endif; ?>
        <meta itemprop="position" content="<?= $__i ?>">
        <?php if ($__i < $__last): ?>
          <span aria-hidden="true" style="color:var(--gold-border,#D8D8D8);margin:0 4px">›</span>
        <?php endif; ?>
      </li>
    <?php $__i++; endforeach; ?>
  </ol>
</nav>
<?php
// Son öğenin URL'i yoksa şu anki canonical'i kullan (JSON-LD için)
$__crumbsForLD = [];
foreach ($crumbs as $__bc) {
    $__url = !empty($__bc['url'])
        ? (strpos($__bc['url'], 'http') === 0 ? $__bc['url'] : $__bcBase . $__bc['url'])
        : $__bcBase . ($_SERVER['REQUEST_URI'] ?? '/');
    $__crumbsForLD[] = ['name' => $__bc['name'], 'url' => $__url];
}
// JSON-LD'yi sadece $crumbsEmitJsonLd === true ise bas (head'de duplicate önlemek için).
// PDP/category/post controller'ları JSON-LD'yi $extraSchemas üzerinden head'de zaten emit ediyor.
if (!empty($crumbsEmitJsonLd) && function_exists('jsonld_breadcrumb')) {
    $__bcLD = jsonld_breadcrumb($__crumbsForLD);
    if ($__bcLD) {
        echo '<script type="application/ld+json">' . json_encode($__bcLD, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . '</script>';
    }
}
?>
