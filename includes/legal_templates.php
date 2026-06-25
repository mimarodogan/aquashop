<?php
/**
 * Türkiye yasal mevzuatına uygun e-ticaret sayfa şablonları.
 * - TKHK md. 48 (Mesafeli Sözleşmeler)
 * - Mesafeli Sözleşmeler Yönetmeliği
 * - KVKK md. 10 (Aydınlatma Yükümlülüğü)
 * - 6502 sayılı kanun
 *
 * Tüm metinlerde {placeholder}'lar admin → Ayarlar'dan otomatik dolar.
 */
require_once __DIR__ . '/functions.php';

if (!function_exists('legal_templates')) {
function legal_templates(): array {
    $name   = trim((string)setting('site_name','')) ?: (defined('SITE_NAME_FALLBACK') ? SITE_NAME_FALLBACK : 'Mağaza');
    $legal  = (string)setting('company_legal_name', $name);
    $taxOff = (string)setting('company_tax_office','—');
    $taxNo  = (string)setting('company_tax_no','—');
    $mersis = (string)setting('company_mersis','—');
    $addr   = (string)setting('contact_address','—');
    $email  = (string)setting('contact_email','—');
    $phone  = (string)setting('contact_phone','—');
    $url    = rtrim((string)setting('site_url',''), '/') ?: 'https://' . ($_SERVER['HTTP_HOST'] ?? 'localhost');

    return [
        'on-bilgilendirme' => [
            'title' => 'Ön Bilgilendirme Formu',
            'content' => <<<HTML
<h2>Ön Bilgilendirme Formu</h2>
<p>İşbu Ön Bilgilendirme Formu, 6502 sayılı <strong>Tüketicinin Korunması Hakkında Kanun</strong>'un 48. maddesi ve <strong>Mesafeli Sözleşmeler Yönetmeliği</strong>'nin 5. maddesi uyarınca düzenlenmiştir. Sipariş onayından önce okunması ve kabul edilmesi gerekmektedir.</p>

<h3>1. Satıcı Bilgileri</h3>
<ul>
  <li><strong>Ünvan:</strong> {$legal}</li>
  <li><strong>Adres:</strong> {$addr}</li>
  <li><strong>Telefon:</strong> {$phone}</li>
  <li><strong>E-posta:</strong> {$email}</li>
  <li><strong>Vergi Dairesi / No:</strong> {$taxOff} / {$taxNo}</li>
  <li><strong>Mersis No:</strong> {$mersis}</li>
</ul>

<h3>2. Sözleşme Konusu Mal/Hizmetin Temel Nitelikleri ve Fiyatı</h3>
<p>Sipariş özetinde belirtilen mal ve hizmetlerin temel nitelikleri, miktarı, satış fiyatı ve KDV dahil toplam fiyat ile geçerli ödeme şekli bilgileri sipariş onay sayfasında ve siparişiniz oluşturulduktan sonra tarafınıza gönderilecek e-postada yer almaktadır. Kargo ücreti, varsa, sipariş özetinde ayrıca gösterilir.</p>

<h3>3. Ödeme ve Teslimat</h3>
<p>Ödemeler, Mesafeli Sözleşmeler Yönetmeliği uyarınca güvenli ödeme altyapısı (kredi/banka kartı) veya banka havalesi/EFT yoluyla alınır. Teslimat, anlaşmalı kargo şirketi aracılığıyla siparişin onaylanmasının ardından en geç <strong>30 (otuz) gün</strong> içinde alıcının bildirdiği adrese yapılır.</p>

<h3>4. Cayma Hakkı</h3>
<p>Tüketici, sözleşmenin kurulduğu veya malın teslim alındığı tarihten itibaren <strong>14 (on dört) gün</strong> içerisinde herhangi bir gerekçe göstermeksizin ve cezai şart ödemeksizin sözleşmeden cayma hakkına sahiptir. Cayma hakkının kullanılması için bu süre içinde Satıcı'ya yazılı olarak veya kalıcı veri saklayıcısı (e-posta vb.) ile bildirimde bulunulması gerekir.</p>

<h3>5. Cayma Hakkının Kullanılamayacağı Haller (MSY md. 15)</h3>
<ul>
  <li>Tüketicinin istekleri veya kişisel ihtiyaçları doğrultusunda hazırlanan mallar</li>
  <li>Çabuk bozulabilen veya son kullanma tarihi geçebilecek mallar</li>
  <li>Tesliminden sonra ambalaj, bant, mühür, paket gibi koruyucu unsurları açılmış olan ve iadesi sağlık/hijyen açısından uygun olmayan ürünler</li>
  <li>Tesliminden sonra başka ürünlerle karışan ve doğası gereği ayrıştırılması mümkün olmayan ürünler</li>
</ul>

<h3>6. İade Süreci</h3>
<p>Cayma hakkının kullanılması durumunda, ürün eksiksiz ve hasarsız olarak <strong>{$addr}</strong> adresine, anlaşmalı kargo şirketi ile gönderilmelidir. Ürünün satıcıya ulaşmasından itibaren en geç 14 gün içinde ödeme iadesi gerçekleştirilir.</p>

<h3>7. Şikayet ve İtirazlar</h3>
<p>Tüketici, şikayet ve itirazlarını <strong>{$email}</strong> adresine veya yerleşim yerinin bulunduğu Tüketici Hakem Heyeti'ne ya da Tüketici Mahkemesi'ne iletebilir.</p>

<p style="margin-top:24px;font-size:13px;color:#5F5F5F"><em>Bu form son güncelleme tarihi:</em> <strong>HTML
                . date('d.m.Y') . <<<HTML
</strong></p>
HTML
        ],

        'mesafeli-satis' => [
            'title' => 'Mesafeli Satış Sözleşmesi',
            'content' => <<<HTML
<h2>Mesafeli Satış Sözleşmesi</h2>

<h3>1. Taraflar</h3>
<p><strong>Satıcı:</strong> {$legal}<br>
Adres: {$addr}<br>
Telefon: {$phone} · E-posta: {$email}<br>
Vergi Dairesi/No: {$taxOff} / {$taxNo}</p>
<p><strong>Alıcı:</strong> Sipariş formunda belirttiği bilgilerle siparişi veren gerçek/tüzel kişi.</p>

<h3>2. Sözleşmenin Konusu</h3>
<p>İşbu sözleşmenin konusu, Alıcı'nın Satıcı'ya ait <strong>{$url}</strong> internet sitesinden elektronik ortamda sipariş ettiği, sipariş özetinde nitelikleri ve satış fiyatı belirtilen mal/hizmetin satışı ve teslimi ile ilgili olarak 6502 sayılı Tüketicinin Korunması Hakkında Kanun ve Mesafeli Sözleşmeler Yönetmeliği hükümleri gereğince tarafların hak ve yükümlülüklerinin saptanmasıdır.</p>

<h3>3. Sözleşme Konusu Ürün ve Ödeme Bilgileri</h3>
<p>Ürünlerin cinsi, türü, miktarı, marka/modeli, satış bedeli, ödeme şekli, alıcısı, teslimat adresi ve KDV dahil toplam tutar sipariş onayında ve müşteriye gönderilen sipariş onay e-postasında yer alır.</p>

<h3>4. Genel Hükümler</h3>
<ol>
  <li>Alıcı, sözleşme konusu ürünün temel nitelikleri, satış fiyatı ve ödeme şekli ile teslimata ilişkin tüm ön bilgileri okuyup bilgi sahibi olduğunu, elektronik ortamda gerekli teyidi verdiğini kabul eder.</li>
  <li>Sözleşme konusu ürün, yasal 30 günlük süreyi aşmamak koşulu ile her bir ürün için Alıcı'nın yerleşim yerinin uzaklığına bağlı olarak ön bilgiler içinde açıklanan süre içinde Alıcı veya gösterdiği adresteki kişi/kuruluşa teslim edilir.</li>
  <li>Sözleşme konusu ürünün teslimatı için işbu sözleşmenin imzalı nüshasının Satıcı'ya ulaştırılmış olması ve bedelinin Alıcı'nın tercih ettiği ödeme şekli ile ödenmiş olması şarttır.</li>
  <li>Ürünün tesliminden sonra Alıcı'ya ait kredi kartının Alıcı'nın kusurundan kaynaklanmayan bir şekilde yetkisiz kişilerce kullanılması nedeni ile bankanın ürün bedelini Satıcı'ya ödememesi halinde, Alıcı kendisine teslim edilmiş olan ürünü 3 (üç) gün içinde Satıcı'ya iade etmekle yükümlüdür.</li>
</ol>

<h3>5. Cayma Hakkı</h3>
<p>Alıcı, sözleşmenin kurulduğu veya malın teslim alındığı tarihten itibaren <strong>14 (on dört) gün</strong> içinde herhangi bir gerekçe göstermeksizin ve cezai şart ödemeksizin sözleşmeden cayma hakkına sahiptir. Cayma hakkının kullanıldığına dair bildirimin bu süre içinde yazılı olarak veya kalıcı veri saklayıcısı ile Satıcı'ya yöneltilmesi yeterlidir.</p>
<p>Cayma hakkı süresi sona ermeden önce, tüketicinin onayı ile hizmetin ifasına başlanan hizmet sözleşmelerinde cayma hakkı kullanılamaz. Cayma hakkının kullanılamayacağı durumlar Mesafeli Sözleşmeler Yönetmeliği md. 15'te düzenlenmiştir (Ön Bilgilendirme Formu'na bakınız).</p>

<h3>6. Yetkili Mahkeme</h3>
<p>İşbu sözleşmenin uygulanmasında, Sanayi ve Ticaret Bakanlığı'nca ilan edilen değere kadar Tüketici Hakem Heyetleri ile Alıcı'nın veya Satıcı'nın yerleşim yerindeki Tüketici Mahkemeleri yetkilidir.</p>

<p>Sipariş onayı vermeniz halinde işbu sözleşmenin tüm koşullarını okuduğunuzu, anladığınızı ve kabul ettiğinizi beyan etmiş sayılırsınız.</p>
HTML
        ],

        'kvkk' => [
            'title' => 'KVKK Aydınlatma Metni',
            'content' => <<<HTML
<h2>Kişisel Verilerin Korunması Aydınlatma Metni</h2>

<p>İşbu aydınlatma metni, <strong>6698 sayılı Kişisel Verilerin Korunması Kanunu (KVKK)</strong>'nun 10. maddesi ve <strong>Aydınlatma Yükümlülüğünün Yerine Getirilmesinde Uyulacak Usul ve Esaslar Hakkında Tebliğ</strong> uyarınca, veri sorumlusu sıfatıyla {$legal} ("Şirket") tarafından düzenlenmiştir.</p>

<h3>1. Veri Sorumlusu</h3>
<p><strong>{$legal}</strong><br>
Adres: {$addr}<br>
E-posta: {$email}<br>
Telefon: {$phone}</p>

<h3>2. İşlenen Kişisel Veriler</h3>
<p>Aşağıdaki kategorilerde kişisel verileriniz işlenebilir:</p>
<ul>
  <li><strong>Kimlik bilgileri:</strong> ad, soyad, T.C. kimlik numarası (yalnızca fatura için)</li>
  <li><strong>İletişim bilgileri:</strong> e-posta adresi, telefon, posta adresi</li>
  <li><strong>Müşteri işlem bilgileri:</strong> sipariş geçmişi, sepet bilgileri, fatura/teslimat adresi</li>
  <li><strong>Ödeme bilgileri:</strong> ödeme yöntemi (kart bilgisi tarafımızda saklanmaz, iyzico altyapısı ile işlenir)</li>
  <li><strong>İşlem güvenliği bilgileri:</strong> IP adresi, oturum bilgileri, tarayıcı bilgileri</li>
  <li><strong>Pazarlama bilgileri:</strong> bülten aboneliği, izinli kampanya bildirimleri</li>
</ul>

<h3>3. Kişisel Verilerin İşlenme Amaçları</h3>
<ul>
  <li>Sözleşmenin kurulması ve ifası (sipariş alma, teslimat, ödeme)</li>
  <li>Yasal yükümlülüklerin yerine getirilmesi (vergi, fatura, defter tutma)</li>
  <li>Müşteri ilişkileri yönetimi, şikayet ve taleplerin değerlendirilmesi</li>
  <li>İletişim faaliyetleri (sipariş bildirimleri, sözleşme metinleri)</li>
  <li>Açık rıza alınması halinde pazarlama faaliyetleri (bülten, kampanya)</li>
  <li>Hukuki süreçlerin yürütülmesi, dolandırıcılık önleme</li>
</ul>

<h3>4. Kişisel Verilerin Aktarılması</h3>
<p>Kişisel verileriniz, KVKK md. 8 ve 9'da belirlenen şartlar çerçevesinde aşağıdaki taraflara aktarılabilir:</p>
<ul>
  <li>Anlaşmalı kargo şirketleri (teslimat amacıyla)</li>
  <li>Ödeme kuruluşları (iyzico — ödeme tahsilatı)</li>
  <li>E-fatura/e-arşiv hizmet sağlayıcıları (yasal yükümlülük)</li>
  <li>Hukuki yükümlülük gereği yetkili kamu kurum ve kuruluşları</li>
</ul>

<h3>5. Kişisel Verilerin Toplanma Yöntemi ve Hukuki Sebebi</h3>
<p>Kişisel verileriniz, <strong>{$url}</strong> üzerinden elektronik ortamda, sipariş formları, üyelik kaydı, iletişim formu ve çerezler aracılığıyla toplanır. Hukuki sebepleri: sözleşmenin kurulması ve ifası, hukuki yükümlülük, meşru menfaat ve açık rıza.</p>

<h3>6. KVKK Madde 11 Kapsamında Haklarınız</h3>
<p>Veri sahibi olarak Kanun'un 11. maddesi kapsamında aşağıdaki haklara sahipsiniz:</p>
<ul>
  <li>Kişisel verilerinizin işlenip işlenmediğini öğrenme</li>
  <li>Kişisel verileriniz işlenmişse buna ilişkin bilgi talep etme</li>
  <li>İşlenme amacını ve amaca uygun kullanılıp kullanılmadığını öğrenme</li>
  <li>Yurt içinde/yurt dışında aktarıldığı üçüncü kişileri bilme</li>
  <li>Eksik veya yanlış işlenmiş veriler için düzeltilmesini isteme</li>
  <li>Kanun'un 7. maddesinde öngörülen şartlar çerçevesinde silinmesini veya yok edilmesini isteme</li>
  <li>Düzeltme/silme/yok etme işlemlerinin verilerin aktarıldığı üçüncü kişilere bildirilmesini isteme</li>
  <li>İşlenen verilerin münhasıran otomatik sistemler vasıtasıyla analiz edilmesi sonucu aleyhinize bir sonucun ortaya çıkmasına itiraz etme</li>
  <li>Kanuna aykırı olarak işlenmesi sebebiyle zarara uğramanız hâlinde zararın giderilmesini talep etme</li>
</ul>

<h3>7. Başvuru Yöntemi</h3>
<p>Yukarıdaki haklarınızı kullanmak için <strong>{$email}</strong> adresine başvurabilirsiniz. Başvurunuz en geç <strong>30 (otuz) gün</strong> içinde sonuçlandırılır.</p>

<p style="margin-top:24px;font-size:13px;color:#5F5F5F"><em>Bu metnin son güncelleme tarihi:</em> <strong>HTML
                . date('d.m.Y') . <<<HTML
</strong></p>
HTML
        ],

        'iade-degisim' => [
            'title' => 'İade ve Değişim Koşulları',
            'content' => <<<HTML
<h2>İade ve Değişim Koşulları</h2>

<h3>14 Günlük Cayma Hakkı</h3>
<p>6502 sayılı Tüketicinin Korunması Hakkında Kanun ve Mesafeli Sözleşmeler Yönetmeliği uyarınca, ürünün size teslim edildiği tarihten itibaren <strong>14 (on dört) gün</strong> içinde herhangi bir gerekçe göstermeksizin sözleşmeden cayma hakkınız bulunmaktadır.</p>

<h3>İade Şartları</h3>
<ul>
  <li>Ürün, ambalajı ve aksesuarları ile birlikte hasarsız ve eksiksiz olmalıdır.</li>
  <li>Orijinal kutusu, varsa hediyeleri ve faturası ile birlikte gönderilmelidir.</li>
  <li>Hijyen ve sağlık nedeniyle bazı ürünler iade edilemez (aşağıya bakın).</li>
</ul>

<h3>İade Edilemeyen Ürünler (MSY md. 15)</h3>
<ul>
  <li>Çabuk bozulabilen veya kısa raf ömürlü ürünler (örn. açılmış canlı balık yemi, canlı bitki, canlı balık vb.)</li>
  <li>Ambalajı, mühürü açılmış hijyenik ürünler</li>
  <li>Tüketicinin istekleri doğrultusunda kişiye özel hazırlanan ürünler</li>
  <li>Tesliminden sonra başka ürünlerle karışan ve doğası gereği ayrıştırılması mümkün olmayan ürünler</li>
</ul>

<h3>İade Süreci</h3>
<ol>
  <li><strong>Hesabım → Siparişlerim</strong> sayfasından ilgili sipariş için "İade Talep Et" butonuna tıklayın veya <strong>{$email}</strong> adresine başvurun.</li>
  <li>Onay sonrası anlaşmalı kargo şirketi ile ürünü gönderin (kargo bedeli kim tarafından karşılanacağı yasal mevzuat çerçevesinde belirlenir).</li>
  <li>Ürün tarafımıza ulaştıktan sonra <strong>en geç 14 gün</strong> içinde ödeme iadesi yapılır. İade, siparişin yapıldığı yöntemle gerçekleştirilir.</li>
</ol>

<h3>Değişim</h3>
<p>Aynı tutardaki başka bir ürün ile değişim talep edebilirsiniz. Fiyat farkı varsa fark tarafınızdan tamamlanır veya size iade edilir.</p>

<h3>Hasarlı / Yanlış Ürün</h3>
<p>Eğer ürün size hasarlı veya yanlış ulaştıysa, teslim aldıktan sonra <strong>48 saat içinde</strong> {$email} adresine fotoğraflı bildirim yapın. Bu durumlarda iade kargo ücreti tarafımızdan karşılanır.</p>

<h3>İletişim</h3>
<p><strong>{$legal}</strong><br>
Adres: {$addr}<br>
E-posta: {$email}<br>
Telefon: {$phone}</p>
HTML
        ],
    ];
}}

/**
 * Hazır şablonları DB'ye yazar (varsa atlar veya isteğe bağlı üzerine yazar).
 */
if (!function_exists('legal_templates_install')) {
function legal_templates_install(bool $overwrite = false): array {
    $stats = ['created'=>0, 'updated'=>0, 'skipped'=>0];
    $find = db()->prepare('SELECT id FROM pages WHERE slug=? LIMIT 1');
    $ins  = db()->prepare('INSERT INTO pages (slug, title, content, is_published) VALUES (?,?,?,1)');
    $upd  = db()->prepare('UPDATE pages SET title=?, content=? WHERE id=?');
    foreach (legal_templates() as $slug => $tpl) {
        $find->execute([$slug]);
        $row = $find->fetch();
        if ($row) {
            if ($overwrite) {
                $upd->execute([$tpl['title'], $tpl['content'], (int)$row['id']]);
                $stats['updated']++;
            } else {
                $stats['skipped']++;
            }
        } else {
            $ins->execute([$slug, $tpl['title'], $tpl['content']]);
            $stats['created']++;
        }
    }
    return $stats;
}}
