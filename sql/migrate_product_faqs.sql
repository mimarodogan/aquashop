CREATE TABLE IF NOT EXISTS product_faqs (
  id INT AUTO_INCREMENT PRIMARY KEY,
  product_id INT NOT NULL,
  question VARCHAR(300) NOT NULL,
  answer TEXT NOT NULL,
  sort_order INT NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_product (product_id),
  CONSTRAINT fk_faq_product FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
