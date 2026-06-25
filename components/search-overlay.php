<?php
/**
 * Arama overlay bileşeni — footer'dan include edilir.
 * JS: assets/js/components/search-overlay.js
 */
?>
<div class="search-overlay" id="searchOverlay" role="dialog" aria-label="Ara" aria-modal="true">
  <div class="search-box">
    <div class="search-input-wrap">
      <?= e3d('search', 22) ?>
      <input type="search" id="searchInput" placeholder="Ürün, marka veya anahtar kelime ara…"
             autocomplete="off" autocorrect="off" spellcheck="false" aria-label="Arama">
      <button type="button" class="search-close-btn" id="searchCloseBtn" aria-label="Aramayı kapat">×</button>
    </div>
    <div class="search-results" id="searchResults" aria-live="polite"></div>
  </div>
</div>
