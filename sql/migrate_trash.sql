-- Ürünler için yumuşak silme (soft delete) sütunu
-- Çöp kutusu sistemi: deleted_at doluysa çöpte, NULL ise aktif
ALTER TABLE products
  ADD COLUMN IF NOT EXISTS deleted_at DATETIME NULL DEFAULT NULL;

-- Hızlı filtre için index
ALTER TABLE products
  ADD INDEX IF NOT EXISTS idx_deleted_at (deleted_at);
