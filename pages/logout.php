<?php
/**
 * Logout — Y-1 GÜVENLİK: tam temizlik
 *   - $_SESSION verisini boşalt
 *   - session cookie'yi silmek için Set-Cookie max-age 0
 *   - session_destroy + yeni session başlat + ID yenile
 *
 * Eski hâl SADECE session_destroy çağırıyordu; cookie tarayıcıda kalıyordu.
 */
require_once __DIR__ . '/../includes/functions.php';

// 1) Session verisini boşalt
$_SESSION = [];

// 2) Session cookie'yi tarayıcıdan sil
if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        [
            'expires'  => time() - 42000,
            'path'     => $params['path'],
            'domain'   => $params['domain'],
            'secure'   => $params['secure'],
            'httponly' => $params['httponly'],
            'samesite' => $params['samesite'] ?? 'Lax',
        ]
    );
}

// 3) Session'ı kalıcı olarak yok et
session_destroy();

// 4) Yeni temiz session başlat → yeni CSRF token üretilsin (eski oturum kalıntısı sıfır)
session_start();
session_regenerate_id(true);

redirect(url('home'));
