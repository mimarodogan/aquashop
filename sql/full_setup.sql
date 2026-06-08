-- =====================================================================
-- TEK DOSYALIK KURULUM / ONARIM
-- Çalıştırma: phpMyAdmin > (config/db.php'deki DB_NAME) > SQL sekmesine yapıştır > Git
-- (İçe Aktar yerine SQL kutusuna YAPIŞTIRMAK karakter sorunlarını engeller.)
-- Tekrar tekrar güvenle çalıştırılabilir.
-- =====================================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ---------------------------------------------------------------------
-- 1) TABLOLAR (yoksa oluştur)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(120) NOT NULL,
  first_name VARCHAR(80) DEFAULT NULL,
  last_name  VARCHAR(80) DEFAULT NULL,
  email VARCHAR(190) NOT NULL UNIQUE,
  password VARCHAR(255) NOT NULL,
  phone VARCHAR(40) DEFAULT NULL,
  address TEXT DEFAULT NULL,
  birth_date DATE DEFAULT NULL,
  email_consent TINYINT(1) NOT NULL DEFAULT 0,
  sms_consent   TINYINT(1) NOT NULL DEFAULT 0,
  role ENUM('customer','admin') NOT NULL DEFAULT 'customer',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS categories (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(120) NOT NULL,
  slug VARCHAR(140) NOT NULL UNIQUE,
  parent_id INT DEFAULT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS product_images (
  id INT AUTO_INCREMENT PRIMARY KEY,
  product_id INT NOT NULL,
  path VARCHAR(255) NOT NULL,
  sort_order INT DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS order_items (
  id INT AUTO_INCREMENT PRIMARY KEY,
  order_id INT NOT NULL,
  product_id INT DEFAULT NULL,
  product_name VARCHAR(180) NOT NULL,
  qty INT NOT NULL DEFAULT 1,
  price DECIMAL(10,2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS settings (
  setting_key VARCHAR(80) PRIMARY KEY,
  setting_value TEXT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS contact_messages (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(120) NOT NULL,
  email VARCHAR(190) NOT NULL,
  subject VARCHAR(200) DEFAULT NULL,
  message TEXT NOT NULL,
  is_read TINYINT(1) NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS pages (
  id INT AUTO_INCREMENT PRIMARY KEY,
  slug VARCHAR(140) NOT NULL UNIQUE,
  title VARCHAR(200) NOT NULL,
  content MEDIUMTEXT,
  is_published TINYINT(1) NOT NULL DEFAULT 1,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS blog_categories (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(120) NOT NULL,
  slug VARCHAR(140) NOT NULL UNIQUE,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS blog_posts (
  id INT AUTO_INCREMENT PRIMARY KEY,
  category_id INT DEFAULT NULL,
  author_id INT DEFAULT NULL,
  title VARCHAR(220) NOT NULL,
  slug VARCHAR(240) NOT NULL UNIQUE,
  excerpt VARCHAR(500) DEFAULT NULL,
  content MEDIUMTEXT,
  cover_image VARCHAR(255) DEFAULT NULL,
  is_published TINYINT(1) NOT NULL DEFAULT 1,
  published_at DATETIME DEFAULT NULL,
  views INT NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 2) MEVCUT TABLOLARI utf8mb4'e ÇEVİR (eski oluşturulmuşlarsa düzeltir)
-- ---------------------------------------------------------------------
ALTER TABLE users            CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE categories       CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE products         CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE product_images   CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE orders           CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE order_items      CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE settings         CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE contact_messages CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE pages            CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE blog_categories  CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE blog_posts       CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 3) ÖNCEKİ BOZUK SEED VERİLERİNİ TEMİZLE
-- ---------------------------------------------------------------------
DELETE FROM blog_posts;
DELETE FROM blog_categories;
DELETE FROM pages WHERE slug IN
  ('uyelik-kosullari','kvkk','kargo-teslimat','iade-degisim','sss');

-- ---------------------------------------------------------------------
-- 4) AYARLAR
-- ---------------------------------------------------------------------
INSERT INTO settings (setting_key, setting_value) VALUES
  ('site_name','E-Ticaret'),
  ('site_tagline','PREMIUM'),
  ('contact_email','info@example.com'),
  ('contact_phone','+90 555 000 00 00'),
  ('contact_address','İstanbul, Türkiye')
ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value);

-- ---------------------------------------------------------------------
-- 5) MAĞAZA KATEGORİLERİ + DEMO ÜRÜNLER
-- ---------------------------------------------------------------------
INSERT INTO categories (name, slug) VALUES
  ('Yeni Sezon','yeni-sezon'),
  ('Klasik','klasik'),
  ('Koleksiyon','koleksiyon'),
  ('Kampanya','kampanya')
ON DUPLICATE KEY UPDATE name = VALUES(name);

INSERT INTO products (category_id, name, slug, short_desc, description, price, old_price, stock, is_featured) VALUES
  (1,'Premium Ürün I','premium-urun-i','Özenle seçilmiş premium ürün.','Detaylı ürün açıklaması burada yer alır.',1250.00,NULL,25,1),
  (2,'Zarif Tasarım II','zarif-tasarim-ii','Zarafetin sade ifadesi.','Detaylı ürün açıklaması burada yer alır.',890.00,990.00,40,1),
  (2,'Klasik Seri III','klasik-seri-iii','Zamansız klasik dokunuş.','Detaylı ürün açıklaması burada yer alır.',1490.00,NULL,15,1),
  (3,'İmza Koleksiyon IV','imza-koleksiyon-iv','İmza koleksiyonun çok satan parçası.','Detaylı ürün açıklaması burada yer alır.',2150.00,NULL,8,1),
  (1,'Yeni Sezon V','yeni-sezon-v','Bu sezonun yeni ürünü.','Detaylı ürün açıklaması burada yer alır.',1090.00,NULL,30,0),
  (4,'Kampanyalı VI','kampanyali-vi','Kampanyalı fırsat ürünü.','Detaylı ürün açıklaması burada yer alır.',650.00,850.00,50,0),
  (3,'Koleksiyon VII','koleksiyon-vii','Sınırlı sayıda üretilmiş.','Detaylı ürün açıklaması burada yer alır.',1790.00,NULL,12,0),
  (2,'Klasik VIII','klasik-viii','Klasiğin sade hali.','Detaylı ürün açıklaması burada yer alır.',780.00,NULL,60,0)
ON DUPLICATE KEY UPDATE name = VALUES(name);

-- ---------------------------------------------------------------------
-- 6) SAYFALAR (CMS)
-- ---------------------------------------------------------------------
INSERT INTO pages (slug, title, content) VALUES
('uyelik-kosullari','Üyelik Koşulları',
 '<h2>Üyelik Koşulları</h2><p>Bu metin, sitemize üye olan kullanıcıların kabul ettiği koşulları içerir. Yönetim panelinden düzenleyebilirsiniz.</p><h3>1. Genel</h3><p>Üyelik formunu doldurarak işbu koşulları kabul etmiş olursunuz.</p><h3>2. Hak ve Yükümlülükler</h3><p>Hesap bilgilerinizin gizliliğinden siz sorumlusunuz.</p><h3>3. İletişim</h3><p>Sorularınız için iletişim sayfamızı kullanabilirsiniz.</p>'),
('kvkk','Kişisel Verilerin Korunması',
 '<h2>Kişisel Verilerin Korunması Hakkında Aydınlatma Metni</h2><p>6698 sayılı Kişisel Verilerin Korunması Kanunu (KVKK) kapsamında veri sorumlusu sıfatıyla işlediğimiz kişisel verilerinize ilişkin bilgilendirmedir.</p><h3>1. Toplanan Veriler</h3><p>Ad-soyad, e-posta, telefon, adres, doğum tarihi gibi bilgiler.</p><h3>2. İşleme Amaçları</h3><p>Sipariş yönetimi, müşteri ilişkileri, pazarlama (onay verdiyseniz).</p><h3>3. Haklarınız</h3><p>KVKK md.11 kapsamındaki haklarınızı kullanmak için bize başvurabilirsiniz.</p>'),
('kargo-teslimat','Kargo & Teslimat',
 '<h2>Kargo & Teslimat</h2><p>Siparişleriniz onayın ardından <strong>1-3 iş günü</strong> içinde anlaşmalı kargo firmamızla gönderilir.</p><h3>Kargo Süreleri</h3><ul><li>Büyükşehirler: 1-2 iş günü</li><li>Diğer iller: 2-4 iş günü</li><li>Köy/uzak bölgeler: 3-5 iş günü</li></ul><h3>Kargo Ücreti</h3><p>Yurt içi tüm siparişlerde <strong>kargo ücretsizdir</strong>.</p><h3>Sipariş Takibi</h3><p>Siparişiniz kargoya verildiğinde e-posta ile takip numarası iletilir.</p>'),
('iade-degisim','İade & Değişim',
 '<h2>İade & Değişim Politikası</h2><p>Müşteri memnuniyeti önceliğimizdir. Ürünlerinizi <strong>14 gün</strong> içinde iade edebilir veya değiştirebilirsiniz.</p><h3>İade Koşulları</h3><ul><li>Ürün kullanılmamış ve orijinal ambalajında olmalı</li><li>Fatura ile birlikte gönderilmeli</li><li>Hijyenik özellik taşıyan ürünler iade edilemez</li></ul><h3>Nasıl İade Ederim?</h3><ol><li>İletişim sayfasından bize ulaşın</li><li>İade onayı sonrası anlaşmalı kargoyla ücretsiz gönderim</li><li>Ürün tarafımıza ulaştığında 7 iş günü içinde iadeniz işleme alınır</li></ol>'),
('sss','Sıkça Sorulan Sorular',
 '<h2>Sıkça Sorulan Sorular</h2><h3>Siparişim ne zaman elime ulaşır?</h3><p>Onaylanan siparişler 1-3 iş günü içinde kargoya verilir; teslimat süresi bölgenize göre 1-5 iş günüdür.</p><h3>Kargo ücreti var mı?</h3><p>Yurt içi tüm siparişlerde kargo ücretsizdir.</p><h3>Hangi ödeme yöntemlerini kullanabilirim?</h3><p>Havale/EFT, kredi kartı ve kapıda ödeme seçeneklerimiz mevcuttur.</p><h3>Ürünü iade edebilir miyim?</h3><p>Evet, 14 gün içinde koşulsuz iade hakkınız vardır.</p><h3>Üye olmadan sipariş verebilir miyim?</h3><p>Evet, misafir olarak da sipariş verebilirsiniz.</p><h3>Faturamı nasıl alırım?</h3><p>E-fatura/e-arşiv kayıtlı e-posta adresinize otomatik gönderilir.</p>')
ON DUPLICATE KEY UPDATE title = VALUES(title), content = VALUES(content);

-- ---------------------------------------------------------------------
-- 7) BLOG (önce kategoriler, sonra yazılar)
-- ---------------------------------------------------------------------
INSERT INTO blog_categories (name, slug) VALUES
  ('Haberler','haberler'),
  ('Rehberler','rehberler'),
  ('Tarif','tarif'),
  ('Kurumsal','kurumsal')
ON DUPLICATE KEY UPDATE name = VALUES(name);

INSERT INTO blog_posts (category_id, title, slug, excerpt, content, is_published, published_at) VALUES
  ((SELECT id FROM blog_categories WHERE slug='haberler' LIMIT 1),
   'Hoş Geldiniz','hos-geldiniz','Blogumuzun ilk yazısı yayında.',
   '<p>Blog sayfamızı açıyoruz. Burada haberlerimiz, rehberlerimiz ve içerik üretimimizi sizinle paylaşacağız.</p><p>Yönetim panelindeki <strong>Blog</strong> bölümünden bu yazıyı düzenleyebilirsiniz.</p>',
   1, NOW())
ON DUPLICATE KEY UPDATE title = VALUES(title);

SET FOREIGN_KEY_CHECKS = 1;

-- =====================================================================
-- BİTTİ. Yönetici hesabı için /install.php çalıştırın (yoksa admin@example.com / admin123).
-- =====================================================================
