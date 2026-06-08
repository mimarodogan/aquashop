ALTER TABLE products ADD COLUMN sku VARCHAR(60) DEFAULT NULL AFTER slug;
CREATE INDEX idx_products_sku ON products(sku);
