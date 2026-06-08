-- Çoka-çok ürün-kategori ilişki tablosu
CREATE TABLE IF NOT EXISTS product_categories (
  product_id  INT NOT NULL,
  category_id INT NOT NULL,
  PRIMARY KEY (product_id, category_id),
  INDEX idx_cat (category_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Mevcut products.category_id verilerini yeni tabloya kopyala
INSERT IGNORE INTO product_categories (product_id, category_id)
SELECT id, category_id FROM products WHERE category_id IS NOT NULL;
