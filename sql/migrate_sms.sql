-- ─────────────────────────────────────────────────────────────────────
-- SMS gönderim log tablosu
-- Tüm gönderimler (başarı + hata) buraya yazılır — denetim, faturalandırma, hata izleme.
-- ─────────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS sms_log (
    id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    recipient     VARCHAR(20)     NOT NULL,
    message       VARCHAR(500)    NOT NULL,
    provider      VARCHAR(32)     NOT NULL,
    template      VARCHAR(64)     NULL,
    status        ENUM('success','failure') NOT NULL DEFAULT 'failure',
    error_message VARCHAR(255)    NULL,
    sent_at       DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_sent  (sent_at),
    KEY idx_recip (recipient, sent_at),
    KEY idx_status(status, sent_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
