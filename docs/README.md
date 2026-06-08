# Yurtiçi E-ticaret (PHP + MySQL) — Modüler Mimari

## Klasör Yapısı

```
aquashop/
├── core/                    # Çekirdek altyapı (DB, Session, Auth, helpers, Media)
│   ├── bootstrap.php        # Tüm sayfalar bu dosyayı yükler
│   ├── Database.php         # PDO bağlantısı (db())
│   ├── Session.php          # session_start, csrf, flash
│   ├── Auth.php             # current_user, require_admin
│   ├── Media.php            # WebP dönüşüm, sıkıştırma, kullanım takibi
│   └── helpers.php          # e(), money(), status_label, carriers, vb.
│
├── models/                  # Veri erişim katmanı
│   ├── Setting.php          # setting()
│   ├── Cart.php             # cart_*
│   ├── Favorite.php         # fav_*
│   └── Comment.php          # comment_*
│
├── components/              # Yeniden kullanılan UI parçaları
│   ├── header.php           # Public navbar + mobil menü
│   └── footer.php           # Public footer
│
├── controllers/             # POST endpoint'leri (gelecekte buraya taşınacak)
│
├── pages/                   # Storefront şablon iskeleti (gelecekte aktifleşecek)
│
├── admin_panel/             # Yönetim paneli (admin/'in yeni kopyası)
│
├── admin/                   # Eski yönetim klasörü (geriye dönük uyum için duruyor)
│
├── includes/                # Geriye dönük uyum: thin shim'ler core/components'e yönlendirir
│   ├── functions.php        # → core/bootstrap.php
│   ├── header.php           # → components/header.php
│   ├── footer.php           # → components/footer.php
│   └── media.php            # → core/bootstrap.php
│
├── config/db.php            # Sadece DB sabitleri ve SITE_URL
├── assets/css/              # style.css, admin.css, auth.css
├── uploads/                 # Yüklenen medya (.htaccess ile PHP yürütme engelli)
├── sql/                     # Migration dosyaları
└── *.php                    # Storefront giriş noktaları (URL'ler korundu)
```

## Geriye Dönük Uyum
- Mevcut tüm URL'ler aynı çalışır.
- `require_once 'includes/functions.php'` → otomatik olarak `core/bootstrap.php`'yi çağırır.
- Tüm public fonksiyon imzaları korundu (db, current_user, cart_*, fav_*, comment_*, setting, csrf_*, flash_*, e, money, status_label, ...).

## Kurulum
1. cPanel'de MySQL DB oluştur, `config/db.php`'deki bilgileri güncelle.
2. phpMyAdmin > SQL'e `sql/full_setup.sql` içeriğini yapıştırıp çalıştır.
3. Tarayıcıda `/install.php` aç (admin kullanıcısı: `admin@example.com` / `admin123`).
4. `install.php`'yi sil.

## Yönetim
- `admin/` veya `admin_panel/` (her ikisi de aynı içerik) → giriş yapıp paneli kullan.
- İleride `admin/` kaldırılıp sadece `admin_panel/` bırakılabilir.

## Geliştirici Notları
- `core/bootstrap.php` tüm dosyaları otomatik yükler.
- Yeni model ekleyeceksen `models/` altına dosya koy ve `core/bootstrap.php`'ye `require_once` ekle.
- Yeni sayfa şablonu `pages/` altında oluştur, root'ta thin shim ile çağır:
  ```php
  <?php require __DIR__ . '/pages/yeni-sayfa.php';
  ```
- Yeni POST endpoint için `controllers/` altına dosya koy, root'ta thin shim ekle.
