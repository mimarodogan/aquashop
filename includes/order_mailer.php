<?php
/**
 * Sipariş onayı + bildirim mailleri.
 */
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/mailer.php';

if (!function_exists('order_send_confirmation')) {
function order_send_confirmation(int $orderId): bool {
    $st = db()->prepare('SELECT * FROM orders WHERE id=?');
    $st->execute([$orderId]);
    $o = $st->fetch();
    if (!$o || empty($o['email'])) return false;

    $items = db()->prepare('SELECT product_name, qty, price FROM order_items WHERE order_id=?');
    $items->execute([$orderId]);
    $rows = $items->fetchAll();

    // Toplam ile alt toplamı ayır (KDV gösterimi için)
    $vatRate = (float)setting('vat_rate', '20'); // % cinsinden
    $total = (float)$o['total'];
    $subtotal = $vatRate > 0 ? round($total / (1 + $vatRate/100), 2) : $total;
    $vat = round($total - $subtotal, 2);

    $rowsHtml = '';
    foreach ($rows as $r) {
        $line = (float)$r['qty'] * (float)$r['price'];
        $rowsHtml .= '<tr>'
            . '<td style="padding:10px;border-bottom:1px solid #E8E8E8">' . e($r['product_name']) . ' <span style="color:#5F5F5F">× ' . (int)$r['qty'] . '</span></td>'
            . '<td style="padding:10px;border-bottom:1px solid #E8E8E8;text-align:right;font-family:monospace">' . money($line) . '</td>'
            . '</tr>';
    }
    $rowsHtml .= '<tr><td style="padding:10px;text-align:right;color:#5F5F5F">Ara Toplam</td><td style="padding:10px;text-align:right;font-family:monospace">' . money($subtotal) . '</td></tr>';
    if ($vat > 0) {
        $rowsHtml .= '<tr><td style="padding:10px;text-align:right;color:#5F5F5F">KDV (%' . (int)$vatRate . ')</td><td style="padding:10px;text-align:right;font-family:monospace">' . money($vat) . '</td></tr>';
    }
    $rowsHtml .= '<tr><td style="padding:10px;text-align:right;font-weight:600;color:#0A0A0A;border-top:2px solid #1A1A1A">Toplam</td><td style="padding:10px;text-align:right;font-family:monospace;font-weight:600;color:#0A0A0A;border-top:2px solid #1A1A1A">' . money($total) . '</td></tr>';

    $payLabel = ['havale'=>'Havale / EFT','kart'=>'Kredi Kartı (iyzico)','kapida'=>'Kapıda Ödeme'][$o['payment_method']] ?? $o['payment_method'];
    $isHavale = $o['payment_method'] === 'havale';

    $body  = '<p>Merhaba <strong>' . e($o['full_name']) . '</strong>,</p>';
    $body .= '<p>Siparişinizi aldık. Aşağıda sipariş özetiniz yer alıyor.</p>';
    $body .= '<table style="width:100%;border-collapse:collapse;margin:20px 0">' . $rowsHtml . '</table>';
    $body .= '<p style="font-size:13px;color:#5F5F5F"><strong>Sipariş No:</strong> #' . (int)$orderId . '<br>';
    $body .= '<strong>Ödeme Yöntemi:</strong> ' . e($payLabel) . '<br>';
    $body .= '<strong>Teslimat Adresi:</strong><br>' . nl2br(e($o['address'])) . '<br>' . e($o['city']) . '</p>';

    if ($isHavale) {
        $iban = trim((string)setting('bank_iban',''));
        $bankName = trim((string)setting('bank_name',''));
        $accHolder = trim((string)setting('bank_account_holder', setting('site_name','')));
        if ($iban) {
            $body .= '<div style="margin:24px 0;padding:18px;border:1px solid #E8E8E8;border-radius:8px;background:#F9F9F9">'
                  . '<h3 style="margin:0 0 10px;font-family:Georgia,serif;font-size:18px">Havale / EFT Bilgileri</h3>'
                  . ($bankName ? '<p style="margin:4px 0"><strong>Banka:</strong> ' . e($bankName) . '</p>' : '')
                  . ($accHolder ? '<p style="margin:4px 0"><strong>Hesap Sahibi:</strong> ' . e($accHolder) . '</p>' : '')
                  . '<p style="margin:4px 0"><strong>IBAN:</strong> <code>' . e($iban) . '</code></p>'
                  . '<p style="margin:4px 0"><strong>Açıklama:</strong> Sipariş #' . (int)$orderId . '</p>'
                  . '<p style="margin:10px 0 0;font-size:13px;color:#5F5F5F">Ödeme tarafımıza ulaştığında siparişiniz hazırlanmaya başlar. 3 iş günü içinde ödeme yapılmazsa sipariş iptal edilir.</p>'
                  . '</div>';
        }
    }

    // Cayma hakkı bilgilendirmesi (yasal — TKHK md. 48)
    $body .= '<div style="margin:24px 0;padding:14px;border-left:3px solid #6B7A2F;background:#F4F4F4;font-size:13px;color:#333">'
           . '<strong>Cayma Hakkı:</strong> Teslim aldığınız tarihten itibaren <strong>14 gün</strong> içinde herhangi bir gerekçe göstermeksizin ve cezai şart ödemeksizin sözleşmeden cayma hakkına sahipsiniz. Detaylar için <a href="' . e(rtrim((string)setting('site_url',''),'/')) . '/sayfa/iade-degisim" style="color:#4F5C26">İade & Değişim sayfası</a>.'
           . '</div>';

    // Sipariş takip linki
    $base = trim((string)setting('site_url','')) ?: ('https://' . ($_SERVER['HTTP_HOST'] ?? 'localhost'));
    $orderUrl = rtrim($base,'/') . '/odeme/' . (int)$orderId;

    $html = mail_template('Siparişiniz Alındı · #' . (int)$orderId, $body, 'Siparişimi Görüntüle', $orderUrl);

    $text = "Merhaba " . $o['full_name'] . ",\n\nSiparişiniz alındı. Sipariş No: #" . $orderId . "\n";
    foreach ($rows as $r) {
        $text .= "- " . $r['product_name'] . " × " . $r['qty'] . " = " . money((float)$r['qty']*(float)$r['price']) . "\n";
    }
    $text .= "\nAra Toplam: " . money($subtotal) . "\n";
    if ($vat > 0) $text .= "KDV (%" . (int)$vatRate . "): " . money($vat) . "\n";
    $text .= "Toplam: " . money($total) . "\n\nSipariş takibi: " . $orderUrl . "\n";

    $mailOk = mail_send($o['email'], 'Siparişiniz Alındı · #' . (int)$orderId, $html, $text);

    // SMS bildirimi (opsiyonel, sms_enabled = '1' ve şablon varsa gönderir)
    if (!empty($o['phone']) && function_exists('sms_send_template')) {
        @sms_send_template((string)$o['phone'], 'order_confirm', [
            'ad'       => $o['full_name'] ?: 'Müşterimiz',
            'order_id' => (int)$orderId,
            'total'    => money($total),
        ]);
    }

    return $mailOk;
}}

if (!function_exists('order_send_shipped_notification')) {
function order_send_shipped_notification(int $orderId): bool {
    $st = db()->prepare('SELECT * FROM orders WHERE id=?');
    $st->execute([$orderId]);
    $o = $st->fetch();
    if (!$o) return false;
    if (function_exists('sms_send_template') && !empty($o['phone'])) {
        $tu = function_exists('tracking_url') ? tracking_url($o['tracking_carrier'] ?? '', $o['tracking_number'] ?? '') : '';
        @sms_send_template((string)$o['phone'], 'order_shipped', [
            'ad'       => $o['full_name'] ?: 'Müşterimiz',
            'order_id' => (int)$orderId,
            'tracking' => $o['tracking_number'] ?? '',
            'url'      => $tu,
        ]);
    }
    return true;
}}

if (!function_exists('order_send_delivered_notification')) {
function order_send_delivered_notification(int $orderId): bool {
    $st = db()->prepare('SELECT * FROM orders WHERE id=?');
    $st->execute([$orderId]);
    $o = $st->fetch();
    if (!$o) return false;
    if (function_exists('sms_send_template') && !empty($o['phone'])) {
        @sms_send_template((string)$o['phone'], 'order_delivered', [
            'ad'       => $o['full_name'] ?: 'Müşterimiz',
            'order_id' => (int)$orderId,
        ]);
    }
    return true;
}}
