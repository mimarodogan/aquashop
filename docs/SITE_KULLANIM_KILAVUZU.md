# 📚 Detaylı Site Kullanım Kılavuzu

> Bu doküman sitenizin **tüm özelliklerinin** detaylı kullanımını anlatır.
> Müşteri tarafı, yönetim paneli, ayarlar, iş akışları ve bakım — hepsi tek bir yerde.

**Versiyon:** 1.0 · **Tarih:** 2026-05-19

---

## 📑 İçindekiler

### BÖLÜM A — SİTE TANITIMI
- [1. Genel Bakış](#1-genel-bakış)
- [2. Site Mimarisi](#2-site-mimarisi)
- [3. Roller ve Yetkiler](#3-roller-ve-yetkiler)

### BÖLÜM B — MÜŞTERİ TARAFI (Storefront)
- [4. Anasayfa](#4-anasayfa)
- [5. Üyelik İşlemleri](#5-üyelik-i̇şlemleri)
- [6. Ürünler ve Gezinme](#6-ürünler-ve-gezinme)
- [7. Ürün Detay Sayfası](#7-ürün-detay-sayfası)
- [8. Sepet ve Ödeme](#8-sepet-ve-ödeme)
- [9. Hesabım Paneli](#9-hesabım-paneli)
- [10. İçerik Sayfaları](#10-i̇çerik-sayfaları)

### BÖLÜM C — YÖNETİM PANELİ (Admin)
- [11. Admin Girişi](#11-admin-girişi)
- [12. Komuta Merkezi (Dashboard)](#12-komuta-merkezi-dashboard)
- [13. Sidebar Navigasyon](#13-sidebar-navigasyon)
- [14. Satış Yönetimi](#14-satış-yönetimi)
- [15. Analiz & Raporlar](#15-analiz--raporlar)
- [16. Katalog Yönetimi](#16-katalog-yönetimi)
- [17. İçerik & SEO Yönetimi](#17-i̇çerik--seo-yönetimi)
- [18. Müşteri Yönetimi](#18-müşteri-yönetimi)
- [19. Sistem Ayarları](#19-sistem-ayarları)
- [20. Araçlar (Bakım)](#20-araçlar-bakım)

### BÖLÜM D — DETAYLI AYARLAR
- [21. Mağaza Kimliği](#21-mağaza-kimliği)
- [22. Pazarlama](#22-pazarlama)
- [23. Ticaret](#23-ticaret)
- [24. Analitik](#24-analitik)
- [25. Entegrasyonlar](#25-entegrasyonlar)

### BÖLÜM E — İŞ AKIŞLARI
- [26. Yeni Ürün Ekleme](#26-yeni-ürün-ekleme-akışı)
- [27. Günlük Sipariş İşleme](#27-günlük-sipariş-i̇şleme)
- [28. Kampanya Başlatma](#28-kampanya-başlatma)
- [29. Stok Yönetimi](#29-stok-yönetimi)
- [30. Müşteri Şikayeti Çözme](#30-müşteri-şikayeti-çözme)
- [31. Aylık Bakım Kontrolü](#31-aylık-bakım-kontrolü)

### BÖLÜM F — TEKNİK & BAKIM
- [32. Cron Job Listesi](#32-cron-job-listesi)
- [33. Yedekleme](#33-yedekleme)
- [34. SQL Migration Çalıştırma](#34-sql-migration-çalıştırma)
- [35. Hata Yönetimi](#35-hata-yönetimi)

### BÖLÜM G — SORUN GİDERME
- [36. SSS — Müşteri Tarafı](#36-sss--müşteri-tarafı)
- [37. SSS — Yönetim Tarafı](#37-sss--yönetim-tarafı)
- [38. Acil Durum Rehberi](#38-acil-durum-rehberi)

---

# BÖLÜM A — SİTE TANITIMI

## 1. Genel Bakış

Bu site, modern bir e-ticaret yazılımıdır. Aşağıdaki temel yetenekleri içerir:

| Yetenek | Açıklama |
|---|---|
| 🛒 **Ürün Satışı** | Varyasyonlu (çeşitli boyut/koku) ürünler, stok takibi, indirim |
| 💳 **Online Ödeme** | iyzico entegrasyonu, taksitli ödeme, havale, kapıda |
| 👤 **Üyelik Sistemi** | Kayıt, giriş, sosyal giriş (opsiyonel), şifre sıfırlama |
| 📊 **Analitik** | GA4 + GTM + Meta Pixel + CAPI + Clarity, KVKK uyumlu |
| 📧 **Email & SMS** | Sipariş bildirimleri, kampanya, sepet terk hatırlatma |
| 🏆 **Sadakat** | Puan toplama/kullanma, seviye sistemi, doğum günü kuponu |
| 📝 **Blog** | Yazı yönetimi, kategori, SEO optimizasyonu |
| 🔍 **SEO** | Sitemap, JSON-LD, breadcrumb, meta yönetimi |
| 🌐 **Çok Sayfa** | Hakkımızda, iletişim, KVKK gibi statik sayfalar |
| 📱 **Mobil Uyumlu** | Tüm sayfalar mobile-first, hızlı, akıcı |

---

## 2. Site Mimarisi

```
siteniz.com/
├── 🏠 Anasayfa (/)
├── 🛍️  Ürünler (/products)
├── 📦 Kategoriler (/category/X)
├── 🛒 Sepet (/cart)
├── 💳 Ödeme (/checkout)
├── 👤 Üyelik (/login, /register)
├── 📊 Hesabım (/account)
├── 📰 Blog (/blog)
├── 📄 Statik (/about, /contact, KVKK vb.)
├── 🔧 Admin (/admin_panel)
└── 🤖 API/AJAX (/ajax/*, /api/*)
```

### Dosya Yapısı (Sunucudaki)
```
public_html/
├── pages/          → Müşteri sayfaları (home, cart, checkout vb.)
├── controllers/    → İşlem alıcılar (sepete ekle, sipariş ver vb.)
├── components/     → Tekrar kullanılan bloklar (header, footer, banner)
├── models/         → Veritabanı sınıfları (Product, Order, User vb.)
├── core/           → Çekirdek (Database, Auth, Session, Csrf, helpers)
├── admin_panel/    → Yönetim arayüzü
├── assets/         → CSS, JS, görseller
├── cron/           → Otomatik çalışacak scriptler
├── sql/            → Veritabanı migrasyonları
└── includes/       → Header, footer, paylaşılan kodlar
```

---

## 3. Roller ve Yetkiler

| Rol | Yetkiler | Erişim |
|---|---|---|
| 🌐 **Misafir** | Ürün görüntüleme, sepete ekleme, misafir checkout | Tüm açık sayfalar |
| 👤 **Üye Müşteri** | Misafir + favoriler, puan, sipariş geçmişi, hızlı checkout | Storefront + /account |
| 🛠️ **Admin** | Tüm yönetim işlemleri | Tümü + /admin_panel |

**Önemli:** Admin panelinde rol detayı yok — admin'siniz veya değilsiniz. Çoklu admin yapacaksanız her birine kullanıcı oluşturup `role='admin'` yapın.

---

# BÖLÜM B — MÜŞTERİ TARAFI (Storefront)

## 4. Anasayfa

**URL:** `/`

**Bölümler (yukarıdan aşağıya):**
1. **Topbar** — Telefon, email, sosyal linkler (admin'den açılır/kapanır)
2. **Header** — Logo, menü, arama, sepet, hesap
3. **Hero Slider** — Banner görselleri (admin → Banner yönetimi)
4. **Öne Çıkan Kategoriler** — Ürün gruplarına hızlı geçiş
5. **Çok Satanlar** — Gerçek satış verisinden otomatik
6. **Yeni Eklenenler** — Son eklenen ürünler
7. **Kampanyalı Ürünler** — Discount alanı olan ürünler
8. **Blog Önizleme** — Son 3 yazı
9. **Bültene Kayıt** — Email yakalama formu
10. **Footer** — İletişim, sayfalar, sosyal, KVKK

**Admin müdahalesi:** Banner sırası, hangi kategorilerin öne çıkacağı, "Çok satanlar" gibi widget'ların gösterilip gösterilmemesi ayarlardan kontrol edilir.

---

## 5. Üyelik İşlemleri

### 5.1 Kayıt
**URL:** `/register`

**Müşteri girer:**
- Ad, soyad
- Email (benzersiz olmalı)
- Şifre (en az 8 karakter önerilir)
- Doğum tarihi (opsiyonel ama puan/kampanya için önemli)
- Telefon
- KVKK onay kutusu (zorunlu)

**Otomatik:**
- Email doğrulama linki gönderilir (SMTP açıksa)
- Hoşgeldin email'i (varsa şablon)

### 5.2 Giriş
**URL:** `/login`

- Email + şifre
- "Beni hatırla" işaretlenebilir (uzun ömürlü cookie)
- Yanlış şifre 5 kez = rate limit (15dk bekleme)

### 5.3 Şifre Sıfırlama
**URL:** `/forgot_password`

1. Email gir → sistem reset linki gönderir
2. Link 1 saat geçerli, tek kullanımlık
3. `/reset_password?token=XYZ` ile yeni şifre belirlenir

### 5.4 Çıkış
**URL:** `/logout` — session temizlenir, anasayfaya yönlenir

---

## 6. Ürünler ve Gezinme

### 6.1 Tüm Ürünler Listesi
**URL:** `/products`

**Özellikler:**
- Sayfalama (her sayfada 20-24 ürün)
- Sıralama: Yeni, Fiyat (artan/azalan), Popüler, A-Z
- Filtreler: Kategori, fiyat aralığı, stokta var/yok
- Arama (sağ üstteki kutu)

### 6.2 Kategori Sayfası
**URL:** `/category/{slug}`

Kategoriye özel:
- Kategori başlığı + açıklama
- Alt kategoriler (varsa)
- Sadece bu kategoriye ait ürünler
- SEO başlık, meta description (admin'den)

### 6.3 Tüm Kategoriler
**URL:** `/categories` (kategori ağacı görüntüsü)

### 6.4 Arama
- Header'daki arama kutusu
- Hem ürün adı hem açıklamada arar
- Sonuç sayfası `/products?q=kelime`

---

## 7. Ürün Detay Sayfası

**URL:** `/product/{slug}`

### 7.1 Görsel Galeri
- Ana görsel + küçük thumb'lar
- Tıklayınca büyük modal açılır (lightbox)
- Mobile'da swipe ile geçiş

### 7.2 Ürün Bilgileri
- Ürün adı + kategori
- Fiyat (varsa indirimli + üstü çizili eski fiyat)
- **"Sadece N tane kaldı"** rozeti (düşük stokta)
- **Sosyal kanıt:** "🔥 Son 24 saatte X satıldı"
- Stok durumu (Var / Yok / Az kaldı)

### 7.3 Varyasyonlar
Eğer ürünün farklı boyut/çeşitleri varsa:
- Dropdown veya buton grubu
- Her varyasyonun **kendi stoğu ve fiyatı** olabilir
- Seçim yapılınca fiyat ve "sadece N kaldı" güncellenir

### 7.4 Adet Seçimi + Sepete Ekleme
- "+/-" tuşları veya manuel adet
- "Sepete Ekle" tuşu → AJAX, sayfa yenilenmez
- Sepete eklenince **mini modal** açılır:
  - "Sepete eklendi" + ürün özeti
  - "Sepete Git" / "Alışverişe Devam Et" tuşları

### 7.5 Diğer Eylemler
- **❤️ Favorilere Ekle** (üye girişi gerek)
- **🔁 Karşılaştırmaya Ekle** (4 ürüne kadar)
- **🔖 Sonra Al** (üye girişi gerek)
- **📤 Paylaş** (WhatsApp/X/Facebook/Pinterest/Email/Kopyala)

### 7.6 Açıklama Sekmeleri
- 📝 **Açıklama** — Detaylı ürün metni (admin'den HTML)
- 📋 **Özellikler** — Teknik özellikler tablosu
- ❓ **SSS** — Accordion (admin'den girilir)
- ⭐ **Yorumlar** — Müşteri yorumları + puan

### 7.7 Yorum Bölümü
**Üye olmayan:** Yorumları okuyabilir, yazamaz.
**Üye:** Bu ürünü **satın almışsa** yorum yazabilir.
- 1-5 yıldız
- Başlık + metin
- Admin onayından sonra yayınlanır

### 7.8 Cross-Sell
"Bunlarla Harika Gider" — gerçek satış verisinden, bu ürünü alanların aldığı diğer 4 ürün.

### 7.9 Son Görüntülenenler
Sayfanın altında, müşterinin son gezdiği ürünlerin rafı (cookie/localStorage).

### 7.10 Breadcrumb (Yol İzi)
Üstte: `Ana Sayfa › Kategori › Ürün Adı` — hem kullanıcı için, hem Google için (JSON-LD).

---

## 8. Sepet ve Ödeme

### 8.1 Sepet Sayfası
**URL:** `/cart`

**Bölümler:**
- 🚚 **Ücretsiz kargo progress bar** (en üstte)
  - "Ücretsiz kargoya 45₺ kaldı" → %78 dolu çubuk
  - Eşik geçilince "🎉 Ücretsiz kargo kazandınız!"
- 📦 **Ürün listesi** (tablo / mobile'da kart)
  - Görsel, ad, varyasyon, birim fiyat, adet, ara toplam
  - "+/-" ile adet değiştirme, "Sil" butonu
- 🎟️ **Kupon kodu kutusu**
- 💰 **Sipariş özeti** (sağda / mobile'da altta)
  - Ara toplam
  - İndirim
  - Kargo
  - **Toplam**
- 🔗 **"Bunlarla harika gider"** cross-sell (altta)

**Butonlar:**
- ← **Alışverişe Devam Et**
- 🗑️ **Sepeti Temizle**
- ✅ **Ödemeye Geç** (büyük altın CTA)

### 8.2 Ödeme Sayfası (Checkout)
**URL:** `/checkout`

**Akış (tek sayfa):**

#### Adım 1 — İletişim
- Email (üyeyseniz dolu gelir)
- Telefon

#### Adım 2 — Teslimat Adresi
- Üyeyseniz: kayıtlı adresler listesi + yeni ekle
- Misafir: tüm alanlar elden girilir
  - Ad, soyad
  - Adres (mahalle, sokak)
  - İl, ilçe, posta kodu

#### Adım 3 — Fatura Bilgisi
- "Adresimle aynı" işaretliyse tek tıkla aynı bilgi
- Kurumsal fatura için vergi no/dairesi alanı

#### Adım 4 — Hediye Paketi (Opsiyonel)
- Hediye olarak paketle? (+ücret olabilir)
- Hediye mesajı (kart)

#### Adım 5 — Kargo Yöntemi
- Standart / Hızlı / Mağazadan Al (varsa)

#### Adım 6 — Ödeme Yöntemi
- 💳 **Kredi Kartı** (iyzico — taksit dahil)
- 🏦 **Banka Havalesi** (banka bilgileri gösterilir)
- 📦 **Kapıda Ödeme** (varsa)

#### Adım 7 — Onay
- Sözleşmeler kabul kutuları (mesafeli satış, ön bilgi, KVKK)
- 🔵 **"Siparişi Tamamla"** tuşu

### 8.3 Ödeme Sonrası
- **Başarılı:** Sipariş onay sayfası + email + (SMS aktifse) SMS
- **Başarısız:** Hata mesajı + tekrar deneme

### 8.4 Sipariş Takibi
**URL:** `/order/{order_number}`
- Sipariş durumu (Onay bekliyor → Hazırlanıyor → Kargoda → Teslim)
- Kargo takip numarası (admin girince görünür)
- Ürün listesi + tutarlar

---

## 9. Hesabım Paneli

**URL:** `/account` (giriş gerektirir)

### 9.1 Profil
- Ad, soyad, telefon güncelleme
- Email değişimi (yeni email'e onay linki gider)
- Şifre değişimi
- **Doğum tarihi** (bir kez girilince değiştirilemez — sahtekarlık koruması)

### 9.2 Adres Defteri
- Yeni adres ekle
- Mevcut adresleri düzenle/sil
- Varsayılan adres seç

### 9.3 Siparişlerim
- Tüm siparişler liste
- Tıklayınca detay
- "İade Talep Et" butonu (uygun durumda)
- "Tekrar Sipariş Ver" (sepete aynı ürünleri ekler)

### 9.4 Favorilerim
- Beğendiğin ürünler
- Direkt sepete ekle veya çıkar

### 9.5 Sonra Al
- Sepetteyken "Sonra Al" dedikleri buraya gider
- Düşmemesi için
- Tek tıkla geri sepete

### 9.6 Puan ve Sadakat
- 🏆 **Seviye rozeti** (Yeni / Sadık / VIP)
- **Mevcut puan bakiyesi**
- **Son hareketler** (kazandı / kullandı / expire)
- "Puan nasıl kazanılır?" açıklama bloğu

### 9.7 İade Talepleri
- Açılmış iade istekleri durumu
- Admin yanıtı

### 9.8 Bülten Tercihleri
- Email bülten aç/kapat
- SMS bildirim aç/kapat

### 9.9 Karşılaştırma
- Karşılaştırmaya eklediğin ürünler (max 4)
- Yan yana özellikler tablosu

---

## 10. İçerik Sayfaları

### 10.1 Blog
**URL:** `/blog`
- Yazı listesi (sayfalama)
- Kategori filtresi
- Yazıya tıklayınca `/post/{slug}`

### 10.2 Blog Yazısı
- Başlık, görsel, içerik (HTML)
- Sosyal paylaşım butonları
- Yazar, tarih
- Yorumlar (üyelik gerektirir)
- İlgili yazılar

### 10.3 İletişim
**URL:** `/contact`
- Adres, telefon, email
- Google Maps embed (varsa)
- İletişim formu
- Çalışma saatleri

### 10.4 Statik Sayfalar
**URL:** `/page/{slug}`
- Hakkımızda
- Mesafeli Satış Sözleşmesi
- Gizlilik Politikası
- KVKK Aydınlatma
- Çerez Politikası
- Sıkça Sorulan Sorular
- ... (admin'den eklenebilir)

### 10.5 Bültene Abone
- Anasayfada veya footer'da form
- Email + KVKK onay
- Onay maili (double opt-in)
- İptal: email içinde "Aboneliği iptal et" → `/unsubscribe`

---

# BÖLÜM C — YÖNETİM PANELİ (Admin)

## 11. Admin Girişi

**URL:** `/admin_panel/login.php` veya doğrudan `/admin_panel/`

- Admin kullanıcı email + şifre
- Yanlış giriş loglanır (`admin_login_attempts` tablosu)
- Başarılı girişten sonra Dashboard'a yönlenir

> ⚠️ Admin şifrenizi **3 ayda bir** değiştirin. En az 12 karakter, karışık.

---

## 12. Komuta Merkezi (Dashboard)

**URL:** `/admin_panel/dashboard.php`

### 12.1 Üst KPI Kartları
| Kart | Anlam |
|---|---|
| 💰 **Bugün Ciro** | Bugünkü onaylanmış sipariş toplamı (+ dünle kıyas) |
| 📦 **Bugün Sipariş** | Bugünkü sipariş adedi |
| 🛒 **AOV** | Ortalama sepet tutarı (son 30 gün) |
| 📈 **Dönüşüm Oranı** | Ziyaretçi → sipariş oranı |

### 12.2 Grafikler
- **7 günlük ciro trendi** (çizgi grafik)
- **Aylık satış** (bar grafik)
- **Ödeme yöntemi dağılımı** (pasta)

### 12.3 Uyarı Paneli
- 🔴 Stoğu kritik ürün sayısı → tıkla, liste aç
- 🟡 Bekleyen sipariş sayısı
- 🟡 Onay bekleyen yorum sayısı
- 🟡 Okunmamış müşteri mesajı

### 12.4 Hızlı Eylemler (sol üstte)
- ➕ **Ürün Ekle** → ürün ekleme formuna direkt
- 📦 **Bekleyen Siparişler** → onay bekleyen liste
- ⭐ **Bekleyen Yorumlar** → moderation kuyruğu

### 12.5 Son Aktivite Tablosu
- Son siparişler (sip no, müşteri, tutar, durum)
- Hızlı görüntüle linki

---

## 13. Sidebar Navigasyon

Sol kenarda 6 ana menü grubu vardır:

| Grup | İçerik |
|---|---|
| 📊 **Satış** | Siparişler, Kuponlar, Terk Sepet, POS |
| 📈 **Analiz** | Satış Raporu, Ürün Performansı, Kupon Raporu |
| 📦 **Katalog** | Ürünler, Kategoriler, Medya |
| 📝 **İçerik & SEO** | Sayfalar, Blog, Banner, SEO, Yönlendirme |
| 👥 **Müşteri** | Müşteriler, Yorumlar, Mesajlar, Bülten, Sadakat |
| ⚙️ **Sistem** | Ayarlar, Araçlar, Bildirim Logları |

**Grup başlığına tıklayınca** alt menü açılır (accordion).

**Aktif sayfa** otomatik vurgulanır.

**Badge'ler** sayı gösterir (örn: 5 bekleyen sipariş).

---

## 14. Satış Yönetimi

### 14.1 Siparişler Listesi
**URL:** `/admin_panel/orders/list.php`

**Filtreler:**
- Durum (Beklemede, Onaylandı, Hazırlanıyor, Kargoda, Teslim, İptal, İade)
- Tarih aralığı
- Ödeme yöntemi
- Arama (sipariş no, müşteri ad, email)

**Tablo sütunları:**
- Sip no, Müşteri, Tarih, Tutar, Ödeme, Durum, Eylemler

**Toplu işlemler** (üstteki checkbox'lar):
- Toplu Kargoya Ver
- Toplu Teslim İşaretle
- Toplu İptal
- Toplu Email Tekrar Gönder
- CSV İndir

### 14.2 Sipariş Detay
**URL:** `/admin_panel/orders/view.php?id=X`

**Bölümler:**
- 👤 Müşteri bilgileri (ad, email, telefon, "Müşteri Profili" linki)
- 📍 Teslimat + Fatura adresleri
- 📦 Ürün listesi (görsel, ad, adet, fiyat)
- 💰 Tutar dökümü (ara toplam, kargo, indirim, KDV, toplam)
- 🎟️ Kullanılan kupon (varsa)
- 💳 Ödeme bilgisi (yöntem, taksit, durum)
- 🎁 Hediye notu (varsa)
- 📜 **Sipariş geçmişi** (her durum değişikliği zaman damgalı)
- 📝 **İç notlar** (sadece admin görür)

**Eylemler:**
- ➡️ Durumu güncelle (dropdown)
- 📧 Email tekrar gönder
- 📱 SMS gönder (SMS aktifse)
- 📄 PDF fatura indir (e-fatura entegrasyonu varsa)
- 🚚 Kargo takip no gir
- 💸 Para iade et (kısmi veya tam)

### 14.3 Kuponlar
**URL:** `/admin_panel/coupons.php`

**Liste:** Aktif kuponlar, kullanım sayısı, geçerlilik

**Yeni kupon ekle:**
- Kod (örn: HOSGELDIN10)
- Tip: Yüzde / Sabit tutar / Ücretsiz kargo
- Değer
- Minimum sepet tutarı
- Maksimum kullanım (toplam ve kişi başı)
- Geçerlilik tarih aralığı
- Sadece üyelere / herkese
- Belli ürün/kategori için mi
- Etiket (otomatik / manuel / kampanya)

### 14.4 Terk Edilmiş Sepetler
**URL:** `/admin_panel/abandoned-carts.php`

**Liste:** Sepete eklemiş ama tamamlamamış kullanıcılar:
- Müşteri (üye veya email yakalanmış misafir)
- Sepet içeriği + tutar
- Son aktivite zamanı
- Gönderilen hatırlatma email'leri (1/2/3)

**Eylemler:**
- Manuel hatırlatma gönder
- İndirim kuponu üret + gönder
- Notlandır

### 14.5 POS (Satış Noktası)
**URL:** `/admin_panel/pos.php`

**Ne işe yarar:** Yüz yüze satış (mağazada) için hızlı sipariş oluşturma.

- Müşteri seç (mevcut veya yeni)
- Ürün ekle (arama veya barkod)
- Adet, indirim
- Ödeme: Nakit / Kart / Havale
- Onayla → sistem normal sipariş gibi kaydeder

---

## 15. Analiz & Raporlar

### 15.1 Satış Raporu
**URL:** `/admin_panel/reports/sales.php`

**Filtreler:** Tarih aralığı

**Gösterilen:**
- Toplam ciro, sipariş, AOV
- Günlük breakdown tablosu
- Ödeme yöntemi dağılımı
- Sehir/bölge bazlı satış
- En çok satan saatler (ısı haritası)

**Eylem:** CSV dışa aktar

### 15.2 Ürün Performansı
**URL:** `/admin_panel/reports/products.php`

**Gösterilen:**
- En çok satan 50 ürün (adet ve ciro)
- En çok görüntülenen
- En çok sepete eklenip terk edilen
- Stok devir hızı

**Eylem:** CSV dışa aktar

### 15.3 Kupon Raporu
**URL:** `/admin_panel/reports/coupons.php`

**Gösterilen:**
- Hangi kupon kaç kez kullanılmış
- Toplam indirim tutarı
- ROI (kuponla satılan / kupon değeri)

---

## 16. Katalog Yönetimi

### 16.1 Ürün Listesi
**URL:** `/admin_panel/products/list.php`

**Filtreler:** Kategori, durum (aktif/pasif), stok durumu, arama

**Sütunlar:** Görsel, Ad, SKU, Kategori, Fiyat, Stok, Durum, Eylemler

**Toplu eylemler:** Aktif yap, pasif yap, sil, kategori ata, CSV indir

### 16.2 Ürün Ekleme/Düzenleme
**URL:** `/admin_panel/products/edit.php` (yeni) veya `?id=X` (düzenle)

**Bölümler:**

#### A. Temel Bilgiler
- Ad, slug (otomatik), kategori, marka, SKU

#### B. Açıklama
- Kısa açıklama (liste sayfasında)
- Uzun açıklama (HTML editör)

#### C. Fiyat ve Stok
- Liste fiyatı, indirimli fiyat (boş bırak = indirimsiz)
- Stok adedi (varyasyonsuzsa)
- Düşük stok eşiği (genel ayarı override)
- KDV oranı

#### D. Görseller
- Sürükle bırak veya tıkla yükle
- Ana görsel (ilk olan)
- Sıralama (sürükle)
- Alt text (SEO için önemli)

#### E. Varyasyonlar
- Boyut, koku, renk gibi seçenekler
- Her varyasyon: SKU, ek fiyat, stok

#### F. SEO
- Meta başlık, meta description
- Open Graph görsel
- Canonical URL

#### G. SSS (FAQ)
- Soru-cevap çiftleri
- PDP'de accordion görünür

#### H. Diğer
- "Öne çıkan" işareti (anasayfa için)
- Yayın durumu (Taslak / Yayında / Pasif)

### 16.3 Stok Yönetimi
**URL:** `/admin_panel/products/stock.php`

- Tüm ürünlerin stok özeti
- Kritik stok listesi
- Stok girişi/çıkışı log'u
- Manuel stok düzeltme

### 16.4 Toplu Güncelleme
**URL:** `/admin_panel/products/bulk-update.php`

**Akış:**
1. CSV şablonu indir (sütunlar: sku, price, stock)
2. Excel'de düzenle
3. Yükle
4. **Önce "Dry Run"** → ne değişecek görmek için
5. Hata yoksa **"Commit"** → atomic uygula

### 16.5 Çöp Kutusu
**URL:** `/admin_panel/products/trash.php`

- Silinen (soft-delete) ürünler
- 30 gün içinde geri yüklenebilir
- Sonra otomatik temizlenir (cron)

### 16.6 Kategoriler
**URL:** `/admin_panel/categories.php`

- Hiyerarşik liste (ana > alt > alt-alt)
- Sürükle bırak ile sıra değiştirme
- Yeni kategori ekle:
  - Ad, slug, üst kategori
  - Görsel, ikon
  - SEO başlık/açıklama
  - Sıra
  - Aktif/pasif

### 16.7 Medya Kütüphanesi
**URL:** `/admin_panel/media/library.php`

- Tüm yüklenmiş görseller/dosyalar
- Filtreleme: tip, tarih, kullanım yeri
- Toplu seçim, silme
- "Bu görsel nerede kullanılıyor" referans listesi

### 16.8 Medya Çöp Kutusu
**URL:** `/admin_panel/media/trash.php`

- Silinen medya 30 gün burada
- Geri yükle veya kalıcı sil

---

## 17. İçerik & SEO Yönetimi

### 17.1 Sayfalar
**URL:** `/admin_panel/pages/list.php`

- Statik sayfalar (hakkımızda, KVKK vb.)
- Yeni sayfa ekle:
  - Başlık, slug, içerik (HTML editör)
  - Şablon (sade / iletişim / SSS)
  - SEO alanları
  - Menüde göster?

### 17.2 Blog
**URL:** `/admin_panel/blog/posts.php`

**Yazı ekleme:**
- Başlık, slug, kategori, etiketler
- Kapak görseli
- Özet (kart için)
- İçerik (HTML editör)
- Yayın tarihi (ileri tarih = zamanlanmış)
- Yazar
- SEO

### 17.3 Blog Kategorileri
**URL:** `/admin_panel/blog/categories.php`

- Kategori adı, slug, açıklama

### 17.4 Blog İçeri Aktarım
**URL:** `/admin_panel/blog/import.php`

- WordPress XML import
- CSV import

### 17.5 Bannerlar
**URL:** `/admin_panel/banners.php`

- Hero slider içeriği
- Banner ekle:
  - Görsel (önerilen 1920x600px)
  - Başlık + alt başlık
  - CTA tuş metni + linki
  - Aktif tarih aralığı
  - Sıra
  - Hedef sayfa (anasayfa, kategori, ürün)

### 17.6 SEO Yöneticisi
**URL:** `/admin_panel/seo_manager.php`

- Sitenin genel SEO ayarları
- Robots.txt düzenle
- Sitemap.xml URL (otomatik üretilir, manuel yenile butonu)
- Open Graph defaults
- Schema.org kurum bilgileri

### 17.7 URL Yönlendirme (Redirects)
**URL:** `/admin_panel/redirects.php`

- 301/302 yönlendirme kuralları
- Eski URL → yeni URL
- Slug değişikliklerinden sonra SEO kaybı önler

---

## 18. Müşteri Yönetimi

### 18.1 Müşteri Listesi
**URL:** `/admin_panel/customers/list.php`

**Filtreler:** Seviye, kayıt tarihi, son alışveriş

**Sütunlar:** Ad, Email, Telefon, Sipariş Sayısı, LTV, Seviye

### 18.2 Müşteri Detay (360°)
**URL:** `/admin_panel/customers/view.php?id=X`

**Tabs:**
- 📋 **Profil** — temel bilgi, KVKK durumu
- 🛒 **Siparişler** — geçmiş + tutar
- ❤️ **Favoriler**
- ⭐ **Yorumlar**
- 📍 **Adresler**
- 🎟️ **Kullandığı kuponlar**
- 🏆 **Puan geçmişi** + bakiye
- 📱 **SMS log** — gönderilen tüm SMS'ler
- 📧 **Email log** — opsiyonel
- 📊 **Davranış** — son ziyaret, en çok baktığı ürünler

**Admin eylemleri:**
- Şifre sıfırlama linki gönder
- Manuel puan ekle/çıkar
- Özel kupon üret
- Hesabı askıya al
- KVKK silme talebi varsa anonimleştir

### 18.3 Müşteri Düzenleme
**URL:** `/admin_panel/customers/edit.php?id=X`

- Profil bilgileri (admin tarafından düzeltme)
- Doğum tarihi düzeltme (admin yapabilir, müşteri yapamaz)
- Email değiştirme

### 18.4 Yorumlar
**URL:** `/admin_panel/reviews.php`

**Filtre:** Onay bekleyen / yayında / reddedilmiş

**Eylem:**
- Onayla (yayına çıkar)
- Reddet (sil)
- Düzenle (uygunsuz kelime temizle)
- Müşteriye cevap yaz

### 18.5 Yorumlar (Eski tablo)
**URL:** `/admin_panel/comments.php`
- Eski yorum sistemi, blog yorumları gibi

### 18.6 Müşteri Mesajları
**URL:** `/admin_panel/messages.php`

İletişim formundan gelen mesajlar:
- Okunmamış sayısı badge
- Cevap yaz (otomatik email gider)
- Kategorize et, etiketle, arşivle

### 18.7 Bülten Aboneleri
**URL:** `/admin_panel/newsletter/subscribers.php`

- Aboneler listesi
- Onay durumu (double opt-in)
- Toplu CSV indir
- Manuel ekle

### 18.8 Bülten Kampanyaları
**URL:** `/admin_panel/newsletter/campaigns.php`

- Yeni kampanya:
  - Konu satırı (AI öneri tuşu var — Anthropic API açıksa)
  - HTML editör veya hazır şablon
  - Hedef segment (tümü, VIP, yeni üye vb.)
  - Test gönder (kendi adresinize)
  - Zamanlama
  - Gönder
- Açılma/tıklama istatistikleri

### 18.9 Sadakat - Müşteriler
**URL:** `/admin_panel/loyalty/customers.php`

- Sadakat programındaki müşteriler
- Seviye, puan, son aktivite

### 18.10 Sadakat - İşlemler
**URL:** `/admin_panel/loyalty/transactions.php`

- Tüm puan hareketleri (kazan/harca/expire/iade)
- Filtre: müşteri, tarih, işlem tipi

---

## 19. Sistem Ayarları

**Hub URL:** `/admin_panel/settings.php` veya `/admin_panel/settings/index.php`

5 ana kategori kartı:

### A. 🏪 Mağaza Kimliği (`settings/identity.php`)
- Genel: site adı, slogan, logo, favicon, topbar
- İletişim: telefon, email, fax
- Konum: adres, harita koordinatı
- Çalışma saatleri
- Sosyal medya linkleri

### B. 📢 Pazarlama (`settings/marketing.php`)
- SEO defaults
- Newsletter ayarları
- Exit-intent
- Banner ayarları

### C. 🛒 Ticaret (`settings/commerce.php`)
- Para birimi, KDV
- Kargo: ücretsiz eşik, fiyatlar
- Stok uyarısı eşikleri
- Kupon ayarları
- Sadakat programı

### D. 📊 Analitik (`settings/analytics.php`)
- GA4 Measurement ID
- GTM Container ID
- Meta Pixel ID + CAPI Token
- Microsoft Clarity ID
- Master "Analitik aktif" switch'i

### E. 🧩 Entegrasyonlar (`settings/integrations.php`)
- iyzico (API key, secret, mode)
- SMTP (email gönderim)
- SMS (NetGSM / İletiMerkezi)
- WhatsApp Business
- Anthropic AI API
- Şirket / vergi bilgileri
- Banka hesapları (havale için)
- KVKK metinleri

> Detay için → [BÖLÜM D — DETAYLI AYARLAR](#bölüm-d--detaylı-ayarlar)

---

## 20. Araçlar (Bakım)

### 20.1 SQL Migration Yöneticisi
**URL:** `/admin_panel/tools/migrations.php`

- Bekleyen migration'ları listeler
- Çalıştırılmışları "tamamlandı" işareti ile gösterir
- Tek tıkla çalıştırma
- Güvenlik analizi (INSERT/UPDATE/DELETE sayısı önceden)

### 20.2 Görsel Yeniden Boyutlandırma
**URL:** `/admin_panel/tools/resize_images.php`

- Eski yüklenmiş büyük görselleri toplu küçültür
- WebP'ye çevirir
- Disk alanı kazandırır

### 20.3 Reset Aracı
**URL:** `/admin_panel/tools/reset.php`

> ⚠️ **TEHLİKELİ** — Demo verileri temizler. Üretimde **asla** kullanmayın.

---

# BÖLÜM D — DETAYLI AYARLAR

## 21. Mağaza Kimliği

**URL:** `/admin_panel/settings/identity.php`

### 21.1 Genel
- **Site Adı:** Header'da, email'lerde, SEO'da kullanılır
- **Slogan/Tagline:** Site adının altında
- **Logo:** Header'daki ana logo (PNG, transparan arka plan önerilir)
- **Favicon:** Tarayıcı sekmesindeki ikon (32x32, ICO veya PNG)
- **Topbar:** En üstteki ince çubuk (telefon vs.) açık/kapalı

### 21.2 İletişim
- Telefon (footer + iletişim sayfası)
- WhatsApp (yeşil floating buton için)
- Email
- Fax (kullanmıyorsanız boş bırakın)

### 21.3 İşletme Konumu
- Adres metni
- Enlem/boylam (Google Maps embed için)
- Harita yüksekliği

### 21.4 Çalışma Saatleri
- Pazartesi-Cuma: 09:00-18:00
- Cumartesi: 10:00-16:00
- Pazar: Kapalı
- ... (her gün için)

### 21.5 Sosyal Medya
- Instagram, Facebook, X, YouTube, Pinterest, TikTok URL'leri
- Boş bırakılırsa o icon gösterilmez

---

## 22. Pazarlama

**URL:** `/admin_panel/settings/marketing.php`

### 22.1 SEO Defaults
- Site genel meta başlık template'i
- Site genel meta description
- Open Graph görsel (paylaşımda görünen)
- Twitter Card türü

### 22.2 Newsletter
- Aktif / pasif
- Onay zorunlu mu (double opt-in)
- Hoşgeldin email metni
- Otomatik abone yapma (kayıt formunda)

### 22.3 Exit-intent Kuponu
- Aktif / pasif
- Hangi kupon kodunu göstereceği
- Minimum saniye (varsayılan 15)
- Cookie ömrü (varsayılan 30 gün)

---

## 23. Ticaret

**URL:** `/admin_panel/settings/commerce.php`

### 23.1 Para Birimi & KDV
- Para birimi (TL varsayılan)
- KDV oranı (varsayılan %20)
- Fiyatlar KDV dahil mi?

### 23.2 Kargo
- **Ücretsiz kargo eşiği:** Bu tutar üstü kargo bedava
- Standart kargo bedeli
- Hızlı kargo bedeli (varsa)
- Kargo firmaları (Yurtiçi, MNG, Aras vb.)

### 23.3 Stok Uyarısı
- **Düşük stok eşiği:** Bu sayının altında "Az kaldı" göster (varsayılan 5)
- **Stoğu kritik eşik:** Admin'e email gitsin (varsayılan 3)
- Admin email adresi

### 23.4 Kupon Politikası
- Birden fazla kupon kullanılabilir mi?
- İndirimli ürünlerde kupon geçerli mi?

### 23.5 Sadakat Programı
- **Aktif/pasif**
- **Kazanma oranı:** 1₺ = X puan
- **Kullanma değeri:** X puan = 1₺
- **Süresi:** Puan kaç ay sonra silinir (varsayılan 12)
- **Seviye eşikleri:**
  - Sadık: yıllık X₺ harcama
  - VIP: yıllık X₺ harcama
- Seviye ayrıcalıkları (kupon, kargo, doğum günü)

---

## 24. Analitik

**URL:** `/admin_panel/settings/analytics.php`

### 24.1 Master Switch
- **"Analitik Aktif"** — Tek bir kutuyla tüm tracking aç/kapa

### 24.2 Google Analytics 4
- Measurement ID (G-XXXXXXXXXX)

### 24.3 Google Tag Manager
- Container ID (GTM-XXXXXXX)
- Aktifse GTM kullanılır, GA4 doğrudan kapatılır (çift sayım önler)

### 24.4 Meta Pixel
- Pixel ID (16 hane)

### 24.5 Meta Conversion API
- Access Token
- "CAPI Aktif" checkbox
- Test Event Code (test sırasında)

### 24.6 Microsoft Clarity
- Project ID

### 24.7 Cookie / Consent
- Banner gösterilsin mi?
- Banner pozisyonu (alt / üst)
- Banner metni

---

## 25. Entegrasyonlar

**URL:** `/admin_panel/settings/integrations.php`

### 25.1 iyzico (Ödeme)
- API Key
- Secret Key
- Mode: Sandbox (test) / Production (canlı)
- 3D Secure aktif mi?
- Taksit ayarları (kaç ay ve hangi bankalar)

### 25.2 SMTP (Email)
- Host (örn: mail.kendidomain.com)
- Port (587, 465)
- Kullanıcı / şifre
- TLS / SSL
- Gönderen ad / email
- **Test maili gönder** tuşu

### 25.3 SMS Sağlayıcı
- Sağlayıcı: NetGSM veya İletiMerkezi
- Kullanıcı, şifre
- Gönderen başlığı (onaylı)
- "SMS Aktif" checkbox
- **Test SMS gönder** tuşu

### 25.4 WhatsApp Business
- API token (Meta WA Business)
- Telefon numarası
- Otomatik mesaj şablonları

### 25.5 Anthropic AI
- API Key
- Model seçimi (Sonnet / Opus)
- Kullanım alanı:
  - Newsletter konu satırı öneri
  - Ürün açıklama yardımı
  - Yorum özet

### 25.6 Şirket / Yasal & Mali
- Şirket adı, vergi no, vergi dairesi
- MERSİS no
- Ticaret sicil no
- KEP adresi
- KVKK Aydınlatma metni (HTML)
- Mesafeli Satış Sözleşmesi (HTML)
- Ön Bilgilendirme Formu (HTML)
- Çerez Politikası (HTML)

### 25.7 Banka Hesapları (Havale için)
- Banka adı, hesap sahibi, IBAN
- Birden fazla banka eklenebilir

---

# BÖLÜM E — İŞ AKIŞLARI

## 26. Yeni Ürün Ekleme Akışı

1. **Admin → Katalog → Ürünler → "Yeni Ürün"**
2. **Temel bilgiler:** Ad, slug, kategori
3. **Görseller:** Ana görsel + galeri (en az 1080x1080 önerilir)
4. **Fiyat & stok:** Liste fiyatı, varsa indirim, stok adedi
5. **Açıklama:** Kısa + uzun açıklama (alt-text'leri görsel için unutma!)
6. **Varyasyon (varsa):** Boyut/koku gibi seçenekleri ekle, her birinin SKU/stoğu
7. **SEO:** Meta başlık + description, alt-text
8. **SSS (FAQ):** En az 3 sıkça sorulan soru ekle
9. **Yayın durumu:** "Yayında" işaretle
10. **Kaydet**
11. **Test:** Storefront'ta görüntüle → düzgün mü?

**İpucu:** İlk ürünü eklerken adım adım gidin, sonra benzer ürünleri "Kopyala" özelliği ile hızlı oluşturabilirsiniz.

---

## 27. Günlük Sipariş İşleme

**Sabah rutini (15 dk):**

1. **Dashboard → "Bekleyen Siparişler"** sayısına bak
2. **Siparişler → "Beklemede"** filtresi seç
3. Her siparişi aç:
   - Adres düzgün mü (yanlış yazım var mı)?
   - Ödeme onaylandı mı?
4. **Bulk işlem ile** seç → "Onayla" (durum: Hazırlanıyor)
5. Müşteriye otomatik email gider
6. **Depo/üretim** süreci başlar

**Kargolama (öğleden sonra):**

1. Hazırlanan siparişleri seç
2. **"Toplu Kargoya Ver"** tuşu
3. Kargo firmasına teslim et, takip no al
4. Her siparişe takip no gir
5. Otomatik SMS + email gider müşteriye

**Teslim sonrası:**

- Kargo firması "teslim edildi" raporu gönderince admin'de durumu "Teslim" yap
- Bu işlem **otomatik puan ekler** (Sadakat aktifse)

---

## 28. Kampanya Başlatma

### A. İndirim Kuponu Kampanyası
1. **Admin → Satış → Kuponlar → "Yeni Kupon"**
2. Kod: KIS2026 (vb.)
3. Tip: %10 veya 50₺
4. Min sepet: 200₺
5. Geçerlilik: 1 hafta
6. Maks kullanım: 500 (toplam) / 1 (kişi başı)
7. **Kaydet**
8. **Bülten kampanyası** oluştur, kupon kodunu içeren mail at

### B. Anasayfa Banner Kampanyası
1. **Admin → İçerik → Bannerlar → "Yeni"**
2. Görsel hazırla (1920x600 önerilir)
3. Başlık, CTA metni, hedef link
4. Yayın tarihi → sona erme
5. **Kaydet** → anasayfada otomatik görünür

### C. Newsletter Patlatma
1. **Admin → Müşteri → Bülten → Kampanyalar → "Yeni"**
2. **AI konu önerici** (varsa) çalıştır
3. Hedef: tüm aktif aboneler
4. **Test gönder** kendine
5. Onayla → Gönder

---

## 29. Stok Yönetimi

**Haftalık rutin:**

1. **Dashboard** → "Stoğu kritik ürün" sayısı kaç?
2. **Katalog → Stok** sayfası → kritik listeyi gör
3. Tedarikçiye sipariş ver
4. Mal gelince **Toplu Güncelleme** ile CSV yükle (zaman kazandırır)

**Stok girişi (manuel):**
1. Ürün → Düzenle → Stok alanını güncelle
2. Kaydet (otomatik log'a yazılır)

**Varyasyonlu ürün:**
- Varyasyon sekmesinde her boyut/kokunun ayrı stoğunu güncelle

---

## 30. Müşteri Şikayeti Çözme

**Müşteri mesaj yollarsa:**

1. **Admin → Müşteri → Mesajlar** → okunmamış kuyrukta
2. Mesajı aç, oku
3. **Müşterinin profilini** aç (link)
4. Sipariş geçmişine bak, sorunu anla
5. **Cevap yaz** → otomatik email gider
6. Gerekirse **özel kupon üret** (telafi için)
7. Mesajı **"Çözüldü"** olarak işaretle

**Telefonla şikayet:**
1. Müşteriyi adı/email ile **CRM'de bul**
2. Sipariş geçmişine, yorumlarına, son aktivitesine bak
3. Çözümü uygulayıp **iç not** olarak yaz (sonra hatırlamak için)

---

## 31. Aylık Bakım Kontrolü

**Her ay 1 saat ayırın:**

- [ ] **Veritabanı yedeği** al (cPanel → Yedek)
- [ ] **Stok devir** raporuna bak → satılmayan ürünleri pasif yap veya indirime sok
- [ ] **Cron logları** kontrol et → hata var mı?
- [ ] **SMS/Email** kotanı kontrol et
- [ ] **iyzico** ödeme onay oranı (başarısız ödeme analizi)
- [ ] **GA4 → Conversion** funnel'a bak — nerede düşüş var?
- [ ] **Microsoft Clarity** → kaydedilmiş 5 oturum izle (müşteri davranışı anla)
- [ ] **Çerez politikası**, KVKK metni güncel mi?
- [ ] **Sayfa hızı testi** (PageSpeed Insights)
- [ ] **Eski medya** çöp kutusunu temizle
- [ ] **Admin şifresi** 3 ayda bir değiş

---

# BÖLÜM F — TEKNİK & BAKIM

## 32. Cron Job Listesi

cPanel → Cron Jobs:

| Sıklık | Komut | Görev |
|---|---|---|
| `0 8 * * *` | `php /home/USER/public_html/cron/abandoned-cart.php` | Terk sepet hatırlatma (3 aşama) |
| `0 9 * * *` | `php /home/USER/public_html/cron/low-stock-alert.php` | Düşük stok admin uyarısı |
| `0 9 * * *` | `php /home/USER/public_html/cron/birthday-coupon.php` | Doğum günü kuponu |
| `0 3 * * *` | `php /home/USER/public_html/cron/loyalty-expire.php` | Puan expire (12ay) |
| `0 2 * * 1` | `php /home/USER/public_html/cron/loyalty-tier-update.php` | Müşteri seviye güncelle |
| `*/5 * * * *` | `php /home/USER/public_html/cron/cart-reservation-cleanup.php` | Sepet rezervasyon temizleme |
| `0 4 * * *` | `php /home/USER/public_html/cron/price-drop-notify.php` | Fiyat düştü bildirimi |

> 💡 **USER** yerine cPanel kullanıcı adınızı yazın.

---

## 33. Yedekleme

### A. cPanel ile (Önerilir)
1. cPanel → **Yedekler** veya **Yedekleme Sihirbazı**
2. **Tam Yedek** veya **Yalnız Veritabanı**
3. İndir + güvenli yere kaydet (Google Drive, dış disk)

**Sıklık:** Haftalık tam yedek, günlük veritabanı yedek

### B. SSH ile (Gelişmiş)
```bash
# Veritabanı yedek
mysqldump -u USER -p DBNAME > backup_$(date +%F).sql

# Dosya yedek (zip)
tar -czf files_$(date +%F).tar.gz public_html/
```

### C. Otomatik Cloud Yedek
- UpdraftPlus (WP değil, manuel kurmak gerekir)
- Veya kendi cron'unuzla S3'e yükle

---

## 34. SQL Migration Çalıştırma

### Tercih edilen yol: Admin Panel'den
1. **Admin → Sistem → Araçlar → SQL Migration**
2. Bekleyen migration'ları gör
3. Sırayla "Çalıştır"
4. Güvenlik analizini incele
5. **Hata olursa** → çalıştırma, ekrana göster, soru sor

### Alternatif: phpMyAdmin
1. cPanel → phpMyAdmin
2. Veritabanını seç
3. SQL sekmesi
4. Migration dosyasının içeriğini yapıştır → Go

---

## 35. Hata Yönetimi

### A. Müşteri Tarafında Hata Olursa
- Standart "Bir hata oluştu" sayfası gösterilir
- **Admin girişi açıksa** → detay teknik bilgi de gösterilir
- Sunucu log'una otomatik yazılır

### B. Hata Loglarına Bakma
- cPanel → **Hatalar** veya **Error Log**
- `error_log` dosyası (genellikle `public_html/error_log`)
- Son 100 satıra bak

### C. Yaygın Hata Tipleri
| Hata | Çözüm |
|---|---|
| **500 Internal Server Error** | PHP versiyonu uyumsuz, .htaccess hatası |
| **Database connection failed** | DB credential'lar yanlış, MySQL durmuş |
| **Memory exhausted** | PHP memory_limit artır (256M) |
| **File upload too large** | upload_max_filesize artır |

---

# BÖLÜM G — SORUN GİDERME

## 36. SSS — Müşteri Tarafı

**S: Müşteri "şifremi unuttum" linkinin email'i gelmiyor?**
C: SMTP ayarları yanlış. Ayarlar → Entegrasyonlar → SMTP → test maili gönder.

**S: Mobilde sepet sayfası bozuk görünüyor?**
C: Tarayıcı önbelleği — `Cmd+Shift+R` / `Ctrl+Shift+R` ile temizle.

**S: Ödeme yapamıyor, "ödeme başarısız" diyor?**
C: iyzico mode "Sandbox"ta olabilir → Production yap. Veya kart bilgisi yanlış. iyzico panelinden hata log'una bak.

**S: Müşteri sipariş verdi ama email gelmemiş diyor?**
C: 1) SMTP doğru çalışıyor mu? 2) Spam klasörüne bak. 3) Admin → Siparişler → ilgili sipariş → "Email Tekrar Gönder".

**S: Kupon kodu çalışmıyor diyor?**
C: 1) Süresi dolmuş olabilir 2) Min sepet tutarı sağlanmamış 3) Maks kullanıma ulaşılmış 4) Sadece üyelere özel olabilir.

**S: Üyelik formu kabul etmiyor?**
C: Email zaten kayıtlı olabilir → şifremi unuttum yönlendir. Veya şifre çok zayıf.

---

## 37. SSS — Yönetim Tarafı

**S: Admin paneline giremiyorum?**
C: 1) Cookie/oturum sıfırlamak için tarayıcı önbelleğini temizle 2) URL'i `/admin_panel/login.php` olarak gir 3) Şifre sıfırla (`forgot_password.php`).

**S: Sildiğim ürün hala sitede görünüyor?**
C: Migration 101 çalıştırıldı mı? Çalıştırılmadıysa **Sistem → Araçlar → Migration**'dan çalıştır.

**S: Yeni özellik yükledim ama menüde yok?**
C: Sidebar'ı yenileyen dosyayı yüklemediniz olabilir. `admin_panel/core/header.php` veya sidebar dosyasının güncel olduğundan emin olun.

**S: Migration "UYARI" verdi, ne yapayım?**
C: Yeşil = güvenli (çalıştır). Sarı = çok satır etkiler (önce yedek al). Kırmızı = TEHLİKE, çalıştırma, soru sor.

**S: SMS göndermiyor?**
C: 1) Ayarlar → SMS → "Aktif" işaretli mi? 2) API key/şifre doğru? 3) Gönderen başlığı onaylı mı? 4) Bakiyeniz var mı? 5) SMS Log'da hata mesajına bak.

**S: Analytics veri toplamıyor?**
C: 1) Master "Analitik Aktif" switch'i açık mı? 2) GA4 ID girilmiş mi? 3) Çerez onayı verdiniz mi? 4) DebugView'da test et.

**S: Müşterinin doğum tarihini değiştiremiyorum?**
C: Müşteri kendi değiştiremez ama **admin değiştirebilir**. Admin → Müşteriler → Düzenle.

**S: Bekleyen sipariş çok ama bana bildirilmiyor?**
C: Admin email adresi ayarlarda doğru mu? Cron çalışıyor mu?

---

## 38. Acil Durum Rehberi

### 🆘 Site açılmıyor (500 hatası)
1. cPanel → File Manager → `.htaccess` dosyasını yedek aldıktan sonra geçici yeniden adlandır (`.htaccess.bak`)
2. Site açılıyor mu? Açılıyorsa .htaccess'te hata var
3. Hala açılmıyor → cPanel → Error Log → hata mesajı

### 🆘 Veritabanı bağlantısı yok
1. cPanel → MySQL Databases → DB hala var mı?
2. `config/database.php` veya `.env` → credentials doğru mu?
3. cPanel → MySQL → kullanıcı şifresini sıfırla

### 🆘 Ödeme alamıyorum
1. iyzico hesabına gir → API durumu
2. Sandbox / Production doğru mu?
3. Test kartı ile dene: `5528790000000008`, herhangi tarih, CVV 123

### 🆘 Hack / şüpheli aktivite
1. **Admin şifresini hemen değiştir**
2. cPanel → Hesap → tüm şifreleri değiştir
3. Son 7 gün **erişim log'larını** indir, incele
4. Son 7 gün **yedeği** geri yükle (kötü ise)
5. WordFence/Sucuri tarzı tarama (yoksa manuel: `core/`, `admin_panel/` klasörlerini yeni indirip kıyasla)

### 🆘 Tüm sitedeki müşterilere acil duyuru
1. Admin → Bültene → Yeni Kampanya
2. Hedef: Tümü
3. Konu: ACİL [konu]
4. Test → Gönder

---

# 📞 Yardım

Bir özelliği kullanırken takıldıysanız veya yeni geliştirme isteğiniz varsa:

✅ **Hangi sayfadasınız?** (URL veya menü yolu)
✅ **Hata varsa screenshot atın**
✅ **Beklediğiniz davranışı yazın** ("X olmasını isterdim ama Y oldu")

Bu kılavuz **yaşayan bir doküman**dır — yeni özellikler eklendikçe güncellenir.

---

**Son güncellenme:** 2026-05-19
**Versiyon:** 1.0
**Hazırlayan:** Claude (Anthropic)
