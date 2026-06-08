-- Fiyatsız ürün desteği — "İletişime Geçin" modu
ALTER TABLE products
  ADD COLUMN IF NOT EXISTS price_on_request TINYINT(1) NOT NULL DEFAULT 0
  AFTER old_price;
