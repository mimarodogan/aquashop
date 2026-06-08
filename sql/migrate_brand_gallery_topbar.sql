-- Marka alanı
ALTER TABLE products ADD COLUMN brand VARCHAR(120) DEFAULT NULL AFTER sku;
CREATE INDEX idx_products_brand ON products(brand);

-- Topbar mesajı (varsa güncelle, yoksa ekle)
INSERT INTO settings (setting_key, setting_value) VALUES
  ('topbar_message','Yurt Geneli Ücretsiz Kargo · Güvenli Ödeme · 14 Gün İade'),
  ('topbar_enabled','1')
ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value);

-- Eğer yoksa product_images tablosu (galeri için) — schema'da zaten var, idempotent
CREATE TABLE IF NOT EXISTS product_images (
  id INT AUTO_INCREMENT PRIMARY KEY,
  product_id INT NOT NULL,
  path VARCHAR(255) NOT NULL,
  sort_order INT DEFAULT 0,
  KEY idx_product (product_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
