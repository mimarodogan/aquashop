ALTER TABLE orders
  ADD COLUMN tracking_carrier VARCHAR(40) DEFAULT NULL AFTER status,
  ADD COLUMN tracking_number  VARCHAR(80) DEFAULT NULL AFTER tracking_carrier,
  ADD COLUMN shipped_at DATETIME DEFAULT NULL AFTER tracking_number;
