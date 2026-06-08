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
