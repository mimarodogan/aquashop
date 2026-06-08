-- E-ticaret veritabanı şeması (MySQL 5.7+/8)
-- NOT: Paylaşımlı hosting (cPanel) ortamında veritabanını panelden oluşturup
--      bu dosyayı phpMyAdmin > İçe Aktar ile o veritabanına yükleyin.

CREATE TABLE IF NOT EXISTS users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(120) NOT NULL,
  email VARCHAR(190) NOT NULL UNIQUE,
  password VARCHAR(255) NOT NULL,
  phone VARCHAR(40) DEFAULT NULL,
  address TEXT DEFAULT NULL,
  role ENUM('customer','admin') NOT NULL DEFAULT 'customer',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS categories (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(120) NOT NULL,
  slug VARCHAR(140) NOT NULL UNIQUE,
  parent_id INT DEFAULT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_cat_parent FOREIGN KEY (parent_id) REFERENCES categories(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS products (
  id INT AUTO_INCREMENT PRIMARY KEY,
  category_id INT DEFAULT NULL,
  name VARCHAR(180) NOT NULL,
  slug VARCHAR(200) NOT NULL UNIQUE,
  short_desc VARCHAR(500) DEFAULT NULL,
  description TEXT DEFAULT NULL,
  price DECIMAL(10,2) NOT NULL DEFAULT 0,
  old_price DECIMAL(10,2) DEFAULT NULL,
  stock INT NOT NULL DEFAULT 0,
  image VARCHAR(255) DEFAULT NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  is_featured TINYINT(1) NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_prod_cat FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS product_images (
  id INT AUTO_INCREMENT PRIMARY KEY,
  product_id INT NOT NULL,
  path VARCHAR(255) NOT NULL,
  sort_order INT DEFAULT 0,
  CONSTRAINT fk_img_prod FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS orders (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT DEFAULT NULL,
  full_name VARCHAR(150) NOT NULL,
  email VARCHAR(190) NOT NULL,
  phone VARCHAR(40) NOT NULL,
  address TEXT NOT NULL,
  city VARCHAR(80) NOT NULL,
  total DECIMAL(10,2) NOT NULL DEFAULT 0,
  status ENUM('pending','paid','shipped','delivered','cancelled') NOT NULL DEFAULT 'pending',
  payment_method VARCHAR(40) DEFAULT 'havale',
  note TEXT DEFAULT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_ord_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS order_items (
  id INT AUTO_INCREMENT PRIMARY KEY,
  order_id INT NOT NULL,
  product_id INT DEFAULT NULL,
  product_name VARCHAR(180) NOT NULL,
  qty INT NOT NULL DEFAULT 1,
  price DECIMAL(10,2) NOT NULL,
  CONSTRAINT fk_oi_order FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
  CONSTRAINT fk_oi_prod FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS settings (
  setting_key VARCHAR(80) PRIMARY KEY,
  setting_value TEXT
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS contact_messages (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(120) NOT NULL,
  email VARCHAR(190) NOT NULL,
  subject VARCHAR(200) DEFAULT NULL,
  message TEXT NOT NULL,
  is_read TINYINT(1) NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Demo verileri
INSERT INTO settings (setting_key, setting_value) VALUES
  ('site_name','E-Ticaret'),
  ('site_tagline','PREMIUM'),
  ('contact_email','info@example.com'),
  ('contact_phone','+90 555 000 00 00'),
  ('contact_address','İstanbul, Türkiye')
ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value);

-- Şifre: admin123  (password_hash, bcrypt)
INSERT INTO users (name,email,password,role) VALUES
  ('Yönetici','admin@example.com','$2y$10$wH8YQ8m3Pm4qkZ0pQwJZguBxFh2gqv8bJqQ8n4Xq7L3GqP2N5d6Iy','admin')
ON DUPLICATE KEY UPDATE name=VALUES(name);

INSERT INTO categories (name,slug) VALUES
  ('Yeni Sezon','yeni-sezon'),
  ('Klasik','klasik'),
  ('Koleksiyon','koleksiyon'),
  ('Kampanya','kampanya')
ON DUPLICATE KEY UPDATE name=VALUES(name);

INSERT INTO products (category_id,name,slug,short_desc,description,price,old_price,stock,is_featured) VALUES
  (1,'Premium Ürün I','premium-urun-i','Özenle seçilmiş premium ürün.','Detaylı ürün açıklaması burada yer alır.',1250.00,NULL,25,1),
  (2,'Zarif Tasarım II','zarif-tasarim-ii','Zarafetin sade ifadesi.','Detaylı ürün açıklaması burada yer alır.',890.00,990.00,40,1),
  (2,'Klasik Seri III','klasik-seri-iii','Zamansız klasik dokunuş.','Detaylı ürün açıklaması burada yer alır.',1490.00,NULL,15,1),
  (3,'İmza Koleksiyon IV','imza-koleksiyon-iv','İmza koleksiyonun çok satan parçası.','Detaylı ürün açıklaması burada yer alır.',2150.00,NULL,8,1),
  (1,'Yeni Sezon V','yeni-sezon-v','Bu sezonun yeni ürünü.','Detaylı ürün açıklaması burada yer alır.',1090.00,NULL,30,0),
  (4,'Kampanyalı VI','kampanyali-vi','Kampanyalı fırsat ürünü.','Detaylı ürün açıklaması burada yer alır.',650.00,850.00,50,0),
  (3,'Koleksiyon VII','koleksiyon-vii','Sınırlı sayıda üretilmiş.','Detaylı ürün açıklaması burada yer alır.',1790.00,NULL,12,0),
  (2,'Klasik VIII','klasik-viii','Klasiğin sade hali.','Detaylı ürün açıklaması burada yer alır.',780.00,NULL,60,0)
ON DUPLICATE KEY UPDATE name=VALUES(name);
