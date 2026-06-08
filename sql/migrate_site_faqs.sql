-- SSS (Sıkça Sorulan Sorular) site geneli soru-cevap tablosu
-- Admin paneli → İçerik & SEO → SSS Yönetimi bölümünden yönetilir.
-- Ön yüzde accordion olarak görünür, FAQPage JSON-LD schema üretir.

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
