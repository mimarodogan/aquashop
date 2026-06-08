<?php
/**
 * Hafif rate-limit helper — session + IP bazlı.
 *
 * Veritabanı veya APCu gerektirmez. Kullanıcı tarayıcı session'ı kaybederse
 * sıfırlanır (bu kabul edilebilir bir maliyet — bot engellemek için yeterli).
 */

/**
 * Verilen anahtar için son $window saniyede $maxAttempts'i geçtiyse true döner.
 */
function rate_limit_exceeded(string $key, int $maxAttempts, int $window): bool {
    $sk = '_rl_' . $key;
    $now = time();
    $bucket = $_SESSION[$sk] ?? ['count' => 0, 'reset' => $now + $window];

    if ($now >= $bucket['reset']) {
        // Pencere bitti, sıfırla
        $bucket = ['count' => 0, 'reset' => $now + $window];
    }

    if ($bucket['count'] >= $maxAttempts) {
        $_SESSION[$sk] = $bucket;
        return true;
    }

    $bucket['count']++;
    $_SESSION[$sk] = $bucket;
    return false;
}

/**
 * Bir anahtarı sıfırla (örn. başarılı login sonrası attempt counter'ı sil).
 */
function rate_limit_reset(string $key): void {
    unset($_SESSION['_rl_' . $key]);
}

/**
 * Kullanıcı IP'sini al. (Y-5 — XFF spoofing koruması)
 *
 * Güvenlik: X-Forwarded-For / CF-Connecting-IP / X-Real-IP header'ları
 * SADECE REMOTE_ADDR güvenilir proxy listesinde olduğunda kabul edilir.
 * Trusted proxy listesi yoksa, sadece REMOTE_ADDR kullanılır (en sıkı mod).
 *
 * config/.env içinde TRUSTED_PROXIES virgülle ayrılmış IP/CIDR olarak tanımlanır.
 */
function client_ip(): string {
    $remote = $_SERVER['REMOTE_ADDR'] ?? '';
    if ($remote === '' || !filter_var($remote, FILTER_VALIDATE_IP)) return '0.0.0.0';

    // Trusted proxy listesi
    $trusted = defined('TRUSTED_PROXIES') ? trim((string)TRUSTED_PROXIES) : '';
    $isFromTrustedProxy = false;
    if ($trusted !== '') {
        foreach (array_filter(array_map('trim', explode(',', $trusted))) as $entry) {
            if (_ip_matches_cidr($remote, $entry)) { $isFromTrustedProxy = true; break; }
        }
    }

    // Trusted proxy ARKASINDA isek header'lara güven; aksi halde sadece REMOTE_ADDR
    if ($isFromTrustedProxy) {
        $headers = ['HTTP_CF_CONNECTING_IP', 'HTTP_X_REAL_IP', 'HTTP_X_FORWARDED_FOR'];
        foreach ($headers as $h) {
            if (!empty($_SERVER[$h])) {
                $ip = trim(explode(',', $_SERVER[$h])[0]);
                if (filter_var($ip, FILTER_VALIDATE_IP)) return $ip;
            }
        }
    }
    return $remote;
}

/**
 * IP, "192.168.1.5" veya CIDR "10.0.0.0/8" formatında entry ile eşleşiyor mu?
 */
function _ip_matches_cidr(string $ip, string $entry): bool {
    if (strpos($entry, '/') === false) {
        return $ip === $entry;
    }
    list($subnet, $bits) = explode('/', $entry, 2);
    $bits = (int)$bits;
    if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) || !filter_var($subnet, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
        return false; // basit IPv4 desteği; IPv6 trusted proxy gerekirse genişletilir
    }
    $ipLong     = ip2long($ip);
    $subnetLong = ip2long($subnet);
    $mask       = $bits === 0 ? 0 : (~0 << (32 - $bits)) & 0xFFFFFFFF;
    return ($ipLong & $mask) === ($subnetLong & $mask);
}
