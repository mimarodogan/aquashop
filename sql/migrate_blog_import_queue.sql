-- Blog yazısı görsel indirme kuyruğu (wp_blog_import_queue)
-- wp_import_queue'nun blog eşdeğeri; kapak görseli indirir, blog_posts.cover_image günceller.
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
