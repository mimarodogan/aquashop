-- Favorilere fiyat takibi kolonu ekle
ALTER TABLE favorites
  ADD COLUMN IF NOT EXISTS price_at_fav DECIMAL(10,2) DEFAULT NULL AFTER created_at;
