<?php
/**
 * Meta (Facebook) Conversions API — server-side gönderim.
 *
 * Amaç: tarayıcı taraflı Pixel'in iOS14+ / adblocker / cookie reddi nedeniyle
 * kaybolan dönüşüm event'lerini sunucudan tekrar göndererek tamamla.
 * Deduplication: `event_id` Pixel ile aynı olduğu için Meta tarafında çift sayım olmaz.
 *
 * Kullanım:
 *   meta_capi_send_purchase($orderRow, $orderItems);
 *
 * Ayarlar (admin_panel/settings.php → Analitik & Ölçüm):
 *   - meta_pixel_id
 *   - meta_capi_token
 *   - meta_capi_test_event_code (opsiyonel, sadece test sırasında)
 *   - meta_capi_enabled (master switch)
 *
 * Notlar:
 *  - PII hash'lenir (SHA-256 lowercase trim) — Meta uyumluluk gereği
 *  - Hatalar error_log'a yazılır, kullanıcı akışını bloklamaz (suppressed)
 *  - cURL kullanır (PHP'de standart, ek paket gerekmez)
 */

if (!function_exists('meta_capi_enabled')) {
    function meta_capi_enabled(): bool {
        return setting('meta_capi_enabled', '0') === '1'
            && trim((string)setting('meta_pixel_id', '')) !== ''
            && trim((string)setting('meta_capi_token', '')) !== '';
    }
}

if (!function_exists('meta_capi_hash')) {
    function meta_capi_hash(?string $value): ?string {
        if ($value === null || $value === '') return null;
        return hash('sha256', strtolower(trim($value)));
    }
}

if (!function_exists('meta_capi_send_event')) {
    /**
     * Tek bir event gönder (purchase, AddToCart, vb.).
     * $eventName: Meta standart adı ("Purchase", "AddToCart", "InitiateCheckout", "ViewContent", "Lead")
     * $eventId:   Pixel ile dedupe için aynı olmalı (örn. "order_42" veya UUID)
     * $userData:  ['email','phone','first_name','last_name','city','zip','country','external_id']
     * $customData:['value','currency','contents','content_ids','content_type','num_items']
     */
    function meta_capi_send_event(string $eventName, string $eventId, array $userData = [], array $customData = []): bool {
        if (!meta_capi_enabled()) return false;

        $pixelId  = trim((string)setting('meta_pixel_id', ''));
        $token    = trim((string)setting('meta_capi_token', ''));
        $testCode = trim((string)setting('meta_capi_test_event_code', ''));

        $hashedUser = array_filter([
            'em'          => meta_capi_hash($userData['email']      ?? null),
            'ph'          => meta_capi_hash(preg_replace('/\D+/', '', (string)($userData['phone'] ?? ''))),
            'fn'          => meta_capi_hash($userData['first_name'] ?? null),
            'ln'          => meta_capi_hash($userData['last_name']  ?? null),
            'ct'          => meta_capi_hash($userData['city']       ?? null),
            'zp'          => meta_capi_hash($userData['zip']        ?? null),
            'country'     => meta_capi_hash($userData['country']    ?? 'tr'),
            'external_id' => meta_capi_hash($userData['external_id']?? null),
            'client_ip_address' => $_SERVER['REMOTE_ADDR']     ?? null,
            'client_user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null,
            'fbp'         => $_COOKIE['_fbp'] ?? null,
            'fbc'         => $_COOKIE['_fbc'] ?? null,
        ], static fn($v) => $v !== null && $v !== '');

        $event = [
            'event_name'   => $eventName,
            'event_time'   => time(),
            'event_id'     => $eventId,
            'event_source_url' => (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https' : 'http')
                              . '://' . ($_SERVER['HTTP_HOST'] ?? '') . ($_SERVER['REQUEST_URI'] ?? '/'),
            'action_source'=> 'website',
            'user_data'    => $hashedUser,
            'custom_data'  => $customData,
        ];

        $payload = [ 'data' => [ $event ] ];
        if ($testCode !== '') $payload['test_event_code'] = $testCode;

        $url = "https://graph.facebook.com/v18.0/{$pixelId}/events?access_token=" . rawurlencode($token);

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 5,
            CURLOPT_CONNECTTIMEOUT => 3,
        ]);
        $resp = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err  = curl_error($ch);
        curl_close($ch);

        if ($code < 200 || $code >= 300) {
            error_log("[meta_capi] {$eventName} failed: HTTP {$code} — {$err} — " . substr((string)$resp, 0, 500));
            return false;
        }
        return true;
    }
}

if (!function_exists('meta_capi_send_purchase')) {
    /**
     * Sipariş onaylandığında çağrılır.
     * $order: orders tablosu satırı
     * $items: order_items tablosu satırları
     */
    function meta_capi_send_purchase(array $order, array $items): bool {
        if (!meta_capi_enabled()) return false;

        // Ad/soyad ayır
        $nameParts = explode(' ', trim((string)($order['full_name'] ?? '')), 2);
        $first = $nameParts[0] ?? '';
        $last  = $nameParts[1] ?? '';

        $contents = [];
        foreach ($items as $it) {
            $contents[] = [
                'id'         => (string)($it['product_id'] ?? ''),
                'quantity'   => (int)($it['qty'] ?? 1),
                'item_price' => round((float)($it['price'] ?? 0), 2),
            ];
        }
        $contentIds = array_column($contents, 'id');

        return meta_capi_send_event('Purchase', 'order_' . (int)$order['id'], [
            'email'       => $order['email'] ?? null,
            'phone'       => $order['phone'] ?? null,
            'first_name'  => $first,
            'last_name'   => $last,
            'city'        => $order['city']  ?? null,
            'country'     => 'tr',
            'external_id' => isset($order['user_id']) && $order['user_id'] ? 'u_' . (int)$order['user_id'] : null,
        ], [
            'value'        => round((float)$order['total'], 2),
            'currency'     => 'TRY',
            'content_type' => 'product',
            'contents'     => $contents,
            'content_ids'  => $contentIds,
            'num_items'    => array_sum(array_column($contents, 'quantity')),
            'order_id'     => (string)$order['id'],
        ]);
    }
}
