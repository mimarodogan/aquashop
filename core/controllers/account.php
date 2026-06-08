<?php
/**
 * Hesabım sayfası — controller.
 * Profil, e-posta, adres ve şifre güncelleme POST handler'ları burada.
 */
require_once __DIR__ . '/../../includes/functions.php';

$title = 'Hesabım'; $page = '';
$user = current_user();
if (!$user) redirect(url('login'));

$err = null; $ok = null;

if ($_SERVER['REQUEST_METHOD']==='POST' && csrf_check(isset($_POST['csrf'])?$_POST['csrf']:null)) {
    $action = isset($_POST['action']) ? $_POST['action'] : '';

    if ($action === 'profile') {
        $first = trim(isset($_POST['first_name'])?$_POST['first_name']:'');
        $last  = trim(isset($_POST['last_name']) ?$_POST['last_name'] :'');
        $name  = trim($first.' '.$last);
        $phone = trim(isset($_POST['phone'])?$_POST['phone']:'');
        // NOT: Adres artık "Adreslerim" defterinden yönetiliyor; profil formunda
        // adres alanı yok. users.address mevcut değeri korunur (ezilmez).

        /* ── Doğum tarihi: yalnız bir kez set edilebilir ───────────────
         * Aksi halde kullanıcı her ay/gün değiştirip BDAY kuponu üretebilir.
         * Mevcut değer doluysa POST'tan gelen değeri yok say. */
        if (!empty($user['birth_date'])) {
            $birth = $user['birth_date']; // değiştirilemez
        } else {
            $birth = trim(isset($_POST['birth_date'])?$_POST['birth_date']:'') ?: null;
            // Geçerli tarih mi? (yyyy-mm-dd formatı + makul yaş)
            if ($birth) {
                if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $birth)) {
                    $birth = null;
                } else {
                    $ts = strtotime($birth);
                    if (!$ts || $ts > time() || $ts < strtotime('-110 years')) {
                        $birth = null;
                    }
                }
            }
        }

        $emailC= !empty($_POST['email_consent'])?1:0;
        $smsC  = !empty($_POST['sms_consent'])?1:0;
        if ($name === '') $name = $user['name'];
        db()->prepare('UPDATE users SET name=?,first_name=?,last_name=?,phone=?,birth_date=?,email_consent=?,sms_consent=? WHERE id=?')
            ->execute(array($name,$first,$last,$phone,$birth,$emailC,$smsC,$user['id']));
        flash_set('success','Profiliniz güncellendi.');
        redirect(url('account'));
    }

    if ($action === 'email') {
        $newEmail = trim(isset($_POST['email'])?$_POST['email']:'');
        $pw       = isset($_POST['current_password'])?$_POST['current_password']:'';
        if (!$newEmail || !password_verify($pw, $user['password'])) {
            $err = 'E-postayı değiştirmek için mevcut şifrenizi doğru girin.';
        } else {
            try {
                db()->prepare('UPDATE users SET email=? WHERE id=?')->execute(array($newEmail, $user['id']));
                flash_set('success','E-posta adresi güncellendi.');
                redirect(url('account'));
            } catch (PDOException $e) {
                $err = (isset($e->errorInfo[1]) && $e->errorInfo[1]==1062) ? 'Bu e-posta başka bir kullanıcıda kayıtlı.' : 'Güncelleme başarısız.';
            }
        }
    }

    if ($action === 'address_add' || $action === 'address_update') {
        $addrId = (int)($_POST['address_id'] ?? 0);
        $label  = trim($_POST['label'] ?? 'Ev') ?: 'Ev';
        $fname  = trim($_POST['full_name'] ?? '');
        $ph     = trim($_POST['phone'] ?? '');
        $line   = trim($_POST['address_line'] ?? '');
        $cit    = trim($_POST['city'] ?? '');
        $dist   = trim($_POST['district'] ?? '');
        $zip    = trim($_POST['zip'] ?? '');
        $isDef  = !empty($_POST['is_default']) ? 1 : 0;
        if (!$fname || !$ph || !$line || !$cit) {
            flash_set('err','Ad Soyad, telefon, adres ve şehir zorunludur.');
        } else {
            if ($isDef) db()->prepare('UPDATE user_addresses SET is_default=0 WHERE user_id=?')->execute([$user['id']]);
            if ($action === 'address_update' && $addrId) {
                db()->prepare('UPDATE user_addresses SET label=?,full_name=?,phone=?,address=?,city=?,district=?,zip=?,is_default=? WHERE id=? AND user_id=?')
                    ->execute([$label,$fname,$ph,$line,$cit,$dist ?: null,$zip ?: null,$isDef,$addrId,$user['id']]);
                flash_set('success','Adres güncellendi.');
            } else {
                db()->prepare('INSERT INTO user_addresses (user_id,label,full_name,phone,address,city,district,zip,is_default) VALUES (?,?,?,?,?,?,?,?,?)')
                    ->execute([$user['id'],$label,$fname,$ph,$line,$cit,$dist ?: null,$zip ?: null,$isDef]);
                flash_set('success','Yeni adres eklendi.');
            }
            redirect(url('account') . '#adresler');
        }
    }
    if ($action === 'address_delete') {
        db()->prepare('DELETE FROM user_addresses WHERE id=? AND user_id=?')
            ->execute([(int)($_POST['address_id'] ?? 0), $user['id']]);
        flash_set('success','Adres silindi.');
        redirect(url('account') . '#adresler');
    }
    if ($action === 'address_set_default') {
        $aid = (int)($_POST['address_id'] ?? 0);
        db()->prepare('UPDATE user_addresses SET is_default=0 WHERE user_id=?')->execute([$user['id']]);
        db()->prepare('UPDATE user_addresses SET is_default=1 WHERE id=? AND user_id=?')->execute([$aid, $user['id']]);
        flash_set('success','Varsayılan adres ayarlandı.');
        redirect(url('account') . '#adresler');
    }

    if ($action === 'password') {
        $cur = isset($_POST['current_password']) ? $_POST['current_password'] : '';
        $new = isset($_POST['new_password']) ? $_POST['new_password'] : '';
        $rep = isset($_POST['repeat_password']) ? $_POST['repeat_password'] : '';
        if (!password_verify($cur, $user['password'])) $err = 'Mevcut şifre hatalı.';
        elseif (strlen($new) < 6)  $err = 'Yeni şifre en az 6 karakter olmalı.';
        elseif ($new !== $rep)     $err = 'Yeni şifreler eşleşmiyor.';
        else {
            db()->prepare('UPDATE users SET password=? WHERE id=?')
                ->execute(array(password_hash($new, PASSWORD_DEFAULT), $user['id']));
            flash_set('success','Şifreniz güncellendi.');
            redirect(url('account'));
        }
    }
}

$orders = db()->prepare("SELECT * FROM orders WHERE user_id=? OR email=? ORDER BY created_at DESC");
$orders->execute(array($user['id'],$user['email']));
$orders = $orders->fetchAll();

$addresses = [];
try {
    $st = db()->prepare("SELECT * FROM user_addresses WHERE user_id=? ORDER BY is_default DESC, id DESC");
    $st->execute([$user['id']]);
    $addresses = $st->fetchAll();
} catch (\Throwable $e) {}

$editAddrId = (int)($_GET['edit_address'] ?? 0);
$editAddress = null;
if ($editAddrId) {
    foreach ($addresses as $a) if ((int)$a['id']===$editAddrId) { $editAddress = $a; break; }
}

