-- ─────────────────────────────────────────────────────────────────────
-- Sadakat Sistemi — Puanlar, İşlemler, Seviyeler, Doğum Günü
-- ─────────────────────────────────────────────────────────────────────

-- 1) Müşteri puan bakiyesi (özet/aggregate)
CREATE TABLE IF NOT EXISTS loyalty_points (
    user_id      INT UNSIGNED NOT NULL PRIMARY KEY,
    points       INT NOT NULL DEFAULT 0,         -- mevcut kullanılabilir bakiye
    points_lifetime INT NOT NULL DEFAULT 0,      -- şu ana dek toplam kazanılan
    updated_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_updated (updated_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2) İşlem günlüğü (denetim + expire takibi)
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

-- 3) Users tablosuna ek alanlar (idempotent)
-- NOT: birthday yerine mevcut users.birth_date kullanılır (zaten migrate_users_v2.sql ile eklenmiş).
-- Bu migration sadece eksik olan loyalty_tier ve birthday_coupon_year alanlarını ekler.
SET @col_tier := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'users' AND column_name = 'loyalty_tier');
SET @sql_tier := IF(@col_tier = 0, "ALTER TABLE users ADD COLUMN loyalty_tier ENUM('new','loyal','vip') NOT NULL DEFAULT 'new' AFTER role, ADD INDEX idx_tier (loyalty_tier)", 'SELECT "users.loyalty_tier already exists"');
PREPARE st FROM @sql_tier; EXECUTE st; DEALLOCATE PREPARE st;

SET @col_bday_sent := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'users' AND column_name = 'birthday_coupon_year');
SET @sql_bsent := IF(@col_bday_sent = 0, 'ALTER TABLE users ADD COLUMN birthday_coupon_year SMALLINT UNSIGNED NULL AFTER loyalty_tier', 'SELECT "users.birthday_coupon_year already exists"');
PREPARE st FROM @sql_bsent; EXECUTE st; DEALLOCATE PREPARE st;

-- 4) Orders tablosuna puan alanları (idempotent)
SET @col_pe := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'orders' AND column_name = 'loyalty_points_earned');
SET @sql_pe := IF(@col_pe = 0, 'ALTER TABLE orders ADD COLUMN loyalty_points_earned INT NOT NULL DEFAULT 0 AFTER discount_amount, ADD COLUMN loyalty_points_used INT NOT NULL DEFAULT 0 AFTER loyalty_points_earned, ADD COLUMN loyalty_points_value DECIMAL(10,2) NOT NULL DEFAULT 0 AFTER loyalty_points_used', 'SELECT "orders.loyalty_points_* already exists"');
PREPARE st FROM @sql_pe; EXECUTE st; DEALLOCATE PREPARE st;
