-- Şifre sıfırlama için users tablosuna kolonlar
ALTER TABLE users
  ADD COLUMN IF NOT EXISTS password_reset_token VARCHAR(64) DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS password_reset_expires DATETIME DEFAULT NULL,
  ADD INDEX IF NOT EXISTS idx_users_reset (password_reset_token);
