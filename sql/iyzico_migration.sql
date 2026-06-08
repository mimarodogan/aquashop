-- iyzico ödeme entegrasyonu için şema güncellemeleri
-- phpMyAdmin → SQL sekmesinde tek seferde çalıştırılır.

-- 1) orders tablosuna ödeme kolonları
ALTER TABLE orders
  ADD COLUMN IF NOT EXISTS payment_status ENUM('pending','paid','failed','refunded','partial_refund') NOT NULL DEFAULT 'pending' AFTER status,
  ADD COLUMN IF NOT EXISTS paid_at DATETIME NULL DEFAULT NULL AFTER payment_status,
  ADD COLUMN IF NOT EXISTS iyzico_token VARCHAR(255) DEFAULT NULL AFTER paid_at,
  ADD COLUMN IF NOT EXISTS iyzico_payment_id VARCHAR(64) DEFAULT NULL AFTER iyzico_token,
  ADD COLUMN IF NOT EXISTS iyzico_conversation_id VARCHAR(64) DEFAULT NULL AFTER iyzico_payment_id,
  ADD INDEX IF NOT EXISTS idx_orders_token (iyzico_token),
  ADD INDEX IF NOT EXISTS idx_orders_payment_id (iyzico_payment_id);

-- 2) Ödeme işlem kaydı (her tetikleme + callback ham yanıtı)
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

-- 3) İade kayıtları (kısmi/tam iade için ayrı satır)
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
