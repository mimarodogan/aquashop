-- Phase 2: Şirket faturası, çoklu adres, restock notify, kupon, yorum, iade
-- Tek seferde phpMyAdmin SQL sekmesinde çalıştır.

-- 1) orders tablosuna fatura tipi ve şirket alanları
ALTER TABLE orders
  ADD COLUMN IF NOT EXISTS invoice_type ENUM('individual','company') NOT NULL DEFAULT 'individual' AFTER note,
  ADD COLUMN IF NOT EXISTS invoice_tax_no VARCHAR(20) DEFAULT NULL AFTER invoice_type,
  ADD COLUMN IF NOT EXISTS invoice_tax_office VARCHAR(120) DEFAULT NULL AFTER invoice_tax_no,
  ADD COLUMN IF NOT EXISTS invoice_company VARCHAR(190) DEFAULT NULL AFTER invoice_tax_office,
  ADD COLUMN IF NOT EXISTS invoice_eposta_zorunlu VARCHAR(190) DEFAULT NULL AFTER invoice_company,
  ADD COLUMN IF NOT EXISTS shipping_amount DECIMAL(10,2) DEFAULT 0 AFTER invoice_eposta_zorunlu,
  ADD COLUMN IF NOT EXISTS subtotal DECIMAL(10,2) DEFAULT 0 AFTER shipping_amount,
  ADD COLUMN IF NOT EXISTS vat_amount DECIMAL(10,2) DEFAULT 0 AFTER subtotal;

-- 2) Çoklu adres
CREATE TABLE IF NOT EXISTS user_addresses (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  label VARCHAR(60) NOT NULL DEFAULT 'Ev',
  full_name VARCHAR(150) NOT NULL,
  phone VARCHAR(40) NOT NULL,
  address TEXT NOT NULL,
  city VARCHAR(80) NOT NULL,
  district VARCHAR(80) DEFAULT NULL,
  zip VARCHAR(15) DEFAULT NULL,
  is_default TINYINT(1) NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_user (user_id),
  CONSTRAINT fk_addr_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3) Stoka gelince haber ver
CREATE TABLE IF NOT EXISTS restock_notifications (
  id INT AUTO_INCREMENT PRIMARY KEY,
  product_id INT NOT NULL,
  email VARCHAR(190) NOT NULL,
  notified_at DATETIME DEFAULT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uniq_pending (product_id, email, notified_at),
  INDEX idx_product (product_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4) Kupon kodları
CREATE TABLE IF NOT EXISTS coupons (
  id INT AUTO_INCREMENT PRIMARY KEY,
  code VARCHAR(40) NOT NULL UNIQUE,
  type ENUM('percent','fixed','free_shipping') NOT NULL DEFAULT 'percent',
  amount DECIMAL(10,2) NOT NULL DEFAULT 0,
  min_cart DECIMAL(10,2) NOT NULL DEFAULT 0,
  max_discount DECIMAL(10,2) DEFAULT NULL,
  usage_limit INT DEFAULT NULL,
  usage_count INT NOT NULL DEFAULT 0,
  per_user_limit TINYINT NOT NULL DEFAULT 1,
  starts_at DATETIME DEFAULT NULL,
  ends_at DATETIME DEFAULT NULL,
  enabled TINYINT(1) NOT NULL DEFAULT 1,
  notes VARCHAR(255) DEFAULT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_enabled (enabled)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS coupon_redemptions (
  id INT AUTO_INCREMENT PRIMARY KEY,
  coupon_id INT NOT NULL,
  user_id INT DEFAULT NULL,
  order_id INT NOT NULL,
  amount DECIMAL(10,2) NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_coupon (coupon_id),
  INDEX idx_order (order_id),
  CONSTRAINT fk_red_coupon FOREIGN KEY (coupon_id) REFERENCES coupons(id) ON DELETE CASCADE,
  CONSTRAINT fk_red_order FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 5) orders'a kupon kayıtları
ALTER TABLE orders
  ADD COLUMN IF NOT EXISTS coupon_code VARCHAR(40) DEFAULT NULL AFTER vat_amount,
  ADD COLUMN IF NOT EXISTS discount_amount DECIMAL(10,2) DEFAULT 0 AFTER coupon_code;

-- 6) Ürün yorumları + 5 yıldız
CREATE TABLE IF NOT EXISTS product_reviews (
  id INT AUTO_INCREMENT PRIMARY KEY,
  product_id INT NOT NULL,
  user_id INT DEFAULT NULL,
  author_name VARCHAR(120) NOT NULL,
  author_email VARCHAR(190) DEFAULT NULL,
  rating TINYINT NOT NULL,
  title VARCHAR(200) DEFAULT NULL,
  body TEXT DEFAULT NULL,
  is_approved TINYINT(1) NOT NULL DEFAULT 0,
  is_verified_buyer TINYINT(1) NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_product (product_id),
  INDEX idx_approved (is_approved),
  CONSTRAINT chk_rating CHECK (rating BETWEEN 1 AND 5)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 7) İade talepleri (kullanıcı tarafında)
CREATE TABLE IF NOT EXISTS return_requests (
  id INT AUTO_INCREMENT PRIMARY KEY,
  order_id INT NOT NULL,
  user_id INT DEFAULT NULL,
  reason VARCHAR(80) NOT NULL,
  description TEXT DEFAULT NULL,
  status ENUM('pending','approved','rejected','processing','completed') NOT NULL DEFAULT 'pending',
  admin_note TEXT DEFAULT NULL,
  refund_amount DECIMAL(10,2) DEFAULT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_order (order_id),
  INDEX idx_status (status),
  CONSTRAINT fk_ret_order FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS return_items (
  id INT AUTO_INCREMENT PRIMARY KEY,
  return_id INT NOT NULL,
  order_item_id INT NOT NULL,
  qty INT NOT NULL DEFAULT 1,
  CONSTRAINT fk_ri_return FOREIGN KEY (return_id) REFERENCES return_requests(id) ON DELETE CASCADE,
  CONSTRAINT fk_ri_orditem FOREIGN KEY (order_item_id) REFERENCES order_items(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
