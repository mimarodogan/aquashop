<?php
/**
 * Sepete Eklendi Modal — JS tarafından doldurulur.
 * Settings: cart_modal_enabled
 */
if (setting('cart_modal_enabled','1') !== '1') return;
?>
<div class="cart-added-modal" id="cart-added-modal" role="dialog" aria-modal="true" aria-hidden="true">
  <div class="cam-inner">
    <button type="button" class="cam-close" data-cam-close aria-label="Kapat">✕</button>
    <p class="cam-success">✓ Sepete eklendi</p>
    <div class="cam-product">
      <img class="cam-img" src="data:image/svg+xml;base64,PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHZpZXdCb3g9IjAgMCA3MiA3MiI+PC9zdmc+" alt="" width="72" height="72">
      <div class="cam-info">
        <p class="cam-name"></p>
        <p class="cam-meta"><span class="cam-qty"></span> · <span class="cam-price"></span></p>
      </div>
    </div>
    <div class="cam-summary">
      Sepetinizde toplam <strong class="cam-count">0</strong> ürün · <strong class="cam-total">0₺</strong>
    </div>
    <div class="cam-actions">
      <button type="button" class="btn btn-secondary" data-cam-close>← Alışverişe Devam</button>
      <a class="btn btn-primary cam-cart-link" href="#">Sepete Git →</a>
    </div>
  </div>
</div>
