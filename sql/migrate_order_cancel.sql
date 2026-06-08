ALTER TABLE orders
  ADD COLUMN cancellation_reason TEXT DEFAULT NULL AFTER note,
  ADD COLUMN cancelled_at DATETIME DEFAULT NULL AFTER cancellation_reason,
  ADD COLUMN cancelled_by INT DEFAULT NULL AFTER cancelled_at;
