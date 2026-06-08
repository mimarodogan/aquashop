# AquaShop — E-Ticaret Platformu

PHP/MySQL ile geliştirilmiş, çok-domain destekli e-ticaret uygulaması. Akvaryum/evcil hayvan ürünleri mağazası olarak yapılandırılmıştır; ürün kategorisi ayarlardan değiştirilebilir.

## Özellikler

- 🛒 Ürün kataloğu, varyasyonlar, sepet, çok adımlı ödeme
- 💳 iyzico ödeme entegrasyonu (3D Secure, taksit) + havale/EFT
- 👤 Üyelik, sipariş geçmişi, çoklu adres, favoriler
- 🏆 Sadakat puanı programı (kazan/harca/seviye)
- 📊 GA4 / GTM / Meta Pixel / CAPI / Clarity (KVKK consent-gated)
- 📧 Terk sepet, fiyat düştü, stok bildirimi e-posta akışları
- 🤖 AI Danışman (Claude tabanlı, canlı ürün arama)
- 🔧 Kapsamlı admin paneli (sipariş, ürün, içerik, rapor, ayar)
- 🔍 SEO: dinamik sitemap, breadcrumb/JSON-LD, sosyal paylaşım

## Teknoloji

- **Backend:** PHP (PDO, prepared statements), MySQL/MariaDB
- **Frontend:** Sunucu taraflı render + vanilla JS, modüler CSS
- **Bağımlılık:** iyzipay-php SDK (`vendor/`)

## Kurulum

```bash
# 1) Depoyu klonla
git clone https://github.com/mimarodogan/aquashop.git
cd aquashop

# 2) Ortam dosyasını hazırla
cp .env.example .env
#    .env içine gerçek DB bilgilerini yaz

# 3) Veritabanı yapılandırması
cp config/db.example.php config/db.php
#    (db.php .env'i otomatik okur)

# 4) Veritabanını oluştur ve şemayı içe aktar
#    sql/schema.sql → ardından sql/ içindeki migrate_*.sql dosyaları
```

Sunucuda `.env` dosyasına `chmod 644` verin. Apache + `mod_rewrite` gereklidir (`.htaccess` SEO dostu URL'leri ve güvenlik kurallarını yönetir).

## Güvenlik Notları

- `config/db.php` ve `.env` **repo dışındadır** (`.gitignore`).
- Tüm sırlar (iyzico, SMTP, AI anahtarları) veritabanındaki `settings` tablosundan, admin panelinden yönetilir — kodda gömülü değildir.
- Veritabanı sorguları parametreli (prepared statements); kullanıcı içeriği çıktıda escape edilir.
- `uploads/` içinde PHP çalıştırma engellidir; yüklenen görseller GD ile yeniden kodlanır.

## Yapı

```
admin_panel/   Yönetim paneli
ajax/          AJAX uç noktaları
api/           LLM/agent JSON API
assets/        CSS / JS / görseller
components/    Tekrar kullanılan parçalar (header, footer, kartlar)
core/          Çekirdek (router, db, auth, helpers)
cron/          Zamanlanmış görevler
includes/      Yardımcılar (mailer, pricing, stock)
models/        Veri erişim katmanı
pages/         Sayfa şablonları
sql/           Şema ve migration'lar
```

## Lisans

Özel/ticari proje.
