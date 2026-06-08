-- Blog yazıları için SSS tablosu
CREATE TABLE IF NOT EXISTS blog_post_faqs (
    id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    post_id    INT UNSIGNED NOT NULL,
    question   VARCHAR(500) NOT NULL,
    answer     TEXT         NOT NULL,
    sort_order SMALLINT     NOT NULL DEFAULT 0,
    INDEX idx_post (post_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
