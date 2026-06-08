-- ─────────────────────────────────────────────────────────────────────
-- abandoned_carts: multi-step hatırlatma desteği
--
-- reminder_step:
--   0 = hiç hatırlatma gönderilmedi (yeni terk)
--   1 = 1. hatırlatma (24 saat sonra, sade)
--   2 = 2. hatırlatma (72 saat sonra, indirim kuponu ile)
--   3 = 3. (son) hatırlatma (7 gün sonra, son şans + sosyal kanıt)
--
-- coupon_code: 2. adımda otomatik üretilen kupon kodu (varsa)
-- last_reminder_at: en son hangi zamanda hatırlatıldı (idempotent dedup)
-- ─────────────────────────────────────────────────────────────────────

ALTER TABLE abandoned_carts
  ADD COLUMN reminder_step    TINYINT UNSIGNED NOT NULL DEFAULT 0 AFTER notified_at,
  ADD COLUMN last_reminder_at DATETIME NULL AFTER reminder_step,
  ADD COLUMN coupon_code      VARCHAR(64) NULL AFTER last_reminder_at,
  ADD INDEX idx_step_update (reminder_step, updated_at);

-- Mevcut kayıtlardan notified_at dolu olanları step=1 olarak işaretle (geriye uyum)
UPDATE abandoned_carts
SET reminder_step = 1, last_reminder_at = notified_at
WHERE notified_at IS NOT NULL AND reminder_step = 0;
