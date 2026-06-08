# Design System: Yurtiçi E-ticaret Şablonu

## 1. Visual Theme & Atmosphere
"Premium e-ticaret" atmosferi — temiz, modern, güvenilir. Derin yeşil aksanlar, antika altın detaylar, ince art-deco köşe süslemeleri. Yoğunluk orta-yüksek; geniş koyu zeminler altın detaylarla nefes alır. Genel his: zamansız, saygın, doğal, dokunulmaya değer.

Anahtar sıfatlar: **Rustik-lüks, mat, dokulu, törensel.**

## 2. Color Palette & Roles
- **Deep Olive Forest (#1F2A1C)** — Birincil arka plan; hero, navbar, footer.
- **Forest Shadow (#243024)** — İkincil yeşil; yumuşak gradient ve büyük yüzeyler.
- **Midnight Olive (#0F1A10)** — En koyu yüzey; kart hover, modal, ürün detay.
- **Antique Gold (#C9A24B)** — Birincil aksan; CTA, başlık, ikon, çerçeve.
- **Soft Champagne Gold (#E5C97A)** — Hover/aktif; vurgu metni; rozet.
- **Muted Gold Border (#8C6F2A)** — İnce ayırıcı, kart kenarlığı.
- **Warm Ivory (#F5EFE0)** — Açık bölüm ve checkout zemini.
- **Soft Cream (#EFE6D2)** — Ivory üzerinde nazik kart yüzeyi.
- **Olive Leaf Green (#6B7A3A)** — Kategori etiketi, başarı durumu.
- **Charcoal Ink (#1A1A1A)** — Ivory zeminde gövde metni.

## 3. Typography Rules
- **Display & Başlık:** `Playfair Display` (veya `Cormorant Garamond`) — antika altın, ağırlık 600-700, letter-spacing 0.02em. Hero 56-72px, bölüm 36-44px.
- **Kicker / Caps Etiket:** All-caps, letter-spacing 0.22em, 12-14px.
- **Gövde:** `Inter` / `Manrope`, ağırlık 400, line-height 1.6. Koyu zeminde Soft Champagne; ivory zeminde Charcoal Ink.
- **Vurgu / Fiyat:** Serif, ağırlık 600, Antique Gold.

## 4. Component Stylings
- **Birincil Buton:** Antique Gold dolgu, Deep Olive Forest metin, radius 4-6px, 1px iç altın kenarlık. Hover: Soft Champagne.
- **İkincil Buton:** Şeffaf zemin, 1px altın kenarlık, altın metin. Hover: %6 altın opaklık.
- **Ürün Kartı (koyu):** Deep Olive Forest zemin, 1px Muted Gold kenarlık, radius 10-12px, dört köşede mikro altın ornament. Gölge: `0 4px 16px rgba(0,0,0,0.25)`.
- **Ürün Kartı (açık):** Warm Ivory zemin, ince altın kenarlık, Charcoal Ink başlık, Antique Gold fiyat.
- **Inputs:** Şeffaf zemin (koyu) / Soft Cream (açık), 1px Muted Gold kenarlık, focus'ta `0 0 0 2px rgba(201,162,75,0.35)` altın halka.
- **Navbar:** Sticky, Deep Olive Forest, ortalanmış altın logo, alt 1px Muted Gold ayraç.
- **Footer:** Midnight Olive, dört köşede büyük art-deco altın süsleme.
- **Rozet:** Pill (radius 999px), 1px altın çerçeve, all-caps 11px, Antique Gold.
- **Bölüm Ayırıcı:** Yatay altın ornament + ortada elmas/dal motifi.

## 5. Layout Principles
- 12 kolon grid, **max-width 1280px**, kenar boşluğu 24-48px (mobilde 16px).
- Bölümler arası dikey ritim **80-120px** (mobilde 56-72px).
- Hero: tam genişlik koyu zemin, sol tarafta serif başlık + kicker + CTA, sağda büyük ürün görseli.
- Liste sayfası: 3-4 kolon grid, gap 32px.
- Detay: sol %55 galeri (ivory mat zemin), sağ %45 bilgi + CTA.

## 6. Görsel Aksesuar
- Art-deco köşe süslemeleri (etiket köşelerinden ilham); hero/footer'da 80px, kartlarda 16px.
- Bölüm arka planlarında %6-10 opaklıkla yaprak/dal filigranı.
- İkonografi: 1.5px stroke, Antique Gold, yuvarlatılmış uçlar.

## 7. E-ticaret Sayfa Kapsamı
Anasayfa, Ürün Listesi, Ürün Detay, Sepet, Checkout, Hakkımızda, İletişim — tümü yukarıdaki sistemden türetilir.

## 8. Erişilebilirlik
- Antique Gold / Deep Olive Forest kontrastı AA üstü.
- Form focus yalnız renk değil 2px altın halka ile de işaretlenir.
