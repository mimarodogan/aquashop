ALTER TABLE pages ADD COLUMN cover_image VARCHAR(255) DEFAULT NULL AFTER content;

INSERT INTO pages (slug,title,content) VALUES
('hakkimizda','Hakkımızda',
 '<h2>Hikayemiz</h2><p>Yıllar içinde damıttığımız ustalık ve özenle, yurt içinin dört bir yanına ulaşan ürünlerimiz; sade ama derin bir estetik anlayışın somut ifadeleridir.</p><p>Her detayda zanaatkar dokunuşu, her seçimde özen. Müşterilerimize yalnızca bir ürün değil, zamansız bir deneyim sunmayı hedefliyoruz.</p><p>Sürdürülebilir üretim, adil ticaret ve müşteri memnuniyeti ilkelerimizin temelini oluşturur.</p>')
ON DUPLICATE KEY UPDATE title=VALUES(title);
