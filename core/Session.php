<?php
/**
 * Session başlatıcı — güvenli cookie parametreleri ve fixation koruması.
 */
if (session_status() === PHP_SESSION_NONE) {
    $secure = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
           || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');

    session_set_cookie_params([
        'lifetime' => 0,        // tarayıcı kapanınca silinsin
        'path'     => '/',
        'domain'   => '',
        'secure'   => $secure,  // sadece HTTPS üzerinden gönder
        'httponly' => true,     // JS erişemesin (XSS koruması)
        // Ödeme sağlayıcısı (iyzico) çapraz-site POST ile geri döndüğünde oturum çerezinin
        // gönderilebilmesi için HTTPS'te SameSite=None gerekir (None, Secure zorunludur).
        // Aksi halde kullanıcı ödeme dönüşünde "çıkış yapılmış" görünür ve login'e atılır.
        // HTTP'de (yerel/geliştirme) Lax'a düşeriz. CSRF korumasını zaten csrf token sağlar.
        'samesite' => $secure ? 'None' : 'Lax',
    ]);

    // Tahmin edilebilir PHPSESSID yerine özel ad
    session_name('STORE_SID');
    session_start();

    // Session fixation koruması: ilk istekte ID yenile
    if (empty($_SESSION['_initialized'])) {
        session_regenerate_id(true);
        $_SESSION['_initialized'] = 1;
        $_SESSION['_started_at']  = time();
    }

    // Periyodik yenile (24 saatte bir) — uzun süreli oturum hijack riski azalır
    if (!empty($_SESSION['_started_at']) && (time() - (int)$_SESSION['_started_at']) > 86400) {
        session_regenerate_id(true);
        $_SESSION['_started_at'] = time();
    }
}
