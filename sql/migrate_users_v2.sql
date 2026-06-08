-- users tablosuna ek alanlar (mevcut DB üzerinde tek seferlik çalıştırın)
ALTER TABLE users
  ADD COLUMN first_name VARCHAR(80) DEFAULT NULL AFTER name,
  ADD COLUMN last_name  VARCHAR(80) DEFAULT NULL AFTER first_name,
  ADD COLUMN birth_date DATE        DEFAULT NULL AFTER address,
  ADD COLUMN email_consent TINYINT(1) NOT NULL DEFAULT 0 AFTER birth_date,
  ADD COLUMN sms_consent   TINYINT(1) NOT NULL DEFAULT 0 AFTER email_consent;
