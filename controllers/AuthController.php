<?php
require_once __DIR__ . '/../core/bootstrap.php';

$action = $_POST['action'] ?? $_GET['action'] ?? '';

if ($action === 'logout') {
    session_destroy();
    redirect(SITE_URL . '/index.php');
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !csrf_check($_POST['csrf'] ?? null)) {
    redirect(SITE_URL . '/login.php');
}

if ($action === 'login') {
    $u = user_find_by_email(trim($_POST['email'] ?? ''));
    if ($u && user_verify($_POST['password'] ?? '', $u['password'])) {
        $_SESSION['user_id'] = $u['id'];
        flash_set('success','Hoş geldiniz, '.$u['name']);
        redirect($u['role']==='admin' ? SITE_URL.'/admin_panel/index.php' : SITE_URL.'/account.php');
    }
    flash_set('err','E-posta veya şifre hatalı.');
    redirect(SITE_URL . '/login.php');
}

if ($action === 'register') {
    $first = trim($_POST['first_name'] ?? '');
    $last  = trim($_POST['last_name']  ?? '');
    $email = trim($_POST['email']      ?? '');
    $pw    = $_POST['password']        ?? '';
    if (!$first || !$last || !$email || strlen($pw) < 6 || empty($_POST['terms'])) {
        flash_set('err','Lütfen tüm alanları doldurun ve koşulları kabul edin.');
        redirect(SITE_URL . '/register.php');
    }
    try {
        $id = user_create(array(
            'name'=>$first.' '.$last,'first_name'=>$first,'last_name'=>$last,
            'email'=>$email,'password'=>$pw,
            'phone'=>trim($_POST['phone'] ?? ''),
            'birth_date'=>!empty($_POST['birth_date']) ? $_POST['birth_date'] : null,
            'email_consent'=>!empty($_POST['email_consent']),
            'sms_consent'=>!empty($_POST['sms_consent']),
            'role'=>'customer'
        ));
        $_SESSION['user_id'] = $id;
        flash_set('success','Hesabınız oluşturuldu.');
        redirect(SITE_URL . '/account.php');
    } catch (PDOException $e) {
        flash_set('err', (isset($e->errorInfo[1]) && $e->errorInfo[1]==1062) ? 'Bu e-posta zaten kayıtlı.' : 'Kayıt başarısız.');
        redirect(SITE_URL . '/register.php');
    }
}
redirect(SITE_URL . '/login.php');
