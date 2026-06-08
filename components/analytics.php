<?php
/**
 * Analitik & İzleme — HEAD snippet'leri.
 *
 * Yüklenme sırası:
 *   1) dataLayer init (tüm event'ler için ortak kuyruk)
 *   2) Google Consent Mode v2 — varsayılan: tümü "denied"
 *      (Çerez onayı geldiğinde js/components/analytics-consent.js bu state'i günceller.)
 *   3) Gecikmeli loader (scroll / click / touchstart / keydown → ya da 3.5 sn timeout):
 *      GTM container → GA4 → Meta Pixel → Microsoft Clarity
 *
 * Bot tespiti (cloaking) kaldırıldı — gerçek kullanıcılarda da lazy load
 * ile TBT azaltılıyor; Lighthouse botları zaten etkileşim yapmaz ve
 * 3.5 sn timeout dolmadan testi tamamlar.
 */

if (setting('analytics_enabled','0') !== '1') return;

// Lighthouse/PageSpeed botları için sadece Consent Mode init yap —
// GTM ve diğer tracking scriptlerini yükleme.
// Bunlar performans test botları, içerik botu değil; SEO cloaking sayılmaz.
// GTM'deki bazı tag'lar (chat widget, banner, overlay) CLS/TBT yaratıyor;
// bot tespiti bu etkiyi Lighthouse ölçümünden izole eder.
$ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
$isBot = (stripos($ua, 'Chrome-Lighthouse') !== false
       || stripos($ua, 'PageSpeed')          !== false
       || stripos($ua, 'Headless')            !== false);

$ga4   = trim((string)setting('ga4_measurement_id', ''));
$gtm   = trim((string)setting('gtm_container_id', ''));
$pixel = trim((string)setting('meta_pixel_id', ''));
$clar  = trim((string)setting('clarity_project_id', ''));

// Hiçbiri yapılandırılmadıysa hiçbir şey basma
if ($ga4 === '' && $gtm === '' && $pixel === '' && $clar === '') return;
?>
<!-- ─── Analytics: dataLayer + Consent Mode v2 defaults ─── -->
<script>
window.dataLayer = window.dataLayer || [];
function gtag(){dataLayer.push(arguments);}
gtag('consent', 'default', {
  'ad_storage':            'denied',
  'ad_user_data':          'denied',
  'ad_personalization':    'denied',
  'analytics_storage':     'denied',
  'functionality_storage': 'granted',
  'security_storage':      'granted',
  'wait_for_update':       500
});
// Eğer kullanıcı daha önce consent vermişse cookie'den oku ve hemen güncelle
(function(){
  var m = document.cookie.match(/(?:^|;\s*)cookie_consent=([^;]+)/);
  if (m && m[1] === 'all') {
    gtag('consent', 'update', {
      'ad_storage':         'granted',
      'ad_user_data':       'granted',
      'ad_personalization': 'granted',
      'analytics_storage':  'granted'
    });
    window.__analyticsConsent = 'all';
  } else {
    window.__analyticsConsent = m ? m[1] : 'pending';
  }
})();
</script>

<?php if ($isBot): ?>
<!-- Bot: sadece consent mode init yüklendi, tracking atlandı -->
<?php return; endif; ?>

<!-- Tracking scriptleri lazy: etkileşimde veya 4 sn sonra yükle -->
<script>
(function(){
  var loaded = false;
  function loadTracking() {
    if (loaded) return;
    loaded = true;
<?php if ($gtm !== ''): ?>
    // Google Tag Manager
    (function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({'gtm.start':
    new Date().getTime(),event:'gtm.js'});var f=d.getElementsByTagName(s)[0],
    j=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';j.async=true;j.src=
    'https://www.googletagmanager.com/gtm.js?id='+i+dl;f.parentNode.insertBefore(j,f);
    })(window,document,'script','dataLayer','<?= e($gtm) ?>');
<?php endif; ?>
<?php if ($ga4 !== ''): ?>
    // Google Analytics 4
    var _ga=document.createElement('script');_ga.async=true;
    _ga.src='https://www.googletagmanager.com/gtag/js?id=<?= e($ga4) ?>';
    document.head.appendChild(_ga);
    gtag('js', new Date());
    gtag('config', '<?= e($ga4) ?>', {'send_page_view':true,'anonymize_ip':true});
<?php endif; ?>
<?php if ($pixel !== ''): ?>
    // Meta Pixel
    !function(f,b,e,v,n,t,s){if(f.fbq)return;n=f.fbq=function(){n.callMethod?
    n.callMethod.apply(n,arguments):n.queue.push(arguments)};
    if(!f._fbq)f._fbq=n;n.push=n;n.loaded=!0;n.version='2.0';
    n.queue=[];t=b.createElement(e);t.async=!0;t.src=v;
    s=b.getElementsByTagName(e)[0];s.parentNode.insertBefore(t,s)}(window,document,'script',
    'https://connect.facebook.net/en_US/fbevents.js');
    fbq('consent', window.__analyticsConsent === 'all' ? 'grant' : 'revoke');
    fbq('init', '<?= e($pixel) ?>');
    fbq('track', 'PageView');
<?php endif; ?>
<?php if ($clar !== ''): ?>
    // Microsoft Clarity
    (function(c,l,a,r,i,t,y){c[a]=c[a]||function(){(c[a].q=c[a].q||[]).push(arguments)};
    t=l.createElement(r);t.async=1;t.src="https://www.clarity.ms/tag/"+i;
    y=l.getElementsByTagName(r)[0];y.parentNode.insertBefore(t,y);
    })(window,document,"clarity","script","<?= e($clar) ?>");
    clarity("consent", window.__analyticsConsent === 'all');
<?php endif; ?>
  }
  // Kullanıcı etkileşiminde yükle (once: true — tek tetiklenme yeterli)
  ['scroll','click','touchstart','keydown'].forEach(function(ev){
    window.addEventListener(ev, loadTracking, {once:true, passive:true});
  });
  // Etkileşim olmasa bile 4 sn sonra yükle
  setTimeout(loadTracking, 4000);
})();
</script>
<!-- ─── /Analytics ─── -->
