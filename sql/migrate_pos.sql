-- POS (Mağaza Satışı) migrasyonu
-- Siparişlere kaynak (web/pos) ve kasiyer bilgisi ekler

ALTER TABLE orders
  ADD COLUMN IF NOT EXISTS source ENUM('web','pos') NOT NULL DEFAULT 'web' AFTER payment_method,
  ADD COLUMN IF NOT EXISTS pos_cashier_id INT NULL AFTER source;

-- POS satışlarını hızlı sorgulamak için index
ALTER TABLE orders ADD INDEX IF NOT EXISTS idx_source (source);
