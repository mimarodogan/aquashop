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
