<?php
/**
 * Y-6 GÜVENLİK: Admin auth zorunlu.
 * Eski hâl public idi → herkes opcache_reset() çağırıp DoS yapabiliyordu.
 *
 * Kullanım: admin paneline giriş yaptıktan sonra
 *   https://ornek-site.test/opcache-clear.php
 */
require_once __DIR__ . '/core/bootstrap.php';

$u = current_user();
if (!$u || ($u['role'] ?? '') !== 'admin') {
    http_response_code(403);
    echo 'Yetkisiz.';
    exit;
}

if (function_exists('opcache_reset')) {
    opcache_reset();
    echo '<b style="color:green">OPcache temizlendi.</b><br><a href="' . htmlspecialchars(url('admin'), ENT_QUOTES) . '">← Admin paneline dön</a>';
} else {
    echo '<b style="color:orange">opcache_reset() bu sunucuda mevcut değil.</b>';
}
