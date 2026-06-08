<?php
/**
 * Google Fonts CSS'ini sunucu tarafında cache'leyen yardımcı.
 *
 * Avantajları:
 *  - PageSpeed "Minify CSS" uyarısı: Google Fonts CSS'i indent'li gelir; biz
 *    minified inline ederek bu uyarıyı tamamen ortadan kaldırırız.
 *  - DNS lookup + TLS handshake'i atlatır (FCP iyileşir).
 *  - 30 günlük cache: Google Fonts'ta yeni font versiyonu olursa kendiliğinden yenilenir.
 *
 * Kullanım (header.php içinde):
 *   <style><?= google_fonts_inline('https://fonts.googleapis.com/css2?family=...') ?></style>
 *
 * NOT: font-face URL'leri hâlâ fonts.gstatic.com'a gider — preconnect korunmalı.
 */

function google_fonts_inline(string $url): string {
    $cacheDir  = APP_ROOT . '/uploads/.cache';
    $cacheFile = $cacheDir . '/gfonts_' . md5($url) . '.css';

    // 30 günden eski değilse cache'den dön
    if (is_file($cacheFile) && (time() - filemtime($cacheFile)) < 30 * 86400) {
        return (string) file_get_contents($cacheFile);
    }

    // Cache yoksa veya eskiyse Google'dan çek
    @mkdir($cacheDir, 0755, true);

    // PageSpeed/Chrome user-agent kullan (Google'ın WOFF2 göndermesi için)
    $ctx = stream_context_create([
        'http' => [
            'timeout'    => 5,
            'user_agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120 Safari/537.36',
            'header'     => "Accept: text/css\r\n",
        ],
    ]);
    $css = @file_get_contents($url, false, $ctx);

    if ($css === false || strlen($css) < 50) {
        // Fetch başarısız: eski cache varsa onu kullan, yoksa boş dön (graceful)
        return is_file($cacheFile) ? (string) file_get_contents($cacheFile) : '';
    }

    // Minify
    $css = preg_replace('~/\*[\s\S]*?\*/~', '', $css);
    $css = preg_replace('/\s+/', ' ', $css);
    $css = preg_replace('/\s*([{};:,>+~()])\s*/', '$1', $css);
    $css = str_replace(';}', '}', $css);
    $css = trim($css);

    @file_put_contents($cacheFile, $css, LOCK_EX);
    return $css;
}
