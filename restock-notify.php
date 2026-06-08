<?php
require_once __DIR__ . '/includes/functions.php';

$ok = false;
$err = null;

if ($_SERVER['REQUEST_METHOD']==='POST' && csrf_check($_POST['csrf'] ?? null)) {
    // D-3 GÜVENLİK: IP başına 1 dakikada 5 istek limiti (spam + e-mail enumeration sömürüsü için)
    if (rate_limit_exceeded('restock_' . md5(client_ip()), 5, 60)) {
        flash_set('err', 'Çok hızlı denedi. Lütfen 1 dakika sonra tekrar deneyin.');
        $ref = safe_back_url($_SERVER['HTTP_REFERER'] ?? '', url('home'));
        redirect($ref);
        exit;
    }

    $pid = (int)($_POST['product_id'] ?? 0);
    $email = trim((string)($_POST['email'] ?? ''));
    if ($pid <= 0) {
        $err = 'Geçersiz ürün.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $err = 'Geçerli bir e-posta girin.';
    } else {
        try {
            // Aynı email-product için pending kayıt varsa eklemeye gerek yok
            $st = db()->prepare('SELECT id FROM restock_notifications WHERE product_id=? AND email=? AND notified_at IS NULL LIMIT 1');
            $st->execute([$pid, $email]);
            $isNew = false;
            if (!$st->fetch()) {
                db()->prepare('INSERT INTO restock_notifications (product_id, email) VALUES (?,?)')->execute([$pid, $email]);
                $isNew = true;
            }
            $ok = true;

            // Misafir kullanıcıya onay maili gönder (üye girişliyse gerek yok)
            $loggedIn = current_user();
            if ($isNew && !$loggedIn) {
                try {
                    require_once __DIR__ . '/includes/mailer.php';
                    // Ürün adını çek
                    $pSt = db()->prepare('SELECT name FROM products WHERE id=? LIMIT 1');
                    $pSt->execute([$pid]);
                    $pName = (string)($pSt->fetchColumn() ?: 'Ürün');

                    $tmpl = mail_template_get('restock_confirm',
                        ['{{urun_adi}}' => $pName],
                        'Stok bildirimi kaydınız alındı — ' . $pName,
                        '<p>Merhaba,</p>'
                        . '<p><strong>' . htmlspecialchars($pName, ENT_QUOTES) . '</strong> için stok bildirimi talebiniz alındı.</p>'
                        . '<p>Ürün stoğa girdiğinde bu adrese e-posta göndereceğiz.</p>'
                    );
                    $body = mail_template($tmpl['subject'], $tmpl['body_html']);
                    mail_send($email, $tmpl['subject'], $body);
                } catch (Exception $e) { /* mail isteğe bağlı */ }
            }
        } catch (\Throwable $e) {
            $err = 'Kayıt sırasında bir sorun oluştu.';
        }
    }
}

// Y-10 GÜVENLİK: open redirect — same-host whitelist
$ref = safe_back_url($_SERVER['HTTP_REFERER'] ?? '', url('home'));
if ($ok) flash_set('success', 'Stoka geldiğinde e-posta ile bildirim alacaksınız.');
elseif ($err) flash_set('err', $err);
redirect($ref);
