-- SEO sayfa-bazlı robots etiketi desteği
ALTER TABLE seo_settings
  ADD COLUMN IF NOT EXISTS meta_robots VARCHAR(120) DEFAULT NULL AFTER meta_keywords;

-- settings tablosuna varsayılan SEO anahtarları (yoksa ekler)
INSERT IGNORE INTO settings (setting_key, setting_value) VALUES
  ('seo_author',           ''),
  ('seo_publisher',        ''),
  ('seo_robots',           'index, follow'),
  ('seo_twitter_handle',   ''),
  ('seo_default_og_image', '');
