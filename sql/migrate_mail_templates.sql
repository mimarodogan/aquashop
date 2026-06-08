-- Mail şablonları tablosu
CREATE TABLE IF NOT EXISTS mail_templates (
  `key`       VARCHAR(60)  NOT NULL PRIMARY KEY,
  subject     VARCHAR(255) NOT NULL,
  body_html   TEXT         NOT NULL,
  updated_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Varsayılan şablonları ekle (INSERT IGNORE = zaten varsa dokunma)
INSERT IGNORE INTO mail_templates (`key`, subject, body_html) VALUES
('welcome',
 'Hoş Geldiniz — {{site_adi}}',
 '<p>Merhaba <strong>{{isim}}</strong>,</p>
<p>{{site_adi}} ailesine hoş geldiniz! Hesabınız başarıyla oluşturuldu.</p>
<p>Binlerce ürün arasından kolayca alışveriş yapabilir, siparişlerinizi takip edebilirsiniz.</p>'),

('price_alert',
 'Favorinizdeki ürün indirimde! — {{urun_adi}}',
 '<p>Merhaba <strong>{{isim}}</strong>,</p>
<p>Favorilerinize eklediğiniz <strong>{{urun_adi}}</strong> ürününün fiyatı düştü!</p>
<p>Eski fiyat: <del>{{eski_fiyat}}</del><br>Yeni fiyat: <strong>{{yeni_fiyat}}</strong></p>'),

('abandoned_cart',
 'Sepetinizde ürünler bekliyor — {{site_adi}}',
 '<p>Merhaba <strong>{{isim}}</strong>,</p>
<p>Sepetinizde unutulan ürünler var! Alışverişinizi tamamlamak için hâlâ zaman var.</p>
<p>{{sepet_ozeti}}</p>'),

('restock_notify',
 'Ürün tekrar stokta! — {{urun_adi}}',
 '<p>Merhaba,</p>
<p>Stok bildirimi istediğiniz <strong>{{urun_adi}}</strong> tekrar stoğa girdi!</p>
<p>Stoğun hızlı tükenebileceğini unutmayın.</p>'),

('restock_confirm',
 'Stok bildirimi kaydınız alındı — {{urun_adi}}',
 '<p>Merhaba,</p>
<p><strong>{{urun_adi}}</strong> için stok bildirimi talebiniz alındı.</p>
<p>Ürün stoğa girdiğinde sizi e-posta ile haberdar edeceğiz.</p>'),

('order_confirm',
 'Siparişiniz Alındı — #{{siparis_no}}',
 '<p>Merhaba <strong>{{isim}}</strong>,</p>
<p><strong>#{{siparis_no}}</strong> numaralı siparişiniz başarıyla alındı.</p>
<p>{{siparis_ozeti}}</p>
<p>Siparişinizi hesabınızdan takip edebilirsiniz.</p>');
