<?php
/**
 * ÖRNEK veritabanı yapılandırması.
 *
 * KURULUM:
 *   1) Bu dosyayı 'config/db.php' olarak kopyalayın.
 *   2) Proje kökünde '.env' oluşturun (bkz. .env.example) ve gerçek
 *      veritabanı bilgilerinizi oraya yazın.
 *   3) config/db.php otomatik olarak .env'i okur.
 *
 * GÜVENLİK:
 *   - Gerçek 'config/db.php' ve '.env' dosyaları .gitignore ile repo dışındadır.
 *   - Bu örnek dosyaya ASLA gerçek şifre yazmayın.
 */

if (!defined('APP_ROOT')) define('APP_ROOT', dirname(__DIR__));

$__envFile = APP_ROOT . '/.env';
$__env = [];
if (is_file($__envFile) && is_readable($__envFile)) {
    $__env = @parse_ini_file($__envFile, false, INI_SCANNER_RAW);
    if ($__env === false) $__env = [];
}

define('DB_HOST',    $__env['DB_HOST']    ?? '127.0.0.1');
define('DB_NAME',    $__env['DB_NAME']    ?? 'your_db_name');
define('DB_USER',    $__env['DB_USER']    ?? 'your_db_user');
define('DB_PASS',    $__env['DB_PASS']    ?? '');   // .env'den okunur — burada gerçek şifre TUTMAYIN
define('DB_CHARSET', $__env['DB_CHARSET'] ?? 'utf8mb4');

// Ortam: production | development
define('APP_ENV',         $__env['APP_ENV']         ?? 'production');
define('TRUSTED_PROXIES', $__env['TRUSTED_PROXIES'] ?? '');

unset($__envFile, $__env);

define('SITE_URL', '');
define('SITE_NAME_FALLBACK', 'E-Ticaret');
