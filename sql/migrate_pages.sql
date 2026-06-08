-- Sayfalar (CMS) tablosu + varsayılan iki sayfa
CREATE TABLE IF NOT EXISTS pages (
  id INT AUTO_INCREMENT PRIMARY KEY,
  slug VARCHAR(140) NOT NULL UNIQUE,
  title VARCHAR(200) NOT NULL,
  content MEDIUMTEXT,
  is_published TINYINT(1) NOT NULL DEFAULT 1,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO pages (slug,title,content) VALUES
  ('uyelik-kosullari','Üyelik Koşulları',
   '<h2>Üyelik Koşulları</h2><p>Bu metin, sitemize üye olan kullanıcıların kabul ettiği koşulları içerir. Yönetim panelinden düzenleyebilirsiniz.</p><h3>1. Genel</h3><p>Üyelik formunu doldurarak işbu koşulları kabul etmiş olursunuz.</p><h3>2. Hak ve Yükümlülükler</h3><p>Hesap bilgilerinizin gizliliğinden siz sorumlusunuz.</p><h3>3. İletişim</h3><p>Sorularınız için iletişim sayfamızı kullanabilirsiniz.</p>'),
  ('kvkk','Kişisel Verilerin Korunması',
   '<h2>Kişisel Verilerin Korunması Hakkında Aydınlatma Metni</h2><p>6698 sayılı Kişisel Verilerin Korunması Kanunu (KVKK) kapsamında veri sorumlusu sıfatıyla işlediğimiz kişisel verilerinize ilişkin bilgilendirmedir. Yönetim panelinden düzenleyebilirsiniz.</p><h3>1. Toplanan Veriler</h3><p>Ad-soyad, e-posta, telefon, adres, doğum tarihi gibi bilgiler.</p><h3>2. İşleme Amaçları</h3><p>Sipariş yönetimi, müşteri ilişkileri, pazarlama (onay verdiyseniz).</p><h3>3. Haklarınız</h3><p>KVKK md.11 kapsamındaki haklarınızı kullanmak için bize başvurabilirsiniz.</p>')
ON DUPLICATE KEY UPDATE title=VALUES(title);
