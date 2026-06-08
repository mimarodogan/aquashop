<?php
$page  = 'pos';
$title = 'Mağaza Satışı (POS)';
require_once __DIR__ . '/core/header.php';
?>

<style>
/* ── POS Layout ─────────────────────────────────────────────── */
.pos-wrap {
    display: grid;
    grid-template-columns: 1fr 380px;
    grid-template-rows: auto 1fr;
    gap: 0;
    height: calc(100vh - 60px);
    overflow: hidden;
}

/* ── Arama Çubuğu ───────────────────────────────────────────── */
.pos-search-bar {
    grid-column: 1 / -1;
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 14px 20px;
    background: #fff;
    border-bottom: 1px solid #e8e0d0;
    box-shadow: 0 2px 6px rgba(0,0,0,.06);
    z-index: 10;
}
.pos-search-bar .pos-search-ico {
    color: #888;
    flex-shrink: 0;
}
#posQuery {
    flex: 1;
    border: 2px solid #d4c9a8;
    border-radius: 10px;
    padding: 12px 16px;
    font-size: 18px;
    font-family: 'Inter', sans-serif;
    background: #fafaf8;
    color: #1a1a1a;
    outline: none;
    transition: border-color .15s;
}
#posQuery:focus { border-color: #6b7a2f; background: #fff; }
#posQuery::placeholder { color: #b0a880; font-size: 15px; }
.pos-scan-hint {
    font-size: 12px;
    color: #999;
    white-space: nowrap;
    letter-spacing: .04em;
}

/* ── Sol: Ürün Sonuçları ────────────────────────────────────── */
.pos-products {
    overflow-y: auto;
    padding: 16px;
    background: #f7f5f0;
}
.pos-products-empty {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    height: 100%;
    color: #bbb;
    text-align: center;
    gap: 12px;
}
.pos-products-empty svg { opacity: .35; }
.pos-products-empty p { font-size: 15px; margin: 0; }

.pos-product-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
    gap: 12px;
}
.pos-product-card {
    background: #fff;
    border: 1px solid #e5dfc8;
    border-radius: 12px;
    padding: 14px;
    cursor: pointer;
    transition: box-shadow .15s, border-color .15s, transform .15s;
    position: relative;
    user-select: none;
}
.pos-product-card:hover {
    border-color: #6b7a2f;
    box-shadow: 0 4px 16px rgba(107,122,47,.15);
    transform: translateY(-2px);
}
.pos-product-card.out-of-stock {
    opacity: .5;
    cursor: not-allowed;
    pointer-events: none;
}
.pos-product-card.out-of-stock::after {
    content: 'Stokta Yok';
    position: absolute;
    inset: 0;
    display: flex;
    align-items: center;
    justify-content: center;
    background: rgba(255,255,255,.7);
    font-size: 12px;
    font-weight: 600;
    color: #c0392b;
    border-radius: 12px;
    letter-spacing: .08em;
}
.pos-product-img {
    width: 100%;
    aspect-ratio: 1/1;
    object-fit: cover;
    border-radius: 8px;
    margin-bottom: 10px;
    background: #f0ece0;
}
.pos-product-ph {
    width: 100%;
    aspect-ratio: 1/1;
    border-radius: 8px;
    margin-bottom: 10px;
    background: #e8e3d5;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 36px;
    color: #a09878;
    font-family: 'Playfair Display', serif;
}
.pos-product-cat {
    font-size: 10px;
    letter-spacing: .12em;
    text-transform: uppercase;
    color: #9a9070;
    margin-bottom: 3px;
}
.pos-product-name {
    font-size: 13px;
    font-weight: 600;
    color: #1a1a1a;
    margin: 0 0 4px;
    line-height: 1.3;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
}
.pos-product-sku {
    font-size: 11px;
    color: #b0a880;
    font-family: monospace;
    margin-bottom: 6px;
}
.pos-product-price {
    font-size: 16px;
    font-weight: 700;
    color: #3d4a1a;
}
.pos-product-stock {
    font-size: 11px;
    color: #8aaa45;
    margin-top: 2px;
}

/* Varyasyon seçici popup */
.pos-var-modal {
    display: none;
    position: fixed;
    inset: 0;
    z-index: 1000;
    background: rgba(0,0,0,.45);
    align-items: center;
    justify-content: center;
}
.pos-var-modal.open { display: flex; }
.pos-var-box {
    background: #fff;
    border-radius: 16px;
    padding: 28px;
    width: 360px;
    max-height: 80vh;
    overflow-y: auto;
    box-shadow: 0 20px 60px rgba(0,0,0,.25);
}
.pos-var-box h3 { margin: 0 0 18px; font-size: 17px; color: #1a1a1a; }
.pos-var-list { display: flex; flex-direction: column; gap: 8px; }
.pos-var-btn {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 12px 16px;
    border: 2px solid #e5dfc8;
    border-radius: 10px;
    background: #fafaf8;
    cursor: pointer;
    font-size: 15px;
    font-weight: 500;
    transition: all .15s;
    width: 100%;
    text-align: left;
}
.pos-var-btn:hover { border-color: #6b7a2f; background: #f5f8ec; }
.pos-var-btn:disabled { opacity: .4; cursor: not-allowed; }
.pos-var-btn-price { font-weight: 700; color: #3d4a1a; }
.pos-var-btn-stock { font-size: 11px; color: #8aaa45; margin-top: 2px; display: block; }
.pos-var-cancel {
    margin-top: 14px;
    width: 100%;
    padding: 10px;
    border: 1px solid #e5dfc8;
    border-radius: 8px;
    background: none;
    cursor: pointer;
    color: #888;
    font-size: 14px;
}
.pos-var-cancel:hover { background: #f0ece0; }

/* ── Sağ: Sepet ─────────────────────────────────────────────── */
.pos-cart {
    border-left: 1px solid #e8e0d0;
    background: #fff;
    display: flex;
    flex-direction: column;
    overflow: hidden;
}
.pos-cart-head {
    padding: 16px 18px 12px;
    border-bottom: 1px solid #f0ece0;
    font-size: 13px;
    font-weight: 600;
    letter-spacing: .12em;
    text-transform: uppercase;
    color: #6b7a2f;
    display: flex;
    align-items: center;
    justify-content: space-between;
}
.pos-cart-clear {
    font-size: 12px;
    color: #c0392b;
    background: none;
    border: none;
    cursor: pointer;
    letter-spacing: .05em;
    text-transform: none;
    font-weight: 500;
    padding: 4px 8px;
    border-radius: 6px;
}
.pos-cart-clear:hover { background: #fdf0f0; }
.pos-cart-items {
    flex: 1;
    overflow-y: auto;
    padding: 8px 0;
}
.pos-cart-empty {
    height: 100%;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    color: #ccc;
    gap: 8px;
    text-align: center;
    padding: 20px;
}
.pos-cart-empty p { font-size: 13px; margin: 0; }

.pos-cart-item {
    padding: 10px 16px;
    border-bottom: 1px solid #f7f5f0;
    display: flex;
    align-items: center;
    gap: 10px;
    animation: fadeSlideIn .2s ease;
}
@keyframes fadeSlideIn { from { opacity: 0; transform: translateX(10px); } to { opacity: 1; transform: none; } }
.pos-cart-item-info { flex: 1; min-width: 0; }
.pos-cart-item-name {
    font-size: 13px;
    font-weight: 600;
    color: #1a1a1a;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.pos-cart-item-var { font-size: 11px; color: #9a9070; }
.pos-cart-item-price { font-size: 13px; color: #3d4a1a; font-weight: 600; }
.pos-cart-item-line { font-size: 11px; color: #a09878; text-align: right; }
.pos-qty-wrap {
    display: flex;
    align-items: center;
    gap: 4px;
    flex-shrink: 0;
}
.pos-qty-btn {
    width: 26px;
    height: 26px;
    border: 1px solid #d8d0b8;
    background: #f5f5f0;
    border-radius: 6px;
    cursor: pointer;
    font-size: 15px;
    line-height: 1;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #555;
    transition: background .1s;
}
.pos-qty-btn:hover { background: #e8e3d5; }
.pos-qty-btn.remove { color: #c0392b; }
.pos-qty-num {
    width: 28px;
    text-align: center;
    font-size: 14px;
    font-weight: 600;
    color: #1a1a1a;
}

/* ── Sepet Alt Kısım ────────────────────────────────────────── */
.pos-cart-foot {
    border-top: 2px solid #e8e0d0;
    padding: 14px 16px 16px;
    background: #fafaf8;
}
.pos-cart-total-row {
    display: flex;
    justify-content: space-between;
    align-items: baseline;
    margin-bottom: 14px;
}
.pos-cart-total-label { font-size: 12px; letter-spacing: .12em; text-transform: uppercase; color: #888; }
.pos-cart-total-val { font-size: 26px; font-weight: 700; color: #1a1a1a; font-family: 'Playfair Display', serif; }

/* Müşteri adı */
.pos-customer-row { margin-bottom: 10px; }
.pos-customer-row input {
    width: 100%;
    border: 1px solid #d8d0b8;
    border-radius: 8px;
    padding: 8px 12px;
    font-size: 13px;
    color: #1a1a1a;
    background: #fff;
    outline: none;
    box-sizing: border-box;
}
.pos-customer-row input:focus { border-color: #6b7a2f; }

/* Ödeme yöntemi */
.pos-payment-row {
    display: grid;
    grid-template-columns: 1fr 1fr 1fr;
    gap: 6px;
    margin-bottom: 12px;
}
.pos-pay-btn {
    padding: 9px 4px;
    border: 2px solid #e5dfc8;
    border-radius: 8px;
    background: #fff;
    cursor: pointer;
    font-size: 12px;
    font-weight: 600;
    color: #555;
    text-align: center;
    transition: all .15s;
    letter-spacing: .04em;
}
.pos-pay-btn:hover { border-color: #6b7a2f; color: #4a5a1f; }
.pos-pay-btn.active { border-color: #6b7a2f; background: #6b7a2f; color: #fff; }

/* Tahsil Et butonu */
.pos-checkout-btn {
    width: 100%;
    padding: 16px;
    background: #6b7a2f;
    color: #fff;
    border: none;
    border-radius: 10px;
    font-size: 16px;
    font-weight: 700;
    letter-spacing: .1em;
    cursor: pointer;
    transition: background .15s, transform .1s;
}
.pos-checkout-btn:hover { background: #556022; }
.pos-checkout-btn:active { transform: scale(.98); }
.pos-checkout-btn:disabled { background: #c5c0b0; cursor: not-allowed; transform: none; }

/* ── Fiş Modal ──────────────────────────────────────────────── */
.pos-receipt-modal {
    display: none;
    position: fixed;
    inset: 0;
    z-index: 2000;
    background: rgba(0,0,0,.55);
    align-items: center;
    justify-content: center;
}
.pos-receipt-modal.open { display: flex; }
.pos-receipt-box {
    background: #fff;
    border-radius: 16px;
    padding: 32px;
    width: 380px;
    box-shadow: 0 24px 80px rgba(0,0,0,.3);
    font-family: 'Inter', sans-serif;
}
.pos-receipt-header { text-align: center; margin-bottom: 20px; }
.pos-receipt-header h2 { margin: 0; font-size: 20px; color: #3d4a1a; }
.pos-receipt-header p { margin: 4px 0 0; font-size: 13px; color: #888; }
.pos-receipt-divider { border: none; border-top: 1px dashed #d8d0b8; margin: 14px 0; }
.pos-receipt-row {
    display: flex;
    justify-content: space-between;
    font-size: 13px;
    padding: 4px 0;
    color: #333;
}
.pos-receipt-row.bold { font-weight: 700; font-size: 16px; color: #1a1a1a; }
.pos-receipt-actions {
    margin-top: 20px;
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 10px;
}
.pos-receipt-print {
    padding: 12px;
    background: #6b7a2f;
    color: #fff;
    border: none;
    border-radius: 8px;
    cursor: pointer;
    font-size: 14px;
    font-weight: 600;
}
.pos-receipt-print:hover { background: #556022; }
.pos-receipt-new {
    padding: 12px;
    background: none;
    border: 2px solid #d8d0b8;
    border-radius: 8px;
    cursor: pointer;
    font-size: 14px;
    font-weight: 600;
    color: #555;
}
.pos-receipt-new:hover { border-color: #6b7a2f; color: #4a5a1f; }

/* Print stili */
@media print {
    body > * { display: none !important; }
    .pos-receipt-print-area { display: block !important; }
}
.pos-receipt-print-area { display: none; }

/* ── Bildirim toast ─────────────────────────────────────────── */
.pos-toast {
    position: fixed;
    bottom: 24px;
    left: 50%;
    transform: translateX(-50%) translateY(80px);
    background: #1a1a1a;
    color: #fff;
    padding: 12px 22px;
    border-radius: 999px;
    font-size: 14px;
    font-weight: 500;
    z-index: 9999;
    transition: transform .3s ease, opacity .3s ease;
    opacity: 0;
    pointer-events: none;
    white-space: nowrap;
}
.pos-toast.show { transform: translateX(-50%) translateY(0); opacity: 1; }
.pos-toast.error { background: #c0392b; }
.pos-toast.success { background: #27ae60; }

@media (max-width: 900px) {
    .pos-wrap { grid-template-columns: 1fr; grid-template-rows: auto 1fr auto; height: auto; }
    .pos-cart { max-height: 420px; }
}
</style>

<div class="pos-wrap" id="posWrap">

  <!-- ── Arama Çubuğu ── -->
  <div class="pos-search-bar">
    <svg class="pos-search-ico" viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="7.5"/><path d="m21 21-4.5-4.5"/></svg>
    <input type="search" id="posQuery" placeholder="Barkod okut veya ürün adı / SKU yaz…"
           autocomplete="off" autocorrect="off" autocapitalize="off" spellcheck="false">
    <span class="pos-scan-hint">USB barkod okuyucu otomatik çalışır</span>
  </div>

  <!-- ── Ürün Listesi ── -->
  <div class="pos-products" id="posProducts">
    <div class="pos-products-empty" id="posEmpty">
      <svg viewBox="0 0 24 24" width="64" height="64" fill="none" stroke="currentColor" stroke-width="1.2"><path d="M21 7 12 2 3 7v10l9 5 9-5V7Z"/><path d="m3 7 9 5 9-5"/><path d="M12 22V12"/></svg>
      <p>Barkod okutun veya ürün adı yazın</p>
      <p style="font-size:13px;color:#ccc">Arama sonuçları burada görünecek</p>
    </div>
    <div class="pos-product-grid" id="posGrid" style="display:none"></div>
  </div>

  <!-- ── Sepet ── -->
  <div class="pos-cart">
    <div class="pos-cart-head">
      <span>Sepet</span>
      <button class="pos-cart-clear" id="posClearCart" style="display:none">Temizle</button>
    </div>
    <div class="pos-cart-items" id="posCartItems">
      <div class="pos-cart-empty" id="posCartEmpty">
        <svg viewBox="0 0 24 24" width="40" height="40" fill="none" stroke="currentColor" stroke-width="1.4"><path d="M6 2 3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4Z"/><path d="M3 6h18"/><path d="M16 10a4 4 0 0 1-8 0"/></svg>
        <p>Sepet boş</p>
        <p style="font-size:11px">Ürün eklemek için soldaki kartlara tıklayın</p>
      </div>
    </div>

    <div class="pos-cart-foot">
      <div class="pos-cart-total-row">
        <span class="pos-cart-total-label">Toplam</span>
        <span class="pos-cart-total-val" id="posTotal">₺ 0,00</span>
      </div>

      <div class="pos-customer-row">
        <input type="text" id="posCustomerName" placeholder="Müşteri adı (opsiyonel)">
      </div>

      <div class="pos-payment-row">
        <button class="pos-pay-btn active" data-pay="nakit">💵 Nakit</button>
        <button class="pos-pay-btn" data-pay="kart">💳 Kart</button>
        <button class="pos-pay-btn" data-pay="havale">🏦 Havale</button>
      </div>

      <button class="pos-checkout-btn" id="posCheckoutBtn" disabled>
        TAHSİL ET →
      </button>
    </div>
  </div>
</div>

<!-- ── Varyasyon Seçici Modal ── -->
<div class="pos-var-modal" id="posVarModal">
  <div class="pos-var-box">
    <h3 id="posVarTitle">Seçenek Seçin</h3>
    <div class="pos-var-list" id="posVarList"></div>
    <button class="pos-var-cancel" id="posVarCancel">İptal</button>
  </div>
</div>

<!-- ── Fiş Modal ── -->
<div class="pos-receipt-modal" id="posReceiptModal">
  <div class="pos-receipt-box">
    <div class="pos-receipt-header">
      <h2>✓ Satış Tamamlandı</h2>
      <p id="receiptDate"></p>
    </div>
    <div id="receiptBody"></div>
    <div class="pos-receipt-actions">
      <button class="pos-receipt-print" onclick="printReceipt()">🖨️ Yazdır</button>
      <button class="pos-receipt-new" id="posNewSale">Yeni Satış</button>
    </div>
  </div>
</div>

<!-- Print area -->
<div class="pos-receipt-print-area" id="printArea"></div>

<!-- Toast -->
<div class="pos-toast" id="posToast"></div>

<script>
(function () {
'use strict';

const CSRF     = <?= json_encode(csrf_token()) ?>;
const SITE_URL = <?= json_encode(rtrim(SITE_URL, '/')) ?>;

// ── State ────────────────────────────────────────────────────
let cart       = [];   // [{id, name, sku, price, qty, variation_id, variation_label, stock}]
let payment    = 'nakit';
let debTimer   = null;
let lastReceipt = null;

// ── DOM ──────────────────────────────────────────────────────
const query       = document.getElementById('posQuery');
const grid        = document.getElementById('posGrid');
const empty       = document.getElementById('posEmpty');
const cartItems   = document.getElementById('posCartItems');
const cartEmpty   = document.getElementById('posCartEmpty');
const clearBtn    = document.getElementById('posClearCart');
const totalEl     = document.getElementById('posTotal');
const checkoutBtn = document.getElementById('posCheckoutBtn');
const customerEl  = document.getElementById('posCustomerName');
const varModal    = document.getElementById('posVarModal');
const varTitle    = document.getElementById('posVarTitle');
const varList     = document.getElementById('posVarList');
const varCancel   = document.getElementById('posVarCancel');
const receiptModal= document.getElementById('posReceiptModal');
const toastEl     = document.getElementById('posToast');

// ── Autofocus ────────────────────────────────────────────────
query.focus();
document.addEventListener('keydown', (e) => {
    if (e.target === query) return;
    if (varModal.classList.contains('open')) return;
    if (receiptModal.classList.contains('open')) return;
    if (['INPUT','TEXTAREA','SELECT'].includes(e.target.tagName)) return;
    query.focus();
});

// ── Arama ────────────────────────────────────────────────────
query.addEventListener('input', () => {
    clearTimeout(debTimer);
    debTimer = setTimeout(doSearch, 250);
});

query.addEventListener('keydown', (e) => {
    if (e.key === 'Enter') { clearTimeout(debTimer); doSearch(); }
});

async function doSearch() {
    const q = query.value.trim();
    if (q.length === 0) { showEmpty(); return; }

    try {
        const res = await fetch(SITE_URL + '/ajax/pos-lookup.php?q=' + encodeURIComponent(q));
        const data = await res.json();
        renderResults(data.results || []);
    } catch {
        toast('Arama hatası', 'error');
    }
}

function showEmpty() {
    grid.style.display = 'none';
    empty.style.display = 'flex';
}

function renderResults(results) {
    if (results.length === 0) {
        grid.innerHTML = '<p style="color:#bbb;padding:20px;grid-column:1/-1;text-align:center">Ürün bulunamadı</p>';
        grid.style.display = 'grid';
        empty.style.display = 'none';
        return;
    }
    grid.innerHTML = results.map(p => {
        const totalStock = p.has_variations
            ? p.variations.reduce((s, v) => s + v.stock, 0)
            : p.stock;
        // "İletişime Geçin" ürünü: online'da kapalı ama MAĞAZADA (POS) satılabilir.
        // Yalnızca fiyatı girilmemişse (0) ve varyasyonu yoksa POS'ta satılamaz.
        const noFixedPrice = p.price_on_request && !p.has_variations && (!p.price || p.price <= 0);
        const oos = totalStock <= 0 || noFixedPrice;

        const imgHtml = p.image
            ? `<img class="pos-product-img" src="${escHtml(p.image)}" alt="${escHtml(p.name)}" loading="lazy">`
            : `<div class="pos-product-ph">${escHtml(p.name.charAt(0))}</div>`;

        const priceHtml = noFixedPrice
            ? '<span style="font-size:12px;color:#888">Fiyat girilmemiş</span>'
            : `<span class="pos-product-price">${fmt(p.price)}</span>`;

        const skuHtml = p.sku ? `<div class="pos-product-sku">${escHtml(p.sku)}</div>` : '';
        const stockHtml = `<div class="pos-product-stock">Stok: ${totalStock}</div>`;

        return `<div class="pos-product-card${oos?' out-of-stock':''}"
                     data-product='${escAttr(JSON.stringify(p))}'
                     onclick="addProduct(this)">
            ${imgHtml}
            <div class="pos-product-cat">${escHtml(p.cat_name)}</div>
            <div class="pos-product-name">${escHtml(p.name)}</div>
            ${skuHtml}
            ${priceHtml}
            ${stockHtml}
        </div>`;
    }).join('');
    grid.style.display = 'grid';
    empty.style.display = 'none';

    // Tek sonuç varsa SKU ile tam eşleşme: otomatik ekle
    if (results.length === 1 && query.value.trim() === results[0].sku) {
        const card = grid.querySelector('.pos-product-card:not(.out-of-stock)');
        if (card) { addProduct(card); query.value = ''; query.focus(); }
    }
}

// ── Ürün Ekle ────────────────────────────────────────────────
window.addProduct = function(el) {
    const p = JSON.parse(el.dataset.product);
    if (p.price_on_request && !p.has_variations && (!p.price || p.price <= 0)) {
        toast('Bu ürünün fiyatı girilmemiş — POS\'ta satmak için ürüne fiyat girin', 'error'); return;
    }

    if (p.has_variations && p.variations.length > 0) {
        openVarModal(p);
    } else {
        addToCart({ id: p.id, name: p.name, sku: p.sku, price: p.price,
                    qty: 1, variation_id: null, variation_label: null, stock: p.stock });
    }
};

function openVarModal(p) {
    varTitle.textContent = p.name + ' — Seçenek Seç';
    varList.innerHTML = p.variations.map(v =>
        `<button class="pos-var-btn${v.stock<=0?' ':''}" ${v.stock<=0?'disabled':''}
                 onclick="pickVar(${JSON.stringify({id:p.id,name:p.name,sku:p.sku,varId:v.id,label:v.label,price:v.price,stock:v.stock}).replace(/"/g,'&quot;')})">
            <div>
                <span>${escHtml(v.label)}</span>
                <span class="pos-var-btn-stock">Stok: ${v.stock}</span>
            </div>
            <span class="pos-var-btn-price">${fmt(v.price)}</span>
        </button>`
    ).join('');
    varModal.classList.add('open');
}

window.pickVar = function(d) {
    varModal.classList.remove('open');
    addToCart({ id: d.id, name: d.name, sku: d.sku, price: d.price,
                qty: 1, variation_id: d.varId, variation_label: d.label, stock: d.stock });
};

varCancel.onclick = () => varModal.classList.remove('open');
varModal.addEventListener('click', (e) => { if (e.target === varModal) varModal.classList.remove('open'); });

function addToCart(item) {
    const key = item.id + '_' + (item.variation_id || '0');
    const existing = cart.find(c => c.id === item.id && c.variation_id === item.variation_id);
    if (existing) {
        if (existing.qty >= existing.stock) { toast('Maksimum stoğa ulaşıldı', 'error'); return; }
        existing.qty++;
    } else {
        cart.push({ ...item, key });
    }
    renderCart();
    toast(item.name + ' eklendi', 'success');
}

// ── Sepet Render ─────────────────────────────────────────────
function renderCart() {
    clearBtn.style.display = cart.length ? 'inline-block' : 'none';
    checkoutBtn.disabled = cart.length === 0;

    if (cart.length === 0) {
        cartEmpty.style.display = 'flex';
        // Temizle (eski satırlar)
        [...cartItems.children].forEach(ch => { if (ch !== cartEmpty) ch.remove(); });
        totalEl.textContent = '₺ 0,00';
        return;
    }

    cartEmpty.style.display = 'none';
    [...cartItems.querySelectorAll('.pos-cart-item')].forEach(el => el.remove());

    let total = 0;
    cart.forEach((item, idx) => {
        total += item.price * item.qty;
        const div = document.createElement('div');
        div.className = 'pos-cart-item';
        div.innerHTML = `
            <div class="pos-cart-item-info">
                <div class="pos-cart-item-name">${escHtml(item.name)}</div>
                ${item.variation_label ? `<div class="pos-cart-item-var">${escHtml(item.variation_label)}</div>` : ''}
                <div class="pos-cart-item-price">${fmt(item.price)} × ${item.qty}</div>
            </div>
            <div>
                <div class="pos-cart-item-line">${fmt(item.price * item.qty)}</div>
                <div class="pos-qty-wrap" style="margin-top:4px">
                    <button class="pos-qty-btn remove" title="Kaldır" onclick="removeItem(${idx})">−</button>
                    <span class="pos-qty-num">${item.qty}</span>
                    <button class="pos-qty-btn" title="Artır" onclick="increaseItem(${idx})">+</button>
                </div>
            </div>`;
        cartItems.appendChild(div);
    });

    totalEl.textContent = fmt(total);
}

window.removeItem = function(idx) {
    cart[idx].qty--;
    if (cart[idx].qty <= 0) cart.splice(idx, 1);
    renderCart();
};

window.increaseItem = function(idx) {
    if (cart[idx].qty >= cart[idx].stock) { toast('Maksimum stok', 'error'); return; }
    cart[idx].qty++;
    renderCart();
};

document.getElementById('posClearCart').onclick = () => {
    if (!confirm('Sepeti temizlemek istiyor musunuz?')) return;
    cart = [];
    renderCart();
};

// ── Ödeme Seçimi ─────────────────────────────────────────────
document.querySelectorAll('.pos-pay-btn').forEach(btn => {
    btn.addEventListener('click', () => {
        document.querySelectorAll('.pos-pay-btn').forEach(b => b.classList.remove('active'));
        btn.classList.add('active');
        payment = btn.dataset.pay;
    });
});

// ── Tahsil Et ────────────────────────────────────────────────
checkoutBtn.addEventListener('click', async () => {
    if (cart.length === 0) return;
    checkoutBtn.disabled = true;
    checkoutBtn.textContent = 'İşleniyor…';

    try {
        const res = await fetch(SITE_URL + '/ajax/pos-sale.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                csrf: CSRF,
                items: cart.map(c => ({
                    id: c.id, name: c.name, price: c.price,
                    qty: c.qty, variation_id: c.variation_id
                })),
                payment,
                customer_name: customerEl.value.trim(),
            }),
        });
        const data = await res.json();

        if (data.ok) {
            lastReceipt = data.receipt;
            showReceipt(data.receipt);
            cart = [];
            renderCart();
            query.value = '';
            showEmpty();
        } else {
            toast(data.error || 'Hata oluştu', 'error');
        }
    } catch {
        toast('Bağlantı hatası', 'error');
    } finally {
        checkoutBtn.disabled = false;
        checkoutBtn.textContent = 'TAHSİL ET →';
    }
});

// ── Fiş Modal ────────────────────────────────────────────────
function showReceipt(r) {
    document.getElementById('receiptDate').textContent = r.date + ' · #' + r.order_id;
    const payMap = { nakit: '💵 Nakit', kart: '💳 Kart', havale: '🏦 Havale' };
    let html = `
        <div class="pos-receipt-row"><span>Müşteri</span><span>${escHtml(r.customer_name)}</span></div>
        <div class="pos-receipt-row"><span>Kasiyer</span><span>${escHtml(r.cashier)}</span></div>
        <div class="pos-receipt-row"><span>Ödeme</span><span>${payMap[r.payment] || r.payment}</span></div>
        <hr class="pos-receipt-divider">`;
    r.items.forEach(it => {
        html += `<div class="pos-receipt-row">
            <span>${escHtml(it.name)} × ${it.qty}</span>
            <span>${fmt(it.line_total)}</span>
        </div>`;
    });
    html += `<hr class="pos-receipt-divider">
        <div class="pos-receipt-row bold"><span>TOPLAM</span><span>${fmt(r.total)}</span></div>`;
    document.getElementById('receiptBody').innerHTML = html;
    receiptModal.classList.add('open');
}

document.getElementById('posNewSale').onclick = () => {
    receiptModal.classList.remove('open');
    customerEl.value = '';
    query.focus();
};

// Yazdırma
window.printReceipt = function() {
    if (!lastReceipt) return;
    const r = lastReceipt;
    const payMap = { nakit: 'Nakit', kart: 'Kart', havale: 'Havale/EFT' };
    let rows = r.items.map(it =>
        `<tr><td>${escHtml(it.name)} × ${it.qty}</td><td style="text-align:right">${fmt(it.line_total)}</td></tr>`
    ).join('');

    const win = window.open('', '_blank', 'width=400,height=600');
    win.document.write(`<!DOCTYPE html><html><head><meta charset="utf-8">
    <title>Fiş #${r.order_id}</title>
    <style>
      body{font-family:monospace;font-size:13px;padding:16px;max-width:300px;margin:auto}
      h2{text-align:center;font-size:15px;margin:0 0 4px}
      p{text-align:center;margin:0 0 10px;font-size:11px;color:#666}
      table{width:100%;border-collapse:collapse}
      td{padding:4px 0}
      .total td{font-weight:bold;font-size:15px;border-top:1px dashed #999;padding-top:6px}
      .meta td{font-size:11px;color:#555}
    </style></head><body>
    <h2>${escHtml(document.querySelector('.brand h1').textContent)}</h2>
    <p>${r.date} · Sipariş #${r.order_id}</p>
    <table>
      <tr class="meta"><td>Müşteri</td><td style="text-align:right">${escHtml(r.customer_name)}</td></tr>
      <tr class="meta"><td>Kasiyer</td><td style="text-align:right">${escHtml(r.cashier)}</td></tr>
      <tr class="meta"><td>Ödeme</td><td style="text-align:right">${payMap[r.payment]||r.payment}</td></tr>
    </table>
    <hr style="border:none;border-top:1px dashed #999;margin:8px 0">
    <table>${rows}</table>
    <table><tr class="total"><td>TOPLAM</td><td style="text-align:right">${fmt(r.total)}</td></tr></table>
    <p style="margin-top:12px">Teşekkürler!</p>
    </body></html>`);
    win.document.close();
    win.focus();
    win.print();
};

// ── Toast ─────────────────────────────────────────────────────
let toastTimer;
function toast(msg, type = '') {
    toastEl.textContent = msg;
    toastEl.className = 'pos-toast' + (type ? ' ' + type : '');
    void toastEl.offsetWidth;
    toastEl.classList.add('show');
    clearTimeout(toastTimer);
    toastTimer = setTimeout(() => toastEl.classList.remove('show'), 2400);
}

// ── Helpers ──────────────────────────────────────────────────
function fmt(n) {
    return '₺ ' + Number(n).toLocaleString('tr-TR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}
function escHtml(s) {
    return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}
function escAttr(s) {
    return String(s).replace(/'/g,'&#39;');
}

})();
</script>

<?php require_once __DIR__ . '/core/footer.php'; ?>
