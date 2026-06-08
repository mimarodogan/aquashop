<?php
/**
 * Sosyal Paylaşım Butonları — PDP, blog post, page için.
 *
 * Kullanım:
 *   $shareUrl   = $base . url('product', ['slug' => $p['slug']]);
 *   $shareTitle = $p['name'];
 *   $shareDesc  = $p['short_desc'] ?? '';
 *   $shareImage = $p['image'] ?? '';
 *   include __DIR__ . '/social-share.php';
 *
 * Çıktı: yatay buton grubu (WhatsApp, X/Twitter, Facebook, Pinterest, Email, Kopyala).
 * Tüm linkler tarayıcıda yeni sekmede açılır (rel=noopener).
 */
if (empty($shareUrl)) return;

$shareTitle = $shareTitle ?? '';
$shareDesc  = $shareDesc  ?? '';
$shareImage = $shareImage ?? '';

$enc = static fn($v) => rawurlencode((string)$v);
$txt = trim($shareTitle . ($shareDesc ? ' — ' . $shareDesc : ''));
$absImg = $shareImage && strpos($shareImage, 'http') !== 0
    ? (((isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? '') . $shareImage)
    : $shareImage;

$links = [
    'whatsapp' => [
        'label' => 'WhatsApp',
        'href'  => 'https://wa.me/?text=' . $enc($txt . "\n" . $shareUrl),
        'svg'   => '<svg viewBox="0 0 24 24" fill="currentColor" width="18" height="18"><path d="M12 2C6.5 2 2 6.5 2 12c0 1.7.4 3.3 1.2 4.7L2 22l5.4-1.4C8.8 21.5 10.4 22 12 22c5.5 0 10-4.5 10-10S17.5 2 12 2zm5.3 14.3c-.2.6-1.2 1.2-1.7 1.3-.5.1-1.1.1-1.7-.1-.4-.1-.9-.3-1.5-.6-2.7-1.2-4.5-4-4.6-4.1-.1-.2-1.1-1.5-1.1-2.8 0-1.4.7-2.1 1-2.4.2-.3.6-.4.8-.4h.6c.2 0 .5 0 .7.5.2.6.8 2.1.9 2.2.1.2.1.3 0 .5-.1.2-.2.3-.3.5-.2.2-.3.4-.5.5-.2.2-.3.3-.1.6.2.3.8 1.3 1.8 2.1 1.3 1.1 2.3 1.4 2.6 1.6.3.1.5.1.6-.1.2-.2.7-.8.9-1.1.2-.3.4-.2.6-.1.3.1 1.7.8 2 1 .3.1.5.2.6.3.1.2.1.7-.1 1.3z"/></svg>',
    ],
    'twitter' => [
        'label' => 'X',
        'href'  => 'https://twitter.com/intent/tweet?text=' . $enc($txt) . '&url=' . $enc($shareUrl),
        'svg'   => '<svg viewBox="0 0 24 24" fill="currentColor" width="18" height="18"><path d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-5.214-6.817L4.99 21.75H1.68l7.73-8.835L1.254 2.25H8.08l4.713 6.231zm-1.161 17.52h1.833L7.084 4.126H5.117z"/></svg>',
    ],
    'facebook' => [
        'label' => 'Facebook',
        'href'  => 'https://www.facebook.com/sharer/sharer.php?u=' . $enc($shareUrl),
        'svg'   => '<svg viewBox="0 0 24 24" fill="currentColor" width="18" height="18"><path d="M22 12c0-5.52-4.48-10-10-10S2 6.48 2 12c0 4.84 3.44 8.87 8 9.8V15H8v-3h2V9.5C10 7.57 11.57 6 13.5 6H16v3h-2c-.55 0-1 .45-1 1v2h3v3h-3v6.95c5.05-.5 9-4.76 9-9.95z"/></svg>',
    ],
    'pinterest' => [
        'label' => 'Pinterest',
        'href'  => 'https://www.pinterest.com/pin/create/button/?url=' . $enc($shareUrl) . '&description=' . $enc($txt) . ($absImg ? '&media=' . $enc($absImg) : ''),
        'svg'   => '<svg viewBox="0 0 24 24" fill="currentColor" width="18" height="18"><path d="M12 2C6.48 2 2 6.48 2 12c0 4.22 2.62 7.83 6.31 9.29-.09-.79-.17-2 .04-2.86.19-.78 1.22-4.95 1.22-4.95s-.31-.62-.31-1.54c0-1.45.84-2.53 1.88-2.53.89 0 1.32.67 1.32 1.46 0 .89-.57 2.22-.86 3.46-.25 1.04.52 1.88 1.54 1.88 1.85 0 3.28-1.95 3.28-4.77 0-2.49-1.79-4.24-4.35-4.24-2.96 0-4.7 2.22-4.7 4.52 0 .89.34 1.85.77 2.37.08.1.1.19.07.29-.08.34-.26 1.04-.29 1.18-.05.19-.16.23-.36.14-1.34-.62-2.18-2.59-2.18-4.17 0-3.39 2.47-6.51 7.11-6.51 3.74 0 6.63 2.66 6.63 6.22 0 3.71-2.34 6.69-5.59 6.69-1.09 0-2.12-.57-2.47-1.24l-.67 2.56c-.24.94-.9 2.12-1.34 2.84.99.31 2.05.49 3.16.49 5.52 0 10-4.48 10-10S17.52 2 12 2z"/></svg>',
    ],
    'email' => [
        'label' => 'E-posta',
        'href'  => 'mailto:?subject=' . $enc($shareTitle) . '&body=' . $enc($txt . "\n\n" . $shareUrl),
        'svg'   => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" width="18" height="18"><path d="M4 4h16v16H4z"/><path d="m4 4 8 8 8-8"/></svg>',
    ],
];
?>
<div class="social-share" role="group" aria-label="Bu sayfayı paylaş">
  <span class="ss-label">Paylaş:</span>
  <?php foreach ($links as $k => $l): ?>
    <a href="<?= e($l['href']) ?>" target="_blank" rel="noopener nofollow" class="ss-btn ss-<?= $k ?>" aria-label="<?= e($l['label']) ?>'da paylaş" title="<?= e($l['label']) ?>'da paylaş">
      <?= $l['svg'] ?>
    </a>
  <?php endforeach; ?>
  <button type="button" class="ss-btn ss-copy" data-share-copy data-url="<?= e($shareUrl) ?>" aria-label="Bağlantıyı kopyala" title="Bağlantıyı kopyala">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" width="18" height="18"><path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"/><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"/></svg>
  </button>
</div>
<script>
(function(){
  var btn = document.querySelector('[data-share-copy]');
  if (!btn) return;
  btn.addEventListener('click', function () {
    var url = btn.getAttribute('data-url');
    if (navigator.clipboard && url) {
      navigator.clipboard.writeText(url).then(function () {
        if (window.toast) window.toast.success('Bağlantı kopyalandı');
      });
    }
  });
})();
</script>
