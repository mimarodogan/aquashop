# Kurulum Rehberi

Bu rehber, dosyalari indiren kisinin sifirdan calisan bir e-ticaret sistemi kurmasi icindir. Ornek komutlar cPanel/Apache + MySQL/MariaDB ortamini hedefler.

## 1. Gereksinimler

- PHP 8.1 veya uzeri
- MySQL 5.7+ ya da MariaDB 10.4+
- Apache `mod_rewrite`
- PHP eklentileri: `pdo_mysql`, `mbstring`, `curl`, `gd`, `fileinfo`, `json`, `openssl`
- En az 256 MB PHP memory limit onerilir

## 2. Dosyalari Sunucuya Yukle

1. Proje dosyalarini hosting hesabindaki site kok dizinine yukleyin. Genelde bu dizin `public_html` olur.
2. `.htaccess` dosyasinin da yuklendiginden emin olun. Gizli dosyalar bazi FTP programlarinda kapali gorunebilir.
3. `uploads/` dizini yoksa olusturun.

Onerilen izinler:

```bash
find . -type d -exec chmod 755 {} \;
find . -type f -exec chmod 644 {} \;
chmod -R 755 uploads
```

## 3. Veritabani Olustur

1. Hosting panelinden yeni bir veritabani ve veritabani kullanicisi olusturun.
2. Kullaniciya bu veritabani icin tum yetkileri verin.
3. phpMyAdmin veya benzeri aracta yeni veritabanini secin.
4. `sql/install.sql` dosyasini ice aktarin.

`sql/install.sql` bos veritabanini kurar, gerekli kolon/indexleri ekler ve varsayilan yonetici hesabini olusturur.

Varsayilan giris:

```text
E-posta: admin@example.com
Sifre: admin123
```

Canliya almadan once bu sifre mutlaka degistirilmelidir.

## 4. Ortam Dosyasini Hazirla

Sunucuda `.env.example` dosyasini `.env` olarak kopyalayin:

```bash
cp .env.example .env
```

`.env` icindeki alanlari kendi hosting bilgilerinizle doldurun:

```ini
DB_HOST=127.0.0.1
DB_NAME=veritabani_adi
DB_USER=veritabani_kullanicisi
DB_PASS=veritabani_sifresi
DB_CHARSET=utf8mb4
APP_ENV=production
TRUSTED_PROXIES=
```

Ardindan veritabani config dosyasini olusturun:

```bash
cp config/db.example.php config/db.php
```

Not: `config/db.php`, veritabani bilgilerini `.env` dosyasindan okur. Gercek sifreleri kod dosyalarina yazmayin.

## 5. Ilk Giris ve Zorunlu Ayarlar

1. Site adresinizi acin.
2. `https://site-adresiniz.com/giris` adresinden varsayilan admin hesabi ile giris yapin.
3. Admin paneline gecin.
4. Ilk is olarak admin sifresini degistirin.
5. `Ayarlar > Kimlik` bolumunden su alanlari doldurun:
   - Site adi
   - Slogan / ust etiket
   - Site URL
   - Iletisim e-postasi
   - Telefon
   - Adres
   - Sosyal medya hesaplari
6. `Ayarlar > Entegrasyonlar` bolumunden SMTP, odeme ve pazarlama anahtarlarini girin.
7. `Ayarlar > Pazarlama / SEO` bolumunden takip kodlarini ve SEO varsayilanlarini kontrol edin.

## 6. Test Et

Canliya almadan once asagidaki ekranlari kontrol edin:

- Anasayfa aciliyor mu?
- Kategoriler ve urun detaylari aciliyor mu?
- Arama calisiyor mu?
- Sepete ekleme ve sepet sayfasi calisiyor mu?
- Uye olma, giris, sifremi unuttum akisi calisiyor mu?
- Admin panelinde urun ekleme/duzenleme/silme calisiyor mu?
- Stok, siparis, kupon, blog, sayfa ve ayar ekranlari aciliyor mu?
- SMTP ile test e-postasi gonderilebiliyor mu?
- Odeme yontemi test modunda basarili donuyor mu?

Yerelde hizli test icin:

```bash
php -S localhost:8000 router.php
```

Ardindan `http://localhost:8000` adresinden siteyi acabilirsiniz.

## 7. Canliya Alma Kontrol Listesi

- `.env` dosyasinda `APP_ENV=production` olmali.
- Varsayilan admin sifresi degistirilmis olmali.
- Admin panelindeki site adi, slogan ve iletisim bilgileri doldurulmus olmali.
- SMTP bilgileri gercek hesapla test edilmeli.
- Odeme entegrasyonu canli anahtarlarla test edilmeli.
- `uploads/` dizini yazilabilir olmali.
- Sunucuda HTTPS aktif olmali.
- `site_url` ayari HTTPS ile baslayan gercek domain olmali.
- phpMyAdmin veya hosting panelinde veritabani yedegi alinmali.

## 8. Guncelleme Notu

Mevcut kurulum uzerine gecis yapiliyorsa once veritabani yedegi alin. Bos kurulum icin yalnizca `sql/install.sql` yeterlidir; mevcut sistemlerde admin panelindeki migration araclari ve `sql/` altindaki migration dosyalari artimli guncelleme icin tutulur.
