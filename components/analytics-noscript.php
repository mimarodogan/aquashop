<?php
/**
 * Analytics — body içi noscript fallback'leri.
 * GTM noscript iframe'i body'nin hemen başına yerleştirilmeli (Google önerisi).
 */
if (setting('analytics_enabled','0') !== '1') return;

$ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
if (stripos($ua, 'Chrome-Lighthouse') !== false
 || stripos($ua, 'PageSpeed') !== false
 || stripos($ua, 'Headless') !== false) {
    return;
}

$gtm = trim((string)setting('gtm_container_id', ''));
if ($gtm === '') return;
?>
<noscript><iframe src="https://www.googletagmanager.com/ns.html?id=<?= e($gtm) ?>"
height="0" width="0" style="display:none;visibility:hidden" title="GTM"></iframe></noscript>
