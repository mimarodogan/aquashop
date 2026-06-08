<?php
// Ortam ayarları — APP_ENV sabiti config/db.php (→ .env) tarafından tanımlanır
if (!defined('DB_HOST')) {
    require_once APP_ROOT . '/config/db.php';
}

// O-2 GÜVENLİK: display_errors ortama duyarlı. Üretimde error_log'a yazılır, ekrana yansımaz.
$__isDev = defined('APP_ENV') && APP_ENV === 'development';
ini_set('display_errors',         $__isDev ? '1' : '0');
ini_set('display_startup_errors', $__isDev ? '1' : '0');
ini_set('log_errors', '1');
error_reporting(E_ALL);
unset($__isDev);

function db() {
    static $pdo = null;
    if ($pdo === null) {
        $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET;
        $pdo = new PDO($dsn, DB_USER, DB_PASS, array(
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ));
    }
    return $pdo;
}
