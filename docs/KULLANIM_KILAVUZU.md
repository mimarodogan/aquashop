# 📘 AQUASHOP — Yönetim Paneli Kullanım Kılavuzu

> Bu kılavuz, sitenize eklenen tüm yeni özelliklerin **ne işe yaradığını**, **nasıl ayarlanacağını** ve **müşterinin nasıl gördüğünü** açıklar. Teknik bilgi gerektirmez.

---

## 🗺️ İçindekiler

1. [Başlarken: İlk Kurulum Sırası](#1-başlarken-i̇lk-kurulum-sırası)
2. [Faz 0 — Analitik & Ölçüm](#2-faz-0--analitik--ölçüm)
3. [Faz 1 — Dönüşüm Artırıcı Özellikler](#3-faz-1--dönüşüm-artırıcı-özellikler)
4. [Faz 2 — Operasyon Otomasyonu](#4-faz-2--operasyon-otomasyonu)
5. [Faz 3 — SEO & Görünürlük](#5-faz-3--seo--görünürlük)
6. [Faz 4 — Sadakat Programı (Puan)](#6-faz-4--sadakat-programı-puan)
7. [Faz 5 — Modernleşmiş Admin Panel](#7-faz-5--modernleşmiş-admin-panel)
8. [Faz 6/7 — 2026 Trendleri](#8-faz-67--2026-trendleri)
9. [Cron Job Listesi (Otomatik Görevler)](#9-cron-job-listesi-otomatik-görevler)
10. [Hızlı Sorun Giderme (SSS)](#10-hızlı-sorun-giderme-sss)

---

## 1) Başlarken: İlk Kurulum Sırası

Yeni bir özellik aktive etmeden önce **bu sıra**yı takip edin:

### Adım 1: SQL Migration'larını Çalıştırın
**Admin → Araçlar → SQL Migration Yöneticisi** (`/admin_panel/tools/migrations.php`)

- Bekleyen tüm migration'ları sırayla "Çalıştır" tuşuna basın
- Güvenlik analizi açılırsa, görüntü kontrol edip onaylayın
- Bütün satırlar yeşil olduğunda devam edin

### Adım 2: Admin Panel → Ayarlar
Hangi özellikleri kullanacaksanız, sırasıyla aşağıdaki bölümlere gidip değerleri girin:
- 📊 **Analitik & Ölçüm** (Faz 0)
- 📱 **SMS Bildirimleri** (Faz 2)
- 🏆 **Sadakat Programı** (Faz 4)
- 🚚 **Kargo Ayarları** (ücretsiz kargo eşiği)

### Adım 3: Cron Job'ları Kur
cPanel → Cron Jobs üzerinden 9. bölümdeki listeyi ekleyin.

---

## 2) Faz 0 — Analitik & Ölçüm

### 2.1 Google Analytics 4 (GA4)
**Ne işe yarar:** Sitenizdeki her tıklama, görüntüleme, satışı Google'a gönderir. Hangi ürün satıyor, müşteri nereden geliyor, sepete ekleyip terk eden kim — hepsini görürsünüz.

**Nasıl ayarlanır:**
1. https://analytics.google.com → Yeni özellik oluştur
2. "Measurement ID" değerini kopyalayın (G-XXXXXXXXXX formatında)
3. **Admin → Ayarlar → Analitik & Ölçüm** → "GA4 Measurement ID" alanına yapıştırın
4. "Analitik aktif" kutusunu işaretleyin → **Kaydet**

**Otomatik gönderilen olaylar:**
- `view_item` — Ürün sayfası açıldığında
- `view_item_list` — Kategori/liste görüntülendiğinde
- `add_to_cart` — Sepete eklendiğinde
- `view_cart` — Sepet açıldığında
- `begin_checkout` — Ödeme adımı başladığında
- `purchase` — Sipariş tamamlandığında

**Test:** GA4 → "DebugView" → telefonla bir ürünü sepete atın, anında görmeli.

---

### 2.2 Google Tag Manager (GTM) — Opsiyonel
**Ne işe yarar:** Tek bir container ile GA4 + Pixel + Clarity + diğer tracker'ları yönetir.

**Nasıl ayarlanır:**
1. https://tagmanager.google.com → Container oluştur
2. ID'yi kopyalayın (GTM-XXXXXXX)
3. **Ayarlar → Analitik** → "GTM Container ID" alanına yapıştırın
4. **Kaydet**

> ⚠️ GTM kullanıyorsanız GA4 ve Pixel'i de **GTM içine** kurun. Hem bizim sistem hem GTM göndermesin (çift sayım olur).

---

### 2.3 Meta (Facebook) Pixel
**Ne işe yarar:** Reklam yapacaksanız, Meta'nın hangi reklamın satış getirdiğini anlamasını sağlar.

**Nasıl ayarlanır:**
1. https://business.facebook.com → Events Manager → Pixel oluştur
2. 16 haneli Pixel ID'yi kopyalayın
3. **Ayarlar → Analitik** → "Meta Pixel ID" alanına yapıştırın → **Kaydet**

---

### 2.4 Meta Conversion API (CAPI) — Önerilir
**Ne işe yarar:** iOS 14+ kullanıcılarında Pixel takip yapamayınca, **sunucudan** Meta'ya satış bildirir. Kayıp dönüşümleri kurtarır.

**Nasıl ayarlanır:**
1. Events Manager → Pixel ayarları → "Conversions API" sekmesi
2. "Access Token" oluştur, kopyala
3. **Ayarlar → Analitik** → "Meta CAPI Token" alanına yapıştırın
4. "CAPI aktif" kutusunu işaretleyin → **Kaydet**

> Test için Events Manager'da "Test Events" sekmesini kullanın.

---

### 2.5 Microsoft Clarity — Opsiyonel ama Çok Yararlı
**Ne işe yarar:** Tamamen **ücretsiz** ısı haritası + oturum kayıt. Müşterinin sayfada nereye tıkladığını **video gibi** izlersiniz.

**Nasıl ayarlanır:**
1. https://clarity.microsoft.com → Yeni proje oluştur
2. Project ID'yi kopyalayın
3. **Ayarlar → Analitik** → "Clarity Project ID" alanına yapıştırın → **Kaydet**

---

### 2.6 KVKK Uyumlu Cookie Onay
**Otomatik:** Tüm tracker'lar yalnızca kullanıcı çerezleri kabul edince yüklenir. Reddederse hiçbir takip yapılmaz. Google Consent Mode v2 standardı.

**Müşteri görünümü:** İlk ziyarette sayfa alt köşesinde "Çerez tercihleri" kutusu.

---

### 2.7 Sunucu Tarafı Sayfa Görüntüleme Takibi
**Ne işe yarar:** GA4 dışında, **bot filtreli** kendi sayfa görüntüleme tablonuz. Bu sayede sunucu tarafında "en çok izlenen ürün" listesi yapılabilir.

**Otomatik:** Hiçbir ayar gerekmez. Arka planda çalışır.

---

### 2.8 Dashboard'da Raporlar
**Nerede:** Admin → Dashboard

Otomatik gösterilen KPI'lar:
- Bugün / 7 gün / 30 gün ciro karşılaştırması
- Ortalama sepet (AOV)
- Dönüşüm oranı
- Sepet terk oranı
- Top 10 ürün
- Ödeme yöntemi dağılımı
- Kupon performansı

**Detaylı raporlar:** Admin → Raporlar
- Satış Raporu (`/admin_panel/reports/sales.php`)
- Ürün Performansı (`/admin_panel/reports/products.php`)
- Kupon Raporu (`/admin_panel/reports/coupons.php`)

Hepsi tarih aralığı seçilebilir + CSV dışa aktarım yapılabilir.

---

## 3) Faz 1 — Dönüşüm Artırıcı Özellikler

### 3.1 "Sadece N Tane Kaldı" Rozeti
**Ne işe yarar:** Stok azaldığında müşteriye kıtlık hissi verir.

**Nerede görünür:** Ürün detay sayfası + ürün listeleme kartları.

**Ayar:** **Ayarlar → Stok Uyarısı** → "Düşük Stok Eşiği" (varsayılan 5).

**Akıllı davranış:** Varyasyonlu ürünlerde her varyasyon kendi stoğuna göre rozeti gösterir.

---

### 3.2 Sosyal Kanıt: "Son 24 Saatte X Satıldı"
**Ne işe yarar:** "🔥 Son 24 saatte 12 satıldı · 👁 Şu an 8 kişi inceliyor" mesajı gösterir. Güven artırır.

**Nerede görünür:** Ürün detay sayfasında başlığın altında.

**Otomatik eşik:** Çok az satış varsa hiç göstermez (komik düşmesin diye).

**Ayar:** Otomatik, ayar gerekmez. Şu eşikleri kullanır:
- En az 3 satış → "X satıldı" gösterir
- En az 2 aktif görüntüleyici → "Y kişi inceliyor" gösterir

---

### 3.3 Ücretsiz Kargo Progress Bar
**Ne işe yarar:** Sepet açıldığında "Ücretsiz kargoya 45₺ kaldı" çubuğu gösterir. Müşteri eşiği geçmek için bir ürün daha eklemek ister.

**Nerede görünür:** Sepet sayfasının en üstü.

**Ayar:** **Ayarlar → Kargo** → "Ücretsiz Kargo Eşiği" (₺ cinsinden).

---

### 3.4 "Bunlarla Harika Gider" (Cross-Sell)
**Ne işe yarar:** Gerçek satış verisinden öğrenir. "X alanların %40'ı Y'yi de almış" mantığı (collaborative filtering).

**Nerede görünür:** Sepet sayfasının altında 4'lü grid.

**Otomatik:** Sipariş geçmişi olduğunda çalışır. Az veri varsa "Çok Satanlar" gösterir.

---

### 3.5 Sepet Terk Email Akışı
**Ne işe yarar:** Müşteri sepete ürün eklemiş ama satın almamışsa, **3 aşamalı email** gönderir:
- **24 saat sonra:** "Sepetinizi unuttunuz mu?" (hatırlatma)
- **72 saat sonra:** "Size özel %5 indirim kuponu" (otomatik kupon)
- **7 gün sonra:** "Son şans!" (final mesaj)

**Ayar:** Cron job'u kurun (Bölüm 9). Email şablonları `includes/order_mailer.php` içinde, düzenleyebilirsiniz.

**Müşteri kapsamı:** Sadece **üye olan** müşterilere gider (email biliyoruz).

---

### 3.6 Exit-Intent Kupon Modal
**Ne işe yarar:** Misafir kullanıcı sayfadan ayrılmak üzereyken (fareyi yukarı çekince veya geri tuşuna basınca) "Size özel %5 indirim" pop-up'ı çıkar.

**Kurallar:**
- Sadece **misafir** (üye olmayan) kullanıcıya gösterilir
- Sayfada en az **15 saniye** geçmiş olmalı
- **30 günde 1 kez** gösterilir (cookie ile)
- Mobile'da scroll-back (yukarı kaydırma) ile tetiklenir

**Ayar:** **Ayarlar → Pazarlama** → "Exit-intent kupon aktif" + kupon kodu seç.

---

## 4) Faz 2 — Operasyon Otomasyonu

### 4.1 SMS Bildirimleri
**Ne işe yarar:** Müşteriye sipariş onayı, kargo çıkış, teslim SMS'i otomatik gider.

**Nasıl ayarlanır:**
1. **NetGSM** veya **İletiMerkezi**'den hesap açın
2. Onaylı gönderen başlığınız hazır olmalı (örn: "AQUASHOP")
3. **Ayarlar → SMS Bildirimleri:**
   - Sağlayıcı seç (NetGSM / İletiMerkezi)
   - API kullanıcı adı / şifre
   - Onaylı başlık
   - "SMS aktif" işaretle → **Kaydet**

**Otomatik gönderilen mesajlar:**
- Sipariş onaylandığında → "Siparişiniz alındı"
- Kargoya verildiğinde → "Siparişiniz kargoda, takip no: XYZ"
- Teslim edildiğinde → "Siparişiniz teslim edildi"

**SMS Log:** Admin → Bildirim Logları → SMS Log (tüm gönderiler buradan görülür).

---

### 4.2 Toplu Sipariş İşlemleri
**Nerede:** Admin → Siparişler

**Ne işe yarar:** Birden fazla siparişi seçip aynı anda:
- Toplu kargoya ver
- Toplu teslim işaretle
- Toplu iptal et
- Toplu onay maili tekrar gönder

**Nasıl kullanılır:** Sol checkbox'ları seçin → üstteki "Toplu İşlem" menüsünden eylem seçin.

---

### 4.3 Müşteri 360° Görünüm
**Nerede:** Admin → Müşteriler → bir müşteriye tıkla

**Gösterilenler:**
- LTV (toplam harcadığı tutar)
- Tüm sipariş geçmişi
- Yorumları
- Favorileri
- Adresleri
- Kullandığı kuponlar
- Puan bakiyesi + hareket geçmişi
- Sadakat seviyesi (Yeni / Sadık / VIP)

**Kullanım:** VIP müşteriye özel kampanya planlamak, şikayet eden müşteriyi anlamak için bire bir.

---

### 4.4 Toplu CSV Ürün/Fiyat/Stok Güncelleme
**Nerede:** Admin → Ürün Yönetimi → Toplu Güncelleme

**Ne işe yarar:** Excel'de hazırladığınız fiyat listesini yükleyip tüm ürünlerin fiyat/stoğunu tek seferde günceller.

**Kullanım:**
1. SKU bazlı CSV hazırlayın (sütunlar: `sku, price, stock`)
2. Yükleyin
3. **Önce "Dry Run"** (test) yapın — sistem ne değiştireceğini gösterir, hata varsa uyarır
4. Doğruysa "Commit" → atomik (ya hepsi olur ya hiçbiri)

> 🛡️ Önce dry-run yapmadan asla commit etmeyin.

---

### 4.5 Düşük Stok Admin Uyarısı
**Ne işe yarar:** Her gün admin'e email gönderir: "Şu ürünlerin stoğu 5'in altında: ..."

**Ayar:** **Ayarlar → Stok Uyarısı** → "Eşik" + "Bildirim email"
**Cron:** `cron/low-stock-alert.php` günlük (9. bölüm)
**Dedupe:** Aynı ürün için 24 saat içinde 2. uyarı gitmez (spam koruma).

---

## 5) Faz 3 — SEO & Görünürlük

### 5.1 Breadcrumb (Yol İzi)
**Ne işe yarar:** "Ana Sayfa > Kategori > Ürün" şeklinde gezinme. Hem kullanıcı için kolay, hem Google için yapısal veri (schema.org/BreadcrumbList).

**Otomatik:** Ürün ve kategori sayfalarında görünür. Ayar gerekmez.

---

### 5.2 Sosyal Paylaşım Butonları
**Nerede:** Ürün sayfaları + blog yazıları altında

**Platformlar:** WhatsApp, X (Twitter), Facebook, Pinterest, Email, "Kopyala"

**Otomatik:** Aktif. Ayar gerekmez.

---

### 5.3 Sitemap (Index Formatı)
**Ne işe yarar:** Google'a hangi sayfaları taraması gerektiğini söyler. Index format çoklu site için optimize.

**URL'ler:**
- `siteniz.com/sitemap.xml` → Index (alt sitemap'ler listesi)
- `siteniz.com/sitemap.xml?type=products` → Tüm ürünler
- `siteniz.com/sitemap.xml?type=categories` → Tüm kategoriler
- `siteniz.com/sitemap.xml?type=blog` → Blog yazıları
- `siteniz.com/sitemap.xml?type=pages` → Sabit sayfalar

**Google Search Console:** Bu URL'yi ekleyin.

---

### 5.4 FAQ Accordion (PDP'de)
**Ne işe yarar:** Ürün sayfasında sıkça sorulan sorular bölümü açılır-kapanır şekilde gösterilir.

**Nerede ayarlanır:** Ürün düzenleme sayfasında "FAQ" alanına soru/cevap girin.

---

## 6) Faz 4 — Sadakat Programı (Puan)

### 6.1 Puan Sistemi
**Ne işe yarar:** Müşteri alışveriş yaptıkça puan kazanır, sonraki siparişte indirim olarak kullanır.

**Nasıl ayarlanır:** **Ayarlar → Sadakat Programı**
- **Kazanma oranı:** "1₺ = 1 puan" → 1.00
- **Kullanma değeri:** "10 puan = 1₺" → 0.10
- **Seviye eşikleri:**
  - Sadık: 2000₺ (son 12 ay)
  - VIP: 10000₺ (son 12 ay)
- "Sadakat aktif" işaretle → **Kaydet**

**Otomatik akış:**
1. Müşteri sipariş verir
2. Sipariş **"Teslim Edildi"** durumuna geçince puan eklenir
3. İade olursa puan geri alınır
4. 12 ay kullanılmazsa süresi dolar (cron çalışır)

---

### 6.2 Müşteri Sayfasında Puan Paneli
**Nerede:** Müşteri girişi → Hesabım

**Gösterilenler:**
- Mevcut puan bakiyesi
- Son hareketler (kazan/harca/expire)
- Seviye rozeti (Yeni / Sadık / VIP)

---

### 6.3 Doğum Günü Kuponu
**Ne işe yarar:** Müşterinin doğum günü geldiğinde otomatik **%15 indirim kuponu** üretilir, email + SMS ile gönderilir. **14 gün geçerli**, **yılda 1 kez**.

**Müşteri yapamayacağı:** Doğum gününü bir kez girdikten sonra **değiştiremez** (sahtekarlık koruması).

**Cron:** `cron/birthday-coupon.php` günlük 09:00 (Bölüm 9).

---

### 6.4 Otomatik Müşteri Seviye Güncelleme
**Ne işe yarar:** Her Pazartesi 02:00, son 12 ayın harcamasına göre tüm müşterilerin seviyesi yeniden hesaplanır.

**Seviyeler:**
- **Yeni** (varsayılan)
- **Sadık** (eşiği aşmış)
- **VIP** (üst eşiği aşmış)

**Cron:** `cron/loyalty-tier-update.php` (Bölüm 9).

---

### 6.5 Puan Expire Cron
**Ne işe yarar:** Kazandığı puan 12 ay içinde kullanılmadıysa otomatik silinir.

**Cron:** `cron/loyalty-expire.php` günlük 03:00 (Bölüm 9).

---

## 7) Faz 5 — Modernleşmiş Admin Panel

### 7.1 Komuta Merkezi (Yeni Dashboard)
**Nerede:** Admin → Dashboard

**Ne değişti:**
- KPI kartları (ciro, sipariş, AOV, dönüşüm)
- Trend grafikleri
- Hızlı eylem butonları
- Alert/uyarı paneli (düşük stok, bekleyen sipariş)
- En çok satılan ürünler tablosu

---

### 7.2 Yenilenmiş Ayarlar Sayfası (Hub)
**Ne değişti:** Eskiden tek devasa form'du, herşey karışık geliyordu. Artık **hub ve sayfa** sistemi:

**Ana hub:** `/admin_panel/settings.php` → 4 ana kategori kartı:

| Kategori | Alt başlıklar |
|---|---|
| 🏪 Mağaza Kimliği | Genel, İletişim, Konum, Çalışma Saatleri, Sosyal Medya |
| 📢 Pazarlama | SEO, Analitik, Exit-intent, Newsletter |
| 🔧 Operasyon | SMS, WhatsApp, Stok, Kargo, SMTP |
| 🧩 Entegrasyonlar | iyzico, Anthropic AI, Sadakat, Yasal/Mali |

Her kategoriye tıklayınca o bölümün ayarları açılır. Çok daha derli toplu.

---

### 7.3 Tema (Görsel)
- **Renk paleti:** Koyu yeşil + altın aksanlar (premium hissi)
- **Yazı tipi:** Playfair (başlık) + Inter (gövde)
- **Tutarlı kart spacing'i** (24px grid)
- **Hover state'leri** ve kontrast iyileştirildi (önceki dark fallback sorunu çözüldü)

---

### 7.4 SQL Migration Yöneticisi
**Nerede:** Admin → Araçlar → SQL Migration Yöneticisi (`/admin_panel/tools/migrations.php`)

**Ne işe yarar:** Her yeni özellik geldiğinde phpMyAdmin'e gitmeden, **tek tıkla** SQL migration'ı çalıştırırsınız.

**Güvenlik:**
- "Güvenlik analizi" otomatik açılır → kaç INSERT, kaç UPDATE, kaç DELETE göreceksiniz önceden
- Yapılmış migration'lar **tekrar çalışmaz** (idempotent)
- String-aware splitter — HTML entity'ler (`&amp;`) artık SQL'i kırmaz

---

### 7.5 Admin Hata Sayfası
**Site'de bir hata olursa:**
- Normal kullanıcı: Sade "Bir hata oluştu" sayfası
- **Admin girişli kullanıcı:** Hata detayı + dosya/satır numarası gösterilir (debug için)

Bu sayede üretim hatalarını siz görebilirsiniz, müşteri görmez.

---

## 8) Faz 6/7 — 2026 Trendleri

### 8.1 Çok Satanlar
**Ne işe yarar:** Gerçek sipariş verisinden en çok satan ürünleri çıkarır.

**Nerede görünür:** Ana sayfa + kategori boş ise.

**Otomatik.**

---

### 8.2 Son Görüntülenenler
**Ne işe yarar:** Müşterinin gezdiği ürünlerin küçük listesi.

**Nerede görünür:** Ürün sayfasının altında "Son baktıklarınız" rafı.

**Veri:** Cookie + localStorage (oturum bazlı).

---

### 8.3 Ürün Karşılaştırma
**Ne işe yarar:** Müşteri 2-4 ürünü yan yana karşılaştırır.

**Müşteri kullanımı:** Ürün kartında "Karşılaştır" → seçilenler sayfa altında küçük bir bar'da → "Karşılaştır" tuşu → tablo açılır.

---

### 8.4 Favorilere/Kayıt Edilenlere Ekle
**Ne işe yarar:** Müşteri kalp ikonuyla ürünü kaydeder, sonra "Hesabım → Favorilerim"den ulaşır.

**Önemli:** Tablo adı `favorites` (Database'de). `user_products` değil.

---

### 8.5 Sepet Rezervasyonu (Stok Tutma)
**Ne işe yarar:** Müşteri sepete bir ürün eklediğinde, 15 dakika boyunca o stok "rezerve" sayılır. Başkası alamaz.

**Cron:** `cron/cart-reservation-cleanup.php` 5 dakikada bir → süresi dolan rezervasyonları temizler.

> ⚠️ Yüksek trafikli sitelerde önerilir. Az trafikli sitede gerekli değil — kapalı tutabilirsiniz.

---

### 8.6 Q&A Skeleton (Soru-Cevap) — *Eksik*
Tablo hazır (`product_questions`) ama PDP'de form + admin moderation sayfası **henüz yapılmadı**. Sonraki sürümde tamamlanacak.

---

### 8.7 Foto/Video Yorumları — *Eksik*
`product_reviews.media` kolonu eklendi ama upload UI + lightbox **henüz yapılmadı**.

---

### 8.8 Hediye Paketleme (Order)
**Ne işe yarar:** Müşteri sipariş sırasında "hediye paketi" + "hediye mesajı" ekleyebilir.

**Admin:** Sipariş detayında hediye notu görünür.

---

## 9) Cron Job Listesi (Otomatik Görevler)

cPanel → Cron Jobs üzerinden ekleyin. **PHP yolu** ve **dosya yolu** kendi sunucunuza göre değişir (cPanel size gösterir).

| Sıklık | Cron Expression | Komut | Amaç |
|---|---|---|---|
| Günlük | `0 8 * * *` | `php /home/USER/public_html/cron/abandoned-cart.php` | Sepet terk hatırlatma (3 aşama) |
| Günlük | `0 9 * * *` | `php /home/USER/public_html/cron/low-stock-alert.php` | Düşük stok admin uyarısı |
| Günlük 09:00 | `0 9 * * *` | `php /home/USER/public_html/cron/birthday-coupon.php` | Doğum günü kuponu |
| Günlük 03:00 | `0 3 * * *` | `php /home/USER/public_html/cron/loyalty-expire.php` | 12 ay puan expire |
| Pazartesi 02:00 | `0 2 * * 1` | `php /home/USER/public_html/cron/loyalty-tier-update.php` | Müşteri seviye güncelle |
| 5 dk | `*/5 * * * *` | `php /home/USER/public_html/cron/cart-reservation-cleanup.php` | Sepet rezervasyon temizleme |

> 💡 İlk kez çalıştırmadan önce **cron komutunu terminal'de manuel test edin**: `php /yol/cron/birthday-coupon.php` → hata vermeli değil.

---

## 10) Hızlı Sorun Giderme (SSS)

### "Sepet sayfası mobilde bozuk görünüyor"
- Tarayıcı önbelleğini temizle (Ctrl+Shift+R / Cmd+Shift+R)
- Cart sayfasının inline CSS'i var, eksiksiz yüklendiğinden emin ol
- iPhone'da: Ayarlar → Safari → Geçmişi ve Web Verilerini Sil

### "Analitik veri gelmiyor"
1. **Ayarlar → Analitik** → ID'lerin **doğru girildiğini** kontrol et
2. "Analitik aktif" işaretli mi?
3. Çerez tercih kutusunda **Onayla** dedin mi? (KVKK)
4. GA4 → DebugView'da test cihazın görünüyor mu?
5. Bot user-agent ile geliyorsan analytics gönderilmez (PageSpeed, Headless dahil)

### "SMS gitmedi"
1. **Ayarlar → SMS** → "SMS aktif" mi?
2. API kullanıcı/şifre doğru mu? (panele giriş yapabilmen lazım)
3. **Onaylı gönderen başlığın** sağlayıcıda onaylanmış mı?
4. Admin → Bildirim Logları → SMS Log → hata mesajını gör

### "Sildiğim ürün hala görünüyor"
- Yeni sistem: Silme artık `is_active = 0` da yapıyor → sorun yok
- Eski silinmiş ürünler için migration 101 çalıştırıldı mı? (Migration Yöneticisi'nden)

### "Cron çalışmıyor"
- cPanel'de cron eklendiğinde, **email çıktısı** ayarlayın → cron çıktısını email'e yollar, hata görürsünüz
- Sunucu zaman dilimi yanlışsa cron yanlış saatte çalışır (cPanel'de TZ ayarı)

### "Migration güvenlik analizinde UYARI çıktı"
- Kırmızı uyarı: tablonu DROP etme veya RİSKLİ bir işlem var → **çalıştırma**
- Sarı uyarı: çok satır etkilenecek → **yedek al, sonra çalıştır**
- Yeşil: güvenli

### "Hangi dosyaları yüklemem gerek?"
- Her özellik talebinden sonra, değiştirilen dosyaların **tam listesi** size verilir
- O liste sayfa açılana kadar **eksiksiz yüklemelisiniz**
- Sadece migration dosyası yüklersiniz **ama PHP dosyaları yüklemezseniz** özellik aktif olmaz

---

## 📞 Yardım / Geliştirme Talebi

Bir özelliği kullanırken takılırsanız veya yeni bir özellik isterseniz, doğrudan benimle (Claude) konuşabilirsiniz. Size yardımcı olurken:

✅ **Hangi sayfada olduğunuzu söyleyin** (URL veya menüden yol)
✅ **Hata varsa screenshot atın**
✅ **Beklediğiniz davranışı yazın** ("X olmasını isterdim ama Y oldu")

---

**Son güncellenme:** 2026-05-19
**Versiyon:** 1.0
