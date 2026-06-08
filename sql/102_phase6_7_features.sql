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

-- 2) Stok rezervasyonu (sepete eklenince 15dk tutma)
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

-- 3) Ürün Q&A
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

-- 4) orders → hediye paketi alanları (idempotent ALTER)
SET @col_gw1 := (SELECT COUNT(*) FROM information_schema.columns
                 WHERE table_schema = DATABASE() AND table_name = 'orders'
                   AND column_name = 'gift_wrap_price');
SET @sql_gw1 := IF(@col_gw1 = 0,
    'ALTER TABLE orders ADD COLUMN gift_wrap_price DECIMAL(10,2) NOT NULL DEFAULT 0 AFTER discount_amount, ADD COLUMN gift_wrap_note VARCHAR(255) NULL AFTER gift_wrap_price',
    'SELECT "orders.gift_wrap_* already exists"');
PREPARE st FROM @sql_gw1; EXECUTE st; DEALLOCATE PREPARE st;

-- 5) product_reviews → media JSON alanı (foto/video yorum)
SET @col_med := (SELECT COUNT(*) FROM information_schema.columns
                 WHERE table_schema = DATABASE() AND table_name = 'product_reviews'
                   AND column_name = 'media');
SET @sql_med := IF(@col_med = 0,
    "ALTER TABLE product_reviews ADD COLUMN media JSON NULL AFTER body",
    'SELECT "product_reviews.media already exists"');
PREPARE st FROM @sql_med; EXECUTE st; DEALLOCATE PREPARE st;
