-- ─────────────────────────────────────────────────────────────────────
-- Sayfa görüntülenmeleri (server-side basit sayım)
-- GA4'e ek/yedek olarak — admin dashboard'da dönüşüm oranı hesaplamak için.
--
-- Saklama: 90 gün (eski kayıtlar cron ile temizlenebilir).
-- Bot trafiği include edilmez (UA filtresi tracking tarafında uygulanır).
-- ─────────────────────────────────────────────────────────────────────
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

-- Ürün-spesifik görüntüleme (sosyal kanıt "şu an X kişi inceliyor" için)
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
