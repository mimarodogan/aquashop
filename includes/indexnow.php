<?php
/**
 * IndexNow — Bing, Yandex ve diğer destekleyen arama motorlarına
 * içerik değişikliklerini anında bildirir (Google hariç, Google kendi
 * Googlebot'unu kullanır).
 *
 * Kullanım:
 *   indexnow_ping(['https://aquashop.com.tr/urun/slug']);
 *   indexnow_ping(['https://aquashop.com.tr/blog/slug']);
 *
 * Belgeleme: https://www.indexnow.org/documentation
 */

if (!defined('INDEXNOW_KEY')) {
    define('INDEXNOW_KEY', '54ff74aed8453cbe0668832dfb52b813');
}

/**
 * Belirtilen URL listesini IndexNow API'sine gönderir.
 *
 * @param  string[] $urls  Tam URL dizisi (https:// ile başlamalı)
 * @return bool            Başarılıysa true
 */
function indexnow_ping(array $urls): bool
{
    if (empty($urls)) return false;

    // Sadece HTTPS URL'lerini gönder
    $urls = array_values(array_filter($urls, fn($u) => str_starts_with($u, 'https://')));
    if (empty($urls)) return false;

    $host = parse_url($urls[0], PHP_URL_HOST) ?: '';
    if ($host === '') return false;

    $payload = json_encode([
        'host'        => $host,
        'key'         => INDEXNOW_KEY,
        'keyLocation' => 'https://' . $host . '/' . INDEXNOW_KEY . '.txt',
        'urlList'     => $urls,
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

    $ctx = stream_context_create([
        'http' => [
            'method'        => 'POST',
            'header'        => "Content-Type: application/json; charset=utf-8\r\nContent-Length: " . strlen($payload),
            'content'       => $payload,
            'timeout'       => 3,      // Admini 3 saniyeden fazla beklettirme
            'ignore_errors' => true,
        ],
        'ssl' => ['verify_peer' => true],
    ]);

    try {
        $resp = @file_get_contents('https://api.indexnow.org/IndexNow', false, $ctx);
        // 200 OK veya 202 Accepted başarı sayılır
        $code = 0;
        if (!empty($http_response_header)) {
            if (preg_match('/HTTP\/\S+\s+(\d+)/', $http_response_header[0], $m)) {
                $code = (int)$m[1];
            }
        }
        return ($code >= 200 && $code < 300);
    } catch (\Throwable $e) {
        return false;
    }
}
