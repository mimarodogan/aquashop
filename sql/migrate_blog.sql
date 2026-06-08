-- Blog modülü tabloları
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
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_post_cat FOREIGN KEY (category_id) REFERENCES blog_categories(id) ON DELETE SET NULL,
  CONSTRAINT fk_post_aut FOREIGN KEY (author_id)   REFERENCES users(id)            ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO blog_categories (name,slug) VALUES
  ('Haberler','haberler'),
  ('Rehberler','rehberler'),
  ('Tarif','tarif'),
  ('Kurumsal','kurumsal')
ON DUPLICATE KEY UPDATE name=VALUES(name);

INSERT INTO blog_posts (category_id,title,slug,excerpt,content,is_published,published_at) VALUES
  (1,'Hoş Geldiniz','hos-geldiniz','Blogumuzun ilk yazısı yayında.',
   '<p>Blog sayfamızı açıyoruz. Burada haberlerimiz, rehberlerimiz ve içerik üretimimizi sizinle paylaşacağız.</p><p>Yönetim panelindeki <strong>Blog</strong> bölümünden bu yazıyı düzenleyebilirsiniz.</p>',
   1, NOW())
ON DUPLICATE KEY UPDATE title=VALUES(title);
