-- ─────────────────────────────────────────────────────────────────────
-- 103 — Çalışma anında (kod içinde) eklenen kolonları şemaya taşı
--
-- Bu kolonlar normalde uygulama kodu tarafından ilk ihtiyaç anında
-- "ALTER TABLE ... ADD COLUMN IF NOT EXISTS" ile ekleniyordu. Tam ve
-- kendine yeten bir kurulum için burada da tanımlanırlar (idempotent).
-- ─────────────────────────────────────────────────────────────────────

-- Stok rezervasyonu/düşümü: order_items satırına stok ne zaman uygulandı
ALTER TABLE order_items ADD COLUMN IF NOT EXISTS stock_applied_at DATETIME DEFAULT NULL;

-- Blog yazısı → yazar ilişkisi
ALTER TABLE blog_posts ADD COLUMN IF NOT EXISTS blog_author_id INT NULL DEFAULT NULL;
