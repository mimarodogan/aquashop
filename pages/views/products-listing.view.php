<?php
/**
 * Ürün listeleme görünümü.
 * core/controllers/products_listing.php tarafından sağlanan değişkenleri kullanır.
 */
include __DIR__ . "/../../includes/header.php";

// GA4 view_item_list — ürün listesi görüntülendi (ilk sayfa yükleme; AJAX load-more'lar JS'te ek event basabilir)
if (!empty($products)) {
    $__ga_items = [];
    foreach (array_slice($products, 0, 20) as $__i => $__row) {
        $__ga_items[] = analytics_ecommerce_item($__row, 1, null, $__i);
    }
    $__listName = (count($catMulti) === 1) ? ucwords(str_replace('-', ' ', $catMulti[0])) : 'Tüm Ürünler';
    analytics_event('view_item_list', [
        'item_list_id'   => 'products_listing',
        'item_list_name' => $__listName,
        'items'          => $__ga_items,
    ]);
}
?>

<?php $__listTitle = count($catMulti)===1 ? ucwords(str_replace('-',' ',$catMulti[0])) : 'Tüm Ürünler'; ?>
<section class="aq-category-page-hero aq-category-page-hero-simple">
  <div class="aq-container">
    <nav class="aq-breadcrumb" aria-label="Konum">
      <a href="<?= e(url('home')) ?>">Anasayfa</a>
      <i class="bi bi-chevron-right" aria-hidden="true"></i>
      <span aria-current="page">Ürünler</span>
    </nav>
    <div class="aq-category-hero-card">
      <h1><?= e($__listTitle) ?></h1>
      <p>Tüm ürünlerimizi keşfedin; filtreleyerek size en uygun olanı kolayca bulun.</p>
    </div>
  </div>
</section>

<section class="aq-category-page">
  <div class="aq-container">

    <!-- Aktif filtre çipleri -->
    <?php if ($activeCount > 0): ?>
    <div class="active-chips">
      <?php
        foreach ($catMulti as $cs) {
            $href = remove_param_url($_GET, 'cats', $cs, 'cat');
            $label = '';
            foreach ($categories as $c) if ($c['slug']===$cs) { $label = $c['name']; break; }
            if ($label==='') $label = ucwords(str_replace('-',' ',$cs));
            echo '<a class="chip" href="'.e($href).'">' . e($label) . ' <span aria-hidden="true">×</span></a>';
        }
        foreach ($brandMulti as $bs) {
            $href = remove_param_url($_GET, 'brands', $bs, 'brand');
            echo '<a class="chip" href="'.e($href).'">' . e($bs) . ' <span aria-hidden="true">×</span></a>';
        }
        if ($priceMin !== null || $priceMax !== null) {
            $href = remove_param_url($_GET, 'pmin', null, 'pmax');
            $lbl = ($priceMin!==null ? money($priceMin):'').' — '.($priceMax!==null ? money($priceMax):'');
            echo '<a class="chip" href="'.e($href).'">' . e($lbl) . ' <span aria-hidden="true">×</span></a>';
        }
        if ($inStock) { $href = remove_param_url($_GET, 'stock'); echo '<a class="chip" href="'.e($href).'">Stoktakiler <span aria-hidden="true">×</span></a>'; }
        if ($onSale)  { $href = remove_param_url($_GET, 'sale');  echo '<a class="chip" href="'.e($href).'">İndirimli <span aria-hidden="true">×</span></a>'; }
        if ($q !== '') { $href = remove_param_url($_GET, 'q'); echo '<a class="chip" href="'.e($href).'">"'.e($q).'" <span aria-hidden="true">×</span></a>'; }
      ?>
      <a class="chip chip-clear" href="<?= url('products') ?>">Tümünü Temizle</a>
    </div>
    <?php endif; ?>

    <!-- Üst toolbar (mobil): Filtrele + Sıralama -->
    <div class="shop-toolbar aq-category-toolbar">
      <button type="button" class="shop-tool" id="btn-filter" aria-haspopup="dialog" aria-expanded="false">
        <?= ic('filter', '', 18) ?>
        <span>Filtrele<?php if ($activeCount>0): ?> <span class="ft-badge">(<?= $activeCount ?>)</span><?php endif; ?></span>
      </button>
      <button type="button" class="shop-tool" id="btn-sort" aria-haspopup="dialog" aria-expanded="false">
        <?= ic('sort', '', 18) ?>
        <span><?= e($sortLabels[$sort] ?? 'Önerilen Sıralama') ?></span>
      </button>
    </div>

    <div class="aq-category-layout">
      <!-- Masaüstü filtre sidebar (kategori sayfasıyla aynı tasarım) -->
      <aside class="aq-category-filter-panel" aria-label="Filtreleme">
        <div class="aq-category-filter-head">
          <h2>Filtreleme</h2>
          <p>Sonuçları daraltın</p>
        </div>
        <form method="get" action="<?= url('products') ?>" class="aq-category-filter-form">
          <input type="hidden" name="sort" value="<?= e($sort) ?>">

          <details class="aq-category-filter-group" <?= $q!=='' ? 'open' : '' ?>>
            <summary>Ara <i class="bi bi-chevron-up" aria-hidden="true"></i></summary>
            <div class="aq-category-filter-options">
              <input type="text" name="q" value="<?= e($q) ?>" placeholder="Ürün adı veya stok kodu…" style="width:100%;padding:10px 12px;border:1px solid var(--aq-border,#e9eef3);border-radius:10px;font-size:13px;background:#fff;color:var(--aq-dark,#111827)">
            </div>
          </details>

          <?php if ($categories): ?>
          <details class="aq-category-filter-group" open>
            <summary>Kategori <i class="bi bi-chevron-up" aria-hidden="true"></i></summary>
            <div class="aq-category-filter-options">
              <?php
              $byPid = array(); foreach ($categories as $c) { $pid = $c['parent_id'] ?? 0; $byPid[$pid][] = $c; }
              $renderCats = function($pid,$depth) use (&$renderCats,$byPid,$catMulti) {
                if (empty($byPid[$pid])) return;
                foreach ($byPid[$pid] as $c) {
                  $checked = in_array($c['slug'],$catMulti,true);
                  echo '<label'.($depth>0?' style="padding-left:'.($depth*14).'px"':'').'><input type="checkbox" name="cats[]" value="'.e($c['slug']).'" '.($checked?'checked':'').'><span>'.e($c['name']).'</span><em>'.(int)($c['cnt'] ?? 0).'</em></label>';
                  $renderCats($c['id'],$depth+1);
                }
              };
              $renderCats(0,0);
              ?>
            </div>
          </details>
          <?php endif; ?>

          <?php if (!empty($brands)): ?>
          <details class="aq-category-filter-group" <?= count($brandMulti)?'open':'' ?>>
            <summary>Marka <i class="bi bi-chevron-up" aria-hidden="true"></i></summary>
            <div class="aq-category-filter-options">
              <?php foreach ($brands as $b): $checked=in_array($b['brand'],$brandMulti,true); ?>
                <label><input type="checkbox" name="brands[]" value="<?= e($b['brand']) ?>" <?= $checked?'checked':'' ?>><span><?= e($b['brand']) ?></span><em><?= (int)$b['cnt'] ?></em></label>
              <?php endforeach; ?>
            </div>
          </details>
          <?php endif; ?>

          <details class="aq-category-filter-group" <?= ($priceMin!==null||$priceMax!==null)?'open':'' ?>>
            <summary>Fiyat <i class="bi bi-chevron-up" aria-hidden="true"></i></summary>
            <div class="aq-category-price-row">
              <input type="number" name="pmin" placeholder="Min" value="<?= $priceMin!==null?(int)$priceMin:'' ?>" min="0" inputmode="numeric">
              <input type="number" name="pmax" placeholder="Maks" value="<?= $priceMax!==null?(int)$priceMax:'' ?>" min="0" inputmode="numeric">
            </div>
          </details>

          <details class="aq-category-filter-group" <?= ($inStock||$onSale)?'open':'' ?>>
            <summary>Durum <i class="bi bi-chevron-up" aria-hidden="true"></i></summary>
            <div class="aq-category-filter-options">
              <label><input type="checkbox" name="stock" value="1" <?= $inStock?'checked':'' ?>><span>Sadece stoktakiler</span></label>
              <label><input type="checkbox" name="sale" value="1" <?= $onSale?'checked':'' ?>><span>İndirimli ürünler</span></label>
            </div>
          </details>

          <div class="aq-category-filter-actions">
            <button type="submit" class="btn btn-primary">Uygula</button>
            <a href="<?= url('products') ?>" class="btn btn-secondary">Temizle</a>
          </div>
        </form>
      </aside>

      <div class="aq-category-main">
        <div class="aq-category-results-head">
          <p><?= (int)$totalCount ?> ürün bulundu</p>
          <?php if (($sortLabels[$sort] ?? '') !== '' && $sort!=='new'): ?><span><?= e($sortLabels[$sort]) ?></span><?php endif; ?>
        </div>

        <div class="aq-product-grid aq-category-products" id="prod-grid">
      <?php
        $favIds = fav_ids();
        $cardBack = $_SERVER['REQUEST_URI'] ?? url('products');
        if ($products):
          foreach ($products as $p):
            include __DIR__ . '/../../components/product-card.php';
          endforeach;
        else: ?>
          <p class="muted" style="grid-column:1/-1;text-align:center;padding:60px 20px">Sonuç bulunamadı. <a href="<?= url('products') ?>" style="color:var(--aq-blue)">Filtreleri temizle</a>.</p>
        <?php endif; ?>
    </div>

    <?php if ($totalCount > 0): ?>
      <div class="loadmore-wrap" style="text-align:center;margin-top:40px"
           data-ajax-url="<?= e(rtrim(SITE_URL,'/').'/products.php') ?>"
           data-button-id="loadmore-btn"
           data-grid-selector="#prod-grid"
           data-base-query="<?= e(http_build_query(array_diff_key($_GET, ['offset'=>'','ajax'=>'']))) ?>"
           data-next-offset="<?= (int)$nextOffset ?>"
           data-total="<?= (int)$totalCount ?>">
        <p class="muted" style="margin-bottom:14px;font-size:13px">
          <span class="lm-shown"><?= count($products) ?></span> / <?= $totalCount ?> ürün gösteriliyor
        </p>
        <?php if ($hasMore): ?>
          <button type="button" class="btn btn-secondary btn-lg" id="loadmore-btn">
            Daha Fazla Göster <span style="opacity:.7;margin-left:8px">(+<?= min(PRODUCTS_PER_PAGE, $totalCount - $offset - count($products)) ?>)</span>
          </button>
        <?php endif; ?>
      </div>

      <?php /* Taranabilir sayfalama — JS load-more kullanmayan ziyaretçi/crawler için */ ?>
      <?php if ($totalPages > 1): ?>
        <nav class="pagination" aria-label="Sayfalama" style="margin-top:30px;display:flex;justify-content:center;gap:6px;flex-wrap:wrap">
          <?php
            if ($currentPage > 1) {
                echo '<a class="pg-link" rel="prev" href="' . e($page_url($currentPage - 1)) . '" aria-label="Önceki sayfa">‹ Önceki</a>';
            }
            // 1 … (c-2 c-1 c c+1 c+2) … N
            $shown = array();
            for ($p = 1; $p <= $totalPages; $p++) {
                if ($p === 1 || $p === $totalPages || abs($p - $currentPage) <= 2) $shown[] = $p;
            }
            $prev = 0;
            foreach ($shown as $p) {
                if ($prev && $p > $prev + 1) echo '<span class="pg-gap" aria-hidden="true">…</span>';
                if ($p === $currentPage) {
                    echo '<span class="pg-link pg-current" aria-current="page">' . $p . '</span>';
                } else {
                    echo '<a class="pg-link" href="' . e($page_url($p)) . '">' . $p . '</a>';
                }
                $prev = $p;
            }
            if ($currentPage < $totalPages) {
                echo '<a class="pg-link" rel="next" href="' . e($page_url($currentPage + 1)) . '" aria-label="Sonraki sayfa">Sonraki ›</a>';
            }
          ?>
        </nav>
        <style>
          .pagination .pg-link{padding:8px 12px;border:1px solid var(--gold-border,#e3dccb);border-radius:6px;color:var(--ink,#0a0a0a);text-decoration:none;font-size:14px;line-height:1;display:inline-flex;align-items:center}
          .pagination .pg-link:hover{background:var(--olive-2,#f6f3e9)}
          .pagination .pg-current{background:var(--gold,#6b7a2f);color:#fff;border-color:var(--gold,#6b7a2f)}
          .pagination .pg-gap{padding:8px 4px;color:var(--muted-text,#5f5f5f)}
        </style>
      <?php endif; ?>

    <?php endif; ?>
      </div><!-- /aq-category-main -->
    </div><!-- /aq-category-layout -->

  </div>
</section>

<!-- Sıralama dialog -->
<div class="sort-pop" id="sort-pop" role="dialog" aria-label="Sıralama" aria-modal="true">
  <div class="sort-pop-overlay" data-close="sort"></div>
  <div class="sort-pop-card">
    <div class="sort-pop-head">Önerilen Sıralama <button type="button" class="sort-pop-close" data-close="sort" aria-label="Kapat">×</button></div>
    <ul>
      <?php foreach ($sortLabels as $k=>$lbl): $args2=array_merge($_GET,['sort'=>$k]); ?>
        <li><a href="?<?= e(http_build_query($args2)) ?>" class="<?= $sort===$k?'active':'' ?>"><?= e($lbl) ?><?php if ($sort===$k): ?> <span aria-hidden="true">✓</span><?php endif; ?></a></li>
      <?php endforeach; ?>
    </ul>
  </div>
</div>

<!-- Filtre dialog -->
<div class="filter-drawer" id="filter-drawer" role="dialog" aria-label="Filtrele" aria-modal="true">
  <div class="filter-drawer-overlay" data-close="filter"></div>
  <form class="filter-drawer-panel" method="get" action="<?= url('products') ?>" id="filter-form">
    <div class="filter-drawer-head">
      <h3>Filtrele<?php if ($activeCount>0): ?> <span class="muted">(<?= $activeCount ?>)</span><?php endif; ?></h3>
      <button type="button" class="filter-drawer-close" data-close="filter" aria-label="Kapat">×</button>
    </div>

    <div class="filter-drawer-body">

      <div class="ft-section">
        <h4>Ara</h4>
        <input type="text" name="q" value="<?= e($q) ?>" placeholder="Ürün adı, açıklama veya stok kodu…">
      </div>

      <details class="ft-section" open>
        <summary><h4>Kategori<?php if (count($catMulti)): ?> <em>(<?= count($catMulti) ?>)</em><?php endif; ?></h4></summary>
        <div class="ft-list">
          <?php
          // Hiyerarşik liste — root + child
          $byPid = array(); foreach ($categories as $c) { $pid = $c['parent_id'] ?? 0; $byPid[$pid][] = $c; }
          $renderCats = function($pid, $depth) use (&$renderCats, $byPid, $catMulti) {
              if (empty($byPid[$pid])) return;
              foreach ($byPid[$pid] as $c) {
                  $checked = in_array($c['slug'], $catMulti, true);
                  $pad = $depth * 16;
                  echo '<label class="ft-check" style="padding-left:'.$pad.'px"><input type="checkbox" name="cats[]" value="'.e($c['slug']).'" '.($checked?'checked':'').'><span>'.($depth>0?'<span class="muted">└</span> ':'').e($c['name']).' <em>('.(int)$c['cnt'].')</em></span></label>';
                  $renderCats($c['id'], $depth+1);
              }
          };
          $renderCats(0, 0);
          ?>
        </div>
      </details>

      <?php if ($brands): ?>
      <details class="ft-section" <?= count($brandMulti)?'open':'' ?>>
        <summary><h4>Marka<?php if (count($brandMulti)): ?> <em>(<?= count($brandMulti) ?>)</em><?php endif; ?></h4></summary>
        <div class="ft-list">
          <?php foreach ($brands as $b): $checked = in_array($b['brand'], $brandMulti, true); ?>
            <label class="ft-check"><input type="checkbox" name="brands[]" value="<?= e($b['brand']) ?>" <?= $checked?'checked':'' ?>><span><?= e($b['brand']) ?> <em>(<?= (int)$b['cnt'] ?>)</em></span></label>
          <?php endforeach; ?>
        </div>
      </details>
      <?php endif; ?>

      <details class="ft-section" <?= ($priceMin!==null||$priceMax!==null)?'open':'' ?>>
        <summary><h4>Fiyat Aralığı</h4></summary>
        <div class="ft-pricerow">
          <input type="number" name="pmin" value="<?= e($priceMin) ?>" placeholder="Min ₺" inputmode="numeric" min="0">
          <span aria-hidden="true">—</span>
          <input type="number" name="pmax" value="<?= e($priceMax) ?>" placeholder="Maks ₺" inputmode="numeric" min="0">
        </div>
        <small class="muted">Mağazada fiyat aralığı: <?= money($priceRange['min']) ?> – <?= money($priceRange['max']) ?></small>
      </details>

      <div class="ft-section">
        <h4>Diğer</h4>
        <label class="ft-check"><input type="checkbox" name="stock" value="1" <?= $inStock?'checked':'' ?>><span>Sadece stoktakiler</span></label>
        <label class="ft-check"><input type="checkbox" name="sale" value="1" <?= $onSale?'checked':'' ?>><span>İndirimli ürünler</span></label>
      </div>

      <input type="hidden" name="sort" value="<?= e($sort) ?>">
    </div>

    <div class="filter-drawer-foot">
      <a class="btn btn-secondary btn-sm" href="<?= url('products') ?>" style="flex:1">Temizle</a>
      <button type="submit" class="btn btn-primary btn-sm" style="flex:2">Uygula (<?= count($products) ?> ürün)</button>
    </div>
  </form>
</div>


<?php include __DIR__ . "/../../includes/footer.php"; ?>
