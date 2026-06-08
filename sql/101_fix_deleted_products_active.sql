-- ─────────────────────────────────────────────────────────────────────
-- 101 — Silinmiş ama hala "aktif" olarak işaretli ürünleri pasif yap
--
-- Sorun: Eskiden admin'de "Sil" butonu sadece deleted_at = NOW() yapıyordu,
-- is_active'e dokunmuyordu. Storefront sorgularının çoğu sadece is_active=1
-- kontrolü yaptığı için soft-deleted ürünler hala sitede görünüyordu.
--
-- Çözüm:
--  1. Bu migration mevcut silinmiş ürünlerin is_active'ini 0'a çekiyor.
--  2. admin_panel/products/list.php artık "Sil" sırasında is_active=0 da yapar.
--
-- Idempotent: tekrar çalıştırılırsa 0 satır etkiler.
-- ─────────────────────────────────────────────────────────────────────

UPDATE products
SET is_active = 0
WHERE deleted_at IS NOT NULL
  AND is_active = 1;
