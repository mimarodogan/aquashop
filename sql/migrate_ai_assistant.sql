-- ───────────────────────────────────────────────────────────────────────────
-- AI Danışman — sohbet logu (KVKK uyumlu)
--
-- Amaç:
--   1) Analiz: "Müşteriler ne soruyor / hangi ürünü arayıp bulamıyor" içgörüsü
--   2) Hız sınırı (rate limit): kısa sürede aşırı istek atan oturum/IP'yi engelle
--
-- KVKK notu: Ham IP SAKLANMAZ. Yalnızca geri döndürülemez sha256 ip_hash tutulur
-- (pseudonimizasyon). Mesaj içeriği saklanır ama kişisel veri girilmesi beklenmez.
-- ───────────────────────────────────────────────────────────────────────────

CREATE TABLE IF NOT EXISTS ai_chat_log (
  id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  session_id  VARCHAR(64)  NOT NULL DEFAULT '',
  user_id     INT UNSIGNED NULL,
  role        ENUM('user','assistant') NOT NULL,
  message     TEXT NOT NULL,
  model       VARCHAR(48) NULL,
  ip_hash     CHAR(64) NOT NULL DEFAULT '',
  created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_session_created (session_id, created_at),
  KEY idx_iphash_created  (ip_hash, created_at),
  KEY idx_role_created    (role, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
