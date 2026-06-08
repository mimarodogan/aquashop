-- Terk edilmiş sepet takip tablosu
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
