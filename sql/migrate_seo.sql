CREATE TABLE IF NOT EXISTS seo_settings (
  id INT AUTO_INCREMENT PRIMARY KEY,
  page_slug VARCHAR(80) NOT NULL UNIQUE,
  page_label VARCHAR(120) DEFAULT NULL,
  meta_title VARCHAR(220) DEFAULT NULL,
  meta_description VARCHAR(500) DEFAULT NULL,
  meta_keywords VARCHAR(500) DEFAULT NULL,
  og_image VARCHAR(500) DEFAULT NULL,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO seo_settings (page_slug, page_label, meta_title, meta_description, meta_keywords) VALUES
  ('home',     'Anasayfa',     NULL, 'Yurt içi premium e-ticaret platformu — özenle seçilmiş ürünler ve hızlı teslimat.', 'premium, e-ticaret, yurt içi'),
  ('products', 'Ürünler',      'Tüm Ürünler', 'Tüm ürünlerimizi keşfedin.', 'ürünler, koleksiyon'),
  ('product',  'Ürün Detay',   NULL, NULL, NULL),
  ('blog',     'Blog',         'Blog', 'Haberler, rehberler ve içeriklerimiz.', 'blog, haberler, rehber'),
  ('post',     'Blog Yazısı',  NULL, NULL, NULL),
  ('about',    'Hakkımızda',   'Hakkımızda', 'Hikayemiz ve değerlerimiz.', 'hakkımızda, kurumsal'),
  ('contact',  'İletişim',     'İletişim', 'Bizimle iletişime geçin.', 'iletişim, telefon, adres'),
  ('cart',     'Sepet',        'Sepetim', NULL, NULL),
  ('checkout', 'Ödeme',        'Ödeme', NULL, NULL),
  ('login',    'Giriş Yap',    'Giriş Yap', NULL, NULL),
  ('register', 'Üye Ol',       'Üye Ol', NULL, NULL),
  ('account',  'Hesabım',      'Hesabım', NULL, NULL),
  ('favorites','Favoriler',    'Favorilerim', NULL, NULL)
ON DUPLICATE KEY UPDATE page_label=VALUES(page_label);
