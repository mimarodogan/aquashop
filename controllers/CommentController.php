<?php
require_once __DIR__ . '/../core/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !csrf_check($_POST['csrf'] ?? null)) {
    redirect(SITE_URL . '/index.php');
}
if (!current_user()) {
    flash_set('err','Yorum yapmak için giriş yapmalısınız.');
    redirect(SITE_URL . '/login.php');
}
$type = $_POST['type'] ?? '';
$tid  = (int)($_POST['target_id'] ?? 0);
$body = $_POST['body'] ?? '';
$rating = $_POST['rating'] ?? null;
// Y-10 GÜVENLİK: same-host whitelist
$back = safe_back_url($_POST['back'] ?? '', url('home'));

if (!in_array($type, array('product','blog'), true) || $tid <= 0) {
    redirect(url('home'));
}
if (comment_add($type, $tid, $body, $rating)) flash_set('success','Yorumunuz gönderildi.');
else                                          flash_set('err','Yorum gönderilemedi.');
redirect($back);
