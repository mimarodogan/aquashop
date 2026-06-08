-- Çerez Politikası sayfası — cookie banner'dan linklenen
INSERT INTO pages (slug, title, content, is_published) VALUES (
  'cerez-politikasi',
  'Çerez Politikası',
  '<h2>Çerez Politikası</h2>
<p>Bu Çerez Politikası, <strong>aquashop.com.tr</strong> web sitesinde kullanılan çerezler (cookies) hakkında sizi bilgilendirmek amacıyla hazırlanmıştır.</p>

<h3>Çerez Nedir?</h3>
<p>Çerezler, web siteleri tarafından tarayıcınıza yerleştirilen küçük metin dosyalarıdır. Sitemizi her ziyaret ettiğinizde web sunucusu tarafından tarayıcınıza gönderilir ve tarayıcınız tarafından saklanır. Sonraki ziyaretlerde bu çerezler sunucumuza geri gönderilerek kimliğinizin tanınmasına ve tercihlerinizin hatırlanmasına yardımcı olur.</p>

<h3>Kullandığımız Çerez Türleri</h3>

<h4>Zorunlu Çerezler</h4>
<p>Bu çerezler sitenin doğru şekilde çalışabilmesi için gereklidir. Oturum açma durumunuzun korunması, alışveriş sepetinizin hatırlanması ve güvenlik kontrolleri gibi temel işlevler bu çerezler aracılığıyla sağlanır. Tarayıcınızda bu çerezleri devre dışı bırakmanız durumunda sitenin bazı bölümleri düzgün çalışmayabilir.</p>

<h4>İşlevsel Çerezler</h4>
<p>Dil tercihiniz, konum bilginiz gibi tercihlerinizi hatırlamamıza olanak tanır. Bu çerezler olmadan tercihlerinizi her ziyarette yeniden belirlemeniz gerekebilir.</p>

<h4>Analitik Çerezler</h4>
<p>Sitemizin nasıl kullanıldığını anlamamıza yardımcı olur; hangi sayfaların en çok ziyaret edildiğini, kullanıcıların sitede nasıl gezindiğini analiz ederiz. Bu veriler anonim olarak toplanır ve hizmetlerimizi iyileştirmek amacıyla kullanılır.</p>

<h3>Çerezleri Nasıl Kontrol Edebilirsiniz?</h3>
<p>Tarayıcınızın ayarlarından çerezleri reddetme veya silme hakkınız bulunmaktadır. Ancak bu işlem sonucunda sitemizin bazı özelliklerinin tam olarak çalışmayabileceğini belirtmek isteriz.</p>
<p>Popüler tarayıcılarda çerez ayarları için:</p>
<ul>
  <li><strong>Google Chrome:</strong> Ayarlar → Gizlilik ve güvenlik → Çerezler ve diğer site verileri</li>
  <li><strong>Mozilla Firefox:</strong> Seçenekler → Gizlilik ve Güvenlik → Çerezler ve Site Verileri</li>
  <li><strong>Safari:</strong> Tercihler → Gizlilik → Çerezler ve web sitesi verileri</li>
  <li><strong>Microsoft Edge:</strong> Ayarlar → Çerezler ve site izinleri</li>
</ul>

<h3>Üçüncü Taraf Çerezleri</h3>
<p>Ödeme altyapısı, harita veya sosyal medya butonları gibi üçüncü taraf hizmetler kendi çerezlerini yerleştirebilir. Bu çerezler ilgili üçüncü tarafların gizlilik politikasına tabidir.</p>

<h3>Kişisel Verilerin Korunması</h3>
<p>Çerezler aracılığıyla elde edilen kişisel veriler KVKK kapsamında işlenmektedir. Ayrıntılı bilgi için <a href="sayfa/kvkk">KVKK Aydınlatma Metni</a> sayfamızı inceleyebilirsiniz.</p>

<h3>Değişiklikler</h3>
<p>Bu politika gerektiğinde güncellenebilir. Önemli değişiklikler söz konusu olduğunda sizi site üzerinden bilgilendireceğiz. Son güncelleme: Mayıs 2025.</p>

<h3>İletişim</h3>
<p>Çerez politikamıza ilişkin sorularınız için <a href="iletisim">iletişim sayfamızdan</a> bize ulaşabilirsiniz.</p>',
  1
) ON DUPLICATE KEY UPDATE title=VALUES(title), content=VALUES(content), is_published=1;
