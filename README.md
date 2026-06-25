# AquaShop — E-Ticaret Platformu

PHP/MySQL ile geliştirilmiş, çok-domain destekli e-ticaret uygulaması. Akvaryum/evcil hayvan ürünleri mağazası olarak yapılandırılmıştır; ürün kategorisi ayarlardan değiştirilebilir.

## Ekran Görüntüleri

Kurulum yapmadan platformun nasıl göründüğünü görün:

| Anasayfa | Ürün Detay |
|---|---|
| [![Anasayfa](docs/screenshots/anasayfa.png)](docs/screenshots/anasayfa.png) | [![Ürün Detay](docs/screenshots/urun-detay.png)](docs/screenshots/urun-detay.png) |
| **Ürün Listesi & Filtre** | **Kategori** |
| [![Ürün Listesi](docs/screenshots/urun-listesi.png)](docs/screenshots/urun-listesi.png) | [![Kategori](docs/screenshots/kategori.png)](docs/screenshots/kategori.png) |
| **Blog Yazısı** | **Hata Sayfası (404)** |
| [![Blog Yazısı](docs/screenshots/blog-yazi.png)](docs/screenshots/blog-yazi.png) | [![404](docs/screenshots/hata-404.png)](docs/screenshots/hata-404.png) |

> Açıklamalı görsel döküm: [docs/EKRAN-GORUNTULERI.md](docs/EKRAN-GORUNTULERI.md)
>
> Öne çıkanlar: yatay kayan ürün/blog carousel'leri, tek-tip 260px kartlar, kategori
> ile aynı filtre sidebar'ı, canlı tasarımla birebir ürün detayı ve temaya uygun
> 404/403/401/500/503 hata sayfaları.

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
- **Frontend:** Sunucu taraflı render + vanilla JS, modüler CSS (aqua tasarım sistemi)
- **Bağımlılık:** iyzipay-php SDK (`vendor/`)

## Kurulum

```bash
# 1) Depoyu klonla
git clone https://github.com/mimarodogan/aquashop.git
cd aquashop

# 2) Ortam dosyasını hazırla
cp .env.example .env
#    .env içine gerçek DB bilgilerini yaz (DB_HOST/DB_NAME/DB_USER/DB_PASS)

# 3) Veritabanını oluştur ve şemayı içe aktar
#    sql/install.sql dosyasını boş bir veritabanına içe aktarın
#    (tüm tablolar + migration'lar tek dosyada birleşiktir)
```

Adım adım kurulum için [KURULUM.md](KURULUM.md) dosyasını takip edin.

Varsayılan admin girişi (canlıya almadan önce **mutlaka değiştirin**):

```text
E-posta: admin@example.com
Şifre:   admin123
```

Apache + `mod_rewrite` gereklidir (`.htaccess` SEO dostu URL'leri, güvenlik kurallarını
ve temaya uygun hata sayfalarını yönetir).

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
docs/          Belgeler ve ekran görüntüleri
includes/      Yardımcılar (mailer, pricing, stock, hata sayfaları)
models/        Veri erişim katmanı
pages/         Sayfa şablonları
sql/           Şema ve migration'lar (install.sql)
```

## Lisans

Özel/ticari proje. Tasarım & geliştirme: [Mimar Osman Doğan](https://odogan.com.tr)
