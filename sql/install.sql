-- ═══════════════════════════════════════════════════════════════════
-- AquaShop — TEK DOSYALIK KURULUM  (otomatik üretildi: build-install.sh)
--
-- Boş bir veritabanına tek seferde çalıştırın (phpMyAdmin > SQL sekmesi).
-- İdempotent: tekrar çalıştırmak güvenlidir (mevcut tablo/kolonları atlar).
-- Üretim: 2026-06-08 22:21
-- ═══════════════════════════════════════════════════════════════════

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ═══════════════════════════════════════════════════════════════════
-- BÖLÜM 1 — TABLOLAR (CREATE TABLE IF NOT EXISTS)
-- ═══════════════════════════════════════════════════════════════════

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

CREATE TABLE IF NOT EXISTS user_saved_items (
    user_id    INT UNSIGNED NOT NULL,
    product_id INT UNSIGNED NOT NULL,
    variant_id INT UNSIGNED NOT NULL DEFAULT 0, -- PK parçası: NULL olamaz; varyasyonsuz ürün = 0
    qty        INT NOT NULL DEFAULT 1,
    note       VARCHAR(255) NULL,
    saved_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (user_id, product_id, variant_id),
    KEY idx_product (product_id),
    KEY idx_saved   (saved_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS cart_reservations (
    id           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    session_id   VARCHAR(64) NOT NULL,
    user_id      INT UNSIGNED NULL,
    product_id   INT UNSIGNED NOT NULL,
    variant_id   INT UNSIGNED NULL,
    qty          INT NOT NULL DEFAULT 1,
    reserved_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    expires_at   DATETIME NOT NULL,
    PRIMARY KEY (id),
    KEY idx_session    (session_id, expires_at),
    KEY idx_product    (product_id, expires_at),
    KEY idx_expires    (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS product_questions (
    id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
    product_id    INT UNSIGNED NOT NULL,
    user_id       INT UNSIGNED NULL,
    asker_name    VARCHAR(120) NOT NULL,
    asker_email   VARCHAR(190) NULL,
    question      TEXT NOT NULL,
    answer        TEXT NULL,
    answered_by   INT UNSIGNED NULL,
    answered_at   DATETIME NULL,
    is_approved   TINYINT(1) NOT NULL DEFAULT 0,
    upvotes       INT UNSIGNED NOT NULL DEFAULT 0,
    created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_product_approved (product_id, is_approved, created_at),
    KEY idx_unanswered (is_approved, answered_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS payments (
  id INT AUTO_INCREMENT PRIMARY KEY,
  order_id INT NOT NULL,
  provider VARCHAR(40) NOT NULL DEFAULT 'iyzico',
  conversation_id VARCHAR(64) DEFAULT NULL,
  token VARCHAR(255) DEFAULT NULL,
  iyzico_payment_id VARCHAR(64) DEFAULT NULL,
  amount DECIMAL(10,2) NOT NULL DEFAULT 0,
  installment TINYINT NOT NULL DEFAULT 1,
  status ENUM('initialized','success','failure','refunded','partial_refund') NOT NULL DEFAULT 'initialized',
  card_family VARCHAR(40) DEFAULT NULL,
  card_assoc VARCHAR(40) DEFAULT NULL,
  card_last4 CHAR(4) DEFAULT NULL,
  error_code VARCHAR(40) DEFAULT NULL,
  error_message VARCHAR(255) DEFAULT NULL,
  raw_response MEDIUMTEXT DEFAULT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_payments_order (order_id),
  INDEX idx_payments_token (token),
  CONSTRAINT fk_payments_order FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS refunds (
  id INT AUTO_INCREMENT PRIMARY KEY,
  order_id INT NOT NULL,
  payment_id INT DEFAULT NULL,
  iyzico_payment_transaction_id VARCHAR(64) DEFAULT NULL,
  amount DECIMAL(10,2) NOT NULL,
  reason VARCHAR(255) DEFAULT NULL,
  status ENUM('success','failure') NOT NULL DEFAULT 'success',
  raw_response MEDIUMTEXT DEFAULT NULL,
  created_by INT DEFAULT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_refunds_order (order_id),
  CONSTRAINT fk_refunds_order FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS abandoned_carts (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id     INT UNSIGNED NULL,
  email       VARCHAR(190) NOT NULL,
  cart_json   TEXT NOT NULL,
  updated_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  notified_at DATETIME NULL,
  INDEX idx_email (email),
  INDEX idx_notified (notified_at, updated_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS ai_chat_log (
  id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  session_id  VARCHAR(64)  NOT NULL DEFAULT '',
  user_id     INT UNSIGNED NULL,
  role        ENUM('user','assistant') NOT NULL,
  message     TEXT NOT NULL,
  model       VARCHAR(48) NULL,
  ip_hash     CHAR(64) NOT NULL DEFAULT '',
  created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_session_created (session_id, created_at),
  KEY idx_iphash_created  (ip_hash, created_at),
  KEY idx_role_created    (role, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS banners (
  id INT AUTO_INCREMENT PRIMARY KEY,
  image VARCHAR(255) NOT NULL,
  link VARCHAR(500) DEFAULT NULL,
  title VARCHAR(200) DEFAULT NULL,
  alt VARCHAR(200) DEFAULT NULL,
  sort_order INT NOT NULL DEFAULT 0,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS blog_post_faqs (
    id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    post_id    INT UNSIGNED NOT NULL,
    question   VARCHAR(500) NOT NULL,
    answer     TEXT         NOT NULL,
    sort_order SMALLINT     NOT NULL DEFAULT 0,
    INDEX idx_post (post_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS wp_blog_import_queue (
  id               INT AUTO_INCREMENT PRIMARY KEY,
  blog_post_id     INT          NOT NULL,
  wp_attachment_id INT          NOT NULL,
  url              VARCHAR(500) NOT NULL,
  status           ENUM('pending','done','failed') NOT NULL DEFAULT 'pending',
  local_path       VARCHAR(500) DEFAULT NULL,
  attempts         TINYINT      NOT NULL DEFAULT 0,
  error            VARCHAR(255) DEFAULT NULL,
  created_at       DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_status (status),
  INDEX idx_post   (blog_post_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS comments (
  id INT AUTO_INCREMENT PRIMARY KEY,
  target_type ENUM('product','blog') NOT NULL,
  target_id INT NOT NULL,
  user_id INT NOT NULL,
  body TEXT NOT NULL,
  rating TINYINT DEFAULT NULL,
  is_approved TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_target (target_type, target_id),
  KEY idx_user (user_id),
  KEY idx_approved (is_approved),
  CONSTRAINT fk_cmt_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS favorites (
  user_id INT NOT NULL,
  product_id INT NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (user_id, product_id),
  KEY idx_product (product_id),
  CONSTRAINT fk_fav_user FOREIGN KEY (user_id)    REFERENCES users(id)    ON DELETE CASCADE,
  CONSTRAINT fk_fav_prod FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS login_attempts (
  id INT AUTO_INCREMENT PRIMARY KEY,
  ip VARCHAR(45) NOT NULL,
  email VARCHAR(190) DEFAULT NULL,
  success TINYINT(1) NOT NULL DEFAULT 0,
  attempted_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_ip_time (ip, attempted_at),
  INDEX idx_email_time (email, attempted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS loyalty_points (
    user_id      INT UNSIGNED NOT NULL PRIMARY KEY,
    points       INT NOT NULL DEFAULT 0,         -- mevcut kullanılabilir bakiye
    points_lifetime INT NOT NULL DEFAULT 0,      -- şu ana dek toplam kazanılan
    updated_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_updated (updated_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS loyalty_transactions (
    id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id     INT UNSIGNED NOT NULL,
    type        ENUM('earn','redeem','expire','adjust','refund') NOT NULL,
    points      INT NOT NULL,                    -- + earn / refund, − redeem / expire
    order_id    INT UNSIGNED NULL,               -- earn/redeem ilişkili sipariş
    note        VARCHAR(255) NULL,
    expires_at  DATETIME NULL,                   -- bu earn ne zaman expire olacak
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_user_time  (user_id, created_at),
    KEY idx_expires    (expires_at),
    KEY idx_order      (order_id),
    KEY idx_type       (type, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS mail_templates (
  `key`       VARCHAR(60)  NOT NULL PRIMARY KEY,
  subject     VARCHAR(255) NOT NULL,
  body_html   TEXT         NOT NULL,
  updated_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS media (
  id INT AUTO_INCREMENT PRIMARY KEY,
  filename VARCHAR(255) NOT NULL UNIQUE,
  original_name VARCHAR(255) DEFAULT NULL,
  path VARCHAR(500) NOT NULL,
  size INT NOT NULL DEFAULT 0,
  width INT DEFAULT NULL,
  height INT DEFAULT NULL,
  mime VARCHAR(60) DEFAULT 'image/webp',
  uploaded_by INT DEFAULT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  deleted_at DATETIME DEFAULT NULL,
  INDEX idx_deleted (deleted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS newsletter_subscribers (
  id INT AUTO_INCREMENT PRIMARY KEY,
  email VARCHAR(190) NOT NULL UNIQUE,
  name VARCHAR(120) DEFAULT NULL,
  token VARCHAR(64) NOT NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  subscribed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  unsubscribed_at DATETIME DEFAULT NULL,
  source VARCHAR(40) DEFAULT NULL,
  KEY idx_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS newsletter_campaigns (
  id INT AUTO_INCREMENT PRIMARY KEY,
  subject VARCHAR(220) NOT NULL,
  body MEDIUMTEXT NOT NULL,
  status ENUM('draft','sending','sent','failed') NOT NULL DEFAULT 'draft',
  total_recipients INT NOT NULL DEFAULT 0,
  sent_count INT NOT NULL DEFAULT 0,
  failed_count INT NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  sent_at DATETIME DEFAULT NULL,
  created_by INT DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS page_views (
    id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    url         VARCHAR(500)    NOT NULL,
    page_type   VARCHAR(32)     NULL,         -- 'home','product','cart','checkout','category','blog',...
    session_id  VARCHAR(64)     NULL,
    user_id     INT UNSIGNED    NULL,
    referrer    VARCHAR(500)    NULL,
    viewed_at   DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_viewed_at (viewed_at),
    KEY idx_page_type (page_type, viewed_at),
    KEY idx_session   (session_id, viewed_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS product_views (
    id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    product_id  INT UNSIGNED    NOT NULL,
    session_id  VARCHAR(64)     NULL,
    user_id     INT UNSIGNED    NULL,
    viewed_at   DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_product_time (product_id, viewed_at),
    KEY idx_session_product (session_id, product_id, viewed_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS product_categories (
  product_id  INT NOT NULL,
  category_id INT NOT NULL,
  PRIMARY KEY (product_id, category_id),
  INDEX idx_cat (category_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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

CREATE TABLE IF NOT EXISTS site_faqs (
    id          INT UNSIGNED     NOT NULL AUTO_INCREMENT PRIMARY KEY,
    question    VARCHAR(500)     NOT NULL,
    answer      TEXT             NOT NULL,
    sort_order  SMALLINT         NOT NULL DEFAULT 0,
    is_active   TINYINT(1)       NOT NULL DEFAULT 1,
    created_at  TIMESTAMP        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at  TIMESTAMP        NULL     ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_active_sort (is_active, sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS sms_log (
    id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    recipient     VARCHAR(20)     NOT NULL,
    message       VARCHAR(500)    NOT NULL,
    provider      VARCHAR(32)     NOT NULL,
    template      VARCHAR(64)     NULL,
    status        ENUM('success','failure') NOT NULL DEFAULT 'failure',
    error_message VARCHAR(255)    NULL,
    sent_at       DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_sent  (sent_at),
    KEY idx_recip (recipient, sent_at),
    KEY idx_status(status, sent_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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

CREATE TABLE IF NOT EXISTS restock_notifications (
  id INT AUTO_INCREMENT PRIMARY KEY,
  product_id INT NOT NULL,
  email VARCHAR(190) NOT NULL,
  notified_at DATETIME DEFAULT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uniq_pending (product_id, email, notified_at),
  INDEX idx_product (product_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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

CREATE TABLE IF NOT EXISTS redirects (
  id INT AUTO_INCREMENT PRIMARY KEY,
  match_type ENUM('exact','prefix','regex') NOT NULL DEFAULT 'exact',
  source VARCHAR(500) NOT NULL,
  target VARCHAR(500) NOT NULL,
  status_code SMALLINT NOT NULL DEFAULT 301,
  enabled TINYINT(1) NOT NULL DEFAULT 1,
  hit_count INT NOT NULL DEFAULT 0,
  last_hit_at DATETIME DEFAULT NULL,
  notes VARCHAR(255) DEFAULT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_source (source(191)),
  INDEX idx_enabled (enabled)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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


-- ═══════════════════════════════════════════════════════════════════
-- BÖLÜM 2 — KOLON EKLEMELERİ · İNDEXLER · VARSAYILAN VERİ
-- ═══════════════════════════════════════════════════════════════════

-- =====================================================================
-- TEK DOSYALIK KURULUM / ONARIM
-- Çalıştırma: phpMyAdmin > (config/db.php'deki DB_NAME) > SQL sekmesine yapıştır > Git
-- (İçe Aktar yerine SQL kutusuna YAPIŞTIRMAK karakter sorunlarını engeller.)
-- Tekrar tekrar güvenle çalıştırılabilir.
-- =====================================================================


-- ---------------------------------------------------------------------
-- 1) TABLOLAR (yoksa oluştur)
-- ---------------------------------------------------------------------











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


-- =====================================================================
-- BİTTİ. Yönetici hesabı için /install.php çalıştırın (yoksa admin@example.com / admin123).
-- =====================================================================
-- ─────────────────────────────────────────────────────────────────────
-- 102 — Faz 6 & 7 özellikleri için DB değişiklikleri
--
-- Bu migration şu özellikler için gerekli tablo/kolonları ekler:
--   • Çoklu sepet ("Sonra Al")          → user_saved_items
--   • Stok rezervasyonu (15dk)          → cart_reservations
--   • Ürün Q&A (soru-cevap)              → product_questions
--   • Hediye paketi                     → orders.gift_wrap_*
--   • Foto/video yorumlar               → product_reviews.media
--
-- Tüm değişiklikler idempotent (IF NOT EXISTS guard'lı).
-- ─────────────────────────────────────────────────────────────────────

-- 1) "Sonra Al" listesi

-- 2) Stok rezervasyonu (sepete eklenince 15dk tutma)

-- 3) Ürün Q&A

-- 4) orders → hediye paketi alanları (idempotent ALTER)
SET @col_gw1 := (SELECT COUNT(*) FROM information_schema.columns
                 WHERE table_schema = DATABASE() AND table_name = 'orders'
                   AND column_name = 'gift_wrap_price');
SET @sql_gw1 := IF(@col_gw1 = 0,
    'ALTER TABLE orders ADD COLUMN IF NOT EXISTS gift_wrap_price DECIMAL(10,2) NOT NULL DEFAULT 0, ADD COLUMN IF NOT EXISTS gift_wrap_note VARCHAR(255) NULL',
    'SELECT "orders.gift_wrap_* already exists"');
PREPARE st FROM @sql_gw1; EXECUTE st; DEALLOCATE PREPARE st;

-- 5) product_reviews → media JSON alanı (foto/video yorum)
SET @col_med := (SELECT COUNT(*) FROM information_schema.columns
                 WHERE table_schema = DATABASE() AND table_name = 'product_reviews'
                   AND column_name = 'media');
SET @sql_med := IF(@col_med = 0,
    "ALTER TABLE product_reviews ADD COLUMN IF NOT EXISTS media JSON NULL AFTER body",
    'SELECT "product_reviews.media already exists"');
PREPARE st FROM @sql_med; EXECUTE st; DEALLOCATE PREPARE st;
-- ─────────────────────────────────────────────────────────────────────
-- 103 — Çalışma anında (kod içinde) eklenen kolonları şemaya taşı
--
-- Bu kolonlar normalde uygulama kodu tarafından ilk ihtiyaç anında
-- "ALTER TABLE ... ADD COLUMN IF NOT EXISTS IF NOT EXISTS" ile ekleniyordu. Tam ve
-- kendine yeten bir kurulum için burada da tanımlanırlar (idempotent).
-- ─────────────────────────────────────────────────────────────────────

-- Stok rezervasyonu/düşümü: order_items satırına stok ne zaman uygulandı
ALTER TABLE order_items ADD COLUMN IF NOT EXISTS stock_applied_at DATETIME DEFAULT NULL;

-- Blog yazısı → yazar ilişkisi
ALTER TABLE blog_posts ADD COLUMN IF NOT EXISTS blog_author_id INT NULL DEFAULT NULL;
-- iyzico ödeme entegrasyonu için şema güncellemeleri
-- phpMyAdmin → SQL sekmesinde tek seferde çalıştırılır.

-- 1) orders tablosuna ödeme kolonları
ALTER TABLE orders
  ADD COLUMN IF NOT EXISTS payment_status ENUM('pending','paid','failed','refunded','partial_refund') NOT NULL DEFAULT 'pending',
  ADD COLUMN IF NOT EXISTS paid_at DATETIME NULL DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS iyzico_token VARCHAR(255) DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS iyzico_payment_id VARCHAR(64) DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS iyzico_conversation_id VARCHAR(64) DEFAULT NULL,
  ADD INDEX IF NOT EXISTS idx_orders_token (iyzico_token),
  ADD INDEX IF NOT EXISTS idx_orders_payment_id (iyzico_payment_id);

-- 2) Ödeme işlem kaydı (her tetikleme + callback ham yanıtı)

-- 3) İade kayıtları (kısmi/tam iade için ayrı satır)
-- Terk edilmiş sepet takip tablosu
-- ─────────────────────────────────────────────────────────────────────
-- abandoned_carts: multi-step hatırlatma desteği
--
-- reminder_step:
--   0 = hiç hatırlatma gönderilmedi (yeni terk)
--   1 = 1. hatırlatma (24 saat sonra, sade)
--   2 = 2. hatırlatma (72 saat sonra, indirim kuponu ile)
--   3 = 3. (son) hatırlatma (7 gün sonra, son şans + sosyal kanıt)
--
-- coupon_code: 2. adımda otomatik üretilen kupon kodu (varsa)
-- last_reminder_at: en son hangi zamanda hatırlatıldı (idempotent dedup)
-- ─────────────────────────────────────────────────────────────────────

ALTER TABLE abandoned_carts
  ADD COLUMN IF NOT EXISTS reminder_step    TINYINT UNSIGNED NOT NULL DEFAULT 0,
  ADD COLUMN IF NOT EXISTS last_reminder_at DATETIME NULL,
  ADD COLUMN IF NOT EXISTS coupon_code      VARCHAR(64) NULL,
  ADD INDEX idx_step_update (reminder_step, updated_at);

-- Mevcut kayıtlardan notified_at dolu olanları step=1 olarak işaretle (geriye uyum)
UPDATE abandoned_carts
SET reminder_step = 1, last_reminder_at = notified_at
WHERE notified_at IS NOT NULL AND reminder_step = 0;
-- ───────────────────────────────────────────────────────────────────────────
-- AI Danışman — sohbet logu (KVKK uyumlu)
--
-- Amaç:
--   1) Analiz: "Müşteriler ne soruyor / hangi ürünü arayıp bulamıyor" içgörüsü
--   2) Hız sınırı (rate limit): kısa sürede aşırı istek atan oturum/IP'yi engelle
--
-- KVKK notu: Ham IP SAKLANMAZ. Yalnızca geri döndürülemez sha256 ip_hash tutulur
-- (pseudonimizasyon). Mesaj içeriği saklanır ama kişisel veri girilmesi beklenmez.
-- ───────────────────────────────────────────────────────────────────────────

-- Blog modülü tabloları


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
-- Blog yazıları için SSS tablosu
-- Blog yazısı görsel indirme kuyruğu (wp_blog_import_queue)
-- wp_import_queue'nun blog eşdeğeri; kapak görseli indirir, blog_posts.cover_image günceller.
-- Marka alanı
ALTER TABLE products ADD COLUMN IF NOT EXISTS brand VARCHAR(120) DEFAULT NULL;
CREATE INDEX idx_products_brand ON products(brand);

-- Topbar mesajı (varsa güncelle, yoksa ekle)
INSERT INTO settings (setting_key, setting_value) VALUES
  ('topbar_message','Yurt Geneli Ücretsiz Kargo · Güvenli Ödeme · 14 Gün İade'),
  ('topbar_enabled','1')
ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value);

-- Eğer yoksa product_images tablosu (galeri için) — schema'da zaten var, idempotent
ALTER TABLE categories
  ADD COLUMN IF NOT EXISTS image VARCHAR(255) DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS sort_order INT NOT NULL DEFAULT 0;
-- Kategoriler tablosuna SEO + içerik alanları ekle
-- Çalıştırma: phpMyAdmin veya MySQL terminali üzerinden bir kez çalıştır.

ALTER TABLE categories
  ADD COLUMN IF NOT EXISTS sort_order INT NOT NULL DEFAULT 0,
  ADD COLUMN IF NOT EXISTS image VARCHAR(255) DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS description TEXT DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS meta_title VARCHAR(255) DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS meta_description TEXT DEFAULT NULL;
-- Favorilere fiyat takibi kolonu ekle
ALTER TABLE favorites
  ADD COLUMN IF NOT EXISTS price_at_fav DECIMAL(10,2) DEFAULT NULL;
-- =====================================================================
-- Login Throttle — Persistent Brute-Force Koruması (K-5)
-- IP ve e-posta başına ayrı sayaç; 15 dk içinde 5 başarısız deneme limiti.
-- phpMyAdmin → DB seç → SQL → bu dosyayı yapıştır → Git
-- =====================================================================

-- ─────────────────────────────────────────────────────────────────────
-- Sadakat Sistemi — Puanlar, İşlemler, Seviyeler, Doğum Günü
-- ─────────────────────────────────────────────────────────────────────

-- 1) Müşteri puan bakiyesi (özet/aggregate)

-- 2) İşlem günlüğü (denetim + expire takibi)

-- 3) Users tablosuna ek alanlar (idempotent)
-- NOT: birthday yerine mevcut users.birth_date kullanılır (zaten migrate_users_v2.sql ile eklenmiş).
-- Bu migration sadece eksik olan loyalty_tier ve birthday_coupon_year alanlarını ekler.
SET @col_tier := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'users' AND column_name = 'loyalty_tier');
SET @sql_tier := IF(@col_tier = 0, "ALTER TABLE users ADD COLUMN IF NOT EXISTS loyalty_tier ENUM('new','loyal','vip') NOT NULL DEFAULT 'new', ADD INDEX idx_tier (loyalty_tier)", 'SELECT "users.loyalty_tier already exists"');
PREPARE st FROM @sql_tier; EXECUTE st; DEALLOCATE PREPARE st;

SET @col_bday_sent := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'users' AND column_name = 'birthday_coupon_year');
SET @sql_bsent := IF(@col_bday_sent = 0, 'ALTER TABLE users ADD COLUMN IF NOT EXISTS birthday_coupon_year SMALLINT UNSIGNED NULL', 'SELECT "users.birthday_coupon_year already exists"');
PREPARE st FROM @sql_bsent; EXECUTE st; DEALLOCATE PREPARE st;

-- 4) Orders tablosuna puan alanları (idempotent)
SET @col_pe := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'orders' AND column_name = 'loyalty_points_earned');
SET @sql_pe := IF(@col_pe = 0, 'ALTER TABLE orders ADD COLUMN IF NOT EXISTS loyalty_points_earned INT NOT NULL DEFAULT 0, ADD COLUMN IF NOT EXISTS loyalty_points_used INT NOT NULL DEFAULT 0, ADD COLUMN IF NOT EXISTS loyalty_points_value DECIMAL(10,2) NOT NULL DEFAULT 0', 'SELECT "orders.loyalty_points_* already exists"');
PREPARE st FROM @sql_pe; EXECUTE st; DEALLOCATE PREPARE st;
-- Mail şablonları tablosu

-- Varsayılan şablonları ekle (INSERT IGNORE = zaten varsa dokunma)
INSERT IGNORE INTO mail_templates (`key`, subject, body_html) VALUES
('welcome',
 'Hoş Geldiniz — {{site_adi}}',
 '<p>Merhaba <strong>{{isim}}</strong>,</p>
<p>{{site_adi}} ailesine hoş geldiniz! Hesabınız başarıyla oluşturuldu.</p>
<p>Binlerce ürün arasından kolayca alışveriş yapabilir, siparişlerinizi takip edebilirsiniz.</p>'),

('price_alert',
 'Favorinizdeki ürün indirimde! — {{urun_adi}}',
 '<p>Merhaba <strong>{{isim}}</strong>,</p>
<p>Favorilerinize eklediğiniz <strong>{{urun_adi}}</strong> ürününün fiyatı düştü!</p>
<p>Eski fiyat: <del>{{eski_fiyat}}</del><br>Yeni fiyat: <strong>{{yeni_fiyat}}</strong></p>'),

('abandoned_cart',
 'Sepetinizde ürünler bekliyor — {{site_adi}}',
 '<p>Merhaba <strong>{{isim}}</strong>,</p>
<p>Sepetinizde unutulan ürünler var! Alışverişinizi tamamlamak için hâlâ zaman var.</p>
<p>{{sepet_ozeti}}</p>'),

('restock_notify',
 'Ürün tekrar stokta! — {{urun_adi}}',
 '<p>Merhaba,</p>
<p>Stok bildirimi istediğiniz <strong>{{urun_adi}}</strong> tekrar stoğa girdi!</p>
<p>Stoğun hızlı tükenebileceğini unutmayın.</p>'),

('restock_confirm',
 'Stok bildirimi kaydınız alındı — {{urun_adi}}',
 '<p>Merhaba,</p>
<p><strong>{{urun_adi}}</strong> için stok bildirimi talebiniz alındı.</p>
<p>Ürün stoğa girdiğinde sizi e-posta ile haberdar edeceğiz.</p>'),

('order_confirm',
 'Siparişiniz Alındı — #{{siparis_no}}',
 '<p>Merhaba <strong>{{isim}}</strong>,</p>
<p><strong>#{{siparis_no}}</strong> numaralı siparişiniz başarıyla alındı.</p>
<p>{{siparis_ozeti}}</p>
<p>Siparişinizi hesabınızdan takip edebilirsiniz.</p>');

ALTER TABLE orders
  ADD COLUMN IF NOT EXISTS cancellation_reason TEXT DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS cancelled_at DATETIME DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS cancelled_by INT DEFAULT NULL;
-- ─────────────────────────────────────────────────────────────────────
-- Sayfa görüntülenmeleri (server-side basit sayım)
-- GA4'e ek/yedek olarak — admin dashboard'da dönüşüm oranı hesaplamak için.
--
-- Saklama: 90 gün (eski kayıtlar cron ile temizlenebilir).
-- Bot trafiği include edilmez (UA filtresi tracking tarafında uygulanır).
-- ─────────────────────────────────────────────────────────────────────

-- Ürün-spesifik görüntüleme (sosyal kanıt "şu an X kişi inceliyor" için)
-- Sayfalar (CMS) tablosu + varsayılan iki sayfa

INSERT INTO pages (slug,title,content) VALUES
  ('uyelik-kosullari','Üyelik Koşulları',
   '<h2>Üyelik Koşulları</h2><p>Bu metin, sitemize üye olan kullanıcıların kabul ettiği koşulları içerir. Yönetim panelinden düzenleyebilirsiniz.</p><h3>1. Genel</h3><p>Üyelik formunu doldurarak işbu koşulları kabul etmiş olursunuz.</p><h3>2. Hak ve Yükümlülükler</h3><p>Hesap bilgilerinizin gizliliğinden siz sorumlusunuz.</p><h3>3. İletişim</h3><p>Sorularınız için iletişim sayfamızı kullanabilirsiniz.</p>'),
  ('kvkk','Kişisel Verilerin Korunması',
   '<h2>Kişisel Verilerin Korunması Hakkında Aydınlatma Metni</h2><p>6698 sayılı Kişisel Verilerin Korunması Kanunu (KVKK) kapsamında veri sorumlusu sıfatıyla işlediğimiz kişisel verilerinize ilişkin bilgilendirmedir. Yönetim panelinden düzenleyebilirsiniz.</p><h3>1. Toplanan Veriler</h3><p>Ad-soyad, e-posta, telefon, adres, doğum tarihi gibi bilgiler.</p><h3>2. İşleme Amaçları</h3><p>Sipariş yönetimi, müşteri ilişkileri, pazarlama (onay verdiyseniz).</p><h3>3. Haklarınız</h3><p>KVKK md.11 kapsamındaki haklarınızı kullanmak için bize başvurabilirsiniz.</p>')
ON DUPLICATE KEY UPDATE title=VALUES(title);
ALTER TABLE pages ADD COLUMN IF NOT EXISTS cover_image VARCHAR(255) DEFAULT NULL;

INSERT INTO pages (slug,title,content) VALUES
('hakkimizda','Hakkımızda',
 '<h2>Hikayemiz</h2><p>Yıllar içinde damıttığımız ustalık ve özenle, yurt içinin dört bir yanına ulaşan ürünlerimiz; sade ama derin bir estetik anlayışın somut ifadeleridir.</p><p>Her detayda zanaatkar dokunuşu, her seçimde özen. Müşterilerimize yalnızca bir ürün değil, zamansız bir deneyim sunmayı hedefliyoruz.</p><p>Sürdürülebilir üretim, adil ticaret ve müşteri memnuniyeti ilkelerimizin temelini oluşturur.</p>')
ON DUPLICATE KEY UPDATE title=VALUES(title);
-- POS (Mağaza Satışı) migrasyonu
-- Siparişlere kaynak (web/pos) ve kasiyer bilgisi ekler

ALTER TABLE orders
  ADD COLUMN IF NOT EXISTS source ENUM('web','pos') NOT NULL DEFAULT 'web',
  ADD COLUMN IF NOT EXISTS pos_cashier_id INT NULL;

-- POS satışlarını hızlı sorgulamak için index
ALTER TABLE orders ADD INDEX IF NOT EXISTS idx_source (source);
-- Fiyatsız ürün desteği — "İletişime Geçin" modu
ALTER TABLE products
  ADD COLUMN IF NOT EXISTS price_on_request TINYINT(1) NOT NULL DEFAULT 0
;
-- Çoka-çok ürün-kategori ilişki tablosu

-- Mevcut products.category_id verilerini yeni tabloya kopyala
INSERT IGNORE INTO product_categories (product_id, category_id)
SELECT id, category_id FROM products WHERE category_id IS NOT NULL;
ALTER TABLE products ADD COLUMN IF NOT EXISTS sku VARCHAR(60) DEFAULT NULL;
CREATE INDEX idx_products_sku ON products(sku);

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
-- SEO sayfa-bazlı robots etiketi desteği
ALTER TABLE seo_settings
  ADD COLUMN IF NOT EXISTS meta_robots VARCHAR(120) DEFAULT NULL;

-- settings tablosuna varsayılan SEO anahtarları (yoksa ekler)
INSERT IGNORE INTO settings (setting_key, setting_value) VALUES
  ('seo_author',           ''),
  ('seo_publisher',        ''),
  ('seo_robots',           'index, follow'),
  ('seo_twitter_handle',   ''),
  ('seo_default_og_image', '');
-- SSS (Sıkça Sorulan Sorular) site geneli soru-cevap tablosu
-- Admin paneli → İçerik & SEO → SSS Yönetimi bölümünden yönetilir.
-- Ön yüzde accordion olarak görünür, FAQPage JSON-LD schema üretir.

-- ─────────────────────────────────────────────────────────────────────
-- SMS gönderim log tablosu
-- Tüm gönderimler (başarı + hata) buraya yazılır — denetim, faturalandırma, hata izleme.
-- ─────────────────────────────────────────────────────────────────────
ALTER TABLE orders
  ADD COLUMN IF NOT EXISTS tracking_carrier VARCHAR(40) DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS tracking_number  VARCHAR(80) DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS shipped_at DATETIME DEFAULT NULL;
-- Ürünler için yumuşak silme (soft delete) sütunu
-- Çöp kutusu sistemi: deleted_at doluysa çöpte, NULL ise aktif
ALTER TABLE products
  ADD COLUMN IF NOT EXISTS deleted_at DATETIME NULL DEFAULT NULL;

-- Hızlı filtre için index
ALTER TABLE products
  ADD INDEX IF NOT EXISTS idx_deleted_at (deleted_at);
-- users tablosuna ek alanlar (mevcut DB üzerinde tek seferlik çalıştırın)
ALTER TABLE users
  ADD COLUMN IF NOT EXISTS first_name VARCHAR(80) DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS last_name  VARCHAR(80) DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS birth_date DATE        DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS email_consent TINYINT(1) NOT NULL DEFAULT 0,
  ADD COLUMN IF NOT EXISTS sms_consent   TINYINT(1) NOT NULL DEFAULT 0;
-- Şifre sıfırlama için users tablosuna kolonlar
ALTER TABLE users
  ADD COLUMN IF NOT EXISTS password_reset_token VARCHAR(64) DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS password_reset_expires DATETIME DEFAULT NULL,
  ADD INDEX IF NOT EXISTS idx_users_reset (password_reset_token);
-- Phase 2: Şirket faturası, çoklu adres, restock notify, kupon, yorum, iade
-- Tek seferde phpMyAdmin SQL sekmesinde çalıştır.

-- 1) orders tablosuna fatura tipi ve şirket alanları
ALTER TABLE orders
  ADD COLUMN IF NOT EXISTS invoice_type ENUM('individual','company') NOT NULL DEFAULT 'individual',
  ADD COLUMN IF NOT EXISTS invoice_tax_no VARCHAR(20) DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS invoice_tax_office VARCHAR(120) DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS invoice_company VARCHAR(190) DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS invoice_eposta_zorunlu VARCHAR(190) DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS shipping_amount DECIMAL(10,2) DEFAULT 0,
  ADD COLUMN IF NOT EXISTS subtotal DECIMAL(10,2) DEFAULT 0,
  ADD COLUMN IF NOT EXISTS vat_amount DECIMAL(10,2) DEFAULT 0;

-- 2) Çoklu adres

-- 3) Stoka gelince haber ver

-- 4) Kupon kodları


-- 5) orders'a kupon kayıtları
ALTER TABLE orders
  ADD COLUMN IF NOT EXISTS coupon_code VARCHAR(40) DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS discount_amount DECIMAL(10,2) DEFAULT 0;

-- 6) Ürün yorumları + 5 yıldız

-- 7) İade talepleri (kullanıcı tarafında)

-- URL Yönlendirmeleri tablosu
-- Çerez Politikası sayfası — cookie banner'dan linklenen
INSERT INTO pages (slug, title, content, is_published) VALUES (
  'cerez-politikasi',
  'Çerez Politikası',
  '<h2>Çerez Politikası</h2>
<p>Bu Çerez Politikası, <strong>aquashop.com.tr</strong> web sitesinde kullanılan çerezler (cookies) hakkında sizi bilgilendirmek amacıyla hazırlanmıştır.</p>

<h3>Çerez Nedir?</h3>
<p>Çerezler, web siteleri tarafından tarayıcınıza yerleştirilen küçük metin dosyalarıdır. Sitemizi her ziyaret ettiğinizde web sunucusu tarafından tarayıcınıza gönderilir ve tarayıcınız tarafından saklanır. Sonraki ziyaretlerde bu çerezler sunucumuza geri gönderilerek kimliğinizin tanınmasına ve tercihlerinizin hatırlanmasına yardımcı olur.</p>

<h3>Kullandığımız Çerez Türleri</h3>

<h4>Zorunlu Çerezler</h4>
<p>Bu çerezler sitenin doğru şekilde çalışabilmesi için gereklidir. Oturum açma durumunuzun korunması, alışveriş sepetinizin hatırlanması ve güvenlik kontrolleri gibi temel işlevler bu çerezler aracılığıyla sağlanır. Tarayıcınızda bu çerezleri devre dışı bırakmanız durumunda sitenin bazı bölümleri düzgün çalışmayabilir.</p>

<h4>İşlevsel Çerezler</h4>
<p>Dil tercihiniz, konum bilginiz gibi tercihlerinizi hatırlamamıza olanak tanır. Bu çerezler olmadan tercihlerinizi her ziyarette yeniden belirlemeniz gerekebilir.</p>

<h4>Analitik Çerezler</h4>
<p>Sitemizin nasıl kullanıldığını anlamamıza yardımcı olur; hangi sayfaların en çok ziyaret edildiğini, kullanıcıların sitede nasıl gezindiğini analiz ederiz. Bu veriler anonim olarak toplanır ve hizmetlerimizi iyileştirmek amacıyla kullanılır.</p>

<h3>Çerezleri Nasıl Kontrol Edebilirsiniz?</h3>
<p>Tarayıcınızın ayarlarından çerezleri reddetme veya silme hakkınız bulunmaktadır. Ancak bu işlem sonucunda sitemizin bazı özelliklerinin tam olarak çalışmayabileceğini belirtmek isteriz.</p>
<p>Popüler tarayıcılarda çerez ayarları için:</p>
<ul>
  <li><strong>Google Chrome:</strong> Ayarlar → Gizlilik ve güvenlik → Çerezler ve diğer site verileri</li>
  <li><strong>Mozilla Firefox:</strong> Seçenekler → Gizlilik ve Güvenlik → Çerezler ve Site Verileri</li>
  <li><strong>Safari:</strong> Tercihler → Gizlilik → Çerezler ve web sitesi verileri</li>
  <li><strong>Microsoft Edge:</strong> Ayarlar → Çerezler ve site izinleri</li>
</ul>

<h3>Üçüncü Taraf Çerezleri</h3>
<p>Ödeme altyapısı, harita veya sosyal medya butonları gibi üçüncü taraf hizmetler kendi çerezlerini yerleştirebilir. Bu çerezler ilgili üçüncü tarafların gizlilik politikasına tabidir.</p>

<h3>Kişisel Verilerin Korunması</h3>
<p>Çerezler aracılığıyla elde edilen kişisel veriler KVKK kapsamında işlenmektedir. Ayrıntılı bilgi için <a href="sayfa/kvkk">KVKK Aydınlatma Metni</a> sayfamızı inceleyebilirsiniz.</p>

<h3>Değişiklikler</h3>
<p>Bu politika gerektiğinde güncellenebilir. Önemli değişiklikler söz konusu olduğunda sizi site üzerinden bilgilendireceğiz. Son güncelleme: Mayıs 2025.</p>

<h3>İletişim</h3>
<p>Çerez politikamıza ilişkin sorularınız için <a href="iletisim">iletişim sayfamızdan</a> bize ulaşabilirsiniz.</p>',
  1
) ON DUPLICATE KEY UPDATE title=VALUES(title), content=VALUES(content), is_published=1;
-- Ek varsayılan sayfalar (footer için)
INSERT INTO pages (slug,title,content) VALUES
  ('kargo-teslimat','Kargo & Teslimat',
   '<h2>Kargo & Teslimat</h2><p>Siparişleriniz onayın ardından <strong>1-3 iş günü</strong> içinde anlaşmalı kargo firmamızla gönderilir.</p><h3>Kargo Süreleri</h3><ul><li>Büyükşehirler: 1-2 iş günü</li><li>Diğer iller: 2-4 iş günü</li><li>Köy/uzak bölgeler: 3-5 iş günü</li></ul><h3>Kargo Ücreti</h3><p>Yurt içi tüm siparişlerde <strong>kargo ücretsizdir</strong>.</p><h3>Sipariş Takibi</h3><p>Siparişiniz kargoya verildiğinde e-posta ile takip numarası iletilir. Hesabım sayfasından da takibinizi yapabilirsiniz.</p>'),
  ('iade-degisim','İade & Değişim',
   '<h2>İade & Değişim Politikası</h2><p>Müşteri memnuniyeti önceliğimizdir. Ürünlerinizi <strong>14 gün</strong> içinde iade edebilir veya değiştirebilirsiniz.</p><h3>İade Koşulları</h3><ul><li>Ürün kullanılmamış ve orijinal ambalajında olmalı</li><li>Fatura ile birlikte gönderilmeli</li><li>Hijyenik özellik taşıyan ürünler iade edilemez</li></ul><h3>Nasıl İade Ederim?</h3><ol><li>İletişim sayfasından bize ulaşın</li><li>İade onayı sonrası anlaşmalı kargoyla ücretsiz gönderim</li><li>Ürün tarafımıza ulaştığında 7 iş günü içinde iadeniz işleme alınır</li></ol><h3>Para İadesi</h3><p>Ödemeyi yaptığınız yönteme göre 1-14 iş günü içinde iadeniz tamamlanır.</p>'),
  ('sss','Sıkça Sorulan Sorular',
   '<h2>Sıkça Sorulan Sorular</h2><h3>Siparişim ne zaman elime ulaşır?</h3><p>Onaylanan siparişler 1-3 iş günü içinde kargoya verilir; teslimat süresi bölgenize göre 1-5 iş günüdür.</p><h3>Kargo ücreti var mı?</h3><p>Yurt içi tüm siparişlerde kargo ücretsizdir.</p><h3>Hangi ödeme yöntemlerini kullanabilirim?</h3><p>Havale/EFT, kredi kartı ve kapıda ödeme seçeneklerimiz mevcuttur.</p><h3>Ürünü iade edebilir miyim?</h3><p>Evet, 14 gün içinde koşulsuz iade hakkınız vardır. Detaylar için <a href="page.php?slug=iade-degisim">İade &amp; Değişim</a> sayfasına bakabilirsiniz.</p><h3>Üye olmadan sipariş verebilir miyim?</h3><p>Evet, misafir olarak da sipariş verebilirsiniz; ancak üyelik daha hızlı ödeme ve sipariş takibi sağlar.</p><h3>Faturamı nasıl alırım?</h3><p>E-fatura/e-arşiv kayıtlı e-posta adresinize otomatik gönderilir.</p><h3>Sipariş iptali yapabilir miyim?</h3><p>Henüz kargoya verilmemiş siparişler için iptal mümkündür. İletişime geçmeniz yeterlidir.</p>')
ON DUPLICATE KEY UPDATE title=VALUES(title);
-- Ürün varyasyonları (1L / 5L gibi)
-- Tek seferlik phpMyAdmin SQL sekmesinde çalıştır.


-- order_items'a varyasyon referansı
ALTER TABLE order_items
  ADD COLUMN IF NOT EXISTS variation_id INT DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS variation_label VARCHAR(120) DEFAULT NULL,
  ADD INDEX IF NOT EXISTS idx_variation (variation_id);

-- products tablosuna varyasyonlu olduğunu işaretleyen flag
ALTER TABLE products
  ADD COLUMN IF NOT EXISTS has_variations TINYINT(1) NOT NULL DEFAULT 0;
-- ─────────────────────────────────────────────────────────────────────
-- 100 — Kritik Performans İndexleri
--
-- Mevcut şemada eksik olan ve admin sorgularını (dashboard, raporlar,
-- müşteri 360°, bulk actions, cron'lar) yavaşlatan index'leri ekler.
--
-- TÜM index'ler "IF NOT EXISTS" benzeri guarded ekleme ile idempotent.
-- Tekrar çalıştırılması güvenlidir.
-- ─────────────────────────────────────────────────────────────────────

-- Helper: bir index var mı kontrol et + yoksa ekle (idempotent pattern)
-- MariaDB 10.2+ / MySQL 5.7+ uyumlu

-- ── orders.user_id ────────────────────────────────────────────────
SET @ix := (SELECT COUNT(*) FROM information_schema.statistics
            WHERE table_schema = DATABASE() AND table_name = 'orders' AND index_name = 'idx_user');
SET @sql := IF(@ix = 0, 'ALTER TABLE orders ADD INDEX idx_user (user_id)', 'SELECT 1');
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;

-- ── orders.status ─────────────────────────────────────────────────
SET @ix := (SELECT COUNT(*) FROM information_schema.statistics
            WHERE table_schema = DATABASE() AND table_name = 'orders' AND index_name = 'idx_status');
SET @sql := IF(@ix = 0, 'ALTER TABLE orders ADD INDEX idx_status (status)', 'SELECT 1');
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;

-- ── orders.created_at (dashboard, raporlar) ──────────────────────
SET @ix := (SELECT COUNT(*) FROM information_schema.statistics
            WHERE table_schema = DATABASE() AND table_name = 'orders' AND index_name = 'idx_created');
SET @sql := IF(@ix = 0, 'ALTER TABLE orders ADD INDEX idx_created (created_at)', 'SELECT 1');
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;

-- ── orders.status + created_at compound (raporlar için ideal) ────
SET @ix := (SELECT COUNT(*) FROM information_schema.statistics
            WHERE table_schema = DATABASE() AND table_name = 'orders' AND index_name = 'idx_status_created');
SET @sql := IF(@ix = 0, 'ALTER TABLE orders ADD INDEX idx_status_created (status, created_at)', 'SELECT 1');
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;

-- ── orders.coupon_code (kupon performans raporu) ─────────────────
SET @ix := (SELECT COUNT(*) FROM information_schema.statistics
            WHERE table_schema = DATABASE() AND table_name = 'orders' AND index_name = 'idx_coupon');
SET @sql := IF(@ix = 0, 'ALTER TABLE orders ADD INDEX idx_coupon (coupon_code)', 'SELECT 1');
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;

-- ── orders.email (kullanıcı sipariş takibi) ───────────────────────
SET @ix := (SELECT COUNT(*) FROM information_schema.statistics
            WHERE table_schema = DATABASE() AND table_name = 'orders' AND index_name = 'idx_email');
SET @sql := IF(@ix = 0, 'ALTER TABLE orders ADD INDEX idx_email (email)', 'SELECT 1');
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;

-- ── order_items.order_id (sipariş detay açılması) ────────────────
SET @ix := (SELECT COUNT(*) FROM information_schema.statistics
            WHERE table_schema = DATABASE() AND table_name = 'order_items' AND index_name = 'idx_order');
SET @sql := IF(@ix = 0, 'ALTER TABLE order_items ADD INDEX idx_order (order_id)', 'SELECT 1');
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;

-- ── order_items.product_id (top satıcılar, cross-sell) ───────────
SET @ix := (SELECT COUNT(*) FROM information_schema.statistics
            WHERE table_schema = DATABASE() AND table_name = 'order_items' AND index_name = 'idx_product');
SET @sql := IF(@ix = 0, 'ALTER TABLE order_items ADD INDEX idx_product (product_id)', 'SELECT 1');
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;

-- ── coupon_redemptions.user_id (müşteri 360°) ────────────────────
SET @ix := (SELECT COUNT(*) FROM information_schema.statistics
            WHERE table_schema = DATABASE() AND table_name = 'coupon_redemptions' AND index_name = 'idx_user');
SET @sql := IF(@ix = 0, 'ALTER TABLE coupon_redemptions ADD INDEX idx_user (user_id)', 'SELECT 1');
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;

-- ── users.created_at (kayıt tarihi raporları) ────────────────────
SET @ix := (SELECT COUNT(*) FROM information_schema.statistics
            WHERE table_schema = DATABASE() AND table_name = 'users' AND index_name = 'idx_created');
SET @sql := IF(@ix = 0, 'ALTER TABLE users ADD INDEX idx_created (created_at)', 'SELECT 1');
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;

-- ── users.role + loyalty_tier compound (admin loyalty sayfası) ───
SET @ix := (SELECT COUNT(*) FROM information_schema.statistics
            WHERE table_schema = DATABASE() AND table_name = 'users' AND index_name = 'idx_role_tier');
SET @sql := IF(@ix = 0, 'ALTER TABLE users ADD INDEX idx_role_tier (role, loyalty_tier)', 'SELECT 1');
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;

-- ── newsletter_subscribers.subscribed_at (kampanya raporu) ───────
SET @ix := (SELECT COUNT(*) FROM information_schema.statistics
            WHERE table_schema = DATABASE() AND table_name = 'newsletter_subscribers' AND index_name = 'idx_subscribed');
SET @sql := IF(@ix = 0, 'ALTER TABLE newsletter_subscribers ADD INDEX idx_subscribed (subscribed_at)', 'SELECT 1');
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;

-- ── products.is_active + is_featured (anasayfa öne çıkan ürünler) ─
SET @ix := (SELECT COUNT(*) FROM information_schema.statistics
            WHERE table_schema = DATABASE() AND table_name = 'products' AND index_name = 'idx_active_featured');
SET @sql := IF(@ix = 0, 'ALTER TABLE products ADD INDEX idx_active_featured (is_active, is_featured)', 'SELECT 1');
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;

-- ── products.category_id (kategori listesi) ──────────────────────
SET @ix := (SELECT COUNT(*) FROM information_schema.statistics
            WHERE table_schema = DATABASE() AND table_name = 'products' AND index_name = 'idx_category');
SET @sql := IF(@ix = 0, 'ALTER TABLE products ADD INDEX idx_category (category_id, is_active)', 'SELECT 1');
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;
-- ─────────────────────────────────────────────────────────────────────
-- 101 — Silinmiş ama hala "aktif" olarak işaretli ürünleri pasif yap
--
-- Sorun: Eskiden admin'de "Sil" butonu sadece deleted_at = NOW() yapıyordu,
-- is_active'e dokunmuyordu. Storefront sorgularının çoğu sadece is_active=1
-- kontrolü yaptığı için soft-deleted ürünler hala sitede görünüyordu.
--
-- Çözüm:
--  1. Bu migration mevcut silinmiş ürünlerin is_active'ini 0'a çekiyor.
--  2. admin_panel/products/list.php artık "Sil" sırasında is_active=0 da yapar.
--
-- Idempotent: tekrar çalıştırılırsa 0 satır etkiler.
-- ─────────────────────────────────────────────────────────────────────

UPDATE products
SET is_active = 0
WHERE deleted_at IS NOT NULL
  AND is_active = 1;

SET FOREIGN_KEY_CHECKS = 1;
