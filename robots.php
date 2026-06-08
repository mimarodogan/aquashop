<?php
/**
 * Dinamik robots.txt — hangi domain'de çalışıyorsa ona göre üretir.
 * .htaccess: RewriteRule ^robots\.txt$ robots.php [L]
 */
header('Content-Type: text/plain; charset=utf-8');
header('Cache-Control: public, max-age=86400'); // 1 gün cache

$scheme = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host   = preg_replace('/[^a-zA-Z0-9._\-]/', '', $_SERVER['HTTP_HOST'] ?? 'localhost');
$base   = $scheme . '://' . $host;

echo <<<ROBOTS
User-agent: *
Allow: /
Disallow: /admin_panel/
Disallow: /admin/
Disallow: /controllers/
Disallow: /core/
Disallow: /models/
Disallow: /includes/
Disallow: /config/
Disallow: /sql/
Disallow: /sepet
Disallow: /odeme
Disallow: /hesabim
Disallow: /favoriler
Disallow: /giris
Disallow: /uye-ol
Disallow: /aboneligi-iptal-et
Disallow: /favorite-toggle.php
Disallow: /comment-add.php
Disallow: /newsletter-subscribe.php

User-agent: GPTBot
Allow: /
Disallow: /admin_panel/

User-agent: ClaudeBot
Allow: /
Disallow: /admin_panel/

User-agent: Google-Extended
Allow: /
Disallow: /admin_panel/

User-agent: PerplexityBot
Allow: /

User-agent: ChatGPT-User
Allow: /
Disallow: /admin_panel/

User-agent: Bytespider
Disallow: /

User-agent: CCBot
Disallow: /

Sitemap: {$base}/sitemap.xml

ROBOTS;
