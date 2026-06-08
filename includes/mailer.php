<?php
/**
 * Posta gönderici — admin panelden SMTP ayarlarıyla.
 * Saf PHP (fsockopen) — harici kütüphane yok.
 */
require_once __DIR__ . '/functions.php';

if (!function_exists('mail_send')) {
function mail_send(string $to, string $subject, string $htmlBody, string $textBody = ''): bool {
    $cfg = [
        'host'   => trim((string)setting('smtp_host', '')),
        'port'   => max(1, (int)setting('smtp_port', '587')),
        'user'   => trim((string)setting('smtp_user', '')),
        'pass'   => (string)setting('smtp_pass', ''),
        'secure' => strtolower((string)setting('smtp_secure', 'tls')), // tls | ssl | none
        'from_email' => trim((string)setting('smtp_from_email', '')) ?: trim((string)setting('contact_email', '')),
        'from_name'  => trim((string)setting('smtp_from_name','')) ?: trim((string)setting('site_name','')),
    ];

    if ($cfg['host'] === '' || $cfg['user'] === '' || $cfg['from_email'] === '') {
        error_log('[mailer] SMTP yapılandırılmamış');
        return false;
    }

    if ($textBody === '') {
        $textBody = trim(strip_tags(preg_replace('~<br\s*/?>~i', "\n", $htmlBody)));
    }

    $boundary = '=_b' . bin2hex(random_bytes(8));
    $fromHeader = $cfg['from_name'] !== ''
        ? mb_encode_mimeheader($cfg['from_name']) . ' <' . $cfg['from_email'] . '>'
        : $cfg['from_email'];

    $headers  = "From: $fromHeader\r\n";
    $headers .= "To: $to\r\n";
    $headers .= "Subject: =?UTF-8?B?" . base64_encode($subject) . "?=\r\n";
    $headers .= "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: multipart/alternative; boundary=\"$boundary\"\r\n";
    $headers .= "Date: " . date('r') . "\r\n";
    $headers .= "Message-ID: <" . bin2hex(random_bytes(8)) . '@' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . ">\r\n";

    // quoted-printable: uzun satırları otomatik 76 karakterle sarar (RFC 5321 uyumu)
    $body  = "--{$boundary}\r\n";
    $body .= "Content-Type: text/plain; charset=UTF-8\r\n";
    $body .= "Content-Transfer-Encoding: quoted-printable\r\n\r\n";
    $body .= quoted_printable_encode($textBody) . "\r\n\r\n";
    $body .= "--{$boundary}\r\n";
    $body .= "Content-Type: text/html; charset=UTF-8\r\n";
    $body .= "Content-Transfer-Encoding: quoted-printable\r\n\r\n";
    $body .= quoted_printable_encode($htmlBody) . "\r\n\r\n";
    $body .= "--{$boundary}--\r\n";

    return smtp_send_raw($cfg, $cfg['from_email'], $to, $headers . "\r\n" . $body);
}}

if (!function_exists('smtp_send_raw')) {
function smtp_send_raw(array $cfg, string $from, string $to, string $message): bool {
    $host = $cfg['host'];
    $port = (int)$cfg['port'];
    $secure = $cfg['secure'];

    $remote = ($secure === 'ssl' ? 'ssl://' : '') . $host;
    $errno = 0; $errstr = '';
    $fp = @stream_socket_client("$remote:$port", $errno, $errstr, 15);
    if (!$fp) {
        error_log("[smtp] connect: $errstr ($errno)");
        return false;
    }
    stream_set_timeout($fp, 15);

    $read = function() use ($fp) {
        $data = '';
        while (($line = fgets($fp, 515)) !== false) {
            $data .= $line;
            if (isset($line[3]) && $line[3] === ' ') break;
        }
        return $data;
    };
    $send = function($cmd) use ($fp, $read) {
        fwrite($fp, $cmd . "\r\n");
        return $read();
    };

    $banner = $read();
    if (substr($banner, 0, 3) !== '220') { fclose($fp); error_log('[smtp] banner: '.$banner); return false; }

    $ehloHost = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $resp = $send('EHLO ' . $ehloHost);
    if (substr($resp, 0, 3) !== '250') { fclose($fp); error_log('[smtp] ehlo1: '.$resp); return false; }

    if ($secure === 'tls') {
        $resp = $send('STARTTLS');
        if (substr($resp, 0, 3) !== '220') { fclose($fp); error_log('[smtp] starttls: '.$resp); return false; }
        if (!@stream_socket_enable_crypto($fp, true, STREAM_CRYPTO_METHOD_TLS_CLIENT
                | STREAM_CRYPTO_METHOD_TLSv1_1_CLIENT
                | STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT)) {
            fclose($fp); error_log('[smtp] tls handshake failed'); return false;
        }
        $resp = $send('EHLO ' . $ehloHost);
        if (substr($resp, 0, 3) !== '250') { fclose($fp); error_log('[smtp] ehlo2: '.$resp); return false; }
    }

    // AUTH LOGIN
    $resp = $send('AUTH LOGIN');
    if (substr($resp, 0, 3) !== '334') { fclose($fp); error_log('[smtp] auth: '.$resp); return false; }
    $resp = $send(base64_encode($cfg['user']));
    if (substr($resp, 0, 3) !== '334') { fclose($fp); error_log('[smtp] user: '.$resp); return false; }
    $resp = $send(base64_encode($cfg['pass']));
    if (substr($resp, 0, 3) !== '235') { fclose($fp); error_log('[smtp] pass: '.$resp); return false; }

    // Envelope
    $resp = $send('MAIL FROM:<' . $from . '>');
    if (substr($resp, 0, 3) !== '250') { fclose($fp); error_log('[smtp] mailfrom: '.$resp); return false; }

    // Birden çok alıcı destek (virgülle ayrılmış)
    $rcpts = array_filter(array_map('trim', preg_split('/[,;]+/', $to)));
    foreach ($rcpts as $r) {
        $resp = $send('RCPT TO:<' . $r . '>');
        if (!in_array(substr($resp, 0, 3), ['250','251'], true)) { fclose($fp); error_log('[smtp] rcpt: '.$resp); return false; }
    }

    $resp = $send('DATA');
    if (substr($resp, 0, 3) !== '354') { fclose($fp); error_log('[smtp] data: '.$resp); return false; }

    // Nokta ile başlayan satırları kaçır
    $message = preg_replace('/^\./m', '..', $message);
    fwrite($fp, $message . "\r\n.\r\n");
    $resp = $read();
    if (substr($resp, 0, 3) !== '250') { fclose($fp); error_log('[smtp] body: '.$resp); return false; }

    $send('QUIT');
    fclose($fp);
    return true;
}}

/**
 * DB'den mail şablonunu çeker; yoksa $default_body kullanır.
 * Değişken yerleştirme: ['{{isim}}' => 'Ali', ...] gibi map ver.
 * Dönüş: ['subject' => '...', 'body_html' => '...'] array'i.
 */
if (!function_exists('mail_template_get')) {
function mail_template_get(string $key, array $vars = [], string $default_subject = '', string $default_body = ''): array {
    $subject  = $default_subject;
    $bodyHtml = $default_body;
    try {
        $st = db()->prepare('SELECT subject, body_html FROM mail_templates WHERE `key`=? LIMIT 1');
        $st->execute([$key]);
        $row = $st->fetch();
        if ($row) {
            $subject  = $row['subject'];
            $bodyHtml = $row['body_html'];
        }
    } catch (Exception $e) { /* tablo yoksa varsayılanı kullan */ }

    // Genel değişkenler her şablona enjekte edilir
    $siteName = (string)setting('site_name', 'AquaShop');
    $defaults = [
        '{{site_adi}}'   => $siteName,
        '{{site_url}}'   => defined('SITE_URL') ? SITE_URL : '',
    ];
    $allVars = array_merge($defaults, $vars);

    $subject  = str_replace(array_keys($allVars), array_values($allVars), $subject);
    $bodyHtml = str_replace(array_keys($allVars), array_values($allVars), $bodyHtml);

    return ['subject' => $subject, 'body_html' => $bodyHtml];
}}

if (!function_exists('mail_template')) {
function mail_template(string $title, string $bodyHtml, ?string $ctaLabel = null, ?string $ctaUrl = null): string {
    $siteName = e((string)setting('site_name','AquaShop'));
    $cta = '';
    if ($ctaLabel && $ctaUrl) {
        // Bulletproof button (Outlook + Gmail + Apple Mail uyumlu) + altında çıplak URL
        $url = e($ctaUrl);
        $cta = '<table cellpadding="0" cellspacing="0" border="0" role="presentation" style="margin:28px 0">'
            . '<tr><td align="center" bgcolor="#1A1A1A" style="background:#1A1A1A;border-radius:6px">'
            . '<a href="' . $url . '" target="_blank" rel="noopener" '
            .   'style="display:inline-block;padding:16px 32px;background:#1A1A1A;color:#FFFFFF;'
            .   'font-family:Arial,Helvetica,sans-serif;font-size:14px;font-weight:600;letter-spacing:.12em;'
            .   'text-transform:uppercase;text-decoration:none;border-radius:6px;mso-padding-alt:0">'
            . '<!--[if mso]>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<![endif]-->'
            . e($ctaLabel)
            . '<!--[if mso]>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<![endif]-->'
            . '</a>'
            . '</td></tr></table>'
            // Yedek çıplak URL — buton tıklanmazsa kopyala-yapıştır
            . '<p style="margin:14px 0 0;font-size:13px;color:#5F5F5F;line-height:1.6;word-break:break-all">'
            . 'Buton çalışmazsa aşağıdaki bağlantıyı kopyalayıp tarayıcınıza yapıştırın:<br>'
            . '<a href="' . $url . '" style="color:#4F5C26;text-decoration:underline;font-family:monospace;font-size:12px">' . $url . '</a>'
            . '</p>';
    }
    return '<!DOCTYPE html><html lang="tr"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>' . e($title) . '</title></head>'
        . '<body style="margin:0;padding:0;background:#F9F9F9;font-family:Arial,Helvetica,sans-serif;color:#1A1A1A">'
        . '<table width="100%" cellpadding="0" cellspacing="0" border="0" role="presentation" style="background:#F9F9F9;padding:32px 16px">'
        . '<tr><td align="center">'
        . '<table width="600" cellpadding="0" cellspacing="0" border="0" role="presentation" style="max-width:600px;background:#FFFFFF;border:1px solid #E8E8E8;border-radius:10px">'
        . '<tr><td style="padding:32px;border-bottom:1px solid #E8E8E8">'
        . '<div style="font-family:Georgia,serif;font-size:24px;color:#1A1A1A;font-weight:600;letter-spacing:.04em">' . $siteName . '</div>'
        . '</td></tr>'
        . '<tr><td style="padding:36px 32px">'
        . '<h1 style="margin:0 0 18px;font-family:Georgia,serif;font-size:26px;color:#1A1A1A;font-weight:500">' . e($title) . '</h1>'
        . '<div style="font-size:15px;line-height:1.7;color:#333">' . $bodyHtml . '</div>'
        . $cta
        . '</td></tr>'
        . '<tr><td style="padding:24px 32px;background:#F4F4F4;border-top:1px solid #E8E8E8;font-size:12px;color:#5F5F5F;line-height:1.6;border-radius:0 0 10px 10px">'
        . 'Bu otomatik bir e-postadır. Soru için <a href="mailto:' . e((string)setting('contact_email','')) . '" style="color:#4F5C26">'
        . e((string)setting('contact_email','iletisim')) . '</a> üzerinden bize ulaşabilirsiniz.'
        . '</td></tr></table></td></tr></table></body></html>';
}}
