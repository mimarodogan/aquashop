<?php
function e($s) { return htmlspecialchars($s === null ? '' : $s, ENT_QUOTES, 'UTF-8'); }
function money($v) { return number_format((float)$v, 2, ',', '.') . ' ₺'; }
function redirect($path) {
    // Normal durum: header ile 302 yönlendirme.
    if (!headers_sent()) {
        header('Location: ' . $path);
        exit;
    }
    // Çıktı zaten başladıysa (ör. admin sayfalarında layout dahil edildikten sonra
    // POST işlenip redirect çağrılıyor) header() "headers already sent" UYARISI verir;
    // strict set_error_handler bunu ErrorException'a çevirip "Bir hata oluştu" sayfası
    // gösterirdi. Bu durumda JS/meta ile güvenli yönlendir.
    echo '<script>window.location.replace(' . json_encode((string)$path) . ');</script>'
       . '<noscript><meta http-equiv="refresh" content="0;url=' . htmlspecialchars((string)$path, ENT_QUOTES) . '"></noscript>';
    exit;
}

/**
 * Pretty URL üretici. Linkleri tek noktadan yönetir.
 *   url('home')                          → /
 *   url('product', ['slug'=>'abc'])      → /products/abc
 *   url('blog_post', ['slug'=>'xyz'])    → /blog/xyz
 *   url('products', ['cat'=>'kampanya']) → /products/category/kampanya
 *   url('page', ['slug'=>'kvkk'])        → /page/kvkk
 *   url('order', ['id'=>42])             → /order/42
 */
function url($name, $params = array()) {
    $base = defined('SITE_URL') ? rtrim(SITE_URL, '/') : '';
    $p = $params;
    switch ($name) {
        case 'home':       return $base . '/';
        case 'products':
            if (!empty($p['cat'])) return $base . '/urun/' . rawurlencode($p['cat']);
            return $base . '/urun';
        case 'product':    return $base . '/urun/' . rawurlencode($p['slug'] ?? '');
        case 'categories_list':
        case 'categories': return $base . '/kategoriler';
        case 'category':   return $base . '/kategoriler/' . rawurlencode($p['slug'] ?? '');
        case 'blog':
            if (!empty($p['cat'])) return $base . '/blog/' . rawurlencode($p['cat']);
            return $base . '/blog';
        case 'blog_post':  return $base . '/blog/' . rawurlencode($p['slug'] ?? '');
        case 'page':       return $base . '/sayfa/' . rawurlencode($p['slug'] ?? '');
        case 'order':      return $base . '/odeme/' . (int)($p['id'] ?? 0);
        case 'cart':       return $base . '/sepet';
        case 'checkout':   return $base . '/odeme';
        case 'login':      return $base . '/giris';
        case 'register':   return $base . '/uye-ol';
        case 'logout':     return $base . '/cikis';
        case 'account':    return $base . '/hesabim';
        case 'favorites':  return $base . '/favoriler';
        case 'compare':    return $base . '/karsilastir';
        case 'contact':    return $base . '/iletisim';
        case 'about':      return $base . '/hakkimizda';
        case 'forgot-password':
        case 'forgot':     return $base . '/sifremi-unuttum';
        case 'reset-password':
        case 'reset':      return $base . '/sifre-sifirla' . (!empty($p['token']) ? '?token='.urlencode($p['token']) : '');
        case 'return':     return $base . '/iade-talebi' . (!empty($p['order']) ? '?order=' . (int)$p['order'] : '');
        case 'admin':      return $base . '/admin';
        case 'unsubscribe': return $base . '/aboneligi-iptal-et' . (!empty($p['email']) ? '?email='.rawurlencode($p['email']).'&token='.rawurlencode($p['token'] ?? '') : '');
    }
    return $base . '/';
}

function status_label($s) {
    $map = array('pending'=>'Beklemede','paid'=>'Ödendi','shipped'=>'Kargoda','delivered'=>'Teslim Edildi','cancelled'=>'İptal Edildi');
    return isset($map[$s]) ? $map[$s] : $s;
}
function role_label($r) {
    $map = array('customer'=>'Müşteri','admin'=>'Yönetici');
    return isset($map[$r]) ? $map[$r] : $r;
}
function payment_label($p) {
    $map = array('havale'=>'Havale / EFT','kapida'=>'Kapıda Ödeme','kart'=>'Kredi Kartı');
    return isset($map[$p]) ? $map[$p] : $p;
}
function status_options() {
    return array('pending'=>'Beklemede','paid'=>'Ödendi','shipped'=>'Kargoda','delivered'=>'Teslim Edildi','cancelled'=>'İptal Edildi');
}

/* Kargo firmaları */
function carriers() {
    return array(
        'yurtici'   => array('label'=>'Yurtiçi Kargo',     'url'=>'https://www.yurticikargo.com/tr/online-servisler/gonderi-sorgula?code={n}'),
        'aras'      => array('label'=>'Aras Kargo',         'url'=>'https://kargotakip.araskargo.com.tr/?code={n}'),
        'mng'       => array('label'=>'MNG Kargo',          'url'=>'https://kargotakip.mngkargo.com.tr/?takipNo={n}'),
        'ptt'       => array('label'=>'PTT Kargo',          'url'=>'https://gonderitakip.ptt.gov.tr/Track/summary?q={n}'),
        'surat'     => array('label'=>'Sürat Kargo',        'url'=>'https://www.suratkargo.com.tr/KargoTakip/?kargotakipno={n}'),
        'ups'       => array('label'=>'UPS Kargo',          'url'=>'https://www.ups.com/track?tracknum={n}'),
        'hepsijet'  => array('label'=>'HepsiJet',           'url'=>'https://hepsijet.com/gonderi-takibi/{n}'),
        'trendyol'  => array('label'=>'Trendyol Express',   'url'=>'https://trendyolexpress.com/?trackingNumber={n}'),
        'kolaygelsin'=>array('label'=>'Kolay Gelsin',       'url'=>'https://www.kolaygelsin.com/Tracking?Number={n}'),
        'fedex'     => array('label'=>'FedEx',              'url'=>'https://www.fedex.com/fedextrack/?tracknumbers={n}'),
        'dhl'       => array('label'=>'DHL',                'url'=>'https://www.dhlecommerce.com.tr/gonderitakip'),
        'other'     => array('label'=>'Diğer',              'url'=>''),
    );
}
function carrier_label($key) { $c = carriers(); return isset($c[$key]) ? $c[$key]['label'] : $key; }
function tracking_url($carrier, $number) {
    $c = carriers();
    if (!isset($c[$carrier]) || !$c[$carrier]['url'] || !$number) return null;
    return str_replace('{n}', urlencode($number), $c[$carrier]['url']);
}

/**
 * İçerikteki YouTube/Vimeo bağlantılarını duyarlı iframe'e dönüştürür.
 * Editörde yanlışlıkla "image" butonuyla yapıştırılmış watch URL'leri,
 * çıplak metin URL'leri ve eski youtube.com/embed iframe'leri yakalar.
 */
function embed_videos($html) {
    if (!$html) return $html;

    // 1) Eski youtube.com/embed → youtube-nocookie.com/embed
    $html = str_replace('youtube.com/embed/', 'youtube-nocookie.com/embed/', $html);

    // 2) <img src="https://www.youtube.com/watch?v=ID..."> → iframe
    $html = preg_replace_callback(
        '~<img[^>]*\bsrc=["\'](?:https?:)?//(?:www\.)?(?:youtube\.com/watch\?v=|youtu\.be/|youtube\.com/shorts/)([\w-]{11})[^"\']*["\'][^>]*>~i',
        function ($m) {
            return '<figure class="video-embed"><iframe width="640" height="360" src="https://www.youtube-nocookie.com/embed/' . $m[1] . '" title="YouTube video" frameborder="0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share" allowfullscreen loading="lazy"></iframe></figure>';
        },
        $html
    );

    // 3) <img src="https://vimeo.com/ID"> → iframe
    $html = preg_replace_callback(
        '~<img[^>]*\bsrc=["\'](?:https?:)?//(?:www\.)?vimeo\.com/(\d+)[^"\']*["\'][^>]*>~i',
        function ($m) {
            return '<figure class="video-embed"><iframe src="https://player.vimeo.com/video/' . $m[1] . '" width="640" height="360" frameborder="0" allow="autoplay; fullscreen; picture-in-picture" allowfullscreen loading="lazy"></iframe></figure>';
        },
        $html
    );

    // 4) Tek başına satırda duran YouTube/Vimeo URL'leri (paragraf içinde) → iframe
    $html = preg_replace_callback(
        '~(<p[^>]*>)\s*((?:https?:)?//(?:www\.)?(?:youtube\.com/watch\?v=|youtu\.be/|youtube\.com/shorts/)([\w-]{11})\S*)\s*(</p>)~i',
        function ($m) {
            return '<figure class="video-embed"><iframe width="640" height="360" src="https://www.youtube-nocookie.com/embed/' . $m[3] . '" title="YouTube video" frameborder="0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share" allowfullscreen loading="lazy"></iframe></figure>';
        },
        $html
    );

    return $html;
}

/**
 * sanitize_html — Admin tarafından girilmiş zengin HTML içeriği için XSS sterilize edici. (K-2, K-3, O-9)
 *
 * Kaldırır:
 *   - <script>, <style>, <iframe> (YouTube/Vimeo iframe'i embed_videos sonradan ekler)
 *   - on* event handler attribute'ları (onclick, onerror, onload, ...)
 *   - javascript:, vbscript:, data: (image/* hariç) protokol şemaları
 *   - <a href> içindeki tehlikeli scheme'leri
 *   - <meta http-equiv="refresh">, <object>, <embed>, <link>, <base>
 *
 * Kullanım: post.php / page.php gibi admin HTML basan yerlerde echo embed_videos(sanitize_html($content));
 */
function sanitize_html($html) {
    if (!is_string($html) || $html === '') return '';

    // 1) Tehlikeli blok elementleri tamamen sök
    $html = preg_replace('#<(script|style|object|embed|applet|meta|base|link)\b[^>]*>.*?</\1>#is', '', $html);
    $html = preg_replace('#<(script|style|object|embed|applet|meta|base|link|iframe)\b[^>]*/?>#i', '', $html);

    // 2) <iframe>'leri tamamen sök (embed_videos() YouTube/Vimeo iframe'lerini sonradan whitelist'le geri ekler)
    $html = preg_replace('#<iframe\b[^>]*>.*?</iframe>#is', '', $html);

    // 3) on* event handler attribute'larını sök (onclick=..., onerror=..., on{anything}=...)
    //    Hem çift hem tek tırnak hem tırnaksız hâli yakala
    $html = preg_replace('#\son[a-z]+\s*=\s*"[^"]*"#i', '', $html);
    $html = preg_replace("#\son[a-z]+\s*=\s*'[^']*'#i", '', $html);
    $html = preg_replace('#\son[a-z]+\s*=\s*[^\s>]+#i', '', $html);

    // 4) Tehlikeli URL şemaları (javascript:, vbscript:, data: text/html)
    $html = preg_replace('#(href|src|action|formaction|xlink:href)\s*=\s*(["\'])\s*(?:javascript|vbscript|livescript|mocha)\s*:[^"\']*\2#i', '$1=$2#$2', $html);
    // data: image dışındaki data URI'lerini engelle
    $html = preg_replace('#(href|src|action)\s*=\s*(["\'])\s*data\s*:\s*(?!image/)[^"\']*\2#i', '$1=$2#$2', $html);

    return $html;
}

/**
 * safe_back_url — Open redirect koruması. (Y-10)
 *
 * Kabul eder:
 *   - "/path/to/page" (kök göreceli)
 *   - "https://AYNI-host/..." (mutlak ama aynı host)
 *
 * Reddeder (ve $default döner):
 *   - "//evil.com/x" (protokol-relatif → tarayıcı evil.com'a gider)
 *   - "https://evil.com/..."
 *   - "javascript:...", "data:..."
 *   - boş string, null, non-string
 *   - null byte injection
 *
 * @param mixed  $input    Genelde $_POST['back'] veya $_SERVER['HTTP_REFERER']
 * @param string $default  Geçersiz girdide döndürülecek (varsayılan: anasayfa)
 * @return string Güvenli URL
 */
function safe_back_url($input, $default = null) {
    if ($default === null) $default = (defined('SITE_URL') && SITE_URL !== '') ? SITE_URL . '/' : '/';
    if (!is_string($input) || $input === '') return $default;

    // Null byte ve kontrol karakterlerini temizle
    $input = str_replace(["\0", "\r", "\n", "\t"], '', $input);
    if ($input === '') return $default;

    // Protokol-relatif // ile başlıyorsa reddet (tarayıcı dış host'a gider)
    if (strpos($input, '//') === 0) return $default;

    // Scheme var mı? Varsa sadece aynı host'a izin ver
    if (preg_match('~^[a-z][a-z0-9+\-.]*:~i', $input)) {
        $parsed = @parse_url($input);
        if (!is_array($parsed) || empty($parsed['scheme']) || empty($parsed['host'])) return $default;
        $scheme = strtolower($parsed['scheme']);
        if ($scheme !== 'http' && $scheme !== 'https') return $default;
        $ourHost = strtolower($_SERVER['HTTP_HOST'] ?? '');
        if (strtolower($parsed['host']) !== $ourHost) return $default;
        return $input;
    }

    // Kök göreceli yol olmalı
    if ($input[0] !== '/') return $default;

    return $input;
}

/**
 * Sabit-zamanda kullanmak için dummy bcrypt hash — O-4 (timing leak fix).
 * Kullanıcı bulunamadığında password_verify() bu hash'le çağrılır → timing eşitlenir.
 */
function dummy_password_hash(): string {
    // Statik bcrypt hash (pwd: "dummy-not-a-real-password")
    return '$2y$10$abcdefghijklmnopqrstuvOXjK1vGKZF.JqxN9q.7YqQNYqJ7tFmS';
}

/**
 * is_url_safe_for_fetch — SSRF koruması (Y-7). wp_importer ve harici fetch yapan kodlarda kullan.
 *
 * Kabul: http(s) scheme, public IP host
 * Reddet:
 *   - Non-http(s) scheme (file://, gopher://, ftp://, dict:// ...)
 *   - Private/local IP'ler: 10.0.0.0/8, 172.16.0.0/12, 192.168.0.0/16, 127.0.0.0/8
 *   - Link-local: 169.254.0.0/16 (AWS/GCP cloud metadata)
 *   - Loopback IPv6: ::1, fc00::/7
 *   - hostname yoksa
 */
function is_url_safe_for_fetch(string $url): bool {
    $p = @parse_url($url);
    if (!is_array($p) || empty($p['scheme']) || empty($p['host'])) return false;
    $scheme = strtolower($p['scheme']);
    if ($scheme !== 'http' && $scheme !== 'https') return false;

    $host = $p['host'];
    // Host'un IP olup olmadığını kontrol; değilse DNS'e sor
    $ips = [];
    if (filter_var($host, FILTER_VALIDATE_IP)) {
        $ips[] = $host;
    } else {
        // gethostbynamel hem v4 hem v6 dönmez — sadece v4. Ek güvenlik için dns_get_record de denenebilir.
        $rec = @gethostbynamel($host);
        if ($rec) $ips = $rec;
    }
    if (!$ips) return false;

    foreach ($ips as $ip) {
        // FILTER_FLAG_NO_PRIV_RANGE: 10/8, 172.16/12, 192.168/16
        // FILTER_FLAG_NO_RES_RANGE: 0/8, 127/8, 169.254/16, 192/24, 224/4, 240/4
        if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 | FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
            return false;
        }
    }
    return true;
}
