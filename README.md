# E-Ticaret Platformu

PHP/MySQL ile gelistirilmis, admin panelinden site adi, slogan, iletisim bilgileri, urunler, siparisler, icerikler, SEO ve entegrasyonlar yonetilebilen e-ticaret sistemi.

## Kurulum

Adim adim kurulum icin [KURULUM.md](KURULUM.md) dosyasini takip edin.

Kisa ozet:

```bash
cp .env.example .env
cp config/db.example.php config/db.php
```

Ardindan `sql/install.sql` dosyasini bos veritabanina ice aktarin.

Varsayilan admin girisi:

```text
E-posta: admin@example.com
Sifre: admin123
```

Canliya almadan once varsayilan sifreyi degistirin.

## Ana Moduller

- Urun, kategori, varyasyon ve stok yonetimi
- Sepet, odeme, siparis ve iade akisleri
- Uye girisi, kayit, sifremi unuttum ve hesap sayfalari
- Kupon, sadakat puani, bulten ve terk sepet akisleri
- Blog, CMS sayfalari, SEO ve sitemap
- SMTP, iyzico, analitik ve pazarlama entegrasyonlari
- Admin panelinden site kimligi, logo, slogan ve iletisim bilgileri

## Yerel Calistirma

```bash
php -S localhost:8000 router.php
```

Sonra `http://localhost:8000` adresini acin.

## Dizinler

```text
admin_panel/   Yonetim paneli
ajax/          AJAX uclari
api/           JSON API uclari
assets/        CSS, JS ve gorseller
components/    Ortak arayuz parcalari
core/          Cekirdek bootstrap, router, helper ve DB katmani
cron/          Zamanlanmis gorevler
includes/      Yardimci servisler
models/        Veri erisim katmani
pages/         Sayfa sablonlari
sql/           Kurulum SQL'i ve migration dosyalari
```

## Notlar

- Gercek veritabani sifreleri `.env` icinde tutulur.
- `.env` ve `config/db.php` canli ortama ozel dosyalardir.
- Canliya almadan once `APP_ENV=production` olmalidir.
- Yukleme sonrasi admin panelindeki `Ayarlar > Kimlik` bolumu mutlaka doldurulmalidir.

## Lisans

Ozel/ticari proje.
