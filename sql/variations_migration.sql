-- Ürün varyasyonları (1L / 5L gibi)
-- Tek seferlik phpMyAdmin SQL sekmesinde çalıştır.

CREATE TABLE IF NOT EXISTS product_variations (
  id INT AUTO_INCREMENT PRIMARY KEY,
  product_id INT NOT NULL,
  label VARCHAR(120) NOT NULL,
  sku VARCHAR(80) DEFAULT NULL,
  price DECIMAL(10,2) NOT NULL DEFAULT 0,
  old_price DECIMAL(10,2) DEFAULT NULL,
  stock INT NOT NULL DEFAULT 0,
  image VARCHAR(255) DEFAULT NULL,
  sort_order INT NOT NULL DEFAULT 0,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_product (product_id),
  INDEX idx_sku (sku),
  CONSTRAINT fk_var_product FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- order_items'a varyasyon referansı
ALTER TABLE order_items
  ADD COLUMN IF NOT EXISTS variation_id INT DEFAULT NULL AFTER product_id,
  ADD COLUMN IF NOT EXISTS variation_label VARCHAR(120) DEFAULT NULL AFTER variation_id,
  ADD INDEX IF NOT EXISTS idx_variation (variation_id);

-- products tablosuna varyasyonlu olduğunu işaretleyen flag
ALTER TABLE products
  ADD COLUMN IF NOT EXISTS has_variations TINYINT(1) NOT NULL DEFAULT 0 AFTER is_featured;
