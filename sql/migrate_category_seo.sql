-- Kategoriler tablosuna SEO + içerik alanları ekle
-- Çalıştırma: phpMyAdmin veya MySQL terminali üzerinden bir kez çalıştır.

ALTER TABLE categories
  ADD COLUMN IF NOT EXISTS sort_order INT NOT NULL DEFAULT 0 AFTER parent_id,
  ADD COLUMN IF NOT EXISTS image VARCHAR(255) DEFAULT NULL AFTER sort_order,
  ADD COLUMN IF NOT EXISTS description TEXT DEFAULT NULL AFTER image,
  ADD COLUMN IF NOT EXISTS meta_title VARCHAR(255) DEFAULT NULL AFTER description,
  ADD COLUMN IF NOT EXISTS meta_description TEXT DEFAULT NULL AFTER meta_title;
