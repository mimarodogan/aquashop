<?php
/**
 * SMS Gönderim Soyutlaması.
 *
 * Birden fazla sağlayıcıyı destekler — admin Settings'ten seçilir:
 *   - netgsm     (NetGSM HTTP API)
 *   - iletimerkezi (İletiMerkezi v1 API)
 *
 * Kullanım (basit):
 *   sms_send('+905551234567', 'Siparişiniz hazırlanıyor: #1234');
 *
 * Şablonlu kullanım:
 *   sms_send_template($phone, 'order_confirm', ['order_id' => 1234, 'total' => '450,00 ₺']);
 *
 * Notlar:
 *  - Tüm gönderimler `sms_log` tablosuna yazılır (provider, status, error).
 *  - Telefon E.164 (+90...) ya da yerel format (05...) — adapter normalize eder.
 *  - sms_enabled = '0' veya provider boşsa hiçbir şey yapılmaz (no-op).
 *  - Mesaj 160 karakter limitine sığacak şekilde kısaltılmalı (UTF-8 → 70 karakter Türkçe).
 *    Bu sınıf KISALTMAZ — şablon yazarken dikkat edin.
 */

/* ────────────────────────────────────────────────────────────────── */

if (!function_exists('sms_enabled')) {
    function sms_enabled(): bool {
        return setting('sms_enabled', '0') === '1'
            && trim((string)setting('sms_provider', '')) !== '';
    }
}

if (!function_exists('sms_normalize_phone')) {
    /**
     * Türk telefonunu 905XXXXXXXXX formatına normalize et.
     * - "+90 555 123 45 67" → "905551234567"
     * - "05551234567"        → "905551234567"
     * - "5551234567"          → "905551234567"
     */
    function sms_normalize_phone(string $phone): ?string {
        $d = preg_replace('/\D+/', '', $phone);
        if ($d === '') return null;
        // Başında 90 yoksa ekle
        if (strlen($d) === 10 && $d[0] === '5') {
            $d = '90' . $d;
        } elseif (strlen($d) === 11 && substr($d, 0, 2) === '05') {
            $d = '90' . substr($d, 1);
        }
        // 12 hane ve 905 ile başlamalı
        if (strlen($d) !== 12 || substr($d, 0, 3) !== '905') return null;
        return $d;
    }
}

if (!function_exists('sms_log_insert')) {
    function sms_log_insert(string $to, string $message, string $provider, string $status, ?string $error = null, ?string $template = null): void {
        try {
            $st = db()->prepare(
                "INSERT INTO sms_log (recipient, message, provider, template, status, error_message)
                 VALUES (?, ?, ?, ?, ?, ?)"
            );
            $st->execute([$to, $message, $provider, $template, $status, $error]);
        } catch (\Throwable $e) {
            error_log('[sms_log] insert failed: ' . $e->getMessage());
        }
    }
}

/* ────────────────────────────────────────────────────────────────── */

if (!function_exists('sms_send')) {
    function sms_send(string $phone, string $message, ?string $template = null): bool {
        if (!sms_enabled()) return false;

        $to = sms_normalize_phone($phone);
        if (!$to) {
            sms_log_insert($phone, $message, setting('sms_provider',''), 'failure', 'invalid_phone', $template);
            return false;
        }

        $provider = setting('sms_provider', '');
        $ok = false; $err = null;
        try {
            switch ($provider) {
                case 'netgsm':       [$ok, $err] = sms_provider_netgsm($to, $message); break;
                case 'iletimerkezi': [$ok, $err] = sms_provider_iletimerkezi($to, $message); break;
                default:
                    $err = 'unknown_provider';
            }
        } catch (\Throwable $e) {
            $ok = false;
            $err = $e->getMessage();
        }

        sms_log_insert($to, $message, $provider, $ok ? 'success' : 'failure', $err, $template);
        return $ok;
    }
}

if (!function_exists('sms_provider_netgsm')) {
    /**
     * NetGSM HTTP API — get/sms/send/get
     * Doc: https://www.netgsm.com.tr/dokuman/
     * Yanıt başında "00" / "01" / "02" gibi kodlar — 0X ile başlayanlar başarı.
     */
    function sms_provider_netgsm(string $to, string $message): array {
        $user   = trim((string)setting('sms_user', ''));
        $pass   = trim((string)setting('sms_pass', ''));
        $sender = trim((string)setting('sms_sender', ''));
        if ($user === '' || $pass === '' || $sender === '') {
            return [false, 'missing_credentials'];
        }

        // NetGSM /sms/send/get
        $params = [
            'usercode' => $user,
            'password' => $pass,
            'gsmno'    => $to,
            'message'  => $message,
            'msgheader'=> $sender,
            'dil'      => 'TR',
        ];
        $url = 'https://api.netgsm.com.tr/sms/send/get?' . http_build_query($params);

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 8,
            CURLOPT_CONNECTTIMEOUT => 4,
        ]);
        $resp = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $cerr = curl_error($ch);
        curl_close($ch);

        if ($cerr) return [false, 'curl: ' . $cerr];
        if ($code !== 200) return [false, 'http_' . $code];
        // Başarı: "00 <jobid>" veya "01 <jobid>" gibi başlar
        $first = substr(trim((string)$resp), 0, 2);
        if (in_array($first, ['00', '01', '02'], true)) return [true, null];
        return [false, 'netgsm_code: ' . substr((string)$resp, 0, 60)];
    }
}

if (!function_exists('sms_provider_iletimerkezi')) {
    /**
     * İletiMerkezi v1 API — XML POST.
     * Doc: https://www.iletimerkezi.com/api/sms-gonderme-api
     */
    function sms_provider_iletimerkezi(string $to, string $message): array {
        $user   = trim((string)setting('sms_user', ''));
        $pass   = trim((string)setting('sms_pass', ''));
        $sender = trim((string)setting('sms_sender', ''));
        if ($user === '' || $pass === '' || $sender === '') {
            return [false, 'missing_credentials'];
        }

        $xml = '<?xml version="1.0" encoding="UTF-8"?>'
             . '<request>'
             .   '<authentication><username>' . htmlspecialchars($user, ENT_XML1) . '</username><password>' . htmlspecialchars($pass, ENT_XML1) . '</password></authentication>'
             .   '<order>'
             .     '<sender>' . htmlspecialchars($sender, ENT_XML1) . '</sender>'
             .     '<sendDateTime></sendDateTime>'
             .     '<iys>1</iys><iysList>BIREYSEL</iysList>'
             .     '<message><text>' . htmlspecialchars($message, ENT_XML1) . '</text><receipents><number>' . $to . '</number></receipents></message>'
             .   '</order>'
             . '</request>';

        $ch = curl_init('https://api.iletimerkezi.com/v1/send-sms');
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $xml,
            CURLOPT_HTTPHEADER     => ['Content-Type: application/xml'],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 8,
            CURLOPT_CONNECTTIMEOUT => 4,
        ]);
        $resp = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($code !== 200) return [false, 'http_' . $code];
        if (strpos((string)$resp, '<code>200</code>') !== false) return [true, null];
        return [false, 'iletimerkezi: ' . substr((string)$resp, 0, 120)];
    }
}

/* ────────────────────────────────────────────────────────────────── */

if (!function_exists('sms_send_template')) {
    /**
     * Şablonlu SMS gönderimi — placeholder doldurur.
     * Kullanım:
     *   sms_send_template('+905...', 'order_confirm', ['order_id'=>1234, 'total'=>'450,00 ₺']);
     *
     * Şablon settings'ten okunur: sms_tpl_<name>
     * Bulunamazsa fallback default kullanır.
     */
    function sms_send_template(string $phone, string $templateName, array $vars = []): bool {
        $fallbacks = [
            'order_confirm'  => 'Sayin {ad}, {magaza}: #{order_id} no\'lu siparisiniz alindi. Toplam: {total}. Tesekkurler.',
            'order_shipped'  => '{magaza}: #{order_id} no\'lu siparisiniz kargoya verildi. Takip: {tracking}',
            'order_delivered'=> '{magaza}: #{order_id} no\'lu siparisiniz teslim edildi. Iyi gunler dileriz.',
            'password_reset' => '{magaza} sifre sifirlama kodunuz: {code}. Sure: 15 dk.',
            'birthday'       => 'Sayin {ad}, dogum gununuz kutlu olsun! Size ozel %15 kupon: {coupon}',
        ];
        $tpl = trim((string)setting('sms_tpl_' . $templateName, ''));
        if ($tpl === '') $tpl = $fallbacks[$templateName] ?? '';
        if ($tpl === '') return false;

        // Mağaza adı vars'ta yoksa otomatik ekle
        if (!isset($vars['magaza'])) $vars['magaza'] = setting('site_name', '');

        $message = $tpl;
        foreach ($vars as $k => $v) {
            $message = str_replace('{' . $k . '}', (string)$v, $message);
        }

        return sms_send($phone, $message, $templateName);
    }
}
